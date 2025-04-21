<?php
/**
 * Student Grades View
 *
 * Allows students to view their own grades and class averages
 *
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

// Database connection
$pdo = safeGetDBConnection('student/grades.php');

// Get grades data
$grades = getStudentGrades($studentId);
$gradeStats = calculateGradeStatistics($grades);

// Include header
include '../includes/header.php';
?>

    <!-- HTML comment: Page title and description card -->

    <!-- HTML comment: Grades display section with conditional messaging if no grades found -->

    <!-- HTML comment: For each subject, display cards with classes and their grades -->

    <!-- HTML comment: For each class, show weighted average and detailed grade items in a table -->

    <!-- HTML comment: CSS styles for success/error text colors -->

<?php
// Include page footer
include '../includes/footer.php';
?>
