<?php
/**
 * Common Header File
 * uwuweb/includes/header.php
 *
 * Contains common HTML header structure included in all pages
 * Includes necessary CSS, initializes session, and displays the navigation bar.
 *
 * Functions:
 * - None (template file)
 */

require_once __DIR__ . '/auth.php';

$currentRole = getUserRole();
$isUserLoggedIn = isLoggedIn();
$roleName = getRoleName($currentRole);
$username = $isUserLoggedIn ? $_SESSION['username'] : '';

$currentPage = basename($_SERVER['PHP_SELF']);

?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>uwuweb - Sistem za upravljanje ocen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/uwuweb/assets/css/style.css">
</head>
<body>
<header class="navbar">
    <div class="navbar-brand">
        <a href="/uwuweb/dashboard.php" class="navbar-logo" aria-label="Domov">
            <img src="/uwuweb/design/uwuweb-logo.png" alt="">uwuweb
        </a>
    </div>

    <?php if ($isUserLoggedIn): ?>
        <button class="navbar-toggle btn" id="navToggle" aria-label="Preklopi navigacijo" aria-expanded="false"
                aria-controls="navMenu">
            <!-- Hamburger Icon -->
            <svg class="icon-menu" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
            <!-- Close Icon (initially hidden) -->
            <svg class="icon-close" style="display: none;" width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>

        <nav class="navbar-menu" id="navMenu">
            <a href="/uwuweb/dashboard.php" class="navbar-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">Nadzorna
                plošča</a>

            <?php if ($currentRole === ROLE_ADMIN): ?>
                <a href="/uwuweb/admin/users.php"
                   class="navbar-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">Uporabniki</a>
                <a href="/uwuweb/admin/manage_classes.php"
                   class="navbar-link <?= $currentPage === 'manage_classes.php' ? 'active' : '' ?>">Razredi</a>
                <a href="/uwuweb/admin/manage_subjects.php"
                   class="navbar-link <?= $currentPage === 'manage_subjects.php' ? 'active' : '' ?>">Predmeti</a>
                <a href="/uwuweb/admin/manage_assignments.php"
                   class="navbar-link <?= $currentPage === 'manage_assignments.php' ? 'active' : '' ?>">Dodelitve</a>
                <a href="/uwuweb/admin/system_settings.php"
                   class="navbar-link <?= $currentPage === 'system_settings.php' ? 'active' : '' ?>">Nastavitve</a>
            <?php elseif ($currentRole === ROLE_TEACHER): ?>
                <a href="/uwuweb/teacher/gradebook.php"
                   class="navbar-link <?= $currentPage === 'gradebook.php' ? 'active' : '' ?>">Redovalnica</a>
                <a href="/uwuweb/teacher/attendance.php"
                   class="navbar-link <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">Prisotnost</a>
                <a href="/uwuweb/teacher/justifications.php"
                   class="navbar-link <?= $currentPage === 'justifications.php' ? 'active' : '' ?>">Opravičila</a>
            <?php elseif ($currentRole === ROLE_STUDENT): ?>
                <a href="/uwuweb/student/grades.php"
                   class="navbar-link <?= $currentPage === 'grades.php' ? 'active' : '' ?>">Ocene</a>
                <a href="/uwuweb/student/attendance.php"
                   class="navbar-link <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">Prisotnost</a>
                <a href="/uwuweb/student/justification.php"
                   class="navbar-link <?= $currentPage === 'justification.php' ? 'active' : '' ?>">Opravičila</a>
            <?php elseif ($currentRole === ROLE_PARENT): ?>
                <a href="/uwuweb/parent/grades.php"
                   class="navbar-link <?= $currentPage === 'grades.php' ? 'active' : '' ?>">Ocene</a>
                <a href="/uwuweb/parent/attendance.php"
                   class="navbar-link <?= $currentPage === 'attendance.php' ? 'active' : '' ?>">Prisotnost</a>
            <?php endif; ?>
            <!-- "Logout", shown only on small screens -->
            <a href="/uwuweb/includes/logout.php" class="navbar-link d-lg-none">Odjava</a>
        </nav>

        <!-- User info and Desktop Logout Button -->
        <div class="navbar-user-info d-none d-lg-flex items-center gap-md">
            <!-- Hide on small screens, show on large -->
            <div class="d-flex flex-column text-right">
                <span class="text-primary font-medium"><?= htmlspecialchars($username) ?></span>
                <span class="text-secondary text-sm"><?= htmlspecialchars($roleName) ?></span>
            </div>
            <!-- Role-specific profile avatar -->
            <div class="profile-avatar <?= 'profile-' . strtolower($roleName) ?> d-flex items-center justify-center text-primary font-bold"
                 style="width: 36px; height: 36px; border-radius: 50%; font-size: var(--font-size-md);">
                <?= strtoupper(mb_substr($username, 0, 1)) ?>
            </div>
            <a href="/uwuweb/includes/logout.php" class="btn btn-secondary btn-sm">Odjava</a> <!-- "Logout" -->
        </div>
    <?php endif; ?>
</header>

<!-- Main content container -->
<main class="container page-transition py-lg"> <!-- Added padding top/bottom -->
    <?php
    // Display session-based flash messages (more robust than GET parameters)
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']); // Clear message after displaying
        $statusClass = match ($message['type']) {
            'success' => 'status-success',
            'error' => 'status-error',
            'warning' => 'status-warning',
            default => 'status-info',
        };
        echo '<div class="alert ' . $statusClass . ' mb-lg" role="alert">';
        echo '<div class="alert-content">' . htmlspecialchars($message['text']) . '</div>';
        echo '</div>';
    }
    ?>
