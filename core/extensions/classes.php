<?php

/**
 * Author: Kingsley Amankwah
 * Date: 2025-05-20
 * Purpose: This file contains classes implementing functionality for the SMS Portal application.
 */

namespace SMSPortalExtensions;

use DateTime;
use mysqli;

require_once __DIR__ . '/../app-config.php';
require_once 'interfaces.php';

const DB_ERROR_PREPARE_FAILED = "Prepare failed: ";
const CUSTOM_LOG = 'C:\xampp\htdocs\dashboard-master\debug.log';

/**
 * Logs custom messages to a predefined log file
 * @param string $message The message to log
 */
function customLog(string $message): void
{
    file_put_contents(CUSTOM_LOG, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

class DateFormatter implements IDateFormatter
{
    /**
     * Formats a date string to a more readable format (e.g., "1st January, 2023")
     * @param string|null $dateString The date string to format
     * @return string|null Formatted date or null if invalid input
     */
    public static function formatDate($dateString)
    {
        if ($dateString === null) {
            return null;
        }
        try {
            $date = new DateTime($dateString);
            return $date->format('jS F, Y');
        } catch (\Exception $e) {
            return null;
        }
    }
}

class MySQLDatabase implements IDatabase
{
    /**
     * Creates a new database connection
     * @return mysqli|false Returns mysqli connection object or false on failure
     */
    public static function createConnection()
    {
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, RESOURCE_DATABASE);
        if ($conn->connect_error) {
            $message = "Database connection failed: " . $conn->connect_error;
            error_log($message);
            customLog($message);
            return false;
        }
        return $conn;
    }

    /**
     * Executes a SELECT query with optional parameters
     * @param mysqli $conn Database connection
     * @param string|false $format Format string for bound parameters
     * @param mixed ...$args Parameters to bind
     * @return mysqli_result|false Returns result set or false on failure
     */
    public static function sqlSelect($conn, $query, $format = false, ...$args)
    {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $message = DB_ERROR_PREPARE_FAILED . $conn->error;
            error_log($message);
            customLog($message);
            return false;
        }

        if ($format) {
            $stmt->bind_param($format, ...$args);
        }
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $stmt->close();
            return $result;
        }
        customLog("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    /**
     * Executes an INSERT query with optional parameters
     * @param mysqli $conn Database connection
     * @param string|false $format Format string for bound parameters
     * @param mixed ...$args Parameters to bind
     * @return int Returns last insert ID or -1 on failure
     */
    public static function sqlInsert($conn, $query, $format = false, ...$args)
    {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $message = DB_ERROR_PREPARE_FAILED . $conn->error;
            error_log($message);
            customLog($message);
            return -1;
        }
        if ($format) {
            $stmt->bind_param($format, ...$args);
        }
        if ($stmt->execute()) {
            $id = $stmt->insert_id;
            $stmt->close();
            return $id;
        }
        customLog("Insert failed: " . $stmt->error);
        $stmt->close();
        return -1;
    }

    /**
     * Executes an UPDATE query with optional parameters
     * @param mysqli $conn Database connection
     * @param string|false $format Format string for bound parameters
     * @param mixed ...$vars Parameters to bind
     * @return bool|string Returns true on success, error message on failure
     */
    public static function sqlUpdate($conn, $query, $format = false, ...$vars)
    {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $message = DB_ERROR_PREPARE_FAILED . $conn->error;
            error_log($message);
            customLog($message);
            return $conn->error;
        }
        if ($format) {
            $stmt->bind_param($format, ...$vars);
        }
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        }
        $error = $stmt->error;
        customLog("Update failed: " . $error);
        $stmt->close();
        return $error;
    }

    /**
     * Executes a DELETE query with optional parameters
     * @param mysqli $conn Database connection
     * @param string $query SQL query
     * @param string|false $format Format string for bound parameters
     * @param mixed ...$args Parameters to bind
     * @return int Returns number of affected rows or -1 on failure
     */
    public static function sqlDelete($conn, $query, $format = false, ...$args)
    {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            $message = DB_ERROR_PREPARE_FAILED . $conn->error;
            error_log($message);
            customLog($message);
            return -1;
        }
        if ($format) {
            $stmt->bind_param($format, ...$args);
        }
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            return $affected_rows;
        }
        customLog("Delete failed: " . $stmt->error);
        $stmt->close();
        return -1;
    }
}

class Authentication implements IAuthentication
{
    /**
     * Creates a new CSRF token and stores it in the session
     * @return string The generated token
     */
    public static function createToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $message = "createToken: Session not active";
            error_log($message);
            customLog($message);
            session_start();
        }
        $seed = self::urlSafeEncode(random_bytes(8));
        $t = time();
        $hash = self::urlSafeEncode(hash_hmac('sha256', $seed . $t, CSRF_TOKEN_SECRET, true));
        $token = self::urlSafeEncode($hash . '|' . $seed . '|' . $t);
        $_SESSION['csrf_token'] = $token;
        customLog("CSRF token created: $token");
        return $token;
    }

    /**
     * Validates a CSRF token against the one stored in session
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateToken($token): bool
    {
        $isValid = false;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            customLog("validateToken: Session not active");
            session_start();
        }

        // Check session token exists and matches
        if (isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token) {
            $parts = explode('|', self::urlSafeDecode($token));

            // Verify token structure
            if (count($parts) === 3) {
                $hash = hash_hmac('sha256', $parts[1] . $parts[2], CSRF_TOKEN_SECRET, true);

                // Verify token hash
                if (hash_equals($hash, self::urlSafeDecode($parts[0]))) {
                    customLog("CSRF token validated successfully: $token");
                    $isValid = true;
                } else {
                    customLog("CSRF token invalid: Hash mismatch");
                }
            } else {
                customLog("CSRF token invalid: Incorrect part count");
            }
        } else {
            customLog("CSRF token invalid: Session token mismatch. Submitted: $token, Session: " . ($_SESSION['csrf_token'] ?? 'none'));
        }

        return $isValid;
    }

    /**
     * URL-safe base64 encoding
     * @param string $data Data to encode
     * @return string Encoded data
     */
    private static function urlSafeEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL-safe base64 decoding
     * @param string $data Data to decode
     * @return string Decoded data
     */
    private static function urlSafeDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}


class Validator implements IValidator
{
    /**
     * Sanitizes and validates user input
     * @param string $data Input data to validate
     * @return string Sanitized data
     */
    public static function validateUserInput($data)
    {
        $conn = MySQLDatabase::createConnection();
        if (!$conn) {
            customLog("validateUserInput: Database connection failed");
            return $data;
        }
        $data = trim($data);
        $data = strip_tags($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        $data = mysqli_real_escape_string($conn, $data);
        $conn->close();
        return $data;
    }

    /**
     * Validates user login credentials
     * @param mysqli $conn Database connection
     * @param string $username Username to validate
     * @param string $password Password to validate
     */
    public static function validateLoginCredentials($conn, $username, $password)
    {
        if (!$conn) {
            $_SESSION['status'] = "Database connection failed";
            $_SESSION['status_code'] = "error";
            return;
        }

        $res = MySQLDatabase::sqlSelect($conn, 'SELECT id, username, password, role FROM users WHERE username = ?', 's', $username);

        if ($res === false) {
            $_SESSION['status'] = "Database query failed";
            $_SESSION['status_code'] = "error";
            return;
        }

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (md5($password) === $user['password']) {
                session_regenerate_id(true);
                $_SESSION['USER_ID'] = $user['id'];
                $_SESSION['ROLE'] = $user['role'];
                $_SESSION['USERNAME'] = $user['username'];
                unset($_SESSION['csrf_token']);

                $updateResult = MySQLDatabase::sqlUpdate($conn, 'UPDATE users SET last_login = NOW() WHERE id = ?', 'i', $user['id']);
                if ($updateResult !== true) {
                    $_SESSION['status'] = "Failed to update last_login for user";
                    $_SESSION['status_code'] = "info";
                }

                $res->free_result();
                header('Location: ' . DASHBOARD_PAGE);
                exit;
            } else {
                $_SESSION['status'] = "Invalid login credentials";
                $_SESSION['status_code'] = "error";
            }
        } else {
            $_SESSION['status'] = "Invalid login credentials";
            $_SESSION['status_code'] = "error";
        }
        if ($res) {
            $res->free_result();
        }
    }
}

class UIActions implements IUIActions
{
    /**
     * Loads and returns spinner HTML content
     * @return string Spinner HTML
     */
    public static function loadSpinner()
    {
        ob_start();
        include '../assets/loader/spinner.html';
        return ob_get_clean();
    }

    /**
     * Generates JavaScript code to show a SweetAlert popup
     * @param string $title Alert title
     * @param string $message Alert message
     * @param string $icon Alert icon (success, error, warning, info, question)
     * @param string ...$params Optional redirect URL
     * @return string JavaScript code for the alert
     */
    public static function showAlert($title, $message, $icon, ...$params)
    {
        $redirectUrl = !empty($params) ? $params[0] : '';
        $html = "<script src=\"../assets/sweetalert/sweetalert2.all.min.js\"></script>";
        $html .= "<script>Swal.fire({
                title: '$title',
                text: '$message',
                icon: '$icon',
                confirmButtonText: 'OK'
            }).then((result) => {
                if ('$redirectUrl' && result.isConfirmed) {
                    window.location = '$redirectUrl';
                }
            });</script>";
        return $html;
    }
}

class SMSClient implements ISMSClient
{
    /**
     * Sends bulk SMS messages
     * @param array $numbersArray Array of phone numbers
     * @param string $message Message to send
     * @return string API response or error message
     */
    public static function sendBulkSMS($numbersArray, $message)
    {
        $curl = curl_init();

        $data = [
            'from' => SMS_SENDER_ID,
            'recipients' => $numbersArray,
            'msg' => $message
        ];

        $jsonData = json_encode($data);

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.giantsms.com/api/v1/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => [
                "Accept: */*",
                "Authorization: Basic " . SMS_API_TOKEN,
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return $err;
        }
        return trim($response, " \t\n\r\0\x0BNULL");
    }

    /**
     * Checks SMS account balance
     * @return string API response or error message
     */
    public static function checkSMSBalance()
    {
        $curl = curl_init();
        $username = SMS_API_USERNAME;
        $password = SMS_API_PASSWORD;

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.giantsms.com/api/v1/balance?username=$username&password=$password",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Accept: */*",
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return $err;
        }
        return trim($response, " \t\n\r\0\x0BNULL");
    }
}
