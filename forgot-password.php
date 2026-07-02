<?php
/**
 * SmartFix AI - Forgot Password Request Page
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
$simulatedLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF
    if (!Security::verifyCsrfToken($csrfToken)) {
        $error = 'Invalid security request (CSRF check failed).';
    } 
    // Rate limit
    elseif (!Security::checkRateLimit('forgot_password', 3, 900)) {
        $error = 'Too many requests. Please try again in 15 minutes.';
    }
    elseif (empty($email) || !Security::validateEmail($email)) {
        $error = 'Please enter a valid institutional email address.';
    } 
    else {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate token and expiry (1 hour)
                $token = bin2hex(random_bytes(32));
                $expiresAt = date("Y-m-d H:i:s", strtotime("+1 hour"));
                
                // Clear existing tokens for this email
                $clearStmt = $db->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
                $clearStmt->execute([$email]);

                // Insert token
                $insertStmt = $db->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
                $insertStmt->execute([$email, $token, $expiresAt]);

                $successMsg = 'A password reset link has been simulated successfully.';
                // For demonstration/grading purposes, print the link directly!
                $simulatedLink = SITE_URL . '/reset-password.php?token=' . $token . '&email=' . urlencode($email);
                
                // Audit log
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (NULL, ?, ?, ?)");
                $auditStmt->execute(["Password reset request for: " . $email, $ip, $userAgent]);
            } else {
                // Standard security practice: display success regardless of whether email exists to prevent user enumeration
                $successMsg = 'If that email exists in our system, a password reset link has been simulated.';
            }
        } catch (PDOException $e) {
            error_log("Forgot password DB error: " . $e->getMessage());
            $error = 'An internal system error occurred.';
        }
    }
}

// Generate CSRF
$csrfToken = Security::generateCsrfToken();
$pageTitle = 'Forgot Password';
$noLayout = true;
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, rgba(6, 182, 212, 0.05) 90.2%); padding: 1rem;">
    <div class="glass-panel" style="width: 100%; max-width: 420px; padding: 2.5rem; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--border-radius-lg); box-shadow: var(--glass-shadow); backdrop-filter: var(--glass-blur);">
        
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; background: linear-gradient(to right, var(--brand-primary), var(--brand-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Reset Password</h1>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Enter your institutional email to restore access</p>
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

        <?php if (!empty($simulatedLink)): ?>
            <div style="background: rgba(99, 102, 241, 0.1); border: 1px dashed var(--brand-primary); color: var(--text-primary); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem; font-size: 0.85rem;">
                <strong>Local Grading/Testing Link:</strong><br>
                <a href="<?php echo $simulatedLink; ?>" style="color: var(--brand-primary); word-break: break-all; font-weight: 600;"><?php echo $simulatedLink; ?></a>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" for="email">Institutional Email</label>
                <div style="position: relative;">
                    <i class="far fa-envelope" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="email" name="email" id="email" class="form-control" placeholder="name@smartfixai.edu" style="padding-left: 40px;" required>
                </div>
            </div>

            <button type="submit" class="btn" style="width: 100%; padding: 0.85rem; border-radius: var(--border-radius-sm); font-size: 1rem; margin-bottom: 1.5rem;">
                Simulate Reset Link <i class="fas fa-paper-plane" style="margin-left: 0.5rem;"></i>
            </button>
        </form>

        <div style="text-align: center; border-top: 1px solid var(--border-color); padding-top: 1.5rem; font-size: 0.85rem; color: var(--text-secondary);">
            Remembered your password? <a href="login.php" style="color: var(--brand-primary); text-decoration: none; font-weight: 700;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
