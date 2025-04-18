<?php
/**
 * Authentication and Session Management
 *
 * Provides functions for user authentication, session management,
 * and role-based access control
 *
 * Functions:
 * - isLoggedIn() - Checks if a user is currently logged in
 * - getUserRole() - Returns the current user's role ID
 * - getUserId() - Returns the current user's ID
 * - hasRole($roleId) - Checks if current user has a specific role
 * - requireRole($roleId) - Restricts page access to users with specific role
 * - generateCSRFToken() - Creates a CSRF token for form security
 * - verifyCSRFToken($token) - Validates submitted CSRF token
 * - getRoleName($roleId) - Returns the name of a role by ID
 * - checkSessionTimeout() - Checks if the session has timed out due to inactivity
 * - updateLastActivityTime() - Updates the last activity timestamp
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();

    // Set session timeout to 30 minutes as per security notes
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(1800);

    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 600) { // 10 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    // Initialize last activity time if not set
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    // Check for session timeout due to inactivity
    checkSessionTimeout();

    // Update last activity time for the current request
    if (isLoggedIn()) {
        updateLastActivityTime();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user's role ID
function getUserRole() {
    return $_SESSION['role_id'] ?? null;
}

// Get current user's ID
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Check if user has a specific role
function hasRole($roleId) {
    return getUserRole() == $roleId;
}

// Require specific role to access page, redirect if not authorized
function requireRole($roleId) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /index.php');
        exit;
    }

    if (!hasRole($roleId) && !hasRole(1)) { // Role ID 1 is assumed to be admin with all access
        header('Location: /dashboard.php?error=unauthorized');
        exit;
    }

    return true;
}

// Generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        header('Location: /dashboard.php?error=invalid_csrf');
        exit;
    }
    return true;
}

// Role constants for improved code readability
define('ROLE_ADMIN', 1);
define('ROLE_TEACHER', 2);
define('ROLE_STUDENT', 3);
define('ROLE_PARENT', 4);

// Get role name by ID
function getRoleName($roleId) {
    $roleNames = [
        ROLE_ADMIN => 'Administrator',
        ROLE_TEACHER => 'Teacher',
        ROLE_STUDENT => 'Student',
        ROLE_PARENT => 'Parent/Guardian'
    ];

    return $roleNames[$roleId] ?? 'Unknown';
}

/**
 * Check if session has timed out due to inactivity
 * Automatically logs out user if session is inactive for more than 30 minutes
 */
function checkSessionTimeout() {
    // Only check timeout if user is logged in
    if (isLoggedIn()) {
        $max_idle_time = 1800; // 30 minutes in seconds

        // If last activity was set and user has been inactive longer than the max idle time
        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity'] > $max_idle_time)) {

            // Clear all session variables
            $_SESSION = array();

            // Destroy the session cookie
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }

            // Destroy the session
            session_destroy();

            // Redirect to login page with timeout message
            header('Location: /index.php?error=session_timeout');
            exit;
        }
    }
}

/**
 * Update the last activity time to the current time
 * Should be called on every user interaction
 */
function updateLastActivityTime() {
    $_SESSION['last_activity'] = time();
}
