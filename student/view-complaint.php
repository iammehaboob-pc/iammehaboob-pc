<?php
/**
 * SmartFix AI - View Complaint Details (Student)
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

// Enforce student role
Session::requireRole('student');

$studentId = Session::get('user_id');
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$pageTitle = 'Ticket Details';
$error = '';
$successMsg = '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch complaint details
    $compStmt = $db->prepare("
        SELECT c.*, cat.name as category_name, p.name as priority_name, d.name as department_name, s.name as staff_name 
        FROM complaints c
        LEFT JOIN categories cat ON c.category_id = cat.id
        LEFT JOIN priorities p ON c.priority_id = p.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN users s ON c.staff_id = s.id
        WHERE c.id = ? AND c.student_id = ?
        LIMIT 1
    ");
    $compStmt->execute([$complaintId, $studentId]);
    $complaint = $compStmt->fetch();

    if (!$complaint) {
        $error = 'Complaint ticket not found or access denied.';
    } else {
        // Fetch submission & resolution images
        $imgStmt = $db->prepare("SELECT * FROM complaint_images WHERE complaint_id = ?");
        $imgStmt->execute([$complaintId]);
        $images = $imgStmt->fetchAll();

        // Fetch status history timeline
        $historyStmt = $db->prepare("
            SELECT h.*, u.name as user_name, u.role as user_role 
            FROM complaint_status_history h
            LEFT JOIN users u ON h.changed_by = u.id
            WHERE h.complaint_id = ?
            ORDER BY h.created_at ASC
        ");
        $historyStmt->execute([$complaintId]);
        $history = $historyStmt->fetchAll();

        // Fetch user feedback
        $feedStmt = $db->prepare("SELECT * FROM feedback WHERE complaint_id = ? LIMIT 1");
        $feedStmt->execute([$complaintId]);
        $feedback = $feedStmt->fetch();
    }
} catch (PDOException $e) {
    error_log("Student view complaint DB error: " . $e->getMessage());
    $error = "An error occurred while loading ticket details.";
}

// Generate CSRF token (needed for feedback form)
$csrfToken = Security::generateCsrfToken();

// Process feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $complaint['status'] === 'resolved' && !$feedback) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comments = trim($_POST['comments'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';

    // Validate CSRF
    if (!Security::verifyCsrfToken($csrfToken)) {
        $error = 'Invalid security request (CSRF check failed).';
    } 
    elseif ($rating < 1 || $rating > 5) {
        $error = 'Please provide a valid rating between 1 and 5 stars.';
    } 
    else {
        $comments = Security::sanitizeString($comments);
        
        try {
            $insertFeed = $db->prepare("INSERT INTO feedback (complaint_id, rating, comments) VALUES (?, ?, ?)");
            $insertFeed->execute([$complaintId, $rating, $comments]);
            
            // Log audit
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $auditStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'Feedback Submitted', ?, ?)");
            $auditStmt->execute([$studentId, $ip, $userAgent]);

            // Notify staff of feedback if they are assigned
            if ($complaint['staff_id']) {
                NotificationHelper::create(
                    $complaint['staff_id'],
                    "Feedback Received",
                    "A student rated your resolution on Ticket #TC-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . " as " . $rating . " Stars.",
                    "staff/dashboard.php"
                );
            }

            $successMsg = 'Thank you for your feedback! Rating saved successfully.';
            
            // Reload page to reflect feedback
            header("Refresh: 2; URL=view-complaint.php?id=" . $complaintId);
        } catch (PDOException $e) {
            error_log("Feedback save failed: " . $e->getMessage());
            $error = 'Failed to submit feedback. Please try again.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar glass-panel">
        <button class="toggle-sidebar"><i class="fas fa-bars"></i></button>
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">Ticket Details</h1>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">Ticket ID: #TC-<?php echo str_pad($complaintId, 4, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to List</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>

        <?php if (!empty($successMsg)): ?>
            <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--success); color: var(--success); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
            
            <!-- Left Side: Detail and Timeline -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- Ticket Detail Glass Card -->
                <div class="glass-panel" style="padding: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.5rem;">
                        <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0;"><?php echo htmlspecialchars($complaint['title']); ?></h2>
                        <span class="badge badge-<?php echo htmlspecialchars($complaint['status']); ?>"><?php echo str_replace('_', ' ', htmlspecialchars($complaint['status'])); ?></span>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Description</h3>
                        <p style="line-height: 1.6; white-space: pre-line;"><?php echo htmlspecialchars($complaint['description']); ?></p>
                    </div>

                    <!-- Grid Info -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Category</span>
                            <strong style="font-size: 0.95rem;"><?php echo htmlspecialchars($complaint['category_name'] ?? 'Unassigned'); ?></strong>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Assigned Department</span>
                            <strong style="font-size: 0.95rem;"><?php echo htmlspecialchars($complaint['department_name'] ?? 'Unassigned'); ?></strong>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Priority Level</span>
                            <strong style="font-size: 0.95rem;">
                                <span class="badge badge-<?php echo htmlspecialchars($complaint['priority_name'] ?? 'medium'); ?>"><?php echo htmlspecialchars($complaint['priority_name'] ?? 'medium'); ?></span>
                            </strong>
                        </div>
                        <div>
                            <span style="font-size: 0.8rem; color: var(--text-secondary); display: block;">Assigned Technician</span>
                            <strong style="font-size: 0.95rem;"><?php echo htmlspecialchars($complaint['staff_name'] ?? 'Not Assigned Yet'); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- Status Timeline Card -->
                <div class="glass-panel" style="padding: 2rem;">
                    <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">Status Updates & Log Timeline</h3>
                    
                    <?php if (empty($history)): ?>
                        <div style="color: var(--text-secondary); font-style: italic; text-align: center; padding: 1rem 0;">
                            No history records exist for this ticket yet.
                        </div>
                    <?php else: ?>
                        <div style="position: relative; padding-left: 1.5rem; border-left: 2px solid var(--border-color);">
                            <?php foreach ($history as $idx => $log): ?>
                                <div style="position: relative; margin-bottom: 1.5rem;">
                                    <!-- Indicator dot -->
                                    <div style="position: absolute; left: calc(-1.5rem - 6px); top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--brand-primary); border: 2px solid var(--bg-primary);"></div>
                                    
                                    <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                        <?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?>
                                    </div>
                                    <div style="font-weight: 700; font-size: 0.95rem; margin: 0.2rem 0;">
                                        Status changed to: <span class="badge badge-<?php echo htmlspecialchars($log['status']); ?>" style="font-size: 0.7rem; padding: 0.15rem 0.5rem;"><?php echo str_replace('_', ' ', htmlspecialchars($log['status'])); ?></span>
                                    </div>
                                    <p style="font-size: 0.9rem; margin: 0; color: var(--text-primary);"><?php echo htmlspecialchars($log['comments'] ?? 'No comments left.'); ?></p>
                                    <span style="font-size: 0.75rem; color: var(--text-secondary);">By: <?php echo htmlspecialchars($log['user_name']); ?> (<?php echo ucfirst($log['user_role']); ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Right Side: Attachment & Ratings -->
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- References Images -->
                <div class="glass-panel" style="padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Attached Photos</h3>
                    
                    <?php 
                    $subImages = array_filter($images, function($img) { return $img['image_type'] === 'submission'; });
                    $resImages = array_filter($images, function($img) { return $img['image_type'] === 'resolution'; });
                    ?>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Submission Proof</h4>
                        <?php if (empty($subImages)): ?>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); font-style: italic;">No reference photos submitted.</p>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.5rem;">
                                <?php foreach ($subImages as $img): ?>
                                    <a href="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($img['file_path']); ?>" target="_blank">
                                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($img['file_path']); ?>" style="width: 100%; height: 80px; object-fit: cover; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); cursor: pointer;" alt="Submission Proof">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase;">Resolution Proof</h4>
                        <?php if (empty($resImages)): ?>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); font-style: italic;">No resolution photos submitted.</p>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.5rem;">
                                <?php foreach ($resImages as $img): ?>
                                    <a href="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($img['file_path']); ?>" target="_blank">
                                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo htmlspecialchars($img['file_path']); ?>" style="width: 100%; height: 80px; object-fit: cover; border-radius: var(--border-radius-sm); border: 1px solid var(--border-color); cursor: pointer;" alt="Resolution Proof">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rating / Feedback Box -->
                <?php if ($complaint['status'] === 'resolved'): ?>
                    <div class="glass-panel" style="padding: 1.5rem; background: rgba(16, 185, 129, 0.03); border: 1px solid rgba(16, 185, 129, 0.2);">
                        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--success); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Resolution Feedback</h3>
                        
                        <?php if ($feedback): ?>
                            <!-- Show Submitted Feedback -->
                            <div>
                                <div style="display: flex; gap: 0.25rem; font-size: 1.25rem; color: var(--warning); margin-bottom: 0.5rem;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?php echo ($i <= $feedback['rating']) ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </div>
                                <p style="font-size: 0.9rem; font-weight: 600; margin: 0;">Your Comments:</p>
                                <p style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;"><?php echo htmlspecialchars($feedback['comments'] ?? 'No comments.'); ?></p>
                                <span style="font-size: 0.75rem; color: var(--text-secondary); display: block; margin-top: 0.5rem;">Submitted on: <?php echo date('d M Y', strtotime($feedback['created_at'])); ?></span>
                            </div>
                        <?php else: ?>
                            <!-- Feedback Rating Form -->
                            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                
                                <div class="form-group">
                                    <label class="form-label" style="color: var(--text-primary);">Service Rating</label>
                                    <div class="star-rating" style="display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 0.5rem; font-size: 1.75rem;">
                                        <input type="radio" id="star5" name="rating" value="5" style="display: none;"><label for="star5" style="cursor: pointer; color: var(--text-secondary);"><i class="far fa-star"></i></label>
                                        <input type="radio" id="star4" name="rating" value="4" style="display: none;"><label for="star4" style="cursor: pointer; color: var(--text-secondary);"><i class="far fa-star"></i></label>
                                        <input type="radio" id="star3" name="rating" value="3" style="display: none;"><label for="star3" style="cursor: pointer; color: var(--text-secondary);"><i class="far fa-star"></i></label>
                                        <input type="radio" id="star2" name="rating" value="2" style="display: none;"><label for="star2" style="cursor: pointer; color: var(--text-secondary);"><i class="far fa-star"></i></label>
                                        <input type="radio" id="star1" name="rating" value="1" style="display: none;"><label for="star1" style="cursor: pointer; color: var(--text-secondary);"><i class="far fa-star"></i></label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="comments" style="color: var(--text-primary);">Comments / Suggestions</label>
                                    <textarea name="comments" id="comments" rows="3" class="form-control" placeholder="Share your experience..." required></textarea>
                                </div>

                                <button type="submit" class="btn btn-success" style="width: 100%; padding: 0.6rem; font-size: 0.9rem;">
                                    Submit Review
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Star rating animation helper
    const stars = document.querySelectorAll('.star-rating label');
    const inputs = document.querySelectorAll('.star-rating input');

    stars.forEach((star, index) => {
        star.addEventListener('click', () => {
            const val = 5 - index; // because row-reverse logic in css matches radio inputs
            inputs.forEach(input => {
                if (input.value == val) {
                    input.checked = true;
                }
            });
            updateStarDisplay(val);
        });

        star.addEventListener('mouseover', () => {
            const val = 5 - index;
            highlightStars(val);
        });

        star.addEventListener('mouseout', () => {
            const activeInput = document.querySelector('.star-rating input:checked');
            const val = activeInput ? activeInput.value : 0;
            highlightStars(val);
        });
    });

    function highlightStars(val) {
        stars.forEach((star, index) => {
            const starVal = 5 - index;
            const icon = star.querySelector('i');
            if (starVal <= val) {
                icon.className = 'fas fa-star text-warning';
                icon.style.color = 'var(--warning)';
            } else {
                icon.className = 'far fa-star';
                icon.style.color = 'var(--text-secondary)';
            }
        });
    }

    function updateStarDisplay(val) {
        highlightStars(val);
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
