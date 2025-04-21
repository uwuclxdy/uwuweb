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
         JOIN student_parent ps ON s.student_id = ps.student_id
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
        "SELECT g.grade_id, g.points, g.comment, gi.item_id, gi.name, 
            gi.max_points, gi.weight
            FROM grades g
            JOIN grade_items gi ON g.item_id = gi.item_id
            JOIN enrollments e ON g.enroll_id = e.enroll_id
            WHERE e.student_id = :student_id AND gi.class_id = :class_id
            ORDER BY gi.item_id"  // Changed from gi.date since date doesn't exist
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
        "SELECT g.grade_id, g.points, gi.item_id, gi.name, 
        gi.max_points, gi.weight
        FROM grades g
        JOIN grade_items gi ON g.item_id = gi.item_id
        JOIN enrollments e ON g.enroll_id = e.enroll_id
        WHERE e.student_id = :student_id AND gi.class_id = :class_id
        ORDER BY gi.name"
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

    <div class="card mb-lg">
        <h1 class="mt-0 mb-md">Grade Records</h1>
        <p class="text-secondary mt-0 mb-0">View your child's academic performance</p>
    </div>

<?php if (count($students) > 1): ?>
    <!-- Student Selection Form -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">Select Student</h2>

        <form method="GET" action="grades.php" class="mb-0">
            <div class="form-group mb-0">
                <select name="student_id" class="form-input" onchange="this.form.submit()">
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                            (<?= htmlspecialchars($student['class_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($selectedStudent): ?>
    <!-- Student Information Card -->
    <div class="card mb-lg">
        <div class="d-flex items-center gap-md">
            <div class="profile-student" style="width: 48px; height: 48px; border-radius: 50%; border: 3px solid; display: flex; align-items: center; justify-content: center; font-size: var(--font-size-lg); font-weight: var(--font-weight-bold);">
                <?= strtoupper(substr($selectedStudent['first_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="mt-0 mb-xs"><?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></h2>
                <p class="text-secondary mb-0">Class: <?= htmlspecialchars($selectedStudent['class_code']) ?></p>
            </div>
        </div>
    </div>

    <!-- Class Selection -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">Select Class</h2>

        <?php if (empty($classes)): ?>
            <div class="bg-tertiary p-md text-center rounded">
                <p class="text-secondary mb-0">No classes found for this student.</p>
            </div>
        <?php else: ?>
            <form method="GET" action="grades.php" class="mb-0">
                <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                <div class="form-group mb-0">
                    <select name="class_id" class="form-input" onchange="this.form.submit()">
                        <option value="">-- Select a class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>" <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['subject_name'] . ' - ' . $class['title']) ?>
                                (<?= htmlspecialchars($class['term_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($selectedClass && $selectedClassId): ?>
        <!-- Class Information and Grades -->
        <div class="card mb-lg">
            <div class="d-flex justify-between items-center mb-lg">
                <div>
                    <h2 class="mt-0 mb-xs"><?= htmlspecialchars($selectedClass['subject_name'] . ' - ' . $selectedClass['title']) ?></h2>
                    <p class="mb-xs"><strong>Term:</strong> <?= htmlspecialchars($selectedClass['term_name']) ?></p>
                    <p class="mb-0"><strong>Teacher:</strong> <?= htmlspecialchars($selectedClass['teacher_name']) ?></p>
                </div>
                <div class="card p-md text-center">
                    <h3 class="mt-0 mb-xs">Overall Grade</h3>
                    <?php if ($classAverage !== null): ?>
                        <div class="grade <?= $classAverage >= 90 ? 'grade-high' : ($classAverage >= 70 ? 'grade-medium' : 'grade-low') ?>" style="font-size: var(--font-size-xl);">
                            <?= number_format($classAverage, 1) ?>%
                        </div>
                        <div class="mt-xs" style="font-size: var(--font-size-lg); font-weight: var(--font-weight-bold);">
                            <?= $gradeLetter ?>
                        </div>
                    <?php else: ?>
                        <div class="text-disabled">No grades yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($grades)): ?>
                <div class="bg-tertiary p-lg text-center rounded">
                    <p class="text-secondary mb-0">No grades have been entered for this class yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Date</th>
                            <th>Score</th>
                            <th>Weight</th>
                            <th>Comments</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <?php
                            $percentage = ($grade['max_points'] > 0) ?
                                ($grade['points'] / $grade['max_points']) * 100 : 0;

                            $gradeClass = '';
                            if ($percentage >= 90) $gradeClass = 'grade-high';
                            else if ($percentage >= 70) $gradeClass = 'grade-medium';
                            else $gradeClass = 'grade-low';
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-column">
                                        <strong><?= htmlspecialchars($grade['name']) ?></strong>
                                        <?php if (!empty($grade['description'])): ?>
                                            <span class="text-secondary" style="font-size: var(--font-size-xs);">
                                                    <?= htmlspecialchars($grade['description']) ?>
                                                </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?= $grade['date'] ? date('d.m.Y', strtotime($grade['date'])) : 'N/A' ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span><?= number_format($grade['points'], 2) ?>/<?= number_format($grade['max_points'], 2) ?></span>
                                        <span class="grade <?= $gradeClass ?>" style="font-size: var(--font-size-xs);">
                                                <?= number_format($percentage, 1) ?>%
                                            </span>
                                    </div>
                                </td>
                                <td>
                                    <?= $grade['weight'] ? number_format($grade['weight'], 2) . 'x' : '1.00x' ?>
                                </td>
                                <td>
                                    <?= !empty($grade['feedback']) ? htmlspecialchars($grade['feedback']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Grade Summary Section -->
        <div class="card">
            <h2 class="mt-0 mb-md">Grade Summary</h2>

            <div class="d-flex flex-column flex-row@md gap-md">
                <div style="flex: 1;">
                    <h3 class="mt-0 mb-sm">Grade Calculation</h3>
                    <div class="bg-tertiary p-md rounded">
                        <p class="mb-sm">The overall grade is calculated using the following method:</p>
                        <ol class="mb-0">
                            <li>Each assignment is weighted according to its importance</li>
                            <li>A percentage score is calculated for each assignment</li>
                            <li>Weighted percentages are averaged to produce the final grade</li>
                            <li>The letter grade is assigned based on the final percentage</li>
                        </ol>
                    </div>
                </div>

                <div style="flex: 1;">
                    <h3 class="mt-0 mb-sm">Grade Distribution</h3>
                    <?php if (!empty($grades)): ?>
                        <div class="bg-tertiary p-md rounded" style="height: 200px;">
                            <div style="display: flex; height: 150px; align-items: flex-end; gap: 8px;">
                                <?php
                                $gradeRanges = [
                                    'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0
                                ];

                                foreach ($grades as $grade) {
                                    $percentage = ($grade['max_points'] > 0) ?
                                        ($grade['points'] / $grade['max_points']) * 100 : 0;

                                    if ($percentage >= 90) $gradeRanges['A']++;
                                    else if ($percentage >= 80) $gradeRanges['B']++;
                                    else if ($percentage >= 70) $gradeRanges['C']++;
                                    else if ($percentage >= 60) $gradeRanges['D']++;
                                    else $gradeRanges['F']++;
                                }

                                $maxCount = max($gradeRanges);
                                $maxCount = $maxCount > 0 ? $maxCount : 1;
                                ?>

                                <?php foreach ($gradeRanges as $letter => $count): ?>
                                    <?php
                                    $height = ($count / $maxCount) * 100;
                                    $height = max($height, 10); // Minimum bar height

                                    $color = '';
                                    switch ($letter) {
                                        case 'A': $color = '#00c853'; break;
                                        case 'B': $color = '#2196f3'; break;
                                        case 'C': $color = '#ffeb3b'; break;
                                        case 'D': $color = '#ff9800'; break;
                                        case 'F': $color = '#f44336'; break;
                                    }
                                    ?>
                                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                                        <div style="width: 100%; height: <?= $height ?>%; background-color: <?= $color ?>; border-radius: 4px;"></div>
                                        <div class="mt-xs"><?= $letter ?> (<?= $count ?>)</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-tertiary p-md text-center rounded">
                            <p class="text-secondary mb-0">No grade data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
