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

// Process form submissions
$message = '';
$messageType = '';
$classDetails = null;

// Get all teachers for the dropdown
$teachers = getAllTeachers();

// Get all existing classes
$classes = getAllClasses();

// Process form submissions before including header
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Napaka pri potrditvi zahteve. Poskusite znova.';
    $messageType = 'error';
} else if (isset($_POST['create_class'])) {
    $classData = [
        'class_code' => $_POST['class_code'] ?? '',
        'title' => $_POST['title'] ?? '',
        'homeroom_teacher_id' => $_POST['homeroom_teacher_id'] ?? ''
    ];

    $result = createClass($classData);
    if ($result) {
        $message = 'Razred je bil uspešno ustvarjen.';
        $messageType = 'success';
        // Refresh classes list
        $classes = getAllClasses();
    } else {
        $message = 'Napaka pri ustvarjanju razreda. Preverite podatke in poskusite znova.';
        $messageType = 'error';
    }
} // Update existing class
elseif (isset($_POST['update_class'], $_POST['class_id'])) {
    $classId = (int)$_POST['class_id'];
    $classData = [
        'class_code' => $_POST['class_code'] ?? '',
        'title' => $_POST['title'] ?? '',
        'homeroom_teacher_id' => $_POST['homeroom_teacher_id'] ?? ''
    ];

    $result = updateClass($classId, $classData);
    if ($result) {
        $message = 'Razred je bil uspešno posodobljen.';
        $messageType = 'success';
        // Refresh classes list
        $classes = getAllClasses();
    } else {
        $message = 'Napaka pri posodabljanju razreda. Preverite podatke in poskusite znova.';
        $messageType = 'error';
    }
} // Delete class
elseif (isset($_POST['delete_class'], $_POST['class_id'])) {
    $classId = (int)$_POST['class_id'];

    $result = deleteClass($classId);
    if ($result) {
        $message = 'Razred je bil uspešno izbrisan.';
        $messageType = 'success';
        // Refresh classes list
        $classes = getAllClasses();
    } else {
        $message = 'Napaka pri brisanju razreda. Razred ne more biti izbrisan, če ima dodeljene učence ali predmete.';
        $messageType = 'error';
    }
}

// Generate CSRF token for forms
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse('Napaka pri generiranju CSRF žetona.', 500);
}

require_once '../includes/header.php';

requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

?>

<div class="container mt-lg">
    <?php renderHeaderCard(
        'Upravljanje Razredov',
        'Dodajanje, urejanje, in brisanje razredov v sistemu.',
        'admin'
    ); ?>

    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType === 'success' ? 'success' : 'error' ?> page-transition mb-lg"
             role="alert" aria-live="polite">
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
            <?php displayClassesList(); ?>
        </div>
    </div>
</div>

<!-- Modals -->

<!-- Create Class Modal -->
<div class="modal" id="createClassModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="createClassModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="createClassModalTitle">Ustvari nov razred</h3>
        </div>
        <form id="createClassForm" method="POST" action="manage_classes.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_class" value="1">

                <div class="form-group">
                    <label for="create_class_code" class="form-label">Koda razreda:</label>
                    <input type="text" id="create_class_code" name="class_code" class="form-input" required
                           placeholder="Npr. 4.RA, 3.RB">
                    <div class="feedback-error" id="create_class_code_error"></div>
                </div>

                <div class="form-group">
                    <label for="create_title" class="form-label">Ime razreda:</label>
                    <input type="text" id="create_title" name="title" class="form-input" required
                           placeholder="Npr. 4. razred A">
                    <div class="feedback-error" id="create_title_error"></div>
                </div>

                <div class="form-group">
                    <label for="create_homeroom_teacher_id" class="form-label">Razrednik:</label>
                    <select id="create_homeroom_teacher_id" name="homeroom_teacher_id" class="form-select" required>
                        <option value="">-- Izberite razrednika --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="feedback-error" id="create_homeroom_teacher_error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Ustvari razred</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal" id="editClassModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editClassModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editClassModalTitle">Uredi razred</h3>
        </div>
        <form id="editClassForm" method="POST" action="manage_classes.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_class" value="1">
                <input type="hidden" id="edit_class_id" name="class_id" value="">

                <div class="form-group">
                    <label for="edit_class_code" class="form-label">Koda razreda:</label>
                    <input type="text" id="edit_class_code" name="class_code" class="form-input" required>
                    <div class="feedback-error" id="edit_class_code_error"></div>
                </div>

                <div class="form-group">
                    <label for="edit_title" class="form-label">Ime razreda:</label>
                    <input type="text" id="edit_title" name="title" class="form-input" required>
                    <div class="feedback-error" id="edit_title_error"></div>
                </div>

                <div class="form-group">
                    <label for="edit_homeroom_teacher_id" class="form-label">Razrednik:</label>
                    <select id="edit_homeroom_teacher_id" name="homeroom_teacher_id" class="form-select" required>
                        <option value="">-- Izberite razrednika --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>">
                                <?= htmlspecialchars($teacher['username']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="feedback-error" id="edit_homeroom_teacher_error"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Shrani spremembe</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Class Confirmation Modal -->
<div class="modal" id="deleteClassModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="deleteClassModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteClassModalTitle">Potrditev izbrisa</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-warning mb-md">
                <p>Ali ste prepričani, da želite izbrisati razred <strong id="deleteClassModal_name"></strong>?</p>
            </div>
            <div class="alert status-error font-bold">
                <p>Tega dejanja ni mogoče razveljaviti. </p>
            </div>
            <input type="hidden" id="deleteClassModal_id" value="">
        </div>
        <p class="text-disabled ml-xl mr-xl">Razred bo mogoče izbrisati le, če nima dodeljenih učencev ali
            predmetov.</p>
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

        // --- Event Listeners ---

        // Create Class Button
        document.getElementById('createClassBtn').addEventListener('click', function () {
            openModal('createClassModal');
        });

        // Edit Class Buttons
        document.querySelectorAll('.edit-class-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const classId = this.getAttribute('data-id');

                // Make an AJAX request to get class details
                fetch(`../api/admin.php?action=getClassDetails&id=${classId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Fill the form with class data
                            document.getElementById('edit_class_id').value = classId;
                            document.getElementById('edit_class_code').value = data.class.class_code;
                            document.getElementById('edit_title').value = data.class.title;
                            document.getElementById('edit_homeroom_teacher_id').value = data.class.homeroom_teacher_id;

                            // Open the modal
                            openModal('editClassModal');
                        } else {
                            alert('Napaka pri pridobivanju podatkov o razredu.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);

                        // Fallback to synchronous loading if API fails
                        const row = this.closest('tr');
                        if (row) {
                            document.getElementById('edit_class_id').value = classId;
                            document.getElementById('edit_class_code').value = row.cells[1].textContent.trim();
                            document.getElementById('edit_title').value = row.cells[0].textContent.trim();

                            // For teacher, we need to find by name, which is less reliable
                            const teacherName = row.cells[2].textContent.trim();
                            const teacherSelect = document.getElementById('edit_homeroom_teacher_id');

                            for (let i = 0; i < teacherSelect.options.length; i++) {
                                if (teacherSelect.options[i].text === teacherName) {
                                    teacherSelect.selectedIndex = i;
                                    break;
                                }
                            }

                            openModal('editClassModal');
                        }
                    });
            });
        });

        // Delete Class Buttons
        document.querySelectorAll('.delete-class-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const classId = this.getAttribute('data-id');
                const className = this.getAttribute('data-name');

                document.getElementById('deleteClassModal_id').value = classId;
                document.getElementById('deleteClassModal_name').textContent = className;

                openModal('deleteClassModal');
            });
        });

        // Confirm Delete Button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const classId = document.getElementById('deleteClassModal_id').value;

            // Create and submit the form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_classes.php';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_class';
            actionInput.value = '1';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'class_id';
            idInput.value = classId;

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
    });
</script>

<?php
include '../includes/footer.php';
?>
