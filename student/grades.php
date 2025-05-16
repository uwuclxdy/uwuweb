<?php
/**
 * Student Grades View
 * /uwuweb/student/grades.php
 *
 * Allows students to view their own grades and class averages
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'student_functions.php';
require_once '../includes/header.php';

// Verify student role and get student ID
requireRole(3); // ROLE_STUDENT = 3
$studentId = getStudentId();
if (!$studentId) {
    echo generateAlert('Študentski račun ni najden.', 'error');
    include '../includes/footer.php';
    exit;
}

$pdo = safeGetDBConnection('student/grades.php');

// Get student's classes
$studentClasses = getStudentClasses($studentId);
if (empty($studentClasses)) {
    echo generateAlert('Niste vpisani v noben razred.');
    include '../includes/footer.php';
    exit;
}

// Debug output to understand the structure
// echo '<pre>'; print_r($studentClasses); echo '</pre>';

// Extract unique classes for the dropdown
$uniqueClasses = [];
foreach ($studentClasses as $class) if (isset($class['class_id'])) {
    $classId = $class['class_id'];
    if (!isset($uniqueClasses[$classId])) $uniqueClasses[$classId] = [
        'class_id' => $classId,
        'class_code' => $class['class_code'] ?? '',
        'title' => $class['title'] ?? ($class['class_title'] ?? 'Razred')
    ];
}

// Get selected class ID from GET parameter or use the first one
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$selectedClass = null;

if ($selectedClassId) foreach ($uniqueClasses as $class) if ($class['class_id'] == $selectedClassId) {
    $selectedClass = $class;
    break;
}

// If no class selected or invalid selection, default to first
if (!$selectedClass && !empty($uniqueClasses)) {
    $selectedClass = reset($uniqueClasses);
    $selectedClassId = $selectedClass['class_id'];
}

// Get all subjects for the selected class
$classSubjects = [];
foreach ($studentClasses as $class):
    if (isset($class['class_id']) && $class['class_id'] == $selectedClassId):
        // Handle two possible data structures
        if (isset($class['subjects']) && is_array($class['subjects'])):
            foreach ($class['subjects'] as $subject):
                if (isset($subject['class_subject_id'])):
                    $classSubjects[] = [
                        'subject_id' => $subject['subject_id'] ?? null,
                        'subject_name' => $subject['subject_name'] ?? 'Predmet',
                        'class_subject_id' => $subject['class_subject_id']
                    ];
                endif;
            endforeach;
        elseif (isset($class['subject_id'], $class['class_subject_id'])):
            // Alternative structure where subjects are directly embedded
            $classSubjects[] = [
                'subject_id' => $class['subject_id'],
                'subject_name' => $class['subject_name'] ?? 'Predmet',
                'class_subject_id' => $class['class_subject_id']
            ];
        endif;
    endif;
endforeach;

// Get enrollment ID for this class
$stmt = $pdo->prepare("SELECT enroll_id FROM enrollments WHERE student_id = ? AND class_id = ?");
$stmt->execute([$studentId, $selectedClassId]);
$enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
$enrollId = $enrollment['enroll_id'] ?? null;

// Get grade data for each subject
$subjectGrades = [];
$subjectAverages = [];
$overallAverage = 0;
$totalGradeItems = 0;
$totalPercentage = 0;

foreach ($classSubjects as $subject) {
    $classSubjectId = $subject['class_subject_id'];

    // Get grade items for this subject
    $gradeItems = getGradeItems($classSubjectId);

    // Get grades data for the class
    $gradesData = getClassGrades($classSubjectId);

    // Extract student's grades
    $grades = [];
    $classAverages = [];

    if (!empty($gradesData) && $enrollId) {
        if (isset($gradesData['grades'][$enrollId])) foreach ($gradesData['grades'][$enrollId] as $itemId => $grade) $grades[$itemId] = $grade;

        // Calculate class averages for each grade item
        foreach ($gradeItems as $item) {
            // Get all grades for this item
            $itemGrades = [];
            foreach ($gradesData['grades'] as $studentGrades) if (isset($studentGrades[$item['item_id']])) $itemGrades[] = ($studentGrades[$item['item_id']]['points'] / $item['max_points']) * 100;

            // Calculate average
            if (!empty($itemGrades)) $classAverages[$item['item_id']] = array_sum($itemGrades) / count($itemGrades); else $classAverages[$item['item_id']] = 0;
        }
    }

    // Calculate student's average for this subject
    $subjectPercentage = 0;
    $subjectGradeCount = 0;

    foreach ($grades as $itemId => $grade) foreach ($gradeItems as $item) if ($item['item_id'] == $itemId) {
        $percentage = ($grade['points'] / $item['max_points']) * 100;
        $subjectPercentage += $percentage;
        $subjectGradeCount++;
        $totalPercentage += $percentage;
        $totalGradeItems++;
        break;
    }

    $subjectAverage = $subjectGradeCount > 0 ? ($subjectPercentage / $subjectGradeCount) : 0;

    $subjectGrades[$classSubjectId] = [
        'subject_name' => $subject['subject_name'],
        'grade_items' => $gradeItems,
        'grades' => $grades,
        'class_averages' => $classAverages,
        'subject_average' => $subjectAverage
    ];

    $subjectAverages[$classSubjectId] = $subjectAverage;
}

// Calculate overall average across all subjects
$overallAverage = $totalGradeItems > 0 ? ($totalPercentage / $totalGradeItems) : 0;

// Render header title
renderHeaderCard(
    'Moje Ocene',
    'Pregled ocen in povprečij',
    'student'
);
?>

<div class="section">
    <!-- Class Selector -->
    <div class="card mb-md">
        <div class="card__content">
            <form method="GET" action="grades.php" class="d-flex justify-between items-center">
                <div class="form-group mb-0">
                    <label for="class_selector" class="form-label">Izberite razred:</label>
                    <select id="class_selector" name="class_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($uniqueClasses as $class): ?>
                            <option value="<?= $class['class_id'] ?>"
                                <?= ($selectedClassId == $class['class_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="button" class="btn btn-secondary btn-sm" id="printGrades" title="Natisni ocene">
                    <span>🖨️ Natisni</span>
                </button>
            </form>
        </div>
    </div>

    <?php if ($selectedClass): ?>
        <!-- Overall Average Card -->
        <div class="card mb-md">
            <div class="card__title">
                Skupno povprečje: <?= htmlspecialchars($selectedClass['title']) ?>
            </div>
            <div class="card__content">
                <div class="d-flex justify-center items-center p-lg">
                    <?php
                    $avgClass = '';
                    if ($overallAverage >= 90) $avgClass = 'grade-5';
                    elseif ($overallAverage >= 75) $avgClass = 'grade-4';
                    elseif ($overallAverage >= 61) $avgClass = 'grade-3';
                    elseif ($overallAverage >= 50) $avgClass = 'grade-2';
                    else $avgClass = 'grade-1';
                    ?>

                    <div class="grade <?= $avgClass ?>"
                         style="font-size: 1.5em; padding: 15px 25px; font-weight: bold;">
                        <?= number_format($overallAverage, 1) ?>%
                    </div>
                </div>

                <!-- Grade Legend -->
                <div class="d-flex gap-md justify-center flex-wrap">
                    <div class="d-flex items-center gap-xs">
                        <div class="grade grade-5" style="width: 30px; text-align: center;">5</div>
                        <span>Odlično (≥90%)</span>
                    </div>
                    <div class="d-flex items-center gap-xs">
                        <div class="grade grade-4" style="width: 30px; text-align: center;">4</div>
                        <span>Prav dobro (75-89%)</span>
                    </div>
                    <div class="d-flex items-center gap-xs">
                        <div class="grade grade-3" style="width: 30px; text-align: center;">3</div>
                        <span>Dobro (61-74%)</span>
                    </div>
                    <div class="d-flex items-center gap-xs">
                        <div class="grade grade-2" style="width: 30px; text-align: center;">2</div>
                        <span>Zadostno (50-60%)</span>
                    </div>
                    <div class="d-flex items-center gap-xs">
                        <div class="grade grade-1" style="width: 30px; text-align: center;">1</div>
                        <span>Nezadostno (<50%)</span>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($subjectGrades as $classSubjectId => $subjectData): ?>
            <!-- Subject Grade Table -->
            <div class="card mb-md">
                <div class="card__title">
                    <?= htmlspecialchars($subjectData['subject_name']) ?>

                    <?php
                    $subjectAvg = $subjectData['subject_average'];
                    $subjectAvgClass = '';
                    if ($subjectAvg >= 90) $subjectAvgClass = 'grade-5';
                    elseif ($subjectAvg >= 75) $subjectAvgClass = 'grade-4';
                    elseif ($subjectAvg >= 61) $subjectAvgClass = 'grade-3';
                    elseif ($subjectAvg >= 50) $subjectAvgClass = 'grade-2';
                    else $subjectAvgClass = $subjectAvg > 0 ? 'grade-1' : '';
                    ?>

                    <?php if ($subjectAvg > 0): ?>
                        <span class="ml-sm">
                            <span class="font-bold <?= $subjectAvgClass ?>"
                                  style="font-size: 0.9em; padding: 3px 6px; border-radius: 6px; text-decoration: underline; text-decoration-thickness: 2px;">
                                <?= number_format($subjectAvg, 1) ?>%
                            </span>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="card__content">
                    <?php if (empty($subjectData['grade_items'])): ?>
                        <div class="alert status-info">
                            <div class="alert-content">Za ta predmet še ni preverjanj znanja.</div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Preverjanje znanja</th>
                                    <th>Datum</th>
                                    <th>Maksimalno točk</th>
                                    <th>Moje točke</th>
                                    <th>Ocena (%)</th>
                                    <th>Povprečje razreda</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($subjectData['grade_items'] as $item): ?>
                                    <?php
                                    // Find grade for this item
                                    $grade = $subjectData['grades'][$item['item_id']] ?? null;

                                    // Calculate percentage and determine grade class
                                    $percentage = 0;
                                    $gradeClass = '';

                                    if ($grade) {
                                        $percentage = ($grade['points'] / $item['max_points']) * 100;

                                        if ($percentage >= 90) $gradeClass = 'grade-5';
                                        elseif ($percentage >= 75) $gradeClass = 'grade-4';
                                        elseif ($percentage >= 61) $gradeClass = 'grade-3';
                                        elseif ($percentage >= 50) $gradeClass = 'grade-2';
                                        else $gradeClass = 'grade-1';
                                    }

                                    // Get class average for this item
                                    $classAvg = $subjectData['class_averages'][$item['item_id']] ?? 0;
                                    $classAvgClass = '';

                                    if ($classAvg >= 90) $classAvgClass = 'grade-5';
                                    elseif ($classAvg >= 75) $classAvgClass = 'grade-4';
                                    elseif ($classAvg >= 61) $classAvgClass = 'grade-3';
                                    elseif ($classAvg >= 50) $classAvgClass = 'grade-2';
                                    else $classAvgClass = 'grade-1';
                                    ?>
                                    <tr>
                                        <td class="font-medium"><?= htmlspecialchars($item['name']) ?></td>
                                        <td><?= !empty($item['date']) ? htmlspecialchars(formatDateDisplay($item['date'])) : '/' ?></td>
                                        <td><?= $item['max_points'] ?></td>
                                        <td><?= $grade ? $grade['points'] : '/' ?></td>
                                        <td>
                                            <?php if ($grade): ?>
                                                <div class="grade <?= $gradeClass ?>"
                                                     data-open-modal="gradeDetailsModal"
                                                     data-item-id="<?= $item['item_id'] ?>"
                                                     data-item-name="<?= htmlspecialchars($item['name']) ?>"
                                                     data-points="<?= $grade['points'] ?>"
                                                     data-max-points="<?= $item['max_points'] ?>"
                                                     data-percentage="<?= number_format($percentage, 1) ?>"
                                                     data-comment="<?= htmlspecialchars($grade['comment'] ?? '') ?>">
                                                    <?= number_format($percentage, 1) ?>%
                                                    <?php if (!empty($grade['comment'])): ?>
                                                        <span class="grade-comment-indicator" title="Komentar">*</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-disabled">/</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($classAvg > 0): ?>
                                                <div class="font-bold <?= $classAvgClass ?>"
                                                     style="font-size: 100%; padding: 3px 6px; border-radius: 6px; text-decoration: underline; text-decoration-thickness: 2px; display: inline-flex;">
                                                    <?= number_format($classAvg, 1) ?>%
                                                </div>
                                            <?php else: ?>
                                                <span class="text-disabled">/</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($classSubjects)): ?>
            <div class="alert status-warning">
                <div class="alert-content">Za ta razred ni najdenih predmetov.</div>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="alert status-warning">
            <div class="alert-content">Izberite razred za prikaz ocen.</div>
        </div>
    <?php endif; ?>
</div>

<!-- Grade Details Modal -->
<div class="modal" id="gradeDetailsModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="gradeDetailsTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="gradeDetailsTitle">Podrobnosti ocene</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-info mb-lg">
                <div class="alert-content">
                    <p>
                        <strong>
                            <span id="details_item_name" class="modal-title" style="font-weight: 800"></span>
                        </strong>
                    </p>
                </div>
            </div>

            <div class="d-flex justify-between mb-md">
                <div>
                    <p><strong>Točke:</strong> <span id="details_points"></span>/<span id="details_max_points"></span>
                    </p>
                </div>
                <div>
                    <p><strong>Ocena:</strong> <span id="details_percentage" class="font-bold"></span></p>
                </div>
            </div>

            <div id="details_comment_container" class="mt-lg" style="display: none;">
                <h4 class="mb-sm">Komentar učitelja:</h4>
                <div id="details_comment" class="p-md bg-secondary rounded"
                     style="border-left: 3px solid var(--accent-primary);"></div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="d-flex justify-between w-full">
                <button type="button" class="btn btn-primary" data-close-modal>Zapri</button>
            </div>
        </div>
    </div>
</div>

<style>
    .grade {
        transition: all 0.15s ease-out;
        position: relative;
        overflow: visible;
        cursor: pointer;
        padding: 6px 8px;
        border-radius: 4px;
        text-align: center;
        align-items: center;
        justify-content: center;
        display: inline-flex;
    }

    .grade:hover {
        transform: scale(1.02);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        z-index: 2;
    }

    .grade::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: var(--accent-primary);
        transform: scaleX(0);
        transition: transform 0.15s ease-out;
    }

    .grade:hover::after {
        transform: scaleX(1);
    }

    .grade-comment-indicator {
        font-size: 1.2em;
        margin-left: 3px;
        display: inline-block;
        vertical-align: super;
    }

    /* Ensure all table cells are centered */
    .data-table td, .data-table th {
        text-align: center;
        vertical-align: middle;
    }

    /* First column should be left aligned */
    .data-table td:first-child, .data-table th:first-child {
        text-align: left;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- Modal Management Functions ---
        const openModal = (modalId) => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                // Focus the first focusable element
                const firstFocusable = modal.querySelector('button, [href], input, select, textarea');
                if (firstFocusable) firstFocusable.focus();
            }
        };

        const closeModal = (modal) => {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }

            if (modal) {
                modal.classList.remove('open');
            }
        };

        // Open modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;

                // Handle grade details modal
                if (modalId === 'gradeDetailsModal') {
                    const itemName = this.dataset.itemName;
                    const points = this.dataset.points;
                    const maxPoints = this.dataset.maxPoints;
                    const percentage = this.dataset.percentage;
                    const comment = this.dataset.comment;

                    // Fill in modal data
                    document.getElementById('details_item_name').textContent = itemName;
                    document.getElementById('details_points').textContent = points;
                    document.getElementById('details_max_points').textContent = maxPoints;
                    document.getElementById('details_percentage').textContent = percentage + '%';

                    // Handle comment display
                    const commentContainer = document.getElementById('details_comment_container');
                    const commentElement = document.getElementById('details_comment');

                    if (comment && comment.trim() !== '') {
                        commentElement.textContent = comment;
                        commentContainer.style.display = 'block';
                    } else {
                        commentContainer.style.display = 'none';
                    }
                }

                openModal(modalId);
            });
        });

        // Close modal buttons
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        // Close modals when clicking the overlay
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.open').forEach(modal => {
                    closeModal(modal);
                });
            }
        });

        // Print functionality
        document.getElementById('printGrades').addEventListener('click', function () {
            window.print();
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
