<?php
/**
 * Admin Class Management
 * /uwuweb/admin/manage_classes.php
 *
 * Provides functionality for administrators to manage classes (homeroom groups)
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
$classDetails = null;

// Load data
$classes = getAllClasses();
$teachers = getAllTeachers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
    $messageType = 'error';
    error_log("CSRF token validation failed for user ID: " . ($_SESSION['user_id'] ?? 'Unknown'));
} else {
    $action = array_key_first(array_intersect_key($_POST, [
        'create_class' => 1, 'update_class' => 1, 'delete_class' => 1,
    ]));

    switch ($action) {
        case 'create_class':
            $className = trim($_POST['class_name'] ?? '');
            $classCode = trim($_POST['class_code'] ?? '');
            $homeroomTeacherId = filter_input(INPUT_POST, 'homeroom_teacher_id', FILTER_VALIDATE_INT);

            if (empty($className) || empty($classCode) || !$homeroomTeacherId) {
                $message = 'Ime razreda, koda razreda in razrednik so obvezni.';
                $messageType = 'error';
            } elseif (createClass([
                'class_code' => $classCode,
                'title' => $className,
                'homeroom_teacher_id' => $homeroomTeacherId
            ])) {
                $message = 'Razred uspešno ustvarjen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri ustvarjanju razreda.';
                $messageType = 'error';
            }
            break;

        case 'update_class':
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $className = trim($_POST['class_name'] ?? '');
            $classCode = trim($_POST['class_code'] ?? '');
            $homeroomTeacherId = filter_input(INPUT_POST, 'homeroom_teacher_id', FILTER_VALIDATE_INT);

            if (!$classId || $classId <= 0) {
                $message = 'Neveljaven ID razreda.';
                $messageType = 'error';
            } elseif (empty($className) || empty($classCode) || !$homeroomTeacherId || $homeroomTeacherId <= 0) {
                $message = 'Vsa polja so obvezna.';
                $messageType = 'error';
            } elseif (updateClass($classId, [
                'class_code' => $classCode,
                'title' => $className,
                'homeroom_teacher_id' => $homeroomTeacherId
            ])) {
                $message = 'Razred uspešno posodobljen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri posodabljanju razreda.';
                $messageType = 'error';
            }
            break;

        case 'delete_class':
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            if (!$classId || $classId <= 0) {
                $message = 'Neveljaven ID razreda.';
                $messageType = 'error';
            } elseif (deleteClass($classId)) {
                $message = 'Razred uspešno izbrisan.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri brisanju razreda. Preverite, da razred ni povezan z drugimi podatki.';
                $messageType = 'error';
            }
            break;
    }

    // Reload data after changes
    $classes = getAllClasses();
}

if (isset($_GET['class_id'])) {
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    if ($classId && $classId > 0) $classDetails = getClassDetails($classId);
}

try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    $csrfToken = '';
    error_log("CSRF token generation failed in admin/manage_classes.php: " . $e->getMessage());
    $message = 'Generiranje varnostnega žetona ni uspelo. Poskusite znova kasneje.';
    $messageType = 'error';
}

?>

<div class="container mt-lg">
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">Upravljanje Razredov</h1>
                <p class="text-secondary mt-0 mb-0">Dodajanje, urejanje, in brisanje razredov v sistemu.</p>
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
            <h2 class="text-lg font-medium mt-0 mb-0">Seznam razredov</h2>
            <button class="btn btn-primary btn-sm d-flex items-center gap-xs" id="createClassBtn">
                <span class="text-lg">+</span> Ustvari Nov Razred
            </button>
        </div>

        <div class="card__content p-md">
            <table class="data-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Ime</th>
                    <th>Koda (Leto)</th>
                    <th>Razrednik</th>
                    <th class="text-center">Učenci</th>
                    <th class="text-center">Predmeti</th>
                    <th class="text-right">Dejanja</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($classes)): ?>
                    <tr>
                        <td colspan="7" class="text-center p-lg">
                            <div class="alert status-info mb-0">
                                Ni še ustvarjenih razredov. Uporabite gumb zgoraj za dodajanje.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classes as $class): ?>
                        <tr>
                            <td><?= $class['class_id'] ?></td>
                            <td><?= htmlspecialchars($class['title']) ?></td>
                            <td><?= htmlspecialchars($class['class_code']) ?></td>
                            <td><?= htmlspecialchars($class['homeroom_teacher_name'] ?? 'N/A') ?></td>
                            <td class="text-center"><?= $class['student_count'] ?? 0 ?></td>
                            <td class="text-center"><?= $class['subject_count'] ?? 0 ?></td>
                            <td>
                                <div class="d-flex justify-end gap-xs">
                                    <button class="btn btn-secondary btn-sm edit-class-btn d-flex items-center gap-xs"
                                            data-id="<?= $class['class_id'] ?>">
                                        <span class="text-md">✎</span> Uredi
                                    </button>
                                    <button class="btn btn-secondary btn-sm delete-class-btn d-flex items-center gap-xs"
                                            data-id="<?= $class['class_id'] ?>"
                                            data-name="<?= htmlspecialchars($class['title'] ?? '') ?>">
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

<!-- Create Class Modal -->
<div class="modal" id="createClassModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Ustvari Nov Razred</h3>
            <button class="btn-close" id="closeCreateClassModal" aria-label="Close modal">×</button>
        </div>
        <form id="createClassForm" method="POST" action="/uwuweb/admin/manage_classes.php">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_class" value="1">

                <div class="form-group mb-md">
                    <label class="form-label" for="create_class_name">Ime Razreda:</label>
                    <input type="text" id="create_class_name" name="class_name" class="form-input" required
                           placeholder="npr. 1.A, 2.B, 3.C">
                    <small class="text-secondary d-block mt-xs">Edinstveno ime za razred.</small>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="create_class_code">Koda Razreda:</label>
                    <input type="text" id="create_class_code" name="class_code" class="form-input" required
                           placeholder="npr. 2024/2025">
                    <small class="text-secondary d-block mt-xs">Običajno šolsko leto, uporabljeno kot koda.</small>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="create_homeroom_teacher_id">Razrednik:</label>
                    <select id="create_homeroom_teacher_id" name="homeroom_teacher_id" class="form-input form-select"
                            required>
                        <option value="">-- Izberi Učitelja --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username'] ?? 'Neznan Učitelj') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($teachers)): ?>
                        <small class="text-warning d-block mt-xs">Ni najdenih učiteljev. Najprej ustvarite
                            učitelje.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelCreateClassBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary d-flex items-center gap-xs" id="saveClassBtn">
                    <span class="text-lg">+</span> Ustvari Razred
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal" id="editClassModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Uredi Razred</h3>
            <button class="btn-close" id="closeEditClassModal" aria-label="Close modal">×</button>
        </div>
        <form id="editClassForm" method="POST" action="/uwuweb/admin/manage_classes.php">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_class" value="1">
                <input type="hidden" name="class_id" id="edit_class_id" value="">

                <div class="form-group mb-md">
                    <label class="form-label" for="edit_class_name">Ime Razreda:</label>
                    <input type="text" id="edit_class_name" name="class_name" class="form-input" required
                           placeholder="npr. 1.A, 2.B, 3.C">
                    <small class="text-secondary d-block mt-xs">Edinstveno ime za razred.</small>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="edit_class_code">Koda Razreda:</label>
                    <input type="text" id="edit_class_code" name="class_code" class="form-input" required
                           placeholder="npr. 2024/2025">
                    <small class="text-secondary d-block mt-xs">Običajno šolsko leto, uporabljeno kot koda.</small>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="edit_homeroom_teacher_id">Razrednik:</label>
                    <select id="edit_homeroom_teacher_id" name="homeroom_teacher_id" class="form-input form-select"
                            required>
                        <option value="">-- Izberi Učitelja --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username'] ?? 'Neznan Učitelj') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelEditClassBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary">Posodobi Razred</button>
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
                modal.classList.add('open');
                const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable) {
                    focusable.focus();
                }
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('open');
            }
        }

        // Open create class modal
        const createClassBtn = document.querySelector('.btn-primary[id="createClassBtn"]');
        if (createClassBtn) {
            createClassBtn.addEventListener('click', function () {
                openModal('createClassModal');
            });
        }

        // Close modals
        document.getElementById('closeCreateClassModal').addEventListener('click', function () {
            closeModal('createClassModal');
        });
        document.getElementById('closeEditClassModal').addEventListener('click', function () {
            closeModal('editClassModal');
        });
        document.getElementById('cancelCreateClassBtn').addEventListener('click', function () {
            closeModal('createClassModal');
        });
        document.getElementById('cancelEditClassBtn').addEventListener('click', function () {
            closeModal('editClassModal');
        });

        // Close modals when clicking on overlay
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    const modal = e.target.closest('.modal');
                    if (modal) {
                        modal.classList.remove('open');
                    }
                }
            });
        });

        // Handle edit class button clicks
        document.querySelectorAll('.edit-class-btn').forEach(button => {
            button.addEventListener('click', function () {
                const classId = this.dataset.id;
                if (!classId) return;

                // Find class in the classes array
                const classes = <?php
                    try {
                        echo json_encode($classes, JSON_THROW_ON_ERROR);
                    } catch (JsonException $e) {
                        echo '[]';
                    }
                    ?>;
                if (!classes) return;

                const classInfo = classes.find(c => parseInt(c.class_id, 10) === parseInt(classId, 10));

                if (classInfo) {
                    document.getElementById('edit_class_id').value = classInfo.class_id;
                    document.getElementById('edit_class_name').value = classInfo.title || '';
                    document.getElementById('edit_class_code').value = classInfo.class_code || '';

                    const teacherSelect = document.getElementById('edit_homeroom_teacher_id');
                    if (teacherSelect && 'options' in teacherSelect && classInfo.homeroom_teacher_id) {
                        const teacherId = classInfo.homeroom_teacher_id.toString();
                        const options = Array.from(teacherSelect.options || []);

                        const matchingOption = options.find(option => option.value === teacherId);
                        if (matchingOption) {
                            matchingOption.selected = true;
                        }
                    }

                    openModal('editClassModal');
                }
            });
        });

        // Handle delete class button clicks
        document.querySelectorAll('.delete-class-btn').forEach(button => {
            button.addEventListener('click', function () {
                const classId = this.dataset.id;
                const className = this.dataset.name;

                if (confirm(`Ali ste prepričani, da želite izbrisati razred "${className}"? Tega dejanja ni mogoče razveljaviti.`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/uwuweb/admin/manage_classes.php';

                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = csrfTokenValue;
                    form.appendChild(csrfInput);

                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_class';
                    deleteInput.value = '1';
                    form.appendChild(deleteInput);

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'class_id';
                    idInput.value = classId;
                    form.appendChild(idInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
    document.addEventListener('DOMContentLoaded', function () {
        const csrfTokenValue = '<?= htmlspecialchars($csrfToken) ?>';

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('show');
                document.body.classList.add('modal-open');
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

        function handleEditClass(classId) {
            // Fetch class details and populate form
            fetch(`/uwuweb/admin/manage_classes.php?class_id=${classId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Napaka pri pridobivanju podatkov o razredu.');
                    }
                    return response.text();
                })
                .then(html => {
                    // Use the raw response to set modal content
                    const parser = new DOMParser();
                    parser.parseFromString(html, 'text/html');

                    // Find class details and populate edit form
                    const classDetails = <?= json_encode($classDetails ?? [], JSON_THROW_ON_ERROR) ?>;

                    if (classDetails && classDetails.class_id === classId) {
                        document.getElementById('edit_class_id').value = classDetails.class_id;
                        document.getElementById('edit_class_name').value = classDetails.name;
                        document.getElementById('edit_class_code').value = classDetails.code;
                        document.getElementById('edit_homeroom_teacher_id').value = classDetails.homeroom_teacher_id;

                        // Open the modal
                        openModal('editClassModal');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message);
                });
        }

        // Expose the function to global scope
        window.handleEditClass = handleEditClass;

        // Handle delete buttons
        document.querySelectorAll('[data-delete="class"]').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');

                if (confirm(`Ali ste prepričani, da želite izbrisati razred "${name}"? Ta operacija je dokončna.`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/uwuweb/admin/manage_classes.php';

                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = csrfTokenValue;
                    form.appendChild(csrfInput);

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'class_id';
                    idInput.value = id;
                    form.appendChild(idInput);

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_class';
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
