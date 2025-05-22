<?php
session_start();
require_once '../core/app-config.php';
require_once '../core/extensions/classes.php';

use SMSPortalExtensions\MySQLDatabase;
use SMSPortalExtensions\Authentication;
use SMSPortalExtensions\Validator;
use SMSPortalExtensions\UIActions;

// Check if already logged in
if (isset($_SESSION['USER_ID'])) {
    header('Location: ' . DASHBOARD_PAGE);
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!Authentication::validateToken($token)) {
        $_SESSION['status'] = "Invalid CSRF token";
        $_SESSION['status_code'] = "error";
    } else {
        // Validate user input
        $username = Validator::validateUserInput($username);
        $password = Validator::validateUserInput($password);

        // Connect to database
        $conn = MySQLDatabase::createConnection();
        if ($conn) {
            // Validate login credentials
            Validator::validateLoginCredentials($conn, $username, $password);
            $conn->close();
        } else {
            $_SESSION['status'] = "Database connection failed";
            $_SESSION['status_code'] = "error";
        }
    }
}

// Generate CSRF token for the form
$csrf_token = Authentication::createToken();
$page_title = "Login - " . APP_BASE_TITLE;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="icon" type="image/x-icon" href="../assets/img/teksed-logo.png" />
    <link rel="stylesheet" href="../assets/sweetalert/sweetalert2.min.css" />
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="login-logo">
                <img src="../assets/img/tek.png" alt="TekSED Logo">
                <h1>SMS Portal</h1>
            </div>
            <?php
            if (isset($_SESSION['status'])) {
                echo UIActions::showAlert(
                    $_SESSION['status_code'] === 'error' ? 'Error' : 'Success',
                    $_SESSION['status'],
                    $_SESSION['status_code']
                );
                unset($_SESSION['status'], $_SESSION['status_code']);
            }
            ?>
            <div class="login-box">
                <p class="login-box-msg">Sign in to send your custom SMS messages</p>
                <form action="" method="POST" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div class="form-group">
                        <input type="text" class="form-control" name="username" placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-login" name="login">Sign In</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="login-right">
            <img src="../assets/img/sms.jpg" alt="SMS Illustration" class="illustration">
        </div>
    </div>
    <script src="../assets/sweetalert/sweetalert2.all.min.js"></script>
</body>
</html>