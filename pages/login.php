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

// Only generate CSRF token for GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $csrf_token = Authentication::createToken();
} else {
    $csrf_token = $_SESSION['csrf_token'] ?? '';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = $_POST['login_email'] ?? '';
    $password = $_POST['password'] ?? '';
    $token = $_POST['csrf_token'] ?? '';

    // ===== BRUTE FORCE PROTECTION =====
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $conn = MySQLDatabase::createConnection();
    if ($conn) {
        $recentAttempts = MySQLDatabase::sqlSelect(
            $conn,
            "SELECT COUNT(*) as attempts FROM login_logs
             WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            's',
            $ip
        );

        if ($recentAttempts && $recentAttempts->fetch_assoc()['attempts'] > 10) {
            $_SESSION['status'] = "Too many login attempts. Please wait 1 hour.";
            $_SESSION['status_code'] = "error";
            $conn->close();
            exit;
        }
    }
    // ===== END BRUTE FORCE PROTECTION =====

    // Validate CSRF token
    if (!Authentication::validateToken($token)) {
        $_SESSION['status'] = "Invalid CSRF token";
        $_SESSION['status_code'] = "error";
    } else {
        $login_identifier = Validator::validateUserInput($login_identifier);
        $password = Validator::validateUserInput($password);

        if (empty($login_identifier) || empty($password)) {
            $_SESSION['status'] = "Please fill in all fields";
            $_SESSION['status_code'] = "error";
        } else {
            $conn = MySQLDatabase::createConnection();
            if ($conn) {
                validateEmailLogin($conn, $login_identifier, $password);
                $conn->close();
            } else {
                $_SESSION['status'] = "Database connection failed";
                $_SESSION['status_code'] = "error";
            }
        }
    }
}


/**
 * Validate login credentials using email only
 */
function validateEmailLogin($conn, $email, $password)
{
    try {
        // Input validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['status'] = "Please enter a valid email address";
            $_SESSION['status_code'] = "error";
            return;
        }

        // Check account lock status first
        $lockCheck = MySQLDatabase::sqlSelect(
            $conn,
            'SELECT locked_until FROM users WHERE email = ?',
            's',
            $email
        );

        if ($lockCheck && $lockCheck->num_rows > 0) {
            $user = $lockCheck->fetch_assoc();
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                logLoginAttempt($conn, null, $email, 'account_locked', $_SERVER['REMOTE_ADDR'] ?? '');
                $_SESSION['status'] = "Account temporarily locked. Try again later.";
                $_SESSION['status_code'] = "error";
                return;
            }
        }

        // Get user data
        $result = MySQLDatabase::sqlSelect(
            $conn,
            'SELECT id, email, password, status, type, failed_login_attempts
             FROM users
             WHERE email = ? AND status = "active"',
            's',
            $email
        );

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $result->free_result();

            // Verify password
            if (password_verify($password, $user['password'])) {
                MySQLDatabase::sqlUpdate(
                    $conn,
                    'UPDATE users
                     SET failed_login_attempts = 0,
                         locked_until = NULL,
                         last_login = NOW()
                     WHERE id = ?',
                    'i',
                    $user['id']
                );

                // Set session variables
                $_SESSION['USER_ID'] = $user['id'];
                $_SESSION['LOGIN_TIME'] = time();
                $_SESSION['USER_ROLE'] = $user['type'];
                $_SESSION['EMAIL'] = $user['email'];

                // Log successful attempt
                logLoginAttempt($conn, $user['id'], $email, 'success', $_SERVER['REMOTE_ADDR'] ?? '');

                // Redirect to dashboard
                header('Location: ' . DASHBOARD_PAGE);
                exit;
            } else {
                handleFailedLogin($conn, $user, $email);
            }
        } else {
            // User not found or inactive
            logLoginAttempt($conn, null, $email, 'user_not_found', $_SERVER['REMOTE_ADDR'] ?? '');
            $_SESSION['status'] = "Invalid login credentials";
            $_SESSION['status_code'] = "error";
        }
    } catch (Exception $e) {
        $_SESSION['status'] = "System error, please try again later";
        $_SESSION['status_code'] = "error";
    }
}

/**
 * Handle failed login attempts
 */
function handleFailedLogin($conn, $user, $email)
{
    // Increment failed attempts
    $newAttempts = $user['failed_login_attempts'] + 1;
    $lockTime = ($newAttempts >= 5) ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : null;

    MySQLDatabase::sqlUpdate(
        $conn,
        'UPDATE users
         SET failed_login_attempts = ?,
             locked_until = ?
         WHERE id = ?',
        'isi',
        $newAttempts,
        $lockTime,
        $user['id']
    );

    // Log the attempt
    logLoginAttempt($conn, $user['id'], $email, 'invalid_password', $_SERVER['REMOTE_ADDR'] ?? '');

    // Set user feedback
    $message = "Invalid credentials";
    if ($newAttempts >= 3) {
        $remaining = 5 - $newAttempts;
        $message .= ($remaining > 0)
            ? ". $remaining attempts remaining"
            : ". Account locked for 30 minutes";
    }

    $_SESSION['status'] = $message;
    $_SESSION['status_code'] = "error";
}

/**
 * Log login attempts for security monitoring
 */
function logLoginAttempt($conn, $user_id, $login_identifier, $status, $ip_address)
{
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $query = 'INSERT INTO login_logs (user_id, login_identifier, status, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)';
    MySQLDatabase::sqlInsert($conn, $query, 'issss', $user_id, $login_identifier, $status, $ip_address, $user_agent);
}

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
                        <input type="email"
                            class="form-control"
                            name="login_email"
                            placeholder="Enter your email address"
                            autocomplete="email"
                            required>
                    </div>
                    <div class="form-group">
                        <input type="password"
                            class="form-control"
                            name="password"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            required>
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

    <style>
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: block;
        }

        .login-help {
            margin-top: 15px;
            text-align: center;
        }

        .login-help p {
            margin: 0;
            color: #555;
        }
    </style>
</body>
<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Loading...';
    });
</script>

</html>