<?php
/**
 * SmartFix AI - Staff Dashboard
 */
require_once __DIR__ . '/../config/bootstrap.php';
Session::requireRole('staff');

$staffId = Session::get('user_id');
$pageTitle = 'Staff Dashboard';
$error = '';

try {
    $db = Database::getInstance()->getConnection();
    $statsStmt = $db->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status='assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN status='wip' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) as resolved
        FROM complaints WHERE staff_id = ?
    ");
    $statsStmt->execute([$staffId]);
    $stats = $statsStmt->fetch();

    $stmt = $db->prepare("
        SELECT c.*, cat.name as category_name, p.name as priority_name, u.name as student_name
        FROM complaints c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN priorities p ON c.priority_id = p.id
        LEFT JOIN users u ON c.student_id = u.id
        WHERE c.staff_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$staffId]);
    $complaints = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Staff Dashboard: ' . $e->getMessage());
    $error = 'Failed to load dashboard data.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <div class="top-navbar glass-panel">
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div>
            <h1 class="page-title">Welcome, <?php echo htmlspecialchars(Session::get('name')); ?>!</h1>
            <p class="page-subtitle">Manage your assigned maintenance tickets.</p>
        </div>
    </div>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <div class="stats-grid">
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">Total Assigned</p><div class="value"><?php echo (int)($stats['total'] ?? 0); ?></div></div>
            <i class="fas fa-clipboard-list stat-icon" style="color:var(--brand-primary)"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">New / Queued</p><div class="value text-warning"><?php echo (int)($stats['assigned'] ?? 0); ?></div></div>
            <i class="fas fa-clock stat-icon text-warning"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">In Progress</p><div class="value text-info"><?php echo (int)($stats['in_progress'] ?? 0); ?></div></div>
            <i class="fas fa-tools stat-icon text-info"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">Resolved</p><div class="value text-success"><?php echo (int)($stats['resolved'] ?? 0); ?></div></div>
            <i class="fas fa-check-circle stat-icon text-success"></i>
        </div>
    </div>
    <div class="glass-panel card-body">
        <h2 class="section-title">My Assigned Tickets</h2>
        <?php if (empty($complaints)): ?>
            <div class="empty-state">
                <i class="far fa-folder-open"></i>
                <p>No tickets assigned to you yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead><tr>
                        <th>Ticket</th><th>Student</th><th>Title</th>
                        <th>Category</th><th>Priority</th><th>Status</th><th>Updated</th><th>Action</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td class="ticket-id">#TC-<?php echo str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($c['student_name']); ?></td>
                            <td class="fw-600"><?php echo htmlspecialchars($c['title']); ?></td>
                            <td><?php echo htmlspecialchars($c['category_name'] ?? 'General'); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($c['priority_name'] ?? 'medium'); ?>"><?php echo ucfirst($c['priority_name'] ?? 'medium'); ?></span></td>
                            <td><span class="badge badge-status-<?php echo $c['status']; ?>"><?php echo str_replace('_', ' ', ucfirst($c['status'])); ?></span></td>
                            <td class="text-muted fs-sm"><?php echo date('d M Y', strtotime($c['updated_at'] ?? $c['created_at'])); ?></td>
                            <td><a href="view-complaint.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-secondary"><i class="far fa-eye"></i> Manage</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
