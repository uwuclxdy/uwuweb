<?php
/**
 * Common Header File
 * 
 * Contains common HTML header structure included in all pages
 * Includes necessary CSS and initializes session
 * 
 * Functions:
 * - None (template file)
 */

// Ensure auth.php is included for session management
require_once __DIR__ . '/auth.php';

// Get current user's role if logged in
$currentRole = getUserRole();
$roleName = $currentRole ? getRoleName($currentRole) : 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>uwuweb - Grade Management System</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="main-header">
        <div class="container">
            <h1 class="site-title">uwuweb</h1>
            <?php if (isLoggedIn()): ?>
                <button class="mobile-menu-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
                    ≡
                </button>
                <div class="user-info">
                    <span class="username"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                    <span class="role-badge"><?= htmlspecialchars($roleName) ?></span>
                    <a href="/includes/logout.php" class="btn btn-sm">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <main class="container">
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php 
                    $errorMsg = '';
                    switch($_GET['error']) {
                        case 'unauthorized':
                            $errorMsg = 'You are not authorized to access that resource.';
                            break;
                        case 'invalid_csrf':
                            $errorMsg = 'Security token mismatch. Please try again.';
                            break;
                        default:
                            $errorMsg = 'An error occurred.';
                    }
                    echo htmlspecialchars($errorMsg);
                ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>