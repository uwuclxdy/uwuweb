<?php
/**
 * Dashboard
 * 
 * Dynamic dashboard that displays different content based on user role
 * Central hub for all user activities with role-specific widgets and navigation
 * 
 * Functions:
 * - None (main script file)
 */

require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Require user to be logged in
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Get role-specific data
$userRole = getUserRole();
$navItems = getNavItemsByRole($userRole);
$widgets = getWidgetsByRole($userRole);
$userInfo = getUserInfo($_SESSION['user_id']);

// Include page header
include 'includes/header.php';
?>

<div class="dashboard">
    <div class="sidebar">
        <nav class="main-nav">
            <ul>
                <?php foreach ($navItems as $item): ?>
                <li>
                    <a href="<?= htmlspecialchars($item['url']) ?>" class="nav-item <?= $_SERVER['PHP_SELF'] == $item['url'] ? 'active' : '' ?>">
                        <span class="icon icon-<?= htmlspecialchars($item['icon']) ?>"></span>
                        <span class="title"><?= htmlspecialchars($item['title']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>
    
    <div class="dashboard-content">
        <h2>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h2>
        <p>Role: <?= htmlspecialchars($userInfo['role_name'] ?? getRoleName($userRole)) ?></p>
        
        <div class="widget-grid">
            <?php foreach ($widgets as $key => $widget): ?>
            <div class="widget" id="<?= htmlspecialchars($key) ?>">
                <h3 class="widget-title"><?= htmlspecialchars($widget['title']) ?></h3>
                <?php 
                // Call the widget's render function if it exists
                if (function_exists($widget['function'])) {
                    echo call_user_func($widget['function']);
                } else {
                    echo '<div class="widget-content">Widget content not available</div>';
                }
                ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
// Include page footer
include 'includes/footer.php';
?>