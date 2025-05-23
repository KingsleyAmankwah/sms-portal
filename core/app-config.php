<?php
# Global App Constants
define("APP_URL", "localhost/sms_portal");
define("APP_LOGO_URL", "assets/images/logo.png");
define("APP_VERSION", "1.0.0");
define("APP_BASE_TITLE", "SMS Portal");
# Page Settings
define("INDEX_PAGE", "login.php");
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
define("CSRF_TOKEN_SECRET", "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6");

# Set timezone
date_default_timezone_set("UTC");
