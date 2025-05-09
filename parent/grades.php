<?php
/**
 * Parent Grades View
 *
 * Allows parents to view grade records for their linked students in read-only mode
 *
 * Functions:
 * - getParentStudents($parentId) - Gets list of students linked to a parent
 * - getParentId() - Gets the parent ID for the current user
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
if (!$parentId) die('Napaka: Starševski račun ni najden.');

// Get students linked to the parent
$students = getParentStudents($parentId);

// Selected student for viewing grades
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($students[0]['student_id'] ?? 0);

// Get classes and selected class if student is selected
$classes = $selectedStudentId ? getStudentClasses($selectedStudentId) : [];
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_id'] ?? 0);

// Get grades if class is selected
$grades = ($selectedStudentId && $selectedClassId) ? getClassGradesTeacher($selectedStudentId, $selectedClassId) : [];

// Calculate class average
$classAverage = calculateClassAverage($grades);
$gradeLetter = getGradeLetter($classAverage);

// Find the selected student's name
$selectedStudent = null;
foreach ($students as $student) if ($student['student_id'] == $selectedStudentId) {
    $selectedStudent = $student;
    break;
}
?>

<!-- Page header card with title and role indicator -->
<div class="card shadow mb-lg mt-lg page-transition">
    <div class="d-flex justify-between items-center">
        <div>
            <h2 class="mt-0 mb-xs">Ocene učenca</h2>
            <p class="text-secondary mt-0 mb-0">Pregled uspešnosti vašega otroka</p>
        </div>
        <div class="role-badge role-parent">Starš</div>
    </div>
</div>

<!-- Student selection form with dropdown -->
<div class="card shadow mb-lg">
    <div class="card__content">
        <form method="GET" action="/uwuweb/parent/grades.php" class="d-flex items-center gap-md flex-wrap">
            <div class="form-group mb-0" style="flex: 1;">
                <label for="student_id" class="form-label">Izberite otroka:</label>
                <select id="student_id" name="student_id" class="form-input form-select">
                    <option value="">-- Izberite otroka --</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Izberi</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedStudentId && $selectedStudent): ?>
    <!-- Class selection form -->
    <div class="card shadow mb-lg">
        <div class="card__content">
            <form method="GET" action="/uwuweb/parent/grades.php" class="d-flex items-center gap-md flex-wrap">
                <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">

                <div class="form-group mb-0" style="flex: 1;">
                    <label for="class_id" class="form-label">Izberite predmet:</label>
                    <select id="class_id" name="class_id" class="form-input form-select">
                        <option value="">-- Izberite predmet --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>" <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['subject_name']) ?>
                                - <?= htmlspecialchars($class['teacher_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-0" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Pokaži ocene</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedClassId && !empty($grades)): ?>
        <!-- Class grade details -->
        <div class="card shadow mb-lg">
            <div class="card__title d-flex justify-between items-center">
                <span>
                    <?= htmlspecialchars($classes[array_search($selectedClassId, array_column($classes, 'class_id'), true)]['subject_name'] ?? 'Predmet') ?>
                </span>
                <span class="grade <?= $classAverage >= 4 ? 'grade-high' : ($classAverage >= 2.5 ? 'grade-medium' : 'grade-low') ?>">
                    <?= number_format($classAverage, 1) ?>
                </span>
            </div>
            <div class="card__content">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Ime naloge</th>
                            <th>Ocena</th>
                            <th>Utež</th>
                            <th>Komentar učitelja</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($grades as $subject): ?>
                            <?php foreach ($subject['grade_items'] as $item): ?>
                                <?php if (isset($item['points'])): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td>
                                        <span class="grade <?= ($item['points'] / $item['max_points']) * 100 >= 80 ? 'grade-high' : (($item['points'] / $item['max_points']) * 100 >= 50 ? 'grade-medium' : 'grade-low') ?>">
                                            <?= number_format($item['points'], 1) ?> / <?= number_format($item['max_points'], 1) ?>
                                        </span>
                                        </td>
                                        <td><?= htmlspecialchars($item['weight']) ?></td>
                                        <td><?= htmlspecialchars($item['comment'] ?? '—') ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Grade distribution visualization -->
        <div class="card shadow mb-lg">
            <div class="card__title">Porazdelitev ocen</div>
            <div class="card__content">
                <?php
                // Create simple visualization of grade distribution
                $gradeGroups = [
                    1 => 0,
                    2 => 0,
                    3 => 0,
                    4 => 0,
                    5 => 0
                ];

                $totalItems = 0;
                foreach ($grades as $subject) foreach ($subject['grade_items'] as $item) if (isset($item['points'])) {
                    $totalItems++;
                    $percentage = ($item['points'] / $item['max_points']) * 100;
                    $grade = getGradeLetter($percentage);
                    $gradeGroups[$grade]++;
                }
                ?>

                <div class="d-flex flex-column gap-md">
                    <?php foreach ($gradeGroups as $grade => $count): ?>
                        <?php
                        $percentage = $totalItems > 0 ? ($count / $totalItems) * 100 : 0;
                        $barClass = $grade >= 4 ? 'grade-high' : ($grade >= 3 ? 'grade-medium' : 'grade-low');
                        ?>
                        <div>
                            <div class="d-flex justify-between mb-xs">
                                <span>Ocena <?= $grade ?></span>
                                <span><?= $count ?> nalog (<?= round($percentage) ?>%)</span>
                            </div>
                            <div class="progress-bar"
                                 style="background-color: var(--bg-tertiary); height: 8px; border-radius: 4px;">
                                <div class="<?= $barClass ?>"
                                     style="width: <?= $percentage ?>%; height: 100%; border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php elseif ($selectedClassId): ?>
        <div class="card shadow">
            <div class="card__content">
                <div class="alert status-info">
                    Za ta predmet še ni ocen. Prosimo, preverite kasneje.
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php elseif (!empty($students)): ?>
    <div class="card shadow">
        <div class="card__content text-center p-xl">
            <div class="alert status-info mb-lg">
                Prosimo, izberite učenca za ogled njegovih ocen.
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow">
        <div class="card__content text-center p-xl">
            <div class="alert status-warning mb-lg">
                Nimate nobenega otroka, povezanega z vašim računom.
            </div>
            <p class="text-secondary">Prosimo, obrnite se na šolskega administratorja, da poveže vaše otroke z vašim
                računom.</p>
        </div>
    </div>
<?php endif; ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
