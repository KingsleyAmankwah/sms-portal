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

class SMSManager
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
                case 'send_bulk':
                    return $this->sendBulkSMS();
                case 'send_individual':
                    return $this->sendIndividualSMS();
                case 'check_balance':
                    return $this->checkBalance();
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

    private function sendBulkSMS()
    {
        $group = Validator::validateUserInput($_POST['group'] ?? '');
        $message = Validator::validateUserInput($_POST['message'] ?? '');

        if (empty($group) || empty($message)) {
            throw SMSPortalException::requiredFields();
        }

        if (strlen($message) > 160) {
            throw SMSPortalException::invalidParameter('Message must be 160 characters or less');
        }

        // Fetch contacts in the group
        $query = $group === 'All'
            ? 'SELECT phone_number FROM contacts WHERE user_id = ?'
            : 'SELECT phone_number FROM contacts WHERE user_id = ? AND `group` = ?';
        $params = $group === 'All'
            ? ['i', $_SESSION['USER_ID']]
            : ['is', $_SESSION['USER_ID'], $group];

        $result = MySQLDatabase::sqlSelect($this->conn, $query, $params[0], ...array_slice($params, 1));
        if ($result === false) {
            throw SMSPortalException::databaseError('Failed to fetch contacts');
        }

        $numbers = [];
        while ($row = $result->fetch_assoc()) {
            $phone = preg_replace('/[^0-9+]/', '', $row['phone_number']);
            if (preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
                $numbers[] = $phone;
            }
        }
        $result->free_result();

        if (empty($numbers)) {
            throw SMSPortalException::invalidParameter('No valid phone numbers found in group');
        }

        // Check balance
        $balanceResponse = SMSClient::checkSMSBalance();
        $balanceData = json_decode($balanceResponse, true);

        if (!isset($balanceData['message']) || $balanceData['message'] < count($numbers)) {
            throw SMSPortalException::insufficientSMSBalance();
        }

        // Send SMS in batches of 100 (GiantSMS API limit)
        $batchSize = 100;
        $successCount = 0;
        $batches = array_chunk($numbers, $batchSize);

        foreach ($batches as $batch) {
            $response = SMSClient::sendSMS($batch, $message);
            $responseData = json_decode($response, true);

            $status = (isset($responseData['status']) && $responseData['status'] === true) ? 'success' : 'failed';
            $error_message = $status === 'failed' ? ($responseData['message'] ?? 'Unknown error') : null;

            // Log each number in the batch
            foreach ($batch as $phone) {
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
                    $this->customLog("Failed to log SMS for $phone");
                    throw SMSPortalException::databaseError('Failed to log SMS');
                }
            }

            if ($status === 'success') {
                $successCount += count($batch);
            } else {
                $this->customLog("SMS batch failed: " . $response);
                throw SMSPortalException::databaseError('Failed to send SMS: ' . ($responseData['message'] ?? 'Unknown error'));
            }
        }

        return json_encode([
            'status' => "SMS sent to $successCount contacts",
            'status_code' => 'success',
            'sent_count' => $successCount
        ]);
    }

    private function sendIndividualSMS()
    {
        $phone_number = Validator::validateUserInput($_POST['phone_number'] ?? '');
        $message = Validator::validateUserInput($_POST['message'] ?? '');

        if (empty($phone_number) || empty($message)) {
            throw SMSPortalException::requiredFields();
        }

        //T: Uncomment this when you want to enforce message length
        // if (strlen($message) > 160) {
        //     throw SMSPortalException::invalidParameter('Message must be 160 characters or less');
        // }

        $phone = preg_replace('/[^0-9+]/', '', $phone_number);
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
            throw SMSPortalException::invalidPhoneFormat();
        }

        $balanceResponse = SMSClient::checkSMSBalance();
        $balanceData = json_decode($balanceResponse, true);

        if (!isset($balanceData['message']) || $balanceData['message'] < 1) {
            throw SMSPortalException::insufficientSMSBalance();
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

        if ($status === 'success') {
            return json_encode([
                'status' => sprintf(
                    'Message sent successfully to %s',
                    preg_replace('/\d(?=\d{4})/', '*', $phone)
                ),
                'status_code' => 'success'
            ]);
        } else {
            throw SMSPortalException::databaseError('Failed to send SMS: ' . ($responseData['message'] ?? 'Unknown error'));
        }
    }


    private function checkBalance()
    {
        $response = SMSClient::checkSMSBalance();
        $responseData = json_decode($response, true);

        if (isset($responseData['status']) && $responseData['status'] === true && isset($responseData['message'])) {
            return json_encode([
                'status' => 'Balance fetched successfully',
                'status_code' => 'success',
                'balance' => $responseData['message']
            ]);
        } else {
            $this->customLog("Balance check failed: " . $response);
            throw SMSPortalException::databaseError('Failed to check balance: ' . ($responseData['message'] ?? 'Unknown error'));
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
            'C:\\xampp\\htdocs\\dashboard-master\\debug.log',
            date('Y-m-d H:i:s') . " - $message\n",
            FILE_APPEND
        );
    }
}

try {
    $manager = new SMSManager();
    echo $manager->process();
} catch (Exception $e) {
    \SMSPortalExtensions\customLog("SMSManager error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'Server error: ' . $e->getMessage(),
        'status_code' => 'error'
    ]);
}
