<?php
/**
 * SmartFix AI - Student Registration Page
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
$departments = [];

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if registration is permitted in settings
    $settingsStmt = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'allow_registration' LIMIT 1");
    $allowReg = $settingsStmt->fetchColumn();
    
    if ($allowReg !== '1') {
        exit("Registration is currently disabled by the system administrator.");
    }
    
    // Fetch departments
    $deptStmt = $db->query("SELECT * FROM departments ORDER BY name ASC");
    $departments = $deptStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Registration DB Load Error: " . $e->getMessage());
    $error = "Unable to connect to the database. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rollNumber = trim($_POST['roll_number'] ?? '');
    $departmentId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF
    if (!Security::verifyCsrfToken($csrfToken)) {
        $error = 'Invalid security request (CSRF check failed).';
    } 
    // Rate limit register attempts
    elseif (!Security::checkRateLimit('register_attempts', 3, 1800)) {
        $error = 'Too many attempts. Please try again in 30 minutes.';
    }
    // Validation
    elseif (empty($name) || empty($email) || empty($password) || empty($rollNumber) || empty($departmentId)) {
        $error = 'All fields are required.';
    } 
    elseif (!Security::validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } 
    elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } 
    else {
        // Sanitize fields
        $name = Security::sanitizeString($name);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $rollNumber = Security::sanitizeString($rollNumber);
        
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email address is already registered.';
            } else {
                // Insert user
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $db->prepare("INSERT INTO users (role, email, password, name, roll_number, department_id, status) VALUES ('student', ?, ?, ?, ?, ?, 'active')");
                $success = $insertStmt->execute([$email, $passwordHash, $name, $rollNumber, $departmentId]);
                
                if ($success) {
                    $newUserId = $db->lastInsertId();
                    $successMsg = 'Registration successful! You can now log in.';
                    
                    // Audit log
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'User Registered', ?, ?)");
                    $auditStmt->execute([$newUserId, $ip, $userAgent]);
                } else {
                    $error = 'An error occurred during account creation. Please try again.';
                }
            }
        } catch (PDOException $e) {
            error_log("Registration SQL Error: " . $e->getMessage());
            $error = 'An internal system error occurred. Please try again later.';
        }
    }
}

// Generate CSRF Token
$csrfToken = Security::generateCsrfToken();
$pageTitle = 'Register';
$noLayout = true;
require_once __DIR__ . '/includes/header.php';
?>

<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, rgba(6, 182, 212, 0.05) 90.2%); padding: 1.5rem 1rem;">
    <div class="glass-panel" style="width: 100%; max-width: 480px; padding: 2.5rem; background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--border-radius-lg); box-shadow: var(--glass-shadow); backdrop-filter: var(--glass-blur);">
        
        <!-- Header -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="display: inline-flex; background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary)); padding: 0.75rem; border-radius: var(--border-radius-md); color: #fff; width: 50px; height: 50px; align-items: center; justify-content: center; font-weight: 800; font-size: 1.5rem; margin-bottom: 1rem;">S</div>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin: 0; background: linear-gradient(to right, var(--brand-primary), var(--brand-secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Create Student Account</h1>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">SmartFix AI Complaint Registration Portal</p>
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

        <!-- Form -->
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <div style="position: relative;">
                    <i class="far fa-user" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="text" name="name" id="name" class="form-control" placeholder="John Doe" style="padding-left: 40px;" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Institutional Email</label>
                <div style="position: relative;">
                    <i class="far fa-envelope" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="email" name="email" id="email" class="form-control" placeholder="john.doe@smartfixai.edu" style="padding-left: 40px;" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="roll_number">Roll Number</label>
                <div style="position: relative;">
                    <i class="far fa-id-card" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="text" name="roll_number" id="roll_number" class="form-control" placeholder="BCA-2026-0045" style="padding-left: 40px;" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="department_id">Department / Division</label>
                <div style="position: relative;">
                    <i class="fas fa-university" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); z-index: 10;"></i>
                    <select name="department_id" id="department_id" class="form-control" style="padding-left: 40px;" required>
                        <option value="" disabled selected>Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept['id']); ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem;">
                <label class="form-label" for="password">Password (min. 8 characters)</label>
                <div style="position: relative;">
                    <i class="fas fa-lock" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" style="padding-left: 40px;" required>
                </div>
            </div>

            <button type="submit" class="btn" style="width: 100%; padding: 0.85rem; border-radius: var(--border-radius-sm); font-size: 1rem; margin-bottom: 1.5rem;">
                Register Account <i class="fas fa-user-plus" style="margin-left: 0.5rem;"></i>
            </button>
        </form>

        <div style="text-align: center; border-top: 1px solid var(--border-color); padding-top: 1.5rem; font-size: 0.85rem; color: var(--text-secondary);">
            Already have an account? <a href="login.php" style="color: var(--brand-primary); text-decoration: none; font-weight: 700;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
