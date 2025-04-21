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

<div class="d-flex flex-column flex-row@md gap-md mt-lg">
    <!-- Main Content -->
    <main style="flex: 1;">
        <div class="card mb-lg">
            <div class="d-flex justify-between items-center">
                <div>
                    <h1 class="mt-0 mb-xs">Welcome, <?= htmlspecialchars($userInfo['first_name'] ?? $_SESSION['username']) ?>!</h1>
                    <p class="text-secondary mt-0">Your personalized dashboard</p>
                </div>
                <div class="profile-<?= strtolower($userRole) ?>" style="width: 48px; height: 48px; border-radius: 50%; border: 3px solid; display: flex; align-items: center; justify-content: center; font-size: var(--font-size-lg); font-weight: var(--font-weight-bold);">
                    <?= strtoupper($_SESSION['username'][0]) ?>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <?php foreach ($widgets as $key => $widget): ?>
                <div class="card card-entrance">
                    <h3 class="card__title">
                        <?php if (isset($widget['icon'])): ?>
                        <span class="text-secondary mr-sm"><?= $widget['icon'] ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($widget['title']) ?>
                    </h3>
                    <div class="card__content">
                        <?php
                        // Call the widget's render function if it exists
                        if (function_exists($widget['function'])) {
                            echo call_user_func($widget['function']);
                        } else {
                            echo '<p class="text-disabled">Widget content not available</p>';
                        }
                        ?>
                    </div>
                    <?php if (isset($widget['action'])): ?>
                    <div class="mt-md text-right">
                        <a href="<?= $widget['action']['url'] ?>" class="btn btn-secondary btn-sm">
                            <?= htmlspecialchars($widget['action']['text']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>

<?php
// Include page footer
include 'includes/footer.php';
?>
