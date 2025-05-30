<?php
require_once __DIR__ . '/../core/app-config.php';
require_once __DIR__ . '/../core/extensions/classes.php';
require_once __DIR__ . '/../core/exceptions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SMSPortalExceptions\SMSPortalException;

// Ensure proper JSON response
header('Content-Type: application/json');

class ContactUploader
{
    private $conn;
    private $errors = [];
    private $successCount = 0;

    /**
     * Initialize database connection and session
     */
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

    /**
     * Process contact upload request (individual or bulk)
     * @return string JSON encoded response
     * @throws SMSPortalException For various validation errors
     */
    public function process()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw SMSPortalException::invalidRequest();
            }

            if (!Authentication::validateToken($_POST['csrf_token'] ?? '')) {
                throw SMSPortalException::invalidToken();
            }

            switch ($_POST['upload_type'] ?? '') {
                case 'individual':
                    $result = $this->processIndividualUpload();
                    break;
                case 'bulk':
                    $result = $this->processBulkUpload();
                    break;
                default:
                    throw SMSPortalException::invalidUploadType();
            }

            return $this->sendResponse($result);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage());
        } finally {
            if ($this->conn) {
                $this->conn->close();
            }
        }
    }

    /**
     * Process individual contact upload
     * @return string Success message
     * @throws SMSPortalException For validation or duplicate entry errors
     */
    private function processIndividualUpload()
    {
        $data = [
            'name' => $this->sanitizeInput($_POST['name'] ?? ''),
            'phone_number' => $this->sanitizeInput($_POST['phone_number'] ?? ''),
            'email' => $this->sanitizeInput($_POST['email'] ?? ''),
            'group' => $this->sanitizeInput($_POST['group'] ?? 'All'),
            'company' => $this->sanitizeInput($_POST['company'] ?? ''),
            'notes' => $this->sanitizeInput($_POST['notes'] ?? '')
        ];

        $this->validateContact($data);

        if (!empty($this->errors)) {
            throw SMSPortalException::requiredFields();
        }

        if ($this->isPhoneNumberDuplicate($data['phone_number'])) {
            throw SMSPortalException::duplicatePhone();
        }

        if (!empty($data['email']) && $this->isEmailDuplicate($data['email'])) {
            throw SMSPortalException::duplicateEmail();
        }

        $result = $this->insertContact($data);
        if ($result === -1) {
            throw SMSPortalException::databaseError();
        }

        return 'Contact added successfully';
    }

    /**
     * Process bulk contact upload from file
     * @return string Success message with count of added contacts
     * @throws SMSPortalException For file handling, validation, or database errors
     */
    private function processBulkUpload()
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw SMSPortalException::fileError();
        }

        $file = $_FILES['file'];
        $this->validateFile($file);

        $spreadsheet = IOFactory::load($file['tmp_name']);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        if (empty($rows)) {
            throw SMSPortalException::fileEmpty();
        }

        $headers = array_map('strtolower', array_map('trim', array_shift($rows)));
        $this->validateHeaders($headers);

        $defaultGroup = $this->sanitizeInput($_POST['group'] ?? 'All');

        foreach ($rows as $index => $row) {
            try {
                $this->processRow($row, $headers, $defaultGroup, $index + 2);
            } catch (Exception $e) {
                $this->errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        $message = "Successfully added {$this->successCount} contacts";
        if (!empty($this->errors)) {
            $message .= '. Errors: ' . $this->formatErrors();
        }

        if ($this->successCount === 0) {
            throw SMSPortalException::noContactsAdded($this->formatErrors());
        }

        return $message;
    }

    /**
     * Validate uploaded file properties
     * @param array $file The $_FILES array element
     * @throws SMSPortalException For file size or type validation errors
     */
    private function validateFile($file)
    {
        $allowedTypes = ['application/vnd.ms-excel', 'text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if ($file['size'] > $maxSize) {
            throw SMSPortalException::fileSizeError($maxSize);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw SMSPortalException::fileTypeError();
        }
    }

    /**
     * Validate required headers in uploaded file
     * @param array $headers Array of column headers from the file
     * @throws SMSPortalException When required headers are missing
     */
    private function validateHeaders($headers)
    {
        if (!in_array('name', $headers) || !in_array('phone number', $headers)) {
            throw SMSPortalException::invalidHeaders();
        }
    }

    /**
     * Validate contact data fields
     * @param array $data Contact data array
     * @throws SMSPortalException For invalid or missing required fields
     */
    private function validateContact($data)
    {
        if (empty($data['name']) || empty($data['phone_number']) || empty($data['group'])) {
            throw SMSPortalException::requiredFields();
        }

        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $data['phone_number'])) {
            throw SMSPortalException::invalidPhoneFormat();
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw SMSPortalException::invalidEmailFormat();
        }
    }

    /**
     * Check if phone number already exists in database
     * @param string $phone Phone number to check
     * @return bool True if duplicate exists
     */
    private function isPhoneNumberDuplicate($phone)
    {
        if (empty($phone)) {
            return false;
        }

        $result = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT id FROM contacts WHERE user_id = ? AND phone_number = ?',
            'is',
            $_SESSION['USER_ID'],
            $phone
        );

        $isDuplicate = $result && $result->num_rows > 0;
        if ($result) {
            $result->free_result();
        }
        return $isDuplicate;
    }

    /**
     * Check if email already exists in database
     * @param string $email Email to check
     * @return bool True if duplicate exists
     */
    private function isEmailDuplicate($email)
    {
        if (empty($email)) {
            return false;
        }

        $result = MySQLDatabase::sqlSelect(
            $this->conn,
            'SELECT id FROM contacts WHERE user_id = ? AND email = ?',
            'is',
            $_SESSION['USER_ID'],
            $email
        );

        $isDuplicate = $result && $result->num_rows > 0;
        if ($result) {
            $result->free_result();
        }
        return $isDuplicate;
    }


    /**
     * Insert new contact into database
     * @param array $data Contact data array
     * @return int|bool Last insert ID or -1 on failure
     */
    private function insertContact($data)
    {
        return MySQLDatabase::sqlInsert(
            $this->conn,
            'INSERT INTO contacts (user_id, name, phone_number, email, `group`, company, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
            'issssss',
            $_SESSION['USER_ID'],
            $data['name'],
            $data['phone_number'],
            $data['email'] ?: null,
            $data['group'],
            $data['company'] ?: null,
            $data['notes'] ?: null
        );
    }

    /**
     * Sanitize user input
     * @param string $input Raw input string
     * @return string Sanitized input string
     */
    private function sanitizeInput($input)
    {
        return Validator::validateUserInput(trim($input));
    }


    /**
     * Format errors for display
     * @return string Formatted error message
     */
    private function formatErrors()
    {
        if (empty($this->errors)) {
            return '';
        }

        $errorCount = count($this->errors);
        $displayErrors = array_slice($this->errors, 0, 5);

        $message = "Found {$errorCount} issue" . ($errorCount > 1 ? 's' : '') . ":\n";
        foreach ($displayErrors as $error) {
            $message .= "• {$error}\n";
        }

        if ($errorCount > 5) {
            $message .= "• and " . ($errorCount - 5) . " more error(s)...";
        }

        return $message;
    }

    /**
     * Process a single row from bulk upload file
     * @param array $row Row data from file
     * @param array $headers Column headers
     * @param string $defaultGroup Default group if not specified
     * @param int $rowNumber Current row number for error reporting
     * @throws SMSPortalException For validation or database errors
     */
    private function processRow($row, $headers, $defaultGroup, $rowNumber)
    {
        if (empty(array_filter($row))) {
            return;
        }

        // Create data array from headers and row values
        $data = array_combine($headers, array_map(function ($value) {
            return trim($value ?? '');
        }, array_slice($row, 0, count($headers))));

        // Prepare error prefix for this row
        $rowPrefix = "Row {$rowNumber} ";

        try {
            $contactData = [
                'name' => $this->sanitizeInput($data['name']),
                'phone_number' => $this->sanitizeInput($data['phone number']),
                'email' => $this->sanitizeInput($data['email'] ?? ''),
                'group' => $this->sanitizeInput($data['group'] ?? $defaultGroup),
                'company' => $this->sanitizeInput($data['company'] ?? ''),
                'notes' => $this->sanitizeInput($data['notes'] ?? '')
            ];

            // Validate required fields
            if (empty($contactData['name'])) {
                throw new SMSPortalException($rowPrefix . "Name field is empty");
            }
            if (empty($contactData['phone_number'])) {
                throw new SMSPortalException($rowPrefix . "Phone number field is empty");
            }

            if (empty($contactData['group'])) {
                throw new SMSPortalException($rowPrefix . "Group field is empty");
            }

            // Validate phone format
            if (!preg_match('/^\+?[1-9]\d{1,14}$/', $contactData['phone_number'])) {
                throw new SMSPortalException($rowPrefix . "Invalid phone number format. Use format: +233xxxxxxxxx");
            }

            // Validate email if provided
            if (!empty($contactData['email']) && !filter_var($contactData['email'], FILTER_VALIDATE_EMAIL)) {
                throw new SMSPortalException($rowPrefix . "Invalid email format");
            }

            // Check duplicates
            if ($this->isPhoneNumberDuplicate($contactData['phone_number'])) {
                throw new SMSPortalException($rowPrefix . "Phone number ({$contactData['phone_number']}) already exists in contacts");
            }

            if (!empty($contactData['email']) && $this->isEmailDuplicate($contactData['email'])) {
                throw new SMSPortalException($rowPrefix . "Email address ({$contactData['email']}) already exists in contacts");
            }

            // Insert contact
            $result = $this->insertContact($contactData);
            if ($result === -1) {
                throw new SMSPortalException($rowPrefix . "Failed to add contact to database");
            }

            $this->successCount++;
        } catch (SMSPortalException $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    /**
     * Send success response
     * @param string $message Success message
     * @return string JSON encoded response
     */
    private function sendResponse($message)
    {
        return json_encode([
            'status' => $message,
            'status_code' => 'success'
        ]);
    }

    /**
     * Send error response
     * @param string $message Error message
     * @return string JSON encoded response
     */
    private function sendError($message)
    {
        return json_encode([
            'status' => $message,
            'status_code' => 'error'
        ]);
    }
}

// Execute the upload process
$uploader = new ContactUploader();
echo $uploader->process();
exit;
