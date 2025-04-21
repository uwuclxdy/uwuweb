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

// Check the PHP syntax
$output = [];
exec('php -l /uwuweb/includes/db.php', $output, $return_var);

// Try connecting to the database and verify schema
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
    
    // Additional verification: check admin user exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
    $result = $stmt->fetch();

} catch (PDOException $e) {
    // Error will be displayed in the interface
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>uwuweb - Database Connection Test</title>
    <link rel="stylesheet" href="/uwuweb/assets/css/style.css">
</head>
<body class="bg-primary">
    <div class="container" style="max-width: 800px; margin: 2rem auto;">
        <div class="card mb-lg">
            <h1 class="mt-0 mb-md">uwuweb - Database Connection Test</h1>
            <p class="text-secondary mb-0">This utility verifies the database connection and schema setup</p>
        </div>
        
        <!-- PHP Syntax Check -->
        <div class="card mb-lg">
            <h2 class="mt-0 mb-md">PHP Syntax Check</h2>
            <pre class="bg-tertiary p-md rounded" style="overflow-x: auto;">
<?php
foreach ($output as $line) {
    echo htmlspecialchars($line) . "\n";
}
echo htmlspecialchars("Exit code: $return_var") . "\n";
echo $return_var === 0 ? "<span class='status-success'>✓ No syntax errors detected</span>" : "<span class='status-error'>✗ Syntax errors detected</span>";
?>
            </pre>
        </div>
        
        <!-- Database Connection Test -->
        <div class="card mb-lg">
            <h2 class="mt-0 mb-md">Database Connection Test</h2>
            <?php try { $testPdo = getDBConnection(); ?>
                <div class="bg-tertiary p-md rounded d-flex items-center gap-md">
                    <span class="status-success">✓</span>
                    <span>Successfully connected to the database</span>
                </div>
            <?php } catch (PDOException $e) { ?>
                <div class="bg-tertiary p-md rounded d-flex items-center gap-md">
                    <span class="status-error">✗</span>
                    <span>Failed to connect to database: <?= htmlspecialchars($e->getMessage()) ?></span>
                </div>
            <?php } ?>
        </div>
        
        <!-- Database Schema Verification -->
        <div class="card">
            <h2 class="mt-0 mb-md">Database Schema Verification</h2>
            
            <?php if (isset($pdo) && $pdo): ?>
                <ul class="mb-lg" style="list-style-type: none; padding-left: 0;">
                    <?php foreach ($expected_tables as $table): ?>
                        <li class="mb-xs d-flex gap-md items-center">
                            <?php if (in_array($table, $found_tables)): ?>
                                <span class="status-success">✓</span>
                                <span>Table <code><?= htmlspecialchars($table) ?></code> exists</span>
                            <?php else: ?>
                                <span class="status-error">✗</span>
                                <span>Table <code><?= htmlspecialchars($table) ?></code> missing</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <!-- Admin User Check -->
                <div class="bg-tertiary p-md rounded d-flex items-center gap-md">
                    <?php if ($result['count'] > 0): ?>
                        <span class="status-success">✓</span>
                        <span>Default admin user exists</span>
                    <?php else: ?>
                        <span class="status-warning">!</span>
                        <span>Default admin user not found. You may need to create an admin user manually.</span>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="bg-tertiary p-md rounded status-error">
                    <p class="mb-0">Unable to verify schema: Database connection failed</p>
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-end mt-lg">
                <a href="/uwuweb/index.php" class="btn btn-primary">Go to Login Page</a>
            </div>
        </div>
    </div>
    
    <footer class="mt-xl">
        <div class="container">
            <p class="text-center text-secondary">© <?= date('Y') ?> uwuweb - Grade Management System</p>
        </div>
    </footer>
</body>
</html>