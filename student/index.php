<?php
/**
 * SmartFix AI - Student Entry File
 * Enforces student authorization prior to granting dashboard access.
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Verify the user holds the 'student' permission role
Session::requireRole('student');

// Redirect to dashboard page
header("Location: dashboard.php");
exit();
