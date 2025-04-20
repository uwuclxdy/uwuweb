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

<?php /* 
    [DASHBOARD PAGE PLACEHOLDER]
    Components:
    - Page wrapper with dashboard class
    
    - Sidebar containing:
      - Navigation menu with dynamic items based on user role
      - Each navigation item includes:
        - Icon based on item type
        - Text link with highlighting for active page
    
    - Main content area containing:
      - Welcome message with username
      - User role badge display
      
      - Dashboard grid with:
        - Dynamic widgets loaded based on user role
        - Each widget is a card with:
          - Header with widget title
          - Body with content from the widget's render function
*/ ?>

<?php foreach ($widgets as $key => $widget): ?>
    <?php /* [WIDGET CARD PLACEHOLDER: <?= $widget['title'] ?>] */ ?>
    <?php 
    // Call the widget's render function if it exists
    if (function_exists($widget['function'])) {
        echo call_user_func($widget['function']);
    } else {
        echo '<!-- Widget content not available -->';
    }
    ?>
<?php endforeach; ?>

<?php
// Include page footer
include 'includes/footer.php';
?>