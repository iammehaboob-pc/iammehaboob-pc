<?php
/**
 * SmartFix AI - System Settings Configuration (Admin)
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Enforce admin role
Session::requireRole('admin');

$adminId = Session::get('user_id');
$pageTitle = 'Global System Configuration';
$error = '';
$successMsg = '';

try {
    $db = Database::getInstance()->getConnection();

    // Ensure gemini_api_key settings entry exists
    $checkKey = $db->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'gemini_api_key'")->fetchColumn();
    if ($checkKey == 0) {
        $db->query("INSERT INTO settings (setting_key, setting_value) VALUES ('gemini_api_key', 'YOUR_GEMINI_API_KEY_HERE')");
    }

    // Handle Form Save POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Security::verifyCsrfToken($csrfToken)) {
            $error = 'Security check failed (CSRF token invalid).';
        } else {
            $systemName = trim($_POST['system_name'] ?? '');
            $systemEmail = trim($_POST['system_email'] ?? '');
            $mMode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $allowReg = isset($_POST['allow_registration']) ? '1' : '0';
            $geminiKey = trim($_POST['gemini_api_key'] ?? '');

            if (empty($systemName) || empty($systemEmail)) {
                $error = 'System name and email are required.';
            } elseif (!Security::validateEmail($systemEmail)) {
                $error = 'Please enter a valid institutional support email.';
            } else {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    
                    $stmt->execute([$systemName, 'system_name']);
                    $stmt->execute([$systemEmail, 'system_email']);
                    $stmt->execute([$mMode, 'maintenance_mode']);
                    $stmt->execute([$allowReg, 'allow_registration']);
                    $stmt->execute([$geminiKey, 'gemini_api_key']);

                    $db->commit();
                    $successMsg = 'System configurations updated successfully.';

                    // Audit Log
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                    $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'Updated Global System Settings', ?, ?)");
                    $auditStmt->execute([$adminId, $ip, $userAgent]);

                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("Failed to save settings details: " . $e->getMessage());
                    $error = "Failed to update settings: " . $e->getMessage();
                }
            }
        }
    }

    // Fetch current settings
    $settingsRaw = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
    $settings = [];
    foreach ($settingsRaw as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

} catch (PDOException $e) {
    error_log("Admin settings portal loading failed: " . $e->getMessage());
    $error = "Database link error. Please check configurations.";
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
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">Global System Settings</h1>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">Configure portal details, toggle maintenance/registrations, and register AI parameters.</p>
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

    <div style="max-width: 600px;">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="glass-panel" style="padding: 2.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <div class="form-group">
                <label class="form-label" for="system_name">Portal / System Name</label>
                <input type="text" name="system_name" id="system_name" class="form-control" value="<?php echo htmlspecialchars($settings['system_name'] ?? 'SmartFix AI'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="system_email">Support Contact Email</label>
                <input type="email" name="system_email" id="system_email" class="form-control" value="<?php echo htmlspecialchars($settings['system_email'] ?? 'support@smartfixai.edu'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="gemini_api_key">Google Gemini API Key</label>
                <input type="password" name="gemini_api_key" id="gemini_api_key" class="form-control" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? 'YOUR_GEMINI_API_KEY_HERE'); ?>" placeholder="Enter API Key here">
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Used for auto-categorization and troubleshooting solutions. Keep empty to run local mock analyzer.</p>
            </div>

            <div style="border-top: 1px solid var(--border-color); padding-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                <!-- Maintenance Mode Toggle -->
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="font-size: 0.95rem; display: block;">Enable Maintenance Mode</strong>
                        <span style="font-size: 0.75rem; color: var(--text-secondary);">Restricts student access to the portal temporarily.</span>
                    </div>
                    <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 22px;">
                        <input type="checkbox" name="maintenance_mode" id="maintenance_mode" <?php echo (isset($settings['maintenance_mode']) && $settings['maintenance_mode'] === '1') ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                        <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border-color); transition: .3s; border-radius: 34px;"></span>
                    </label>
                </div>

                <!-- Registration Toggle -->
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <strong style="font-size: 0.95rem; display: block;">Allow Student Registrations</strong>
                        <span style="font-size: 0.75rem; color: var(--text-secondary);">Permits new students to register through the sign-up page.</span>
                    </div>
                    <label class="switch" style="position: relative; display: inline-block; width: 44px; height: 22px;">
                        <input type="checkbox" name="allow_registration" id="allow_registration" <?php echo (isset($settings['allow_registration']) && $settings['allow_registration'] === '1') ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                        <span class="slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--border-color); transition: .3s; border-radius: 34px;"></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn" style="padding: 0.85rem; margin-top: 1rem;">
                Save System Configurations <i class="fas fa-save" style="margin-left: 0.5rem;"></i>
            </button>
        </form>
    </div>
</div>

<style>
    /* Styling for custom toggles */
    .switch input:checked + .slider {
        background-color: var(--brand-primary);
    }
    .switch .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    .switch input:checked + .slider:before {
        transform: translateX(22px);
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
