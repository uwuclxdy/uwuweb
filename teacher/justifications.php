<?php
/**
 * Teacher Justification Approval Page
 *
 * Allows teachers to view and approve/reject student absence justifications
 *
 * Functions:
 * - getTeacherClasses($teacherId) - Gets list of classes taught by a teacher
 * - getPendingJustifications($teacherId) - Gets list of pending justifications for a teacher's classes
 * - getJustificationById($absenceId) - Gets detailed information about a specific justification
 * - getStudentName($studentId) - Gets student's full name by ID
 * - approveJustification($absenceId) - Approves a justification
 * - rejectJustification($absenceId, $reason) - Rejects a justification with a reason
 * - getJustificationFileInfo($absenceId) - Gets information about a saved justification file
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId(getUserId());
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Get teacher ID based on user ID
function getTeacherId($userId) {
    $pdo = safeGetDBConnection('getTeacherId()', false);
    if (!$pdo) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();
    return $result ? $result['teacher_id'] : null;
}

// Get pending justifications for a teacher's classes
function getPendingJustifications($teacherId) {
    $pdo = safeGetDBConnection('getPendingJustifications()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification, 
            a.justification_file,
            a.approved, 
            p.period_date, 
            p.period_label,
            s.name AS subject_name,
            c.title AS class_title,
            st.student_id,
            st.first_name,
            st.last_name,
            st.class_code
         FROM attendance a
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN students st ON e.student_id = st.student_id
         WHERE c.teacher_id = :teacher_id 
         AND a.justification IS NOT NULL 
         AND a.approved IS NULL
         ORDER BY p.period_date DESC"
    );
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get justification by ID
function getJustificationById($absenceId) {
    $pdo = safeGetDBConnection('getJustificationById()', false);
    if (!$pdo) {
        return null;
    }
    
    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification, 
            a.justification_file,
            a.approved, 
            p.period_date, 
            p.period_label,
            s.name AS subject_name,
            c.title AS class_title,
            st.student_id,
            st.first_name,
            st.last_name,
            st.class_code
         FROM attendance a
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN students st ON e.student_id = st.student_id
         WHERE a.att_id = :att_id"
    );
    $stmt->execute(['att_id' => $absenceId]);
    return $stmt->fetch();
}

// Approve a justification
function approveJustification($absenceId) {
    $pdo = safeGetDBConnection('approveJustification()', false);
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare(
            "UPDATE attendance 
             SET approved = 1, reject_reason = NULL
             WHERE att_id = :att_id"
        );
        return $stmt->execute(['att_id' => $absenceId]);
    } catch (PDOException $e) {
        error_log("Error approving justification: " . $e->getMessage());
        return false;
    }
}

// Reject a justification
function rejectJustification($absenceId, $reason) {
    $pdo = safeGetDBConnection('rejectJustification()', false);
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare(
            "UPDATE attendance 
             SET approved = 0, reject_reason = :reason
             WHERE att_id = :att_id"
        );
        return $stmt->execute([
            'att_id' => $absenceId,
            'reason' => $reason
        ]);
    } catch (PDOException $e) {
        error_log("Error rejecting justification: " . $e->getMessage());
        return false;
    }
}

// Get justification file information
function getJustificationFileInfo($absenceId) {
    try {
        $pdo = safeGetDBConnection('getJustificationFileInfo()', false);
        if (!$pdo) {
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
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        if (isset($_POST['approve_justification'])) {
            $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
            
            if ($absenceId <= 0) {
                $message = 'Invalid absence selected.';
                $messageType = 'error';
            } else if (approveJustification($absenceId)) {
                $message = 'Justification approved successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error approving justification. Please try again.';
                $messageType = 'error';
            }
        } else if (isset($_POST['reject_justification'])) {
            $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
            $reason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';
            
            if ($absenceId <= 0) {
                $message = 'Invalid absence selected.';
                $messageType = 'error';
            } else if (empty($reason)) {
                $message = 'Please provide a reason for rejection.';
                $messageType = 'error';
            } else if (rejectJustification($absenceId, $reason)) {
                $message = 'Justification rejected successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error rejecting justification. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Get pending justifications
$justifications = getPendingJustifications($teacherId);

// Selected justification for detailed view
$selectedJustificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedJustification = $selectedJustificationId ? getJustificationById($selectedJustificationId) : null;

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include header
include '../includes/header.php';
?>

<?php /* 
    [TEACHER JUSTIFICATIONS PAGE PLACEHOLDER]
    Components:
    - Page container with justification review layout
    
    - Page title "Absence Justifications"
    
    - Alert message display (when $message is not empty)
      - Different styling based on $messageType (success, error, warning)
    
    - Main content with two possible views:
    
    1. LIST VIEW (when no justification is selected):
      - Info card explaining the purpose of the page
      - Card with table of pending justifications:
        - Headers: Student, Class, Date, Period, Action
        - For each justification:
          - Student name and class code
          - Subject and class title
          - Absence date
          - Period label
          - "Review" button to open detailed view
      - Empty state message when no pending justifications
    
    2. DETAIL VIEW (when justification is selected):
      - Card with justification details:
        - Student information (name and class)
        - Absence details (date, class, period)
        - Justification text
        - File attachment link (if available)
        
      - Approval form with:
        - Approve button
        - Reject button with reason text field
        - Cancel button to return to list view
*/ ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
