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
require_once 'student_functions.php';

// Ensure only students can access this page
requireRole(ROLE_STUDENT);

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) {
    die('Error: Student account not found.');
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

?>
    <!-- HTML comment: Page title and description card -->

    <!-- HTML comment: Alert message display section (if message exists) -->

    <!-- HTML comment: Information card about valid justification reasons -->

    <!-- HTML comment: Absences list table with date, period, subject, status and action columns -->

    <!-- HTML comment: Justification form modal (hidden by default) -->

    <!-- HTML comment: View justification modal (hidden by default) -->

    <!-- HTML comment: JavaScript for modal interactions and form validation -->

<?php
// Include page footer
include '../includes/footer.php';
?>
