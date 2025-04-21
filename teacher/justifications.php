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
require_once 'teacher_functions.php';

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId(getUserId());
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else if (isset($_POST['approve_justification'])) {
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

// Get pending justifications
$justifications = getPendingJustifications($teacherId);

// Selected justification for detailed view
$selectedJustificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedJustification = $selectedJustificationId ? getJustificationById($selectedJustificationId) : null;

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>

    <!-- HTML comment: Main card with page title and description -->

<?php if (!empty($message)): ?>
    <!-- HTML comment: Alert message for success, error, or warning -->
<?php endif; ?>

<?php if ($selectedJustification): ?>
    <!-- HTML comment: Detailed justification view card -->

    <!-- HTML comment: Header with title and back button -->

    <!-- HTML comment: Student and absence information section -->
    <!-- HTML comment: Shows student name, class, date, period, and subject -->

    <!-- HTML comment: Justification text display section -->

    <?php if (!empty($selectedJustification['justification_file'])): ?>
        <!-- HTML comment: Supporting document link section -->
    <?php endif; ?>

    <!-- HTML comment: Approval and rejection forms side by side -->
    <!-- HTML comment: Green approval card with submit button -->
    <!-- HTML comment: Red rejection card with reason textarea and submit button -->
<?php else: ?>
    <!-- HTML comment: List view of pending justifications -->

    <?php if (empty($justifications)): ?>
        <!-- HTML comment: Empty state message when no pending justifications exist -->
    <?php else: ?>
        <!-- HTML comment: Table of pending justifications -->
        <!-- HTML comment: Columns for student, class, date, period, file status, and actions -->
        <!-- HTML comment: Review button for each justification -->
    <?php endif; ?>
<?php endif; ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
