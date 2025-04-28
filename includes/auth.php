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

use JetBrains\PhpStorm\NoReturn;
use Random\RandomException;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(1800);
    session_start();

    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    checkSessionTimeout();

    if (isLoggedIn()) {
        updateLastActivityTime();
    }
}

// Check if user is logged in
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id'] ?? null);
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
function hasRole($roleId): bool
{
    return getUserRole() == $roleId;
}

// Require specific role to access page, redirect if not authorized
function requireRole($roleId) {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /index.php');
        exit;
    }

    if (!hasRole($roleId) && !hasRole(1)) {
        header('Location: /dashboard.php?error=unauthorized');
        exit;
    }

    return true;
}

// Generate CSRF token
/**
 * @throws RandomException
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken(string $token): bool {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

const ROLE_ADMIN = 1;
const ROLE_TEACHER = 2;
const ROLE_STUDENT = 3;
const ROLE_PARENT = 4;

/**
 * Returns the name of a role by ID
 *
 * @param int $roleId The role ID to lookup
 * @return string The name of the role or 'Unknown' if not found
 */
function getRoleName(int $roleId): string {
    $roleNames = [
        ROLE_ADMIN => 'Administrator',
        ROLE_TEACHER => 'Teacher',
        ROLE_STUDENT => 'Student',
        ROLE_PARENT => 'Parent/Guardian'
    ];

    if (isset($roleNames[$roleId])) {
        return $roleNames[$roleId];
    }

    require_once 'db.php';

    try {
            $pdo = safeGetDBConnection('getRoleName');
            if ($pdo === null) {
                logDBError("Failed to establish database connection in getRoleName");
                return 'Unknown';
            }

            $stmt = $pdo->prepare("SELECT name FROM roles WHERE role_id = ? LIMIT 1");
            $stmt->execute([$roleId]);
            $role = $stmt->fetch();

            return $role ? $role['name'] : 'Unknown';
        } catch (PDOException $e) {
            logDBError("Error in getRoleName: " . $e->getMessage());
            return 'Unknown';
        }
}

/**
 * Checks if the session has timed out due to inactivity
 *
 * @return void
 */
function checkSessionTimeout(): void {
    if (isLoggedIn()) {
        $timeout = 1800;

        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity'] > $timeout)) {
            session_unset();
            session_destroy();

            header('Location: /index.php?error=session_timeout');
            exit;
        }
    }
}

/**
 * Updates the last activity timestamp for the current session
 *
 * @return void
 */
function updateLastActivityTime(): void {
    $_SESSION['last_activity'] = time();
}

/**
 * Destroys the current session and redirects to login page
 *
 * @param string $reason Optional reason to include in the redirect URL
 * @return void
 */
#[NoReturn] function destroySession(string $reason = ''): void {
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    $redirect = '/index.php';
    if (!empty($reason)) {
        $redirect .= '?error=' . $reason;
    }

    header('Location: ' . $redirect);
    exit;
}
