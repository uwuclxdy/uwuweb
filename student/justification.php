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

// CSS styles are included in header.php

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

    <div class="card mb-lg">
        <h1 class="mt-0 mb-md">Absence Justifications</h1>
        <p class="text-secondary mt-0 mb-0">Submit justifications for your absences</p>
    </div>

<?php if (!empty($message)): ?>
    <div class="alert <?= $messageType === 'success' ? 'status-success' : ($messageType === 'warning' ? 'status-warning' : 'status-error') ?> mb-lg">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

    <!-- Information Card -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">About Justifications</h2>
        <p class="mb-sm">Absence justifications help explain why you were unable to attend class. Valid reasons include:</p>
        <ul class="mb-md">
            <li>Illness or medical appointments</li>
            <li>Family emergencies</li>
            <li>School-approved activities</li>
            <li>Religious observances</li>
            <li>Other unavoidable circumstances</li>
        </ul>
        <p class="mb-0">Supporting documents (such as medical notes) improve the chances of approval. Submit justifications as soon as possible after an absence.</p>
    </div>

    <!-- Absences List -->
<?php if (empty($absences)): ?>
    <div class="card">
        <div class="bg-tertiary p-lg text-center rounded">
            <p class="mb-sm">You have no absences that require justification.</p>
            <p class="text-secondary mb-0">All your absences have been justified or you have perfect attendance.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <h2 class="mt-0 mb-md">Your Absences</h2>

        <div class="table-responsive">
            <table class="data-table">
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
                            $badgeClass = 'status-warning';
                        } elseif ($absence['approved'] == 1) {
                            $justificationStatus = 'Approved';
                            $badgeClass = 'status-success';
                        } else {
                            $justificationStatus = 'Rejected';
                            $badgeClass = 'status-error';
                        }
                    } else {
                        $justificationStatus = 'Not justified';
                        $badgeClass = 'status-error';
                    }
                    ?>
                    <tr>
                        <td><?= $formattedDate ?></td>
                        <td><?= htmlspecialchars($absence['period_label']) ?></td>
                        <td>
                            <?= htmlspecialchars($absence['subject_name']) ?> -
                            <?= htmlspecialchars($absence['class_title']) ?>
                        </td>
                        <td>
                                <span class="attendance-status <?= $badgeClass ?>">
                                    <?= $justificationStatus ?>
                                </span>
                        </td>
                        <td>
                            <?php if (empty($absence['justification'])): ?>
                                <button class="btn btn-primary btn-sm submit-justification-btn"
                                        data-id="<?= $absence['att_id'] ?>"
                                        data-date="<?= $formattedDate ?>"
                                        data-subject="<?= htmlspecialchars($absence['subject_name']) ?>"
                                        data-class="<?= htmlspecialchars($absence['class_title']) ?>">
                                    Submit Justification
                                </button>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm view-justification-btn"
                                        data-id="<?= $absence['att_id'] ?>"
                                        data-date="<?= $formattedDate ?>"
                                        data-subject="<?= htmlspecialchars($absence['subject_name']) ?>"
                                        data-class="<?= htmlspecialchars($absence['class_title']) ?>"
                                        data-justification="<?= htmlspecialchars($absence['justification']) ?>"
                                        data-status="<?= $justificationStatus ?>"
                                        data-badge-class="<?= $badgeClass ?>"
                                        data-file="<?= !empty($absence['justification_file']) ? htmlspecialchars($absence['justification_file']) : '' ?>">
                                    View Details
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

    <!-- Justification Form Modal -->
    <div class="modal" id="justificationFormModal" style="display: none;">
        <div class="modal-overlay" id="justificationFormModalOverlay"></div>
        <div class="modal-container">
            <div class="card">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0">Submit Justification</h3>
                    <button type="button" class="btn-close" id="closeJustificationFormModal">&times;</button>
                </div>

                <div class="bg-tertiary p-md rounded mb-lg">
                    <div class="d-flex justify-between">
                        <div>
                            <p class="mb-xs"><strong>Date:</strong> <span id="absenceDate"></span></p>
                            <p class="mb-0"><strong>Class:</strong> <span id="absenceClass"></span></p>
                        </div>
                        <div>
                            <span class="attendance-status status-absent">Absent</span>
                        </div>
                    </div>
                </div>

                <form method="POST" action="justification.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="absence_id" id="absenceId" value="">

                    <div class="form-group">
                        <label for="justification" class="form-label">Justification Explanation</label>
                        <textarea id="justification" name="justification" class="form-input" rows="4" required
                                  placeholder="Explain why you were absent..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="justification_file" class="form-label">Supporting Document (Optional)</label>
                        <input type="file" id="justification_file" name="justification_file" class="form-input">
                        <div class="feedback-text">Accepted formats: PDF, DOC, DOCX, JPG, PNG (max 2MB)</div>
                    </div>

                    <div class="d-flex gap-md justify-end mt-lg">
                        <button type="button" class="btn" id="cancelJustificationBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Justification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Justification Modal -->
    <div class="modal" id="viewJustificationModal" style="display: none;">
        <div class="modal-overlay" id="viewJustificationModalOverlay"></div>
        <div class="modal-container">
            <div class="card">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0">Justification Details</h3>
                    <button type="button" class="btn-close" id="closeViewJustificationModal">&times;</button>
                </div>

                <div class="bg-tertiary p-md rounded mb-lg">
                    <div class="d-flex justify-between">
                        <div>
                            <p class="mb-xs"><strong>Date:</strong> <span id="viewAbsenceDate"></span></p>
                            <p class="mb-0"><strong>Class:</strong> <span id="viewAbsenceClass"></span></p>
                        </div>
                        <div>
                            <span class="attendance-status" id="justificationStatusBadge"></span>
                        </div>
                    </div>
                </div>

                <h4 class="mt-0 mb-sm">Your Explanation</h4>
                <div class="bg-tertiary p-md rounded mb-lg">
                    <p class="mb-0" id="viewJustificationText"></p>
                </div>

                <div id="attachmentSection" style="display: none;">
                    <h4 class="mt-0 mb-sm">Attached Document</h4>
                    <div class="bg-tertiary p-md rounded mb-lg">
                        <a href="#" id="attachmentLink" target="_blank" class="btn btn-secondary">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-xs">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            View Document
                        </a>
                    </div>
                </div>

                <div class="d-flex justify-end mt-lg">
                    <button type="button" class="btn" id="closeViewDetailsBtn">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Justification Form Modal
            const submitJustificationBtns = document.querySelectorAll('.submit-justification-btn');
            const justificationFormModal = document.getElementById('justificationFormModal');
            const closeJustificationFormModal = document.getElementById('closeJustificationFormModal');
            const cancelJustificationBtn = document.getElementById('cancelJustificationBtn');
            const justificationFormModalOverlay = document.getElementById('justificationFormModalOverlay');
            const absenceId = document.getElementById('absenceId');
            const absenceDate = document.getElementById('absenceDate');
            const absenceClass = document.getElementById('absenceClass');

            submitJustificationBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const date = this.getAttribute('data-date');
                    const subject = this.getAttribute('data-subject');
                    const className = this.getAttribute('data-class');

                    absenceId.value = id;
                    absenceDate.textContent = date;
                    absenceClass.textContent = subject + ' - ' + className;

                    justificationFormModal.style.display = 'flex';
                });
            });

            if (closeJustificationFormModal) {
                closeJustificationFormModal.addEventListener('click', function() {
                    justificationFormModal.style.display = 'none';
                });
            }

            if (cancelJustificationBtn) {
                cancelJustificationBtn.addEventListener('click', function() {
                    justificationFormModal.style.display = 'none';
                });
            }

            if (justificationFormModalOverlay) {
                justificationFormModalOverlay.addEventListener('click', function() {
                    justificationFormModal.style.display = 'none';
                });
            }

            // View Justification Modal
            const viewJustificationBtns = document.querySelectorAll('.view-justification-btn');
            const viewJustificationModal = document.getElementById('viewJustificationModal');
            const closeViewJustificationModal = document.getElementById('closeViewJustificationModal');
            const closeViewDetailsBtn = document.getElementById('closeViewDetailsBtn');
            const viewJustificationModalOverlay = document.getElementById('viewJustificationModalOverlay');
            const viewAbsenceDate = document.getElementById('viewAbsenceDate');
            const viewAbsenceClass = document.getElementById('viewAbsenceClass');
            const viewJustificationText = document.getElementById('viewJustificationText');
            const justificationStatusBadge = document.getElementById('justificationStatusBadge');
            const attachmentSection = document.getElementById('attachmentSection');
            const attachmentLink = document.getElementById('attachmentLink');

            viewJustificationBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const date = this.getAttribute('data-date');
                    const subject = this.getAttribute('data-subject');
                    const className = this.getAttribute('data-class');
                    const justification = this.getAttribute('data-justification');
                    const status = this.getAttribute('data-status');
                    const badgeClass = this.getAttribute('data-badge-class');
                    const file = this.getAttribute('data-file');

                    viewAbsenceDate.textContent = date;
                    viewAbsenceClass.textContent = subject + ' - ' + className;
                    viewJustificationText.textContent = justification;
                    justificationStatusBadge.textContent = status;
                    justificationStatusBadge.className = 'attendance-status ' + badgeClass;

                    if (file) {
                        attachmentSection.style.display = 'block';
                        attachmentLink.href = '/uwuweb/uploads/justifications/' + file;
                    } else {
                        attachmentSection.style.display = 'none';
                    }

                    viewJustificationModal.style.display = 'flex';
                });
            });

            if (closeViewJustificationModal) {
                closeViewJustificationModal.addEventListener('click', function() {
                    viewJustificationModal.style.display = 'none';
                });
            }

            if (closeViewDetailsBtn) {
                closeViewDetailsBtn.addEventListener('click', function() {
                    viewJustificationModal.style.display = 'none';
                });
            }

            if (viewJustificationModalOverlay) {
                viewJustificationModalOverlay.addEventListener('click', function() {
                    viewJustificationModal.style.display = 'none';
                });
            }

            // File input validation
            const justificationFile = document.getElementById('justification_file');
            if (justificationFile) {
                justificationFile.addEventListener('change', function() {
                    const maxSize = 2 * 1024 * 1024; // 2MB
                    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png', 'image/gif'];

                    if (this.files.length > 0) {
                        const file = this.files[0];

                        if (file.size > maxSize) {
                            alert('File size exceeds the 2MB limit.');
                            this.value = '';
                        } else if (!allowedTypes.includes(file.type)) {
                            alert('File type not allowed. Please upload PDF, DOC, DOCX, JPG, or PNG files only.');
                            this.value = '';
                        }
                    }
                });
            }
        });
    </script>

<?php
// Include page footer
include '../includes/footer.php';
?>
