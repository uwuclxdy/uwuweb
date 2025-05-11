<?php
/**
 * Authentication and Session Management
 *
 * File path: /includes/auth.php
 *
 * Provides functions for user authentication, session management,
 * and role-based access control.
 *
 * Session Management:
 * - isLoggedIn(): bool - Checks if a user is currently logged in
 * - checkSessionTimeout(): void - Checks if the session has timed out due to inactivity
 * - updateLastActivityTime(): void - Updates the last activity timestamp
 * - destroySession(string $reason = ''): void - Destroys current session and redirects to login
 *
 * User Information:
 * - getUserRole(): int|null - Returns the current user's role ID from session
 * - getUserId(): int|null - Returns the current user's ID from session
 * - hasRole(int $roleId): bool - Checks if current user has a specific role
 * - getRoleName(?int $roleId): string - Returns the name of a role by ID
 *
 * Access Control:
 * - requireRole(int $roleId): bool - Restricts page access to users with specific role
 *
 * Security:
 * - generateCSRFToken(): string - Creates a CSRF token for form security
 * - verifyCSRFToken(string $token): bool - Validates submitted CSRF token
 */

// Initialize session if it hasn't been started
use JetBrains\PhpStorm\NoReturn;
use Random\RandomException;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 1800);
    session_set_cookie_params(1800);
    session_start();

    if (!isset($_SESSION['last_regeneration'])) $_SESSION['last_regeneration'] = time(); elseif (time() - $_SESSION['last_regeneration'] > 600) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    if (!isset($_SESSION['last_activity'])) $_SESSION['last_activity'] = time();

    checkSessionTimeout();

    if (isLoggedIn()) updateLastActivityTime();
}

// Enforce session timeout
checkSessionTimeout();

/**
 * Checks if a user is currently logged in
 *
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id'] ?? null);
}

/**
 * Returns the current user's role ID from session
 *
 * @return int|null Role ID or null if not set
 */
function getUserRole(): int|null
{
    return $_SESSION['role_id'] ?? null;
}

/**
 * Returns the current user's ID from session
 *
 * @return int|null User ID or null if not set
 */
function getUserId(): int|null
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Checks if the current user has a specific role
 *
 * @param int $roleId The role ID to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole(int $roleId): bool
{
    return getUserRole() == $roleId;
}

/**
 * Restricts page access to users with a specific role
 * Redirects to login or dashboard if unauthorized
 *
 * @param int $roleId The required role ID for access
 * @return bool True if user has access (will exit if not)
 */
function requireRole(int $roleId): bool
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /uwuweb/index.php');
        exit;
    }

    if (!hasRole($roleId) && !hasRole(1)) {
        header('Location: /uwuweb/dashboard.php?error=unauthorized');
        exit;
    }

    return true;
}

/**
 * Creates a CSRF token for form security
 *
 * @return string The generated CSRF token
 * @throws RandomException
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/**
 * Validates a submitted CSRF token
 *
 * @param string $token The token to verify
 * @return bool True if token is valid, false otherwise
 */
function verifyCSRFToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) return false;

    return hash_equals($_SESSION['csrf_token'], $token);
}

// Role constants
const ROLE_ADMIN = 1;
const ROLE_TEACHER = 2;
const ROLE_STUDENT = 3;
const ROLE_PARENT = 4;

/**
 * Returns the name of a role by ID
 *
 * @param int|null $roleId The role ID to lookup
 * @return string The name of the role or 'Unknown' if not found
 */
function getRoleName(?int $roleId): string
{
    if ($roleId === null) return 'Gost';

    $roleNames = [
        ROLE_ADMIN => 'Administrator',
        ROLE_TEACHER => 'Učitelj',
        ROLE_STUDENT => 'Dijak',
        ROLE_PARENT => 'Starš/Skrbnik'
    ];

    if (isset($roleNames[$roleId])) return $roleNames[$roleId];

    require_once 'db.php';

    try {
        $pdo = safeGetDBConnection('getRoleName');
        if ($pdo === null) {
            logDBError("Failed to establish database connection in getRoleName");
            return 'Neznano';
        }

        $stmt = $pdo->prepare("SELECT name FROM roles WHERE role_id = ? LIMIT 1");
        $stmt->execute([$roleId]);
        $role = $stmt->fetch();

        return $role ? $role['name'] : 'Neznano';
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
function checkSessionTimeout(): void
{
    if (isLoggedIn()) {
        $timeout = 1800;

        if (isset($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity'] > $timeout)) {
            session_unset();
            session_destroy();

            header('Location: /uwuweb/index.php?error=session_timeout');
            exit;
        }
    }
}

/**
 * Updates the last activity timestamp for the current session
 *
 * @return void
 */
function updateLastActivityTime(): void
{
    $_SESSION['last_activity'] = time();
}

/**
 * Destroys the current session and redirects to login page
 *
 * @param string $reason Optional reason to include in the redirect URL
 * @return void
 */
#[NoReturn] function destroySession(string $reason = ''): void
{
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();

    $redirect = '/uwuweb/index.php';
    if (!empty($reason)) $redirect .= '?error=' . $reason;

    header('Location: ' . $redirect);
    exit;
}
