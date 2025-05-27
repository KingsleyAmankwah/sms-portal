<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
// Define constants for the application configuration
define("APP_URL", $_ENV['APP_URL'] ?? '');
define("APP_LOGO_URL", $_ENV['APP_LOGO_URL'] ?? '');
define("APP_VERSION", $_ENV['APP_VERSION'] ?? '1.0.0');
define("APP_BASE_TITLE", $_ENV['APP_BASE_TITLE'] ?? 'SMS Portal');


#Email Settings
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? '');
define('SUPPORT_EMAIL', $_ENV['SUPPORT_EMAIL'] ?? '');
define('SUPPORT_EMAIL_PASSWORD', $_ENV['SUPPORT_EMAIL_PASSWORD'] ?? '');
define('SUPPORT_EMAIL_SUBJECT', $_ENV['SUPPORT_EMAIL_SUBJECT'] ?? 'SMS Portal Support');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? '');
define('SMTP_ENCRYPTION', $_ENV['SMTP_ENCRYPTION'] ?? 'tls');


# Page Settings
define("INDEX_PAGE", $_ENV['INDEX_PAGE'] ?? 'login.php');
define("DASHBOARD_PAGE", $_ENV['DASHBOARD_PAGE'] ?? 'dashboard.php');

# Giant SMS Settings
define("SMS_API_USERNAME", $_ENV['SMS_API_USERNAME'] ?? '');
define("SMS_API_PASSWORD", $_ENV['SMS_API_PASSWORD'] ?? '');
define("SMS_API_TOKEN", $_ENV['SMS_API_TOKEN'] ?? '');
define("SMS_SENDER_ID", $_ENV['SMS_SENDER_ID'] ?? 'Test');

# MySQL Database Credentials
define("DB_HOST", $_ENV['DB_HOST'] ?? 'localhost');
define("RESOURCE_DATABASE", $_ENV['RESOURCE_DATABASE'] ?? 'sms_portal');
define("DB_USERNAME", $_ENV['DB_USERNAME'] ?? 'root');
define("DB_PASSWORD", $_ENV['DB_PASSWORD'] ?? '');

# Security Settings
define("MAX_LOGIN_ATTEMPTS_PER_HOUR", $_ENV['MAX_LOGIN_ATTEMPTS_PER_HOUR'] ?? 5);
define("CSRF_TOKEN_SECRET", $_ENV['CSRF_TOKEN_SECRET'] ?? 'default_secret_key');

# Set timezone
date_default_timezone_set("UTC");
