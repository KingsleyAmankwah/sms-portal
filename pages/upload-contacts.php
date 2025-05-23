<?php

/**
 * Author: [Your Name]
 * Purpose: Upload contacts (individual or bulk) for the SMS Portal application.
 */
require_once 'C:\xampp\htdocs\dashboard-master\vendor\autoload.php';
include '../components/header.php';

use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\Validator;
use SMSPortalExtensions\UIActions;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Ensure customLog is defined
if (!function_exists('customLog')) {
    define('CUSTOM_LOG', 'C:\xampp\htdocs\dashboard-master\debug.log');
    function customLog($message) {
        file_put_contents(CUSTOM_LOG, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
    }
}

// Generate CSRF token
$csrf_token = $_SERVER['REQUEST_METHOD'] === 'GET'
    ? Authentication::createToken()
    : ($_SESSION['csrf_token'] ?? '');

// Fetch groups for dropdown
$conn = MySQLDatabase::createConnection();
$groups = [];
if ($conn) {
    $result = MySQLDatabase::sqlSelect($conn, 'SELECT name FROM groups WHERE user_id = ?', 'i', $_SESSION['USER_ID']);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row['name'];
        }
        $result->free_result();
    }
    $conn->close();
}

// Persist form data on error
$individual_data = [
    'name' => $_POST['name'] ?? '',
    'phone_number' => $_POST['phone_number'] ?? '',
    'email' => $_POST['email'] ?? '',
    'group' => $_POST['group'] ?? 'All',
    'company' => $_POST['company'] ?? '',
    'notes' => $_POST['notes'] ?? ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $upload_type = $_POST['upload_type'] ?? '';

    if (!Authentication::validateToken($token)) {
        $_SESSION['status'] = 'Invalid CSRF token';
        $_SESSION['status_code'] = 'error';
    } elseif ($upload_type === 'individual') {
        // Individual upload
        $name = $_POST['name'] ?? '';
        $phone_number = $_POST['phone_number'] ?? '';
        $email = $_POST['email'] ?? '';
        $group = $_POST['group'] ?? 'All';
        $company = $_POST['company'] ?? '';
        $notes = $_POST['notes'] ?? '';

        // Sanitize inputs
        $name = Validator::validateUserInput($name);
        $phone_number = Validator::validateUserInput($phone_number);
        $email = Validator::validateUserInput($email);
        $group = Validator::validateUserInput($group);
        $company = Validator::validateUserInput($company);
        $notes = Validator::validateUserInput($notes);

        // Validate inputs
        if (empty($name) || empty($phone_number)) {
            $_SESSION['status'] = 'Name and phone number are required';
            $_SESSION['status_code'] = 'error';
        } elseif (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone_number)) {
            $_SESSION['status'] = 'Invalid phone number format (e.g., +233123456789)';
            $_SESSION['status_code'] = 'error';
        } elseif ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['status'] = 'Invalid email format';
            $_SESSION['status_code'] = 'error';
        } else {
            $conn = MySQLDatabase::createConnection();
            if ($conn) {
                $result = MySQLDatabase::sqlInsert(
                    $conn,
                    'INSERT INTO contacts (user_id, name, phone_number, email, `group`, company, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    'issssss',
                    $_SESSION['USER_ID'],
                    $name,
                    $phone_number,
                    $email ?: null,
                    $group,
                    $company ?: null,
                    $notes ?: null
                );
                if ($result !== -1) {
                    $_SESSION['status'] = 'Contact added successfully';
                    $_SESSION['status_code'] = 'success';
                    customLog("Individual contact added: $name, $phone_number, group: $group");
                    // Clear form data on success
                    $individual_data = ['name' => '', 'phone_number' => '', 'email' => '', 'group' => 'All', 'company' => '', 'notes' => ''];
                } else {
                    $_SESSION['status'] = 'Failed to add contact';
                    $_SESSION['status_code'] = 'error';
                    customLog("Failed to add individual contact: $name, $phone_number");
                }
                $conn->close();
            } else {
                $_SESSION['status'] = 'Database connection failed';
                $_SESSION['status_code'] = 'error';
            }
        }
    } elseif ($upload_type === 'bulk') {
        // Bulk upload
        $group = $_POST['group'] ?? 'All';
        $group = Validator::validateUserInput($group);

        if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['status'] = 'Please upload a file';
            $_SESSION['status_code'] = 'error';
        } else {
            $file = $_FILES['file'];
            $allowed_types = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types) || $file['size'] > $max_size) {
                $_SESSION['status'] = 'Invalid file type or size (CSV/Excel, max 5MB)';
                $_SESSION['status_code'] = 'error';
            } else {
                try {
                    $spreadsheet = IOFactory::load($file['tmp_name']);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();
                    $headers = array_map('strtolower', array_shift($rows)); // Remove header row

                    // Validate required columns
                    if (!in_array('name', $headers) || !in_array('phone number', $headers)) {
                        $_SESSION['status'] = 'File must contain Name and Phone Number columns';
                        $_SESSION['status_code'] = 'error';
                    } else {
                        $conn = MySQLDatabase::createConnection();
                        if ($conn) {
                            $success_count = 0;
                            $errors = [];
                            foreach ($rows as $row) {
                                $data = array_combine($headers, $row);
                                $name = Validator::validateUserInput($data['name'] ?? '');
                                $phone_number = Validator::validateUserInput($data['phone number'] ?? '');
                                $email = Validator::validateUserInput($data['email'] ?? '');
                                $row_group = Validator::validateUserInput($data['group'] ?? $group);
                                $company = Validator::validateUserInput($data['company'] ?? '');
                                $notes = Validator::validateUserInput($data['notes'] ?? '');

                                if (empty($name) || empty($phone_number)) {
                                    $errors[] = "Skipping row: Missing name or phone number";
                                    continue;
                                }
                                if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone_number)) {
                                    $errors[] = "Skipping row for $name: Invalid phone number ($phone_number)";
                                    continue;
                                }
                                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $errors[] = "Skipping row for $name: Invalid email ($email)";
                                    continue;
                                }

                                $result = MySQLDatabase::sqlInsert(
                                    $conn,
                                    'INSERT INTO contacts (user_id, name, phone_number, email, `group`, company, notes) VALUES (?, ?, ?, ?, ?, ?, ?)',
                                    'issssss',
                                    $_SESSION['USER_ID'],
                                    $name,
                                    $phone_number,
                                    $email ?: null,
                                    $row_group,
                                    $company ?: null,
                                    $notes ?: null
                                );
                                if ($result !== -1) {
                                    $success_count++;
                                } else {
                                    $errors[] = "Failed to add contact: $name, $phone_number";
                                }
                            }
                            if ($success_count > 0) {
                                $_SESSION['status'] = "Successfully added $success_count contacts";
                                $_SESSION['status_code'] = 'success';
                                customLog("Bulk upload: $success_count contacts added, group: $group");
                            } else {
                                $_SESSION['status'] = 'No contacts added';
                                $_SESSION['status_code'] = 'error';
                            }
                            if ($errors) {
                                $_SESSION['status'] .= '. Errors: ' . implode('; ', $errors);
                            }
                            $conn->close();
                        } else {
                            $_SESSION['status'] = 'Database connection failed';
                            $_SESSION['status_code'] = 'error';
                        }
                    }
                } catch (Exception $e) {
                    $_SESSION['status'] = 'Failed to process file: ' . $e->getMessage();
                    $_SESSION['status_code'] = 'error';
                    customLog("Bulk upload error: " . $e->getMessage());
                }
            }
        }
    } else {
        $_SESSION['status'] = 'Invalid upload type';
        $_SESSION['status_code'] = 'error';
    }
}
?>

<div class="content">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card p-4">
                <div class="d-flex justify-content-between mb-4">
                    <h4 class="card-title">Upload Contacts</h4>
                </div>
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#individual">Individual Upload</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#bulk">Bulk Upload</a>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- Individual Upload Form -->
                    <div class="tab-pane fade show active" id="individual">
                        <form action="" method="POST" autocomplete="off" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="upload_type" value="individual">
                            <div class="form-group">
                                <label for="name">Name</label>
                                <input type="text" class="form-control" name="name" id="name" placeholder="Enter name" required value="<?php echo htmlspecialchars($individual_data['name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone_number">Phone Number (e.g., +233123456789)</label>
                                <input type="text" class="form-control" name="phone_number" id="phone_number" placeholder="Enter phone number" required value="<?php echo htmlspecialchars($individual_data['phone_number']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email (optional)</label>
                                <input type="email" class="form-control" name="email" id="email" placeholder="Enter email" value="<?php echo htmlspecialchars($individual_data['email']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="group">Group</label>
                                <select class="form-control" name="group" id="group">
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $g === $individual_data['group'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="company">Company (optional)</label>
                                <input type="text" class="form-control" name="company" id="company" placeholder="Enter company" value="<?php echo htmlspecialchars($individual_data['company']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes (optional)</label>
                                <textarea class="form-control" name="notes" id="notes" rows="4" placeholder="Enter notes"><?php echo htmlspecialchars($individual_data['notes']); ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Add Contact</button>
                        </form>
                    </div>
                    <!-- Bulk Upload Form -->
                    <div class="tab-pane fade" id="bulk">
                        <form action="" method="POST" enctype="multipart/form-data" autocomplete="off" class="mt-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="upload_type" value="bulk">
                            <div class="form-group">
                                <label for="group_bulk">Group (applied if not specified in file)</label>
                                <select class="form-control" name="group" id="group_bulk">
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?php echo htmlspecialchars($g); ?>" <?php echo $g === 'All' ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($g); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Upload File (CSV, XLS, XLSX)</label>
                                <div class="upload-area text-center p-4 border rounded">
                                    <p><a href="#" onclick="$('#file').click()">Browse</a> device for your file</p>
                                    <i class="nc-icon nc-cloud-upload-94" style="font-size: 2em;"></i>
                                    <input type="file" class="form-control-file" name="file" id="file" accept=".csv,.xls,.xlsx" style="display:none;" required onchange="$('#file-name').text(this.files[0].name)">
                                    <p id="file-name" class="mt-2"></p>
                                    <small class="form-text text-muted">Supports CSV and Excel files</small>
                                </div>
                            </div>
                            <p><strong>File Format Requirements:</strong></p>
                            <ul>
                                <li>File must be in CSV or Excel (.xlsx, .xls) format</li>
                                <li>Must contain at least two columns: Name and Phone Number</li>
                                <li>Phone numbers should include country code (e.g., +233)</li>
                                <li>Optional columns: Email, Group, Company, Notes</li>
                            </ul>
                            <p><a href="../assets/files/sample_contacts.xlsx" download>Download Sample Excel File</a></p>
                            <button type="submit" class="btn btn-primary w-100">Import Contacts</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.upload-area {
    background: #f8f9fa;
    border: 2px dashed #ccc;
    transition: all 0.3s ease;
}
.upload-area:hover {
    background: #e9ecef;
    border-color: #aaa;
}
.upload-area p {
    margin: 0;
    font-size: 1.1em;
}
.upload-area small {
    display: block;
    margin-top: 0.5em;
}
</style>

<?php
include '../components/footer.php';
?>