<?php
/**
 * Teacher Grade Book
 * /uwuweb/teacher/gradebook.php
 *
 * Provides interface for teachers to manage student grades
 * Supports viewing, adding, and editing grades for assigned classes
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'teacher_functions.php';

requireRole(ROLE_TEACHER);

$teacherId = getTeacherId();
if (!$teacherId) {
    echo generateAlert('Račun učitelja ni najden.', 'error');
    include '../includes/footer.php';
    exit;
}

$pdo = safeGetDBConnection('teacher/gradebook.php');
$csrfToken = generateCSRFToken();

// Get classes taught by this teacher
$teacherClassSubjects = getTeacherClasses($teacherId);

// Process classes into a nested structure for the template
$teacherClasses = [];
foreach ($teacherClassSubjects as $classSubject) {
    $classId = $classSubject['class_id'];
    if (!isset($teacherClasses[$classId])) {
        $teacherClasses[$classId] = [
            'class_id' => $classId,
            'class_title' => $classSubject['class_title'],
            'class_code' => $classSubject['class_code'],
            'subjects' => []
        ];
    }

    $teacherClasses[$classId]['subjects'][] = [
        'class_subject_id' => $classSubject['class_subject_id'],
        'subject_id' => $classSubject['subject_id'],
        'subject_name' => $classSubject['subject_name']
    ];
}
$teacherClasses = array_values($teacherClasses);

// Handle class selection
$selectedClassSubject = null;
$selectedClassInfo = null;
$gradeItems = [];
$classGrades = [];

if (isset($_GET['class_subject_id']) && is_numeric($_GET['class_subject_id'])) {
    $selectedClassSubject = (int)$_GET['class_subject_id'];

    // Verify teacher has access to this class-subject
    if (!teacherHasAccessToClassSubject($selectedClassSubject, $teacherId)) {
        echo generateAlert('Nimate dostopa do izbranega razreda.', 'error');
        $selectedClassSubject = null;
    } else try {
        $stmt = $pdo->prepare("
            SELECT cs.class_subject_id, c.title as class_title, s.name as subject_name 
            FROM class_subjects cs
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE cs.class_subject_id = ?
        ");
        $stmt->execute([$selectedClassSubject]);
        $selectedClassInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get grade items for this class-subject
        $gradeItems = getGradeItems($selectedClassSubject);

        // Get all grades for this class-subject
        $gradesData = getClassGrades($selectedClassSubject);

        // Process the data structure for the template
        $classGrades = [];
        if (!empty($gradesData['students'])) {
            foreach ($gradesData['students'] as $student) {
                $studentData = [
                    'enroll_id' => $student['enroll_id'],
                    'student_name' => $student['last_name'] . ' ' . $student['first_name'],
                    'grades' => []
                ];

                // Add grades if they exist
                if (isset($gradesData['grades'][$student['enroll_id']])) {
                    foreach ($gradesData['grades'][$student['enroll_id']] as $itemId => $grade) {
                        $grade['item_id'] = $itemId;
                        $studentData['grades'][] = $grade;
                    }
                } else {
                    $studentData['grades'] = []; // Ensure grades array exists even if empty
                }

                $classGrades[] = $studentData;
            }
        }
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        echo generateAlert('Napaka pri pridobivanju podatkov o ocenah.', 'error');
    }
}

// Process form submission for adding grade item (AJAX is handled in api/grades.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'add_grade_item') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) echo generateAlert('Neveljaven varnostni žeton. Poskusite znova.', 'error'); else {
    $classSubjectId = isset($_POST['class_subject_id']) ? (int)$_POST['class_subject_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $maxPoints = isset($_POST['max_points']) ? (float)$_POST['max_points'] : 0;
    $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 1.0;

    if (empty($name) || $maxPoints <= 0) echo generateAlert('Prosimo, izpolnite vsa obvezna polja.', 'error'); else {
        $result = addGradeItem($classSubjectId, $name, $maxPoints, $weight);
        if ($result !== false) {
            echo generateAlert('Element ocene uspešno dodan.', 'success');
            // Refresh the page to show the new grade item
            header("Location: gradebook.php?class_subject_id=$classSubjectId");
            exit;
        } else echo generateAlert('Napaka pri dodajanju elementa ocene.', 'error');
    }
}

// Process form submission for saving grade (AJAX is handled in api/grades.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'save_grade') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) echo generateAlert('Neveljaven varnostni žeton. Poskusite znova.', 'error'); else {
    $enrollId = isset($_POST['enroll_id']) ? (int)$_POST['enroll_id'] : 0;
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $points = isset($_POST['points']) ? (float)$_POST['points'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;
    $classSubjectId = isset($_POST['class_subject_id']) ? (int)$_POST['class_subject_id'] : 0;

    if ($enrollId > 0 && $itemId > 0) {
        $result = saveGrade($enrollId, $itemId, $points, $comment);
        if ($result) {
            echo generateAlert('Ocena uspešno shranjena.', 'success');
            // Refresh the page to show the updated grade
            header("Location: gradebook.php?class_subject_id=$classSubjectId");
            exit;
        } else echo generateAlert('Napaka pri shranjevanju ocene.', 'error');
    } else echo generateAlert('Manjkajoči ali neveljavni podatki.', 'error');
}

renderHeaderCard('Redovalnica', 'Upravljanje ocen učencev', 'teacher', 'Učitelj');
?>

<div class="section">
    <div class="container">
        <!-- Class selection -->
        <div class="card mb-lg">
            <div class="card__title">Izbira razreda in predmeta</div>
            <div class="card__content">
                <?php if (empty($teacherClasses)): ?>
                    <p>Trenutno nimate dodeljenih razredov.</p>
                <?php else: ?>
                    <form method="GET" action="gradebook.php" class="mb-md">
                        <div class="form-group">
                            <label for="class_subject_select" class="form-label">Izberite razred in predmet:</label>
                            <select id="class_subject_select" name="class_subject_id" class="form-select"
                                    onchange="this.form.submit()">
                                <option value="">-- Izberite razred in predmet --</option>
                                <?php foreach ($teacherClasses as $class): ?>
                                    <?php foreach ($class['subjects'] as $subject): ?>
                                        <option value="<?= htmlspecialchars($subject['class_subject_id']) ?>"
                                            <?= $selectedClassSubject == $subject['class_subject_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($class['class_title']) ?>
                                            - <?= htmlspecialchars($subject['subject_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($selectedClassSubject && $selectedClassInfo): ?>
            <div class="card mb-lg">
                <div class="card__title">
                    Redovalnica: <?= htmlspecialchars($selectedClassInfo['class_title']) ?> -
                    <?= htmlspecialchars($selectedClassInfo['subject_name']) ?>
                </div>
                <div class="card__content">
                    <div class="mb-md d-flex items-center justify-between">
                        <h3>Elementi ocenjevanja</h3>
                        <button data-open-modal="addGradeItemModal" class="btn btn-primary">
                            Dodaj element ocene
                        </button>
                    </div>

                    <?php if (empty($gradeItems)): ?>
                        <div class="alert status-info mb-md">
                            <div class="alert-content">
                                <p>Za ta razred in predmet še ni elementov ocenjevanja. Dodajte nov element z gumbom
                                    zgoraj.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Učenec</th>
                                    <?php foreach ($gradeItems as $item): ?>
                                        <th title="Max: <?= htmlspecialchars($item['max_points']) ?>, Utež: <?= htmlspecialchars($item['weight']) ?>">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th>Povprečje</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($classGrades)): ?>
                                    <tr>
                                        <td colspan="<?= count($gradeItems) + 2 ?>">Ni najdenih učencev za ta
                                            razred.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($classGrades as $student): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($student['student_name'] ?? '') ?></td>
                                            <?php
                                            $totalPoints = 0;
                                            $totalWeight = 0;
                                            $gradeCount = 0;
                                            ?>
                                            <?php foreach ($gradeItems as $item): ?>
                                                <?php
                                                $grade = null;
                                                if (!empty($student['grades'])) {
                                                    foreach ($student['grades'] as $g) {
                                                        if ($g['item_id'] == $item['item_id']) {
                                                            $grade = $g;
                                                            break;
                                                        }
                                                    }
                                                }

                                                $percentage = 0;
                                                if ($grade) {
                                                    $percentage = ($grade['points'] / $item['max_points']) * 100;
                                                    $totalPoints += ($percentage / 100) * $item['weight'];
                                                    $totalWeight += $item['weight'];
                                                    $gradeCount++;
                                                }

                                                $gradeClass = '';
                                                if ($grade) if ($percentage >= 80) $gradeClass = 'grade-high'; elseif ($percentage >= 50) $gradeClass = 'grade-medium';
                                                else $gradeClass = 'grade-low';
                                                ?>
                                                <td class="<?= $gradeClass ?>">
                                                    <?php if ($grade): ?>
                                                        <span class="grade"
                                                              data-open-modal="editGradeModal"
                                                              data-enroll-id="<?= $student['enroll_id'] ?>"
                                                              data-item-id="<?= $item['item_id'] ?>"
                                                              data-points="<?= $grade['points'] ?>"
                                                              data-comment="<?= htmlspecialchars($grade['comment'] ?? '') ?>"
                                                              data-max-points="<?= $item['max_points'] ?>"
                                                              data-student-name="<?= htmlspecialchars($student['student_name']) ?>"
                                                              data-item-name="<?= htmlspecialchars($item['name']) ?>">
                                                                <?= htmlspecialchars($grade['points']) ?>
                                                            <?php if (!empty($grade['comment'])): ?>
                                                                <span class="text-secondary">*</span>
                                                            <?php endif; ?>
                                                            </span>
                                                    <?php else: ?>
                                                        <span class="text-disabled"
                                                              data-open-modal="editGradeModal"
                                                              data-enroll-id="<?= $student['enroll_id'] ?? 0 ?>"
                                                              data-item-id="<?= $item['item_id'] ?>"
                                                              data-points=""
                                                              data-comment=""
                                                              data-max-points="<?= $item['max_points'] ?>"
                                                              data-student-name="<?= htmlspecialchars($student['student_name'] ?? '') ?>"
                                                              data-item-name="<?= htmlspecialchars($item['name']) ?>">
                                                                -
                                                            </span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td>
                                                <?php if ($gradeCount > 0 && $totalWeight > 0): ?>
                                                    <?php
                                                    $weightedAverage = $totalPoints / $totalWeight;
                                                    $averageClass = '';
                                                    if ($weightedAverage >= 0.8) $averageClass = 'grade-high'; elseif ($weightedAverage >= 0.5) $averageClass = 'grade-medium';
                                                    else $averageClass = 'grade-low';
                                                    ?>
                                                    <span class="grade <?= $averageClass ?>">
                                                            <?= number_format($weightedAverage * 5, 1) ?>
                                                        </span>
                                                <?php else: ?>
                                                    <span class="text-disabled">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Class average visualization -->
            <?php if (!empty($gradeItems) && !empty($classGrades)): ?>
                <div class="card">
                    <div class="card__title">Razredna analiza ocen</div>
                    <div class="card__content">
                        <div class="row">
                            <div class="col col-md-6">
                                <h4 class="mb-md">Povprečne ocene po elementih</h4>
                                <div class="alert status-info mb-md">
                                    <div class="alert-content">
                                        <p>Kliknite na ime elementi za urejanje podrobnosti ali element ocene za
                                            vnos ocene.</p>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                        <tr>
                                            <th>Element ocene</th>
                                            <th>Povprečje razreda</th>
                                            <th>Max. točke</th>
                                            <th>Utež</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($gradeItems as $item): ?>
                                            <?php
                                            // Calculate class average for this item
                                            $totalPoints = 0;
                                            $count = 0;

                                            foreach ($classGrades as $student) {
                                                if (!empty($student['grades'])) {
                                                    foreach ($student['grades'] as $grade) {
                                                        if ($grade['item_id'] == $item['item_id']) {
                                                            $totalPoints += $grade['points'];
                                                            $count++;
                                                            break;
                                                        }
                                                    }
                                                }
                                            }

                                            $average = $count > 0 ? $totalPoints / $count : 0;
                                            $percentage = $item['max_points'] > 0 ? ($average / $item['max_points']) * 100 : 0;

                                            $averageClass = '';
                                            if ($percentage >= 80) $averageClass = 'grade-high'; elseif ($percentage >= 50) $averageClass = 'grade-medium';
                                            else $averageClass = 'grade-low';
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['name']) ?></td>
                                                <td class="<?= $averageClass ?>">
                                                    <?php if ($count > 0): ?>
                                                        <?= number_format($average, 1) ?> / <?= $item['max_points'] ?>
                                                        (<?= number_format($percentage, 1) ?>%)
                                                    <?php else: ?>
                                                        <span class="text-disabled">Ni ocen</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $item['max_points'] ?></td>
                                                <td><?= $item['weight'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col col-md-6">
                                <h4 class="mb-md">Skupno razredno povprečje</h4>
                                <?php
                                // Calculate overall class average
                                $overallTotal = 0;
                                $overallCount = 0;

                                foreach ($classGrades as $student) {
                                    $studentTotalPoints = 0;
                                    $studentTotalWeight = 0;

                                    if (!empty($student['grades'])) {
                                        foreach ($student['grades'] as $grade) {
                                            foreach ($gradeItems as $item) {
                                                if ($item['item_id'] == $grade['item_id']) {
                                                    $percentage = ($grade['points'] / $item['max_points']) * 100;
                                                    $studentTotalPoints += ($percentage / 100) * $item['weight'];
                                                    $studentTotalWeight += $item['weight'];
                                                    break;
                                                }
                                            }
                                        }
                                    }

                                    if ($studentTotalWeight > 0) {
                                        $overallTotal += ($studentTotalPoints / $studentTotalWeight) * 5; // Convert to 5-point scale
                                        $overallCount++;
                                    }
                                }

                                $classAverage = $overallCount > 0 ? $overallTotal / $overallCount : 0;

                                $averageClass = '';
                                if ($classAverage >= 4) $averageClass = 'grade-high'; elseif ($classAverage >= 2.5) $averageClass = 'grade-medium';
                                else $averageClass = 'grade-low';
                                ?>

                                <div class="card">
                                    <div class="card__content text-center">
                                        <h2 class="font-bold mb-sm">Skupno povprečje razreda</h2>
                                        <div class="grade <?= $averageClass ?> font-bold" style="font-size: 3rem;">
                                            <?= number_format($classAverage, 2) ?>
                                        </div>
                                        <p class="mt-md">na lestvici od 1 do 5</p>
                                    </div>
                                </div>

                                <div class="mt-lg">
                                    <h4 class="mb-md">Legenda ocen</h4>
                                    <div class="d-flex flex-column gap-sm">
                                        <div class="d-flex items-center gap-sm">
                                            <span class="grade grade-high">5</span>
                                            <span>Odlično (80-100%)</span>
                                        </div>
                                        <div class="d-flex items-center gap-sm">
                                            <span class="grade grade-medium">3</span>
                                            <span>Dobro (50-79%)</span>
                                        </div>
                                        <div class="d-flex items-center gap-sm">
                                            <span class="grade grade-low">1</span>
                                            <span>Nezadostno (0-49%)</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Grade Item Modal -->
<div class="modal" id="addGradeItemModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="addGradeItemModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="addGradeItemModalTitle">Dodaj element ocene</h3>
        </div>
        <form id="addGradeItemForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action_type" value="add_grade_item">
                <input type="hidden" name="class_subject_id" value="<?= $selectedClassSubject ?? '' ?>">

                <div class="form-group">
                    <label class="form-label" for="grade_item_name">Naziv:</label>
                    <input type="text" id="grade_item_name" name="name" class="form-input" required>
                </div>

                <div class="row">
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="grade_item_max_points">Največje možno število
                                točk:</label>
                            <input type="number" id="grade_item_max_points" name="max_points" class="form-input"
                                   required min="1" step="0.01">
                        </div>
                    </div>
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="grade_item_weight">Utež:</label>
                            <input type="number" id="grade_item_weight" name="weight" class="form-input"
                                   value="1.00" min="0.01" max="3.00" step="0.01">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Dodaj</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Grade Modal -->
<div class="modal" id="editGradeModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editGradeModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editGradeModalTitle">Urejanje ocene</h3>
        </div>
        <form id="editGradeForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action_type" value="save_grade">
                <input type="hidden" name="class_subject_id" value="<?= $selectedClassSubject ?? '' ?>">
                <input type="hidden" id="editGradeModal_enroll_id" name="enroll_id" value="">
                <input type="hidden" id="editGradeModal_item_id" name="item_id" value="">

                <p class="mb-md">
                    Urejanje ocene za <strong id="editGradeModal_student_name"></strong> pri elementu
                    <strong id="editGradeModal_item_name"></strong>
                </p>

                <div class="form-group">
                    <label class="form-label" for="grade_points">Točke:</label>
                    <input type="number" id="grade_points" name="points" class="form-input" required min="0"
                           step="0.01">
                    <div class="feedback-text" id="grade_points_info">Maksimalne točke: <span
                                id="editGradeModal_max_points"></span></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="grade_comment">Opomba (opcijsko):</label>
                    <textarea id="grade_comment" name="comment" class="form-textarea" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Shrani</button>
            </div>
        </form>
    </div>
</div>

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

        // --- Event Listeners ---

        // Open modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;
                openModal(modalId);

                // Handle grade editing modal data
                if (modalId === 'editGradeModal') {
                    document.getElementById('editGradeModal_enroll_id').value = this.dataset.enrollId;
                    document.getElementById('editGradeModal_item_id').value = this.dataset.itemId;
                    document.getElementById('grade_points').value = this.dataset.points;
                    document.getElementById('grade_comment').value = this.dataset.comment;
                    document.getElementById('editGradeModal_max_points').textContent = this.dataset.maxPoints;
                    document.getElementById('editGradeModal_student_name').textContent = this.dataset.studentName;
                    document.getElementById('editGradeModal_item_name').textContent = this.dataset.itemName;

                    // Set max attribute on the points input
                    document.getElementById('grade_points').setAttribute('max', this.dataset.maxPoints);
                }
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

        // Validate max points when entering grade points
        document.getElementById('grade_points').addEventListener('input', function () {
            const maxPoints = parseFloat(document.getElementById('editGradeModal_max_points').textContent);
            const currentPoints = parseFloat(this.value);

            if (currentPoints > maxPoints) {
                this.setCustomValidity(`Najvišje možno število točk je ${maxPoints}`);
            } else {
                this.setCustomValidity('');
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
