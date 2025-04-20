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

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Add CSS link for this specific page
echo '<link rel="stylesheet" href="/uwuweb/assets/css/student-justification.css">';

// Ensure only students can access this page
requireRole(ROLE_STUDENT);

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) {
    die('Error: Student account not found.');
}

// Get all absences for the student
function getStudentAbsences($studentId) {
    try {
        $pdo = safeGetDBConnection('getStudentAbsences() in student/justification.php', false);
        if (!$pdo) {
            error_log("Database connection failed in getStudentAbsences()");
            return [];
        }

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
             ORDER BY p.period_date DESC, p.period_label"
        );

        $stmt->execute(['student_id' => $studentId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error in getStudentAbsences: " . $e->getMessage());
        return [];
    }
}

// Upload justification for an absence
function uploadJustification($absenceId, $justification) {
    try {
        $pdo = safeGetDBConnection('uploadJustification() in student/justification.php', false);
        if (!$pdo) {
            error_log("Database connection failed in uploadJustification()");
            return false;
        }

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
    } catch (PDOException $e) {
        error_log("Database error in uploadJustification: " . $e->getMessage());
        return false;
    }
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

    if (!in_array($file['type'], $allowedTypes, true)) {
        return false;
    }

    return true;
}

// Save uploaded justification file
function saveJustificationFile($file, $absenceId) {
    // Create uploads directory if it doesn't exist
    $uploadsDir = '../uploads/justifications';
    if (!mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
        return false;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'justification_' . $absenceId . '_' . time() . '.' . $extension;
    $filePath = $uploadsDir . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Update database with file path
        try {
            $pdo = safeGetDBConnection('saveJustificationFile() in student/justification.php', false);
            if (!$pdo) {
                error_log("Database connection failed in saveJustificationFile()");
                return false;
            }

            $stmt = $pdo->prepare(
                "UPDATE attendance 
                 SET justification_file = :file_path 
                 WHERE att_id = :att_id"
            );

            return $stmt->execute([
                'att_id' => $absenceId,
                'file_path' => $filename
            ]);
        } catch (PDOException $e) {
            error_log("Database error in saveJustificationFile: " . $e->getMessage());
            return false;
        }
    }

    return false;
}

// Get justification file information
function getJustificationFileInfo($absenceId) {
    try {
        $pdo = safeGetDBConnection('getJustificationFileInfo() in student/justification.php', false);
        if (!$pdo) {
            error_log("Database connection failed in getJustificationFileInfo()");
            return null;
        }

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
    } catch (PDOException $e) {
        error_log("Database error in getJustificationFileInfo: " . $e->getMessage());
        return null;
    }
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
        } else if (uploadJustification($absenceId, $justification)) {
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

// Get student absences
$absences = getStudentAbsences($studentId);

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include page header
include '../includes/header.php';
?>

<div class="page-container">
    <h1 class="page-title">Absence Justifications</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Information</h3>
        </div>
        <div class="card-body">
            <p>Here you can submit justifications for your absences. Provide a valid reason and optionally attach supporting documents.</p>
        </div>
    </div>

    <?php if (empty($absences)): ?>
        <div class="card">
            <div class="card-body">
                <p class="text-secondary">You have no absences that require justification.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Your Absences</h2>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
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
                                    $badgeClass = '';

                                    if (!empty($absence['justification'])) {
                                        if ($absence['approved'] === null) {
                                            $justificationStatus = 'Pending review';
                                            $badgeClass = 'badge badge-warning';
                                        } elseif ($absence['approved'] == 1) {
                                            $justificationStatus = 'Approved';
                                            $badgeClass = 'badge badge-success';
                                        } else {
                                            $justificationStatus = 'Rejected';
                                            $badgeClass = 'badge badge-error';
                                        }
                                    } else {
                                        $justificationStatus = 'Not justified';
                                        $badgeClass = 'badge badge-error';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($formattedDate) ?></td>
                                    <td><?= htmlspecialchars($absence['period_label']) ?></td>
                                    <td><?= htmlspecialchars($absence['subject_name'] . ' - ' . $absence['class_title']) ?></td>
                                    <td>
                                        <span class="<?= $badgeClass ?>"><?= htmlspecialchars($justificationStatus) ?></span>
                                    </td>
                                    <td>
                                        <?php if (empty($absence['justification'])): ?>
                                            <button class="btn btn-primary open-justification"
                                                    data-absence-id="<?= (int)$absence['att_id'] ?>"
                                                    data-date="<?= htmlspecialchars($formattedDate) ?>"
                                                    data-class="<?= htmlspecialchars($absence['subject_name']) ?>">
                                                Submit Justification
                                            </button>
                                        <?php elseif ($absence['approved'] === null): ?>
                                            <button class="btn btn-secondary open-justification"
                                                    data-absence-id="<?= (int)$absence['att_id'] ?>"
                                                    data-date="<?= htmlspecialchars($formattedDate) ?>"
                                                    data-class="<?= htmlspecialchars($absence['subject_name']) ?>"
                                                    data-justification="<?= htmlspecialchars($absence['justification']) ?>">
                                                Edit
                                            </button>
                                        <?php elseif ($absence['approved'] == 0): ?>
                                            <button class="btn btn-warning open-justification"
                                                    data-absence-id="<?= (int)$absence['att_id'] ?>"
                                                    data-date="<?= htmlspecialchars($formattedDate) ?>"
                                                    data-class="<?= htmlspecialchars($absence['subject_name']) ?>"
                                                    data-justification="<?= htmlspecialchars($absence['justification']) ?>">
                                                Resubmit
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary open-view-justification"
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
            </div>
        </div>
    <?php endif; ?>

    <!-- Justification Form Modal -->
    <div id="justification-form-modal" class="modal" style="display: none;">
        <div class="modal-content card">
            <div class="card-header">
                <h3 class="card-title">Submit Justification</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="card-body">
                <p id="absence-details"></p>

                <form id="justification-form" method="post" action="/uwuweb/student/justification.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="absence_id" id="absence_id" value="">

                    <div class="form-group">
                        <label for="justification" class="form-label">Justification:</label>
                        <textarea id="justification" name="justification" class="form-input" rows="5" required></textarea>
                        <small class="text-secondary">Please provide a detailed explanation for your absence.</small>
                    </div>

                    <div class="form-group">
                        <label for="justification_file" class="form-label">Supporting Document (optional):</label>
                        <input type="file" id="justification_file" name="justification_file" class="form-input">
                        <small class="text-secondary">Upload a doctor's note, official document, or other supporting evidence (max 2MB).</small>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Justification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Justification Modal -->
    <div id="view-justification-modal" class="modal" style="display: none;">
        <div class="modal-content card">
            <div class="card-header">
                <h3 class="card-title">Justification Details</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="card-body">
                <p id="view-absence-details"></p>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Your Justification</h4>
                    </div>
                    <div class="card-body">
                        <div id="view-justification-text"></div>
                    </div>
                </div>

                <div id="view-file-section" class="card" style="display: none;">
                    <div class="card-header">
                        <h4 class="card-title">Attached Document</h4>
                    </div>
                    <div class="card-body">
                        <div id="view-file-info"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Status</h4>
                    </div>
                    <div class="card-body">
                        <div id="view-approval-status"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close-modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Open justification form
        document.querySelectorAll('.open-justification').forEach(button => {
            button.addEventListener('click', function() {
                const absenceId = this.getAttribute('data-absence-id');
                const date = this.getAttribute('data-date');
                const className = this.getAttribute('data-class');
                const justification = this.getAttribute('data-justification');
                
                document.getElementById('absence-id').value = absenceId;
                document.getElementById('absence-details').textContent = `Absence on ${date} for ${className}`;
                
                if (justification) {
                    document.getElementById('justification').value = justification;
                }
                
                document.getElementById('justification-form-modal').style.display = 'flex';
            });
        });
        
        // Open view justification
        document.querySelectorAll('.open-view-justification').forEach(button => {
            button.addEventListener('click', function() {
                const absenceId = this.getAttribute('data-absence-id');
                const date = this.getAttribute('data-date');
                const className = this.getAttribute('data-class');
                const justification = this.getAttribute('data-justification');
                
                document.getElementById('view-absence-details').textContent = `Absence on ${date} for ${className}`;
                document.getElementById('view-justification-text').textContent = justification;
                
                // Check if there's an attached file
                fetch('/uwuweb/api/justifications.php?action=get&absence_id=' + absenceId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.justification.justification_file) {
                            document.getElementById('view-file-section').style.display = 'block';
                            const fileLink = `<a href="/uwuweb/uploads/justifications/${data.justification.justification_file}" class="btn btn-secondary" target="_blank">View Document</a>`;
                            document.getElementById('view-file-info').innerHTML = fileLink;
                            
                            let status = '';
                            let statusClass = '';
                            
                            if (data.justification.approved === null) {
                                status = 'Pending review';
                                statusClass = 'badge badge-warning';
                            } else if (data.justification.approved === 1) {
                                status = 'Approved';
                                statusClass = 'badge badge-success';
                            } else {
                                status = 'Rejected';
                                statusClass = 'badge badge-error';
                                
                                if (data.justification.reject_reason) {
                                    status += `: ${data.justification.reject_reason}`;
                                }
                            }
                            
                            document.getElementById('view-approval-status').innerHTML = `<span class="${statusClass}">${status}</span>`;
                        } else {
                            document.getElementById('view-file-section').style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching justification details:', error);
                    });
                
                document.getElementById('view-justification-modal').style.display = 'flex';
            });
        });
        
        // Close modals
        document.querySelectorAll('.close-modal').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });
        
        // Close modal when clicking outside it
        window.addEventListener('click', function(event) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    });
</script>

<?php
// Include page footer
include '../includes/footer.php';
?>
