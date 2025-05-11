<?php
/**
 * Parent Grades View
 *
 * Allows parents to view grade records for their linked students in read-only mode
 *
 */

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once '../parent/parent_functions.php';

requireRole(ROLE_PARENT);

$parentId = getParentId();
if (!$parentId) die('Napaka: Starševski račun ni najden.');

$pdo = safeGetDBConnection('parent/grades.php');

// Get all students linked to this parent
$students = getParentStudents($parentId);

// Get selected student ID from URL parameter or default to first student
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($students[0]['student_id'] ?? 0);

// Get the selected student's details
$selectedStudent = null;
if ($selectedStudentId) foreach ($students as $student) if ($student['student_id'] == $selectedStudentId) {
    $selectedStudent = $student;
    break;
}

// If we have a selected student, get their class and grade information
$studentClasses = [];
$classGrades = [];

if ($selectedStudent) {
    $studentClasses = getStudentClasses($selectedStudentId);

    // Get the selected class ID from URL parameter or default to first class
    $selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($studentClasses[0]['class_id'] ?? 0);

    if ($selectedClassId > 0) $classGrades = getClassGrades($selectedClassId);
}

// Get the CSRF token for form security
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse('Napaka pri ustvarjanju token-a za zaščito oblike.', 500);
}
?>

<div class="container mt-lg mb-lg">
    <?php
    renderHeaderCard(
        'Ocene učenca',
        'Preglejte ocene vašega otroka po predmetih.',
        'parent',
        'Starš'
    );
    ?>

    <?php if (count($students) > 1): ?>
        <div class="card shadow mb-lg">
            <div class="card__content">
                <form method="GET" action="grades.php" class="d-flex items-end gap-md flex-wrap">
                    <div class="form-group mb-0 flex-grow-1">
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
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary">Pokaži ocene</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selectedStudentId && $selectedStudent): ?>
        <?php if (!empty($studentClasses)): ?>
            <div class="card shadow mb-lg">
                <div class="card__title d-flex justify-between items-center">
                    <span>Izbira razreda in predmeta</span>
                    <span class="text-sm text-secondary">Učenec: <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></span>
                </div>
                <div class="card__content">
                    <form method="GET" action="grades.php" class="d-flex items-end gap-md flex-wrap">
                        <input type="hidden" name="student_id" value="<?= $selectedStudentId ?>">
                        <div class="form-group mb-0 flex-grow-1">
                            <label for="class_id" class="form-label">Izberite razred:</label>
                            <select id="class_id" name="class_id" class="form-input form-select">
                                <option value="">-- Izberite razred --</option>
                                <?php
                                $uniqueClasses = [];
                                foreach ($studentClasses as $class) if (!isset($uniqueClasses[$class['class_id']])) $uniqueClasses[$class['class_id']] = $class;
                                foreach ($uniqueClasses as $class):
                                    ?>
                                    <option value="<?= $class['class_id'] ?>" <?= isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_title'] . ' (' . $class['class_code'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <button type="submit" class="btn btn-primary">Pokaži ocene za razred</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($classGrades)): ?>
                <div class="row mb-lg">
                    <div class="col">
                        <div class="card shadow">
                            <div class="card__title">Ocene po predmetih</div>
                            <div class="card__content">
                                <?php foreach ($classGrades as $subject): ?>
                                    <div class="mb-xl">
                                        <div class="d-flex justify-between items-center mb-md">
                                            <h3 class="m-0 font-medium"><?= htmlspecialchars($subject['subject_name']) ?></h3>
                                            <?php if (isset($subject['average']) && $subject['average'] > 0): ?>
                                                <?php
                                                $avgClass = 'badge-error';
                                                if ($subject['average'] >= 90) $avgClass = 'badge-success';
                                                elseif ($subject['average'] >= 75) $avgClass = 'badge-warning';
                                                elseif ($subject['average'] >= 60) $avgClass = 'badge-info';
                                                ?>
                                                <div class="d-flex gap-sm items-center">
                                                    <span class="text-secondary">Povprečje:</span>
                                                    <span class="badge <?= $avgClass ?>">
                                                        <?= number_format($subject['average'], 1) ?>%
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($subject['grade_items'])): ?>
                                            <div class="table-responsive">
                                                <table class="data-table w-full">
                                                    <thead>
                                                    <tr>
                                                        <th>Ocenjevalna enota</th>
                                                        <th class="text-center">Točke</th>
                                                        <th class="text-center">Možne točke</th>
                                                        <th class="text-center">Odstotek</th>
                                                        <th class="text-center">Utež</th>
                                                        <th>Opombe</th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($subject['grade_items'] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['name']) ?></td>
                                                            <td class="text-center"><?= isset($item['points']) ? htmlspecialchars($item['points']) : '<span class="text-disabled">—</span>' ?></td>
                                                            <td class="text-center"><?= htmlspecialchars($item['max_points']) ?></td>
                                                            <td class="text-center">
                                                                <?php if (isset($item['points'])): ?>
                                                                    <?php
                                                                    $percentage = ($item['points'] / $item['max_points']) * 100;
                                                                    $percentClass = 'text-error';
                                                                    if ($percentage >= 90) $percentClass = 'text-success';
                                                                    elseif ($percentage >= 75) $percentClass = 'text-warning';
                                                                    elseif ($percentage >= 60) $percentClass = 'text-info';
                                                                    ?>
                                                                    <span class="<?= $percentClass ?>"><?= number_format($percentage, 1) ?>%</span>
                                                                <?php else: ?>
                                                                    <span class="text-disabled">—</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center"><?= htmlspecialchars($item['weight']) ?></td>
                                                            <td><?= isset($item['comment']) ? nl2br(htmlspecialchars($item['comment'])) : '<span class="text-disabled">—</span>' ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert status-info">
                                                <span>Za ta predmet ni ocen na voljo.</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade details modal -->
                <div class="modal" id="gradeDetailsModal">
                    <div class="modal-overlay" aria-hidden="true"></div>
                    <div class="modal-container" role="dialog" aria-modal="true"
                         aria-labelledby="gradeDetailsModalTitle">
                        <div class="modal-header">
                            <h3 class="modal-title" id="gradeDetailsModalTitle">Podrobnosti ocene</h3>
                        </div>
                        <div class="modal-body">
                            <div class="d-flex justify-between mb-md">
                                <strong>Ocenjevalna enota:</strong>
                                <span id="gradeDetailsModal_name"></span>
                            </div>
                            <div class="d-flex justify-between mb-md">
                                <strong>Točke:</strong>
                                <span id="gradeDetailsModal_points"></span>
                            </div>
                            <div class="d-flex justify-between mb-md">
                                <strong>Možne točke:</strong>
                                <span id="gradeDetailsModal_maxPoints"></span>
                            </div>
                            <div class="d-flex justify-between mb-md">
                                <strong>Odstotek:</strong>
                                <span id="gradeDetailsModal_percentage"></span>
                            </div>
                            <div class="d-flex justify-between mb-md">
                                <strong>Utež:</strong>
                                <span id="gradeDetailsModal_weight"></span>
                            </div>
                            <div class="mb-md">
                                <strong>Opombe:</strong>
                                <div id="gradeDetailsModal_comment" class="p-sm border rounded mt-xs"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-close-modal>Zapri</button>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="card shadow">
                    <div class="card__content">
                        <div class="alert status-info d-flex items-center gap-sm">
                            <span>Izberite razred za prikaz ocen.</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="card shadow">
                <div class="card__content">
                    <div class="alert status-warning d-flex items-center gap-sm">
                        <span>Za učenca <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?> ni vpisanih razredov.</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif (!empty($students) && count($students) === 1): ?>
        <div class="alert status-info d-flex items-center gap-sm mb-lg">
            <span>Prikazujemo ocene za vašega edinega otroka: <?= htmlspecialchars($students[0]['first_name'] . ' ' . $students[0]['last_name']) ?>.</span>
        </div>
        <div class="alert status-warning d-flex items-center gap-sm">
            <span>Za <?= htmlspecialchars($students[0]['first_name'] . ' ' . $students[0]['last_name']) ?> ni podatkov o ocenah.</span>
        </div>
    <?php elseif (!empty($students) && count($students) > 1): ?>
        <div class="card shadow">
            <div class="card__content text-center p-xl">
                <div class="alert status-info d-flex items-center justify-center gap-sm mb-lg">
                    <span>Prosimo, izberite otroka iz spustnega menija zgoraj za ogled njegovih ocen.</span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow">
            <div class="card__content text-center p-xl">
                <div class="alert status-warning d-flex items-center justify-center gap-sm mb-lg">
                    <span>Trenutno nimate otrok, povezanih z vašim računom.</span>
                </div>
                <p class="text-secondary">Prosimo, obrnite se na šolskega administratorja, če menite, da gre za
                    napako.</p>
            </div>
        </div>
    <?php endif; ?>
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

                // If the button has additional data attributes, process them
                // Example: data-id, data-name, etc.
                const dataId = this.dataset.id;
                const dataName = this.dataset.name;
                const dataPoints = this.dataset.points;
                const dataMaxPoints = this.dataset.maxPoints;
                const dataPercentage = this.dataset.percentage;
                const dataWeight = this.dataset.weight;
                const dataComment = this.dataset.comment;

                if (dataId) {
                    const idField = document.getElementById(`${modalId}_id`);
                    if (idField) idField.value = dataId;
                }

                if (dataName) {
                    const nameField = document.getElementById(`${modalId}_name`);
                    if (nameField) nameField.textContent = dataName;
                }

                if (dataPoints) {
                    const pointsField = document.getElementById(`${modalId}_points`);
                    if (pointsField) pointsField.textContent = dataPoints;
                }

                if (dataMaxPoints) {
                    const maxPointsField = document.getElementById(`${modalId}_maxPoints`);
                    if (maxPointsField) maxPointsField.textContent = dataMaxPoints;
                }

                if (dataPercentage) {
                    const percentageField = document.getElementById(`${modalId}_percentage`);
                    if (percentageField) percentageField.textContent = dataPercentage;
                }

                if (dataWeight) {
                    const weightField = document.getElementById(`${modalId}_weight`);
                    if (weightField) weightField.textContent = dataWeight;
                }

                if (dataComment) {
                    const commentField = document.getElementById(`${modalId}_comment`);
                    if (commentField) commentField.innerHTML = dataComment;
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

        // Add click event for grade rows to show details in modal
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
            row.addEventListener('click', function () {
                // Extract data from row
                const cells = this.querySelectorAll('td');
                if (cells.length < 6) return;

                const name = cells[0].textContent;
                const points = cells[1].textContent;
                const maxPoints = cells[2].textContent;
                const percentage = cells[3].textContent;
                const weight = cells[4].textContent;
                const comment = cells[5].innerHTML;

                // Set modal data
                document.getElementById('gradeDetailsModal_name').textContent = name;
                document.getElementById('gradeDetailsModal_points').textContent = points;
                document.getElementById('gradeDetailsModal_maxPoints').textContent = maxPoints;
                document.getElementById('gradeDetailsModal_percentage').textContent = percentage;
                document.getElementById('gradeDetailsModal_weight').textContent = weight;
                document.getElementById('gradeDetailsModal_comment').innerHTML = comment;

                // Open modal
                openModal('gradeDetailsModal');
            });
        });
    });
</script>

<?php
include '../includes/footer.php';
?>
