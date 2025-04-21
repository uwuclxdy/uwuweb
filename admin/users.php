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

// CSS styles are included in header.php

// Ensure only administrators can access this page
requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
if (!$pdo) {
    error_log("Database connection failed in admin/users.php");
    die("Database connection failed. Please check the error log for details.");
}
$message = '';
$userDetails = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
    } else {
        // Create new user
        if (isset($_POST['create_user'])) {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
            $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
            $role = isset($_POST['role']) ? (int)$_POST['role'] : 0;

            if (empty($username) || empty($password) || empty($email) || empty($firstName) || empty($lastName) || $role === 0) {
                $message = 'Please complete all required fields.';
            } else if (strlen($password) < 8) {
                $message = 'Password must be at least 8 characters long.';
            } else {
                // Check if username or email already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username");
                $stmt->execute(['username' => $username]);
                if ($stmt->rowCount() > 0) {
                    $message = 'Username or email already exists.';
                } else {
                    // Create user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (username, pass_hash, role_id)
                        VALUES (:username, :password, :role)"
                    );
                    $success = $stmt->execute([
                        'username' => $username,
                        'password' => $hashedPassword,
                        'role' => $role
                    ]);

                    if ($success) {
                        $userId = $pdo->lastInsertId();

                        // Create role-specific record if needed
                        switch ($role) {
                            case ROLE_TEACHER:
                                $stmt = $pdo->prepare("INSERT INTO teachers (user_id) VALUES (:user_id)");
                                $stmt->execute(['user_id' => $userId]);
                                break;
                            case ROLE_STUDENT:
                                $classCode = isset($_POST['class_code']) ? trim($_POST['class_code']) : '';
                                $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, class_code, dob) VALUES (:user_id, :first_name, :last_name, :class_code, :dob)");
                                $stmt->execute(['user_id' => $userId, 'first_name' => $firstName, 'last_name' => $lastName, 'class_code' => $classCode, 'dob' => NULL]);
                                break;
                            case ROLE_PARENT:
                                // Parents table only has user_id based on the database schema
                                $stmt = $pdo->prepare("INSERT INTO parents (user_id) VALUES (:user_id)");
                                $stmt->execute(['user_id' => $userId]);
                                
                                // Link parent to student if specified
                                if (isset($_POST['linked_student_id']) && !empty($_POST['linked_student_id'])) {
                                    $parentId = $pdo->lastInsertId();
                                    $studentId = (int)$_POST['linked_student_id'];
                                    $stmt = $pdo->prepare("INSERT INTO student_parent (student_id, parent_id) VALUES (:student_id, :parent_id)");
                                    $stmt->execute(['student_id' => $studentId, 'parent_id' => $parentId]);
                                }
                                break;
                        }

                        $message = 'User created successfully.';
                    } else {
                        $message = 'Error creating user. Please try again.';
                    }
                }
            }
        }

        // Update user
        else if (isset($_POST['update_user'])) {
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
            $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
            $classCode = isset($_POST['class_code']) ? trim($_POST['class_code']) : '';
            $dob = isset($_POST['dob']) && !empty($_POST['dob']) ? $_POST['dob'] : null;

            if ($userId <= 0 || empty($username) || empty($firstName) || empty($lastName)) {
                $message = 'Please complete all required fields.';
            } else {
                // Start a transaction
                $pdo->beginTransaction();
                try {
                    // Check if username belongs to another user
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username AND user_id != :user_id");
                    $stmt->execute([
                        'username' => $username, 
                        'user_id' => $userId
                    ]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = 'Username already in use by another user.';
                        $pdo->rollBack();
                    } else {
                        // Update username
                        $stmt = $pdo->prepare("UPDATE users SET username = :username WHERE user_id = :user_id");
                        $success = $stmt->execute([
                            'username' => $username,
                            'user_id' => $userId
                        ]);
                        
                        if ($success) {
                            // Get user role
                            $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
                            $stmt->execute(['user_id' => $userId]);
                            $userRole = $stmt->fetch()['role_id'];
                            
                            // Update role-specific information
                            if ($userRole == ROLE_STUDENT) {
                                $stmt = $pdo->prepare(
                                    "UPDATE students 
                                     SET first_name = :first_name, 
                                         last_name = :last_name,
                                         class_code = :class_code,
                                         dob = :dob
                                     WHERE user_id = :user_id"
                                );
                                $stmt->execute([
                                    'first_name' => $firstName,
                                    'last_name' => $lastName,
                                    'class_code' => $classCode,
                                    'dob' => $dob,
                                    'user_id' => $userId
                                ]);
                            } elseif ($userRole == ROLE_PARENT) {
                                // The parents table doesn't have first_name or last_name columns
                                // No need to update name fields for parents
                                
                                // Handle student links if provided
                                if (isset($_POST['linked_student_id']) && !empty($_POST['linked_student_id'])) {
                                    $studentId = (int)$_POST['linked_student_id'];
                                    
                                    // Get parent ID
                                    $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = :user_id");
                                    $stmt->execute(['user_id' => $userId]);
                                    $parentId = $stmt->fetch()['parent_id'];
                                    
                                    // Check if link already exists
                                    $stmt = $pdo->prepare("SELECT * FROM student_parent WHERE student_id = :student_id AND parent_id = :parent_id");
                                    $stmt->execute([
                                        'student_id' => $studentId,
                                        'parent_id' => $parentId
                                    ]);
                                    
                                    if ($stmt->rowCount() == 0) {
                                        // Create new link
                                        $stmt = $pdo->prepare("INSERT INTO student_parent (student_id, parent_id) VALUES (:student_id, :parent_id)");
                                        $stmt->execute([
                                            'student_id' => $studentId,
                                            'parent_id' => $parentId
                                        ]);
                                    }
                                }
                            } elseif ($userRole == ROLE_TEACHER) {
                                // The teachers table doesn't have first_name or last_name columns
                                // No need to update name fields for teachers
                            }
                            
                            $pdo->commit();
                            $message = 'User updated successfully.';
                        } else {
                            $pdo->rollBack();
                            $message = 'Error updating user. Please try again.';
                        }
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $message = 'Database error: ' . $e->getMessage();
                }
            }
        }

        // Reset password
        else if (isset($_POST['reset_password'])) {
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $newPassword = $_POST['new_password'] ?? '';

            if ($userId <= 0 || empty($newPassword)) {
                $message = 'Please provide a new password.';
            } else if (strlen($newPassword) < 8) {
                $message = 'Password must be at least 8 characters long.';
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "UPDATE users 
                     SET pass_hash = :password
                     WHERE user_id = :user_id"
                );
                $success = $stmt->execute([
                    'user_id' => $userId,
                    'password' => $hashedPassword
                ]);

                if ($success) {
                    $message = 'Password reset successfully.';
                } else {
                    $message = 'Error resetting password. Please try again.';
                }
            }
        }

        // Delete user
        else if (isset($_POST['delete_user'])) {
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

            if ($userId <= 0) {
                $message = 'Invalid user ID.';
            } else if ($userId === getUserId()) {
                $message = 'You cannot delete your own account.';
            } else {
                // Check if user has any dependencies before deleting
                $canDelete = true;
                $role = null;

                // Get user role
                $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $userId]);
                $userData = $stmt->fetch();

                if ($userData) {
                    $role = $userData['role_id'];

                    switch ($role) {
                        case ROLE_TEACHER:
                            $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM classes WHERE teacher_id = (SELECT teacher_id FROM teachers WHERE user_id = :user_id)");
                            $stmt->execute(['user_id' => $userId]);
                            if ($stmt->fetch()['count'] > 0) {
                                $canDelete = false;
                                $message = 'Cannot delete user: This teacher has assigned classes.';
                            }
                            break;
                        case ROLE_STUDENT:
                            $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM enrollments WHERE student_id = (SELECT student_id FROM students WHERE user_id = :user_id)");
                            $stmt->execute(['user_id' => $userId]);
                            if ($stmt->fetch()['count'] > 0) {
                                $canDelete = false;
                                $message = 'Cannot delete user: This student is enrolled in classes.';
                            }
                            break;
                        case ROLE_PARENT:
                            $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM student_parent WHERE parent_id = (SELECT parent_id FROM parents WHERE user_id = :user_id)");
                            $stmt->execute(['user_id' => $userId]);
                            if ($stmt->fetch()['count'] > 0) {
                                $canDelete = false;
                                $message = 'Cannot delete user: This parent has linked students.';
                            }
                            break;
                    }

                    if ($canDelete) {
                        // Delete role-specific records first
                        switch ($role) {
                            case ROLE_TEACHER:
                                $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id = :user_id");
                                $stmt->execute(['user_id' => $userId]);
                                break;
                            case ROLE_STUDENT:
                                $stmt = $pdo->prepare("DELETE FROM students WHERE user_id = :user_id");
                                $stmt->execute(['user_id' => $userId]);
                                break;
                            case ROLE_PARENT:
                                $stmt = $pdo->prepare("DELETE FROM parents WHERE user_id = :user_id");
                                $stmt->execute(['user_id' => $userId]);
                                break;
                        }

                        // Then delete user
                        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                        $stmt->execute(['user_id' => $userId]);

                        $message = 'User deleted successfully.';
                    }
                } else {
                    $message = 'User not found.';
                }
            }
        }
    }
}

// Get user details if ID is provided in URL
if (isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    if ($userId > 0) {
        // Get base user details
        $stmt = $pdo->prepare(
            "SELECT u.user_id, u.username, u.role_id, u.created_at,
             r.name AS role_name
             FROM users u 
             JOIN roles r ON u.role_id = r.role_id
             WHERE u.user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
        $userDetails = $stmt->fetch();
        
        if ($userDetails) {
            // Get role-specific details
            switch ($userDetails['role_id']) {
                case ROLE_STUDENT:
                    $stmt = $pdo->prepare(
                        "SELECT s.first_name, s.last_name, s.class_code, s.dob, s.student_id
                         FROM students s
                         WHERE s.user_id = :user_id"
                    );
                    $stmt->execute(['user_id' => $userId]);
                    $studentDetails = $stmt->fetch();
                    if ($studentDetails) {
                        $userDetails = array_merge($userDetails, $studentDetails);
                    }
                    break;
                    
                case ROLE_TEACHER:
                    $stmt = $pdo->prepare(
                        "SELECT t.teacher_id
                         FROM teachers t
                         WHERE t.user_id = :user_id"
                    );
                    $stmt->execute(['user_id' => $userId]);
                    $teacherDetails = $stmt->fetch();
                    if ($teacherDetails) {
                        $userDetails = array_merge($userDetails, $teacherDetails);
                        // Add empty name fields since teachers table doesn't have them
                        $userDetails['first_name'] = '';
                        $userDetails['last_name'] = '';
                    }
                    break;
                    
                case ROLE_PARENT:
                    $stmt = $pdo->prepare(
                        "SELECT p.parent_id
                         FROM parents p
                         WHERE p.user_id = :user_id"
                    );
                    $stmt->execute(['user_id' => $userId]);
                    $parentDetails = $stmt->fetch();
                    if ($parentDetails) {
                        $userDetails = array_merge($userDetails, $parentDetails);
                        // Add empty name fields since parents table doesn't have them
                        $userDetails['first_name'] = '';
                        $userDetails['last_name'] = '';
                        
                        // Get linked students
                        $stmt = $pdo->prepare(
                            "SELECT s.student_id, s.first_name, s.last_name
                             FROM students s
                             JOIN student_parent sp ON s.student_id = sp.student_id
                             WHERE sp.parent_id = :parent_id"
                        );
                        $stmt->execute(['parent_id' => $parentDetails['parent_id']]);
                        $userDetails['linked_students'] = $stmt->fetchAll();
                    }
                    break;
            }
            
            // Add empty email field for backward compatibility
            $userDetails['email'] = '';
        }
    }
}

// Get all users for listing
$stmt = $pdo->prepare(
    "SELECT u.user_id, u.username, u.role_id, u.created_at,
     r.name AS role_name
     FROM users u
     JOIN roles r ON u.role_id = r.role_id
     ORDER BY u.role_id, u.username"
);
$stmt->execute();
$users = $stmt->fetchAll();

// Get names for all users in a single optimized query
$userIds = array_column($users, 'user_id');
if (!empty($userIds)) {
    // Get student names
    $stmt = $pdo->prepare(
        "SELECT user_id, first_name, last_name 
         FROM students 
         WHERE user_id IN (" . implode(',', $userIds) . ")"
    );
    $stmt->execute();
    $studentNames = $stmt->fetchAll(PDO::FETCH_GROUP);
    
    // Teachers table doesn't have first_name and last_name columns according to schema
    $stmt = $pdo->prepare(
        "SELECT user_id, teacher_id
         FROM teachers 
         WHERE user_id IN (" . implode(',', $userIds) . ")"
    );
    $stmt->execute();
    $teacherIds = $stmt->fetchAll(PDO::FETCH_GROUP);
    
    // Parents table doesn't have first_name and last_name columns according to schema
    $stmt = $pdo->prepare(
        "SELECT user_id, parent_id
         FROM parents 
         WHERE user_id IN (" . implode(',', $userIds) . ")"
    );
    $stmt->execute();
    $parentIds = $stmt->fetchAll(PDO::FETCH_GROUP);
    
    // Merge names into user records
    foreach ($users as &$user) {
        $userId = $user['user_id'];
        $role = $user['role_id'];
        
        switch ($role) {
            case ROLE_STUDENT:
                if (isset($studentNames[$userId]) && !empty($studentNames[$userId][0])) {
                    $user['first_name'] = $studentNames[$userId][0]['first_name'];
                    $user['last_name'] = $studentNames[$userId][0]['last_name'];
                } else {
                    $user['first_name'] = '';
                    $user['last_name'] = '';
                }
                break;
            case ROLE_TEACHER:
                // Teachers don't have first/last names in the database schema
                // Set default display values based on username
                $user['first_name'] = '';
                $user['last_name'] = 'Teacher: ' . $user['username'];
                break;
            case ROLE_PARENT:
                // Parents don't have first/last names in the database schema
                // Set default display values based on username
                $user['first_name'] = '';
                $user['last_name'] = 'Parent: ' . $user['username'];
                break;
        }
        
        // Make sure we have name fields even if they're empty
        if (!isset($user['first_name'])) $user['first_name'] = '';
        if (!isset($user['last_name'])) $user['last_name'] = '';
    }
    unset($user); // Break the reference
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>

<div class="card card-entrance mt-xl">
    <h1 class="mt-0 mb-lg text-accent">User Management</h1>

    <?php if (!empty($message)): ?>
        <div class="alert <?= strpos($message, 'successfully') !== false ? 'status-success' : 'status-error' ?> mb-lg">
            <div class="d-flex items-center gap-sm">
                <?php if (strpos($message, 'successfully') !== false): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-between items-center mb-lg">
        <div>
            <p class="text-secondary mb-0">Manage system users, their roles, and permissions</p>
            <p class="text-secondary mt-xs mb-0"><small><?= count($users) ?> total users in the system</small></p>
        </div>
        <div class="d-flex gap-sm">
            <div class="search-container">
                <input type="text" id="userSearchInput" class="form-input" placeholder="Search users...">
                <span class="input-icon"><i class="fas fa-search"></i></span>
            </div>
            <button class="btn btn-primary" id="createUserBtn">
                <i class="fas fa-plus-circle"></i> Create New User
            </button>
        </div>
    </div>

    <?php if (empty($users)): ?>
        <div class="bg-tertiary p-lg text-center rounded mb-lg shadow-sm">
            <p class="mb-sm">No users found.</p>
            <p class="text-secondary mt-0">Click "Create New User" to add the first user.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive mb-lg shadow-sm">
            <table class="data-table" id="usersTable">
                <thead>
                    <tr>
                        <th><i class="fas fa-id-card"></i> ID</th>
                        <th><i class="fas fa-user"></i> Username</th>
                        <th><i class="fas fa-signature"></i> Full Name</th>
                        <th><i class="fas fa-user-tag"></i> Role</th>
                        <th><i class="fas fa-info-circle"></i> Details</th>
                        <th><i class="fas fa-calendar"></i> Created</th>
                        <th><i class="fas fa-cogs"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="hover-effect user-row" data-role="<?= $user['role_id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                            <td><span class="badge"><?= $user['user_id'] ?></span></td>
                            <td><strong><?= htmlspecialchars($user['username']) ?></strong></td>
                            <td>
                                <?php 
                                $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
                                echo !empty($fullName) ? htmlspecialchars($fullName) : '<span class="text-disabled">Not provided</span>'; 
                                ?>
                            </td>
                            <td>
                                <?php
                                $roleBadge = '';
                                switch ($user['role_id']) {
                                    case ROLE_ADMIN:
                                        $roleBadge = '<span class="badge profile-admin"><i class="fas fa-shield-alt"></i> Administrator</span>';
                                        break;
                                    case ROLE_TEACHER:
                                        $roleBadge = '<span class="badge profile-teacher"><i class="fas fa-chalkboard-teacher"></i> Teacher</span>';
                                        break;
                                    case ROLE_STUDENT:
                                        $roleBadge = '<span class="badge profile-student"><i class="fas fa-user-graduate"></i> Student</span>';
                                        break;
                                    case ROLE_PARENT:
                                        $roleBadge = '<span class="badge profile-parent"><i class="fas fa-user-friends"></i> Parent</span>';
                                        break;
                                    default:
                                        $roleBadge = '<span class="badge"><i class="fas fa-question-circle"></i> Unknown</span>';
                                }
                                echo $roleBadge;
                                ?>
                            </td>
                            <td>
                                <?php 
                                // Show role-specific information
                                switch ($user['role_id']) {
                                    case ROLE_STUDENT:
                                        // Get class code for student if available
                                        $stmt = $pdo->prepare("SELECT class_code FROM students WHERE user_id = :user_id");
                                        $stmt->execute(['user_id' => $user['user_id']]);
                                        $classCode = $stmt->fetch(PDO::FETCH_COLUMN);
                                        echo !empty($classCode) ? '<span class="badge bg-tertiary"><i class="fas fa-users"></i> Class: ' . htmlspecialchars($classCode) . '</span>' : '<span class="text-disabled">No class assigned</span>';
                                        break;
                                    case ROLE_PARENT:
                                        // Get linked children count
                                        $stmt = $pdo->prepare(
                                            "SELECT COUNT(*) FROM student_parent sp
                                             JOIN parents p ON sp.parent_id = p.parent_id
                                             WHERE p.user_id = :user_id"
                                        );
                                        $stmt->execute(['user_id' => $user['user_id']]);
                                        $childCount = $stmt->fetchColumn();
                                        
                                        if ($childCount > 0) {
                                            echo '<span class="badge bg-tertiary"><i class="fas fa-child"></i> ' . $childCount . ' ' . ($childCount == 1 ? 'child' : 'children') . ' linked</span>';
                                        } else {
                                            echo '<span class="text-disabled">No children linked</span>';
                                        }
                                        break;
                                    case ROLE_TEACHER:
                                        // Get class count for teacher
                                        $stmt = $pdo->prepare(
                                            "SELECT COUNT(*) FROM classes c
                                             JOIN teachers t ON c.teacher_id = t.teacher_id
                                             WHERE t.user_id = :user_id"
                                        );
                                        $stmt->execute(['user_id' => $user['user_id']]);
                                        $classCount = $stmt->fetchColumn();
                                        
                                        if ($classCount > 0) {
                                            echo '<span class="badge bg-tertiary"><i class="fas fa-chalkboard"></i> ' . $classCount . ' ' . ($classCount == 1 ? 'class' : 'classes') . ' assigned</span>';
                                        } else {
                                            echo '<span class="text-disabled">No classes assigned</span>';
                                        }
                                        break;
                                    default:
                                        echo '<span class="text-disabled">No additional details</span>';
                                }
                                ?>
                            </td>
                            <td data-sort="<?= strtotime($user['created_at']) ?>">
                                <span title="<?= date('Y-m-d H:i:s', strtotime($user['created_at'])) ?>">
                                    <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-sm flex-wrap">
                                    <a href="?id=<?= $user['user_id'] ?>" class="btn btn-secondary btn-sm" title="Edit User">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button class="btn btn-secondary btn-sm reset-password-btn" data-id="<?= $user['user_id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>" title="Reset Password">
                                        <i class="fas fa-key"></i> Reset
                                    </button>
                                    <button class="btn btn-sm delete-user-btn" data-id="<?= $user['user_id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>" title="Delete User">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="d-flex justify-between items-center mb-lg">
            <div class="filter-controls d-flex gap-sm">
                <button class="btn btn-sm role-filter active" data-role="all">All Users</button>
                <button class="btn btn-sm role-filter" data-role="<?= ROLE_ADMIN ?>">Admins</button>
                <button class="btn btn-sm role-filter" data-role="<?= ROLE_TEACHER ?>">Teachers</button>
                <button class="btn btn-sm role-filter" data-role="<?= ROLE_STUDENT ?>">Students</button>
                <button class="btn btn-sm role-filter" data-role="<?= ROLE_PARENT ?>">Parents</button>
            </div>
            <div class="pagination-info">
                <span id="showing-entries">Showing all <?= count($users) ?> entries</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Create User Modal -->
    <div class="modal" id="createUserModal" style="display: none;">
        <div class="modal-overlay" id="createUserModalOverlay"></div>
        <div class="modal-container">
            <div class="card shadow-lg">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0 text-accent"><i class="fas fa-user-plus"></i> Create New User</h3>
                    <button type="button" class="btn-close" id="closeCreateUserModal">&times;</button>
                </div>

                <form method="POST" action="users.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="form-group">
                        <label for="username" class="form-label">Username <span class="text-accent">*</span></label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="username" name="username" class="form-input" required>
                        </div>
                        <div class="feedback-text"><i class="fas fa-info-circle"></i> Unique username for login</div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password <span class="text-accent">*</span></label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-input" required>
                        </div>
                        <div class="feedback-text"><i class="fas fa-info-circle"></i> Must be at least 8 characters long</div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email <span class="text-accent">*</span></label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-input" required>
                        </div>
                    </div>

                    <div class="d-flex gap-md">
                        <div class="form-group" style="flex: 1;">
                            <label for="first_name" class="form-label">First Name <span class="text-accent">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="form-input" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="last_name" class="form-label">Last Name <span class="text-accent">*</span></label>
                            <input type="text" id="last_name" name="last_name" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="role" class="form-label">Role <span class="text-accent">*</span></label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-user-tag"></i></span>
                            <select id="role" name="role" class="form-input" required>
                                <option value="">-- Select Role --</option>
                                <option value="<?= ROLE_ADMIN ?>">Administrator</option>
                                <option value="<?= ROLE_TEACHER ?>">Teacher</option>
                                <option value="<?= ROLE_STUDENT ?>">Student</option>
                                <option value="<?= ROLE_PARENT ?>">Parent</option>
                            </select>
                        </div>
                    </div>

                    <!-- Role-specific fields -->
                    <div id="studentFields" class="role-specific-fields" style="display: none;">
                        <h4 class="mt-lg mb-md text-secondary"><i class="fas fa-user-graduate"></i> Student Details</h4>
                        
                        <div class="form-group">
                            <label for="class_code" class="form-label">Class Code</label>
                            <div class="d-flex">
                                <span class="input-icon"><i class="fas fa-users"></i></span>
                                <input type="text" id="class_code" name="class_code" class="form-input">
                            </div>
                            <div class="feedback-text"><i class="fas fa-info-circle"></i> Student's class identifier</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dob" class="form-label">Date of Birth</label>
                            <div class="d-flex">
                                <span class="input-icon"><i class="fas fa-calendar"></i></span>
                                <input type="date" id="dob" name="dob" class="form-input">
                            </div>
                        </div>
                    </div>

                    <div id="parentFields" class="role-specific-fields" style="display: none;">
                        <h4 class="mt-lg mb-md text-secondary"><i class="fas fa-user-friends"></i> Parent Details</h4>
                        
                        <div class="form-group">
                            <label for="linked_student_id" class="form-label">Link to Student</label>
                            <div class="d-flex">
                                <span class="input-icon"><i class="fas fa-child"></i></span>
                                <select id="linked_student_id" name="linked_student_id" class="form-input">
                                    <option value="">-- Select Student (Optional) --</option>
                                    <?php
                                    // Get all students
                                    $stmt = $pdo->prepare(
                                        "SELECT s.student_id, s.first_name, s.last_name 
                                        FROM students s
                                        ORDER BY s.last_name, s.first_name"
                                    );
                                    $stmt->execute();
                                    $allStudents = $stmt->fetchAll();
                                    
                                    foreach ($allStudents as $student): ?>
                                        <option value="<?= $student['student_id'] ?>">
                                            <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="feedback-text"><i class="fas fa-info-circle"></i> You can link more students later</div>
                        </div>
                    </div>

                    <div id="teacherFields" class="role-specific-fields" style="display: none;">
                        <h4 class="mt-lg mb-md text-secondary"><i class="fas fa-chalkboard-teacher"></i> Teacher Details</h4>
                        
                        <div class="form-group">
                            <label for="specialization" class="form-label">Specialization</label>
                            <div class="d-flex">
                                <span class="input-icon"><i class="fas fa-book"></i></span>
                                <input type="text" id="specialization" name="specialization" class="form-input">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-md justify-end mt-lg">
                        <button type="button" class="btn" id="cancelCreateUserBtn"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary"><i class="fas fa-save"></i> Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal" id="editUserModal" style="display: <?= $userDetails ? 'flex' : 'none' ?>;">
        <div class="modal-overlay" id="editUserModalOverlay"></div>
        <div class="modal-container">
            <div class="card shadow-lg">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0 text-accent"><i class="fas fa-user-edit"></i> Edit User</h3>
                    <button type="button" class="btn-close" id="closeEditUserModal">&times;</button>
                </div>

                <form method="POST" action="users.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="user_id" value="<?= $userDetails ? $userDetails['user_id'] : '' ?>">

                    <div class="user-info-panel bg-tertiary p-md rounded mb-lg">
                        <div class="d-flex justify-between items-center">
                            <div>
                                <div class="text-secondary mb-xs"><i class="fas fa-user-circle"></i> Account Information</div>
                                <div class="text-primary text-bold"><?= $userDetails ? htmlspecialchars($userDetails['username']) : '' ?></div>
                                <div class="text-secondary mt-xs">
                                    <small>
                                        <i class="fas fa-user-tag"></i> <?= $userDetails ? htmlspecialchars($userDetails['role_name']) : '' ?> &bull; 
                                        <i class="fas fa-calendar"></i> Created <?= $userDetails ? date('d.m.Y', strtotime($userDetails['created_at'])) : '' ?>
                                    </small>
                                </div>
                            </div>
                            <?php if ($userDetails): ?>
                                <div>
                                    <?php
                                    $roleBadge = '';
                                    switch ($userDetails['role_id']) {
                                        case ROLE_ADMIN:
                                            $roleBadge = '<span class="badge profile-admin"><i class="fas fa-shield-alt"></i> Administrator</span>';
                                            break;
                                        case ROLE_TEACHER:
                                            $roleBadge = '<span class="badge profile-teacher"><i class="fas fa-chalkboard-teacher"></i> Teacher</span>';
                                            break;
                                        case ROLE_STUDENT:
                                            $roleBadge = '<span class="badge profile-student"><i class="fas fa-user-graduate"></i> Student</span>';
                                            break;
                                        case ROLE_PARENT:
                                            $roleBadge = '<span class="badge profile-parent"><i class="fas fa-user-friends"></i> Parent</span>';
                                            break;
                                    }
                                    echo $roleBadge;
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_username" class="form-label">Username <span class="text-accent">*</span></label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-user"></i></span>
                            <input type="text" id="edit_username" name="username" class="form-input" value="<?= $userDetails ? htmlspecialchars($userDetails['username']) : '' ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_email" class="form-label">Email <span class="text-accent">*</span></label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="edit_email" name="email" class="form-input" value="<?= $userDetails ? htmlspecialchars($userDetails['email']) : '' ?>" required>
                        </div>
                    </div>

                    <div class="d-flex gap-md">
                        <div class="form-group" style="flex: 1;">
                            <label for="edit_first_name" class="form-label">First Name <span class="text-accent">*</span></label>
                            <input type="text" id="edit_first_name" name="first_name" class="form-input" 
                                   value="<?= $userDetails && isset($userDetails['first_name']) ? htmlspecialchars($userDetails['first_name']) : '' ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="edit_last_name" class="form-label">Last Name <span class="text-accent">*</span></label>
                            <input type="text" id="edit_last_name" name="last_name" class="form-input" 
                                   value="<?= $userDetails && isset($userDetails['last_name']) ? htmlspecialchars($userDetails['last_name']) : '' ?>" required>
                        </div>
                    </div>

                    <?php if ($userDetails && $userDetails['role_id'] == ROLE_STUDENT): ?>
                        <!-- Student-specific fields -->
                        <div class="student-edit-fields">
                            <h4 class="mt-lg mb-md text-secondary"><i class="fas fa-user-graduate"></i> Student Details</h4>
                            
                            <div class="form-group">
                                <label for="edit_class_code" class="form-label">Class Code</label>
                                <div class="d-flex">
                                    <span class="input-icon"><i class="fas fa-users"></i></span>
                                    <input type="text" id="edit_class_code" name="class_code" class="form-input" value="<?= $userDetails ? htmlspecialchars($userDetails['class_code'] ?? '') : '' ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_dob" class="form-label">Date of Birth</label>
                                <div class="d-flex">
                                    <span class="input-icon"><i class="fas fa-calendar"></i></span>
                                    <input type="date" id="edit_dob" name="dob" class="form-input" value="<?= $userDetails && isset($userDetails['dob']) && $userDetails['dob'] ? date('Y-m-d', strtotime($userDetails['dob'])) : '' ?>">
                                </div>
                            </div>
                            
                            <?php if (isset($userDetails['student_id'])): ?>
                                <div class="student-enrollments bg-tertiary p-md rounded mb-lg mt-md">
                                    <h5 class="mt-0 mb-sm">Class Enrollments</h5>
                                    <?php
                                    // Get student enrollments
                                    $stmt = $pdo->prepare(
                                        "SELECT e.enroll_id, c.title AS class_title, s.name AS subject_name
                                         FROM enrollments e
                                         JOIN classes c ON e.class_id = c.class_id
                                         JOIN subjects s ON c.subject_id = s.subject_id
                                         WHERE e.student_id = :student_id"
                                    );
                                    $stmt->execute(['student_id' => $userDetails['student_id']]);
                                    $enrollments = $stmt->fetchAll();
                                    
                                    if (!empty($enrollments)): ?>
                                        <div class="enrollments-list">
                                            <?php foreach ($enrollments as $enrollment): ?>
                                                <div class="enrollment-item d-flex justify-between">
                                                    <div><i class="fas fa-book"></i> <?= htmlspecialchars($enrollment['subject_name']) ?> - <?= htmlspecialchars($enrollment['class_title']) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-disabled mb-0">No classes enrolled</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($userDetails && $userDetails['role_id'] == ROLE_PARENT): ?>
                        <!-- Parent-specific fields -->
                        <div class="parent-edit-fields">
                            <h4 class="mt-lg mb-md text-secondary"><i class="fas fa-user-friends"></i> Parent Details</h4>
                            
                            <?php if (isset($userDetails['parent_id'])): ?>
                                <div class="form-group">
                                    <label class="form-label">Linked Students</label>
                                    <div class="bg-tertiary p-md rounded mb-md">
                                        <?php if (!empty($userDetails['linked_students'])): ?>
                                            <div class="linked-students-list">
                                                <?php foreach ($userDetails['linked_students'] as $student): ?>
                                                    <div class="student-item d-flex justify-between items-center mb-xs">
                                                        <div><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-disabled mb-0">No students linked to this parent</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="linked_student_id" class="form-label">Link Another Student</label>
                                    <div class="d-flex">
                                        <span class="input-icon"><i class="fas fa-child"></i></span>
                                        <select id="linked_student_id" name="linked_student_id" class="form-input">
                                            <option value="">-- Select Student --</option>
                                            <?php
                                            // Get all students not already linked to this parent
                                            $linkedStudentIds = array_column($userDetails['linked_students'] ?? [], 'student_id');
                                            $sqlNotIn = !empty($linkedStudentIds) ? " AND s.student_id NOT IN (" . implode(',', $linkedStudentIds) . ")" : "";
                                            
                                            $stmt = $pdo->prepare(
                                                "SELECT s.student_id, s.first_name, s.last_name 
                                                FROM students s
                                                WHERE 1=1 $sqlNotIn
                                                ORDER BY s.last_name, s.first_name"
                                            );
                                            $stmt->execute();
                                            $availableStudents = $stmt->fetchAll();
                                            
                                            foreach ($availableStudents as $student): ?>
                                                <option value="<?= $student['student_id'] ?>">
                                                    <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($userDetails && $userDetails['role_id'] == ROLE_TEACHER): ?>
                        <!-- Teacher-specific fields -->
                        <div class="teacher-edit-fields">
                            <h4 class="mt-lg mb-md text-secondary"><i class="fas fa-chalkboard-teacher"></i> Teacher Details</h4>
                            
                            <?php if (isset($userDetails['teacher_id'])): ?>
                                <div class="bg-tertiary p-md rounded mb-lg">
                                    <h5 class="mt-0 mb-sm">Assigned Classes</h5>
                                    <?php
                                    // Get teacher classes
                                    $stmt = $pdo->prepare(
                                        "SELECT c.class_id, c.title, s.name AS subject_name, t.name AS term_name
                                         FROM classes c
                                         JOIN subjects s ON c.subject_id = s.subject_id
                                         JOIN terms t ON c.term_id = t.term_id
                                         WHERE c.teacher_id = :teacher_id"
                                    );
                                    $stmt->execute(['teacher_id' => $userDetails['teacher_id']]);
                                    $classes = $stmt->fetchAll();
                                    
                                    if (!empty($classes)): ?>
                                        <div class="classes-list">
                                            <?php foreach ($classes as $class): ?>
                                                <div class="class-item d-flex justify-between mb-xs">
                                                    <div><i class="fas fa-book"></i> <?= htmlspecialchars($class['subject_name']) ?> - <?= htmlspecialchars($class['title']) ?></div>
                                                    <div class="text-secondary"><small><?= htmlspecialchars($class['term_name']) ?></small></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-disabled mb-0">No classes assigned to this teacher</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-md justify-end mt-lg">
                        <a href="users.php" class="btn"><i class="fas fa-times"></i> Cancel</a>
                        <button type="submit" name="update_user" class="btn btn-primary"><i class="fas fa-save"></i> Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal" id="resetPasswordModal" style="display: none;">
        <div class="modal-overlay" id="resetPasswordModalOverlay"></div>
        <div class="modal-container">
            <div class="card shadow-lg">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0 text-accent"><i class="fas fa-key"></i> Reset Password</h3>
                    <button type="button" class="btn-close" id="closeResetPasswordModal">&times;</button>
                </div>

                <div class="mb-md bg-tertiary p-md rounded">
                    <p class="mb-0">Enter a new password for user <strong id="resetPasswordUsername" class="text-accent"></strong>.</p>
                </div>

                <form method="POST" action="users.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="user_id" id="resetPasswordUserId" value="">

                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-lock"></i></span>
                            <input type="password" id="new_password" name="new_password" class="form-input" required>
                        </div>
                        <div class="feedback-text"><i class="fas fa-info-circle"></i> Must be at least 8 characters long</div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="d-flex">
                            <span class="input-icon"><i class="fas fa-lock-open"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        </div>
                    </div>

                    <div id="passwordMismatch" class="feedback-text feedback-invalid" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i> Passwords do not match
                    </div>

                    <div class="d-flex gap-md justify-end mt-lg">
                        <button type="button" class="btn" id="cancelResetPasswordBtn"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" name="reset_password" class="btn btn-primary" id="resetPasswordSubmitBtn"><i class="fas fa-key"></i> Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Confirmation Modal -->
    <div class="modal" id="deleteUserModal" style="display: none;">
        <div class="modal-overlay" id="deleteUserModalOverlay"></div>
        <div class="modal-container">
            <div class="card shadow-lg">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0 text-accent"><i class="fas fa-user-slash"></i> Confirm Deletion</h3>
                    <button type="button" class="btn-close" id="closeDeleteUserModal">&times;</button>
                </div>

                <div class="mb-lg">
                    <p class="mb-md">Are you sure you want to delete user "<span id="deletingUsername" class="text-accent text-bold"></span>"?</p>
                    <div class="alert status-warning">
                        <div class="d-flex gap-sm items-center">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <p class="mb-xs">Warning: This action cannot be undone.</p>
                                <p class="mb-0">Users with dependencies (classes, enrollments, etc.) cannot be deleted.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="users.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="user_id" id="deleteUserId" value="">

                    <div class="d-flex gap-md justify-end">
                        <button type="button" class="btn" id="cancelDeleteUserBtn"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" name="delete_user" class="btn btn-primary"><i class="fas fa-trash"></i> Delete User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Additional custom styles for the User Management page */
.hover-effect {
    transition: var(--transition-fast);
}
.hover-effect:hover {
    background-color: rgba(45, 125, 179, 0.05);
}
.input-icon {
    display: flex;
    align-items: center;
    padding: 0 10px;
    background-color: rgba(45, 125, 179, 0.1);
    border-radius: 10px 0 0 10px;
    border: 1px solid var(--bg-tertiary);
    border-right: none;
}
.input-icon + input, .input-icon + select {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    flex: 1;
}
.modal {
    animation: fadeIn 0.3s ease-out;
}
.modal-container {
    animation: modalSlideIn 0.3s ease-out;
}
@keyframes modalSlideIn {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
}
.profile-admin {
    background-color: rgba(145, 61, 136, 0.15);
    color: #c27fba;
    border: 1px solid rgba(145, 61, 136, 0.25);
}
.profile-teacher {
    background-color: rgba(45, 125, 179, 0.15);
    color: #4d9eda;
    border: 1px solid rgba(45, 125, 179, 0.25);
}
.profile-student {
    background-color: rgba(39, 174, 96, 0.15);
    color: #2ecc71;
    border: 1px solid rgba(39, 174, 96, 0.25);
}
.profile-parent {
    background-color: rgba(230, 126, 34, 0.15);
    color: #e67e22;
    border: 1px solid rgba(230, 126, 34, 0.25);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Create user modal
    const createUserBtn = document.getElementById('createUserBtn');
    const createUserModal = document.getElementById('createUserModal');
    const closeCreateUserModal = document.getElementById('closeCreateUserModal');
    const cancelCreateUserBtn = document.getElementById('cancelCreateUserBtn');
    const createUserModalOverlay = document.getElementById('createUserModalOverlay');
    const roleSelect = document.getElementById('role');
    const studentFields = document.getElementById('studentFields');

    function toggleStudentFields() {
        if (roleSelect.value == <?= ROLE_STUDENT ?>) {
            studentFields.style.display = 'block';
        } else {
            studentFields.style.display = 'none';
        }
    }

    if (createUserBtn) {
        createUserBtn.addEventListener('click', function() {
            createUserModal.style.display = 'flex';
        });
    }

    if (closeCreateUserModal) {
        closeCreateUserModal.addEventListener('click', function() {
            createUserModal.style.display = 'none';
        });
    }

    if (cancelCreateUserBtn) {
        cancelCreateUserBtn.addEventListener('click', function() {
            createUserModal.style.display = 'none';
        });
    }

    if (createUserModalOverlay) {
        createUserModalOverlay.addEventListener('click', function() {
            createUserModal.style.display = 'none';
        });
    }

    if (roleSelect) {
        roleSelect.addEventListener('change', toggleStudentFields);
    }

    // Edit user modal
    const closeEditUserModal = document.getElementById('closeEditUserModal');
    const editUserModalOverlay = document.getElementById('editUserModalOverlay');

    if (closeEditUserModal) {
        closeEditUserModal.addEventListener('click', function() {
            window.location.href = 'users.php';
        });
    }

    if (editUserModalOverlay) {
        editUserModalOverlay.addEventListener('click', function() {
            window.location.href = 'users.php';
        });
    }

    // Reset password modal
    const resetPasswordBtns = document.querySelectorAll('.reset-password-btn');
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    const closeResetPasswordModal = document.getElementById('closeResetPasswordModal');
    const cancelResetPasswordBtn = document.getElementById('cancelResetPasswordBtn');
    const resetPasswordModalOverlay = document.getElementById('resetPasswordModalOverlay');
    const resetPasswordUserId = document.getElementById('resetPasswordUserId');
    const resetPasswordUsername = document.getElementById('resetPasswordUsername');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMismatch = document.getElementById('passwordMismatch');
    const resetPasswordSubmitBtn = document.getElementById('resetPasswordSubmitBtn');

    resetPasswordBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            resetPasswordUserId.value = id;
            resetPasswordUsername.textContent = username;
            resetPasswordModal.style.display = 'flex';
        });
    });

    if (closeResetPasswordModal) {
        closeResetPasswordModal.addEventListener('click', function() {
            resetPasswordModal.style.display = 'none';
        });
    }

    if (cancelResetPasswordBtn) {
        cancelResetPasswordBtn.addEventListener('click', function() {
            resetPasswordModal.style.display = 'none';
        });
    }

    if (resetPasswordModalOverlay) {
        resetPasswordModalOverlay.addEventListener('click', function() {
            resetPasswordModal.style.display = 'none';
        });
    }

    // Password validation
    function validatePasswords() {
        if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
            passwordMismatch.style.display = 'block';
            resetPasswordSubmitBtn.disabled = true;
        } else {
            passwordMismatch.style.display = 'none';
            resetPasswordSubmitBtn.disabled = false;
        }
    }

    if (newPassword && confirmPassword) {
        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    }

    // Delete user functionality
    const deleteUserBtns = document.querySelectorAll('.delete-user-btn');
    const deleteUserModal = document.getElementById('deleteUserModal');
    const closeDeleteUserModal = document.getElementById('closeDeleteUserModal');
    const cancelDeleteUserBtn = document.getElementById('cancelDeleteUserBtn');
    const deleteUserId = document.getElementById('deleteUserId');
    const deletingUsername = document.getElementById('deletingUsername');
    const deleteUserModalOverlay = document.getElementById('deleteUserModalOverlay');

    deleteUserBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            deleteUserId.value = id;
            deletingUsername.textContent = username;
            deleteUserModal.style.display = 'flex';
        });
    });

    if (closeDeleteUserModal) {
        closeDeleteUserModal.addEventListener('click', function() {
            deleteUserModal.style.display = 'none';
        });
    }

    if (cancelDeleteUserBtn) {
        cancelDeleteUserBtn.addEventListener('click', function() {
            deleteUserModal.style.display = 'none';
        });
    }

    if (deleteUserModalOverlay) {
        deleteUserModalOverlay.addEventListener('click', function() {
            deleteUserModal.style.display = 'none';
        });
    }
});
</script>

<?php
// Include page footer
include '../includes/footer.php';
?>
