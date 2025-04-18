<?php
/**
 * Parent Grades View
 *
 * Allows parents to view grades and class averages for their linked students in read-only mode
 *
 * Functions:
 * - getParentStudents($parentId) - Gets list of students linked to a parent
 * - getParentId() - Gets the parent ID for the current user
 * - getStudentGrades($studentId, $termId) - Gets grades for a specific student
 * - getClassAverage($classId) - Gets class average for a specific class
 * - calculateGradeStatistics($grades) - Calculates grade statistics for visualization
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Ensure only parents can access this page
requireRole(ROLE_PARENT);

// Get the parent ID of the logged-in user
$parentId = getParentId();
if (!$parentId) {
    die('Error: Parent account not found.');
}

// Database connection
$pdo = safeGetDBConnection('parent/grades.php');

// Get students linked to this parent
function getParentStudents($parentId) {
    $pdo = safeGetDBConnection('getParentStudents() in parent/grades.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getParentStudents()");
        return [];
    }
    
    $stmt = $pdo->prepare(
        "SELECT 
            s.student_id, 
            s.first_name, 
            s.last_name,
            s.class_code
         FROM students s
         JOIN student_parent sp ON s.student_id = sp.student_id
         WHERE sp.parent_id = :parent_id
         ORDER BY s.last_name, s.first_name"
    );

    $stmt->execute(['parent_id' => $parentId]);
    return $stmt->fetchAll();
}

// Get current term
$currentTerm = getCurrentTerm();
$termId = $currentTerm ? $currentTerm['term_id'] : null;

// Get student grades for the current term
function getStudentGrades($studentId, $termId = null) {
    $pdo = safeGetDBConnection('getStudentGrades() in parent/grades.php', false);
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
    $pdo = safeGetDBConnection('getClassAverage() in parent/grades.php', false);
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
         ORDER BY gi.name ASC"
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
    $pdo = safeGetDBConnection('getAvailableTerms() in parent/grades.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getAvailableTerms()");
        return [];
    }
    
    $stmt = $pdo->query(
        "SELECT DISTINCT t.term_id, t.name, t.start_date, t.end_date
         FROM terms t
         JOIN classes c ON t.term_id = c.term_id
         ORDER BY t.start_date DESC"
    );
    return $stmt->fetchAll();
}

// Process student and term filter
$selectedStudentId = null;
$selectedTermId = null;

$students = getParentStudents($parentId);

if (empty($students)) {
    header("Location: ../dashboard.php?error=no_students");
    exit;
}

if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
    $selectedStudentId = (int)$_GET['student_id'];

    // Verify the selected student belongs to this parent
    $validStudent = false;
    foreach ($students as $student) {
        if ($student['student_id'] == $selectedStudentId) {
            $validStudent = true;
            break;
        }
    }

    if (!$validStudent) {
        $selectedStudentId = $students[0]['student_id'];
    }
} else {
    $selectedStudentId = $students[0]['student_id'];
}

if (isset($_GET['term_id']) && is_numeric($_GET['term_id'])) {
    $selectedTermId = (int)$_GET['term_id'];
} else {
    $selectedTermId = $termId;
}

// Get grades data for the selected student
$grades = getStudentGrades($selectedStudentId, $selectedTermId);
$gradeStats = calculateGradeStatistics($grades);
$availableTerms = getAvailableTerms();

// Get selected student info
$selectedStudent = null;
foreach ($students as $student) {
    if ($student['student_id'] == $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

// Include page header
include '../includes/header.php';
?>

<div class="grades-container">
    <h1>Student Grades</h1>

    <div class="grades-filter">
        <form method="get" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="student_id">Student:</label>
                    <select id="student_id" name="student_id" onchange="this.form.submit()">
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int)$student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                (<?= htmlspecialchars($student['class_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="term_id">Term:</label>
                    <select id="term_id" name="term_id" onchange="this.form.submit()">
                        <?php foreach ($availableTerms as $term): ?>
                            <option value="<?= (int)$term['term_id'] ?>" <?= $selectedTermId == $term['term_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($term['name']) ?>
                                (<?= date('d.m.Y', strtotime($term['start_date'])) ?> -
                                 <?= date('d.m.Y', strtotime($term['end_date'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($selectedStudent): ?>
        <div class="student-info">
            <h2>
                <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?>
                <span class="class-code">(<?= htmlspecialchars($selectedStudent['class_code']) ?>)</span>
            </h2>
        </div>

        <?php if (empty($gradeStats)): ?>
            <div class="grades-empty">
                <p class="empty-message">No grades found for the selected student and term.</p>
            </div>
        <?php else: ?>
            <div class="grades-summary">
                <?php foreach ($gradeStats as $subjectId => $subject): ?>
                    <div class="subject-container">
                        <h3><?= htmlspecialchars($subject['subject_name']) ?></h3>

                        <?php foreach ($subject['classes'] as $classId => $class): ?>
                            <div class="class-container">
                                <div class="class-header">
                                    <h4><?= htmlspecialchars($class['class_title']) ?></h4>
                                    <div class="class-average">
                                        Average: <span class="average-value"><?= $class['average'] ?>%</span>
                                    </div>
                                </div>

                                <div class="grades-table-container">
                                    <table class="grades-table">
                                        <thead>
                                            <tr>
                                                <th>Assessment</th>
                                                <th>Weight</th>
                                                <th>Score</th>
                                                <th>Percentage</th>
                                                <th>Class Average</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $classAverage = getClassAverage($classId);
                                            $averagesByItemId = [];
                                            foreach ($classAverage as $avg) {
                                                $averagesByItemId[$avg['item_id']] = $avg;
                                            }

                                            foreach ($class['grades'] as $grade):
                                                $percentage = round(($grade['points'] / $grade['max_points']) * 100, 1);
                                                $classAvgPercentage = 0;

                                                if (isset($averagesByItemId[$grade['item_id']])) {
                                                    $avg = $averagesByItemId[$grade['item_id']];
                                                    $classAvgPercentage = round(($avg['avg_points'] / $grade['max_points']) * 100, 1);
                                                }

                                                // Determine grade color based on percentage
                                                $gradeColor = '';
                                                if ($percentage >= 90) {
                                                    $gradeColor = 'grade-a';
                                                }
                                                else if ($percentage >= 80) {
                                                    $gradeColor = 'grade-b';
                                                }
                                                else if ($percentage >= 70) {
                                                    $gradeColor = 'grade-c';
                                                }
                                                else if ($percentage >= 60) {
                                                    $gradeColor = 'grade-d';
                                                }
                                                else {
                                                    $gradeColor = 'grade-f';
                                                }
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($grade['item_name']) ?></td>
                                                    <td><?= $grade['weight'] ?>x</td>
                                                    <td><?= $grade['points'] ?>/<?= $grade['max_points'] ?></td>
                                                    <td class="<?= $gradeColor ?>"><?= $percentage ?>%</td>
                                                    <td><?= $classAvgPercentage ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if (!empty($class['grades'][0]['comment'])): ?>
                                <div class="comment-section">
                                    <h5>Teacher Comments:</h5>
                                    <div class="comment-text">
                                        <?= nl2br(htmlspecialchars($class['grades'][0]['comment'])) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    /* Grades page specific styles */
    .grades-container {
        padding: 1rem 0;
    }

    .grades-filter {
        margin-bottom: 2rem;
    }

    .filter-form {
        max-width: 600px;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .form-group {
        flex: 1;
        min-width: 250px;
    }

    .student-info {
        margin-bottom: 1.5rem;
    }

    .class-code {
        font-weight: normal;
        color: #666;
    }

    .grades-empty {
        padding: 2rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        text-align: center;
    }

    .empty-message {
        color: #666;
        font-style: italic;
    }

    .subject-container {
        margin-bottom: 2rem;
    }

    .class-container {
        margin: 1rem 0 2rem;
        padding: 1rem;
        background-color: #f9f9f9;
        border-radius: 4px;
    }

    .class-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .class-header h4 {
        margin: 0;
    }

    .class-average {
        font-weight: 500;
    }

    .average-value {
        font-weight: bold;
    }

    .grades-table-container {
        overflow-x: auto;
    }

    .grades-table {
        width: 100%;
        border-collapse: collapse;
    }

    .grades-table th, .grades-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .grades-table th {
        background-color: #f5f5f5;
    }

    .grade-a {
        color: #28a745;
        font-weight: bold;
    }

    .grade-b {
        color: #5cb85c;
        font-weight: bold;
    }

    .grade-c {
        color: #f0ad4e;
        font-weight: bold;
    }

    .grade-d {
        color: #d9534f;
        font-weight: bold;
    }

    .grade-f {
        color: #dc3545;
        font-weight: bold;
    }

    .comment-section {
        margin-top: 1rem;
        padding: 1rem;
        background-color: #f0f0f0;
        border-radius: 4px;
    }

    .comment-section h5 {
        margin-top: 0;
        margin-bottom: 0.5rem;
    }

    .comment-text {
        white-space: pre-line;
    }
</style>

<?php
// Include page footer
include '../includes/footer.php';
?>
