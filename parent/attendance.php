<?php
/**
 * Parent Attendance View
 *
 * Allows parents to view attendance records for their linked students in read-only mode
 *
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once '../parent/parent_functions.php';

// Ensure only parents can access this page
requireRole(ROLE_PARENT);

// Get the parent ID of the logged-in user
$parentId = getParentId();
if (!$parentId) {
    die('Error: Parent account not found.');
}

// Database connection
$pdo = safeGetDBConnection('parent/attendance.php');

// Get students linked to the parent
$students = getParentStudents($parentId);

// Selected student for viewing attendance
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($students[0]['student_id'] ?? 0);

// Get attendance data if student is selected
$attendanceRecords = $selectedStudentId ? getStudentAttendance($selectedStudentId) : [];

// Find the selected student's name
$selectedStudent = null;
foreach ($students as $student) {
    if ($student['student_id'] == $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

// Include header
include '../includes/header.php';
?>

    <!-- HTML comment: Page title card with heading and description -->

<?php if (count($students) > 1): ?>
    <!-- HTML comment: Student selection form with dropdown menu for parents with multiple students -->
<?php endif; ?>

<?php if ($selectedStudent): ?>
    <!-- HTML comment: Student information card with profile icon and personal details -->

    <!-- HTML comment: Attendance summary card with statistics -->

    <?php if (empty($attendanceRecords)): ?>
        <!-- HTML comment: Message for when no attendance records are found -->
    <?php else: ?>
        <?php
        // Calculate attendance statistics
        $totalRecords = count($attendanceRecords);
        $present = 0;
        $absent = 0;
        $late = 0;
        $justified = 0;

        foreach ($attendanceRecords as $record) {
            switch ($record['status']) {
                case 'P':
                    $present++;
                    break;
                case 'A':
                    $absent++;
                    if ($record['approved'] == 1) {
                        $justified++;
                    }
                    break;
                case 'L':
                    $late++;
                    break;
            }
        }

        $presentPercentage = $totalRecords > 0 ? ($present / $totalRecords) * 100 : 0;
        $absentPercentage = $totalRecords > 0 ? ($absent / $totalRecords) * 100 : 0;
        $latePercentage = $totalRecords > 0 ? ($late / $totalRecords) * 100 : 0;
        $justifiedPercentage = $absent > 0 ? ($justified / $absent) * 100 : 0;
        ?>

        <!-- HTML comment: Attendance statistics cards showing present, absent, late and justified percentages -->

        <!-- HTML comment: Monthly attendance trend visualization chart -->
    <?php endif; ?>

    <!-- HTML comment: Detailed attendance records table with filtering options -->

<?php endif; ?>

    <!-- HTML comment: View justification modal dialog for absence details -->

    <!-- HTML comment: JavaScript for handling the justification modal interactions -->

<?php
// Include page footer
include '../includes/footer.php';
?>
