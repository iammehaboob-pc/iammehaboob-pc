<?php
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/config/config.php';

// Start secure session
Session::start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!Security::verifyCsrfToken($csrfToken)) {
        $errors[] = 'Invalid CSRF token.';
    }

    // Rate limit login attempts (max attempts defined in config)
    if (!Security::checkRateLimit('login', MAX_LOGIN_ATTEMPTS, LOGIN_LOCKOUT_TIME)) {
        $errors[] = 'Too many login attempts. Please try again later.';
    }

    // Sanitize input
    $email = Security::sanitizeString($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!Security::validateEmail($email)) {
        $errors[] = 'Please provide a valid email address.';
    }
    if (empty($password)) {
        $errors[] = 'Password cannot be empty.';
    }

    if (empty($errors)) {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, role, email, password, name FROM users WHERE email = :email AND status = "active" LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            Session::login((int)$user['id'], $user['role'], $user['email'], $user['name']);
            // Redirect based on role
            switch (strtolower($user['role'])) {
                case 'admin':
                    $dest = SITE_URL . '/admin/dashboard.php';
                    break;
                case 'staff':
                    $dest = SITE_URL . '/staff/dashboard.php';
                    break;
                case 'student':
                    $dest = SITE_URL . '/student/dashboard.php';
                    break;
                default:
                    $dest = SITE_URL . '/login.php';
            }
            header('Location: ' . $dest);
            exit();
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
    // Regenerate CSRF token for re‑display
    $csrfToken = Security::generateCsrfToken();
} else {
    // Initial GET request – generate token
    $csrfToken = Security::generateCsrfToken();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login – SmartFix AI</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:linear-gradient(135deg, hsl(210,30%,12%), hsl(210,30%,20%)); color:#fff; font-family:'Inter',sans-serif; }
        .login-form { background:rgba(255,255,255,0.08); padding:2rem; border-radius:0.8rem; box-shadow:0 4px 12px rgba(0,0,0,0.3); width:320px; }
        .login-form h2 { margin-bottom:1.5rem; font-weight:300; text-align:center; }
        .login-form input[type=email], .login-form input[type=password] { width:100%; padding:0.6rem; margin-bottom:1rem; border:none; border-radius:0.4rem; background:rgba(255,255,255,0.15); color:#fff; }
        .login-form button { width:100%; padding:0.6rem; border:none; border-radius:0.4rem; background:hsl(200,80%,45%); color:#fff; cursor:pointer; transition:background 0.2s; }
        .login-form button:hover { background:hsl(200,80%,55%); }
        .error { background:hsl(0,70%,30%); padding:0.5rem; border-radius:0.4rem; margin-bottom:1rem; }
    </style>
</head>
<body>
    <form class="login-form" method="post" action="login.php">
        <h2>Login</h2>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?>
            </div>
        <?php endif; ?>
        <input type="email" name="email" placeholder="Email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
        <input type="password" name="password" placeholder="Password" required>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <button type="submit">Sign In</button>
    </form>
</body>
</html>


