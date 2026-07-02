<?php
/**
 * SmartFix AI - Categories & SLA Manager
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Enforce admin role
Session::requireRole('admin');

$adminId = Session::get('user_id');
$pageTitle = 'Categories & SLA Configuration';
$error = '';
$successMsg = '';

try {
    $db = Database::getInstance()->getConnection();

    // Handle Form Submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Security::verifyCsrfToken($csrfToken)) {
            $error = 'Security check failed (CSRF token invalid).';
        } 
        // 1. Create Category
        elseif ($action === 'create_category') {
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $error = 'Category name is required.';
            } else {
                $name = Security::sanitizeString($name);
                $desc = Security::sanitizeString($desc);

                $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $desc]);

                $successMsg = "Category '{$name}' added successfully.";
                
                // Audit
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $actionMsg = 'Created Category: ' . $name;
                $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $auditStmt->execute([$adminId, $actionMsg, $ip, $userAgent]);
            }
        }
        // 2. Create Department
        elseif ($action === 'create_department') {
            $name = trim($_POST['name'] ?? '');

            if (empty($name)) {
                $error = 'Department name is required.';
            } else {
                $name = Security::sanitizeString($name);

                $stmt = $db->prepare("INSERT INTO departments (name) VALUES (?)");
                $stmt->execute([$name]);

                $successMsg = "Department '{$name}' added successfully.";
                
                // Audit
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $actionMsg = 'Created Department: ' . $name;
                $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $auditStmt->execute([$adminId, $actionMsg, $ip, $userAgent]);
            }
        }
        // 3. Update Priority SLA
        elseif ($action === 'update_sla') {
            $prioritiesData = $_POST['sla'] ?? [];
            
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE priorities SET sla_hours = ? WHERE id = ?");

                foreach ($prioritiesData as $prioId => $hours) {
                    $stmt->execute([(int)$hours, (int)$prioId]);
                }
                
                $db->commit();
                $successMsg = "SLA parameters updated successfully.";

                // Audit
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'Updated SLA hours settings', ?, ?)");
                $auditStmt->execute([$adminId, $ip, $userAgent]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }
    }

    // Fetch lists
    $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
    $departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
    $priorities = $db->query("SELECT * FROM priorities ORDER BY id ASC")->fetchAll();

} catch (Exception $e) {
    error_log("Categories/SLA config page failed: " . $e->getMessage());
    $error = "Database operation failed: " . $e->getMessage();
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
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">System Categories & SLAs</h1>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">Configure categories, assign routing departments, and establish response hours.</p>
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

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
        
        <!-- Category Manager Card -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-tags"></i> Issue Categories</h3>
            
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create_category">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="catName">Add Category Name</label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="Category Name" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="catDesc">Description</label>
                    <input type="text" name="description" id="catDesc" class="form-control" placeholder="Short description..." required>
                </div>
                <button type="submit" class="btn" style="padding: 0.5rem;"><i class="fas fa-plus"></i> Add Category</button>
            </form>

            <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                <table class="custom-table" style="font-size: 0.85rem;">
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($cat['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Department Manager Card -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-university"></i> Resolving Departments</h3>
            
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="create_department">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="deptName">Add Department Name</label>
                    <input type="text" name="name" id="deptName" class="form-control" placeholder="Department Name" required>
                </div>
                <button type="submit" class="btn" style="padding: 0.5rem;"><i class="fas fa-plus"></i> Add Department</button>
            </form>

            <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm);">
                <table class="custom-table" style="font-size: 0.85rem;">
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($d['name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SLA Manager Card -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-history"></i> Priority SLA Resolution Parameters</h3>
            
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="action" value="update_sla">
                
                <?php foreach ($priorities as $prio): ?>
                    <div class="form-group" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                        <label class="form-label" style="margin-bottom: 0; text-transform: uppercase; font-weight: 700;">
                            <span class="badge badge-<?php echo htmlspecialchars($prio['name']); ?>"><?php echo htmlspecialchars($prio['name']); ?></span>
                        </label>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="number" name="sla[<?php echo $prio['id']; ?>]" class="form-control" value="<?php echo $prio['sla_hours']; ?>" style="width: 80px; text-align: center;" min="1" required>
                            <span style="font-size: 0.85rem; color: var(--text-secondary);">Hours</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="btn" style="padding: 0.5rem; margin-top: 1rem;"><i class="fas fa-save"></i> Save SLA Changes</button>
            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
