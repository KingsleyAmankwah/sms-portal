<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/app-config.php';
require_once __DIR__ . '/../core/extensions/classes.php';
require_once __DIR__ . '/../core/exceptions.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\SMSClient;
use SMSPortalExtensions\Validator;
use SMSPortalExceptions\SMSPortalException;

header('Content-Type: application/json; charset=utf-8');

class SenderIdManager
{
    private $conn;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

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
                    return $this->fetchSenderIds();
                case 'request':
                    return $this->requestSenderId();
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

    private function fetchSenderIds()
    {
        try {
            // Fetch sender IDs from GiantSMS API (approved only)
            $response = SMSClient::getSenderIDs();
            $data = json_decode($response, true);

            // Check if API response is valid
            if (!isset($data['status']) || $data['status'] !== true || !isset($data['data'])) {
                $this->customLog("Invalid or empty API response: " . json_encode($data));
                return json_encode([
                    'status' => 'Sender IDs fetched successfully',
                    'status_code' => 'success',
                    'sender_ids' => []
                ]);
            }

            $senderIds = $data['data'] ?? [];
            $processedSenderIds = [];

            // Log fetched sender IDs
            $ids = array_map(fn($item) => $item['name'] ?? 'UNKNOWN', $senderIds);
            $this->customLog("Fetched " . count($ids) . " sender IDs from API: " . implode(', ', $ids));

            // Process API sender IDs (approved)
            foreach ($senderIds as $sender) {
                $senderId = $sender['name'] ?? '';
                // Skip entries with empty name
                if (empty($senderId)) {
                    $this->customLog("Skipping empty sender ID (name) in API response");
                    continue;
                }
                $processedSenderIds[] = [
                    'id' => '', // No ID provided by API
                    'sender_id' => $senderId,
                    'business_name' => $senderId, // Use name as business_name
                    'business_purpose' => $sender['purpose'] ?? '',
                    'status' => $sender['approval_status'] ?? 'approved', // Use approval_status, default to approved
                    'created_at' => 'N/A', // No created_at provided by API
                    'user_id' => null,
                    'username' => 'Unassigned',
                    'email' => ''
                ];
            }

            return json_encode([
                'status' => 'Sender IDs fetched successfully',
                'status_code' => 'success',
                'sender_ids' => $processedSenderIds
            ]);
        } catch (Exception $e) {
            $this->customLog("Error fetching sender IDs: " . $e->getMessage());
            throw SMSPortalException::apiError("Failed to fetch sender IDs: " . $e->getMessage());
        }
    }

    private function requestSenderId()
    {
        $required = ['user_id', 'business_name', 'business_purpose'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw SMSPortalException::requiredFields();
            }
        }

        $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $businessName = Validator::validateUserInput($_POST['business_name']);
        $businessPurpose = Validator::validateUserInput($_POST['business_purpose']);

        if (!$userId) {
            throw SMSPortalException::invalidParameter('Invalid user ID');
        }

        if (strlen($businessPurpose) < 20) {
            throw SMSPortalException::invalidParameter('Business purpose must be at least 20 characters');
        }

        if (strlen($businessName) > 5) {
            throw SMSPortalException::invalidParameter('Business name must be 11 characters or less');
        }

        $this->conn->begin_transaction();
        try {
            // Verify user exists and is not deleted
            $check = MySQLDatabase::sqlSelect(
                $this->conn,
                'SELECT username, email, phone, password FROM users WHERE id = ? AND status != "deleted"',
                'i',
                $userId
            );
            if ($check && $check->num_rows === 0) {
                $check->free_result();
                throw SMSPortalException::invalidParameter('User not found');
            }
            $user = $check->fetch_assoc();
            $check->free_result();

            // Check if user already has a sender ID
            if (!empty($user['sender_id'])) {
                throw SMSPortalException::invalidParameter('User already has a sender ID');
            }

            // Send request to GiantSMS API
            $response = SMSClient::registerSenderID($businessName, $businessPurpose);
            $data = json_decode($response, true);

            if (!isset($data['status']) || $data['status'] !== true) {
                throw SMSPortalException::apiError($data['message'] ?? 'Failed to request sender ID');
            }

            $senderId = $data['data']['name'] ?? null;
            $status = $data['data']['status'] ?? 'pending';

            // Update user with sender ID details
            $result = MySQLDatabase::sqlUpdate(
                $this->conn,
                'UPDATE users SET business_name = ?, sender_id = ?, business_purpose = ? WHERE id = ?',
                'sssi',
                $businessName,
                $senderId,
                $businessPurpose,
                $userId
            );

            if ($result !== true) {
                throw SMSPortalException::databaseError('Failed to update user with sender ID');
            }

            // Send login SMS if approved immediately
            if ($status === 'approved') {
                $this->sendLoginSMS(
                    $user['username'],
                    $user['email'],
                    $user['phone'],
                    $user['password']
                );
            }

            $this->conn->commit();
            return json_encode([
                'status' => 'Sender ID requested successfully',
                'status_code' => 'success',
                'sender_id' => $senderId,
                'api_status' => $status
            ]);
        } catch (Exception $e) {
            $this->conn->rollback();
            throw SMSPortalException::apiError("Failed to request sender ID: " . $e->getMessage());
        }
    }



    private function sendLoginSMS($username, $email, $phone, $hashedPassword)
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

        // Placeholder password; replace with secure token-based reset in production
        $tempPassword = '********';
        $message = "Hello $username, your sender ID has been approved! Log in with Email: $email, Password: $tempPassword at https://yourdomain.com/change-password to change your password.";

        if (strlen($message) > 160) {
            $this->customLog("Login SMS message too long: " . strlen($message) . " characters");
            throw SMSPortalException::invalidParameter('Login message exceeds 160 characters');
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
            throw SMSPortalException::apiError('Failed to send login SMS: ' . ($responseData['message'] ?? 'Unknown error'));
        }
    }

    private function customLog($message)
    {
        file_put_contents(
            'C:\\xampp\\htdocs\\sms-portal\\debug.log',
            date('Y-m-d H:i:s') . " - SenderIdManager: $message\n",
            FILE_APPEND
        );
    }

    private function sendError($message)
    {
        return json_encode([
            'status' => $message,
            'status_code' => 'error'
        ]);
    }
}

try {
    $manager = new SenderIdManager();
    echo $manager->process();
} catch (Exception $e) {
    \SMSPortalExtensions\customLog("SenderIdManager error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'Server error: ' . $e->getMessage(),
        'status_code' => 'error'
    ]);
}
