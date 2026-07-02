<?php
/**
 * SmartFix AI - Staff Entry File
 * Enforces staff authorization prior to granting dashboard access.
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Verify the user holds the 'staff' permission role
Session::requireRole('staff');

// Redirect to dashboard page
header("Location: dashboard.php");
exit();
