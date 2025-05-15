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

// Include role-specific function files based on user's role
$userRole = getUserRole();
if ($userRole === ROLE_ADMIN) require_once 'admin/admin_functions.php'; elseif ($userRole === ROLE_TEACHER) require_once 'teacher/teacher_functions.php';
elseif ($userRole === ROLE_STUDENT) require_once 'student/student_functions.php';
elseif ($userRole === ROLE_PARENT) require_once 'parent/parent_functions.php';

require_once 'includes/header.php';

// Require user to be logged in
if (!isLoggedIn()) {
    header('Location: /uwuweb/index.php');
    exit;
}

// Get role-specific data
$userRole = getUserRole();
$navItems = getNavItemsByRole($userRole);
$widgets = getWidgetsByRole($userRole);
$userInfo = getUserInfo($_SESSION['user_id']);

?>
<div class="container py-lg">
    <!-- Welcome Card -->
    <div class="card mb-lg shadow page-transition">
        <div class="d-flex justify-between items-center px-md py-md">
            <div>
                <h1 class="mt-0 mb-xs">
                    Dobrodošli, <?= htmlspecialchars($userInfo['first_name'] ?? $_SESSION['username']) ?>!</h1>
                <p class="text-secondary mt-0">Vaša osebna nadzorna plošča</p>
            </div>
            <div class="profile-<?= strtolower(getRoleName($userRole)) ?> d-flex items-center justify-center rounded-full shadow-sm"
                 style="width: 48px; height: 48px;">
                <span class="text-lg font-bold"><?= strtoupper($_SESSION['username'][0]) ?></span>
            </div>
        </div>
    </div>

    <!-- Dashboard Widgets Grid -->
    <div class="dashboard-grid gap-md">
        <?php foreach ($widgets as $key => $widget): ?>
            <div class="card card-entrance shadow">
                <div class="card__title px-md py-md">
                    <?= htmlspecialchars($widget['title']) ?>
                </div>
                <div class="card__content py-sm">
                    <?php
                    // Call the widget's render function if it exists
                    if (function_exists($widget['function'])) echo call_user_func($widget['function']); else echo '<p class="text-disabled">Vsebina pripomočka ni na voljo</p>';
                    ?>
                </div>
                <?php if (isset($widget['action'])): ?>
                    <div class="card__footer d-flex justify-end px-md py-sm">
                        <a href="/uwuweb/<?= $widget['action']['url'] ?>" class="btn btn-primary btn-sm">
                            <?= htmlspecialchars($widget['action']['text']) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Include page footer
include 'includes/footer.php';
?>
