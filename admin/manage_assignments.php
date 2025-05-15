<?php
/**
 * Admin Class-Subject Assignment Management
 * /uwuweb/admin/manage_assignments.php
 *
 * Provides functionality for administrators to manage class-subject assignments,
 * linking classes, subjects, and teachers together.
 *
 */

declare(strict_types=1);

use Random\RandomException;

require_once 'admin_functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

requireRole(ROLE_ADMIN);

// Load data
$classes = getAllClasses();
$subjects = getAllSubjects();
$teachers = getAllTeachers();
$classSubjects = getAllClassSubjectAssignments();

// Process form submissions before showing header to allow for redirects
$message = '';
$messageType = '';
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse($e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna seja. Poskusite znova.';
    $messageType = 'error';
} else {
    // Create new assignment
    if (isset($_POST['create_assignment'])) {
        $assignmentData = [
            'class_id' => (int)($_POST['class_id'] ?? 0),
            'subject_id' => (int)($_POST['subject_id'] ?? 0),
            'teacher_id' => (int)($_POST['teacher_id'] ?? 0)
        ];

        $result = assignSubjectToClass($assignmentData);

        if ($result) {
            $message = 'Povezava je bila uspešno ustvarjena.';
            $messageType = 'success';
        } else {
            $message = 'Napaka pri ustvarjanju povezave.';
            $messageType = 'error';
        }
    }

    // Update existing assignment
    if (isset($_POST['update_assignment'], $_POST['assignment_id'])) {
        $assignmentId = (int)$_POST['assignment_id'];
        $assignmentData = [
            'teacher_id' => (int)($_POST['teacher_id'] ?? 0)
        ];

        $result = updateClassSubjectAssignment($assignmentId, $assignmentData);

        if ($result) {
            $message = 'Povezava je bila uspešno posodobljena.';
            $messageType = 'success';
        } else {
            $message = 'Napaka pri posodabljanju povezave.';
            $messageType = 'error';
        }
    }

    // Delete assignment
    if (isset($_POST['delete_assignment'], $_POST['assignment_id'])) {
        $assignmentId = (int)$_POST['assignment_id'];

        $result = removeSubjectFromClass($assignmentId);

        if ($result) {
            $message = 'Povezava je bila uspešno izbrisana.';
            $messageType = 'success';
        } else {
            $message = 'Napaka pri brisanju povezave.';
            $messageType = 'error';
        }
    }
}

// Get assignment details for editing
$editAssignment = null;
if (isset($_GET['assignment_id'])) {
    $assignmentId = (int)$_GET['assignment_id'];
    // Find the assignment in the list
    foreach ($classSubjects as $assignment) if ($assignment['class_subject_id'] == $assignmentId) {
        $editAssignment = $assignment;
        break;
    }
}

$pdo = getDBConnection();

?>

<div class="container mt-lg">
    <?php renderHeaderCard(
        'Upravljanje Povezav Razredov in Predmetov',
        'Dodeljevanje predmetov razredom in učiteljem.',
        'admin'
    ); ?>

    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType === 'success' ? 'success' : 'error' ?> page-transition mb-lg"
             role="alert" aria-live="polite">
            <div class="alert-icon">
                <?= $messageType === 'success' ? '✓' : '⚠' ?>
            </div>
            <div class="alert-content">
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow rounded-lg mb-xl">
        <div class="d-flex justify-between items-center p-md"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h2 class="text-lg font-medium mt-0 mb-0">Seznam povezav</h2>
            <button class="btn btn-primary btn-sm d-flex items-center gap-xs" data-open-modal="createAssignmentModal">
                <span class="text-lg">+</span> Ustvari Novo Povezavo
            </button>
        </div>

        <div class="card__content p-md">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Razred</th>
                    <th>Predmet</th>
                    <th>Učitelj</th>
                    <th class="text-right">Dejanja</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($classSubjects)): ?>
                    <tr>
                        <td colspan="4" class="text-center p-lg">
                            <div class="alert status-info page-transition mb-0" role="status">
                                <div class="alert-icon">ℹ</div>
                                <div class="alert-content">
                                    Ni še ustvarjenih povezav. Uporabite gumb zgoraj za dodajanje.
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classSubjects as $assignment): ?>
                        <tr>
                            <td><?= htmlspecialchars($assignment['class_code'] . ' - ' . $assignment['class_title']) ?></td>
                            <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                            <td><?= htmlspecialchars($assignment['teacher_name'] ?? 'Nedodeljen') ?></td>
                            <td>
                                <div class="d-flex justify-end gap-xs">
                                    <button class="btn btn-secondary btn-sm d-flex items-center gap-xs"
                                            data-open-modal="editAssignmentModal"
                                            data-id="<?= $assignment['class_subject_id'] ?>"
                                            data-class="<?= htmlspecialchars($assignment['class_code'] . ' - ' . $assignment['class_title']) ?>"
                                            data-subject="<?= htmlspecialchars($assignment['subject_name']) ?>"
                                            data-teacher="<?= $assignment['teacher_id'] ?? '' ?>">
                                        <span class="text-md">✎</span> Uredi
                                    </button>
                                    <button class="btn btn-secondary btn-sm d-flex items-center gap-xs"
                                            data-open-modal="deleteAssignmentModal"
                                            data-id="<?= $assignment['class_subject_id'] ?>"
                                            data-info="<?= htmlspecialchars($assignment['class_code'] . ' - ' . $assignment['subject_name']) ?>">
                                        <span class="text-md">🗑</span> Izbriši
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Assignment Modal -->
<div class="modal" id="createAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="createAssignmentModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="createAssignmentModalTitle">Ustvari Novo Povezavo</h3>
        </div>
        <form id="createAssignmentForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_assignment" value="1">

                <div class="form-group">
                    <label class="form-label" for="create_class_id">Razred:</label>
                    <select id="create_class_id" name="class_id" class="form-select" required>
                        <option value="">Izberite razred</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>">
                                <?= htmlspecialchars($class['class_code'] . ' - ' . $class['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_subject_id">Predmet:</label>
                    <select id="create_subject_id" name="subject_id" class="form-select" required>
                        <option value="">Izberite predmet</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>">
                                <?= htmlspecialchars($subject['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_teacher_id">Učitelj:</label>
                    <select id="create_teacher_id" name="teacher_id" class="form-select" required>
                        <option value="">Izberite učitelja</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name'] . ' (' . $teacher['username'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Ustvari</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal" id="editAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editAssignmentModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editAssignmentModalTitle">Uredi Povezavo</h3>
        </div>
        <form id="editAssignmentForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_assignment" value="1">
                <input type="hidden" id="editAssignmentModal_id" name="assignment_id" value="">

                <div class="form-group">
                    <label class="form-label">Razred:</label>
                    <div id="edit_class_display" class="form-static-text"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Predmet:</label>
                    <div id="edit_subject_display" class="form-static-text"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_teacher_id">Učitelj:</label>
                    <select id="edit_teacher_id" name="teacher_id" class="form-select" required>
                        <option value="">Izberite učitelja</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Shrani</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Assignment Confirmation Modal -->
<div class="modal" id="deleteAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h3 class="modal-title">Potrditev izbrisa</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-warning mb-md">
                <p>Ali ste prepričani, da želite izbrisati povezavo <strong id="deleteAssignmentModal_info"></strong>?
                </p>
            </div>
            <div class="alert status-error font-bold">
                <p>Tega dejanja ni mogoče razveljaviti.</p>
            </div>
            <input type="hidden" id="deleteAssignmentModal_id" value="">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
            <button type="button" class="btn btn-error" id="confirmDeleteBtn">Izbriši</button>
        </div>
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

        // Open modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;
                openModal(modalId);

                // If the button has additional data attributes, process them
                const dataId = this.dataset.id;
                const dataClass = this.dataset.class;
                const dataSubject = this.dataset.subject;
                const dataTeacher = this.dataset.teacher;
                const dataInfo = this.dataset.info;

                if (dataId) {
                    // Handle ID data (e.g., fill hidden form field)
                    const idField = document.getElementById(`${modalId}_id`);
                    if (idField) idField.value = dataId;
                }

                if (dataInfo) {
                    // Handle info data for confirmation modals
                    const infoDisplay = document.getElementById(`${modalId}_info`);
                    if (infoDisplay) infoDisplay.textContent = dataInfo;
                }

                // For edit modal, populate fields
                if (modalId === 'editAssignmentModal') {
                    const classDisplay = document.getElementById('edit_class_display');
                    const subjectDisplay = document.getElementById('edit_subject_display');
                    const teacherSelect = document.getElementById('edit_teacher_id');

                    if (classDisplay && dataClass) classDisplay.textContent = dataClass;
                    if (subjectDisplay && dataSubject) subjectDisplay.textContent = dataSubject;
                    if (teacherSelect && dataTeacher) teacherSelect.value = dataTeacher;
                }
            });
        });

        // Confirm Delete Button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const assignmentId = document.getElementById('deleteAssignmentModal_id').value;

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_assignment';
            actionInput.value = '1';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'assignment_id';
            idInput.value = assignmentId;

            form.appendChild(csrfInput);
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
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

        // Auto-open edit modal if assignment_id is in URL
        <?php if (isset($_GET['assignment_id']) && $editAssignment): ?>
        // Fill form with assignment data
        document.getElementById('editAssignmentModal_id').value = '<?= $editAssignment['class_subject_id'] ?>';
        document.getElementById('edit_class_display').textContent = '<?= htmlspecialchars($editAssignment['class_code'] . ' - ' . $editAssignment['class_title']) ?>';
        document.getElementById('edit_subject_display').textContent = '<?= htmlspecialchars($editAssignment['subject_name']) ?>';
        document.getElementById('edit_teacher_id').value = '<?= $editAssignment['teacher_id'] ?>';
        openModal('editAssignmentModal');
        <?php endif; ?>
    });
</script>

<?php
include '../includes/footer.php';
?>
