<?php
/**
 * SmartFix AI - User Management (Admin)
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Enforce admin role
Session::requireRole('admin');

$adminId = Session::get('user_id');
$pageTitle = 'User Profile Management';
$error = '';
$successMsg = '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch departments for dropdown
    $depts = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

    // Handle Creating New User
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = trim($_POST['role'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $deptId = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : null;
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Security::verifyCsrfToken($csrfToken)) {
            $error = 'Security check failed (CSRF token invalid).';
        } elseif (empty($name) || empty($email) || empty($password) || empty($role)) {
            $error = 'All fields are required for user creation.';
        } elseif (!Security::validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else {
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);
            $name = Security::sanitizeString($name);

            // Check if email already taken
            $dupCheck = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $dupCheck->execute([$email]);
            if ($dupCheck->fetch()) {
                $error = 'A user with this email address already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (role, email, password, name, department_id, status) VALUES (?, ?, ?, ?, ?, ?)");
                $success = $stmt->execute([$role, $email, $passwordHash, $name, $deptId, $status]);

                if ($success) {
                    $newUserId = $db->lastInsertId();
                    $successMsg = "User account '{$name}' created successfully.";

                    // Audit Log
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                    $auditStmt->execute([$adminId, "Created new user profile (ID: {$newUserId}, Role: {$role})", $ip, $userAgent]);
                } else {
                    $error = 'Failed to create user account.';
                }
            }
        }
    }

    // Handle Toggling Status (Active/Suspended)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $targetUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Security::verifyCsrfToken($csrfToken)) {
            $error = 'Security check failed (CSRF token invalid).';
        } elseif ($targetUserId === $adminId) {
            $error = 'Self lockout prevention: You cannot suspend your own administrative account.';
        } else {
            // Fetch current status
            $statusStmt = $db->prepare("SELECT status, name FROM users WHERE id = ?");
            $statusStmt->execute([$targetUserId]);
            $user = $statusStmt->fetch();

            if ($user) {
                $newStatus = ($user['status'] === 'active') ? 'suspended' : 'active';
                $update = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
                $update->execute([$newStatus, $targetUserId]);

                $successMsg = "User profile '{$user['name']}' status updated to " . strtoupper($newStatus) . ".";

                // Audit Log
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $auditStmt->execute([$adminId, "Toggled status of user profile ID: {$targetUserId} to {$newStatus}", $ip, $userAgent]);
            } else {
                $error = 'Target user profile not found.';
            }
        }
    }

    // Fetch all users list
    $usersStmt = $db->query("
        SELECT u.*, d.name as department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.id 
        ORDER BY u.created_at DESC
    ");
    $usersList = $usersStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Admin user management failure: " . $e->getMessage());
    $error = 'Failed to fetch user list database parameters.';
}

// Generate CSRF
$csrfToken = Security::generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar glass-panel">
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">User Profiles</h1>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">Manage student access, technician departments, and admin roles.</p>
        </div>
        <div>
            <button onclick="openCreateModal()" class="btn"><i class="fas fa-user-plus"></i> Create User</button>
        </div>
    </div>

    <!-- Alert panels -->
    <?php if (!empty($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($successMsg)): ?>
        <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Users Grid/Table -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Email Address</th>
                        <th>Account Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Action Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersList as $u): ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--brand-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; text-transform: uppercase; font-size: 0.8rem;">
                                        <?php echo substr($u['name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <div><?php echo htmlspecialchars($u['name']); ?></div>
                                        <?php if ($u['roll_number']): ?>
                                            <span style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($u['roll_number']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <?php echo strtoupper($u['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($u['department_name'] ?? 'None'); ?></td>
                            <td>
                                <span class="badge" style="background: <?php echo ($u['status'] === 'active') ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; color: <?php echo ($u['status'] === 'active') ? 'var(--success)' : 'var(--danger)'; ?>;">
                                    <?php echo strtoupper($u['status']); ?>
                                </span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn <?php echo ($u['status'] === 'active') ? 'btn-danger' : 'btn-success'; ?>" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;" <?php echo ($u['id'] === $adminId) ? 'disabled' : ''; ?>>
                                        <?php echo ($u['status'] === 'active') ? '<i class="fas fa-user-slash"></i> Suspend' : '<i class="fas fa-user-check"></i> Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Simple Create User Modal Overlay -->
<div id="createModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 3000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="width: 100%; max-width: 480px; padding: 2rem; background: var(--bg-secondary);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; margin: 0;">Create User Profile</h3>
            <button onclick="closeCreateModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-primary);"><i class="fas fa-times"></i></button>
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input type="text" name="name" id="name" class="form-control" placeholder="Jane Doe" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Institutional Email</label>
                <input type="email" name="email" id="email" class="form-control" placeholder="jane.doe@smartfixai.edu" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password (min. 8 characters)</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="role">Access Role</label>
                <select name="role" id="role" class="form-control" onchange="toggleDeptField(this.value)" required>
                    <option value="student">Student</option>
                    <option value="staff">Staff (Technician)</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <!-- Conditional Department field for Staff -->
            <div class="form-group" id="deptFormGroup" style="display: none;">
                <label class="form-label" for="department_id">Assigned Department</label>
                <select name="department_id" id="department_id" class="form-control">
                    <option value="" disabled selected>Select Department</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Initial Status</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>

            <button type="submit" class="btn" style="width: 100%; padding: 0.85rem; margin-top: 1rem;">
                Create Profile <i class="fas fa-plus" style="margin-left: 0.5rem;"></i>
            </button>
        </form>
    </div>
</div>

<script>
const modal = document.getElementById('createModal');
const deptGroup = document.getElementById('deptFormGroup');

function openCreateModal() {
    modal.style.display = 'flex';
}

function closeCreateModal() {
    modal.style.display = 'none';
}

function toggleDeptField(role) {
    if (role === 'staff') {
        deptGroup.style.display = 'block';
    } else {
        deptGroup.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
