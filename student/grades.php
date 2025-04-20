<?php
/**
 * Student Grades View
 *
 * Allows students to view their own grades and class averages
 *
 * Functions:
 * - getStudentGrades($studentId, $termId) - Gets grades for a student
 * - getClassAverage($classId) - Gets class average for a specific class
 * - calculateGradeStatistics($grades) - Calculates grade statistics for visualization
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Add CSS link for this specific page
echo '<link rel="stylesheet" href="/uwuweb/assets/css/student-grades.css">';

// Ensure only students can access this page
requireRole(ROLE_STUDENT);

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) {
    die('Error: Student account not found.');
}

// Database connection
$pdo = safeGetDBConnection('student/grades.php');

// Get current term
$currentTerm = getCurrentTerm();
$termId = $currentTerm ? $currentTerm['term_id'] : null;

// Get student grades for the current term
function getStudentGrades($studentId, $termId = null) {
    $pdo = safeGetDBConnection('getStudentGrades() in student/grades.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getStudentGrades()");
        return [];
    }

    $query = "SELECT 
                g.grade_id,
                g.points,
                g.comment,
                gi.item_id,
                gi.name as item_name,
                gi.max_points,
                gi.weight,
                c.class_id,
                c.title as class_title,
                s.subject_id,
                s.name as subject_name,
                e.enroll_id
             FROM grades g
             JOIN grade_items gi ON g.item_id = gi.item_id
             JOIN enrollments e ON g.enroll_id = e.enroll_id
             JOIN classes c ON gi.class_id = c.class_id
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN terms t ON c.term_id = t.term_id
             WHERE e.student_id = :student_id";

    if ($termId) {
        $query .= " AND c.term_id = :term_id";
    }

    $query .= " ORDER BY s.name ASC, c.title ASC, gi.name ASC";

    $stmt = $pdo->prepare($query);

    $params = ['student_id' => $studentId];
    if ($termId) {
        $params['term_id'] = $termId;
    }

    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get class average for a specific class
function getClassAverage($classId) {
    $pdo = safeGetDBConnection('getClassAverage() in student/grades.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getClassAverage()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT 
            gi.item_id,
            gi.name as item_name,
            gi.max_points,
            gi.weight,
            AVG(g.points) as avg_points,
            MIN(g.points) as min_points,
            MAX(g.points) as max_points
         FROM grade_items gi
         JOIN grades g ON gi.item_id = g.item_id
         JOIN enrollments e ON g.enroll_id = e.enroll_id
         WHERE gi.class_id = :class_id
         GROUP BY gi.item_id, gi.name, gi.max_points, gi.weight
         ORDER BY gi.name"
    );

    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Calculate weighted average for a set of grades
function calculateWeightedAverage($grades) {
    $totalWeightedPoints = 0;
    $totalWeight = 0;

    foreach ($grades as $grade) {
        $percentage = ($grade['points'] / $grade['max_points']) * 100;
        $totalWeightedPoints += $percentage * $grade['weight'];
        $totalWeight += $grade['weight'];
    }

    if ($totalWeight > 0) {
        return round($totalWeightedPoints / $totalWeight, 1);
    }

    return 0;
}

// Calculate grade statistics
function calculateGradeStatistics($grades) {
    $stats = [];

    // Group grades by subject and class
    foreach ($grades as $grade) {
        $subjectId = $grade['subject_id'];
        $classId = $grade['class_id'];

        if (!isset($stats[$subjectId])) {
            $stats[$subjectId] = [
                'subject_name' => $grade['subject_name'],
                'classes' => []
            ];
        }

        if (!isset($stats[$subjectId]['classes'][$classId])) {
            $stats[$subjectId]['classes'][$classId] = [
                'class_id' => $classId,
                'class_title' => $grade['class_title'],
                'grades' => [],
                'average' => 0
            ];
        }

        $stats[$subjectId]['classes'][$classId]['grades'][] = $grade;
    }

    // Calculate weighted average for each class
    foreach ($stats as $subjectId => &$subject) {
        foreach ($subject['classes'] as $classId => &$class) {
            $class['average'] = calculateWeightedAverage($class['grades']);
        }
    }

    return $stats;
}

// Get available terms for filtering
function getAvailableTerms() {
    $pdo = safeGetDBConnection('getAvailableTerms() in student/grades.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getAvailableTerms()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT t.term_id, t.name, t.start_date, t.end_date
         FROM terms t
         JOIN classes c ON t.term_id = c.term_id
         JOIN enrollments e ON c.class_id = e.class_id
         JOIN students s ON e.student_id = s.student_id
         WHERE s.user_id = :user_id
         ORDER BY t.start_date DESC"
    );

    $stmt->execute(['user_id' => getUserId()]);
    return $stmt->fetchAll();
}

// Process term filter
$selectedTermId = null;
if (isset($_GET['term_id']) && is_numeric($_GET['term_id'])) {
    $selectedTermId = (int)$_GET['term_id'];
} else {
    $selectedTermId = $termId;
}

// Get grades data
$grades = getStudentGrades($studentId, $selectedTermId);
$gradeStats = calculateGradeStatistics($grades);
$availableTerms = getAvailableTerms();
?>

<?php /* 
    [STUDENT GRADES PAGE PLACEHOLDER]
    Components:
    - Page title "My Grades"
    
    - Term filter card:
      - Term selection dropdown with:
        - Term name and date range for each option
        - Auto-submit on change
    
    - Grades display:
      - Empty state message when no grades available
      
      - When grades exist:
        - Organized by subject (outer cards)
        - Each subject contains class cards
        - Each class card includes:
          - Class title and overall average percentage
          - Table of assessments with:
            - Assessment name
            - Weight column
            - Score (points achieved/total)
            - Percentage with color-coded badge based on score
            - Class average comparison
*/ ?>

<?php if (empty($gradeStats)): ?>
    <?php /* [EMPTY GRADES PLACEHOLDER] - "No grades found for the selected term." */ ?>
<?php else: ?>
    <?php foreach ($gradeStats as $subjectId => $subject): ?>
        <?php /* [SUBJECT CARD PLACEHOLDER: <?= $subject['subject_name'] ?>] */ ?>
        
        <?php foreach ($subject['classes'] as $classId => $class): ?>
            <?php /* [CLASS CARD PLACEHOLDER: <?= $class['class_title'] ?> - Average: <?= $class['average'] ?>%] */ ?>
            
            <?php
            // Process class average data
            $classAverage = getClassAverage($classId);
            $averagesByItemId = [];
            foreach ($classAverage as $avg) {
                $averagesByItemId[$avg['item_id']] = $avg;
            }
            ?>
            
            <?php foreach ($class['grades'] as $grade): ?>
                <?php
                // Calculate percentages
                $percentage = round(($grade['points'] / $grade['max_points']) * 100, 1);
                $classAvgPercentage = 0;
                
                if (isset($averagesByItemId[$grade['item_id']])) {
                    $avg = $averagesByItemId[$grade['item_id']];
                    $classAvgPercentage = round(($avg['avg_points'] / $grade['max_points']) * 100, 1);
                }
                ?>
                
                <?php /* [GRADE ITEM PLACEHOLDER] - <?= $grade['item_name'] ?> - <?= $grade['points'] ?>/<?= $grade['max_points'] ?> - <?= $percentage ?>% - Class Avg: <?= $classAvgPercentage ?>% */ ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
