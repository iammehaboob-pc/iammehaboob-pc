<?php
/**
 * SmartFix AI - Logout Script
 */

require_once __DIR__ . '/config/bootstrap.php';

if (Session::isLoggedIn()) {
    try {
        $db = Database::getInstance()->getConnection();
        $userId = Session::get('user_id');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Log logout audit
        $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'Logout', ?, ?)");
        $auditStmt->execute([$userId, $ip, $userAgent]);
    } catch (PDOException $e) {
        error_log("Logout auditing failed: " . $e->getMessage());
    }
    
    // Destroy Session
    Session::destroy();
}

header("Location: " . SITE_URL . "/login.php");
exit();
