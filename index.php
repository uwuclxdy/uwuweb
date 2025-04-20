<?php
/**
 * Login Page
 *
 * Handles user authentication and redirects to the dashboard
 *
 * Functions:
 * - None (main script file)
 */

require_once 'includes/auth.php';
require_once 'includes/db.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

include 'includes/header.php';

$error = '';
$username = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Simple validation
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        // Attempt to authenticate
        try {
            $pdo = getDBConnection();
            if (!$pdo) {
                error_log("Database connection failed in index.php login");
                $error = 'System error: Unable to connect to database. Please try again later.';
            } else {
                $stmt = $pdo->prepare("SELECT user_id, username, pass_hash, role_id FROM users WHERE username = :username");
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['pass_hash'])) {
                    // Authentication successful
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role_id'] = $user['role_id'];

                    // Redirect to dashboard or saved redirect URL
                    $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header("Location: $redirect");
                    exit;
                }

                $error = 'Invalid username or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<?php /* 
    [LOGIN PAGE PLACEHOLDER]
    Components:
    - Custom HTML head with:
      - Title "Login - uwuweb Grade Management"
      - Login specific CSS stylesheet reference
    
    - Login container card with:
      - Header section with:
        - System name as title (uwuweb)
        - Subtitle "Grade Management System"
      
      - Card body containing:
        - Error alert (when $error is not empty)
        - Success message (when logged_out parameter is present)
        
        - Login form with:
          - Username field (with value preservation)
          - Password field
          - CSRF token (hidden)
          - Submit button "Log In"
*/ ?>

<?php if (!empty($error)): ?>
    <?php /* [ERROR ALERT PLACEHOLDER] - Displays login error message */ ?>
<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
    <?php /* [SUCCESS ALERT PLACEHOLDER] - Displays logout success message */ ?>
<?php endif; ?>
