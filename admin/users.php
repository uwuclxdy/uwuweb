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
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = :username OR email = :email");
                $stmt->execute(['username' => $username, 'email' => $email]);
                if ($stmt->rowCount() > 0) {
                    $message = 'Username or email already exists.';
                } else {
                    // Create user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users (username, password, email, first_name, last_name, role)
                         VALUES (:username, :password, :email, :first_name, :last_name, :role)"
                    );
                    $success = $stmt->execute([
                        'username' => $username,
                        'password' => $hashedPassword,
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'role' => $role
                    ]);
                    
                    if ($success) {
                        $userId = $pdo->lastInsertId();
                        
                        // Create role-specific record if needed
                        switch ($role) {
                            case ROLE_TEACHER:
                                $stmt = $pdo->prepare("INSERT INTO teachers (user_id, first_name, last_name) VALUES (:user_id, :first_name, :last_name)");
                                $stmt->execute(['user_id' => $userId, 'first_name' => $firstName, 'last_name' => $lastName]);
                                break;
                            case ROLE_STUDENT:
                                $classCode = isset($_POST['class_code']) ? trim($_POST['class_code']) : '';
                                $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, class_code) VALUES (:user_id, :first_name, :last_name, :class_code)");
                                $stmt->execute(['user_id' => $userId, 'first_name' => $firstName, 'last_name' => $lastName, 'class_code' => $classCode]);
                                break;
                            case ROLE_PARENT:
                                $stmt = $pdo->prepare("INSERT INTO parents (user_id, first_name, last_name) VALUES (:user_id, :first_name, :last_name)");
                                $stmt->execute(['user_id' => $userId, 'first_name' => $firstName, 'last_name' => $lastName]);
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
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
            $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
            
            if ($userId <= 0 || empty($email) || empty($firstName) || empty($lastName)) {
                $message = 'Please complete all required fields.';
            } else {
                // Check if email belongs to another user
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
                $stmt->execute(['email' => $email, 'user_id' => $userId]);
                if ($stmt->rowCount() > 0) {
                    $message = 'Email already in use by another user.';
                } else {
                    // Update user
                    $stmt = $pdo->prepare(
                        "UPDATE users 
                         SET email = :email, first_name = :first_name, last_name = :last_name
                         WHERE user_id = :user_id"
                    );
                    $success = $stmt->execute([
                        'user_id' => $userId,
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ]);
                    
                    if ($success) {
                        $message = 'User updated successfully.';
                    } else {
                        $message = 'Error updating user. Please try again.';
                    }
                }
            }
        }
        
        // Reset password
        else if (isset($_POST['reset_password'])) {
            $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
            $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            
            if ($userId <= 0 || empty($newPassword)) {
                $message = 'Please provide a new password.';
            } else if (strlen($newPassword) < 8) {
                $message = 'Password must be at least 8 characters long.';
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare(
                    "UPDATE users 
                     SET password = :password
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
                $stmt = $pdo->prepare("SELECT role FROM users WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $userId]);
                $userData = $stmt->fetch();
                
                if ($userData) {
                    $role = $userData['role'];
                    
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
                            $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM parent_student WHERE parent_id = (SELECT parent_id FROM parents WHERE user_id = :user_id)");
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
        // Get user details
        $stmt = $pdo->prepare(
            "SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.role, u.created_at
             FROM users u
             WHERE u.user_id = :user_id"
        );
        $stmt->execute(['user_id' => $userId]);
        $userDetails = $stmt->fetch();
    }
}

// Get all users for listing
$stmt = $pdo->prepare(
    "SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.role, u.created_at 
     FROM users u 
     ORDER BY u.user_id"
);
$stmt->execute();
$users = $stmt->fetchAll();

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include header
include '../includes/header.php';
?>

<?php /* 
    [ADMIN USERS PAGE PLACEHOLDER]
    Components:
    - Page container with admin user management layout
    
    - Page title "User Management"
    
    - Alert message display (when $message is not empty)
      - For success/error notifications
    
    - Action buttons section:
      - "Create New User" button that opens the user creation form
    
    - User listing table with:
      - Headers: ID, Username, Name, Email, Role, Created, Actions
      - For each user:
        - User ID
        - Username
        - Full name (first + last)
        - Email address
        - Role name with appropriate badge
        - Creation date
        - Action buttons (Edit, Reset Password, Delete)
    
    - Modal components:
      1. Create User Modal:
         - Form with fields for:
           - Username
           - Password
           - Email
           - First Name
           - Last Name
           - Role selection
           - Role-specific fields (conditionally shown based on role)
         - Create and Cancel buttons
      
      2. Edit User Modal:
         - Form with fields for:
           - Email
           - First Name
           - Last Name
         - Update and Cancel buttons
      
      3. Reset Password Modal:
         - Form with fields for:
           - New Password
           - Confirm Password
         - Reset and Cancel buttons
      
      4. Delete User Modal:
         - Confirmation message
         - Delete and Cancel buttons
    
    - Interactive features:
      - Form validation
      - Role-specific field toggling
      - Confirmation for delete actions
*/ ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
