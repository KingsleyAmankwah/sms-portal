<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/app-config.php';
require_once __DIR__ . '/../core/extensions/classes.php';
require_once __DIR__ . '/../core/exceptions.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\Validator;
use SMSPortalExceptions\SMSPortalException;
use SMSPortalExtensions\SMSClient;

header('Content-Type: application/json; charset=utf-8');

class UserManager
{
    private $conn;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Strict admin access control
        if (!isset($_SESSION['USER_ID']) || $_SESSION['USER_ROLE'] !== 'admin') {
            throw SMSPortalException::unauthorizedAccess();
        }

        $this->conn = MySQLDatabase::createConnection();
        if ($this->conn === false) {
            throw SMSPortalException::databaseError('Failed to connect to database');
        }
    }

    public function process()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw SMSPortalException::invalidRequest();
            }

            if (!Authentication::validateToken($_POST['csrf_token'] ?? '')) {
                throw SMSPortalException::invalidToken();
            }

            $action = $_POST['action'] ?? '';
            switch ($action) {
                case 'fetch':
                    return $this->fetchUsers();
                case 'create':
                    return $this->createUser();
                case 'update':
                    return $this->updateUser();
                case 'update_status':
                    return $this->updateUserStatus();
                case 'reset_password':
                    return $this->resetPassword();
                case 'delete':
                    return $this->deleteUser();
                default:
                    throw SMSPortalException::invalidAction();
            }
        } catch (Exception $e) {
            $this->customLog("Process error: " . $e->getMessage());
            return $this->sendError($e->getMessage());
        } finally {
            if ($this->conn) {
                $this->conn->close();
            }
        }
    }

    private function fetchUsers()
    {
        $page = max(1, (int)($_POST['page'] ?? 1));
        $itemsPerPage = (int)($_POST['per_page'] ?? 10);
        $search = trim($_POST['search'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $role = trim($_POST['role'] ?? '');

        $conditions = ['status != "deleted"'];
        $params = [''];

        if (!empty($search)) {
            $conditions[] = '(username LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params[0] .= 'sss';
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($status)) {
            $conditions[] = 'status = ?';
            $params[0] .= 's';
            $params[] = $status;
        }

        if (!empty($role)) {
            $conditions[] = 'type = ?';
            $params[0] .= 's';
            $params[] = $role;
        }

        $whereClause = implode(' AND ', $conditions);

        $countQuery = "SELECT COUNT(*) as total FROM users WHERE $whereClause";
        $result = MySQLDatabase::sqlSelect($this->conn, $countQuery, $params[0], ...array_slice($params, 1));

        if (!$result) {
            throw SMSPortalException::databaseError('Failed to count users');
        }

        $totalUsers = $result->fetch_assoc()['total'];
        $result->free_result();

        $totalPages = ceil($totalUsers / $itemsPerPage);
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $itemsPerPage;

        $query = "SELECT
                    id, username, email, phone, status, type,
                    created_at, last_login
                 FROM users
                 WHERE $whereClause
                 ORDER BY created_at DESC
                 LIMIT ? OFFSET ?";

        $params[0] .= 'ii';
        $params[] = $itemsPerPage;
        $params[] = $offset;

        $result = MySQLDatabase::sqlSelect($this->conn, $query, $params[0], ...array_slice($params, 1));

        if (!$result) {
            throw SMSPortalException::databaseError('Failed to fetch users');
        }

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->free_result();

        return json_encode([
            'status' => 'Users fetched successfully',
            'status_code' => 'success',
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $itemsPerPage,
                'total_pages' => $totalPages,
                'total_users' => $totalUsers
            ]
        ]);
    }

    private function createUser()
    {
        $required = ['username', 'email', 'phone', 'role'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw SMSPortalException::requiredFields();
            }
        }

        $username = Validator::validateUserInput($_POST['username']);
        $email = Validator::validateEmail($_POST['email']);
        $phone = Validator::validatePhone($_POST['phone']);
        $role = in_array($_POST['role'], ['admin', 'client']) ? $_POST['role'] : 'client';

        $this->conn->begin_transaction();
        try {
            $check = MySQLDatabase::sqlSelect(
                $this->conn,
                'SELECT id FROM users WHERE username = ? OR email = ?',
                'ss',
                $username,
                $email
            );
            if ($check && $check->num_rows > 0) {
                $check->free_result();
                throw SMSPortalException::invalidParameter('Username or email already exists');
            }
            if ($check) {
                $check->free_result();
            }

            $tempPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

            $result = MySQLDatabase::sqlInsert(
                $this->conn,
                'INSERT INTO users
                (username, email, phone, password, type, status) VALUES (?, ?, ?, ?, ?, "active")',
                'sssss',
                $username,
                $email,
                $phone,
                $hashedPassword,
                $role
            );

            if ($result === -1) {
                throw SMSPortalException::databaseError('Failed to create user');
            }

            // Send SMS notification
            $this->sendWelcomeSMS($username,  $phone);

            $this->conn->commit();
            return json_encode([
                'status' => 'User created successfully',
                'status_code' => 'success',
                'user_id' => $result,
                'temp_password' => $tempPassword // Only for development
            ]);
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->customLog("Create user error: " . $e->getMessage());
            throw $e;
        }
    }

    private function updateUser()
    {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId) {
            throw SMSPortalException::requiredFields();
        }

        $username = Validator::validateUserInput($_POST['username'] ?? '');
        $email = Validator::validateEmail($_POST['email'] ?? '');
        $phone = Validator::validatePhone($_POST['phone'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['admin', 'client']) ? $_POST['role'] : 'client';

        $this->conn->begin_transaction();
        try {
            $check = MySQLDatabase::sqlSelect(
                $this->conn,
                'SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?',
                'ssi',
                $phone,
                $email,
                $userId
            );
            if ($check && $check->num_rows > 0) {
                $check->free_result();
                throw SMSPortalException::invalidParameter('Username or email already in use');
            }
            if ($check) {
                $check->free_result();
            }

            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE users SET username = ?, email = ?, phone = ?, type = ? WHERE id = ?',
                'ssssi',
                $username,
                $email,
                $phone,
                $role,
                $userId
            );

            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to update user');
            }

            $this->conn->commit();
            return json_encode([
                'status' => 'User updated successfully',
                'status_code' => 'success'
            ]);
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function updateUserStatus()
    {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $newStatus = in_array($_POST['new_status'] ?? '', ['active', 'in_active', 'suspended', 'deleted'])
            ? $_POST['new_status']
            : 'active';

        if (!$userId) {
            throw SMSPortalException::requiredFields();
        }

        $this->conn->begin_transaction();
        try {
            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE users SET status = ? WHERE id = ?',
                'si',
                $newStatus,
                $userId
            );

            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to update user status');
            }

            $this->conn->commit();
            return json_encode([
                'status' => 'User status updated successfully',
                'status_code' => 'success'
            ]);
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function resetPassword()
    {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId) {
            throw SMSPortalException::requiredFields();
        }

        $this->conn->begin_transaction();
        try {
            $tempPassword = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE users SET password = ? WHERE id = ?',
                'si',
                $hashedPassword,
                $userId
            );

            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to reset password');
            }

            $this->conn->commit();
            return json_encode([
                'status' => 'Password reset successfully',
                'status_code' => 'success',
                'temp_password' => $tempPassword // Only for development
            ]);
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    private function deleteUser()
    {
        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId) {
            throw SMSPortalException::requiredFields();
        }

        $this->conn->begin_transaction();
        try {
            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE users SET status = "deleted", updated_at = NOW() WHERE id = ?',
                'i',
                $userId
            );

            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to delete user');
            }

            $this->conn->commit();
            return json_encode([
                'status' => 'User deleted successfully',
                'status_code' => 'success'
            ]);
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }



    private function sendWelcomeSMS($username, $phone)
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
            throw SMSPortalException::invalidPhoneFormat();
        }

        $balanceResponse = SMSClient::checkSMSBalance();
        $balanceData = json_decode($balanceResponse, true);

        if (!isset($balanceData['message']) || $balanceData['message'] < 1) {
            throw SMSPortalException::insufficientSMSBalance();
        }

        $message = "Welcome to TekSed SMS Portal, $username! We're glad to have you onboard. The team will keep you updated on the next steps. Let's get started!";

        if (strlen($message) > 160) {
            throw SMSPortalException::invalidParameter('Welcome message exceeds 160 characters');
        }

        $response = SMSClient::sendSMS([$phone], $message);
        $responseData = json_decode($response, true);

        $status = (isset($responseData['status']) && $responseData['status'] === true) ? 'success' : 'failed';
        $error_message = $status === 'failed' ? ($responseData['message'] ?? 'Failed to load error message') : null;

        // Log SMS attempt
        $insert_id = MySQLDatabase::sqlInsert(
            $this->conn,
            'INSERT INTO sms_logs (user_id, phone_number, message, sent_at, status, error_message) VALUES (?, ?, ?, NOW(), ?, ?)',
            'issss',
            $_SESSION['USER_ID'],
            $phone,
            $message,
            $status,
            $error_message
        );
        if ($insert_id === -1) {
            throw SMSPortalException::databaseError('Failed to log SMS');
        }

        if ($status !== 'success') {
            throw SMSPortalException::databaseError('Failed to send welcome SMS: ' . ($responseData['message'] ?? 'Unknown error'));
        }
    }


    private function sendError($message)
    {
        return json_encode([
            'status' => 'error',
            'status_code' => $message
        ]);
    }

    private function customLog($message)
    {
        file_put_contents(
            'C:\\xampp\\htdocs\\sms-portal\\debug.log',
            date('Y-m-d H:i:s') . " - UserManager: $message\n",
            FILE_APPEND
        );
    }
}

try {
    $manager = new UserManager();
    echo $manager->process();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'Server error: ' . $e->getMessage(),
        'status_code' => 'error'
    ]);
}
