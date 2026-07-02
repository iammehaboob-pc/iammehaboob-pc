<?php
/**
 * SmartFix AI - Reset Password Page
 */

require_once __DIR__ . '/config/bootstrap.php';

// Redirect if already logged in
if (Session::isLoggedIn()) {
    $role = Session::get('role');
    header("Location: " . SITE_URL . "/" . $role . "/dashboard.php");
    exit();
}

$error = '';
$successMsg = '';

// Get parameters
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');

if (empty($token) || empty($email)) {
    $error = 'Invalid request. Missing token or email parameter.';
} else {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    try {
        $db = Database::getInstance()->getConnection();
        
        // Validate Token
        $stmt = $db->prepare("SELECT * FROM password_reset_tokens WHERE email = ? AND token = ? LIMIT 1");
        $stmt->execute([$email, $token]);
        $resetRecord = $stmt->fetch();
        
        if (!$resetRecord) {
            $error = 'Invalid token or email combination.';
        } elseif (strtotime($resetRecord['expires_at']) < time()) {
            $error = 'This password reset link has expired.';
            // Clean up expired token
            $delStmt = $db->prepare("DELETE FROM password_reset_tokens WHERE id = ?");
            $delStmt->execute([$resetRecord['id']]);
        }
    } catch (PDOException $e) {
        error_log("Reset token validation error: " . $e->getMessage());
        $error = 'Internal database error. Please try again.';
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF
    if (!Security::verifyCsrfToken($csrfToken)) {
        $error = 'Security check failed (CSRF token invalid).';
    } 
    // Validation
    elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } 
    elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } 
    elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } 
    else {
        try {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $success = $updateStmt->execute([$hashedPassword, $email]);

            if ($success) {
                // Delete reset token
                $deleteStmt = $db->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
                $deleteStmt->execute([$email]);

                $successMsg = 'Your password has been reset successfully! Redirecting to login...';
                
                // Audit log
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                
                // Get user ID
                $userStmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $userStmt->execute([$email]);
                $userVal = $userStmt->fetch();
                
                $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'Password Reset Complete', ?, ?)");
                $auditStmt->execute([$userVal ? $userVal['id'] : null, $ip, $userAgent]);

                // Header redirect script for front-end
                header("Refresh: 3; URL=" . SITE_URL . "/login.php");
            } else {
                $error = 'Failed to update your password. Please try again.';
            }
        } catch (PDOException $e) {
            error_log("Password reset updates failed: " . $e->getMessage());
            $error = 'Internal database error. Please try again.';
        }
    }
}

// Generate CSRF Token
$csrfToken = Security::generateCsrfToken();
$pageTitle = 'Reset Password';
$noLayout = true;
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, rgba(6, 182, 212, 0.05) 90.2%); padding: 1rem;">
    <div class="glass-panel" style="width: 100%; max-width: 420px; padding: 2.5rem; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--border-radius-lg); box-shadow: var(--glass-shadow); backdrop-filter: var(--glass-blur);">
        
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; background: linear-gradient(to right, var(--brand-primary), var(--brand-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Choose New Password</h1>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Enter your secure password credentials below</p>
        </div>

        <!-- Alert messages -->
        <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem; font-size: 0.85rem; font-weight: 500;">
                <i class="fas fa-exclamation-circle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($successMsg)): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem; font-size: 0.85rem; font-weight: 500;">
                <i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i> <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($error) || !empty($successMsg)): ?>
        <!-- Form -->
        <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            
            <div class="form-group">
                <label class="form-label" for="password">New Password (min. 8 characters)</label>
                <div style="position: relative;">
                    <i class="fas fa-lock" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" style="padding-left: 40px;" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <div style="position: relative;">
                    <i class="fas fa-lock" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" style="padding-left: 40px;" required>
                </div>
            </div>

            <button type="submit" class="btn" style="width: 100%; padding: 0.85rem; border-radius: var(--border-radius-sm); font-size: 1rem; margin-bottom: 1.5rem;">
                Save Password <i class="fas fa-save" style="margin-left: 0.5rem;"></i>
            </button>
        </form>
        <?php endif; ?>

        <div style="text-align: center; border-top: 1px solid var(--border-color); padding-top: 1.5rem; font-size: 0.85rem; color: var(--text-secondary);">
            Back to <a href="login.php" style="color: var(--brand-primary); text-decoration: none; font-weight: 700;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
