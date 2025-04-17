<?php
/**
 * uwuweb - Grade Management System
 * Database Connection Handler
 * 
 * Establishes and provides a PDO connection to the uwuweb database
 * Used by all data access operations throughout the application
 */

// Database configuration
$db_config = [
    'host' => 'localhost',
    'dbname' => 'uwuweb',
    'charset' => 'utf8mb4',
    'username' => 'root', // Default XAMPP username
    'password' => ''      // Default XAMPP password (blank)
];

// PDO connection options
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

// Connection function
function getDBConnection() {
    global $db_config, $pdo_options;
    
    try {
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $pdo_options);
        return $pdo;
    } catch (PDOException $e) {
        // In production, you would log this error and display a generic message
        die("Database connection failed: " . $e->getMessage());
    }
}

// Test connection function
function testDBConnection() {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT 'Connection successful' AS message");
        return $stmt->fetch()['message'];
    } catch (PDOException $e) {
        return "Connection failed: " . $e->getMessage();
    }
}