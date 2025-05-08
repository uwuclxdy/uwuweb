<?php
/**
 * Purpose: Admin User Management Page
 * Description: Provides functionality for administrators to manage users in the system
 * Path: /uwuweb/admin/users.php
 */

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'admin_functions.php';
require_once '../includes/header.php';

// Ensure only administrators can access this page
requireRole(ROLE_ADMIN);

// Initialize variables
$message = '';
$messageType = '';
$userDetails = null;

// Get database connection
$pdo = safeGetDBConnection('admin/users.php');

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Invalid form submission. Please try again.';
    $messageType = 'error';
} else if (isset($_POST['create_user'])) handleCreateUser(); else if (isset($_POST['update_user'])) handleUpdateUser(); else if (isset($_POST['reset_password'])) handleResetPassword(); else if (isset($_POST['delete_user'])) handleDeleteUser();

// Handle JSON request for user details
if (isset($_GET['action'], $_GET['user_id'], $_GET['format']) && $_GET['action'] === 'edit' && $_GET['format'] === 'json') {
    $userId = (int)$_GET['user_id'];
    $userData = getUserDetails($userId);

    header('Content-Type: application/json');

    if ($userData)
        try {
            echo json_encode($userData, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            sendJsonErrorResponse('Error encoding JSON', 500, 'admin/users.php AJAX get_user_details');
        }

    if (!$userData)
        try {
            echo json_encode(['error' => 'User not found'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            sendJsonErrorResponse('Error encoding JSON', 500, 'admin/users.php AJAX get_user_details');
        }
    exit;
}

// Handle edit, reset, or delete requests
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

// Get all users and apply role filter if specified
$users = getAllUsers();
$roleFilter = $_GET['role'] ?? 'all';
if ($roleFilter !== 'all') $users = array_filter($users, static function ($user) use ($roleFilter) {
    if ($roleFilter === 'admin' && strtolower($user['role_name']) === 'administrator') return true;
    return strtolower($user['role_name']) === strtolower($roleFilter);
});

// Generate CSRF token
$csrfToken = ''; // Initialize with default value
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    $message = 'Error generating security token. Please try again.';
    $messageType = 'error';
}

// Get necessary data for forms
$allClasses = getAllClasses();
$allSubjects = getAllSubjects();
$allStudents = getAllStudentsBasicInfo();

/**
 * Creates a new user based on form data
 * @return void
 */
function handleCreateUser(): void
{
    global $message, $messageType;

    $userData = [
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? '',
        'email' => $_POST['email'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'role_id' => (int)($_POST['role_id'] ?? 0)
    ];

    // Validate password length
    if (strlen($userData['password']) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $messageType = 'error';
        return;
    }

    // Add role-specific fields
    if ($userData['role_id'] === ROLE_STUDENT) {
        $userData['class_code'] = $_POST['student_class'] ?? '';
        $userData['dob'] = $_POST['dob'] ?? '';
        if (empty($userData['dob'])) {
            $message = 'Date of birth is required for students.';
            $messageType = 'error';
            return;
        }
    } elseif ($userData['role_id'] === ROLE_TEACHER) $userData['teacher_subjects'] = $_POST['teacher_subjects'] ?? [];
    elseif ($userData['role_id'] === ROLE_PARENT) $userData['student_ids'] = $_POST['parent_children'] ?? [];

    // Validate and create user
    $validationResult = validateUserForm($userData);
    if ($validationResult !== true) {
        $message = $validationResult;
        $messageType = 'error';
    } else if (createNewUser($userData)) {
        $message = 'User created successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error creating user. Please check the form and try again.';
        $messageType = 'error';
    }
}

/**
 * Updates an existing user based on form data
 * @return void
 */
function handleUpdateUser(): void
{
    global $message, $messageType;

    $userId = (int)($_POST['user_id'] ?? 0);
    $userData = [
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'role_id' => (int)($_POST['role_id'] ?? 0)
    ];

    // Add role-specific fields
    if ($userData['role_id'] === ROLE_STUDENT) {
        $userData['class_code'] = $_POST['student_class'] ?? '';
        $userData['dob'] = $_POST['dob'] ?? '';
        if (empty($userData['dob'])) {
            $message = 'Date of birth is required for students.';
            $messageType = 'error';
            return;
        }
    } elseif ($userData['role_id'] === ROLE_TEACHER) $userData['teacher_subjects'] = $_POST['teacher_subjects'] ?? [];
    elseif ($userData['role_id'] === ROLE_PARENT) $userData['student_ids'] = $_POST['parent_children'] ?? [];

    // Validate and update user
    $validationResult = validateUserForm($userData);
    if ($validationResult !== true) {
        $message = $validationResult;
        $messageType = 'error';
    } else if (updateUser($userId, $userData)) {
        $message = 'User updated successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error updating user. Please check the form and try again.';
        $messageType = 'error';
    }
}

/**
 * Resets a user's password
 * @return void
 */
function handleResetPassword(): void
{
    global $message, $messageType;

    $userId = (int)($_POST['user_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match. Please try again.';
        $messageType = 'error';
    } else if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $messageType = 'error';
    } else if (resetUserPassword($userId, $newPassword)) {
        $message = 'Password reset successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error resetting password. Please try again.';
        $messageType = 'error';
    }
}

/**
 * Deletes a user after confirmation
 * @return void
 */
function handleDeleteUser(): void
{
    global $message, $messageType;

    $userId = (int)($_POST['user_id'] ?? 0);
    $confirmation = $_POST['delete_confirmation'] ?? '';

    if ($confirmation !== 'DELETE') {
        $message = 'Invalid confirmation. User was not deleted.';
        $messageType = 'error';
    } else if ($userId == $_SESSION['user_id']) {
        $message = 'You cannot delete your own account.';
        $messageType = 'error';
    } else if (deleteUser($userId)) {
        $message = 'User deleted successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error deleting user. The user may have associated data that prevents deletion.';
        $messageType = 'error';
    }
}

?>

<div class="container mt-lg">
    <!-- Header Card -->
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">User Management</h1>
                <p class="text-secondary mt-0 mb-0">Manage user accounts across the system.</p>
            </div>
            <div class="role-badge role-admin">Administrator</div>
        </div>
    </div>

    <!-- Status Message -->
    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType ?> mb-lg">
            <?= htmlspecialchars($message) ?>
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
                <button id="createUserBtn" class="btn btn-primary">
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
                        <th class="text-left">ID</th>
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
                            <td colspan="7" class="text-center py-md">No users found matching the criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $roleClass = strtolower($user['role_name']);
                            if ($roleClass === 'administrator') $roleClass = 'admin';
                            ?>
                            <tr data-role="<?= strtolower($user['role_name']) ?>"
                                data-search-terms="<?= strtolower(htmlspecialchars($user['username'] . ' ' . $user['first_name'] . ' ' . $user['last_name'] . ' ' . ($user['email'] ?? ''))) ?>">
                                <td><?= $user['user_id'] ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                <td>
                                        <span class="role-badge role-<?= $roleClass ?>">
                                            <?= htmlspecialchars(ucfirst($user['role_name'])) ?>
                                        </span>
                                </td>
                                <td><?= htmlspecialchars($user['email'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div class="d-flex gap-xs justify-center">
                                        <button class="btn btn-secondary btn-sm edit-user-btn"
                                                data-id="<?= $user['user_id'] ?>">Edit
                                        </button>
                                        <button class="btn btn-secondary btn-sm reset-pwd-btn"
                                                data-id="<?= $user['user_id'] ?>"
                                                data-username="<?= htmlspecialchars($user['username']) ?>">Reset PW
                                        </button>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-error btn-sm delete-user-btn"
                                                    data-id="<?= $user['user_id'] ?>"
                                                    data-username="<?= htmlspecialchars($user['username']) ?>">Delete
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
            <button class="btn-close" aria-label="Close modal" data-close-modal>×</button>
        </div>
        <form id="createUserForm" method="POST" action="users.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_user" value="1">

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
                    <input type="email" id="create_email" name="email" class="form-input" required>
                </div>

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
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
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
            <button class="btn-close" aria-label="Close modal" data-close-modal>×</button>
        </div>
        <form id="editUserForm" method="POST" action="users.php">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" id="edit_user_id" name="user_id" value="">

                <div class="form-group">
                    <label class="form-label" for="edit_username">Username:</label>
                    <input type="text" id="edit_username" name="username" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" class="form-input" required>
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
                    <label class="form-label" for="edit_role">Role:</label>
                    <select id="edit_role" name="role_id" class="form-input form-select" required>
                        <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                        <option value="<?= ROLE_TEACHER ?>">Teacher</option>
                        <option value="<?= ROLE_STUDENT ?>">Student</option>
                        <option value="<?= ROLE_PARENT ?>">Parent</option>
                    </select>
                </div>

                <!-- Role-specific fields (similar to create modal) -->
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
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
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
            <button class="btn-close" aria-label="Close modal" data-close-modal>×</button>
        </div>
        <form id="resetPasswordForm" method="POST" action="users.php">
            <div class="modal-body">
                <p>You are about to reset the password for <strong id="resetUsername"></strong>.</p>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="reset_password" value="1">
                <input type="hidden" id="reset_user_id" name="user_id" value="">

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
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-primary" id="resetPasswordSubmitBtn" disabled>Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Confirmation Modal -->
<div class="modal" id="deleteUserModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="deleteUserModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="deleteUserModalTitle">Delete User</h3>
            <button class="btn-close" aria-label="Close modal" data-close-modal>×</button>
        </div>
        <form id="deleteUserForm" method="POST" action="users.php">
            <div class="modal-body">
                <div class="alert status-error mb-md">
                    <p>You are about to permanently delete user <strong id="deleteUsername"></strong>.</p>
                    <p>This action cannot be undone and will remove all associated data.</p>
                </div>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" id="delete_user_id" name="user_id" value="">

                <div class="form-group">
                    <label class="form-label" for="delete_confirmation">To confirm deletion, type "DELETE"
                        below:</label>
                    <input type="text" id="delete_confirmation" name="delete_confirmation" class="form-input" required
                           pattern="DELETE">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn btn-error" id="deleteUserSubmitBtn" disabled>Delete User</button>
            </div>
        </form>
    </div>
</div>

<!--suppress JSUnresolvedReference -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- Constants ---
        const ROLE_TEACHER = <?= ROLE_TEACHER ?>;
        const ROLE_STUDENT = <?= ROLE_STUDENT ?>;
        const ROLE_PARENT = <?= ROLE_PARENT ?>;

        // --- Modal Elements ---
        const modals = {
            create: document.getElementById('createUserModal'),
            edit: document.getElementById('editUserModal'),
            reset: document.getElementById('resetPasswordModal'),
            delete: document.getElementById('deleteUserModal')
        };

        // --- Helper Functions ---
        const openModal = (modal) => {
            if (modal) {
                modal.classList.add('open');
                // Focus the first focusable element
                const firstFocusable = modal.querySelector('button, [href], input, select, textarea');
                if (firstFocusable) firstFocusable.focus();
            }
        };

        const closeModal = (modal) => {
            if (modal) {
                modal.classList.remove('open');
                // Reset forms and fields
                modal.querySelectorAll('form').forEach(form => form.reset());
                modal.querySelectorAll('.role-fields').forEach(field => {
                    if (field && field.style) {
                        field.style.display = 'none';
                    }
                });

                // Hide error messages
                const errorMsgs = modal.querySelectorAll('.feedback-error');
                errorMsgs.forEach(msg => {
                    if (msg && msg.style) {
                        msg.style.display = 'none';
                    }
                });

                // Reset buttons
                const deleteBtn = modal.querySelector('#deleteUserSubmitBtn');
                if (deleteBtn) deleteBtn.disabled = true;

                const resetPwdBtn = modal.querySelector('#resetPasswordSubmitBtn');
                if (resetPwdBtn) resetPwdBtn.disabled = true;
            }
        };

        // Updates role-specific fields visibility
        const updateRoleSpecificFields = (selectElement, prefix) => {
            if (!selectElement) return;

            const roleId = parseInt(selectElement.value || '0');
            const teacherFields = document.getElementById(`${prefix}_teacherFields`);
            const studentFields = document.getElementById(`${prefix}_studentFields`);
            const parentFields = document.getElementById(`${prefix}_parentFields`);
            const nameFieldsRow = document.getElementById(`${prefix}_nameFields`);

            if (teacherFields) teacherFields.style.display = (roleId === ROLE_TEACHER) ? 'block' : 'none';
            if (studentFields) studentFields.style.display = (roleId === ROLE_STUDENT) ? 'block' : 'none';
            if (parentFields) parentFields.style.display = (roleId === ROLE_PARENT) ? 'block' : 'none';
            if (nameFieldsRow) nameFieldsRow.style.display = (roleId === ROLE_STUDENT) ? 'flex' : 'none';

            // Update required attributes
            const studentClass = document.getElementById(`${prefix}_student_class`);
            const dob = document.getElementById(`${prefix}_dob`);
            const firstName = document.getElementById(`${prefix}_first_name`);
            const lastName = document.getElementById(`${prefix}_last_name`);

            if (studentClass) studentClass.required = (roleId === ROLE_STUDENT);
            if (dob) dob.required = (roleId === ROLE_STUDENT);
            if (firstName) firstName.required = (roleId === ROLE_STUDENT);
            if (lastName) lastName.required = (roleId === ROLE_STUDENT);
        };

        // --- Search Functionality ---
        const searchInput = document.getElementById('searchInput');
        const usersTable = document.getElementById('usersTable');

        if (searchInput && usersTable) {
            searchInput.addEventListener('input', function () {
                const searchTermValue = this.value || '';
                const searchTerm = searchTermValue.toLowerCase().trim();
                const rows = usersTable.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    if (row.classList.contains('no-results')) return;

                    const searchTerms = (row.dataset.searchTerms || '').toLowerCase();
                    const visible = searchTerm === '' || searchTerms.includes(searchTerm);

                    if (row.style) {
                        row.style.display = visible ? '' : 'none';
                    }
                });

                // Check if we need to show "no results" message
                const visibleRows = Array.from(rows).filter(row =>
                    !row.classList.contains('no-results') &&
                    (!row.style || row.style.display !== 'none')
                );

                let noResultsRow = usersTable.querySelector('.no-results');

                if (visibleRows.length === 0) {
                    if (!noResultsRow) {
                        noResultsRow = document.createElement('tr');
                        noResultsRow.classList.add('no-results');
                        noResultsRow.innerHTML = `<td colspan="7" class="text-center py-md">No users found matching your search criteria.</td>`;
                        const tbody = usersTable.querySelector('tbody');
                        if (tbody) tbody.appendChild(noResultsRow);
                    }
                } else if (noResultsRow) {
                    noResultsRow.remove();
                }
            });
        }

        // --- Event Listeners ---

        // Create User Button
        const createUserBtn = document.getElementById('createUserBtn');
        if (createUserBtn) {
            createUserBtn.addEventListener('click', () => {
                openModal(modals.create);
            });
        }

        // Edit User Buttons
        document.querySelectorAll('.edit-user-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.dataset.id;
                if (!userId) return;

                // Get the user from the table row instead of using AJAX
                const row = this.closest('tr');
                const username = row.querySelector('td:nth-child(2)').textContent;
                const fullName = row.querySelector('td:nth-child(3)').textContent;
                const roleId = parseInt(row.dataset.role === 'administrator' ? '1' :
                    row.dataset.role === 'teacher' ? '2' :
                        row.dataset.role === 'student' ? '3' :
                            row.dataset.role === 'parent' ? '4' : '0');
                const email = row.querySelector('td:nth-child(5)').textContent;

                // Populate the edit form
                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_email').value = email === 'N/A' ? '' : email;

                // Split full name into first and last name
                const nameParts = fullName.split(' ');
                document.getElementById('edit_first_name').value = nameParts[0] || '';
                document.getElementById('edit_last_name').value = nameParts.slice(1).join(' ') || '';

                // Set role
                document.getElementById('edit_role').value = roleId;

                // Update fields visibility based on role
                updateRoleSpecificFields(document.getElementById('edit_role'), 'edit');

                // For full details, we'll need to redirect to get the data
                // This opens the modal with basic info immediately
                openModal(modals.edit);

                // Then fetch additional role-specific data
                fetch(`users.php?action=edit&user_id=${userId}&format=json`)
                    .then(response => response.json())
                    .then(userData => {
                        // Populate role-specific fields if data is available
                        if (userData.role_id === ROLE_STUDENT) {
                            const studentClassField = document.getElementById('edit_student_class');
                            const dobField = document.getElementById('edit_dob');

                            if (studentClassField && userData.class_code) {
                                studentClassField.value = userData.class_code;
                            }

                            if (dobField && userData.dob) {
                                dobField.value = userData.dob;
                            }
                        } else if (userData.role_id === ROLE_TEACHER && userData.subjects) {
                            const subjectSelect = document.getElementById('edit_teacher_subjects');
                            if (subjectSelect) {
                                const optionElements = subjectSelect.querySelectorAll('option');
                                optionElements.forEach(option => {
                                    option.selected = userData.subjects.includes(parseInt(option.value));
                                });
                            }
                        } else if (userData.role_id === ROLE_PARENT && userData.children) {
                            const childrenSelect = document.getElementById('edit_parent_children');
                            if (childrenSelect) {
                                const optionElements = childrenSelect.querySelectorAll('option');
                                optionElements.forEach(option => {
                                    option.selected = userData.children.includes(parseInt(option.value));
                                });
                            }
                        }
                    })
                    .catch(() => {
                        console.log('Could not load additional user details. Some fields may need to be filled in manually.');
                    });
            });
        });

        // Reset Password Buttons
        document.querySelectorAll('.reset-pwd-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.dataset.id;
                const username = this.dataset.username;
                if (!userId) return;

                const userIdField = document.getElementById('reset_user_id');
                const usernameDisplay = document.getElementById('resetUsername');

                if (userIdField) userIdField.value = userId;
                if (usernameDisplay) usernameDisplay.textContent = username || 'this user';

                openModal(modals.reset);
            });
        });

        // Delete User Buttons
        document.querySelectorAll('.delete-user-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const userId = this.dataset.id;
                const username = this.dataset.username;
                if (!userId) return;

                const userIdField = document.getElementById('delete_user_id');
                const usernameDisplay = document.getElementById('deleteUsername');
                const confirmationField = document.getElementById('delete_confirmation');
                const submitBtn = document.getElementById('deleteUserSubmitBtn');

                if (userIdField) userIdField.value = userId;
                if (usernameDisplay) usernameDisplay.textContent = username || 'this user';
                if (confirmationField) confirmationField.value = '';
                if (submitBtn) submitBtn.disabled = true;

                openModal(modals.delete);
            });
        });

        // Close Modal Buttons
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        // Close Modal on Overlay Click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        // Close Modal on Escape Key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.open').forEach(closeModal);
            }
        });

        // Role Select Change Handlers
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

        // Password Validation
        const validatePasswords = () => {
            const newPwd = document.getElementById('new_password');
            const confirmPwd = document.getElementById('confirm_password');
            const errorMsg = document.getElementById('password_match_error');
            const submitBtn = document.getElementById('resetPasswordSubmitBtn');

            if (!newPwd || !confirmPwd || !errorMsg || !submitBtn) return;

            const newPwdValue = newPwd.value || '';
            const confirmPwdValue = confirmPwd.value || '';

            // Check password length - require minimum 6 characters
            const minLength = 6;
            if (newPwdValue.length < minLength) {
                errorMsg.textContent = `Password must be at least ${minLength} characters.`;
                errorMsg.style.display = 'block';
                submitBtn.disabled = true;
                return;
            }

            // Check if passwords match
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
                submitBtn.disabled = true;
            }
        };

        const newPasswordField = document.getElementById('new_password');
        const confirmPasswordField = document.getElementById('confirm_password');

        if (newPasswordField) newPasswordField.addEventListener('input', validatePasswords);
        if (confirmPasswordField) confirmPasswordField.addEventListener('input', validatePasswords);

        // Delete Confirmation Validation
        const deleteConfirmInput = document.getElementById('delete_confirmation');
        const deleteSubmitBtn = document.getElementById('deleteUserSubmitBtn');

        if (deleteConfirmInput && deleteSubmitBtn) {
            deleteConfirmInput.addEventListener('input', function () {
                const inputValue = this.value || '';
                deleteSubmitBtn.disabled = inputValue !== 'DELETE';
            });
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
