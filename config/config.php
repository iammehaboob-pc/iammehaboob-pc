<?php
/**
 * SmartFix AI - Configuration File
 * Contains global constants, database details, security parameters, and API credentials.
 */

// Prevent direct access to config file
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Environment Mode (development / production)
define('ENV', 'development');

// Error reporting based on environment
if (ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'smartfix_db');
define('DB_USER', 'root');
define('DB_PASS', '@iammehaboob');
define('DB_CHARSET', 'utf8mb4');

// Google Gemini API Configuration
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent');

// PHPMailer SMTP Settings
define('SMTP_HOST', 'smtp.mailtrap.io'); // Change to production SMTP (e.g., smtp.gmail.com)
define('SMTP_PORT', 2525);               // 587 for TLS, 465 for SSL, 2525 for Mailtrap
define('SMTP_USER', 'your_smtp_username');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_SECURE', 'tls');            // 'tls' or 'ssl'
define('SMTP_FROM_EMAIL', 'no-reply@smartfixai.edu');
define('SMTP_FROM_NAME', 'SmartFix AI Support');

// Application Paths & URL Settings
define('SITE_URL', 'http://localhost:8000');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('ALLOWED_FILE_EXTENSIONS', ['jpg', 'jpeg', 'png']);
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB in bytes

// Security & Session Settings
define('SESSION_LIFETIME', 1800); // 30 minutes in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
define('CSRF_TOKEN_EXPIRE', 7200); // 2 hours in seconds
