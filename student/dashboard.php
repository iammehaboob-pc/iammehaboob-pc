<?php
/**
 * SmartFix AI - Student Dashboard
 */

require_once __DIR__ . '/../config/bootstrap.php';

// Enforce student role
Session::requireRole('student');

$studentId = Session::get('user_id');
$pageTitle = 'Student Dashboard';

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch stats
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status IN ('submitted', 'under_review', 'assigned') THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'wip' THEN 1 ELSE 0 END) as wip,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
        FROM complaints 
        WHERE student_id = ?
    ");
    $statsStmt->execute([$studentId]);
    $stats = $statsStmt->fetch();
    
    // Fetch complaints list
    $complaintsStmt = $db->prepare("
        SELECT c.*, cat.name as category_name, p.name as priority_name 
        FROM complaints c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN priorities p ON c.priority_id = p.id
        WHERE c.student_id = ?
        ORDER BY c.created_at DESC
    ");
    $complaintsStmt->execute([$studentId]);
    $complaints = $complaintsStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Student Dashboard DB error: " . $e->getMessage());
    $error = "Failed to load dashboard data. Please try again later.";
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar glass-panel">
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">Welcome, <?php echo htmlspecialchars(Session::get('name')); ?>!</h1>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">View your filed tickets and resolution statuses.</p>
        </div>
        <div>
            <a href="create-complaint.php" class="btn"><i class="fas fa-plus"></i> File Ticket</a>
        </div>
    </div>

    <!-- Alert details if error -->
    <?php if (isset($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card glass-panel glass-panel-hover">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Total Filed</p>
                <div class="value"><?php echo (int)($stats['total'] ?? 0); ?></div>
            </div>
            <i class="fas fa-clipboard-list icon" style="color: var(--brand-primary);"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Pending Review</p>
                <div class="value" style="color: var(--warning);"><?php echo (int)($stats['pending'] ?? 0); ?></div>
            </div>
            <i class="fas fa-clock icon" style="color: var(--warning);"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">In Progress</p>
                <div class="value" style="color: var(--info);"><?php echo (int)($stats['wip'] ?? 0); ?></div>
            </div>
            <i class="fas fa-tools icon" style="color: var(--info);"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Resolved</p>
                <div class="value" style="color: var(--success);"><?php echo (int)($stats['resolved'] ?? 0); ?></div>
            </div>
            <i class="fas fa-check-circle icon" style="color: var(--success);"></i>
        </div>
    </div>

    <!-- Tickets List Table -->
    <div class="glass-panel" style="padding: 1.5rem;">
        <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.25rem;">My Active & Resolved Tickets</h2>
        
        <?php if (empty($complaints)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
                <i class="far fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-weight: 500;">You have not filed any complaints yet.</p>
                <a href="create-complaint.php" class="btn" style="margin-top: 1rem;">File Your First Complaint</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Complaint Title</th>
                            <th>Category</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Date Filed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $complaint): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-secondary);">#TC-<?php echo str_pad($complaint['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="font-weight: 600;"><?php echo htmlspecialchars($complaint['title']); ?></td>
                                <td><?php echo htmlspecialchars($complaint['category_name'] ?? 'Unassigned'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($complaint['priority_name'] ?? 'medium'); ?>">
                                        <?php echo htmlspecialchars($complaint['priority_name'] ?? 'medium'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($complaint['status']); ?>">
                                        <?php echo str_replace('_', ' ', htmlspecialchars($complaint['status'])); ?>
                                    </span>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-secondary);"><?php echo date('d M Y, h:i A', strtotime($complaint['created_at'])); ?></td>
                                <td>
                                    <a href="view-complaint.php?id=<?php echo $complaint['id']; ?>" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                        <i class="far fa-eye"></i> View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
