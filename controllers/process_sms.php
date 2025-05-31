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
                case 'validate_bulk':
                    return $this->validateBulkSMS();
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
            return $this->sendError($e->getMessage());
        } finally {
            if ($this->conn) {
                $this->conn->close();
            }
        }
    }

    private function validateBulkSMS()
    {
        $group = Validator::validateUserInput($_POST['group'] ?? '');
        $message = Validator::validateUserInput($_POST['message'] ?? '');

        if (empty($group) || empty($message)) {
            return json_encode([
                'status_code' => 'validation_failed',
                'message' => 'Group and message are required'
            ]);
        }

        // Fetch and validate numbers
        $query = 'SELECT phone_number, name FROM contacts WHERE user_id = ? AND `group` = ?';
        $result = MySQLDatabase::sqlSelect($this->conn, $query, 'is', $_SESSION['USER_ID'], $group);

        if ($result === false) {
            throw SMSPortalException::databaseError('Failed to fetch contacts');
        }

        $validNumbers = [];
        $invalidNumbers = [];

        while ($row = $result->fetch_assoc()) {
            $phone = preg_replace('/[^0-9+]/', '', $row['phone_number']);
            if (preg_match('/^\+?[1-9]\d{9,14}$/', $phone)) {
                $validNumbers[] = [
                    'phone' => $phone,
                    'name' => $row['name']
                ];
            } else {
                $invalidNumbers[] = sprintf(
                    '%s (%s) - Invalid format',
                    $row['name'],
                    $row['phone_number']
                );
            }
        }
        $result->free_result();

        // Check for valid numbers
        if (empty($validNumbers)) {
            return json_encode([
                'status_code' => 'validation_failed',
                'message' => 'No valid phone numbers found',
                'invalid_numbers' => $invalidNumbers
            ]);
        }

        // Check SMS balance
        $balanceResponse = SMSClient::checkSMSBalance();
        $balanceData = json_decode($balanceResponse, true);

        if (!isset($balanceData['message']) || $balanceData['message'] < count($validNumbers)) {
            return json_encode([
                'status_code' => 'validation_failed',
                'message' => 'Insufficient SMS balance',
                'required_credits' => count($validNumbers),
                'available_credits' => $balanceData['message'] ?? 0
            ]);
        }

        // Store validated data in session
        $_SESSION['validated_bulk_sms'] = [
            'numbers' => $validNumbers,
            'message' => $message,
            'group' => $group,
            'timestamp' => time()
        ];

        return json_encode([
            'status_code' => 'validation_success',
            'message' => 'Validation successful',
            'valid_count' => count($validNumbers),
            'group_name' => $group
        ]);
    }


    private function sendBulkSMS()
    {
        $validatedData = $_SESSION['validated_bulk_sms'] ?? null;

        if (!$validatedData || (time() - $validatedData['timestamp']) > 300) {
            throw SMSPortalException::invalidParameter('Please validate numbers first');
        }

        $numbers = $validatedData['numbers'];
        $message = $validatedData['message'];
        $group = $validatedData['group'];

        unset($_SESSION['validated_bulk_sms']);

        $batchSize = 100;
        $successCount = 0;
        $failedNumbers = [];
        $batches = array_chunk($numbers, $batchSize);

        foreach ($batches as $batch) {
            try {
                $phoneBatch = array_column($batch, 'phone');
                $response = SMSClient::sendSMS($phoneBatch, $message);
                $responseData = json_decode($response, true);

                $status = (isset($responseData['status']) && $responseData['status'] === true) ? 'success' : 'failed';
                $error_message = $status === 'failed' ? ($responseData['message'] ?? 'Failed to send message') : null;

                foreach ($batch as $contact) {
                    // Log attempt
                    $insert_id = MySQLDatabase::sqlInsert(
                        $this->conn,
                        'INSERT INTO sms_logs (user_id, phone_number, message, sent_at, status, error_message) VALUES (?, ?, ?, NOW(), ?, ?)',
                        'issss',
                        $_SESSION['USER_ID'],
                        $contact['phone'],
                        $message,
                        $status,
                        $error_message
                    );

                    if ($insert_id === -1) {
                        throw SMSPortalException::databaseError('Failed to log SMS');
                    }

                    // Track success/failure
                    if ($status === 'success') {
                        $successCount++;
                    } else {
                        $failedNumbers[] = $contact;
                    }
                }
            } catch (Exception $e) {
                // Handle batch failure
                foreach ($batch as $contact) {
                    $failedNumbers[] = $contact;
                    // Log failure
                    MySQLDatabase::sqlInsert(
                        $this->conn,
                        'INSERT INTO sms_logs (user_id, phone_number, message, sent_at, status, error_message) VALUES (?, ?, ?, NOW(), ?, ?)',
                        'issss',
                        $_SESSION['USER_ID'],
                        $contact['phone'],
                        $message,
                        'failed',
                        $e->getMessage()
                    );
                }
            }
        }

        return json_encode([
            'status_code' => $failedNumbers ? 'partial_success' : 'success',
            'status' => sprintf(
                $failedNumbers ?
                    'Sent %d out of %d messages to group "%s". %d message(s) failed.' :
                    'Successfully sent %d message%s to group "%s"',
                $successCount,
                $failedNumbers ? count($numbers) : ($successCount === 1 ? '' : 's'),
                htmlspecialchars($group),
                $failedNumbers ? count($failedNumbers) : null
            ),
            'total_recipients' => count($numbers),
            'successful_sends' => $successCount,
            'failed_sends' => count($failedNumbers),
            'failed_recipients' => $failedNumbers ? array_map(function ($contact) {
                return sprintf(
                    '%s (%s)',
                    htmlspecialchars($contact['name']),
                    preg_replace('/\d(?=\d{4})/', '*', $contact['phone'])
                );
            }, $failedNumbers) : [],
            'group_name' => htmlspecialchars($group)
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
}

try {
    $manager = new SMSManager();
    echo $manager->process();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'Server error: ' . $e->getMessage(),
        'status_code' => 'error'
    ]);
}
