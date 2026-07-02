<?php
/**
 * SmartFix AI - Sidebar Template
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access not permitted.");
}

$role = Session::get('role', 'student');
$name = Session::get('name', 'User');
$email = Session::get('email', '');

// Set link paths based on roles
$dashboardLink = SITE_URL . '/' . $role . '/dashboard.php';
?>
<aside class="sidebar glass-panel" style="position: fixed; top: 0; left: 0; bottom: 0; width: var(--sidebar-width); background: var(--bg-sidebar); border-right: 1px solid var(--glass-border); display: flex; flex-direction: column; z-index: 1000; border-radius: 0; padding: 1.5rem 1rem; color: var(--text-on-dark);">
    <!-- Brand Logo / Name -->
    <div class="brand-logo" style="display: flex; align-items: center; gap: 0.75rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 2rem;">
        <div style="background: linear-gradient(135deg, var(--brand-primary), var(--brand-secondary)); padding: 0.5rem; border-radius: var(--border-radius-sm); color: #fff; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem;">S</div>
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; background: linear-gradient(to right, #fff, #c7d2fe); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">SmartFix AI</h2>
            <span style="font-size: 0.75rem; color: var(--brand-secondary); font-weight: 600; text-transform: uppercase; letter-spacing: 1px;"><?php echo htmlspecialchars($role); ?> Portal</span>
        </div>
    </div>
    
    <!-- User Info Card -->
    <div class="user-info-card" style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: var(--border-radius-md); display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; border: 1px solid rgba(255, 255, 255, 0.05);">
        <div style="width: 42px; height: 42px; border-radius: 50%; background: var(--brand-primary); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; text-transform: uppercase;">
            <?php echo substr($name, 0, 1); ?>
        </div>
        <div style="overflow: hidden;">
            <p style="font-size: 0.9rem; font-weight: 600; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff;"><?php echo htmlspecialchars($name); ?></p>
            <p style="font-size: 0.75rem; margin: 0; color: rgba(255,255,255,0.5); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($email); ?></p>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav style="flex: 1; display: flex; flex-direction: column; gap: 0.5rem;">
        <!-- General Dashboard link for all roles -->
        <a href="<?php echo $dashboardLink; ?>" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard.php') !== false) ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; transition: all 0.2s;">
            <i class="fas fa-th-large" style="width: 20px;"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($role === 'student'): ?>
            <!-- Student Links -->
            <a href="<?php echo SITE_URL; ?>/student/create-complaint.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'create-complaint.php') !== false) ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; transition: all 0.2s;">
                <i class="fas fa-plus-circle" style="width: 20px;"></i>
                <span>File a Complaint</span>
            </a>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <!-- Admin Links -->
            <a href="<?php echo SITE_URL; ?>/admin/users.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'users.php') !== false) ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; transition: all 0.2s;">
                <i class="fas fa-users" style="width: 20px;"></i>
                <span>Manage Users</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/categories.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'categories.php') !== false) ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; transition: all 0.2s;">
                <i class="fas fa-tags" style="width: 20px;"></i>
                <span>Categories & SLA</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/settings.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'settings.php') !== false) ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; transition: all 0.2s;">
                <i class="fas fa-cog" style="width: 20px;"></i>
                <span>Settings</span>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/reports.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'reports.php') !== false) ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; transition: all 0.2s;">
                <i class="fas fa-chart-bar" style="width: 20px;"></i>
                <span>Reports</span>
            </a>
        <?php endif; ?>

        <?php if ($role === 'staff'): ?>
            <a href="<?php echo SITE_URL; ?>/staff/view-complaint.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'view-complaint') !== false) ? 'active' : ''; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; transition: all 0.2s;">
                <i class="fas fa-tasks" style="width: 20px;"></i>
                <span>My Tickets</span>
            </a>
        <?php endif; ?>
    </nav>
    
    <!-- Footer Links in Sidebar -->
    <div style="border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem;">
        <!-- Theme Toggle button -->
        <button id="theme-toggle" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); border: none; background: rgba(255,255,255,0.05); text-decoration: none; color: rgba(255,255,255,0.7); font-weight: 500; width: 100%; text-align: left; cursor: pointer; transition: all 0.2s;">
            <i class="fas fa-moon text-primary" style="width: 20px;"></i>
            <span>Toggle Theme</span>
        </button>

        <a href="<?php echo SITE_URL; ?>/logout.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: var(--border-radius-sm); text-decoration: none; color: var(--brand-accent); font-weight: 600; transition: all 0.2s;">
            <i class="fas fa-sign-out-alt" style="width: 20px;"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<style>
    /* Styling active navigation states */
    .sidebar .nav-item:hover, .sidebar .nav-item.active {
        background: rgba(255, 255, 255, 0.1);
        color: #fff !important;
    }
    .sidebar .nav-item.active {
        border-left: 3px solid var(--brand-primary);
        padding-left: calc(1rem - 3px);
    }
</style>
