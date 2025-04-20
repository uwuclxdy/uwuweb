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

// Add CSS link for this specific page
echo '<link rel="stylesheet" href="/uwuweb/assets/css/parent-grades.css">';

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

<div class="page-container">
    <h1 class="page-title">Student Grades</h1>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filter Options</h3>
        </div>
        <div class="card-body">
            <form method="get" action="/uwuweb/parent/grades.php" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="student_id" class="form-label">Student:</label>
                        <select id="student_id" name="student_id" class="form-input" onchange="this.form.submit()">
                            <?php foreach ($students as $student): ?>
                                <option value="<?= (int)$student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                    (<?= htmlspecialchars($student['class_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="term_id" class="form-label">Term:</label>
                        <select id="term_id" name="term_id" class="form-input" onchange="this.form.submit()">
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
    </div>

    <?php if ($selectedStudent): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?>
                    <span class="badge"><?= htmlspecialchars($selectedStudent['class_code']) ?></span>
                </h2>
            </div>
        </div>

        <?php if (empty($gradeStats)): ?>
            <div class="card">
                <div class="card-body">
                    <p class="text-secondary">No grades found for the selected student and term.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="grades-summary">
                <?php foreach ($gradeStats as $subjectId => $subject): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?= htmlspecialchars($subject['subject_name']) ?></h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($subject['classes'] as $classId => $class): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h4 class="card-title"><?= htmlspecialchars($class['class_title']) ?></h4>
                                    <div class="class-average">
                                        Average: <span class="badge badge-primary"><?= $class['average'] ?>%</span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-wrapper">
                                        <table class="table">
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

                                                    // Determine grade badge class based on percentage
                                                    $gradeBadge = '';
                                                    if ($percentage >= 90) {
                                                        $gradeBadge = 'badge badge-success';
                                                    }
                                                    else if ($percentage >= 80) {
                                                        $gradeBadge = 'badge badge-primary';
                                                    }
                                                    else if ($percentage >= 70) {
                                                        $gradeBadge = 'badge badge-secondary';
                                                    }
                                                    else if ($percentage >= 60) {
                                                        $gradeBadge = 'badge badge-warning';
                                                    }
                                                    else {
                                                        $gradeBadge = 'badge badge-error';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($grade['item_name']) ?></td>
                                                        <td><?= $grade['weight'] ?>x</td>
                                                        <td><?= $grade['points'] ?>/<?= $grade['max_points'] ?></td>
                                                        <td><span class="<?= $gradeBadge ?>"><?= $percentage ?>%</span></td>
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
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include page footer
include '../includes/footer.php';
?>
