<?php
# Global App Constants
define("APP_URL", "localhost/sms_portal");
define("APP_LOGO_URL", "assets/images/logo.png");
define("APP_VERSION", "1.0.0");
define("APP_BASE_TITLE", "SMS Portal");
# Page Settings
define("INDEX_PAGE", "index.html");
define("DASHBOARD_PAGE", "dashboard.php");

# Giant SMS Settings
define("SMS_API_USERNAME", "sms_api_username");
define("SMS_API_SECRET", "sms_api_secret");
define("SMS_SENDER_ID", "sender_id");
define("SMS_TOKEN", "sms_token");

# MySQL Database Credentials
define("DB_HOST", 'localhost');
define("RESOURCE_DATABASE", 'sms_portal');
define("DB_USERNAME", 'root');
define("DB_PASSWORD", '');

# Security Settings
define("MAX_LOGIN_ATTEMPTS_PER_HOUR", 5);
define("CSRF_TOKEN_SECRET", bin2hex(random_bytes(32))); // Generate a random secret

# Set timezone
date_default_timezone_set("UTC");
