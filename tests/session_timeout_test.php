<?php
/**
 * Session Timeout Test
 * 
 * Tests the session timeout functionality to ensure sessions expire after 30 minutes
 * of inactivity as per security requirements
 * 
 * Functions:
 * - simulateSessionUsage() - Creates a test session with activity timestamps
 * - testSessionTimeout() - Tests if the session expires after inactivity
 * - displayResults() - Shows the test results in a readable format
 * - testCheckSessionTimeout() - Modified version of checkSessionTimeout for testing
 */

// Include auth.php for session handling functions
require_once '../includes/auth.php';

// Define constants for test
define('TEST_TIMEOUT', 1800); // 30 minutes in seconds
define('TEST_SHORTER_TIME', 1700); // Just under 30 minutes
define('TEST_LONGER_TIME', 1900); // Just over 30 minutes

/**
 * Simulate session usage with controlled timestamps
 * @param int $inactivity_time - Time in seconds to simulate inactivity
 * @return bool - Whether session was created successfully
 */
function simulateSessionUsage($inactivity_time = 0) {
    // Create a mock user session
    $_SESSION['user_id'] = 999;
    $_SESSION['role_id'] = ROLE_ADMIN;
    $_SESSION['last_activity'] = time() - $inactivity_time;
    
    return isLoggedIn();
}

/**
 * Modified version of checkSessionTimeout for testing that doesn't redirect
 * @return bool - Whether the session should be timed out
 */
function testCheckSessionTimeout() {
    // Only check timeout if user is logged in
    if (isLoggedIn()) {
        $max_idle_time = 1800; // 30 minutes in seconds
        
        // If last activity was set and user has been inactive longer than the max idle time
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > $max_idle_time)) {
            
            // Clear all session variables
            $_SESSION = array();
            
            // Destroy the session
            session_destroy();
            
            return true; // Session timed out
        }
    }
    return false; // No timeout occurred
}

/**
 * Test session timeout functionality
 * @param int $inactivity_time - Time in seconds to simulate inactivity
 * @return array - Test results
 */
function testSessionTimeout($inactivity_time) {
    // Start with a clean session
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        session_start();
    }
    
    // Set up test session
    simulateSessionUsage($inactivity_time);
    
    // Save session state before timeout check
    $before_logged_in = isLoggedIn();
    
    // Use our test version of checkSessionTimeout
    $timed_out = testCheckSessionTimeout();
    
    // Check if still logged in after timeout check
    $after_logged_in = isLoggedIn();
    
    return [
        'inactivity_time' => $inactivity_time,
        'before_check' => $before_logged_in,
        'after_check' => $after_logged_in,
        'session_destroyed' => !$after_logged_in && $before_logged_in,
        'timed_out' => $timed_out
    ];
}

/**
 * Display test results in a readable format
 * @param array $results - Test results from testSessionTimeout()
 */
function displayResults($results) {
    $expected_timeout = $results['inactivity_time'] >= TEST_TIMEOUT;
    $actual_timeout = $results['timed_out'];
    $test_passed = ($expected_timeout === $actual_timeout);
    
    echo "<div style='margin: 10px; padding: 15px; border: 1px solid " . 
         ($test_passed ? "green" : "red") . 
         "; border-radius: 5px;'>";
    
    echo "<h3>Test Case: " . ($results['inactivity_time'] >= TEST_TIMEOUT ? "After" : "Before") . 
         " Timeout ({$results['inactivity_time']} seconds)</h3>";
    
    echo "<p>Inactivity time: {$results['inactivity_time']} seconds</p>";
    echo "<p>Session timeout setting: " . TEST_TIMEOUT . " seconds</p>";
    echo "<p>Logged in before check: " . ($results['before_check'] ? "Yes" : "No") . "</p>";
    echo "<p>Logged in after check: " . ($results['after_check'] ? "Yes" : "No") . "</p>";
    echo "<p>Session destroyed: " . ($results['session_destroyed'] ? "Yes" : "No") . "</p>";
    echo "<p>Session timed out: " . ($results['timed_out'] ? "Yes" : "No") . "</p>";
    
    echo "<p><strong>Result: " . 
         ($test_passed ? "PASSED - " . ($expected_timeout ? "Session correctly destroyed" : "Session correctly maintained") : 
            "FAILED - Unexpected behavior") . 
         "</strong></p>";
    
    echo "</div>";
}

// Output HTML header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>uwuweb - Session Timeout Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .test-summary {
            margin-top: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .test-case {
            margin: 10px 0;
        }
        h1, h2, h3 {
            color: #333;
        }
        .note {
            background-color: #ffffcc;
            padding: 10px;
            border-left: 5px solid #ffcc00;
            margin: 20px 0;
        }
        .passed {
            color: green;
            font-weight: bold;
        }
        .failed {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Session Timeout Test</h1>
    <p>This script tests if the session timeout functionality works as expected (30 minutes).</p>
    
    <div class="note">
        <p><strong>Note:</strong> In a real application, the <code>checkSessionTimeout()</code> function would 
        redirect to the login page and exit the script when a timeout occurs. For testing purposes, we've 
        created a modified version that doesn't redirect so we can view all test results.</p>
    </div>
    
    <h2>Test Results</h2>
    
    <?php
    // Run test cases
    $test_cases = [
        0,                  // Just logged in (no inactivity)
        TEST_SHORTER_TIME,  // Just under timeout limit
        TEST_TIMEOUT,       // Exactly at timeout limit
        TEST_LONGER_TIME    // Beyond timeout limit
    ];
    
    $all_tests_passed = true;
    
    foreach ($test_cases as $inactivity_time) {
        $results = testSessionTimeout($inactivity_time);
        displayResults($results);
        
        $expected_timeout = $inactivity_time >= TEST_TIMEOUT;
        $actual_timeout = $results['timed_out'];
        if ($expected_timeout !== $actual_timeout) {
            $all_tests_passed = false;
        }
    }
    ?>
    
    <div class="test-summary">
        <h2>Test Summary</h2>
        <p>The session timeout is set to <?php echo TEST_TIMEOUT; ?> seconds (30 minutes).</p>
        <p>Expected behavior:</p>
        <ul>
            <li>Sessions with inactivity less than 30 minutes should remain active</li>
            <li>Sessions with inactivity of 30 minutes or more should be destroyed</li>
        </ul>
        
        <p class="<?php echo $all_tests_passed ? 'passed' : 'failed'; ?>">
            Overall test result: <?php echo $all_tests_passed ? 'PASSED' : 'FAILED'; ?>
        </p>
    </div>
    
    <div class="note">
        <h3>How to use this in your testing:</h3>
        <ol>
            <li>Log in to the application</li>
            <li>Wait for 31+ minutes without any activity</li>
            <li>Try to access a protected page</li>
            <li>You should be redirected to the login page with a session timeout message</li>
        </ol>
    </div>
    
    <p><a href="../dashboard.php">Return to Dashboard</a></p>
</body>
</html>