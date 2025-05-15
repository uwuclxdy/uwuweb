<?php
/**
 * Teacher Grade Book
 * /uwuweb/teacher/gradebook.php
 *
 * Provides interface for teachers to manage student grades
 * Supports viewing, adding, and editing grades for assigned classes
 */

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'teacher_functions.php';
require_once '../includes/header.php';

// Verify teacher role and get teacher ID
requireRole(2); // ROLE_TEACHER = 2
$teacherId = getTeacherId();
if (!$teacherId) {
    echo generateAlert('Račun učitelja ni najden.', 'error');
    include '../includes/footer.php';
    exit;
}

$pdo = safeGetDBConnection('teacher/gradebook.php');
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse('Napaka pri generiranju CSRF žetona: ' . $e->getMessage(), 500, 'csrf_token');
}

// Initialize variables with empty defaults to avoid undefined errors
$selectedClassSubjectId = null;
$selectedClassSubject = null;
$students = [];
$gradeItems = [];
$grades = [];
$classAverages = [];
$studentAverages = [];

// Get teacher's classes
$teacherClasses = getTeacherClasses($teacherId);
if (empty($teacherClasses)) {
    echo generateAlert('Nimate dodeljenih razredov.');
    include '../includes/footer.php';
    exit;
}

// Get selected class-subject from GET parameters
$selectedClassSubjectId = isset($_GET['class_subject_id']) ? (int)$_GET['class_subject_id'] : null;

// Find the selected class-subject if an ID was provided
if ($selectedClassSubjectId) $selectedClassSubject = findClassSubjectById($teacherClasses, $selectedClassSubjectId);

// If no class selected or invalid selection, default to first
if (!$selectedClassSubject && !empty($teacherClasses)) {
    $firstClass = $teacherClasses[0];

    if (!empty($firstClass['subjects'])) {
        // Nested structure
        $firstSubject = $firstClass['subjects'][0];
        $selectedClassSubject = [
            'class_id' => $firstClass['class_id'] ?? null,
            'class_code' => $firstClass['class_code'] ?? '',
            'class_title' => $firstClass['title'] ?? '',
            'subject_id' => $firstSubject['subject_id'] ?? null,
            'subject_name' => $firstSubject['subject_name'] ?? '',
            'class_subject_id' => $firstSubject['class_subject_id'] ?? null
        ];
        $selectedClassSubjectId = $firstSubject['class_subject_id'] ?? null;
    } elseif (isset($firstClass['class_subject_id'])) {
        // Direct structure
        $selectedClassSubject = [
            'class_id' => $firstClass['class_id'] ?? null,
            'class_code' => $firstClass['class_code'] ?? '',
            'class_title' => $firstClass['title'] ?? $firstClass['class_title'] ?? 'Razred',
            'subject_id' => $firstClass['subject_id'] ?? null,
            'subject_name' => $firstClass['subject_name'] ?? '',
            'class_subject_id' => $firstClass['class_subject_id']
        ];
        $selectedClassSubjectId = $firstClass['class_subject_id'];
    }
}

// Load data if we have a selected class
if (isset($selectedClassSubject['class_id'], $selectedClassSubject['class_subject_id']) && $selectedClassSubject) {
    // Get students in class
    $students = getClassStudents($selectedClassSubject['class_id']);

    // Get grade items
    $gradeItems = getGradeItems($selectedClassSubject['class_subject_id']);

    // Get grades data
    $gradesData = getClassGrades($selectedClassSubject['class_subject_id']);

    // Process grades data
    if (!empty($gradesData)) if (isset($gradesData[0]) && is_array($gradesData[0])) $grades = $gradesData; elseif (isset($gradesData['grades'])) {
        // Convert to flat array format
        $grades = [];
        foreach ($students as $student) {
            if (!isset($student['enroll_id'])) continue;

            foreach ($gradeItems as $item) if (isset($gradesData['grades'][$student['enroll_id']][$item['item_id']])) {
                $gradeInfo = $gradesData['grades'][$student['enroll_id']][$item['item_id']];
                $grades[] = [
                    'enroll_id' => $student['enroll_id'],
                    'item_id' => $item['item_id'],
                    'points' => $gradeInfo['points'],
                    'comment' => $gradeInfo['comment'] ?? null
                ];
            }
        }
    }

    foreach ($gradeItems as $item) {
        // Calculate class averages for this grade item
        $itemGrades = array_filter($grades, static function ($grade) use ($item) {
            return isset($grade['item_id']) && $grade['item_id'] == $item['item_id'];
        });

        if (!empty($itemGrades)) {
            $totalPercentages = 0;
            $totalGrades = 0;

            foreach ($itemGrades as $grade) if (isset($grade['points'])) {
                // Calculate percentage for each grade
                $percentage = ($grade['points'] / $item['max_points']) * 100;
                $totalPercentages += $percentage;
                $totalGrades++;
            }

            // Calculate average: sum of all percentages / number of grades
            $classAverages[$item['item_id']] = $totalGrades > 0 ? ($totalPercentages / $totalGrades) : 0;
        }
    }

    // Calculate per-student averages (moved outside the gradeItems loop)
    foreach ($students as $student) {
        if (!isset($student['enroll_id'])) continue;

        $studentGrades = array_filter($grades, static function ($grade) use ($student) {
            return isset($grade['enroll_id']) && $grade['enroll_id'] == $student['enroll_id'];
        });

        if (!empty($studentGrades)) {
            // Calculate percentages for each grade
            $totalPercentages = 0;
            $totalGrades = 0;

            foreach ($studentGrades as $grade) if (isset($grade['item_id'], $grade['points'])) foreach ($gradeItems as $item) if ($item['item_id'] == $grade['item_id']) {
                // Calculate percentage and add to total
                $percentage = ($grade['points'] / $item['max_points']) * 100;
                $totalPercentages += $percentage;
                $totalGrades++;
                break;
            }

            // Calculate average percentage
            $studentAverages[$student['enroll_id']] = $totalGrades > 0 ? ($totalPercentages / $totalGrades) : 0;
        } else $studentAverages[$student['enroll_id']] = 0;
    }
}

// Now process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && verifyCSRFToken($_POST['csrf_token'])) {
    // Add Grade Item
    if (isset($_POST['add_grade_item'], $_POST['class_subject_id'])) {
        $classSubjectId = (int)$_POST['class_subject_id'];

        // Verify access
        if (teacherHasAccessToClassSubject($classSubjectId, $teacherId)) {
            $name = $_POST['name'] ?? '';
            $maxPoints = (float)($_POST['max_points'] ?? 0);
            $date = $_POST['test_date'] ?? '';

            if (!empty($name) && $maxPoints > 0) if (!empty($date) && !validateDate($date)) echo generateAlert('Neveljavni datum.', 'error'); else {
                $result = addGradeItem($classSubjectId, $name, $maxPoints, $date);
                if ($result) {
                    // Redirect to the same page to refresh data
                    header("Location: gradebook.php?class_subject_id=$classSubjectId&success=add_item");
                    exit;
                } else echo generateAlert('Napaka pri dodajanju elementa ocenjevanja.', 'error');
            } else echo generateAlert('Vsi podatki morajo biti izpolnjeni.', 'error');
        } else echo generateAlert('Nimate dostopa do tega predmeta.', 'error');
    }

    // Edit Grade Item
    if (isset($_POST['edit_grade_item'], $_POST['item_id'], $_POST['class_subject_id'])) {
        $itemId = (int)$_POST['item_id'];
        $classSubjectId = (int)$_POST['class_subject_id'];

        // Verify access
        if (teacherHasAccessToClassSubject($classSubjectId, $teacherId)) {
            $name = $_POST['name'] ?? '';
            $maxPoints = (float)($_POST['max_points'] ?? 0);
            $date = $_POST['test_date'] ?? '';

            if (!empty($name) && $maxPoints > 0) if (!empty($date) && !validateDate($date)) echo generateAlert('Neveljavni datum.', 'error'); else {
                $result = updateGradeItem($itemId, $name, $maxPoints, $date);
                if ($result) {
                    header("Location: gradebook.php?class_subject_id=$classSubjectId&success=update_item");
                    exit;
                } else echo generateAlert('Napaka pri posodabljanju elementa ocenjevanja.', 'error');
            } else echo generateAlert('Vsi podatki morajo biti izpolnjeni.', 'error');
        } else echo generateAlert('Nimate dostopa do tega predmeta.', 'error');
    }

    // Delete Grade Item
    if (isset($_POST['delete_grade_item'], $_POST['item_id'], $_POST['class_subject_id'])) {
        $itemId = (int)$_POST['item_id'];
        $classSubjectId = (int)$_POST['class_subject_id'];

        // The enroll_id is not needed for deleting a grade item, it's only needed for deleting a grade
        // Only pass the parameter if it exists in the POST data
        $enrollId = isset($_POST['enroll_id']) ? (int)$_POST['enroll_id'] : 0;

        // Verify access
        if (teacherHasAccessToClassSubject($classSubjectId, $teacherId)) {
            $result = deleteGradeItem($enrollId, $itemId);
            if ($result) {
                header("Location: gradebook.php?class_subject_id=$classSubjectId&success=delete_item");
                exit;
            } else echo generateAlert('Napaka pri brisanju elementa ocenjevanja.', 'error');
        } else echo generateAlert('Nimate dostopa do tega predmeta.', 'error');
    }

    // Save Grade
    if (isset($_POST['save_grade'], $_POST['enroll_id'], $_POST['item_id'])) {
        $enrollId = (int)$_POST['enroll_id'];
        $itemId = (int)$_POST['item_id'];
        $points = (float)($_POST['points'] ?? 0);
        $comment = $_POST['comment'] ?? null;

        // Find item for validation (if we have grade items loaded)
        $maxPoints = null;
        if (!empty($gradeItems)) foreach ($gradeItems as $item) if ($item['item_id'] == $itemId) {
            $maxPoints = $item['max_points'];
            break;
        }

        // Only validate if we found the item
        if ($maxPoints !== null && $points > $maxPoints) echo generateAlert('Število točk ne more presegati največjega števila točk (' . $maxPoints . ').', 'error'); else {
            $result = saveGrade($enrollId, $itemId, $points, $comment);
            if ($result) {
                // Redirect to refresh data
                $redirectParams = $selectedClassSubjectId ? "?class_subject_id=$selectedClassSubjectId&success=save_grade" : "?success=save_grade";
                header("Location: gradebook.php$redirectParams");
                exit;
            } else echo generateAlert('Napaka pri shranjevanju ocene.', 'error');
        }
    }

    // Delete Grade
    if (isset($_POST['delete_grade'], $_POST['enroll_id'], $_POST['item_id'])) {
        $enrollId = (int)$_POST['enroll_id'];
        $itemId = (int)$_POST['item_id'];

        // Add function call to delete grade (would need to be implemented in functions.php)
        $result = deleteGradeItem($enrollId, $itemId);
        if ($result) {
            $redirectParams = $selectedClassSubjectId ? "?class_subject_id=$selectedClassSubjectId&success=delete_grade" : "?success=delete_grade";
            header("Location: gradebook.php$redirectParams");
            exit;
        } else echo generateAlert('Napaka pri brisanju ocene.', 'error');
    }

    // Batch Grades
    if (isset($_POST['batch_grades'], $_POST['grades_data'], $_POST['class_subject_id'])) {
        $classSubjectId = (int)$_POST['class_subject_id'];

        // Verify access
        if (teacherHasAccessToClassSubject($classSubjectId, $teacherId)) try {
            $gradesData = json_decode($_POST['grades_data'], true, 512, JSON_THROW_ON_ERROR);

            $savedCount = 0;
            $errors = [];

            foreach ($gradesData as $gradeData) if (isset($gradeData['enrollId'], $gradeData['itemId'], $gradeData['points'])) {
                $enrollId = (int)$gradeData['enrollId'];
                $itemId = (int)$gradeData['itemId'];
                $points = (float)$gradeData['points'];
                $comment = $gradeData['comment'] ?? null;

                $result = saveGrade($enrollId, $itemId, $points, $comment);
                if ($result) $savedCount++; else $errors[] = "Napaka pri shranjevanju ocene za učenca $enrollId.";
            }

            if ($savedCount > 0) {
                $redirectParams = "?class_subject_id=$classSubjectId&success=batch_grades&count=$savedCount";
                header("Location: gradebook.php$redirectParams");
                exit;
            } else echo generateAlert('Ni uspelo shraniti nobene ocene.', 'error');
        } catch (JsonException $e) {
            sendJsonErrorResponse('Napaka pri obdelavi podatkov: ' . $e->getMessage(), 400, 'batch_grades');
        } else echo generateAlert('Nimate dostopa do tega predmeta.', 'error');
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') echo generateAlert('Neveljavna seja. Poskusite znova.', 'error');

// Show success messages based on redirects
if (isset($_GET['success'])) switch ($_GET['success']) {
    case 'add_item':
        echo generateAlert('Nov element ocenjevanja je bil uspešno dodan.', 'success');
        break;
    case 'update_item':
        echo generateAlert('Element ocenjevanja je bil uspešno posodobljen.', 'success');
        break;
    case 'delete_item':
        echo generateAlert('Element ocenjevanja je bil uspešno izbrisan.', 'success');
        break;
    case 'save_grade':
        echo generateAlert('Ocena je bila uspešno shranjena.', 'success');
        break;
    case 'delete_grade':
        echo generateAlert('Ocena je bila uspešno izbrisana.', 'success');
        break;
    case 'batch_grades':
        $count = (int)($_GET['count'] ?? 0);
        echo generateAlert("Uspešno shranjenih $count ocen.", 'success');
        break;
}

// Render header title
renderHeaderCard(
    'Redovalnica',
    'Pregled in urejanje ocen učencev',
    'teacher'
);
?>

<div class="section">
    <!-- Class/Subject Selector -->
    <div class="card mb-md">
        <div class="card__content">
            <form method="GET" action="gradebook.php" class="d-flex justify-between items-center">
                <div class="form-group mb-0">
                    <label for="class_subject_selector" class="form-label">Izberite razred in predmet:</label>
                    <select id="class_subject_selector" name="class_subject_id" class="form-select"
                            onchange="this.form.submit()">
                        <?php foreach ($teacherClasses as $class): ?>
                            <?php if (isset($class['subjects']) && is_array($class['subjects'])): ?>
                                <?php foreach ($class['subjects'] as $subject): ?>
                                    <?php if (isset($subject['class_subject_id'])): ?>
                                        <option value="<?= $subject['class_subject_id'] ?>"
                                            <?= ($selectedClassSubjectId == $subject['class_subject_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($class['title'] ?? 'Razred') ?>
                                            - <?= htmlspecialchars($subject['subject_name'] ?? 'Predmet') ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php elseif (isset($class['class_subject_id'], $class['subject_name'])): ?>
                                <!-- Alternative data structure where subjects are directly embedded -->
                                <option value="<?= $class['class_subject_id'] ?>"
                                    <?= ($selectedClassSubjectId == $class['class_subject_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['title'] ?? $class['class_title'] ?? 'Razred') ?> -
                                    <?= htmlspecialchars($class['subject_name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selectedClassSubject): ?>
                    <button type="button" class="btn btn-primary" data-open-modal="addGradeItemModal">
                        Dodaj preverjanje znanja
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($selectedClassSubject): ?>
        <!-- Grade Table -->
        <div class="card">
            <div class="card__title">
                Redovalnica: <?= htmlspecialchars($selectedClassSubject['class_title'] ?? 'Razred') ?> -
                <?= htmlspecialchars($selectedClassSubject['subject_name'] ?? 'Predmet') ?>
            </div>
            <div class="card__content">
                <?php if (empty($students)): ?>
                    <div class="alert status-info">
                        <div class="alert-icon">ℹ</div>
                        <div class="alert-content">V tem razredu ni učencev.</div>
                    </div>
                <?php elseif (empty($gradeItems)): ?>
                    <div class="alert status-info">
                        <div class="alert-icon">ℹ</div>
                        <div class="alert-content">Za ta predmet še ni preverjanj znanja. Kliknite gumb "Dodaj
                            preverjanje znanja" zgoraj.
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Grade Legend -->
                    <div class="mb-md">
                        <div class="d-flex gap-md mb-sm flex-wrap">
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
                        <div class="d-flex justify-end">
                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary btn-sm" id="printGradebook"
                                        title="Natisni redovalnico">
                                    <span>🖨️ Natisni</span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="exportGradebook"
                                        title="Izvozi v Excel">
                                    <span>📊 Izvozi</span>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm"
                                        data-open-modal="batchGradeModal" title="Vnesi ocene za vse učence">
                                    <span>📝 Množični vnos</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th class="text-left">Učenec</th>
                                <?php foreach ($gradeItems as $item): ?>
                                    <th>
                                        <div class="grade-item-header">
                                            <!-- Clickable name now doubles as the edit trigger -->
                                            <div class="grade-item-name editable-grade grade-item-edit"
                                                 title="Kliknite za urejanje"
                                                 data-open-modal="editGradeItemModal"
                                                 data-item-id="<?= $item['item_id'] ?>"
                                                 data-name="<?= htmlspecialchars($item['name']) ?>"
                                                 data-max-points="<?= $item['max_points'] ?>"
                                                 data-date="<?= $item['date'] ?? '' ?>"
                                                 data-avg-score="<?= isset($classAverages[$item['item_id']]) ? number_format($classAverages[$item['item_id']], 1) : '' ?>">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </div>

                                            <div class="text-secondary" style="font-size: 0.85em;">
                                                <?php if (!empty($item['date'])): ?>
                                                    <div class="mt-xs">
                                                        <?= htmlspecialchars(formatDateDisplay($item['date'])) ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div>(<?= $item['max_points'] ?> točk)</div>
                                            </div>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                                <th>Povprečje</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars(($student['last_name'] ?? '') . ' ' . ($student['first_name'] ?? '')) ?></td>

                                    <?php foreach ($gradeItems as $item): ?>
                                        <?php
                                        // Find grade for this student and item
                                        $grade = null;
                                        foreach ($grades as $g) if (isset($g['enroll_id'], $student['enroll_id'], $g['item_id']) && $g['enroll_id'] == $student['enroll_id'] && $g['item_id'] == $item['item_id']) {
                                            $grade = $g;
                                            break;
                                        }

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
                                        ?>

                                        <td>
                                            <?php if (isset($student['enroll_id'])): ?>
                                                <div class="grade <?= $gradeClass ?> editable-grade"
                                                     title="Kliknite za urejanje"
                                                     data-open-modal="editGradeModal"
                                                     data-enroll-id="<?= $student['enroll_id'] ?>"
                                                     data-item-id="<?= $item['item_id'] ?>"
                                                     data-max-points="<?= $item['max_points'] ?>"
                                                     data-points="<?= $grade ? $grade['points'] : '' ?>"
                                                     data-comment="<?= $grade ? htmlspecialchars($grade['comment'] ?? '') : '' ?>"
                                                     data-student-name="<?= htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?>"
                                                     data-item-name="<?= htmlspecialchars($item['name']) ?>">
                                                    <?php if ($grade): ?>
                                                        <?= number_format($percentage) ?>%
                                                        <?php if (!empty($grade['comment'])): ?>
                                                            <span class="grade-comment-indicator"
                                                                  title="Ima komentar">*</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="edit-icon" title="Dodaj oceno">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16"
                                                                     height="16" viewBox="0 0 24 24" fill="none"
                                                                     stroke="currentColor" stroke-width="2"
                                                                     stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
                                                                    <path d="m15 5 4 4"></path>
                                                                </svg>
                                                            </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-disabled">ID napaka</div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>

                                    <td class="font-bold">
                                        <?php if (isset($student['enroll_id'], $studentAverages[$student['enroll_id']])): ?>
                                            <?php
                                            $avgPercentage = $studentAverages[$student['enroll_id']];
                                            $gradeClass = '';
                                            if ($avgPercentage >= 90) $gradeClass = 'grade-5';
                                            elseif ($avgPercentage >= 75) $gradeClass = 'grade-4';
                                            elseif ($avgPercentage >= 61) $gradeClass = 'grade-3';
                                            elseif ($avgPercentage >= 50) $gradeClass = 'grade-2';
                                            else $gradeClass = 'grade-1';
                                            ?>
                                            <div class="grade <?= $gradeClass ?>">
                                                <?= number_format($avgPercentage, 1) ?> %
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Class Average Row -->
                            <?php if (!empty($classAverages)): ?>
                                <tr class="bg-secondary">
                                    <td class="font-bold">Povprečje</td>
                                    <?php foreach ($gradeItems as $item): ?>
                                        <td class="font-bold">
                                            <?php if (isset($classAverages[$item['item_id']])):
                                                $avg = $classAverages[$item['item_id']];
                                                $gradeClass = '';
                                                if ($avg >= 90) $gradeClass = 'grade-5';
                                                elseif ($avg >= 75) $gradeClass = 'grade-4';
                                                elseif ($avg >= 61) $gradeClass = 'grade-3';
                                                elseif ($avg >= 50) $gradeClass = 'grade-2';
                                                else $gradeClass = 'grade-1';
                                                ?>
                                                <div class="grade <?= $gradeClass ?>"
                                                     style="text-decoration: underline; text-underline-offset: 3px; font-weight: bold; font-size: 105%;">
                                                    <?= number_format($avg, 1) ?>%
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="font-bold">
                                        <?php
                                        // Calculate overall class average
                                        $overallAverage = 0;
                                        $validStudents = 0;

                                        foreach ($studentAverages as $avg) if ($avg > 0) {
                                            $overallAverage += $avg;
                                            $validStudents++;
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert status-warning">
            <div class="alert-icon">⚠</div>
            <div class="alert-content">Izberite razred in predmet za prikaz redovalnice.</div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Grade Item Modal -->
<div class="modal" id="addGradeItemModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="addGradeItemTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="addGradeItemTitle">Dodaj preverjanje znanja</h3>
        </div>
        <form id="addGradeItemForm" method="POST" action="gradebook.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="add_grade_item" value="1">
                <input type="hidden" name="class_subject_id" value="<?= $selectedClassSubjectId ?>">

                <div class="form-group">
                    <label class="form-label" for="grade_item_name">Naziv:</label>
                    <input type="text" id="grade_item_name" name="name" class="form-input" required>
                </div>

                <div class="row">
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="grade_item_max_points">Največje število točk:</label>
                            <input type="number" id="grade_item_max_points" name="max_points" class="form-input"
                                   required min="1" step="0.01" value="100">
                        </div>
                    </div>
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="grade_item_date">Datum:</label>
                            <input type="date" id="grade_item_date" name="test_date" class="form-input"
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-primary">Dodaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Grade Item Modal -->
<div class="modal" id="editGradeItemModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editGradeItemTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editGradeItemTitle">Uredi preverjanje znanja</h3>
        </div>
        <form id="editGradeItemForm" method="POST" action="gradebook.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="edit_grade_item" value="1">
                <input type="hidden" name="class_subject_id" value="<?= $selectedClassSubjectId ?>">
                <input type="hidden" id="edit_item_id" name="item_id" value="">

                <div class="alert status-info mt-sm" id="avg_score_container" style="display: none;">
                    <div class="alert-content">
                        <p id="avg_score_info">Povprečje: <span id="edit_item_avg_score"></span>%</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_item_name">Naziv:</label>
                    <input type="text" id="edit_item_name" name="name" class="form-input" required>
                </div>

                <div class="row">
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="edit_item_max_points">Največje število točk:</label>
                            <input type="number" id="edit_item_max_points" name="max_points" class="form-input"
                                   required min="1" step="0.01">
                        </div>
                    </div>
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="edit_item_date">Datum:</label>
                            <input type="date" id="edit_item_date" name="test_date" class="form-input">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <div class="d-flex gap-md">
                        <button type="button" class="btn btn-error" id="deleteGradeItemBtn">Izbriši</button>
                        <button type="submit" class="btn btn-primary">Shrani</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Grade Item Confirmation Modal -->
<div class="modal" id="deleteGradeItemConfirmModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="deleteGradeItemConfirmTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteGradeItemConfirmTitle">Potrditev izbrisa</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-warning mb-md">
                <div class="alert-icon">⚠</div>
                <div class="alert-content">
                    <p>Ali ste prepričani, da želite izbrisati preverjanje znanja <strong
                                id="delete_item_name"></strong>?</p>
                </div>
            </div>
            <div class="alert status-error">
                <div class="alert-icon">✕</div>
                <div class="alert-content">
                    <p>S tem bodo izbrisane tudi vse obstoječe ocene za to preverjanje znanja. Tega dejanja ni mogoče
                        razveljaviti.</p>
                </div>
            </div>
            <input type="hidden" id="delete_item_id" value="">
        </div>
        <div class="modal-footer">
            <div class="d-flex justify-between w-full">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="button" class="btn btn-error" id="confirmDeleteItemBtn">Izbriši</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Grade Modal -->
<div class="modal" id="editGradeModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editGradeTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editGradeTitle">Uredi oceno</h3>
        </div>
        <form id="editGradeForm" method="POST" action="gradebook.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="save_grade" value="1">
                <input type="hidden" id="edit_grade_enroll_id" name="enroll_id" value="">
                <input type="hidden" id="edit_grade_item_id" name="item_id" value="">

                <div class="alert status-info mb-lg">
                    <div class="alert-content">
                        <p><strong>Učenec:</strong> <span id="edit_grade_student_name"
                                                          style="text-decoration: underline; text-underline-offset: 3px"></span>
                        </p>
                        <p><strong>Preverjanje znanja:</strong> <span id="edit_grade_item_name"></span></p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_grade_points">Točke:</label>
                    <input type="number" id="edit_grade_points" name="points" class="form-input"
                           required min="0" step="0.01">
                    <small id="edit_grade_max_points" class="text-secondary"></small>
                    <!-- Add percentage display -->
                    <div class="form-group mt-sm">
                        <div id="grade_percentage_display" class="grade grade-1 mb-sm"
                             style="display: inline-block; padding: 6px 12px; min-width: 100px; text-align: center;">
                            0%
                        </div>
                        <span id="grade_letter_display" class="text-secondary ml-sm">Nezadostno (1)</span>
                    </div>
                </div>

                <!-- Grade Presets -->
                <div class="form-group">
                    <label class="form-label">Hitri vnos ocen:</label>
                    <div class="d-flex gap-sm flex-wrap">
                        <button type="button" class="btn btn-sm grade grade-5" onclick="setGradePoints(5)">Odlično (5)
                        </button>
                        <button type="button" class="btn btn-sm grade grade-4" onclick="setGradePoints(4)">Prav dobro
                            (4)
                        </button>
                        <button type="button" class="btn btn-sm grade grade-3" onclick="setGradePoints(3)">Dobro (3)
                        </button>
                        <button type="button" class="btn btn-sm grade grade-2" onclick="setGradePoints(2)">Zadostno
                            (2)
                        </button>
                        <button type="button" class="btn btn-sm grade grade-1" onclick="setGradePoints(1)">Nezadostno
                            (1)
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_grade_comment">Komentar (neobvezno):</label>
                    <textarea id="edit_grade_comment" name="comment" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <div class="d-flex gap-md">
                        <button type="button" class="btn btn-error" id="deleteGradeBtn">Izbriši</button>
                        <button type="submit" class="btn btn-primary">Shrani</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Grade Confirmation Modal -->
<div class="modal" id="deleteGradeConfirmModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="deleteGradeConfirmTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteGradeConfirmTitle">Potrditev izbrisa</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-warning mb-md">
                <div class="alert-icon">⚠</div>
                <div class="alert-content">
                    <p>Ali ste prepričani, da želite izbrisati oceno za učenca <strong
                                id="delete_grade_student_name"></strong>?</p>
                </div>
            </div>
            <div class="alert status-error">
                <div class="alert-icon">✕</div>
                <div class="alert-content">
                    <p>Tega dejanja ni mogoče razveljaviti.</p>
                </div>
            </div>
            <input type="hidden" id="delete_grade_enroll_id" value="">
            <input type="hidden" id="delete_grade_item_id" value="">
        </div>
        <div class="modal-footer">
            <div class="d-flex justify-between w-full">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="button" class="btn btn-error" id="confirmDeleteGradeBtn">Izbriši</button>
            </div>
        </div>
    </div>
</div>

<!-- Batch Grade Entry Modal -->
<div class="modal" id="batchGradeModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="batchGradeTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="batchGradeTitle">Množični vnos ocen</h3>
        </div>
        <form id="batchGradeForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="batch_grade_item">Izberi preverjanje znanja:</label>
                    <select id="batch_grade_item" class="form-select" required>
                        <?php foreach ($gradeItems as $item): ?>
                            <option value="<?= $item['item_id'] ?>"><?= htmlspecialchars($item['name']) ?>
                                (<?= $item['max_points'] ?> točk)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="table-responsive mt-md">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th class="text-left">Učenec</th>
                            <th>Točke</th>
                            <th>Komentar</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $student): ?>
                            <?php if (isset($student['enroll_id'])): ?>
                                <tr>
                                    <td><?= htmlspecialchars(($student['last_name'] ?? '') . ' ' . ($student['first_name'] ?? '')) ?></td>
                                    <td>
                                        <label>
                                            <input type="number" class="form-input batch-points"
                                                   data-enroll-id="<?= $student['enroll_id'] ?>"
                                                   min="0" step="0.5" style="width: 80px">
                                        </label>
                                    </td>
                                    <td>
                                        <label>
                                            <input type="text" class="form-input batch-comment"
                                                   data-enroll-id="<?= $student['enroll_id'] ?>">
                                        </label>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Quick tools for batch entry -->
                <div class="mt-md px-md pb-md">
                    <label class="form-label">Hitri vnos:</label>
                    <div class="d-flex gap-md flex-wrap w-full">
                        <div class="form-group mb-0 flex-grow-1">
                            <label for="batchValueInput"></label><input type="number" id="batchValueInput"
                                                                        class="form-input" placeholder="Točke">
                        </div>
                        <button type="button" class="btn btn-secondary" id="batchApplyButton">Določi vsem</button>
                        <button type="button" class="btn btn-secondary" id="clearAllBatchButton">Počisti vse</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="button" class="btn btn-primary" id="saveBatchGradesButton">Shrani vse ocene</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
    .editable-grade {
        transition: all 0.15s ease-out;
        position: relative;
        overflow: visible;
        cursor: pointer;
        padding: 6px 8px;
        border-radius: 4px;
        text-align: center;
        align-items: center;
        justify-content: center;
        display: inline-flex; /* Changed to inline-flex */
    }

    .editable-grade:hover {
        transform: scale(1.02);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        z-index: 2;
    }

    .editable-grade::after {
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

    .editable-grade:hover::after {
        transform: scaleX(1);
    }

    .edit-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        opacity: 0.6;
        transition: opacity 0.15s ease-out;
    }

    .edit-icon svg {
        stroke: var(--text-secondary);
    }

    .editable-grade:hover .edit-icon {
        opacity: 1;
    }

    .editable-grade:hover .edit-icon svg {
        stroke: var(--accent-primary);
    }

    /* Ensure all table cells are centered */
    .data-table td {
        text-align: center;
        vertical-align: middle;
    }

    /* Student name column should remain left-aligned */
    .data-table td:first-child {
        text-align: left;
    }

    .grade-item-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .grade-item-name {
        font-weight: 600;
        text-align: center;
        max-width: fit-content; /* Added max-width */
        margin: 0 auto; /* Center the element */
    }

    /* Specific fix for grade-item-edit combined with editable-grade */
    .grade-item-name.editable-grade.grade-item-edit {
        display: inline-block;
        width: auto;
        max-width: 100%;
    }

    .data-table th {
        text-align: center;
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
                // Reset forms if present
                const form = modal.querySelector('form');
                if (form) form.reset();

                // Clear any error messages
                const errorMsgs = modal.querySelectorAll('.feedback-error');
                errorMsgs.forEach(msg => {
                    if (msg && msg.style) {
                        msg.style.display = 'none';
                    }
                });
            }
        };

        // --- Percentage Calculation Function ---
        const updateGradePercentage = () => {
            const pointsInput = document.getElementById('edit_grade_points');
            const maxPointsText = document.getElementById('edit_grade_max_points').textContent;
            const percentageDisplay = document.getElementById('grade_percentage_display');
            const letterDisplay = document.getElementById('grade_letter_display');

            if (pointsInput && maxPointsText && percentageDisplay) {
                const points = parseFloat(pointsInput.value) || 0;
                const maxPointsMatch = maxPointsText.match(/\d+(\.\d+)?/);

                if (maxPointsMatch) {
                    const maxPoints = parseFloat(maxPointsMatch[0]);
                    const percentage = maxPoints > 0 ? (points / maxPoints) * 100 : 0;

                    // Update display
                    percentageDisplay.textContent = percentage.toFixed(1) + '%';

                    // Set grade class
                    let gradeClass;
                    let letterGrade;

                    if (percentage >= 90) {
                        gradeClass = 'grade-5';
                        letterGrade = 'Odlično (5)';
                    } else if (percentage >= 75) {
                        gradeClass = 'grade-4';
                        letterGrade = 'Prav dobro (4)';
                    } else if (percentage >= 61) {
                        gradeClass = 'grade-3';
                        letterGrade = 'Dobro (3)';
                    } else if (percentage >= 50) {
                        gradeClass = 'grade-2';
                        letterGrade = 'Zadostno (2)';
                    } else {
                        gradeClass = 'grade-1';
                        letterGrade = 'Nezadostno (1)';
                    }

                    // Remove all grade classes
                    percentageDisplay.classList.remove('grade-1', 'grade-2', 'grade-3', 'grade-4', 'grade-5');
                    // Add the correct grade class
                    percentageDisplay.classList.add(gradeClass);

                    // Update letter grade
                    letterDisplay.textContent = letterGrade;
                }
            }
        };

        // --- Event Listeners ---

        // Grade cells click to edit
        document.querySelectorAll('.grade[data-open-modal="editGradeModal"]').forEach(cell => {
            cell.addEventListener('click', function () {
                const modalId = this.dataset.openModal;
                const enrollId = this.dataset.enrollId;
                const itemId = this.dataset.itemId;
                const studentName = this.dataset.studentName;
                const itemName = this.dataset.itemName;
                const maxPoints = this.dataset.maxPoints;
                const points = this.dataset.points;
                const comment = this.dataset.comment;

                // Fill in form fields
                document.getElementById('edit_grade_enroll_id').value = enrollId;
                document.getElementById('edit_grade_item_id').value = itemId;
                document.getElementById('edit_grade_student_name').textContent = studentName;
                document.getElementById('edit_grade_item_name').textContent = itemName;
                document.getElementById('edit_grade_points').value = points;
                document.getElementById('edit_grade_points').max = maxPoints;
                document.getElementById('edit_grade_max_points').textContent = `Največje število točk: ${maxPoints}`;
                document.getElementById('edit_grade_comment').value = comment;

                // Update percentage display
                updateGradePercentage();

                openModal(modalId);
            });
        });

        // Add event listener for points input to update percentage
        document.getElementById('edit_grade_points').addEventListener('input', updateGradePercentage);

        // Grade item header click to edit
        document.querySelectorAll('.grade-item-edit').forEach(el => {
            el.addEventListener('click', function () {
                const {itemId, name, maxPoints, date, avgScore} = this.dataset;

                // Fill modal fields
                document.getElementById('edit_item_id').value = itemId;
                document.getElementById('edit_item_name').value = name;
                document.getElementById('edit_item_max_points').value = maxPoints;
                document.getElementById('edit_item_date').value = date || '';

                // Average‑score visibility
                const avgScoreElement = document.getElementById('edit_item_avg_score');
                const avgScoreContainer = document.getElementById('avg_score_container');
                if (avgScore) {
                    avgScoreElement.textContent = avgScore;
                    avgScoreContainer.style.display = 'flex';
                } else {
                    avgScoreContainer.style.display = 'none';
                }

                openModal('editGradeItemModal');
            });
        });

        // Open modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            if (!btn.classList.contains('grade') && !btn.classList.contains('grade-item-edit')) { // Skip already handled elements
                btn.addEventListener('click', function () {
                    const modalId = this.dataset.openModal;
                    openModal(modalId);

                    // If the button has additional data attributes, process them
                    // Example: data-id, data-name, etc.
                    const dataId = this.dataset.id;
                    const dataName = this.dataset.name;

                    if (dataId) {
                        // Handle ID data (e.g., fill hidden form field)
                        const idField = document.getElementById(`${modalId}_id`);
                        if (idField) idField.value = dataId;
                    }

                    if (dataName) {
                        // Handle name data (e.g., show in confirmation text)
                        const nameDisplay = document.getElementById(`${modalId}_name`);
                        if (nameDisplay) nameDisplay.textContent = dataName;
                    }
                });
            }
        });

        // Delete grade item button
        if (document.getElementById('deleteGradeItemBtn')) {
            document.getElementById('deleteGradeItemBtn').addEventListener('click', function () {
                const itemId = document.getElementById('edit_item_id').value;
                const itemName = document.getElementById('edit_item_name').value;

                // Set values in the confirmation modal
                document.getElementById('delete_item_id').value = itemId;
                document.getElementById('delete_item_name').textContent = itemName;

                closeModal('editGradeItemModal');
                openModal('deleteGradeItemConfirmModal');
            });
        }

        // Confirm delete grade item button
        if (document.getElementById('confirmDeleteItemBtn')) {
            document.getElementById('confirmDeleteItemBtn').addEventListener('click', function () {
                const itemId = document.getElementById('delete_item_id').value;

                // Create and submit form for deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'gradebook.php';
                form.style.display = 'none';

                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';
                form.appendChild(csrfInput);

                // Add delete flag
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_grade_item';
                deleteInput.value = '1';
                form.appendChild(deleteInput);

                // Add item_id
                const itemInput = document.createElement('input');
                itemInput.type = 'hidden';
                itemInput.name = 'item_id';
                itemInput.value = itemId;
                form.appendChild(itemInput);

                // Add class_subject_id
                const classSubjectInput = document.createElement('input');
                classSubjectInput.type = 'hidden';
                classSubjectInput.name = 'class_subject_id';
                classSubjectInput.value = '<?= $selectedClassSubjectId ?>';
                form.appendChild(classSubjectInput);

                // Submit the form
                document.body.appendChild(form);
                form.submit();
            });
        }

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

        // Submit edit grade form validation
        document.getElementById('editGradeForm').addEventListener('submit', function (e) {
            // Add form validation
            const points = parseFloat(document.getElementById('edit_grade_points').value);
            const maxPointsText = document.getElementById('edit_grade_max_points').textContent;
            const maxPointsMatch = maxPointsText.match(/\d+(\.\d+)?/);

            if (maxPointsMatch) {
                const maxPoints = parseFloat(maxPointsMatch[0]);

                if (points > maxPoints) {
                    e.preventDefault();

                    // Create or use existing feedback element
                    let feedbackEl = document.querySelector('#points-feedback');
                    if (!feedbackEl) {
                        feedbackEl = document.createElement('div');
                        feedbackEl.id = 'points-feedback';
                        feedbackEl.className = 'feedback-error';
                        document.getElementById('edit_grade_points').parentNode.appendChild(feedbackEl);
                    }

                    feedbackEl.textContent = `Število točk ne more presegati največjega števila točk (${maxPoints}).`;
                    feedbackEl.style.display = 'block';

                    document.getElementById('edit_grade_points').classList.add('is-invalid');
                    document.getElementById('edit_grade_points').focus();

                    return false;
                }
            }
        });

        // Delete grade button
        if (document.getElementById('deleteGradeBtn')) {
            document.getElementById('deleteGradeBtn').addEventListener('click', function () {
                const enrollId = document.getElementById('edit_grade_enroll_id').value;
                const itemId = document.getElementById('edit_grade_item_id').value;

                // Set values in the confirmation modal
                document.getElementById('delete_grade_student_name').textContent = document.getElementById('edit_grade_student_name').textContent;
                document.getElementById('delete_grade_enroll_id').value = enrollId;
                document.getElementById('delete_grade_item_id').value = itemId;

                closeModal('editGradeModal');
                openModal('deleteGradeConfirmModal');
            });
        }

        // Confirm delete grade button
        if (document.getElementById('confirmDeleteGradeBtn')) {
            document.getElementById('confirmDeleteGradeBtn').addEventListener('click', function () {
                const enrollId = document.getElementById('delete_grade_enroll_id').value;
                const itemId = document.getElementById('delete_grade_item_id').value;

                // Create and submit form for deletion
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'gradebook.php';
                form.style.display = 'none';

                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';
                form.appendChild(csrfInput);

                // Add delete flag
                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = 'delete_grade';
                deleteInput.value = '1';
                form.appendChild(deleteInput);

                // Add enroll_id
                const enrollInput = document.createElement('input');
                enrollInput.type = 'hidden';
                enrollInput.name = 'enroll_id';
                enrollInput.value = enrollId;
                form.appendChild(enrollInput);

                // Add item_id
                const itemInput = document.createElement('input');
                itemInput.type = 'hidden';
                itemInput.name = 'item_id';
                itemInput.value = itemId;
                form.appendChild(itemInput);

                // Submit the form
                document.body.appendChild(form);
                form.submit();
            });
        }

        // Print gradebook functionality
        document.getElementById('printGradebook').addEventListener('click', function () {
            window.print();
        });

        // Export gradebook to CSV
        document.getElementById('exportGradebook').addEventListener('click', function () {
            const table = document.querySelector('.data-table');
            let csv = [];
            const rows = table.querySelectorAll('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');

                for (let j = 0; j < cols.length; j++) {
                    // Get text content and clean it up
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, '').trim();

                    // If it's a grade cell, get the actual grade value
                    if (cols[j].querySelector('.grade')) {
                        data = cols[j].querySelector('.grade').innerText.split('*')[0].trim();
                    }

                    // Escape double quotes and wrap data in quotes
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }

                csv.push(row.join(','));
            }

            // Create and trigger download
            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'redovalnica.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });

        // Batch grade functionality
        if (document.getElementById('batchApplyButton')) {
            document.getElementById('batchApplyButton').addEventListener('click', function () {
                const value = document.getElementById('batchValueInput').value;
                if (value) {
                    document.querySelectorAll('.batch-points').forEach(input => {
                        input.value = value;
                    });
                }
            });

            document.getElementById('clearAllBatchButton').addEventListener('click', function () {
                document.querySelectorAll('.batch-points').forEach(input => {
                    input.value = '';
                });
                document.querySelectorAll('.batch-comment').forEach(input => {
                    input.value = '';
                });
            });

            document.getElementById('saveBatchGradesButton').addEventListener('click', function () {
                const itemId = document.getElementById('batch_grade_item').value;
                if (!itemId) {
                    alert('Prosimo, izberite preverjanje znanja.');
                    return;
                }

                const grades = [];

                document.querySelectorAll('.batch-points').forEach(input => {
                    const enrollId = input.dataset.enrollId;
                    const points = input.value.trim();
                    const comment = document.querySelector(`.batch-comment[data-enroll-id="${enrollId}"]`).value.trim();

                    if (points) {
                        grades.push({
                            enrollId: enrollId,
                            itemId: itemId,
                            points: points,
                            comment: comment
                        });
                    }
                });

                if (grades.length === 0) {
                    alert('Ni vnešenih ocen za shranjevanje.');
                    return;
                }

                // Create form for submission
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'gradebook.php';
                form.style.display = 'none';

                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';
                form.appendChild(csrfInput);

                // Add batch flag
                const batchInput = document.createElement('input');
                batchInput.type = 'hidden';
                batchInput.name = 'batch_grades';
                batchInput.value = '1';
                form.appendChild(batchInput);

                // Add grades data
                const gradesInput = document.createElement('input');
                gradesInput.type = 'hidden';
                gradesInput.name = 'grades_data';
                gradesInput.value = JSON.stringify(grades);
                form.appendChild(gradesInput);

                // Add selected class subject id
                const classSubjectInput = document.createElement('input');
                classSubjectInput.type = 'hidden';
                classSubjectInput.name = 'class_subject_id';
                classSubjectInput.value = '<?= $selectedClassSubjectId ?>';
                form.appendChild(classSubjectInput);

                // Submit the form
                document.body.appendChild(form);
                form.submit();
            });
        }

        // Grade presets
        window.setGradePoints = function (grade) {
            const maxPointsText = document.getElementById('edit_grade_max_points').textContent;
            const maxPointsMatch = maxPointsText.match(/\d+(\.\d+)?/);

            if (maxPointsMatch) {
                const maxPoints = parseFloat(maxPointsMatch[0]);
                let pointsToSet = 0;

                // Calculate points based on grade
                switch (grade) {
                    case 5: // Excellent - 90% or higher
                        pointsToSet = maxPoints * 0.95; // Set to 95% of max
                        break;
                    case 4: // Very good - 75-89%
                        pointsToSet = maxPoints * 0.85; // Set to 85% of max
                        break;
                    case 3: // Good - 61-74%
                        pointsToSet = maxPoints * 0.70; // Set to 70% of max
                        break;
                    case 2: // Sufficient - 50-60%
                        pointsToSet = maxPoints * 0.55; // Set to 55% of max
                        break;
                    case 1: // Insufficient - <50%
                        pointsToSet = maxPoints * 0.40; // Set to 40% of max
                        break;
                    default:
                        return;
                }

                // Set the points
                document.getElementById('edit_grade_points').value = pointsToSet.toFixed(2);

                // Update percentage display
                updateGradePercentage();
            }
        };

        // Keyboard navigation for grade entry
        const setupKeyboardNavigation = () => {
            const pointsInputs = document.querySelectorAll('.batch-points');
            const commentInputs = document.querySelectorAll('.batch-comment');

            pointsInputs.forEach((input, index) => {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown' && index < pointsInputs.length - 1) {
                        e.preventDefault();
                        pointsInputs[index + 1].focus();
                    } else if (e.key === 'ArrowUp' && index > 0) {
                        e.preventDefault();
                        pointsInputs[index - 1].focus();
                    } else if (e.key === 'Tab' && !e.shiftKey) {
                        e.preventDefault();
                        commentInputs[index].focus();
                    }
                });
            });

            commentInputs.forEach((input, index) => {
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowDown' && index < commentInputs.length - 1) {
                        e.preventDefault();
                        commentInputs[index + 1].focus();
                    } else if (e.key === 'ArrowUp' && index > 0) {
                        e.preventDefault();
                        commentInputs[index - 1].focus();
                    } else if (e.key === 'Tab' && e.shiftKey) {
                        e.preventDefault();
                        pointsInputs[index].focus();
                    } else if (e.key === 'Tab' && !e.shiftKey && index < pointsInputs.length - 1) {
                        e.preventDefault();
                        pointsInputs[index + 1].focus();
                    }
                });
            });
        };

        // Initialize keyboard navigation if batch modal is open
        document.querySelector('[data-open-modal="batchGradeModal"]')?.addEventListener('click', function () {
            setTimeout(setupKeyboardNavigation, 100);
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
