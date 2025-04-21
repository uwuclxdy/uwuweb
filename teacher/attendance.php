<?php
/**
 * Teacher Attendance Form
 *
 * Provides interface for teachers to manage student attendance
 * Supports tracking attendance for class periods
 *
 * Functions:
 * - getTeacherId($userId) - Retrieves teacher ID from user ID
 * - getTeacherClasses($teacherId) - Gets classes taught by a teacher
 * - getClassStudents($classId) - Gets students enrolled in a class
 * - getClassPeriods($classId) - Gets periods for a specific class
 * - getPeriodAttendance($periodId) - Gets attendance records for a period
 * - addPeriod($classId, $periodDate, $periodLabel) - Adds a new period to a class
 * - saveAttendance($enroll_id, $period_id, $status) - Saves attendance status for a student
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'teacher_functions.php';

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId();
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Database connection
$pdo = safeGetDBConnection('teacher/attendance.php');

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else if (isset($_POST['add_period'])) {
        $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
        $periodDate = $_POST['period_date'] ?? '';
        $periodLabel = isset($_POST['period_label']) ? trim($_POST['period_label']) : '';

        if ($classId <= 0 || empty($periodDate) || empty($periodLabel)) {
            $message = 'Please fill out all period details.';
            $messageType = 'error';
        } else {
            try {
                if (addPeriod($classId, $periodDate, $periodLabel)) {
                    $message = 'New period added successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error adding period. Please try again.';
                    $messageType = 'error';
                }
            } catch (JsonException $e) {

            }
        }
    } else if (isset($_POST['save_attendance'])) {
        $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
        $attendance = $_POST['attendance'] ?? [];

        if ($periodId <= 0 || empty($attendance)) {
            $message = 'Invalid attendance data.';
            $messageType = 'error';
        } else {
            $success = true;
            foreach ($attendance as $enrollId => $status) {
                try {
                    if (!saveAttendance($enrollId, $periodId, $status)) {
                        $success = false;
                    }
                } catch (JsonException $e) {

                }
            }

            if ($success) {
                $message = 'Attendance saved successfully.';
                $messageType = 'success';
            } else {
                $message = 'Some attendance records failed to save. Please try again.';
                $messageType = 'warning';
            }
        }
    }
}

// Get teacher's classes
$classes = getTeacherClasses($teacherId);

// Selected class and period
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_id'] ?? 0);
$periods = $selectedClassId ? getClassPeriods($selectedClassId) : [];
$selectedPeriodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : ($periods[0]['period_id'] ?? 0);

// Get students and attendance data if a period is selected
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$attendanceData = $selectedPeriodId ? getPeriodAttendance($selectedPeriodId) : [];

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>
<!-- HTML comment: Main card with page title and description -->

<?php if (!empty($message)): ?>
    <!-- HTML comment: Alert message box for success/error notifications -->
<?php endif; ?>

<!-- HTML comment: Class selection form with dropdown and select button -->

<?php if ($selectedClassId): ?>
    <!-- HTML comment: Period management section with two columns -->
    <!-- HTML comment: Left column shows existing periods with selection form -->
    <!-- HTML comment: Right column shows form to add new period with date and label inputs -->

    <?php if ($selectedPeriodId): ?>
        <!-- HTML comment: Attendance records card -->

        <?php
        $periodInfo = null;
        foreach ($periods as $period) {
            if ($period['period_id'] == $selectedPeriodId) {
                $periodInfo = $period;
                break;
            }
        }
        ?>

        <?php if ($periodInfo): ?>
            <!-- HTML comment: Period info header with date, label and attendance statistics -->

            <?php
            // Calculate attendance statistics
            $totalStudents = count($students);
            $present = 0;
            $absent = 0;
            $late = 0;

            foreach ($students as $student) {
                $status = isset($attendanceData[$student['enroll_id']]) ?
                    $attendanceData[$student['enroll_id']]['status'] : '';

                if ($status === 'P') {
                    $present++;
                }
                else if ($status === 'A') {
                    $absent++;
                }
                else if ($status === 'L') {
                    $late++;
                }
            }
            ?>

            <?php if (empty($students)): ?>
                <!-- HTML comment: Message for when no students are enrolled in the class -->
            <?php else: ?>
                <!-- HTML comment: Attendance form with table of students and attendance status options -->
                <!-- HTML comment: Each student row has Present/Absent/Late radio buttons -->
                <!-- HTML comment: Submit button to save attendance at bottom -->
            <?php endif; ?>
        <?php else: ?>
            <!-- HTML comment: Message for when period information is not found -->
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<!-- HTML comment: JavaScript for auto-submitting class selection and date validation -->

<?php
// Include page footer
include '../includes/footer.php';
?>
