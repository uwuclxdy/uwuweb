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
require_once 'auth.php';

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
    <link rel="stylesheet" href="/uwuweb/assets/css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="navbar-container container">
            <h1 class="navbar-brand site-title">uwuweb</h1>
            <?php if (isLoggedIn()): ?>
                <button class="mobile-menu-toggle" aria-label="Toggle navigation menu" aria-expanded="false">
                    <span class="icon">≡</span>
                </button>
                <div class="user-info">
                    <span class="username"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                    <span class="badge badge-primary role-badge"><?= htmlspecialchars($roleName) ?></span>
                    <a href="/uwuweb/includes/logout.php" class="btn btn-sm btn-secondary">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <main class="container">
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php
                    $errorMsg=match($_GET['error']){'unauthorized'=>'You are not authorized to access that resource.','invalid_csrf'=>'Security token mismatch. Please try again.',default=>'An error occurred.',};
                    echo htmlspecialchars($errorMsg);
                ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>
