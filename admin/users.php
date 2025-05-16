<?php
/**
 * Purpose: Admin User Management Page
 * /uwuweb/admin/users.php
 *
 * Description: Provides functionality for administrators to manage users in the system
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'admin_functions.php';
require_once '../includes/header.php';

// Require admin role for this page
requireRole(ROLE_ADMIN);

// Initialize variables
$message = '';
$messageType = '';
$userDetails = null;

// Get database connection
$pdo = safeGetDBConnection('admin/users.php');

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Invalid form submission. Please try again.';
    $messageType = 'error';
} else if (isset($_POST['create_user'])) handleCreateUser(); else if (isset($_POST['update_user'])) handleUpdateUser(); else if (isset($_POST['reset_password'])) handleResetPassword(); else if (isset($_POST['delete_user'])) handleDeleteUser();

// Replace this section in users.php

// Handle AJAX request for user details
if (isset($_GET['action'], $_GET['user_id'], $_GET['format']) &&
    $_GET['action'] === 'edit' && $_GET['format'] === 'json') {

    // Set content type header immediately - do this first
    header('Content-Type: application/json');

    try {
        $userId = (int)$_GET['user_id'];

        // Get user details
        $userData = getUserDetails($userId);

        // Simple response - no nested conditions or multiple try/catch blocks
        if ($userData) {
            $jsonData = json_encode($userData, JSON_THROW_ON_ERROR);
            if ($jsonData === false) throw new RuntimeException('JSON encoding failed: ' . json_last_error_msg());
            echo $jsonData;
        } else echo json_encode(['error' => 'User not found'], JSON_THROW_ON_ERROR);
    } catch (Exception $e) {
        // Log the error
        error_log('Error in user edit JSON endpoint: ' . $e->getMessage());
        // Return a proper JSON error response
        try {
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            sendJsonErrorResponse('Server error: ' . $e->getMessage(), 500, 'users.php');
        }
    }
    exit;
}

// Get user details for edit/reset/delete actions
if (isset($_GET['action'], $_GET['user_id'])) {
    $userId = (int)$_GET['user_id'];
    $action = $_GET['action'];

    if (in_array($action, ['edit', 'reset', 'delete'])) {
        $userDetails = getUserDetails($userId);
        if (!$userDetails) {
            $message = 'User not found.';
            $messageType = 'error';
        }
    }
}

// Get all users
$users = getAllUsers();

// Filter users by role if a role filter is selected
$roleFilter = $_GET['role'] ?? 'all';
if ($roleFilter !== 'all') $users = array_filter($users, static function ($user) use ($roleFilter) {
    if ($roleFilter === 'admin' && strtolower($user['role_name']) === 'administrator') return true;
    return strtolower($user['role_name']) === strtolower($roleFilter);
});

// Generate CSRF token
try {
    $csrfToken = generateCSRFToken();
} catch (Exception $e) {
    $message = 'Error generating security token. Please try again.';
    $messageType = 'error';
    $csrfToken = '';
}

// Get additional data needed for the forms
$allClasses = getAllClasses();
$allSubjects = getAllSubjects();
$allStudents = getAllStudentsBasicInfo();
?>

<div class="container mt-lg">
    <?php renderHeaderCard(
        'User Management',
        'Manage user accounts across the system.',
        'admin'
    ); ?>

    <!-- Status Message -->
    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType ?> page-transition mb-lg"
             role="<?= $messageType === 'error' ? 'alert' : 'status' ?>"
             aria-live="<?= $messageType === 'error' ? 'assertive' : 'polite' ?>">
            <div class="alert-content">
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search Box -->
    <div class="card shadow mb-lg">
        <div class="card__content">
            <div class="d-flex justify-between items-center flex-wrap gap-md">
                <div class="form-group mb-0 flex-grow-1">
                    <label for="searchInput" class="sr-only">Search Users</label>
                    <input type="text" id="searchInput" class="form-input"
                           placeholder="Search users by name, username, or email...">
                </div>
                <button id="createUserBtn" data-open-modal="createUserModal" class="btn btn-primary">
                    <span class="btn-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </span>
                    Create New User
                </button>
            </div>
        </div>
    </div>

    <!-- Role Filters -->
    <div class="mb-lg">
        <div class="d-flex gap-sm flex-wrap">
            <a href="?role=all"
               class="role-filter btn btn-sm <?= $roleFilter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
                All Users
            </a>
            <a href="?role=admin"
               class="role-filter btn btn-sm <?= $roleFilter === 'admin' ? 'btn-primary' : 'btn-secondary' ?>">
                Administrators
            </a>
            <a href="?role=teacher"
               class="role-filter btn btn-sm <?= $roleFilter === 'teacher' ? 'btn-primary' : 'btn-secondary' ?>">
                Teachers
            </a>
            <a href="?role=student"
               class="role-filter btn btn-sm <?= $roleFilter === 'student' ? 'btn-primary' : 'btn-secondary' ?>">
                Students
            </a>
            <a href="?role=parent"
               class="role-filter btn btn-sm <?= $roleFilter === 'parent' ? 'btn-primary' : 'btn-secondary' ?>">
                Parents
            </a>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow mb-lg">
        <div class="card__content">
            <div class="table-responsive">
                <table class="data-table w-full" id="usersTable">
                    <thead>
                    <tr>
                        <th class="text-left">Username</th>
                        <th class="text-left">Full Name</th>
                        <th class="text-left">Role</th>
                        <th class="text-left">Email</th>
                        <th class="text-left">Created At</th>
                        <th class="text-center">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-md">No users found matching the criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $roleClass = strtolower($user['role_name']);
                            if ($roleClass === 'administrator') $roleClass = 'admin';
                            ?>
                            <tr data-role="<?= strtolower($user['role_name']) ?>"
                                data-search-terms="<?= strtolower(htmlspecialchars($user['username'] . ' ' . ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '') . ' ' . ($user['email'] ?? ''))) ?>">
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></td>
                                <td>
                                    <span class="role-badge role-<?= $roleClass ?>">
                                        <?= htmlspecialchars(ucfirst($user['role_name'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-xs justify-center">
                                        <button class="btn btn-secondary btn-sm"
                                                data-open-modal="editUserModal"
                                                data-id="<?= $user['user_id'] ?>">
                                            <span class="text-md">✎</span> Edit
                                        </button>
                                        <button class="btn btn-secondary btn-sm"
                                                data-open-modal="resetPasswordModal"
                                                data-id="<?= $user['user_id'] ?>"
                                                data-username="<?= htmlspecialchars($user['username']) ?>">
                                            Reset PW
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-error btn-sm"
                                                    data-open-modal="deleteUserModal"
                                                    data-id="<?= $user['user_id'] ?>"
                                                    data-name="<?= htmlspecialchars($user['username']) ?>">
                                                <span class="text-md">🗑</span> Delete
                                            </button>
                                        <?php endif; ?>
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
</div>

<!-- Create User Modal -->
<div class="modal" id="createUserModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="createUserModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="createUserModalTitle">Create New User</h3>
        </div>
        <form id="createUserForm" method="POST" action="users.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_user" value="1">

                <div class="form-group">
                    <label class="form-label" for="create_role">Role:</label>
                    <select id="create_role" name="role_id" class="form-input form-select" required>
                        <option value="">-- Select Role --</option>
                        <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                        <option value="<?= ROLE_TEACHER ?>">Teacher</option>
                        <option value="<?= ROLE_STUDENT ?>">Student</option>
                        <option value="<?= ROLE_PARENT ?>">Parent</option>
                    </select>
                </div>

                <div id="create_nameFields" class="row" style="display: none;">
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="create_first_name">First Name:</label>
                            <input type="text" id="create_first_name" name="first_name" class="form-input">
                        </div>
                    </div>
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="create_last_name">Last Name:</label>
                            <input type="text" id="create_last_name" name="last_name" class="form-input">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_username">Username:</label>
                    <input type="text" id="create_username" name="username" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_password">Password:</label>
                    <input type="password" id="create_password" name="password" class="form-input" required
                           minlength="6">
                    <small class="text-secondary d-block mt-xs">Password must be at least 6 characters.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="create_email">Email:</label>
                    <input type="email" id="create_email" name="email" class="form-input">
                </div>

                <!-- Teacher-specific fields -->
                <div id="create_teacherFields" class="role-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="create_teacher_subjects">Subjects:</label>
                        <select id="create_teacher_subjects" name="teacher_subjects[]" class="form-input form-select"
                                multiple>
                            <?php foreach ($allSubjects as $subject): ?>
                                <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-secondary text-xs">Hold Ctrl/Cmd to select multiple subjects.</small>
                    </div>
                </div>

                <!-- Student-specific fields -->
                <div id="create_studentFields" class="role-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="create_student_class">Class:</label>
                        <select id="create_student_class" name="student_class" class="form-input form-select">
                            <option value="">-- Select Class --</option>
                            <?php foreach ($allClasses as $class): ?>
                                <option value="<?= $class['class_code'] ?>"><?= htmlspecialchars($class['class_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="create_dob">Date of Birth:</label>
                        <input type="date" id="create_dob" name="dob" class="form-input">
                    </div>
                </div>

                <!-- Parent-specific fields -->
                <div id="create_parentFields" class="role-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="create_parent_children">Link to Children:</label>
                        <select id="create_parent_children" name="parent_children[]" class="form-input form-select"
                                multiple>
                            <?php foreach ($allStudents as $student): ?>
                                <option value="<?= $student['student_id'] ?>">
                                    <?= htmlspecialchars($student['full_name'] ?? 'Unknown Name') ?>
                                    (<?= htmlspecialchars($student['username'] ?? 'Unknown Username') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-secondary text-xs">Hold Ctrl/Cmd to select multiple children.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal" id="editUserModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="editUserModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="editUserModalTitle">Edit User</h3>
        </div>
        <form id="editUserForm" method="POST" action="users.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" id="edit_user_id" name="user_id" value="">
                <!-- Add an original username field to help with validation -->
                <input type="hidden" id="edit_original_username" name="original_username" value="">

                <div class="form-group">
                    <label class="form-label" for="edit_role">Role:</label>
                    <select id="edit_role" name="role_id" class="form-input form-select" required>
                        <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                        <option value="<?= ROLE_TEACHER ?>">Teacher</option>
                        <option value="<?= ROLE_STUDENT ?>">Student</option>
                        <option value="<?= ROLE_PARENT ?>">Parent</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="edit_first_name">First Name:</label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-input" required>
                        </div>
                    </div>
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label class="form-label" for="edit_last_name">Last Name:</label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-input" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_username">Username:</label>
                    <input type="text" id="edit_username" name="username" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" class="form-input">
                </div>

                <!-- Role-specific fields -->
                <div id="edit_teacherFields" class="role-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="edit_teacher_subjects">Subjects:</label>
                        <select id="edit_teacher_subjects" name="teacher_subjects[]" class="form-input form-select"
                                multiple>
                            <?php foreach ($allSubjects as $subject): ?>
                                <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-secondary text-xs">Hold Ctrl/Cmd to select multiple subjects.</small>
                    </div>
                </div>

                <div id="edit_studentFields" class="role-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="edit_student_class">Class:</label>
                        <select id="edit_student_class" name="student_class" class="form-input form-select">
                            <option value="">-- Select Class --</option>
                            <?php foreach ($allClasses as $class): ?>
                                <option value="<?= $class['class_code'] ?>"><?= htmlspecialchars($class['class_code']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_dob">Date of Birth:</label>
                        <input type="date" id="edit_dob" name="dob" class="form-input">
                    </div>
                </div>

                <div id="edit_parentFields" class="role-fields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label" for="edit_parent_children">Linked Children:</label>
                        <select id="edit_parent_children" name="parent_children[]" class="form-input form-select"
                                multiple>
                            <?php foreach ($allStudents as $student): ?>
                                <option value="<?= $student['student_id'] ?>"><?= htmlspecialchars($student['full_name'] ?? 'Unknown Name') ?>
                                    (<?= htmlspecialchars($student['username'] ?? 'Unknown Username') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-secondary text-xs">Hold Ctrl/Cmd to select multiple children.</small>
                    </div>
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

<!-- Reset Password Modal -->
<div class="modal" id="resetPasswordModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="resetPasswordModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="resetPasswordModalTitle">Reset Password</h3>
        </div>
        <form id="resetPasswordForm" method="POST" action="users.php">
            <div class="modal-body">
                <p>You are about to reset the password for <strong id="resetUsername"></strong>.</p>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="reset_password" value="1">
                <input type="hidden" id="resetPasswordModal_id" name="user_id" value="">

                <div class="form-group">
                    <label class="form-label" for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" required
                           minlength="6">
                    <small class="text-secondary d-block mt-xs">Password must be at least 6 characters.</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required
                           minlength="6">
                    <div id="password_match_error" class="feedback-text feedback-error mt-xs" style="display: none;">
                        Passwords do not match!
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-primary" id="resetPasswordSubmitBtn">Reset Password</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Confirmation Modal -->
<div class="modal" id="deleteUserModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="deleteUserModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteUserModalTitle">Potrditev izbrisa</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-warning mb-md">
                <div class="alert-content">
                    <p>Ali ste prepričani, da želite izbrisati uporabnika <strong id="deleteUserModal_name"></strong>?
                    </p>
                </div>
            </div>
            <div class="alert status-error font-bold">
                <div class="alert-content">
                    <p>Tega dejanja ni mogoče razveljaviti.</p>
                </div>
            </div>
            <input type="hidden" id="deleteUserModal_id" value="">
        </div>
        <p class="text-disabled ml-xl mr-xl">Izbris bo mogoče le, če uporabnik ni v nobeni povezavi.</p>
        <div class="modal-footer">
            <div class="d-flex justify-between w-full">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="button" class="btn btn-error" id="confirmDeleteBtn">Izbriši</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ROLE_TEACHER = <?= ROLE_TEACHER ?>;
        const ROLE_STUDENT = <?= ROLE_STUDENT ?>;
        const ROLE_PARENT = <?= ROLE_PARENT ?>;

        // Modal Management Functions
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

                // Hide role-specific fields
                modal.querySelectorAll('.role-fields').forEach(field => {
                    if (field && field.style) {
                        field.style.display = 'none';
                    }
                });
            }
        };

        // Function to update role-specific fields visibility based on selected role
        const updateRoleSpecificFields = (selectElement, prefix) => {
            if (!selectElement) return;

            const roleId = parseInt(selectElement.value || '0');
            const teacherFields = document.getElementById(`${prefix}_teacherFields`);
            const studentFields = document.getElementById(`${prefix}_studentFields`);
            const parentFields = document.getElementById(`${prefix}_parentFields`);
            const nameFieldsRow = document.getElementById(`${prefix}_nameFields`);

            // Show/hide role-specific fields
            if (teacherFields) teacherFields.style.display = (roleId === ROLE_TEACHER) ? 'block' : 'none';
            if (studentFields) studentFields.style.display = (roleId === ROLE_STUDENT) ? 'block' : 'none';
            if (parentFields) parentFields.style.display = (roleId === ROLE_PARENT) ? 'block' : 'none';

            // Show name fields for all roles except admin
            if (nameFieldsRow) {
                nameFieldsRow.style.display = (roleId === ROLE_TEACHER || roleId === ROLE_STUDENT || roleId === ROLE_PARENT) ? 'flex' : 'none';
            }

            // Set required fields based on role
            const studentClass = document.getElementById(`${prefix}_student_class`);
            const dob = document.getElementById(`${prefix}_dob`);
            const firstName = document.getElementById(`${prefix}_first_name`);
            const lastName = document.getElementById(`${prefix}_last_name`);

            // Make fields required based on role
            if (studentClass) studentClass.required = (roleId === ROLE_STUDENT);
            if (dob) dob.required = (roleId === ROLE_STUDENT);
            if (firstName) firstName.required = (roleId === ROLE_STUDENT || roleId === ROLE_TEACHER);
            if (lastName) lastName.required = (roleId === ROLE_STUDENT || roleId === ROLE_TEACHER);
        };

        // User search functionality
        const searchInput = document.getElementById('searchInput');
        const usersTable = document.getElementById('usersTable');

        if (searchInput && usersTable) {
            searchInput.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase().trim();
                const rows = usersTable.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    if (row.classList.contains('no-results')) return;

                    const searchTerms = (row.dataset.searchTerms || '').toLowerCase();
                    const visible = searchTerm === '' || searchTerms.includes(searchTerm);
                    row.style.display = visible ? '' : 'none';
                });

                // Show "no results" message if no rows are visible
                const visibleRows = Array.from(rows).filter(row =>
                    !row.classList.contains('no-results') &&
                    row.style.display !== 'none'
                );

                let noResultsRow = usersTable.querySelector('.no-results');

                if (visibleRows.length === 0) {
                    if (!noResultsRow) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.classList.add('no-results');
                        noResultsRow.innerHTML = `<td colspan="6" class="text-center py-md">No users found matching your search criteria.</td>`;
                        const tbody = usersTable.querySelector('tbody');
                        if (tbody) tbody.appendChild(noResultsRow);
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
            });
        }

        // Event Listeners for modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;
                openModal(modalId);

                // Handle additional data attributes
                const dataId = this.dataset.id;
                const dataName = this.dataset.name;
                const dataUsername = this.dataset.username;

                if (dataId) {
                    const idField = document.getElementById(`${modalId}_id`);
                    if (idField) idField.value = dataId;
                }

                if (dataName) {
                    const nameDisplay = document.getElementById(`${modalId}_name`);
                    if (nameDisplay) nameDisplay.textContent = dataName;
                }

                if (dataUsername) {
                    const usernameDisplay = document.getElementById('resetUsername');
                    if (usernameDisplay) usernameDisplay.textContent = dataUsername;
                }

                // If opening edit modal, fetch user data and populate form
                if (modalId === 'editUserModal' && dataId) {
                    // Set the user ID immediately so it's not forgotten
                    const userIdField = document.getElementById('edit_user_id');
                    if (userIdField) userIdField.value = dataId;

                    fetch(`../api/admin.php?action=getUserDetails&id=${dataId}`)
                        .then(response => {
                            // Check if response is OK
                            if (!response.ok) {
                                return response.json().then(data => {
                                    throw new Error(data.error || `HTTP error ${response.status}`);
                                }).catch(e => {
                                    // If JSON parsing failed, provide a clearer error
                                    if (e instanceof SyntaxError) {
                                        throw new Error(`Invalid server response (not JSON): ${response.status}`);
                                    }
                                    throw e;
                                });
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Response data:', data);

                            // Check if data contains an error
                            if (data.error) {
                                throw new Error(data.error);
                            }

                            // Double-check that user_id is set correctly
                            document.getElementById('edit_user_id').value = dataId;

                            // Store the original username for validation
                            if (data.username) {
                                document.getElementById('edit_original_username').value = data.username;
                            }

                            // Populate user data into form fields
                            const fields = {
                                'edit_username': data.username || '',
                                'edit_email': data.email || '',
                                'edit_first_name': data.first_name || '',
                                'edit_last_name': data.last_name || '',
                                'edit_role': data.role_id || ''
                            };

                            // Set each field value
                            Object.keys(fields).forEach(id => {
                                const field = document.getElementById(id);
                                if (field) field.value = fields[id];
                            });

                            // Update visible fields based on role
                            updateRoleSpecificFields(document.getElementById('edit_role'), 'edit');

                            // Populate role-specific fields
                            if (data.role_id === ROLE_STUDENT) {
                                const studentClassField = document.getElementById('edit_student_class');
                                const dobField = document.getElementById('edit_dob');

                                if (studentClassField && data.class_code) {
                                    studentClassField.value = data.class_code;
                                }

                                if (dobField && data.dob) {
                                    dobField.value = data.dob;
                                }
                            } else if (data.role_id === ROLE_TEACHER && data.subjects) {
                                const subjectSelect = document.getElementById('edit_teacher_subjects');
                                if (subjectSelect) {
                                    const optionElements = subjectSelect.querySelectorAll('option');
                                    optionElements.forEach(option => {
                                        option.selected = data.subjects.includes(parseInt(option.value));
                                    });
                                }
                            } else if (data.role_id === ROLE_PARENT && data.children) {
                                const childrenSelect = document.getElementById('edit_parent_children');
                                if (childrenSelect) {
                                    const optionElements = childrenSelect.querySelectorAll('option');
                                    optionElements.forEach(option => {
                                        option.selected = data.children.includes(parseInt(option.value));
                                    });
                                }
                            }

                            console.log('Successfully loaded user data for ID:', dataId);
                        })
                        .catch(error => {
                            console.error('Error fetching user details:', error);
                            // Show the actual error message from the server if available
                            alert(`Error loading user data: ${error.message || 'Please try again.'}`);
                        });
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

        // Handle delete user button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
            const userId = document.getElementById('deleteUserModal_id').value;
            if (!userId) return;

            // Create and submit form for delete action
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_user';
            actionInput.value = '1';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'user_id';
            idInput.value = userId;

            // Add the delete confirmation field that handleDeleteUser() expects
            const confirmInput = document.createElement('input');
            confirmInput.type = 'hidden';
            confirmInput.name = 'delete_confirmation';
            confirmInput.value = 'DELETE';

            form.appendChild(csrfInput);
            form.appendChild(actionInput);
            form.appendChild(idInput);
            form.appendChild(confirmInput);
            document.body.appendChild(form);
            form.submit();
        });

        // Role change handlers
        const createRoleSelect = document.getElementById('create_role');
        const editRoleSelect = document.getElementById('edit_role');

        if (createRoleSelect) {
            createRoleSelect.addEventListener('change', function () {
                updateRoleSpecificFields(this, 'create');
            });
        }

        if (editRoleSelect) {
            editRoleSelect.addEventListener('change', function () {
                updateRoleSpecificFields(this, 'edit');
            });
        }

        // Password validation for reset password form
        const validatePasswords = () => {
            const newPwd = document.getElementById('new_password');
            const confirmPwd = document.getElementById('confirm_password');
            const errorMsg = document.getElementById('password_match_error');
            const submitBtn = document.getElementById('resetPasswordSubmitBtn');

            if (!newPwd || !confirmPwd || !errorMsg || !submitBtn) return;

            const newPwdValue = newPwd.value || '';
            const confirmPwdValue = confirmPwd.value || '';

            const minLength = 6;
            if (newPwdValue.length < minLength) {
                errorMsg.textContent = `Password must be at least ${minLength} characters.`;
                errorMsg.style.display = 'block';
                submitBtn.disabled = true;
                return;
            }

            if (newPwdValue && confirmPwdValue) {
                if (newPwdValue !== confirmPwdValue) {
                    errorMsg.textContent = 'Passwords do not match!';
                    errorMsg.style.display = 'block';
                    submitBtn.disabled = true;
                } else {
                    errorMsg.style.display = 'none';
                    submitBtn.disabled = false;
                }
            } else {
                errorMsg.style.display = 'none';
                submitBtn.disabled = newPwdValue.length === 0 || confirmPwdValue.length === 0;
            }
        };

        // Add password validation listeners
        const newPasswordField = document.getElementById('new_password');
        const confirmPasswordField = document.getElementById('confirm_password');

        if (newPasswordField) newPasswordField.addEventListener('input', validatePasswords);
        if (confirmPasswordField) confirmPasswordField.addEventListener('input', validatePasswords);

        // Initialize password validation
        validatePasswords();
    });
</script>

<?php include '../includes/footer.php'; ?>
