<?php
/**
 * Student Absence Justification Page
 * 
 * Allows students to view their absences and submit justifications
 * 
 * Functions:
 * - getStudentAbsences($studentId) - Gets list of absences for a student
 * - getSubjectNameById($subjectId) - Gets subject name by ID
 * - uploadJustification($absenceId, $justification) - Uploads a justification for an absence
 * - validateJustificationFile($file) - Validates an uploaded justification file
 * - saveJustificationFile($file, $absenceId) - Saves an uploaded justification file
 * - getJustificationFileInfo($absenceId) - Gets information about a saved justification file
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Require student role to access this page
requireRole(ROLE_STUDENT);

// Get student ID based on user ID
$studentId = getStudentId();

if (!$studentId) {
    header("Location: ../dashboard.php?error=invalid_student");
    exit;
}

// Get all absences for the student
function getStudentAbsences($studentId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification, 
            a.approved, 
            p.period_id,
            p.period_date, 
            p.period_label, 
            c.class_id,
            c.title as class_title, 
            s.subject_id,
            s.name as subject_name,
            e.enroll_id
         FROM attendance a
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         WHERE e.student_id = :student_id AND a.status = 'A'
         ORDER BY p.period_date DESC, p.period_label ASC"
    );
    
    $stmt->execute(['student_id' => $studentId]);
    return $stmt->fetchAll();
}

// Upload justification for an absence
function uploadJustification($absenceId, $justification) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "UPDATE attendance 
         SET justification = :justification, 
             approved = NULL
         WHERE att_id = :att_id"
    );
    
    return $stmt->execute([
        'att_id' => $absenceId,
        'justification' => $justification
    ]);
}

// Validate justification file
function validateJustificationFile($file) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    
    // Check file type (allow common document/image types)
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    return true;
}

// Save uploaded justification file
function saveJustificationFile($file, $absenceId) {
    // Create uploads directory if it doesn't exist
    $uploadsDir = '../uploads/justifications';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'justification_' . $absenceId . '_' . time() . '.' . $extension;
    $filePath = $uploadsDir . '/' . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Update database with file path
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "UPDATE attendance 
             SET justification_file = :file_path 
             WHERE att_id = :att_id"
        );
        
        return $stmt->execute([
            'att_id' => $absenceId,
            'file_path' => $filename
        ]);
    }
    
    return false;
}

// Get justification file information
function getJustificationFileInfo($absenceId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT justification_file 
         FROM attendance 
         WHERE att_id = :att_id"
    );
    
    $stmt->execute(['att_id' => $absenceId]);
    $result = $stmt->fetch();
    
    if ($result && !empty($result['justification_file'])) {
        return $result['justification_file'];
    }
    
    return null;
}

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        // Process justification submission
        $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
        $justification = isset($_POST['justification']) ? trim($_POST['justification']) : '';
        
        if ($absenceId <= 0) {
            $message = 'Invalid absence selected.';
            $messageType = 'error';
        } elseif (empty($justification)) {
            $message = 'Please provide a justification message.';
            $messageType = 'error';
        } else {
            // Save the justification text
            if (uploadJustification($absenceId, $justification)) {
                $message = 'Justification submitted successfully.';
                $messageType = 'success';
                
                // Check if there's also a file to upload
                if (isset($_FILES['justification_file']) && $_FILES['justification_file']['size'] > 0) {
                    $file = $_FILES['justification_file'];
                    
                    if (validateJustificationFile($file)) {
                        if (saveJustificationFile($file, $absenceId)) {
                            $message = 'Justification and supporting document submitted successfully.';
                        } else {
                            $message .= ' However, there was an error uploading your file.';
                            $messageType = 'warning';
                        }
                    } else {
                        $message .= ' However, the file you uploaded was invalid. Only images, PDFs and documents up to 2MB are accepted.';
                        $messageType = 'warning';
                    }
                }
            } else {
                $message = 'Error submitting justification. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Get student absences
$absences = getStudentAbsences($studentId);

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include page header
include '../includes/header.php';
?>

<div class="justification-container">
    <h1>Absence Justifications</h1>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <div class="justification-intro">
        <p>Here you can submit justifications for your absences. Provide a valid reason and optionally attach supporting documents.</p>
    </div>
    
    <?php if (empty($absences)): ?>
        <div class="absence-list empty">
            <p class="empty-message">You have no absences that require justification.</p>
        </div>
    <?php else: ?>
        <div class="absence-list">
            <h2>Your Absences</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Period</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($absences as $absence): ?>
                        <?php 
                            $formattedDate = date('d.m.Y', strtotime($absence['period_date']));
                            $justificationStatus = '';
                            
                            if (!empty($absence['justification'])) {
                                if ($absence['approved'] === null) {
                                    $justificationStatus = 'Pending review';
                                } elseif ($absence['approved'] == 1) {
                                    $justificationStatus = 'Approved';
                                } else {
                                    $justificationStatus = 'Rejected';
                                }
                            } else {
                                $justificationStatus = 'Not justified';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($formattedDate) ?></td>
                            <td><?= htmlspecialchars($absence['period_label']) ?></td>
                            <td><?= htmlspecialchars($absence['subject_name'] . ' - ' . $absence['class_title']) ?></td>
                            <td class="justification-status <?= strtolower(str_replace(' ', '-', $justificationStatus)) ?>">
                                <?= htmlspecialchars($justificationStatus) ?>
                            </td>
                            <td>
                                <?php if (empty($absence['justification'])): ?>
                                    <button class="btn btn-small open-justification" 
                                            data-absence-id="<?= (int)$absence['att_id'] ?>"
                                            data-date="<?= htmlspecialchars($formattedDate) ?>"
                                            data-class="<?= htmlspecialchars($absence['subject_name']) ?>">
                                        Submit Justification
                                    </button>
                                <?php elseif ($absence['approved'] === null): ?>
                                    <button class="btn btn-small btn-edit open-justification" 
                                            data-absence-id="<?= (int)$absence['att_id'] ?>"
                                            data-date="<?= htmlspecialchars($formattedDate) ?>"
                                            data-class="<?= htmlspecialchars($absence['subject_name']) ?>"
                                            data-justification="<?= htmlspecialchars($absence['justification']) ?>">
                                        Edit
                                    </button>
                                <?php elseif ($absence['approved'] == 0): ?>
                                    <button class="btn btn-small btn-resubmit open-justification" 
                                            data-absence-id="<?= (int)$absence['att_id'] ?>"
                                            data-date="<?= htmlspecialchars($formattedDate) ?>"
                                            data-class="<?= htmlspecialchars($absence['subject_name']) ?>"
                                            data-justification="<?= htmlspecialchars($absence['justification']) ?>">
                                        Resubmit
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-small btn-view open-view-justification" 
                                            data-absence-id="<?= (int)$absence['att_id'] ?>"
                                            data-date="<?= htmlspecialchars($formattedDate) ?>"
                                            data-class="<?= htmlspecialchars($absence['subject_name']) ?>"
                                            data-justification="<?= htmlspecialchars($absence['justification']) ?>">
                                        View
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Justification Form Modal -->
    <div id="justification-form-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Submit Justification</h3>
            <p id="absence-details"></p>
            
            <form id="justification-form" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="absence_id" id="absence_id" value="">
                
                <div class="form-group">
                    <label for="justification">Justification:</label>
                    <textarea id="justification" name="justification" rows="5" required></textarea>
                    <small>Please provide a detailed explanation for your absence.</small>
                </div>
                
                <div class="form-group">
                    <label for="justification_file">Supporting Document (optional):</label>
                    <input type="file" id="justification_file" name="justification_file">
                    <small>Upload a doctor's note, official document, or other supporting evidence (max 2MB).</small>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-cancel close-modal">Cancel</button>
                    <button type="submit" class="btn">Submit Justification</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Justification Modal -->
    <div id="view-justification-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Justification Details</h3>
            <p id="view-absence-details"></p>
            
            <div class="justification-view">
                <h4>Your Justification:</h4>
                <div id="view-justification-text" class="justification-text"></div>
                
                <div id="view-file-section" style="display: none;">
                    <h4>Attached Document:</h4>
                    <div id="view-file-info" class="file-info"></div>
                </div>
                
                <div class="approval-status">
                    <h4>Status:</h4>
                    <div id="view-approval-status"></div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn close-modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to open justification form modal
    function openJustificationModal() {
        const buttons = document.querySelectorAll('.open-justification');
        const modal = document.getElementById('justification-form-modal');
        const absenceDetails = document.getElementById('absence-details');
        const justificationTextarea = document.getElementById('justification');
        const absenceIdInput = document.getElementById('absence_id');
        
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const absenceId = this.getAttribute('data-absence-id');
                const date = this.getAttribute('data-date');
                const className = this.getAttribute('data-class');
                const existingJustification = this.getAttribute('data-justification');
                
                absenceDetails.textContent = `Absence on ${date} - ${className}`;
                absenceIdInput.value = absenceId;
                
                // Pre-fill existing justification if available
                if (existingJustification) {
                    justificationTextarea.value = existingJustification;
                } else {
                    justificationTextarea.value = '';
                }
                
                modal.style.display = 'flex';
            });
        });
    }
    
    // Function to open view justification modal
    function openViewJustificationModal() {
        const buttons = document.querySelectorAll('.open-view-justification');
        const modal = document.getElementById('view-justification-modal');
        const absenceDetails = document.getElementById('view-absence-details');
        const justificationText = document.getElementById('view-justification-text');
        const approvalStatus = document.getElementById('view-approval-status');
        
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const date = this.getAttribute('data-date');
                const className = this.getAttribute('data-class');
                const justification = this.getAttribute('data-justification');
                
                absenceDetails.textContent = `Absence on ${date} - ${className}`;
                justificationText.textContent = justification;
                approvalStatus.textContent = 'Approved';
                approvalStatus.className = 'status-approved';
                
                modal.style.display = 'flex';
            });
        });
    }
    
    // Function to close modals
    function setupModalClosing() {
        const closeButtons = document.querySelectorAll('.close-modal');
        
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });
        
        // Close modal when clicking outside it
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    }
    
    // Initialize all modal functionality
    document.addEventListener('DOMContentLoaded', function() {
        openJustificationModal();
        openViewJustificationModal();
        setupModalClosing();
    });
</script>

<style>
    /* Justification page specific styles */
    .justification-container {
        padding: 1rem 0;
    }
    
    .justification-intro {
        margin-bottom: 2rem;
    }
    
    .absence-list {
        margin: 2rem 0;
    }
    
    .absence-list h2 {
        margin-bottom: 1rem;
    }
    
    .absence-list.empty {
        padding: 2rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        text-align: center;
    }
    
    .empty-message {
        color: #666;
        font-style: italic;
    }
    
    .justification-status {
        font-weight: 500;
    }
    
    .justification-status.not-justified {
        color: #dc3545;
    }
    
    .justification-status.pending-review {
        color: #ffc107;
    }
    
    .justification-status.approved {
        color: #28a745;
    }
    
    .justification-status.rejected {
        color: #dc3545;
    }
    
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background-color: #fff;
        padding: 2rem;
        border-radius: 4px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }
    
    .close-modal {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
    }
    
    .close-modal:hover {
        color: #000;
    }
    
    #absence-details,
    #view-absence-details {
        font-weight: 500;
        margin-bottom: 1rem;
    }
    
    .justification-text {
        padding: 1rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        margin-bottom: 1rem;
        white-space: pre-wrap;
    }
    
    .status-approved {
        color: #28a745;
        font-weight: 500;
    }
    
    .status-rejected {
        color: #dc3545;
        font-weight: 500;
    }
    
    .file-info {
        padding: 0.5rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    /* Form styles */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: inherit;
        font-size: 1rem;
    }
    
    .form-group small {
        display: block;
        color: #666;
        font-size: 0.875rem;
        margin-top: 0.5rem;
    }
    
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 2rem;
    }
    
    /* Alert styles */
    .alert {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 4px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
    }
</style>

<?php
// Include page footer
include '../includes/footer.php';
?>