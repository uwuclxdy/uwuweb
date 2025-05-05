<?php
/**
 * Database Connection Handler
 *
 * Establishes and provides a PDO connection to the uwuweb database
 * Used by all data access operations throughout the application
 *
 * Functions:
 * - getDBConnection() - Creates and returns a PDO database connection
 * - safeGetDBConnection() - Gets a PDO connection or dies with an error message
 * - testDBConnection() - Tests database connectivity and returns status
 * - logDBError() - Logs database connection errors
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$db_config = [
    'host' => 'localhost',
    'dbname' => 'uwuweb',
    'charset' => 'utf8mb4',
    'username' => 'root',
    'password' => ''
];

// PDO connection options
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

/**
 * Logs database errors to a file
 *
 * @param string $error Error message to log
 * @return void
 */
function logDBError(string $error): void
{
    error_log('Database error: ' . $error);
}

/**
 * Creates and returns a PDO database connection
 *
 * @return PDO|null Returns a PDO object or null on failure
 */
function getDBConnection(): ?PDO
{
    global $db_config, $pdo_options;

    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        return new PDO($dsn, $db_config['username'], $db_config['password'], $pdo_options);
    } catch (PDOException $e) {
        logDBError($e->getMessage());

        return null;
    }
}

/**
 * This function should be used when a database connection is required
 * and the script cannot continue without it.
 *
 * @param string $context Context information for error logging
 * @param bool $terminate Whether to terminate execution if connection fails
 * @return PDO|null Return a valid PDO object or terminates the script
 */
function safeGetDBConnection(string $context = '', bool $terminate = true): ?PDO
{
    $pdo = getDBConnection();

    if (!$pdo) {
        $errorMsg = "Database connection failed" . ($context ? " in $context" : "");
        error_log($errorMsg);

        if ($terminate) {
            http_response_code(500);
            die("Database connection failed. Please check the error log for details.");
        }
    }

    return $pdo;
}

/**
 * Tests the database connection
 *
 * @return string Connection status message
 */
function testDBConnection(): string
{
    try {
        $pdo = getDBConnection();
        if (!$pdo) return "Connection failed. Check database settings.";

        $stmt = $pdo->query("SELECT VERSION() as version, DATABASE() as db_name");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return "Connection successful! MySQL Version: " .
            ($result['version'] ?? 'Unknown') .
            ", Database: " . ($result['db_name'] ?? 'Unknown');
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return "Connection test failed: " . $e->getMessage();
    }
}
