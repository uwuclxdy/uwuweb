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

// CSS styles are included in header.php

// Ensure only parents can access this page
requireRole(ROLE_PARENT);

// Get the parent ID of the logged-in user
$parentId = getParentId();
if (!$parentId) {
    die('Error: Parent account not found.');
}

// Get parent ID for the current user
function getParentId() {
    $pdo = safeGetDBConnection('getParentId()', false);
    if (!$pdo) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = :user_id");
    $stmt->execute(['user_id' => getUserId()]);
    $result = $stmt->fetch();
    return $result ? $result['parent_id'] : null;
}

// Get students linked to a parent
function getParentStudents($parentId) {
    $pdo = safeGetDBConnection('getParentStudents()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT s.student_id, s.first_name, s.last_name, s.class_code
         FROM students s
         JOIN parent_student ps ON s.student_id = ps.student_id
         WHERE ps.parent_id = :parent_id
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute(['parent_id' => $parentId]);
    return $stmt->fetchAll();
}

// Get classes that a student is enrolled in
function getStudentClasses($studentId) {
    $pdo = safeGetDBConnection('getStudentClasses()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT c.class_id, c.title, s.name AS subject_name, t.name AS term_name,
                CONCAT(tc.first_name, ' ', tc.last_name) AS teacher_name
         FROM enrollments e
         JOIN classes c ON e.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN terms t ON c.term_id = t.term_id
         JOIN teachers tc ON c.teacher_id = tc.teacher_id
         WHERE e.student_id = :student_id
         ORDER BY t.start_date DESC, s.name"
    );
    $stmt->execute(['student_id' => $studentId]);
    return $stmt->fetchAll();
}

// Get grades for a specific class
function getClassGrades($studentId, $classId) {
    $pdo = safeGetDBConnection('getClassGrades()', false);
    if (!$pdo) {
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT g.grade_id, g.points, g.feedback, gi.item_id, gi.name, 
                gi.description, gi.max_points, gi.weight, gi.date
         FROM grades g
         JOIN grade_items gi ON g.item_id = gi.item_id
         JOIN enrollments e ON g.enroll_id = e.enroll_id
         WHERE e.student_id = :student_id AND gi.class_id = :class_id
         ORDER BY gi.date"
    );
    $stmt->execute([
        'student_id' => $studentId,
        'class_id' => $classId
    ]);
    return $stmt->fetchAll();
}

// Calculate overall grade average for a class
function calculateClassAverage($grades) {
    if (empty($grades)) {
        return null;
    }
    
    $totalPoints = 0;
    $totalMaxPoints = 0;
    $weightedGrade = 0;
    $totalWeight = 0;
    
    foreach ($grades as $grade) {
        $totalPoints += $grade['points'];
        $totalMaxPoints += $grade['max_points'];
        
        if ($grade['weight'] > 0) {
            $percentage = $grade['max_points'] > 0 ? ($grade['points'] / $grade['max_points']) : 0;
            $weightedGrade += $percentage * $grade['weight'];
            $totalWeight += $grade['weight'];
        }
    }
    
    // If weights are used, calculate weighted average
    if ($totalWeight > 0) {
        return ($weightedGrade / $totalWeight) * 100;
    }
    
    // Otherwise use simple average
    return $totalMaxPoints > 0 ? ($totalPoints / $totalMaxPoints) * 100 : null;
}

// Get grade letter based on percentage
function getGradeLetter($percentage) {
    if ($percentage === null) {
        return 'N/A';
    }
    
    if ($percentage >= 90) {
        return 'A';
    } else if ($percentage >= 80) {
        return 'B';
    } else if ($percentage >= 70) {
        return 'C';
    } else if ($percentage >= 60) {
        return 'D';
    } else {
        return 'F';
    }
}

// Get students linked to the parent
$students = getParentStudents($parentId);

// Selected student for viewing grades
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isset($students[0]['student_id']) ? $students[0]['student_id'] : 0);

// Get classes and selected class if student is selected
$classes = $selectedStudentId ? getStudentClasses($selectedStudentId) : [];
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (isset($classes[0]['class_id']) ? $classes[0]['class_id'] : 0);

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

<?php /* 
    [PARENT GRADES PAGE PLACEHOLDER]
    Components:
    - Page container with grades view layout
    
    - Page title "Grade Records"
    
    - If multiple students are linked to parent:
      - Student selection form with dropdown of students
    
    - Selected student information:
      - Student name display
      - Class code display
    
    - Class selection form with dropdown of classes
    
    - If class is selected:
      - Class information panel:
        - Subject name and class title
        - Term name
        - Teacher name
        - Overall grade average with letter grade
      
      - Grade breakdown table with:
        - Headers: Assignment, Date, Score, Weight, Comments
        - For each grade item:
          - Assignment name with popup description
          - Formatted date
          - Score as points and percentage
          - Weight percentage
          - Teacher feedback (if provided)
        - Visual indicators for performance on each assignment
    
    - Grade summary section with:
      - Overall grade calculation explanation
      - Visual grade distribution chart
    
    - Responsive design for mobile viewing
*/ ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
