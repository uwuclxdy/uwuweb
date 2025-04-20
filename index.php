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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - uwuweb Grade Management</title>
    <link rel="stylesheet" href="/uwuweb/assets/css/login.css">
</head>
<body class="login-page">
    <div class="login-container card">
        <div class="card-header login-header">
            <h1 class="card-title">uwuweb</h1>
            <p>Grade Management System</p>
        </div>

        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out'): ?>
                <div class="alert alert-success">
                    You have been successfully logged out.
                </div>
            <?php endif; ?>

            <form method="post" action="/uwuweb/index.php">
                <div class="form-group">
                    <label for="username" class="form-label">Username:</label>
                    <input type="text" id="username" name="username" class="form-input" value="<?= htmlspecialchars($username) ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password:</label>
                    <input type="password" id="password" name="password" class="form-input" required>
            </div>

                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Log In</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
