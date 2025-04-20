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

<?php /* 
    [STUDENT JUSTIFICATION PAGE PLACEHOLDER]
    Components:
    - Page container with justification page layout
    
    - Page title "Absence Justifications"
    
    - Alert message display (when $message is not empty)
      - Different styling based on $messageType (success, error, warning)
    
    - Information card explaining:
      - Purpose of the page
      - Instructions for submitting justifications
      
    - Conditional content based on $absences:
      IF empty($absences):
        - Card showing "You have no absences that require justification"
      ELSE:
        - Card with table of absences containing:
          - Headers: Date, Period, Subject, Status, Action
          - For each absence, a row showing:
              - Formatted date
              - Period label
              - Subject name and class title
              - Status badge with appropriate styling
              - Action button based on justification status
*/ ?>

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
<?php endforeach; ?>

<?php /* 
    [JUSTIFICATION MODAL PLACEHOLDERS]
    Components:
    
    1. Justification Form Modal:
       - Modal dialog with card styling
       - Header with title and close button
       - Form containing:
         - Hidden CSRF token and absence ID fields
         - Textarea for justification explanation
         - File upload field for supporting documents
         - Submit and cancel buttons
    
    2. View Justification Modal:
       - Modal dialog with card styling
       - Header with title and close button
       - Absence details display
       - Justification text display
       - File attachment section (if present)
       - Status display showing approval state
       - Close button
*/ ?>

<?php /* 
    [JUSTIFICATION PAGE JAVASCRIPT PLACEHOLDER]
    Components:
    - Event listeners for opening/closing modals
    - Form handling and validation
    - API calls to get justification file information
    - Dynamic content updates for modal displays
*/ ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
