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

class GroupManager {
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
                    return $this->fetchGroups();
                case 'create':
                    return $this->createGroup();
                case 'update':
                    return $this->updateGroup();
                case 'delete':
                    return $this->deleteGroup();
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

    private function fetchGroups() {
        $query = 'SELECT g.id, g.name, COUNT(c.id) as contact_count 
                  FROM groups g 
                  LEFT JOIN contacts c ON g.name = c.group AND c.user_id = ? 
                  WHERE g.user_id = ? 
                  GROUP BY g.id, g.name 
                  ORDER BY g.name ASC';
        $result = MySQLDatabase::sqlSelect($this->conn, $query, 'ii', $_SESSION['USER_ID'], $_SESSION['USER_ID']);
        if ($result === false) {
            throw SMSPortalException::databaseError('Failed to fetch groups');
        }

        $groups = [];
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
        $result->free_result();

        return json_encode([
            'status' => 'Groups fetched successfully',
            'status_code' => 'success',
            'groups' => $groups
        ]);
    }

    private function createGroup() {
        $group_name = Validator::validateUserInput($_POST['group_name'] ?? '');
        if (empty($group_name)) {
            throw SMSPortalException::requiredFields();
        }

        // Check for duplicate group name
        $check = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT id FROM groups WHERE user_id = ? AND name = ?',
            'is',
            $_SESSION['USER_ID'],
            $group_name
        );
        if ($check && $check->num_rows > 0) {
            $check->free_result();
            throw SMSPortalException::duplicateGroup();
        }
        if ($check) {
            $check->free_result();
        }

        $result = MySQLDatabase::sqlInsert(
            $this->conn,
            'INSERT INTO groups (user_id, name) VALUES (?, ?)',
            'is',
            $_SESSION['USER_ID'],
            $group_name
        );

        if ($result !== true) {
            throw SMSPortalException::databaseError('Failed to create group');
        }

        return json_encode([
            'status' => 'Group created successfully',
            'status_code' => 'success'
        ]);
    }

    private function updateGroup() {
        $group_id = $_POST['group_id'] ?? '';
        $group_name = Validator::validateUserInput($_POST['group_name'] ?? '');

        if (!is_numeric($group_id) || empty($group_name)) {
            throw SMSPortalException::requiredFields();
        }

        // Check if group is "All"
        $group = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT name FROM groups WHERE id = ? AND user_id = ?',
            'ii',
            $group_id,
            $_SESSION['USER_ID']
        );
        if (!$group || $group->num_rows === 0) {
            $group->free_result();
            throw SMSPortalException::invalidParameter('Group not found');
        }
        $current_name = $group->fetch_assoc()['name'];
        $group->free_result();

        if (strtolower($current_name) === 'all') {
            throw SMSPortalException::protectedGroup();
        }

        // Check for duplicate group name (excluding current group)
        $check = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT id FROM groups WHERE user_id = ? AND name = ? AND id != ?',
            'isi',
            $_SESSION['USER_ID'],
            $group_name,
            $group_id
        );
        if ($check && $check->num_rows > 0) {
            $check->free_result();
            throw SMSPortalException::duplicateGroup();
        }
        if ($check) {
            $check->free_result();
        }

        // Update group and associated contacts
        $this->conn->begin_transaction();
        try {
            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE groups SET name = ? WHERE id = ? AND user_id = ?',
                'sii',
                $group_name,
                $group_id,
                $_SESSION['USER_ID']
            );
            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to update group');
            }

            // Update contacts' group field
            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE contacts SET `group` = ? WHERE `group` = ? AND user_id = ?',
                'ssi',
                $group_name,
                $current_name,
                $_SESSION['USER_ID']
            );
            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to update contacts');
            }

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }

        return json_encode([
            'status' => 'Group updated successfully',
            'status_code' => 'success'
        ]);
    }

    private function deleteGroup() {
        $group_id = $_POST['group_id'] ?? '';
        if (!is_numeric($group_id)) {
            throw SMSPortalException::invalidParameter('Group ID');
        }

        // Get group name and check if it's "All"
        $group = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT name FROM groups WHERE id = ? AND user_id = ?',
            'ii',
            $group_id,
            $_SESSION['USER_ID']
        );
        if (!$group || $group->num_rows === 0) {
            $group->free_result();
            throw SMSPortalException::invalidParameter('Group not found');
        }
        $group_name = $group->fetch_assoc()['name'];
        $group->free_result();

        if (strtolower($group_name) === 'all') {
            throw SMSPortalException::protectedGroup();
        }

        // Option 2 (Prevent deletion if contacts exist) 
        /*
        $check = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT COUNT(*) as count FROM contacts WHERE user_id = ? AND `group` = (SELECT name FROM groups WHERE id = ?)',
            'ii',
            $_SESSION['USER_ID'],
            $group_id
        );
        if ($check && $check->fetch_assoc()['count'] > 0) {
            $check->free_result();
            throw SMSPortalException::groupHasContacts();
        }
        if ($check) {
            $check->free_result();
        }
        */

        // Option 1: Reassign contacts to "All"
        $this->conn->begin_transaction();
        try {
            // Reassign contacts to "All"
            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE contacts SET `group` = ? WHERE `group` = ? AND user_id = ?',
                'ssi',
                'All',
                $group_name,
                $_SESSION['USER_ID']
            );
            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to reassign contacts');
            }

            // Delete the group
            $result = MySQLDatabase::sqlDelete(
                $this->conn,
                'DELETE FROM groups WHERE id = ? AND user_id = ?',
                'ii',
                $group_id,
                $_SESSION['USER_ID']
            );
            if ($result === -1 || $this->conn->affected_rows === 0) {
                throw SMSPortalException::databaseError('Failed to delete group');
            }

            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }

        return json_encode([
            'status' => 'Group deleted successfully',
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
    $manager = new GroupManager();
    echo $manager->process();
} catch (Exception $e) {
    \SMSPortalExtensions\customLog("GroupManager error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'Server error: ' . $e->getMessage(),
        'status_code' => 'error'
    ]);
}
?>