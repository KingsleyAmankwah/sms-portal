<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/app-config.php';
require_once __DIR__ . '/../core/extensions/classes.php';
require_once __DIR__ . '/../core/exceptions.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\Validator;
use SMSPortalExceptions\SMSPortalException;

header('Content-Type: application/json; charset=utf-8');

class ContactManager {
    private $conn;

    public function __construct() {
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

    public function process() {
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
                    return $this->fetchContacts();
                case 'delete':
                    return $this->deleteContact();
                case 'update':
                    return $this->updateContact();
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

    private function fetchContacts() {
        $page = isset($_POST['page']) && is_numeric($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $itemsPerPage = isset($_POST['items_per_page']) && is_numeric($_POST['items_per_page']) 
            ? max(1, (int)$_POST['items_per_page']) 
            : 10;
        $search = isset($_POST['search']) ? Validator::validateUserInput($_POST['search']) : '';

        $offset = ($page - 1) * $itemsPerPage;

        // Build query
        $query = 'SELECT id, name, phone_number, email, `group`, company, notes FROM contacts WHERE user_id = ?';
        $params = ['i', $_SESSION['USER_ID']];
        
        if ($search) {
            $query .= ' AND (name LIKE ? OR phone_number LIKE ?)';
            $params[0] .= 'ss';
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        // Count total contacts (for pagination)
        $countQuery = 'SELECT COUNT(*) as total FROM contacts WHERE user_id = ?';
        $countParams = ['i', $_SESSION['USER_ID']];
        if ($search) {
            $countQuery .= ' AND (name LIKE ? OR phone_number LIKE ?)';
            $countParams[0] .= 'ss';
            $countParams[] = $searchParam;
            $countParams[] = $searchParam;
        }

        $countResult = MySQLDatabase::sqlSelect($this->conn, $countQuery, ...$countParams);
        if ($countResult === false) {
            throw SMSPortalException::databaseError('Failed to count contacts');
        }
        $totalContacts = $countResult->fetch_assoc()['total'];
        $countResult->free_result();

        // Fetch paginated contacts
        $query .= ' ORDER BY name ASC LIMIT ? OFFSET ?';
        $params[0] .= 'ii';
        $params[] = $itemsPerPage;
        $params[] = $offset;

        $result = MySQLDatabase::sqlSelect($this->conn, $query, ...$params);
        if ($result === false) {
            throw SMSPortalException::databaseError('Failed to fetch contacts');
        }

        $contacts = [];
        while ($row = $result->fetch_assoc()) {
            $contacts[] = $row;
        }
        $result->free_result();

        return json_encode([
            'status' => 'Contacts fetched successfully',
            'status_code' => 'success',
            'contacts' => $contacts,
            'total_contacts' => (int)$totalContacts,
            'items_per_page' => $itemsPerPage,
            'current_page' => $page
        ]);
    }

    private function deleteContact() {
        $contact_id = $_POST['contact_id'] ?? '';
        if (!is_numeric($contact_id)) {
            throw SMSPortalException::invalidParameter('Contact ID');
        }

        $result = MySQLDatabase::sqlDelete(
            $this->conn,
            'DELETE FROM contacts WHERE id = ? AND user_id = ?',
            'ii',
            $contact_id,
            $_SESSION['USER_ID']
        );

        if ($result === -1 || $this->conn->affected_rows === 0) {
            throw SMSPortalException::databaseError('Failed to delete contact');
        }

        return json_encode([
            'status' => 'Contact deleted successfully',
            'status_code' => 'success'
        ]);
    }

    private function updateContact() {
        $data = [
            'contact_id' => $_POST['contact_id'] ?? '',
            'name' => Validator::validateUserInput($_POST['name'] ?? ''),
            'phone_number' => Validator::validateUserInput($_POST['phone_number'] ?? ''),
            'email' => Validator::validateUserInput($_POST['email'] ?? ''),
            'group' => Validator::validateUserInput($_POST['group'] ?? 'All'),
            'company' => Validator::validateUserInput($_POST['company'] ?? ''),
            'notes' => Validator::validateUserInput($_POST['notes'] ?? '')
        ];

        if (!is_numeric($data['contact_id'])) {
            throw SMSPortalException::invalidParameter('Contact ID');
        }

        if (empty($data['name']) || empty($data['phone_number'])) {
            throw SMSPortalException::requiredFields();
        }

        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $data['phone_number'])) {
            throw SMSPortalException::invalidPhoneFormat();
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw SMSPortalException::invalidEmailFormat();
        }

        // Check for duplicates (excluding current contact)
        $check = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT id FROM contacts WHERE user_id = ? AND (phone_number = ? OR (email IS NOT NULL AND email = ?)) AND id != ?',
            'issi',
            $_SESSION['USER_ID'],
            $data['phone_number'],
            $data['email'],
            $data['contact_id']
        );

        if ($check && $check->num_rows > 0) {
            $check->free_result();
            throw SMSPortalException::duplicateContact();
        }
        if ($check) {
            $check->free_result();
        }

        $result = MySQLDatabase::sqlUpdate(
            $this->conn,
            'UPDATE contacts SET name = ?, phone_number = ?, email = ?, `group` = ?, company = ?, notes = ? WHERE id = ? AND user_id = ?',
            'ssssssii',
            $data['name'],
            $data['phone_number'],
            $data['email'] ?: null,
            $data['group'],
            $data['company'] ?: null,
            $data['notes'] ?: null,
            $data['contact_id'],
            $_SESSION['USER_ID']
        );

        if ($result !== true) {
            throw SMSPortalException::databaseError('Failed to update contact: ' . $result);
        }

        return json_encode([
            'status' => 'Contact updated successfully',
            'status_code' => 'success'
        ]);
    }

    private function sendError($message) {
        return json_encode([
            'status' => $message,
            'status_code' => 'error'
        ]);
    }

    private function customLog($message) {
        file_put_contents(
            'C:\\xampp\\htdocs\\dashboard-master\\debug.log',
            date('Y-m-d H:i:s') . " - $message\n",
            FILE_APPEND
        );
    }
}

try {
    $manager = new ContactManager();
    echo $manager->process();
} catch (Exception $e) {
    \SMSPortalExtensions\customLog("ContactManager error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'Server error: ' . $e->getMessage(),
        'status_code' => 'error'
    ]);
}
?>