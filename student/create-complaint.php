<?php
/**
 * SmartFix AI - File a Complaint
 */

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/AIHelper.php';
require_once __DIR__ . '/../includes/NotificationHelper.php';

// Enforce student role
Session::requireRole('student');

$studentId = Session::get('user_id');
$pageTitle = 'File a Complaint';
$error = '';
$success = '';

try {
    $db = Database::getInstance()->getConnection();
    
    // Fetch categories for dropdown
    $catStmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    error_log("DB load failed in complaint submission: " . $e->getMessage());
    $error = "Failed to load page parameters. Please try again.";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // AI Triage suggestions (hidden fields set by JS after AI analysis completes)
    $aiCategoryName = trim($_POST['ai_category'] ?? '');
    $aiPriorityName = trim($_POST['ai_priority'] ?? '');
    $aiDeptName = trim($_POST['ai_department'] ?? '');
    $aiSolution = trim($_POST['ai_solution'] ?? '');
    $aiConfidence = isset($_POST['ai_confidence']) ? (float)$_POST['ai_confidence'] : 0.0;
    $duplicateOfId = isset($_POST['ai_duplicate_id']) && (int)$_POST['ai_duplicate_id'] > 0 ? (int)$_POST['ai_duplicate_id'] : null;

    // Validate CSRF
    if (!Security::verifyCsrfToken($csrfToken)) {
        $error = 'Invalid security request (CSRF check failed).';
    } 
    // Validation
    elseif (empty($title) || empty($description) || empty($categoryId)) {
        $error = 'Please fill in all required fields.';
    } 
    else {
        // Sanitize
        $title = Security::sanitizeString($title);
        $description = Security::sanitizeString($description);
        
        try {
            $db->beginTransaction();

            // 1. Resolve department and priority from AI or database defaults
            // Fetch default priority (e.g. medium)
            $prioQuery = $db->prepare("SELECT id FROM priorities WHERE name = ? LIMIT 1");
            $prioName = in_array(strtolower($aiPriorityName), ['low', 'medium', 'high', 'urgent']) ? strtolower($aiPriorityName) : 'medium';
            $prioQuery->execute([$prioName]);
            $priorityId = $prioQuery->fetchColumn();
            if (!$priorityId) $priorityId = 2; // Default to medium

            // Fetch suggested department ID
            $deptId = null;
            if (!empty($aiDeptName)) {
                $deptQuery = $db->prepare("SELECT id FROM departments WHERE name = ? LIMIT 1");
                $deptQuery->execute([$aiDeptName]);
                $deptId = $deptQuery->fetchColumn();
            }
            if (!$deptId) {
                // If not set, map it based on category
                if ($categoryId === 1) $deptId = 1; // IT Support
                elseif ($categoryId === 2) $deptId = 2; // Electrical
                elseif ($categoryId === 3) $deptId = 3; // Plumbing
                elseif ($categoryId === 4) $deptId = 4; // Carpentry
                elseif ($categoryId === 5) $deptId = 5; // Housekeeping
                elseif ($categoryId === 6) $deptId = 6; // Classrooms
            }

            // 2. Insert complaint record
            $insertStmt = $db->prepare("
                INSERT INTO complaints (student_id, title, description, category_id, priority_id, department_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'submitted')
            ");
            $insertStmt->execute([$studentId, $title, $description, $categoryId, $priorityId, $deptId]);
            $complaintId = $db->lastInsertId();

            // 3. Handle File Uploads (Images)
            if (isset($_FILES['images']) && count($_FILES['images']['name']) > 0) {
                $files = $_FILES['images'];
                
                // Ensure upload directory exists
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }

                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $fileName = $files['name'][$i];
                        $fileTmp = $files['tmp_name'][$i];
                        $fileSize = $files['size'][$i];
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        // Validate file
                        if (in_array($fileExt, ALLOWED_FILE_EXTENSIONS) && $fileSize <= MAX_FILE_SIZE) {
                            $newFileName = 'complaint_' . $complaintId . '_' . time() . '_' . $i . '.' . $fileExt;
                            $destPath = UPLOAD_DIR . $newFileName;
                            
                            if (move_uploaded_file($fileTmp, $destPath)) {
                                $imgStmt = $db->prepare("INSERT INTO complaint_images (complaint_id, file_path, image_type) VALUES (?, ?, 'submission')");
                                $imgStmt->execute([$complaintId, $newFileName]);
                            }
                        }
                    }
                }
            }

            // 4. Save AI Analysis logs
            $aiStmt = $db->prepare("
                INSERT INTO ai_analysis (complaint_id, summary, predicted_category, predicted_priority, suggested_department, possible_solution, duplicate_of_id, confidence_score)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $summary = substr($description, 0, 100);
            $aiStmt->execute([
                $complaintId,
                $summary,
                !empty($aiCategoryName) ? $aiCategoryName : 'Unknown',
                $prioName,
                !empty($aiDeptName) ? $aiDeptName : 'Unknown',
                !empty($aiSolution) ? $aiSolution : 'No suggested solution.',
                $duplicateOfId,
                $aiConfidence
            ]);

            // 5. Notify Staff/Admins of the department
            $staffStmt = $db->prepare("SELECT id FROM users WHERE role = 'staff' AND department_id = ?");
            $staffStmt->execute([$deptId]);
            $staffMembers = $staffStmt->fetchAll();
            
            foreach ($staffMembers as $staff) {
                NotificationHelper::create(
                    $staff['id'],
                    "New Ticket Submitted",
                    "A new ticket #TC-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . " has been assigned to your department.",
                    "staff/dashboard.php"
                );
            }

            // Notify System Admins
            $adminStmt = $db->query("SELECT id FROM users WHERE role = 'admin'");
            $admins = $adminStmt->fetchAll();
            foreach ($admins as $admin) {
                NotificationHelper::create(
                    $admin['id'],
                    "New Complaint Registered",
                    "Ticket #TC-" . str_pad($complaintId, 4, '0', STR_PAD_LEFT) . ": " . $title,
                    "admin/dashboard.php"
                );
            }

            $db->commit();
            
            header("Location: dashboard.php?submitted=1");
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            error_log("Failed to insert complaint: " . $e->getMessage());
            $error = 'Failed to submit complaint. Database error occurred.';
        }
    }
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
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0;">File a Complaint</h1>
            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;">Submit details and leverage real-time AI triage diagnostics.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: var(--border-radius-sm); margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; align-items: start;">
        
        <form id="complaintForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" class="glass-panel" style="padding: 2rem; display: flex; flex-direction: column; gap: 1.25rem;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <!-- Hidden AI triage parameters -->
            <input type="hidden" name="ai_category" id="ai_category">
            <input type="hidden" name="ai_priority" id="ai_priority">
            <input type="hidden" name="ai_department" id="ai_department">
            <input type="hidden" name="ai_solution" id="ai_solution">
            <input type="hidden" name="ai_confidence" id="ai_confidence">
            <input type="hidden" name="ai_duplicate_id" id="ai_duplicate_id">

            <div class="form-group">
                <label class="form-label" for="title">Complaint Title / Summary</label>
                <input type="text" name="title" id="title" class="form-control" placeholder="e.g. Wi-Fi connection down in Block B second floor" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Detailed Description</label>
                <textarea name="description" id="description" rows="6" class="form-control" placeholder="Describe the issue in detail, including room numbers, specific item locations, or error messages. (AI analysis runs automatically when you finish writing!)" required></textarea>
            </div>

            <!-- Real-time AI Triage Assistant Box -->
            <div id="aiAssistantBox" class="glass-panel" style="padding: 1.25rem; display: none; background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: var(--border-radius-md);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--brand-primary);">
                        <i class="fas fa-robot animate-pulse"></i> SmartFix AI Triage Assistant
                    </div>
                    <span id="confidenceBadge" class="badge" style="background: rgba(99, 102, 241, 0.15); color: var(--brand-primary); font-size: 0.7rem;">Confidence: 0%</span>
                </div>
                
                <div id="duplicateWarning" style="display: none; background: rgba(245, 158, 11, 0.15); color: #b45309; padding: 0.75rem; border-radius: var(--border-radius-sm); font-size: 0.85rem; font-weight: 600; margin-bottom: 0.75rem; border: 1px solid rgba(245, 158, 11, 0.3);">
                    <i class="fas fa-exclamation-triangle"></i> Possible Duplicate: A similar active ticket already exists! Check <a href="" id="duplicateLink" target="_blank" style="color: #b45309; text-decoration: underline;">Ticket Details</a> before filing.
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.85rem; margin-bottom: 0.75rem;">
                    <div><strong>Suggested Category:</strong> <span id="sCategory">-</span></div>
                    <div><strong>Suggested Department:</strong> <span id="sDept">-</span></div>
                    <div><strong>Predicted Priority:</strong> <span id="sPriority">-</span></div>
                </div>

                <div style="background: rgba(255,255,255,0.05); padding: 0.75rem; border-radius: var(--border-radius-sm); font-size: 0.85rem; margin-bottom: 1rem;">
                    <strong>AI Recommended Action / Troubleshooting Tip:</strong>
                    <p id="sSolution" style="margin-top: 0.25rem; color: var(--text-secondary); font-style: italic;">Analyzing...</p>
                </div>

                <button type="button" id="applyAiBtn" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem; background: var(--brand-primary); color: #fff;">
                    <i class="fas fa-check"></i> Apply AI Suggested Category
                </button>
            </div>

            <div class="form-group">
                <label class="form-label" for="category_id">Select Category</label>
                <select name="category_id" id="category_id" class="form-control" required>
                    <option value="" disabled selected>Select Category manually or apply via AI</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="images">Attach Reference Photos (Optional, Max 3 files, Max 5MB each)</label>
                <input type="file" name="images[]" id="images" class="form-control" accept="image/*" multiple style="background: none; border: none; padding-left: 0;">
                <p style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Supported formats: JPEG, JPG, PNG</p>
            </div>

            <button type="submit" class="btn" style="padding: 0.9rem; font-size: 1rem; font-weight: 700; margin-top: 1rem;">
                Submit Complaint <i class="fas fa-paper-plane" style="margin-left: 0.5rem;"></i>
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const descTextarea = document.getElementById('description');
    const titleInput = document.getElementById('title');
    const aiBox = document.getElementById('aiAssistantBox');
    
    const sCategory = document.getElementById('sCategory');
    const sDept = document.getElementById('sDept');
    const sPriority = document.getElementById('sPriority');
    const sSolution = document.getElementById('sSolution');
    const confidenceBadge = document.getElementById('confidenceBadge');
    
    const applyAiBtn = document.getElementById('applyAiBtn');
    const categoryDropdown = document.getElementById('category_id');
    const duplicateWarning = document.getElementById('duplicateWarning');
    const duplicateLink = document.getElementById('duplicateLink');

    // Hidden input fields
    const hCategory = document.getElementById('ai_category');
    const hPriority = document.getElementById('ai_priority');
    const hDept = document.getElementById('ai_department');
    const hSolution = document.getElementById('ai_solution');
    const hConfidence = document.getElementById('ai_confidence');
    const hDuplicate = document.getElementById('ai_duplicate_id');

    let typingTimer;
    const doneTypingInterval = 1500; // wait 1.5 seconds after user stops typing to trigger AI analysis

    descTextarea.addEventListener('keyup', () => {
        clearTimeout(typingTimer);
        if (descTextarea.value.trim().length > 10 && titleInput.value.trim().length > 5) {
            typingTimer = setTimeout(triggerAIAnalysis, doneTypingInterval);
        }
    });

    titleInput.addEventListener('keyup', () => {
        clearTimeout(typingTimer);
        if (descTextarea.value.trim().length > 10 && titleInput.value.trim().length > 5) {
            typingTimer = setTimeout(triggerAIAnalysis, doneTypingInterval);
        }
    });

    async function triggerAIAnalysis() {
        aiBox.style.display = 'block';
        sSolution.textContent = 'SmartFix AI is analyzing the complaint context...';
        sCategory.textContent = '-';
        sDept.textContent = '-';
        sPriority.textContent = '-';
        duplicateWarning.style.display = 'none';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const response = await fetch('<?php echo SITE_URL; ?>/api/ai-analyze.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    title: titleInput.value.trim(),
                    description: descTextarea.value.trim()
                })
            });

            if (!response.ok) {
                throw new Error('API request failed');
            }

            const data = await response.json();
            if (data.status === 'success') {
                const analysis = data.analysis;
                
                // Set hidden values
                hCategory.value = analysis.category;
                hPriority.value = analysis.priority;
                hDept.value = analysis.department;
                hSolution.value = analysis.solution;
                hConfidence.value = analysis.confidence;
                hDuplicate.value = analysis.duplicate_id || 0;

                // Update UI Display
                sCategory.textContent = analysis.category;
                sDept.textContent = analysis.department;
                sPriority.textContent = analysis.priority.toUpperCase();
                sSolution.textContent = analysis.solution;
                
                const confidencePercent = Math.round(analysis.confidence * 100);
                confidenceBadge.textContent = `Confidence: ${confidencePercent}%`;
                
                // Show priority badge color matching priority predicted
                let badgeClass = 'badge-medium';
                if (analysis.priority === 'low') badgeClass = 'badge-low';
                else if (analysis.priority === 'high') badgeClass = 'badge-high';
                else if (analysis.priority === 'urgent') badgeClass = 'badge-urgent';
                
                sPriority.className = `badge ${badgeClass}`;

                // Handle duplicates
                if (analysis.duplicate_id) {
                    duplicateWarning.style.display = 'block';
                    duplicateLink.href = `view-complaint.php?id=${analysis.duplicate_id}`;
                }

                // Apply AI Suggestions action handler
                applyAiBtn.onclick = () => {
                    // Match selected options in categories dropdown
                    for (let i = 0; i < categoryDropdown.options.length; i++) {
                        if (categoryDropdown.options[i].text.toLowerCase() === analysis.category.toLowerCase()) {
                            categoryDropdown.selectedIndex = i;
                            break;
                        }
                    }
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'AI suggestions applied successfully!',
                        showConfirmButton: false,
                        timer: 2000
                    });
                };
            }
        } catch (error) {
            console.error('AI analyze call error:', error);
            sSolution.textContent = 'Unable to run AI analysis at this time. Standard database rules will apply.';
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
