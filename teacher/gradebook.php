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

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId(getUserId());
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Database connection - using safe connection to prevent null pointer exceptions
$pdo = safeGetDBConnection('teacher/gradebook.php');

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

// Get classes taught by teacher
function getTeacherClasses($teacherId) {
    $pdo = safeGetDBConnection('getTeacherClasses()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT c.class_id, c.title, s.name AS subject_name, t.name AS term_name
         FROM classes c
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN terms t ON c.term_id = t.term_id
         WHERE c.teacher_id = :teacher_id
         ORDER BY t.start_date DESC, s.name"
    );
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get students enrolled in a class
function getClassStudents($classId) {
    $pdo = safeGetDBConnection('getClassStudents()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT e.enroll_id, s.student_id, s.first_name, s.last_name, s.class_code
         FROM enrollments e
         JOIN students s ON e.student_id = s.student_id
         WHERE e.class_id = :class_id
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get grade items for a class
function getGradeItems($classId) {
    $pdo = safeGetDBConnection('getGradeItems()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT item_id, name, description, max_points, weight, DATE_FORMAT(date, '%Y-%m-%d') as item_date
         FROM grade_items
         WHERE class_id = :class_id
         ORDER BY date, name"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get grades for a class
function getClassGrades($classId) {
    $pdo = safeGetDBConnection('getClassGrades()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT g.grade_id, g.enroll_id, g.item_id, g.points, g.feedback
         FROM grades g
         JOIN enrollments e ON g.enroll_id = e.enroll_id
         JOIN grade_items gi ON g.item_id = gi.item_id
         WHERE e.class_id = :class_id"
    );
    $stmt->execute(['class_id' => $classId]);
    
    // Index by enrollment_id and item_id for easier lookup
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[$row['enroll_id']][$row['item_id']] = $row;
    }
    return $result;
}

// Add a new grade item
function addGradeItem($classId, $name, $description, $maxPoints, $weight, $date) {
    $pdo = safeGetDBConnection('addGradeItem()', false);
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO grade_items (class_id, name, description, max_points, weight, date)
             VALUES (:class_id, :name, :description, :max_points, :weight, :date)"
        );
        return $stmt->execute([
            'class_id' => $classId,
            'name' => $name,
            'description' => $description,
            'max_points' => $maxPoints,
            'weight' => $weight,
            'date' => $date
        ]);
    } catch (PDOException $e) {
        error_log("Error adding grade item: " . $e->getMessage());
        return false;
    }
}

// Save a grade
function saveGrade($enrollId, $itemId, $points, $feedback) {
    $pdo = safeGetDBConnection('saveGrade()', false);
    if (!$pdo) {
        return false;
    }
    
    try {
        // Check if grade already exists
        $stmt = $pdo->prepare(
            "SELECT grade_id FROM grades
             WHERE enroll_id = :enroll_id AND item_id = :item_id"
        );
        $stmt->execute([
            'enroll_id' => $enrollId,
            'item_id' => $itemId
        ]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing grade
            $stmt = $pdo->prepare(
                "UPDATE grades
                 SET points = :points, feedback = :feedback
                 WHERE grade_id = :grade_id"
            );
            return $stmt->execute([
                'grade_id' => $existing['grade_id'],
                'points' => $points,
                'feedback' => $feedback
            ]);
        } else {
            // Insert new grade
            $stmt = $pdo->prepare(
                "INSERT INTO grades (enroll_id, item_id, points, feedback)
                 VALUES (:enroll_id, :item_id, :points, :feedback)"
            );
            return $stmt->execute([
                'enroll_id' => $enrollId,
                'item_id' => $itemId,
                'points' => $points,
                'feedback' => $feedback
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error saving grade: " . $e->getMessage());
        return false;
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
        if (isset($_POST['add_grade_item'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
            $name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
            $description = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
            $maxPoints = isset($_POST['max_points']) ? (float)$_POST['max_points'] : 0;
            $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 0;
            $date = isset($_POST['item_date']) ? $_POST['item_date'] : date('Y-m-d');
            
            if ($classId <= 0 || empty($name) || $maxPoints <= 0) {
                $message = 'Please fill out all required fields for the grade item.';
                $messageType = 'error';
            } else {
                if (addGradeItem($classId, $name, $description, $maxPoints, $weight, $date)) {
                    $message = 'New grade item added successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error adding grade item. Please try again.';
                    $messageType = 'error';
                }
            }
        } else if (isset($_POST['save_grades'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
            $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            $grades = isset($_POST['grade']) ? $_POST['grade'] : [];
            $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : [];
            
            if ($classId <= 0 || $itemId <= 0) {
                $message = 'Invalid grade data.';
                $messageType = 'error';
            } else {
                $success = true;
                foreach ($grades as $enrollId => $points) {
                    $studentFeedback = isset($feedback[$enrollId]) ? $feedback[$enrollId] : '';
                    if (!saveGrade($enrollId, $itemId, $points, $studentFeedback)) {
                        $success = false;
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
}

// Get teacher's classes
$classes = getTeacherClasses($teacherId);

// Selected class and grade item
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (isset($classes[0]['class_id']) ? $classes[0]['class_id'] : 0);
$gradeItems = $selectedClassId ? getGradeItems($selectedClassId) : [];
$selectedItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Get students and grades if a class is selected
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$grades = $selectedClassId ? getClassGrades($selectedClassId) : [];

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include header
include '../includes/header.php';
?>

<?php /* 
    [TEACHER GRADEBOOK PAGE PLACEHOLDER]
    Components:
    - Page container with gradebook layout
    
    - Page title "Grade Management"
    
    - Alert message display when $message is not empty
      - Styling based on $messageType (success, error, warning)
    
    - Class selection form:
      - Dropdown list of classes taught by teacher
      - Submit button
    
    - If class is selected:
      - Tab navigation with:
        - "Grade Items" tab for managing assessments
        - "Enter Grades" tab for adding student scores
        - "Grade Overview" tab for viewing all grades in the class
      
      - Grade Items tab containing:
        - Table of existing grade items with:
          - Name, Description, Max Points, Weight, Date
          - Edit/Delete buttons for each item
        - Form to add new grade item
      
      - Enter Grades tab containing:
        - Grade item selection dropdown
        - If grade item selected:
          - Form with table of students
          - For each student:
            - Input field for points
            - Input field for feedback
          - Save button
      
      - Grade Overview tab containing:
        - Comprehensive table with:
          - Students listed in rows
          - Grade items in columns
          - Final average/grade in last column
          - Color-coding based on grade performance
*/ ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
