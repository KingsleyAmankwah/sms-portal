<?php

/**
 * Author: [Your Name]
 * Purpose: This file contains classes implementing functionality for the SMS Portal application.
 */

namespace SMSPortalExtensions;

require_once __DIR__ . '/../app-config.php';
require_once 'interfaces.php';

use DateTime;
use mysqli;

class DateFormatter implements IDateFormatter
{
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
    public static function createConnection()
    {
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, RESOURCE_DATABASE);
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            return false;
        }
        return $conn;
    }

    public static function sqlSelect($conn, $query, $format = false, ...$args)
    {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
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
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    public static function sqlInsert($conn, $query, $format = false, ...$args)
    {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
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
        $stmt->close();
        return -1;
    }

    public static function sqlUpdate($conn, $query, $format = false, ...$vars)
    {
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            error_log("Prepare failed: " . $conn->error);
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
        $stmt->close();
        return $error;
    }
}

class Authentication implements IAuthentication
{
    public static function createToken()
    {
        $seed = self::urlSafeEncode(random_bytes(8));
        $t = time();
        $hash = self::urlSafeEncode(hash_hmac('sha256', session_id() . $seed . $t, CSRF_TOKEN_SECRET, true));
        return self::urlSafeEncode($hash . '|' . $seed . '|' . $t);
    }

    public static function validateToken($token)
    {
        $parts = explode('|', self::urlSafeDecode($token));
        if (count($parts) !== 3) {
            error_log("CSRF token invalid: Incorrect part count");
            return false;
        }
        $hash = hash_hmac('sha256', session_id() . $parts[1] . $parts[2], CSRF_TOKEN_SECRET, true);
        if (!hash_equals($hash, self::urlSafeDecode($parts[0]))) {
            error_log("CSRF token invalid: Hash mismatch");
            return false;
        }
        return true;
    }

    private static function urlSafeEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function urlSafeDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

class Validator implements IValidator
{
    public static function validateUserInput($data)
    {
        $conn = MySQLDatabase::createConnection();
        if (!$conn) {
            error_log("validateUserInput: Database connection failed");
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

    public static function validateLoginCredentials($conn, $username, $password)
    {
        if (!$conn) {
            error_log("validateLoginCredentials: Invalid database connection");
            $_SESSION['status'] = "Database connection failed";
            $_SESSION['status_code'] = "error";
            return;
        }

        $res = MySQLDatabase::sqlSelect($conn, 'SELECT id, username, password, role FROM users WHERE username = ?', 's', $username);

        if ($res === false) {
            error_log("validateLoginCredentials: SQL query failed for username: $username");
            $_SESSION['status'] = "Database query failed";
            $_SESSION['status_code'] = "error";
            return;
        }

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['USER_ID'] = $user['id'];
                $_SESSION['ROLE'] = $user['role'];
                $_SESSION['USERNAME'] = $user['username'];
                
                $updateResult = MySQLDatabase::sqlUpdate($conn, 'UPDATE users SET last_login = NOW() WHERE id = ?', 'i', $user['id']);
                if ($updateResult !== true) {
                    error_log("validateLoginCredentials: Failed to update last_login for user ID: " . $user['id']);
                }
                
                $res->free_result();
                header('Location: ' . DASHBOARD_PAGE);
                exit;
            } else {
                error_log("validateLoginCredentials: Password verification failed for username: $username");
                $_SESSION['status'] = "Invalid login credentials";
                $_SESSION['status_code'] = "error";
            }
        } else {
            error_log("validateLoginCredentials: No user found for username: $username");
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
    public static function loadSpinner()
    {
        ob_start();
        include '../assets/loader/spinner.html';
        $content = ob_get_clean();
        return $content;
    }

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
                "Authorization: Basic " . SMS_TOKEN,
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

    public static function checkSMSBalance()
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.giantsms.com/api/v1/balance",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Accept: */*",
                "Authorization: Basic " . SMS_TOKEN,
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
