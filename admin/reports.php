<?php
/**
 * SmartFix AI - Admin Reports
 */
require_once __DIR__ . '/../config/bootstrap.php';
Session::requireRole('admin');

$pageTitle = 'System Reports';
$error = '';

try {
    $db = Database::getInstance()->getConnection();

    // Stats by status
    $statusReport = $db->query("SELECT status, COUNT(*) as cnt FROM complaints GROUP BY status ORDER BY cnt DESC")->fetchAll();

    // Stats by category
    $catReport = $db->query("
        SELECT cat.name, COUNT(c.id) as cnt
        FROM categories cat
        LEFT JOIN complaints c ON c.category_id = cat.id
        GROUP BY cat.id ORDER BY cnt DESC
    ")->fetchAll();

    // Stats by staff
    $staffReport = $db->query("
        SELECT u.name, COUNT(c.id) as assigned, SUM(c.status='resolved') as resolved
        FROM users u
        LEFT JOIN complaints c ON c.staff_id = u.id
        WHERE u.role = 'staff'
        GROUP BY u.id ORDER BY assigned DESC
    ")->fetchAll();

    // Monthly trend (last 6 months)
    $monthlyReport = $db->query("
        SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as cnt
        FROM complaints
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY created_at ASC
    ")->fetchAll();

    // Average resolution time
    $avgResolution = $db->query("
        SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, c.created_at, h.created_at)), 1) as avg_hours
        FROM complaints c
        JOIN complaint_status_history h ON h.complaint_id = c.id AND h.status = 'resolved'
    ")->fetchColumn();

    // CSV Export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        $allComplaints = $db->query("
            SELECT c.id, c.title, c.status, cat.name as category, p.name as priority,
                   u.name as student, st.name as staff, c.created_at, c.updated_at
            FROM complaints c
            LEFT JOIN categories cat ON c.category_id = cat.id
            LEFT JOIN priorities p ON c.priority_id = p.id
            LEFT JOIN users u ON c.student_id = u.id
            LEFT JOIN users st ON c.staff_id = st.id
            ORDER BY c.created_at DESC
        ")->fetchAll();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=smartfix_report_' . date('Ymd') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Ticket ID', 'Title', 'Status', 'Category', 'Priority', 'Student', 'Assigned Staff', 'Created', 'Updated']);
        foreach ($allComplaints as $row) {
            fputcsv($out, [
                '#TC-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                $row['title'], $row['status'], $row['category'], $row['priority'],
                $row['student'], $row['staff'] ?? 'Unassigned', $row['created_at'], $row['updated_at']
            ]);
        }
        fclose($out);
        exit();
    }

} catch (PDOException $e) {
    error_log('Admin Reports: ' . $e->getMessage());
    $error = 'Failed to generate reports.';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content">
    <div class="top-navbar glass-panel">
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div>
            <h1 class="page-title">System Reports</h1>
            <p class="page-subtitle">Analytics, trends, and performance metrics.</p>
        </div>
        <div>
            <a href="?export=csv" class="btn btn-secondary"><i class="fas fa-file-csv"></i> Export CSV</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- KPI Summary -->
    <div class="stats-grid">
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">Avg Resolution Time</p><div class="value"><?php echo $avgResolution ?: 'N/A'; ?></div></div>
            <i class="fas fa-hourglass-half stat-icon text-info"></i>
            <p class="text-muted fs-sm" style="margin-top:0.5rem;">Hours</p>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">Total Tickets</p>
            <div class="value"><?php echo array_sum(array_column($statusReport, 'cnt')); ?></div></div>
            <i class="fas fa-ticket-alt stat-icon" style="color:var(--brand-primary)"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">Resolved</p>
            <div class="value text-success"><?php
                $resolved = array_filter($statusReport, fn($r) => $r['status'] === 'resolved');
                echo $resolved ? reset($resolved)['cnt'] : 0;
            ?></div></div>
            <i class="fas fa-check-circle stat-icon text-success"></i>
        </div>
        <div class="stat-card glass-panel glass-panel-hover">
            <div><p class="stat-label">Active Tickets</p>
            <div class="value text-warning"><?php
                $active = array_filter($statusReport, fn($r) => !in_array($r['status'], ['resolved', 'rejected']));
                echo array_sum(array_column(array_values($active), 'cnt'));
            ?></div></div>
            <i class="fas fa-exclamation-triangle stat-icon text-warning"></i>
        </div>
    </div>

    <!-- Charts -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(340px, 1fr)); gap:1.5rem; margin-bottom:1.5rem;">
        <div class="glass-panel card-body">
            <h3 class="section-title">Tickets by Status</h3>
            <canvas id="statusChart" style="max-height:280px;"></canvas>
        </div>
        <div class="glass-panel card-body">
            <h3 class="section-title">Tickets by Category</h3>
            <canvas id="catChart" style="max-height:280px;"></canvas>
        </div>
        <div class="glass-panel card-body" style="grid-column:1/-1;">
            <h3 class="section-title">Monthly Trend (Last 6 Months)</h3>
            <canvas id="trendChart" style="max-height:280px;"></canvas>
        </div>
    </div>

    <!-- Staff Performance Table -->
    <div class="glass-panel card-body" style="margin-bottom:1.5rem;">
        <h2 class="section-title">Staff Performance</h2>
        <div class="table-responsive">
            <table class="custom-table">
                <thead><tr><th>Staff Member</th><th>Assigned</th><th>Resolved</th><th>Resolution Rate</th></tr></thead>
                <tbody>
                    <?php foreach ($staffReport as $s): ?>
                    <tr>
                        <td class="fw-600"><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo (int)$s['assigned']; ?></td>
                        <td><?php echo (int)$s['resolved']; ?></td>
                        <td>
                            <?php $rate = $s['assigned'] > 0 ? round(($s['resolved'] / $s['assigned']) * 100) : 0; ?>
                            <div class="progress-bar-wrap">
                                <div class="progress-bar-fill" style="width:<?php echo $rate; ?>%"></div>
                            </div>
                            <span class="fs-sm text-muted"><?php echo $rate; ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const statusData = <?php echo json_encode($statusReport); ?>;
const catData = <?php echo json_encode($catReport); ?>;
const trendData = <?php echo json_encode($monthlyReport); ?>;
const COLORS = ['#6366f1','#06b6d4','#10b981','#f59e0b','#f43f5e','#3b82f6','#8b5cf6'];

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels: statusData.map(r=>r.status.replace('_',' ').toUpperCase()), datasets:[{data:statusData.map(r=>r.cnt), backgroundColor:COLORS}] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
});
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: { labels: catData.map(r=>r.name), datasets:[{data:catData.map(r=>r.cnt), backgroundColor:COLORS}] },
    options: { responsive:true, plugins:{ legend:{ position:'bottom' } } }
});
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendData.map(r=>r.month),
        datasets:[{ label:'Tickets Filed', data:trendData.map(r=>r.cnt), borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,0.15)', tension:0.4, fill:true }]
    },
    options: { responsive:true, scales:{ y:{ beginAtZero:true } } }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
