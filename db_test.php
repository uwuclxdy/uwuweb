<?php
/**
 * uwuweb - Grade Management System
 * Database Connection Test
 *
 * This file tests the connection to the database
 * and verifies that the schema was properly imported
 */

// Include the database connection file
require_once '../includes/db.php';

// Run PHP syntax check on the DB file
echo "<h2>PHP Syntax Check:</h2>";
$output = [];
exec('php -l /uwuweb/includes/db.php', $output, $return_var);
echo "<pre>";
foreach ($output as $line) {
    echo htmlspecialchars($line) . "<br>";
}
echo "</pre>";

// Test database connection
echo "<h2>Database Connection Test:</h2>";
echo "<p>" . testDBConnection() . "</p>";

// Verify all tables exist
echo "<h2>Database Schema Verification:</h2>";
try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new PDOException("Failed to connect to database");
    }

    // List of expected tables based on our schema
    $expected_tables = [
        'roles', 'users', 'students', 'parents', 'student_parent',
        'teachers', 'subjects', 'terms', 'classes', 'enrollments',
        'periods', 'grade_items', 'grades', 'attendance'
    ];

    $found_tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch()) {
        $found_tables[] = $row["Tables_in_uwuweb"];
    }

    // Check each expected table
    echo "<ul>";
    foreach ($expected_tables as $table) {
        if (in_array($table, $found_tables, true)) {
            echo "<li>✅ Table '{$table}' exists</li>";
        } else {
            echo "<li>❌ Table '{$table}' missing</li>";
        }
    }
    echo "</ul>";

    // Additional verification: check admin user exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        echo "<p>✅ Default admin user exists</p>";
    } else {
        echo "<p>❌ Default admin user is missing</p>";
    }

} catch (PDOException $e) {
    echo "<p>Error checking schema: " . htmlspecialchars($e->getMessage()) . "</p>";
}
