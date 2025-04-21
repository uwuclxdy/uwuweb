<?php
/**
 * Student Attendance View
 *
 * Allows students to view their own attendance records in read-only mode
 *
 * Functions:
 * - getStudentAttendance($studentId) - Gets attendance records for a student
 * - getStudentId() - Gets the student ID for the current user
 * - getAttendanceStatusLabel($status) - Converts attendance status code to readable label
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
$pdo = safeGetDBConnection('student/attendance.php');

// Get attendance data
$attendance = getStudentAttendance($studentId);
$attendanceStats = calculateAttendanceStats($attendance);

// Include header
include '../includes/header.php';
?>

    <!-- HTML comment: Page title and description card -->

    <!-- HTML comment: Attendance summary statistics card showing present, absent, late and justified percentages -->

    <!-- HTML comment: Attendance records table with date, period, subject, status, justification and action columns -->

<?php
// Include page footer
include '../includes/footer.php';
?>
