<?php
/**
 * Admin Subject Management
 * /uwuweb/admin/manage_subjects.php
 *
 * Provides functionality for administrators to manage subjects
 *
 */

declare(strict_types=1);

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'admin_functions.php';

// Process form submissions before showing header to allow for redirects
$message = '';
$messageType = '';
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse($e->getMessage());
}

// Handle create/update subject
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna seja. Poskusite znova.';
    $messageType = 'error';
} else {
    // Create new subject
    if (isset($_POST['create_subject'])) {
        $subjectData = [
            'name' => $_POST['subject_name'] ?? ''
        ];

        $result = createSubject($subjectData);

        if ($result) {
            $message = 'Predmet je bil uspešno ustvarjen.';
            $messageType = 'success';
        } else {
            $message = 'Napaka pri ustvarjanju predmeta.';
            $messageType = 'error';
        }
    }

    // Update existing subject
    if (isset($_POST['update_subject'], $_POST['subject_id'])) {
        $subjectId = (int)$_POST['subject_id'];
        $subjectData = [
            'name' => $_POST['subject_name'] ?? ''
        ];

        $result = updateSubject($subjectId, $subjectData);

        if ($result) {
            $message = 'Predmet je bil uspešno posodobljen.';
            $messageType = 'success';
        } else {
            $message = 'Napaka pri posodabljanju predmeta.';
            $messageType = 'error';
        }
    }

    // Delete subject
    if (isset($_POST['delete_subject'], $_POST['subject_id'])) {
        $subjectId = (int)$_POST['subject_id'];

        $result = deleteSubject($subjectId);

        if ($result) {
            $message = 'Predmet je bil uspešno izbrisan.';
            $messageType = 'success';
        } else {
            $message = 'Predmeta ni mogoče izbrisati, ker je v uporabi.';
            $messageType = 'error';
        }
    }
}

// Get subject details for editing
$editSubject = null;
if (isset($_GET['subject_id'])) {
    $subjectId = (int)$_GET['subject_id'];
    $editSubject = getSubjectDetails($subjectId);
}

// Get all subjects
$subjects = getAllSubjects();

// Add class count and teacher count for each subject
foreach ($subjects as &$subject) {
    $classCount = 0;
    $teacherCount = 0;
    $teacherIds = [];

    // Check if we have class information
    if (isset($subject['classes']) && is_array($subject['classes'])) {
        $classCount = count($subject['classes']);

        // Count unique teachers
        foreach ($subject['classes'] as $class) if (isset($class['teacher_id']) && !in_array($class['teacher_id'], $teacherIds, true)) {
            $teacherIds[] = $class['teacher_id'];
            $teacherCount++;
        }
    } else {
        // If we don't have class information, get it from getSubjectDetails
        $subjectDetails = getSubjectDetails($subject['subject_id']);
        if ($subjectDetails && isset($subjectDetails['classes'])) {
            $classCount = count($subjectDetails['classes']);

            // Count unique teachers
            foreach ($subjectDetails['classes'] as $class) if (isset($class['teacher_id']) && !in_array($class['teacher_id'], $teacherIds, true)) {
                $teacherIds[] = $class['teacher_id'];
                $teacherCount++;
            }
        }
    }

    $subject['class_count'] = $classCount;
    $subject['teacher_count'] = $teacherCount;
}

require_once '../includes/header.php';

requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

?>

<div class="container mt-lg">
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">Upravljanje Predmetov</h1>
                <p class="text-secondary mt-0 mb-0">Dodajanje, urejanje, in brisanje predmetov.</p>
            </div>
            <div class="role-badge role-admin">Administrator</div>
        </div>
    </div>

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
            <h2 class="text-lg font-medium mt-0 mb-0">Seznam predmetov</h2>
            <button class="btn btn-primary btn-sm d-flex items-center gap-xs" data-open-modal="createSubjectModal">
                <span class="text-lg">+</span> Ustvari Nov Predmet
            </button>
        </div>

        <div class="card__content p-md">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Ime</th>
                    <th class="text-center">Razredi</th>
                    <th class="text-center">Učitelji</th>
                    <th class="text-right">Dejanja</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="4" class="text-center p-lg">
                            <div class="alert status-info page-transition mb-0" role="status">
                                <div class="alert-icon">ℹ</div>
                                <div class="alert-content">
                                    Ni še ustvarjenih predmetov. Uporabite gumb zgoraj za dodajanje.
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><?= htmlspecialchars($subject['name']) ?></td>
                            <td class="text-center"><?= $subject['class_count'] ?? 0 ?></td>
                            <td class="text-center"><?= $subject['teacher_count'] ?? 0 ?></td>
                            <td>
                                <div class="d-flex justify-end gap-xs">
                                    <button class="btn btn-secondary btn-sm d-flex items-center gap-xs"
                                            data-open-modal="editSubjectModal"
                                            data-id="<?= $subject['subject_id'] ?>"
                                            data-name="<?= htmlspecialchars($subject['name'] ?? '') ?>">
                                        <span class="text-md">✎</span> Uredi
                                    </button>
                                    <button class="btn btn-secondary btn-sm d-flex items-center gap-xs"
                                            data-open-modal="deleteSubjectModal"
                                            data-id="<?= $subject['subject_id'] ?>"
                                            data-name="<?= htmlspecialchars($subject['name'] ?? '') ?>">
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

<!-- Create Subject Modal -->
<div class="modal" id="createSubjectModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="createSubjectModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="createSubjectModalTitle">Ustvari Nov Predmet</h3>
        </div>
        <form id="createSubjectForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_subject" value="1">

                <div class="form-group">
                    <label class="form-label" for="create_subject_name">Ime predmeta:</label>
                    <input type="text" id="create_subject_name" name="subject_name" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Ustvari</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal" id="editSubjectModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editSubjectModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editSubjectModalTitle">Uredi Predmet</h3>
        </div>
        <form id="editSubjectForm" method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_subject" value="1">
                <input type="hidden" id="editSubjectModal_id" name="subject_id" value="">

                <div class="form-group">
                    <label class="form-label" for="edit_subject_name">Ime predmeta:</label>
                    <input type="text" id="edit_subject_name" name="subject_name" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Shrani</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Subject Confirmation Modal -->
<div class="modal" id="deleteSubjectModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h3 class="modal-title">Potrditev izbrisa</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-warning mb-md">
                <p>Ali ste prepričani, da želite izbrisati predmet <strong id="deleteSubjectModal_name"></strong>?</p>
            </div>
            <div class="alert status-error font-bold">
                <p>Tega dejanja ni mogoče razveljaviti.</p>
            </div>
            <input type="hidden" id="deleteSubjectModal_id" value="">
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

                    // If it's the edit modal, also set the name field
                    if (modalId === 'editSubjectModal') {
                        const nameField = document.getElementById('edit_subject_name');
                        if (nameField) nameField.value = dataName;
                    }
                }
            });
        });

        // Confirm Delete Button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const subjectId = document.getElementById('deleteSubjectModal_id').value;

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
            actionInput.name = 'delete_subject';
            actionInput.value = '1';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'subject_id';
            idInput.value = subjectId;

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

        // Auto-open edit modal if subject_id is in URL
        <?php if (isset($_GET['subject_id']) && $editSubject): ?>
        // Fill form with subject data
        document.getElementById('editSubjectModal_id').value = '<?= $editSubject['subject_id'] ?>';
        document.getElementById('edit_subject_name').value = '<?= htmlspecialchars($editSubject['name']) ?>';
        openModal('editSubjectModal');
        <?php endif; ?>
    });
</script>

<?php
include '../includes/footer.php';
?>
