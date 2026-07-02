<?php
/**
 * SmartFix AI - Application Bootstrap Loader
 * Loads configs, sets secure response headers, starts sessions, and auto-includes helpers.
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

// Load configurations
require_once __DIR__ . '/config.php';

// Set secure HTTP headers
header("X-Frame-Options: DENY"); // Protect against Clickjacking
header("X-Content-Type-Options: nosniff"); // Prevent MIME sniffing
header("X-XSS-Protection: 1; mode=block"); // XSS Filter protection
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");

// Strict Transport Security (HSTS) - only enable on secure connections
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Require architectural classes
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Session.php';
require_once __DIR__ . '/../includes/Security.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';
require_once __DIR__ . '/../includes/AIHelper.php';

// Initialize and start secure sessions
Session::start();
