<?php
/**
 * SmartFix AI - Staff View & Update Complaint
 */
require_once __DIR__ . '/../config/bootstrap.php';
Session::requireRole('staff');

$staffId = Session::get('user_id');
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pageTitle = 'Manage Ticket';
$error = '';
$success = '';

if ($complaintId <= 0) {
    header('Location: dashboard.php');
    exit();
}

try {
    $db = Database::getInstance()->getConnection();

    // Handle POST (status update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Security::verifyCsrfToken($csrfToken)) {
            $error = 'Security check failed. Please refresh and try again.';
        } else {
            $newStatus = $_POST['status'] ?? '';
            $comment = Security::sanitizeString($_POST['comment'] ?? '');
            $allowed = ['wip', 'resolved', 'assigned'];
            if (!in_array($newStatus, $allowed)) {
                $error = 'Invalid status selection.';
            } else {
                $db->beginTransaction();
                $upStmt = $db->prepare("UPDATE complaints SET status = ?, updated_at = NOW() WHERE id = ? AND staff_id = ?");
                $upStmt->execute([$newStatus, $complaintId, $staffId]);
                $histStmt = $db->prepare("INSERT INTO complaint_status_history (complaint_id, status, comments, changed_by) VALUES (?, ?, ?, ?)");
                $histStmt->execute([$complaintId, $newStatus, $comment, $staffId]);

                // Fetch student_id for notification
                $stu = $db->prepare("SELECT student_id FROM complaints WHERE id = ?");
                $stu->execute([$complaintId]);
                $studentId = $stu->fetchColumn();
                if ($studentId) {
                    $statusLabel = str_replace('_', ' ', ucfirst($newStatus));
                    NotificationHelper::create($studentId, 'Ticket Status Updated', "Your ticket #TC-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . " is now: {$statusLabel}.", "student/view-complaint.php?id={$complaintId}");
                }
                $db->commit();
                $success = 'Ticket status updated successfully.';
            }
        }
    }

    // Fetch complaint details
    $stmt = $db->prepare("
        SELECT c.*, cat.name as category_name, p.name as priority_name,
               u.name as student_name, u.email as student_email, u.roll_number,
               d.name as department_name, ai.possible_solution, ai.confidence_score
        FROM complaints c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN priorities p ON c.priority_id = p.id
        LEFT JOIN users u ON c.student_id = u.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN ai_analysis ai ON c.id = ai.complaint_id
        WHERE c.id = ? AND c.staff_id = ?
    ");
    $stmt->execute([$complaintId, $staffId]);
    $complaint = $stmt->fetch();

    if (!$complaint) {
        header('Location: dashboard.php');
        exit();
    }

    // Fetch history
    $histStmt = $db->prepare("
        SELECT h.*, u.name as changed_by_name
        FROM complaint_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.complaint_id = ?
        ORDER BY h.created_at ASC
    ");
    $histStmt->execute([$complaintId]);
    $history = $histStmt->fetchAll();

} catch (PDOException $e) {
    error_log('Staff View Complaint: ' . $e->getMessage());
    $error = 'Failed to load ticket data.';
}

$csrfToken = Security::generateCsrfToken();
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <div class="top-navbar glass-panel">
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div>
            <h1 class="page-title">Ticket #TC-<?php echo str_pad($complaint['id'], 4, '0', STR_PAD_LEFT); ?></h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($complaint['title']); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="detail-grid">
        <!-- Main Ticket Details -->
        <div class="glass-panel card-body">
            <h2 class="section-title"><i class="fas fa-info-circle"></i> Ticket Details</h2>
            <table class="detail-table">
                <tr><th>Student</th><td><?php echo htmlspecialchars($complaint['student_name']); ?> (<?php echo htmlspecialchars($complaint['roll_number'] ?? 'N/A'); ?>)</td></tr>
                <tr><th>Category</th><td><?php echo htmlspecialchars($complaint['category_name'] ?? 'General'); ?></td></tr>
                <tr><th>Priority</th><td><span class="badge badge-<?php echo htmlspecialchars($complaint['priority_name'] ?? 'medium'); ?>"><?php echo ucfirst($complaint['priority_name'] ?? 'medium'); ?></span></td></tr>
                <tr><th>Status</th><td><span class="badge badge-status-<?php echo $complaint['status']; ?>"><?php echo str_replace('_', ' ', ucfirst($complaint['status'])); ?></span></td></tr>
                <tr><th>Department</th><td><?php echo htmlspecialchars($complaint['department_name'] ?? 'Unassigned'); ?></td></tr>
                <tr><th>Filed On</th><td><?php echo date('d M Y, h:i A', strtotime($complaint['created_at'])); ?></td></tr>
            </table>
            <div style="margin-top:1.5rem;">
                <h3 style="font-size:1rem; font-weight:700; margin-bottom:0.75rem;">Description</h3>
                <div class="complaint-description"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></div>
            </div>
        </div>

        <!-- Side Panel: AI + Update Status -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <!-- AI Insights -->
            <?php if (!empty($complaint['possible_solution'])): ?>
            <div class="glass-panel card-body ai-panel">
                <h3 style="font-size:1rem; font-weight:700; color:var(--brand-primary); margin-bottom:0.75rem;"><i class="fas fa-robot"></i> AI Triage Insights</h3>
                <p style="font-size:0.85rem; color:var(--text-secondary);">Confidence: <?php echo round(($complaint['confidence_score'] ?? 0) * 100); ?>%</p>
                <p style="font-size:0.9rem; margin-top:0.5rem;"><?php echo htmlspecialchars($complaint['possible_solution']); ?></p>
            </div>
            <?php endif; ?>

            <!-- Update Status Form -->
            <?php if (!in_array($complaint['status'], ['resolved', 'rejected'])): ?>
            <div class="glass-panel card-body">
                <h3 style="font-size:1rem; font-weight:700; margin-bottom:1rem;"><i class="fas fa-edit"></i> Update Status</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <div class="form-group">
                        <label class="form-label" for="status">New Status</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="wip" <?php echo $complaint['status']==='wip' ? 'selected' : ''; ?>>In Progress (WIP)</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="comment">Work Notes / Comments</label>
                        <textarea name="comment" id="comment" class="form-control" rows="4" placeholder="Describe the actions taken..."></textarea>
                    </div>
                    <button type="submit" class="btn" style="width:100%;"><i class="fas fa-save"></i> Update Ticket</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Timeline History -->
    <div class="glass-panel card-body" style="margin-top:1.5rem;">
        <h2 class="section-title"><i class="fas fa-history"></i> Activity Timeline</h2>
        <?php if (empty($history)): ?>
            <p class="text-muted">No status changes recorded yet.</p>
        <?php else: ?>
            <div class="timeline">
                <?php foreach (array_reverse($history) as $h): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content glass-panel">
                        <span class="badge badge-status-<?php echo $h['status']; ?>"><?php echo str_replace('_', ' ', ucfirst($h['status'])); ?></span>
                        <span style="font-size:0.8rem; color:var(--text-secondary); margin-left:0.75rem;"><?php echo date('d M Y, h:i A', strtotime($h['created_at'])); ?></span>
                        <p style="margin:0.5rem 0 0; font-size:0.9rem;"><?php echo htmlspecialchars($h['comments'] ?? ''); ?></p>
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin:0.25rem 0 0;">By: <?php echo htmlspecialchars($h['changed_by_name'] ?? 'System'); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
