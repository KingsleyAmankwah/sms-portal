<?php
require_once __DIR__ . '/../core/app-config.php';
require_once __DIR__ . '/../core/extensions/classes.php';
require_once __DIR__ . '/../core/exceptions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\Validator;
use SMSPortalExceptions\SMSPortalException;

header('Content-Type: application/json; charset=utf-8');

class AccountManager
{
    private $conn;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION['USER_ID'])) {
            throw SMSPortalException::invalidSession();
        }
        $this->conn = MySQLDatabase::createConnection();
        if ($this->conn === false) {
            throw SMSPortalException::databaseError('Failed to connect to database');
        }
    }

    public function processRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw SMSPortalException::invalidRequest('Invalid request method');
        }

        $action = Validator::validateUserInput($_POST['action'] ?? '');
        if ($action !== 'update_account') {
            throw SMSPortalException::invalidRequest('Invalid action');
        }

        return $this->updateAccount();
    }

    private function updateAccount()
    {
        $name = Validator::validateUserInput($_POST['username'] ?? '');
        $email = Validator::validateUserInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($email)) {
            throw SMSPortalException::requiredFields();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw SMSPortalException::invalidParameter('Invalid email format');
        }

        if (!empty($password)) {
            if ($password !== $confirm_password) {
                throw SMSPortalException::invalidParameter('Passwords do not match');
            }
            if (strlen($password) < 8) {
                throw SMSPortalException::invalidParameter('Password must be at least 8 characters');
            }
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
        } else {
            $password_hash = null;
        }

        // Check if email is taken (excluding current user)
        $result = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT id FROM users WHERE email = ? AND id != ?',
            'si',
            $email,
            $_SESSION['USER_ID']
        );
        if ($result && $result->num_rows > 0) {
            $result->free_result();
            throw SMSPortalException::invalidParameter('Email is already in use');
        }
        if ($result) {
            $result->free_result();
        }

        // Update user
        $query = 'UPDATE users SET username = ?, email = ?';
        $params = ['ss', $name, $email];
        if ($password_hash) {
            $query .= ', password = ?';
            $params[0] .= 's';
            $params[] = $password_hash;
        }
        $query .= ' WHERE id = ?';
        $params[0] .= 'i';
        $params[] = $_SESSION['USER_ID'];

        $success = MySQLDatabase::sqlUpdate($this->conn, $query, $params[0], ...array_slice($params, 1));
        if (!$success) {
            throw SMSPortalException::databaseError('Failed to update account');
        }

        // Update session
        $_SESSION['USER_NAME'] = $name;
        $_SESSION['USER_EMAIL'] = $email;

        return json_encode([
            'status' => 'Account updated successfully',
            'status_code' => 'success'
        ]);
    }

    public function __destruct()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

try {
    $manager = new AccountManager();
    echo $manager->processRequest();
} catch (SMSPortalException $e) {
    http_response_code($e->getCode() === 'invalid_request' ? 400 : 500);
    echo json_encode([
        'status' => $e->getMessage(),
        'status_code' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'Unexpected error',
        'status_code' => 'error',
        'message' => 'An unexpected error occurred'
    ]);
}
