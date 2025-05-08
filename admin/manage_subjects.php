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
require_once '../includes/header.php';

requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

$message = '';
$messageType = '';
$subjectDetails = null;

// Load data
$subjects = getAllSubjects();

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
    $messageType = 'error';
    error_log("CSRF token validation failed for user ID: " . ($_SESSION['user_id'] ?? 'Unknown'));
} else {
    $action = array_key_first(array_intersect_key($_POST, [
        'create_subject' => 1, 'update_subject' => 1, 'delete_subject' => 1,
    ]));

    switch ($action) {
        case 'create_subject':
            $subjectName = trim($_POST['subject_name'] ?? '');
            if (empty($subjectName)) {
                $message = 'Ime predmeta je obvezno.';
                $messageType = 'error';
            } elseif (createSubject(['name' => $subjectName])) {
                $message = 'Predmet uspešno ustvarjen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri ustvarjanju predmeta.';
                $messageType = 'error';
            }
            break;

        case 'update_subject':
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            $subjectName = trim($_POST['subject_name'] ?? '');

            if (!$subjectId || $subjectId <= 0) {
                $message = 'Neveljaven ID predmeta.';
                $messageType = 'error';
            } elseif (empty($subjectName)) {
                $message = 'Ime predmeta je obvezno.';
                $messageType = 'error';
            } elseif (updateSubject($subjectId, ['name' => $subjectName])) {
                $message = 'Predmet uspešno posodobljen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri posodabljanju predmeta.';
                $messageType = 'error';
            }
            break;

        case 'delete_subject':
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            if (!$subjectId || $subjectId <= 0) {
                $message = 'Neveljaven ID predmeta.';
                $messageType = 'error';
            } elseif (deleteSubject($subjectId)) {
                $message = 'Predmet uspešno izbrisan.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri brisanju predmeta. Preverite, da predmet ni povezan z razredi.';
                $messageType = 'error';
            }
            break;
    }

    // Reload data after changes
    $subjects = getAllSubjects();
}

if (isset($_GET['subject_id'])) {
    $subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
    if ($subjectId && $subjectId > 0) $subjectDetails = getSubjectDetails($subjectId);
}

try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    $csrfToken = '';
    error_log("CSRF token generation failed in admin/manage_subjects.php: " . $e->getMessage());
    $message = 'Generiranje varnostnega žetona ni uspelo. Poskusite znova kasneje.';
    $messageType = 'error';
}

?>

<div class="container mt-lg">
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">Upravljanje Predmetov</h1>
                <p class="text-secondary mt-0 mb-0">Dodajanje, urejanje, in brisanje predmetov v sistemu.</p>
            </div>
            <div class="role-badge role-admin">Administrator</div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType === 'success' ? 'success' : 'error' ?> mb-lg d-flex items-center gap-sm">
             <span class="alert-icon text-lg">
                <?= $messageType === 'success' ? '✓' : '⚠' ?>
            </span>
            <div class="alert-content">
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow rounded-lg mb-xl">
        <div class="d-flex justify-between items-center p-md"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h2 class="text-lg font-medium mt-0 mb-0">Seznam predmetov</h2>
            <button class="btn btn-primary btn-sm d-flex items-center gap-xs" id="createSubjectBtn">
                <span class="text-lg">+</span> Ustvari Nov Predmet
            </button>
        </div>

        <div class="card__content p-md">
            <table class="data-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Ime</th>
                    <th class="text-center">Razredi</th>
                    <th class="text-center">Učitelji</th>
                    <th class="text-right">Dejanja</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="5" class="text-center p-lg">
                            <div class="alert status-info mb-0">
                                Ni še ustvarjenih predmetov. Uporabite gumb zgoraj za dodajanje.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><?= $subject['subject_id'] ?></td>
                            <td><?= htmlspecialchars($subject['name']) ?></td>
                            <td class="text-center"><?= $subject['class_count'] ?? 0 ?></td>
                            <td class="text-center"><?= $subject['teacher_count'] ?? 0 ?></td>
                            <td>
                                <div class="d-flex justify-end gap-xs">
                                    <a href="/uwuweb/admin/manage_subjects.php?subject_id=<?= $subject['subject_id'] ?>"
                                       class="btn btn-secondary btn-sm d-flex items-center gap-xs">
                                        <span class="text-md">✎</span> Uredi
                                    </a>
                                    <button class="btn btn-secondary btn-sm delete-subject-btn d-flex items-center gap-xs"
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
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Ustvari Nov Predmet</h3>
            <button class="btn-close" id="closeCreateSubjectModal" aria-label="Close modal">×</button>
        </div>
        <form id="createSubjectForm" method="POST" action="/uwuweb/admin/manage_subjects.php">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_subject" value="1">

                <div class="form-group mb-0">
                    <label class="form-label" for="create_subject_name">Ime Predmeta:</label>
                    <input type="text" id="create_subject_name" name="subject_name" class="form-input" required
                           placeholder="npr. Matematika, Fizika">
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelCreateSubjectBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary d-flex items-center gap-xs" id="saveSubjectBtn">
                    <span class="text-lg">+</span> Ustvari Predmet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal" id="editSubjectModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Uredi Predmet</h3>
            <button class="btn-close" id="closeEditSubjectModal" aria-label="Close modal">×</button>
        </div>
        <form id="editSubjectForm" method="POST" action="/uwuweb/admin/manage_subjects.php">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_subject" value="1">
                <input type="hidden" name="subject_id" id="edit_subject_id"
                       value="<?= $subjectDetails['subject_id'] ?? '' ?>">

                <div class="form-group mb-0">
                    <label class="form-label" for="edit_subject_name">Ime Predmeta:</label>
                    <input type="text" id="edit_subject_name" name="subject_name" class="form-input" required
                           value="<?= htmlspecialchars($subjectDetails['name'] ?? '') ?>"
                           placeholder="npr. Matematika, Fizika">
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelEditSubjectBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary">Posodobi Predmet</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfTokenValue = '<?= htmlspecialchars($csrfToken) ?>';

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.classList.add('modal-open');
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
            }
        }

        const modalTriggers = document.querySelectorAll('[data-modal-target]');
        modalTriggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                const modal = trigger.getAttribute('data-modal-target');
                openModal(modal);
            });
        });

        document.querySelectorAll('.modal-close, .modal-overlay').forEach(closer => {
            closer.addEventListener('click', (event) => {
                const modal = event.target.closest('.modal');
                if (modal) {
                    modal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                }
            });
        });

        // Handle edit buttons
        document.querySelectorAll('[data-edit="subject"]').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');

                document.getElementById('edit_subject_id').value = id;
                document.getElementById('edit_subject_name').value = name;

                openModal('editSubjectModal');
            });
        });

        // Handle delete buttons
        document.querySelectorAll('[data-delete="subject"]').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');

                if (confirm(`Ali ste prepričani, da želite izbrisati predmet "${name}"? Ta operacija je dokončna.`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/uwuweb/admin/manage_subjects.php';

                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = csrfTokenValue;
                    form.appendChild(csrfInput);

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'subject_id';
                    idInput.value = id;
                    form.appendChild(idInput);

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_subject';
                    actionInput.value = '1';
                    form.appendChild(actionInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
</script>

<?php
include '../includes/footer.php';
?>
