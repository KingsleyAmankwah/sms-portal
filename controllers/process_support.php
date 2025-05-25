<?php
session_start();
require_once __DIR__ . '/../core/app-config.php';
require_once __DIR__ . '/../core/extensions/classes.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/exceptions.php';

use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\Validator;
use SMSPortalExceptions\SMSPortalException;
use SMSPortalExtensions\Authentication;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SupportManager
{
    private $conn;

    public function __construct()
    {
        $this->conn = MySQLDatabase::createConnection();
        if (!$this->conn) {
            throw new SMSPortalException('Database connection failed');
        }
    }

    public function processRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw SMSPortalException::InvalidRequest();
        }

        $action = Validator::validateUserInput($_POST['action'] ?? '');
        $csrf_token = $_POST['csrf_token'] ?? '';

        if (!Authentication::validateToken($csrf_token)) {
            throw new SMSPortalException('Invalid CSRF token');
        }

        if ($action !== 'send_support_email') {
            throw SMSPortalException::InvalidRequest();
        }

        return $this->sendEmail();
    }

    private function sendEmail()
    {
        $name = Validator::validateUserInput($_POST['name'] ?? '');
        $email = Validator::validateUserInput($_POST['email'] ?? '');
        $subject = Validator::validateUserInput($_POST['subject'] ?? '');
        $message = Validator::validateUserInput($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            throw new SMSPortalException('All fields are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SMSPortalException('Invalid email format');
        }

        // PHPMailer setup
        $mail = new PHPMailer(true);
        try {
            // SMTP settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SUPPORT_EMAIL;
            $mail->Password = SUPPORT_EMAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            // $mail->setFrom('SUPPORT_EMAIL', 'No-Reply Support System');
            $mail->addAddress(SUPPORT_EMAIL);
            $mail->Subject = "Support Request: $subject";
            $mail->isHTML(true);
            $mail->Body = "
                <h3>Support Request</h3>
                <p><strong>From:</strong> $name &lt;$email&gt;</p>
                <p><strong>Subject:</strong> $subject</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
            ";

            $mail->AltBody = "From: $name <$email>\n\nSubject: $subject\n\nMessage:\n$message";

            $mail->send();
            return json_encode([
                'message' => 'Support request sent successfully',
                'status_code' => 'success'
            ]);
        } catch (Exception $e) {
            throw new SMSPortalException("Failed to send support email: {$mail->ErrorInfo}");
        }
    }

    public function __destruct()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

try {
    $manager = new SupportManager();
    echo $manager->processRequest();
} catch (SMSPortalException $e) {
    http_response_code($e->getCode() === SMSPortalException::INVALID_REQUEST ? 400 : 500);
    echo json_encode([
        'message' => $e->getMessage(),
        'status_code' => 'error'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => 'Unexpected error',
        'status_code' => 'error'
    ]);
}
