<?php
/**
 * SmartFix AI - Admin Dashboard
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

// Enforce admin role
Session::requireRole('admin');

$adminId = Session::get('user_id');
$pageTitle = 'System Analytics & Workboard';
$error = '';
$successMsg = '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch stats
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalComplaints = $db->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
    $avgRating = $db->query("SELECT ROUND(AVG(rating), 1) FROM feedback")->fetchColumn() ?? 'N/A';
    $activeComplaints = $db->query("SELECT COUNT(*) FROM complaints WHERE status NOT IN ('resolved', 'rejected')")->fetchColumn();

    // Fetch complaint list with AI predictions and assignment status
    $complaintsStmt = $db->query("
        SELECT c.*, cat.name as category_name, p.name as priority_name, d.name as department_name, 
               u.name as student_name, st.name as staff_name, ai.confidence_score, ai.possible_solution
        FROM complaints c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN priorities p ON c.priority_id = p.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN users u ON c.student_id = u.id
        LEFT JOIN users st ON c.staff_id = st.id
        LEFT JOIN ai_analysis ai ON c.id = ai.complaint_id
        ORDER BY c.created_at DESC
    ");
    $complaints = $complaintsStmt->fetchAll();

    // Fetch departments for assignment dropdown
    $depts = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

    // Fetch technicians (staff) for assignment dropdown
    $staffList = $db->query("SELECT id, name, department_id FROM users WHERE role = 'staff' AND status = 'active' ORDER BY name ASC")->fetchAll();

    // Fetch stats for Chart.js: Categories Distribution
    $catStats = $db->query("
        SELECT cat.name, COUNT(c.id) as count 
        FROM categories cat 
        LEFT JOIN complaints c ON cat.id = c.category_id 
        GROUP BY cat.id
    ")->fetchAll();

    // Fetch stats for Chart.js: Status Distribution
    $statusStats = $db->query("
        SELECT status, COUNT(*) as count 
        FROM complaints 
        GROUP BY status
    ")->fetchAll();

    // Handle Assigning Ticket
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $complaintId = isset($_POST['complaint_id']) ? (int)$_POST['complaint_id'] : 0;
        $deptId = isset($_POST['department_id']) && $_POST['department_id'] !== '' ? (int)$_POST['department_id'] : null;
        $technicianId = isset($_POST['staff_id']) && $_POST['staff_id'] !== '' ? (int)$_POST['staff_id'] : null;
        $csrfToken = $_POST['csrf_token'] ?? '';

        if (!Security::verifyCsrfToken($csrfToken)) {
            $error = 'Security check failed (CSRF token invalid).';
        } elseif ($complaintId <= 0) {
            $error = 'Invalid complaint ticket ID.';
        } else {
            try {
                $db->beginTransaction();

                // Get current values
                $curStmt = $db->prepare("SELECT student_id, department_id, staff_id FROM complaints WHERE id = ? FOR UPDATE");
                $curStmt->execute([$complaintId]);
                $currentTicket = $curStmt->fetch();

                if (!$currentTicket) {
                    throw new Exception("Ticket not found.");
                }

                // Update complaint details
                $upStmt = $db->prepare("UPDATE complaints SET department_id = ?, staff_id = ?, status = 'assigned' WHERE id = ?");
                $upStmt->execute([$deptId, $technicianId, $complaintId]);

                // Create history log
                $historyMsg = "Ticket assigned to department and/or technician by Admin.";
                $histStmt = $db->prepare("
                    INSERT INTO complaint_status_history (complaint_id, status, comments, changed_by)
                    VALUES (?, 'assigned', ?, ?)
                ");
                $histStmt->execute([$complaintId, $historyMsg, $adminId]);

                // Notify Student
                NotificationHelper::create(
                    $currentTicket['student_id'],
                    "Ticket Assigned",
                    "Your ticket #TC-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . " has been assigned to a resolving specialist.",
                    "student/view-complaint.php?id=" . $complaintId
                );

                // Notify assigned technician (if set)
                if ($technicianId) {
                    NotificationHelper::create(
                        $technicianId,
                        "New Ticket Assigned",
                        "Admin has assigned Ticket #TC-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . " to you.",
                        "staff/dashboard.php"
                    );
                }

                // Audit log
                $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
                $auditStmt->execute([$adminId, "Assigned ticket #TC-" . $complaintId . " by Admin.", $ip, $userAgent]);

                $db->commit();
                $successMsg = "Ticket #TC-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . " assigned successfully.";

                // Reload data
                header("Location: dashboard.php?success=1");
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Admin ticket assignment failed: " . $e->getMessage());
                $error = "Failed to assign ticket: " . $e->getMessage();
            }
        }
    }
} catch (PDOException $e) {
    error_log("Admin Dashboard DB Load Error: " . $e->getMessage());
    $error = "Failed to connect to database dashboard portal.";
}

// Redirect alert handling
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $successMsg = "Ticket assigned successfully.";
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
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">Administrative Workboard</h1>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">SLA parameters, user access control lists, and database settings.</p>
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

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card glass-panel">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Total Users</p>
                <div class="value"><?php echo (int)$totalUsers; ?></div>
            </div>
            <i class="fas fa-users-cog icon" style="color: var(--brand-primary);"></i>
        </div>
        <div class="stat-card glass-panel">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Total Tickets</p>
                <div class="value"><?php echo (int)$totalComplaints; ?></div>
            </div>
            <i class="fas fa-ticket-alt icon" style="color: var(--brand-secondary);"></i>
        </div>
        <div class="stat-card glass-panel">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Active Tickets</p>
                <div class="value" style="color: var(--danger);"><?php echo (int)$activeComplaints; ?></div>
            </div>
            <i class="fas fa-exclamation-triangle icon" style="color: var(--danger);"></i>
        </div>
        <div class="stat-card glass-panel">
            <div>
                <p style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600; text-transform: uppercase;">Average Rating</p>
                <div class="value" style="color: var(--warning);"><?php echo htmlspecialchars($avgRating); ?> <span style="font-size: 1rem; color: var(--text-secondary); font-weight: 500;">/ 5</span></div>
            </div>
            <i class="fas fa-star icon" style="color: var(--warning);"></i>
        </div>
    </div>

    <!-- Analytical Charts Section -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Categories Chart Card -->
        <div class="glass-panel" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; width: 100%;">Complaints by Category</h3>
            <div style="position: relative; width: 100%; height: 260px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        
        <!-- Status Chart Card -->
        <div class="glass-panel" style="padding: 1.5rem; display: flex; flex-direction: column; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; width: 100%;">Complaints Status Distribution</h3>
            <div style="position: relative; width: 100%; height: 260px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- All Complaints Table List -->
    <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
        <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.25rem;">Global Ticket Queue Management</h2>
        
        <?php if (empty($complaints)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
                <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-weight: 500;">No complaints have been registered in the system yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Ticket ID</th>
                            <th>Student</th>
                            <th>Title & Summary</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Assignee Info</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($complaints as $comp): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-secondary);">#TC-<?php echo str_pad($comp['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($comp['student_name']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($comp['title']); ?></div>
                                    <span style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($comp['category_name'] ?? 'General'); ?></span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($comp['priority_name'] ?? 'medium'); ?>">
                                        <?php echo htmlspecialchars($comp['priority_name'] ?? 'medium'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($comp['status']); ?>">
                                        <?php echo str_replace('_', ' ', htmlspecialchars($comp['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($comp['department_name'] ?? 'No Dept.'); ?></div>
                                    <span style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($comp['staff_name'] ?? 'Unassigned'); ?></span>
                                </td>
                                <td>
                                    <button class="btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: var(--brand-secondary); color: #000;" onclick='openAssignDrawer(<?php echo json_encode($comp); ?>)'>
                                        <i class="fas fa-exchange-alt"></i> Assign
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Slide-out Drawer Panel for Assignment -->
<div id="assignDrawer" style="position: fixed; top: 0; right: -450px; width: 450px; bottom: 0; background: var(--bg-secondary); border-left: 1px solid var(--border-color); box-shadow: -10px 0 30px rgba(0,0,0,0.1); z-index: 2000; transition: right 0.3s ease; padding: 2rem; overflow-y: auto; display: flex; flex-direction: column;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem;">
        <h3 style="font-size: 1.2rem; font-weight: 800; margin: 0;">Assign Ticket & Review AI</h3>
        <button onclick="closeAssignDrawer()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-primary);"><i class="fas fa-times"></i></button>
    </div>

    <!-- Ticket Description Summary inside Drawer -->
    <div id="drawerSummaryCard" class="glass-panel" style="padding: 1rem; margin-bottom: 1.5rem; font-size: 0.85rem;">
        <strong id="drawerTicketId" style="color: var(--brand-primary); display: block; margin-bottom: 0.25rem;">#TC-0000</strong>
        <strong id="drawerTicketTitle" style="display: block; font-size: 0.95rem; margin-bottom: 0.5rem;">Ticket Title</strong>
        <p id="drawerTicketDesc" style="color: var(--text-secondary); line-height: 1.4; max-height: 80px; overflow-y: auto;"></p>
    </div>

    <!-- AI Diagnostic Summary Box -->
    <div id="drawerAiBox" class="glass-panel" style="padding: 1rem; background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: var(--border-radius-md); margin-bottom: 1.5rem; font-size: 0.85rem;">
        <div style="font-weight: 700; color: var(--brand-primary); margin-bottom: 0.5rem;"><i class="fas fa-robot"></i> SmartFix AI Triage Assistant Insights</div>
        <p><strong>Confidence:</strong> <span id="aiConfidenceText">-</span></p>
        <p style="margin-top: 0.25rem;"><strong>Recommended Solution:</strong></p>
        <p id="aiSolutionText" style="font-style: italic; color: var(--text-secondary); margin-top: 0.15rem;"></p>
    </div>

    <!-- Assignment Form -->
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" style="display: flex; flex-direction: column; gap: 1.25rem; flex: 1;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="complaint_id" id="formComplaintId">

        <div class="form-group">
            <label class="form-label" for="formDept">Assign Department</label>
            <select name="department_id" id="formDept" class="form-control" onchange="filterTechnicians(this.value)" required>
                <option value="" disabled selected>Select Department</option>
                <?php foreach ($depts as $d): ?>
                    <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="formStaff">Assign Technician (Staff Member)</label>
            <select name="staff_id" id="formStaff" class="form-control">
                <option value="">Leave Unassigned (Department Pool)</option>
                <?php foreach ($staffList as $st): ?>
                    <option value="<?php echo $st['id']; ?>" data-dept="<?php echo $st['department_id']; ?>"><?php echo htmlspecialchars($st['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn" style="width: 100%; margin-top: auto; padding: 0.85rem;">
            Confirm Assignment <i class="fas fa-check" style="margin-left: 0.5rem;"></i>
        </button>
    </form>
</div>

<!-- Load Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Chart.js implementation details
document.addEventListener('DOMContentLoaded', () => {
    // Categories Chart
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    const categoryData = <?php echo json_encode($catStats); ?>;
    
    new Chart(ctxCat, {
        type: 'doughnut',
        data: {
            labels: categoryData.map(item => item.name),
            datasets: [{
                data: categoryData.map(item => item.count),
                backgroundColor: [
                    '#6366f1', // Indigo
                    '#06b6d4', // Cyan
                    '#10b981', // Emerald
                    '#f59e0b', // Amber
                    '#f43f5e', // Rose
                    '#3b82f6'  // Blue
                ],
                borderWidth: 1,
                borderColor: 'var(--border-color)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim()
                    }
                }
            }
        }
    });

    // Status Chart
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    const statusData = <?php echo json_encode($statusStats); ?>;
    
    new Chart(ctxStatus, {
        type: 'bar',
        data: {
            labels: statusData.map(item => item.status.replace('_', ' ').toUpperCase()),
            datasets: [{
                label: 'Complaints Count',
                data: statusData.map(item => item.count),
                backgroundColor: 'rgba(99, 102, 241, 0.65)',
                borderColor: 'var(--brand-primary)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim(),
                        stepSize: 1
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim()
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});

// Drawer functions
const drawer = document.getElementById('assignDrawer');
const drawerTicketId = document.getElementById('drawerTicketId');
const drawerTicketTitle = document.getElementById('drawerTicketTitle');
const drawerTicketDesc = document.getElementById('drawerTicketDesc');
const aiConfidenceText = document.getElementById('aiConfidenceText');
const aiSolutionText = document.getElementById('aiSolutionText');
const formComplaintId = document.getElementById('formComplaintId');
const formDept = document.getElementById('formDept');
const formStaff = document.getElementById('formStaff');

function openAssignDrawer(ticket) {
    drawerTicketId.textContent = `#TC-${String(ticket.id).padStart(4, '0')}`;
    drawerTicketTitle.textContent = ticket.title;
    drawerTicketDesc.textContent = ticket.description;
    formComplaintId.value = ticket.id;
    
    // Set current values
    formDept.value = ticket.department_id || '';
    filterTechnicians(ticket.department_id || '');
    formStaff.value = ticket.staff_id || '';

    // Set AI Insights
    const confidence = ticket.confidence_score ? Math.round(ticket.confidence_score * 100) : 0;
    aiConfidenceText.textContent = `${confidence}%`;
    aiSolutionText.textContent = ticket.possible_solution || 'No diagnostic tip available.';
    
    // Open drawer
    drawer.style.right = '0px';
}

function closeAssignDrawer() {
    drawer.style.right = '-450px';
}

function filterTechnicians(deptId) {
    const options = formStaff.options;
    
    // Keep first option "Unassigned" visible
    options[0].style.display = 'block';
    
    let anyMatches = false;
    for (let i = 1; i < options.length; i++) {
        const optionDept = options[i].getAttribute('data-dept');
        if (optionDept == deptId) {
            options[i].style.display = 'block';
            anyMatches = true;
        } else {
            options[i].style.display = 'none';
        }
    }
    
    // Reset staff value if they don't match the selected department
    if (formStaff.selectedIndex > 0 && options[formStaff.selectedIndex].getAttribute('data-dept') != deptId) {
        formStaff.selectedIndex = 0;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
