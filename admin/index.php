<?php
/**
 * SmartFix AI - Admin Entry File
 * Enforces admin authorization prior to granting dashboard access.
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Verify the user holds the 'admin' permission role
Session::requireRole('admin');

// Redirect to dashboard page
header("Location: dashboard.php");
exit();
