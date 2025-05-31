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
     * Phone validation rules for different countries
     * @var array
     * This array contains rules for phone number validation based on country codes.
     * Each entry includes:
     * - min_length: Minimum length of the phone number including country code
     * - max_length: Maximum length of the phone number including country code
     * - pattern: Regular expression to validate the phone number format
     * - example: Example phone number format for the country
     */
    private $phoneValidationRules = [
        '233' => [ // Ghana
            'min_length' => 13, // +233 + 9 digits = 13 total
            'max_length' => 13,
            'pattern' => '/^\+233[2-9]\d{8}$/', // Ghana mobile numbers start with 2-9 after country code
            'example' => '+233553157024'
        ],
        '234' => [ // Nigeria
            'min_length' => 14,
            'max_length' => 14,
            'pattern' => '/^\+234[7-9]\d{9}$/', // Nigerian mobile numbers start with 7-9 after country code
            'example' => '+2348012345678'
        ],
        '1' => [ // US/Canada
            'min_length' => 12,
            'max_length' => 12,
            'pattern' => '/^\+1[2-9]\d{9}$/', // US/Canada numbers start with 2-9 after country code
            'example' => '+12345678901'
        ],
        '44' => [ // UK
            'min_length' => 13,
            'max_length' => 13,
            'pattern' => '/^\+44[1-9]\d{9}$/', // UK mobile numbers start with 1-9 after country code
            'example' => '+441234567890'
        ],
        '91' => [ // India
            'min_length' => 14,
            'max_length' => 14,
            'pattern' => '/^\+91[789]\d{9}$/', // Indian mobile numbers start with 7-9 after country code
            'example' => '+919876543210'
        ],
        '61' => [ // Australia
            'min_length' => 13,
            'max_length' => 13,
            'pattern' => '/^\+61[4]\d{8}$/', // Australian mobile numbers start with 4 after country code
            'example' => '+61412345678'
        ],
        '27' => [ // South Africa
            'min_length' => 13,
            'max_length' => 13,
            'pattern' => '/^\+27[6-8]\d{8}$/', // South African mobile numbers start with 6-8 after country code
            'example' => '+27612345678'
        ],
    ];

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

        $this->validatePhoneNumber($data['phone_number']);

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

            // phone validation method with row prefix for errors
            try {
                $this->validatePhoneNumber($contactData['phone_number']);
            } catch (SMSPortalException $e) {
                throw new SMSPortalException($rowPrefix . $e->getMessage());
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
     * Validate phone number with country-specific rules
     * @param string $phoneNumber The phone number to validate
     * @return bool True if valid
     * @throws SMSPortalException For validation errors
     */
    private function validatePhoneNumber($phoneNumber)
    {
        // Remove any spaces or special characters except +
        $cleanPhone = preg_replace('/[^\+\d]/', '', $phoneNumber);

        // Check if phone starts with +
        if (strpos($cleanPhone, '+') !== 0) {
            throw new SMSPortalException("Phone number must start with country code (e.g., +233553157024)");
        }

        // Extract country code
        $countryCode = $this->extractCountryCode($cleanPhone);

        if (!$countryCode) {
            throw new SMSPortalException("Invalid or unsupported country code in phone number: {$phoneNumber}");
        }

        // Get validation rules for this country
        if (!isset($this->phoneValidationRules[$countryCode])) {
            throw new SMSPortalException("Unsupported country code: +{$countryCode}. Supported countries: " . $this->getSupportedCountries());
        }

        $rules = $this->phoneValidationRules[$countryCode];

        // Check length
        $phoneLength = strlen($cleanPhone);
        if ($phoneLength < $rules['min_length']) {
            throw new SMSPortalException("Phone number too short for country code +{$countryCode}. Expected format: {$rules['example']}");
        }

        if ($phoneLength > $rules['max_length']) {
            throw new SMSPortalException("Phone number too long for country code +{$countryCode}. Expected format: {$rules['example']}");
        }

        // Check pattern
        if (!preg_match($rules['pattern'], $cleanPhone)) {
            throw new SMSPortalException("Invalid phone number format for country code +{$countryCode}. Expected format: {$rules['example']}");
        }

        return true;
    }

    /**
     * Extract country code from phone number
     * @param string $phoneNumber Clean phone number with +
     * @return string|false Country code or false if not found
     */
    private function extractCountryCode($phoneNumber)
    {
        // Remove the + sign for processing
        $number = substr($phoneNumber, 1);

        // Check each country code from longest to shortest to avoid conflicts
        $countryCodes = array_keys($this->phoneValidationRules);

        // Sort by length descending to check longer codes first
        usort($countryCodes, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($countryCodes as $code) {
            if (strpos($number, $code) === 0) {
                return $code;
            }
        }

        return false;
    }

    /**
     * Get list of supported countries for error messages
     * @return string Comma-separated list of supported country codes
     */
    private function getSupportedCountries()
    {
        $codes = array_keys($this->phoneValidationRules);
        return '+' . implode(', +', $codes);
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
