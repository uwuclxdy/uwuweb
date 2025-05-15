<?php
/**
 * Purpose: Modal Examples Page
 * Description: Provides examples of various modal implementations for uwuweb
 * Path: /uwuweb/examples/modal-examples.php
 */

// Include auth for CSRF token generation
use Random\RandomException;

require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Generate CSRF token for forms
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
}

// Dummy data for examples
$subjects = [
    ['subject_id' => 1, 'name' => 'Matematika'],
    ['subject_id' => 2, 'name' => 'Slovenščina'],
    ['subject_id' => 3, 'name' => 'Fizika'],
    ['subject_id' => 4, 'name' => 'Kemija']
];

$students = [
    ['student_id' => 1, 'full_name' => 'Janez Novak', 'username' => 'jnovak'],
    ['student_id' => 2, 'full_name' => 'Maja Kos', 'username' => 'mkos'],
    ['student_id' => 3, 'full_name' => 'Luka Petan', 'username' => 'lpetan']
];

$gradeItems = [
    ['item_id' => 1, 'name' => 'Test 1', 'max_points' => 30],
    ['item_id' => 2, 'name' => 'Domača naloga', 'max_points' => 10],
    ['item_id' => 3, 'name' => 'Kontrolna naloga', 'max_points' => 20]
];

?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modal Examples - uwuweb</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-primary">
<div class="container mt-lg">
    <!-- Header Card -->
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">Modal Examples</h1>
                <p class="text-secondary mt-0 mb-0">Preview of modal implementation patterns for uwuweb</p>
            </div>
            <div class="role-badge role-admin">Administrator</div>
        </div>
    </div>

    <!-- Modal Types Showcase -->
    <div class="card shadow mb-lg">
        <div class="card__header">
            <h2 class="card__title">Modal Types</h2>
        </div>
        <div class="card__content">
            <p class="mb-md">Click the buttons below to see different modal types in action.</p>

            <div class="d-flex gap-md flex-wrap">
                <button data-open-modal="createItemModal" class="btn btn-primary">
                        <span class="btn-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                            </svg>
                        </span>
                    Create New Item
                </button>

                <button data-open-modal="editItemModal" data-id="2" data-name="Domača naloga" class="btn btn-secondary">
                        <span class="btn-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </span>
                    Edit Item
                </button>

                <button data-open-modal="deleteConfirmModal" data-id="2" data-name="Domača naloga"
                        class="btn btn-error">
                        <span class="btn-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </span>
                    Delete Item
                </button>
            </div>
        </div>
    </div>

    <!-- Real-world Examples -->
    <div class="card shadow mb-lg">
        <div class="card__header">
            <h2 class="card__title">Real-world Examples</h2>
        </div>
        <div class="card__content">
            <p class="mb-md">These examples show modals in context of actual application features.</p>

            <div class="d-flex gap-md flex-wrap">
                <button data-open-modal="addGradeItemModal" class="btn btn-primary">
                    Add Grade Item
                </button>

                <button data-open-modal="assignStudentModal" class="btn btn-primary">
                    Assign Student to Class
                </button>

                <button data-open-modal="attendanceModal" data-id="3" data-name="Luka Petan" class="btn btn-secondary">
                    Record Attendance
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Examples Table -->
    <div class="card shadow mb-lg">
        <div class="card__header">
            <h2 class="card__title">Grade Items</h2>
        </div>
        <div class="card__content">
            <div class="table-responsive">
                <table class="data-table w-full">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Max Points</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($gradeItems as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= $item['max_points'] ?></td>
                            <td>
                                <div class="d-flex gap-xs justify-center">
                                    <button data-open-modal="editItemModal"
                                            data-id="<?= $item['item_id'] ?>"
                                            data-name="<?= htmlspecialchars($item['name']) ?>"
                                            class="btn btn-secondary btn-sm">
                                        Edit
                                    </button>
                                    <button data-open-modal="deleteConfirmModal"
                                            data-id="<?= $item['item_id'] ?>"
                                            data-name="<?= htmlspecialchars($item['name']) ?>"
                                            class="btn btn-error btn-sm">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal 1: Create Item Modal -->
<div class="modal" id="createItemModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="createItemModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="createItemModalTitle">Create New Item</h3>
        </div>
        <form id="createItemForm" method="POST" action="modal-examples.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_item" value="1">

                <div class="form-group">
                    <label class="form-label" for="create_item_name">Name:</label>
                    <input type="text" id="create_item_name" name="name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_item_description">Description:</label>
                    <textarea id="create_item_description" name="description" class="form-input form-textarea"
                              rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_item_points">Points:</label>
                    <input type="number" id="create_item_points" name="points" class="form-input" required
                           min="1" max="100">
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Item</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Item Modal -->
<div class="modal" id="editItemModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editItemModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editItemModalTitle">Edit Item</h3>
        </div>
        <form id="editItemForm" method="POST" action="modal-examples.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_item" value="1">
                <input type="hidden" id="editItemModal_id" name="item_id" value="">

                <div class="form-group">
                    <label class="form-label" for="edit_item_name">Name:</label>
                    <input type="text" id="edit_item_name" name="name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_item_description">Description:</label>
                    <textarea id="edit_item_description" name="description" class="form-input form-textarea"
                              rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_item_points">Points:</label>
                    <input type="number" id="edit_item_points" name="points" class="form-input" required min="1"
                           max="100">
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Delete Confirmation Modal -->
<div class="modal" id="deleteConfirmModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteConfirmModalTitle">Potrditev izbrisa</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-warning mb-md">
                <p>Ali ste prepričani, da želite izbrisati <strong id="deleteConfirmModal_name"></strong>?</p>
            </div>
            <div class="alert status-error font-bold">
                <p>Tega dejanja ni mogoče razveljaviti.</p>
            </div>
            <input type="hidden" id="deleteConfirmModal_id" value="">
        </div>
        <!-- optional info message -->
        <p class="text-disabled ml-xl">Izbris bo mogoče le, če uporabnik ni v nobeni povezavi.</p>
        <div class="modal-footer">
            <div class="d-flex justify-between w-full">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="button" class="btn btn-primary" id="confirmDeleteBtn">Izbriši</button>
            </div>
        </div>
    </div>
</div>

<!-- Real-world Example: Add Grade Item Modal -->
<div class="modal" id="addGradeItemModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="addGradeItemModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="addGradeItemModalTitle">Dodaj ocenjevalno enoto</h3>
        </div>
        <form id="addGradeItemForm" method="POST" action="modal-examples.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="class_subject_id" value="1">

                <div class="form-group">
                    <label class="form-label" for="grade_item_name">Ime:</label>
                    <input type="text" id="grade_item_name" name="name" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="grade_item_max_points">Maksimalno število točk:</label>
                    <input type="number" id="grade_item_max_points" name="max_points" class="form-input"
                           required min="1" step="0.01">
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

<!-- Real-world Example: Assign Student Modal -->
<div class="modal" id="assignStudentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="assignStudentModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="assignStudentModalTitle">Dodaj dijaka v razred</h3>
        </div>
        <form id="assignStudentForm" method="POST" action="modal-examples.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="assign_student" value="1">

                <div class="form-group">
                    <label class="form-label" for="assign_student_id">Dijak:</label>
                    <select id="assign_student_id" name="student_id" class="form-input form-select" required>
                        <option value="">-- Izberi dijaka --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?= $student['student_id'] ?>">
                                <?= htmlspecialchars($student['full_name']) ?>
                                (<?= htmlspecialchars($student['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="assign_class_id">Razred:</label>
                    <select id="assign_class_id" name="class_id" class="form-input form-select" required>
                        <option value="">-- Izberi razred --</option>
                        <option value="1">1.A</option>
                        <option value="2">2.B</option>
                        <option value="3">3.C</option>
                        <option value="4">4.D</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Predmeti:</label>
                    <div class="d-flex flex-wrap gap-md">
                        <?php foreach ($subjects as $index => $subject): ?>
                            <div class="form-group mb-0 d-flex items-center gap-xs">
                                <input type="checkbox" id="subject_<?= $subject['subject_id'] ?>"
                                       name="subjects[]" value="<?= $subject['subject_id'] ?>"
                                       class="form-checkbox">
                                <label for="subject_<?= $subject['subject_id'] ?>" class="m-0">
                                    <?= htmlspecialchars($subject['name']) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-primary">Dodaj v razred</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Real-world Example: Attendance Modal -->
<div class="modal" id="attendanceModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="attendanceModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="attendanceModalTitle">Zabeleži prisotnost</h3>
        </div>
        <form id="attendanceForm" method="POST" action="modal-examples.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="save_attendance" value="1">
                <input type="hidden" id="attendanceModal_id" name="student_id" value="">

                <p class="mb-md">Beleži prisotnost za dijaka: <strong id="attendanceModal_name"></strong></p>

                <div class="form-group">
                    <label class="form-label" for="attendance_date">Datum:</label>
                    <input type="date" id="attendance_date" name="date" class="form-input"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="attendance_period">Ura:</label>
                    <select id="attendance_period" name="period_id" class="form-input form-select" required>
                        <option value="">-- Izberi uro --</option>
                        <option value="1">1. ura (8:00 - 8:45)</option>
                        <option value="2">2. ura (8:50 - 9:35)</option>
                        <option value="3">3. ura (9:40 - 10:25)</option>
                        <option value="4">4. ura (10:30 - 11:15)</option>
                    </select>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label">Status:</label>
                    <div class="d-flex gap-md">
                        <div class="form-group mb-0 d-flex items-center gap-xs">
                            <input type="radio" id="status_present" name="status" value="P" checked>
                            <label for="status_present" class="m-0">Prisoten</label>
                        </div>
                        <div class="form-group mb-0 d-flex items-center gap-xs">
                            <input type="radio" id="status_absent" name="status" value="A">
                            <label for="status_absent" class="m-0">Odsoten</label>
                        </div>
                        <div class="form-group mb-0 d-flex items-center gap-xs">
                            <input type="radio" id="status_late" name="status" value="L">
                            <label for="status_late" class="m-0">Zamuda</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-primary">Shrani</button>
                </div>
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
            }
        };

        // --- Event Listeners ---

        // Open modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;
                openModal(modalId);

                // Process additional data attributes
                const dataId = this.dataset.id;
                const dataName = this.dataset.name;

                if (dataId) {
                    // Set ID in form field or hidden input
                    const idField = document.getElementById(`${modalId}_id`);
                    if (idField) idField.value = dataId;

                    // For edit form, populate data based on item ID
                    if (modalId === 'editItemModal') {
                        // In a real application, this would fetch data from API
                        // For this example, we'll hardcode some values
                        document.getElementById('edit_item_name').value = dataName || '';
                        document.getElementById('edit_item_points').value = '20';
                        document.getElementById('edit_item_description').value = 'Primer opisnega besedila za uredi dialog.';
                    }
                }

                if (dataName) {
                    // Display name in confirmation text if needed
                    const nameDisplay = document.getElementById(`${modalId}_name`);
                    if (nameDisplay) nameDisplay.textContent = dataName;
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

        // Delete confirmation button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const itemId = document.getElementById('deleteConfirmModal_id').value;
            const itemName = document.getElementById('deleteConfirmModal_name').textContent;

            alert(`Item deletion confirmed for ID ${itemId} (${itemName}).\n\nIn a real application, this would submit a form or make an API call.`);
            closeModal('deleteConfirmModal');

            // In a real application, you would do one of these:
            // 1. Submit a form
            // 2. Make an AJAX call
            // 3. Redirect to a processing page
        });

        // Prevent default form submissions in this demo
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const formId = this.id;
                alert(`Form submitted: ${formId}\n\nIn a real application, this would save the data.`);
                closeModal(this.closest('.modal'));
            });
        });
    });
</script>
</body>
</html>
