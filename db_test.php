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

// [PLACEHOLDER: Page header]

// [PLACEHOLDER: Database test section with heading for PHP syntax check]
$output = [];
exec('php -l /uwuweb/includes/db.php', $output, $return_var);
// [PLACEHOLDER: Display syntax check results in formatted pre block]

// [PLACEHOLDER: Database connection test heading]
// [PLACEHOLDER: Display connection test result]

// [PLACEHOLDER: Database schema verification heading]
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

    // [PLACEHOLDER: Unordered list showing table verification results]
    // This will display a list of tables with checkmarks or X marks
    // indicating whether each expected table exists in the database
    
    // Additional verification: check admin user exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $result = $stmt->fetch();

    // [PLACEHOLDER: Admin user verification result]
    // This will display whether the default admin user exists or not

} catch (PDOException $e) {
    // [PLACEHOLDER: Error message display]
    // This will show a formatted error message if database checking fails
}

// [PLACEHOLDER: Page footer]
