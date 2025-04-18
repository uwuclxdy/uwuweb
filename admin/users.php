<?php
/**
 * Admin User Management
 *
 * Provides functionality for administrators to manage users in the system
 * including creating, editing, deleting users and resetting passwords
 *
 * Functions:
 * - displayUserList() - Displays a table of all users with management actions
 * - getUserDetails($userId) - Fetches detailed information about a specific user
 * - createNewUser($userData) - Creates a new user with specified role
 * - updateUser($userId, $userData) - Updates an existing user's information
 * - resetUserPassword($userId, $newPassword) - Resets a user's password
 * - deleteUser($userId) - Deletes a user if they have no dependencies
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Ensure only administrators can access this page
requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
if (!$pdo) {
    error_log("Database connection failed in admin/users.php");
    die("Database connection failed. Please check the error log for details.");
}
$message = '';
$userDetails = null;
$error = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['action'] ?? '';

    // Verify CSRF token for all POST actions
    if (isset($_POST['csrf_token'])) {
        verifyCSRFToken($_POST['csrf_token']);
    } else {
        $error = 'Security token missing. Please try again.';
    }

    if (empty($error)) {
        try {
            switch ($formAction) {
                case 'create':
                    // Create new user
                    $username = trim($_POST['username']);
                    $password = trim($_POST['password']);
                    $roleId = (int)$_POST['role_id'];

                    // Basic validation
                    if (empty($username) || empty($password)) {
                        $error = 'Username and password are required.';
                        break;
                    }

                    // Check if username already exists
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username");
                    $stmt->execute(['username' => $username]);
                    if ($stmt->fetch()) {
                        $error = 'Username already exists. Please choose another.';
                        break;
                    }

                    // Hash password and insert user
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, pass_hash, role_id, created_at) 
                                        VALUES (:username, :pass_hash, :role_id, NOW())");
                    $stmt->execute([
                        'username' => $username,
                        'pass_hash' => $passwordHash,
                        'role_id' => $roleId
                    ]);

                    // Get new user ID
                    $newUserId = $pdo->lastInsertId();

                    // Create role-specific record if needed
                    if ($roleId == ROLE_STUDENT) {
                        // Add student record
                        $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name) 
                                            VALUES (:user_id, :first_name, :last_name)");
                        $stmt->execute([
                            'user_id' => $newUserId,
                            'first_name' => trim($_POST['first_name'] ?? ''),
                            'last_name' => trim($_POST['last_name'] ?? '')
                        ]);
                    } elseif ($roleId == ROLE_TEACHER) {
                        // Add teacher record
                        $stmt = $pdo->prepare("INSERT INTO teachers (user_id) VALUES (:user_id)");
                        $stmt->execute(['user_id' => $newUserId]);
                    } elseif ($roleId == ROLE_PARENT) {
                        // Add parent record
                        $stmt = $pdo->prepare("INSERT INTO parents (user_id) VALUES (:user_id)");
                        $stmt->execute(['user_id' => $newUserId]);

                        // Link to student if specified
                        if (!empty($_POST['linked_student_id'])) {
                            $stmt = $pdo->prepare("INSERT INTO student_parent (student_id, parent_id) 
                                                VALUES (:student_id, :parent_id)");
                            $stmt->execute([
                                'student_id' => (int)$_POST['linked_student_id'],
                                'parent_id' => $pdo->lastInsertId()
                            ]);
                        }
                    }

                    $message = "User '$username' created successfully.";
                    break;

                case 'update':
                    // Update existing user
                    $userId = (int)$_POST['user_id'];
                    $username = trim($_POST['username']);

                    // Check if username exists (excluding current user)
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username AND user_id != :user_id");
                    $stmt->execute([
                        'username' => $username,
                        'user_id' => $userId
                    ]);

                    if ($stmt->fetch()) {
                        $error = 'Username already exists. Please choose another.';
                        break;
                    }

                    // Update the user record
                    $stmt = $pdo->prepare("UPDATE users SET username = :username WHERE user_id = :user_id");
                    $stmt->execute([
                        'username' => $username,
                        'user_id' => $userId
                    ]);

                    // Update role-specific details
                    $roleId = getUserRoleById($userId);
                    if (isset($_POST['first_name'], $_POST['last_name']) && $roleId == ROLE_STUDENT) {
                        $stmt = $pdo->prepare("UPDATE students SET 
                                            first_name = :first_name, 
                                            last_name = :last_name 
                                            WHERE user_id = :user_id");
                        $stmt->execute([
                            'first_name' => trim($_POST['first_name']),
                            'last_name' => trim($_POST['last_name']),
                            'user_id' => $userId
                        ]);
                    }

                    $message = "User updated successfully.";
                    break;

                case 'reset_password':
                    // Reset user password
                    $userId = (int)$_POST['user_id'];
                    $newPassword = trim($_POST['new_password']);

                    if (empty($newPassword)) {
                        $error = 'New password cannot be empty.';
                        break;
                    }

                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET pass_hash = :pass_hash WHERE user_id = :user_id");
                    $stmt->execute([
                        'pass_hash' => $passwordHash,
                        'user_id' => $userId
                    ]);

                    $message = "Password reset successfully.";
                    break;

                case 'delete':
                    // Delete user (if no dependencies)
                    $userId = (int)$_POST['user_id'];

                    // Begin transaction
                    $pdo->beginTransaction();

                    // Get user's role
                    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
                    $stmt->execute(['user_id' => $userId]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        $error = 'User not found.';
                        $pdo->rollBack();
                        break;
                    }

                    $roleId = $user['role_id'];

                    // Check for dependencies based on role
                    if ($roleId == ROLE_TEACHER) {
                        // Check if teacher has classes
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count FROM classes c
                            JOIN teachers t ON t.teacher_id = c.teacher_id
                            WHERE t.user_id = :user_id
                        ");
                        $stmt->execute(['user_id' => $userId]);
                        if ($stmt->fetch()['count'] > 0) {
                            $error = 'Cannot delete: Teacher has assigned classes.';
                            $pdo->rollBack();
                            break;
                        }

                        // Delete teacher record
                        $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id = :user_id");
                        $stmt->execute(['user_id' => $userId]);

                    } elseif ($roleId == ROLE_STUDENT) {
                        // Check if student has grades
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count FROM grades g
                            JOIN enrollments e ON e.enroll_id = g.enroll_id
                            JOIN students s ON s.student_id = e.student_id
                            WHERE s.user_id = :user_id
                        ");
                        $stmt->execute(['user_id' => $userId]);
                        if ($stmt->fetch()['count'] > 0) {
                            $error = 'Cannot delete: Student has grades.';
                            $pdo->rollBack();
                            break;
                        }

                        // Check if student has attendance records
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as count FROM attendance a
                            JOIN enrollments e ON e.enroll_id = a.enroll_id
                            JOIN students s ON s.student_id = e.student_id
                            WHERE s.user_id = :user_id
                        ");
                        $stmt->execute(['user_id' => $userId]);
                        if ($stmt->fetch()['count'] > 0) {
                            $error = 'Cannot delete: Student has attendance records.';
                            $pdo->rollBack();
                            break;
                        }

                        // Delete student-parent relationships
                        $stmt = $pdo->prepare("
                            DELETE FROM student_parent 
                            WHERE student_id = (SELECT student_id FROM students WHERE user_id = :user_id)
                        ");
                        $stmt->execute(['user_id' => $userId]);

                        // Delete student record
                        $stmt = $pdo->prepare("DELETE FROM students WHERE user_id = :user_id");
                        $stmt->execute(['user_id' => $userId]);

                    } elseif ($roleId == ROLE_PARENT) {
                        // Delete student-parent relationships
                        $stmt = $pdo->prepare("
                            DELETE FROM student_parent 
                            WHERE parent_id = (SELECT parent_id FROM parents WHERE user_id = :user_id)
                        ");
                        $stmt->execute(['user_id' => $userId]);

                        // Delete parent record
                        $stmt = $pdo->prepare("DELETE FROM parents WHERE user_id = :user_id");
                        $stmt->execute(['user_id' => $userId]);
                    }

                    // Delete user record
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                    $stmt->execute(['user_id' => $userId]);

                    // Commit transaction
                    $pdo->commit();

                    $message = "User deleted successfully.";
                    break;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Load user details for editing if user_id is in GET
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $userId = (int)$_GET['edit'];
    $userDetails = getUserDetails($userId);
}

/**
 * Retrieves detailed information about a specific user
 *
 * @param int $userId The user ID to get details for
 * @return array|null User details or null if not found
 */
function getUserDetails($userId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.role_id, r.name as role_name
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.user_id = :user_id
    ");
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    // Get role-specific details
    if ($user['role_id'] == ROLE_STUDENT) {
        $stmt = $pdo->prepare("
            SELECT first_name, last_name, dob, class_code
            FROM students
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $studentDetails = $stmt->fetch();

        if ($studentDetails) {
            $user = array_merge($user, $studentDetails);
        }
    }

    return $user;
}

/**
 * Get user's role ID by user ID
 *
 * @param int $userId The user ID
 * @return int|null The role ID or null if not found
 */
function getUserRoleById($userId) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();

    return $result ? $result['role_id'] : null;
}

/**
 * Displays a table of all users with management actions
 *
 * @return void
 */
function displayUserList() {
    global $pdo;

    // Get all users with roles
    $stmt = $pdo->query("
        SELECT u.user_id, u.username, r.role_id, r.name as role_name, u.created_at
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        ORDER BY u.created_at DESC
    ");

    $users = $stmt->fetchAll();
?>
    <div class="user-list">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['user_id']) ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['role_name']) ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($user['created_at']))) ?></td>
                    <td class="actions">
                        <a href="?edit=<?= $user['user_id'] ?>" class="btn btn-edit">Edit</a>
                        <button class="btn btn-danger" onclick="showPasswordResetModal(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">Reset Password</button>
                        <button class="btn btn-danger" onclick="confirmDelete(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php
}
?>

<main class="container">
    <h1>User Management</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= empty($userDetails) ? 'active' : '' ?>" href="#" onclick="showTab('user-list')">User List</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= !empty($userDetails) ? 'active' : '' ?>" href="#" onclick="showTab('user-form')">
                        <?= empty($userDetails) ? 'Create User' : 'Edit User' ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body">
            <div id="user-list" class="tab-content <?= empty($userDetails) ? 'active' : '' ?>">
                <?php displayUserList(); ?>
            </div>

            <div id="user-form" class="tab-content <?= !empty($userDetails) ? 'active' : '' ?>">
                <form method="post" action="users.php">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="<?= empty($userDetails) ? 'create' : 'update' ?>">

                    <?php if (!empty($userDetails)): ?>
                        <input type="hidden" name="user_id" value="<?= $userDetails['user_id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($userDetails['username'] ?? '') ?>" required>
                    </div>

                    <?php if (empty($userDetails)): ?>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="form-group">
                            <label for="role_id">Role</label>
                            <select class="form-control" id="role_id" name="role_id" onchange="toggleRoleFields()" required>
                                <?php
                                // Get all roles
                                $stmt = $pdo->query("SELECT role_id, name FROM roles ORDER BY role_id");
                                $roles = $stmt->fetchAll();

                                foreach ($roles as $role): ?>
                                    <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="student-fields" class="role-fields" style="display: none;">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name">
                            </div>
                        </div>

                        <div id="parent-fields" class="role-fields" style="display: none;">
                            <div class="form-group">
                                <label for="linked_student_id">Link to Student</label>
                                <select class="form-control" id="linked_student_id" name="linked_student_id">
                                    <option value="">-- Select Student --</option>
                                    <?php
                                    // Get all students
                                    $stmt = $pdo->query("
                                        SELECT s.student_id, s.first_name, s.last_name 
                                        FROM students s
                                        ORDER BY s.last_name, s.first_name
                                    ");
                                    $students = $stmt->fetchAll();

                                    foreach ($students as $student): ?>
                                        <option value="<?= $student['student_id'] ?>">
                                            <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($userDetails['role_id'] == ROLE_STUDENT): ?>
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                       value="<?= htmlspecialchars($userDetails['first_name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                       value="<?= htmlspecialchars($userDetails['last_name'] ?? '') ?>">
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?= empty($userDetails) ? 'Create User' : 'Update User' ?>
                        </button>
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Password Reset Modal -->
<div id="password-reset-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('password-reset-modal')">&times;</span>
        <h2>Reset Password</h2>
        <form id="reset-password-form" method="post" action="users.php">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset-user-id">

            <p>Resetting password for: <span id="reset-username"></span></p>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" class="form-control" id="new_password" name="new_password" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-warning">Reset Password</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('password-reset-modal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('delete-modal')">&times;</span>
        <h2>Confirm Deletion</h2>
        <form id="delete-form" method="post" action="users.php">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="delete-user-id">

            <p>Are you sure you want to delete user: <span id="delete-username"></span>?</p>
            <p class="text-danger">Warning: This action cannot be undone!</p>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Delete User</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('delete-modal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');

    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    event.target.classList.add('active');
}

function toggleRoleFields() {
    const roleId = document.getElementById('role_id').value;
    const studentFields = document.getElementById('student-fields');
    const parentFields = document.getElementById('parent-fields');

    studentFields.style.display = (roleId === <?= ROLE_STUDENT ?>) ? 'block' : 'none';
    parentFields.style.display = (roleId === <?= ROLE_PARENT ?>) ? 'block' : 'none';
}

function showPasswordResetModal(userId, username) {
    document.getElementById('reset-user-id').value = userId;
    document.getElementById('reset-username').textContent = username;
    document.getElementById('password-reset-modal').style.display = 'block';
}

function confirmDelete(userId, username) {
    document.getElementById('delete-user-id').value = userId;
    document.getElementById('delete-username').textContent = username;
    document.getElementById('delete-modal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Initialize role fields visibility
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('role_id')) {
        toggleRoleFields();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
