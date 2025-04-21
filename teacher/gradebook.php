<?php
/**
 * Teacher Grade Book
 *
 * Provides interface for teachers to manage student grades
 * Supports viewing, adding, and editing grades for assigned classes
 *
 * Functions:
 * - getTeacherId($userId) - Retrieves teacher ID from user ID
 * - getTeacherClasses($teacherId) - Gets classes taught by a teacher
 * - getClassStudents($classId) - Gets students enrolled in a class
 * - getGradeItems($classId) - Gets grade items for a class
 * - getClassGrades($classId) - Gets all grades for a class
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
$teacherId = getTeacherId();
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Database connection - using safe connection to prevent null pointer exceptions
$pdo = safeGetDBConnection('teacher/gradebook.php');

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else if (isset($_POST['add_grade_item'])) {
        $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
        $name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
        $description = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
        $maxPoints = isset($_POST['max_points']) ? (float)$_POST['max_points'] : 0;
        $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 0;
        $date = $_POST['item_date'] ?? date('Y-m-d');

        if ($classId <= 0 || empty($name) || $maxPoints <= 0) {
            $message = 'Please fill out all required fields for the grade item.';
            $messageType = 'error';
        } else {
            try {
                if (addGradeItem($classId, $name, $maxPoints, $weight)) {
                    $message = 'New grade item added successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error adding grade item. Please try again.';
                    $messageType = 'error';
                }
            } catch (JsonException $e) {

            }
        }
    } else if (isset($_POST['save_grades'])) {
        $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
        $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
        $grades = $_POST['grade'] ?? [];
        $feedback = $_POST['feedback'] ?? [];

        if ($classId <= 0 || $itemId <= 0) {
            $message = 'Invalid grade data.';
            $messageType = 'error';
        } else {
            $success = true;
            foreach ($grades as $enrollId => $points) {
                $studentFeedback = $feedback[$enrollId] ?? '';
                try {
                    if (!saveGrade($enrollId, $itemId, $points)) {
                        $success = false;
                    }
                } catch (JsonException $e) {

                }
            }

            if ($success) {
                $message = 'Grades saved successfully.';
                $messageType = 'success';
            } else {
                $message = 'Some grades failed to save. Please try again.';
                $messageType = 'warning';
            }
        }
    }
}

// Get teacher's classes
$classes = getTeacherClasses($teacherId);

// Selected class and grade item
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_id'] ?? 0);
$gradeItems = $selectedClassId ? getGradeItems($selectedClassId) : [];
$selectedItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Get students and grades if a class is selected
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$grades = $selectedClassId ? getClassGrades($selectedClassId) : [];

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>

    <!-- HTML comment: Main card with page title and description -->

<?php if (!empty($message)): ?>
    <!-- HTML comment: Alert message for success, error, or warning -->
<?php endif; ?>

    <!-- HTML comment: Class selection form with dropdown and select button -->

<?php if ($selectedClassId): ?>
    <!-- HTML comment: Tab navigation with three tabs: Grade Items, Enter Grades, Grade Overview -->

    <!-- HTML comment: Grade Items tab content with table of assessment items -->
    <!-- HTML comment: Shows name, points, weight and has edit/delete buttons for each item -->
    <!-- HTML comment: Shows empty state message if no grade items exist -->

    <!-- HTML comment: Enter Grades tab content with grade item selection and student grade form -->
    <!-- HTML comment: Form has student list with point inputs and feedback field for each student -->
    <!-- HTML comment: Shows empty state message if no students enrolled -->

    <!-- HTML comment: Grade Overview tab with complete grade summary table -->
    <!-- HTML comment: Shows each student with all grade items and calculated averages -->
    <!-- HTML comment: Color codes grades (high/medium/low) based on score percentages -->
    <!-- HTML comment: Shows empty state message if no data available -->

    <!-- HTML comment: Modal dialog for adding/editing grade items -->
    <!-- HTML comment: Fields for name, description, max points, weight, and date -->

    <!-- HTML comment: Modal dialog for confirming grade item deletion -->
    <!-- HTML comment: Warning about permanently deleting grades -->
<?php endif; ?>

    <!-- HTML comment: JavaScript for tab switching, modal handling, and form validations -->

<?php
// Include page footer
include '../includes/footer.php';
?>
