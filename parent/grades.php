<?php
/**
 * Parent Grades View
 *
 * Allows parents to view grade records for their linked students in read-only mode
 *
 * Functions:
 * - getParentStudents($parentId) - Gets list of students linked to a parent
 * - getParentId() - Gets the parent ID for the current user
 * - getStudentGrades($studentId) - Gets grade records for a student
 * - getStudentClasses($studentId) - Gets classes that a student is enrolled in
 * - getClassGrades($studentId, $classId) - Gets grades for a specific class
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

// Get students linked to the parent
$students = getParentStudents($parentId);

// Selected student for viewing grades
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($students[0]['student_id'] ?? 0);

// Get classes and selected class if student is selected
$classes = $selectedStudentId ? getStudentClasses($selectedStudentId) : [];
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_id'] ?? 0);

// Get grades if class is selected
$grades = ($selectedStudentId && $selectedClassId) ? getClassGrades($selectedStudentId, $selectedClassId) : [];

// Calculate class average
$classAverage = calculateClassAverage($grades);
$gradeLetter = getGradeLetter($classAverage);

// Find the selected student's name
$selectedStudent = null;
foreach ($students as $student) {
    if ($student['student_id'] == $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

// Find the selected class
$selectedClass = null;
foreach ($classes as $class) {
    if ($class['class_id'] == $selectedClassId) {
        $selectedClass = $class;
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

    <!-- HTML comment: Class selection card with dropdown menu -->

    <?php if ($selectedClass && $selectedClassId): ?>
        <!-- HTML comment: Class information and grades card with subject details and overall grade display -->

        <?php if (empty($grades)): ?>
            <!-- HTML comment: Message for when no grades have been entered yet -->
        <?php else: ?>
            <!-- HTML comment: Grades table with assignment details, scores, weights and comments -->
        <?php endif; ?>

        <!-- HTML comment: Grade summary section with calculation explanation and grade distribution chart -->
    <?php endif; ?>
<?php endif; ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
