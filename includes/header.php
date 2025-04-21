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
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>uwuweb - Grade Management System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="navbar">
        <a href="../dashboard.php" class="navbar-logo">uwuweb</a>

        <?php if (isLoggedIn()): ?>
            <button class="navbar-toggle btn" id="navToggle" aria-label="Toggle navigation">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>

            <nav class="navbar-menu" id="navMenu">
                <a href="../dashboard.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>

                <?php if ($currentRole === ROLE_ADMIN): ?>
                    <a href="../admin/users.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">Users</a>
                    <a href="../admin/settings.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">Settings</a>
                <?php elseif ($currentRole === ROLE_TEACHER): ?>
                    <a href="../teacher/gradebook.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'gradebook.php' ? 'active' : '' ?>">Gradebook</a>
                    <a href="../teacher/attendance.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : '' ?>">Attendance</a>
                    <a href="../teacher/justifications.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'justifications.php' ? 'active' : '' ?>">Justifications</a>
                <?php elseif ($currentRole === ROLE_STUDENT): ?>
                    <a href="../student/grades.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'grades.php' ? 'active' : '' ?>">Grades</a>
                    <a href="../student/attendance.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : '' ?>">Attendance</a>
                    <a href="../student/justification.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'justification.php' ? 'active' : '' ?>">Justifications</a>
                <?php elseif ($currentRole === ROLE_PARENT): ?>
                    <a href="../parent/grades.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'grades.php' ? 'active' : '' ?>">Grades</a>
                    <a href="../parent/attendance.php" class="navbar-link <?= basename($_SERVER['PHP_SELF']) === 'attendance.php' ? 'active' : '' ?>">Attendance</a>
                <?php endif; ?>
            </nav>

            <div class="d-flex items-center gap-md">
                <div class="d-flex flex-column">
                    <span class="text-primary"><?= htmlspecialchars($_SESSION['username']) ?></span>
                    <span class="text-secondary"><?= htmlspecialchars($roleName) ?></span>
                </div>
                <div class="profile-<?= strtolower($roleName) ?>" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid; display: flex; align-items: center; justify-content: center;">
                    <?= strtoupper($_SESSION['username'][0]) ?>
                </div>
                <a href="../includes/logout.php" class="btn btn-secondary">Logout</a>
            </div>
        <?php endif; ?>
    </header>

    <div class="container page-transition">
        <?php if (isset($_GET['error'])):
            $errorMsg = match($_GET['error']) {
                'unauthorized' => 'You are not authorized to access that resource.',
                'invalid_csrf' => 'Security token mismatch. Please try again.',
                default => 'An error occurred.',
            };
        ?>
            <div class="alert status-error mt-lg">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert status-success mt-lg">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>
