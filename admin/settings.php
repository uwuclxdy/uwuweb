<?php
/**
 * Admin Settings Redirect
 * /uwuweb/admin/settings.php
 *
 * Redirects from old settings page to the appropriate new split page based on tab parameter
 */

declare(strict_types=1);

require_once '../includes/auth.php';

// Check authentication and role
requireRole(ROLE_ADMIN);

// Check for tab parameter to redirect appropriately
$tab = $_GET['tab'] ?? 'system';

switch ($tab) {
    case 'classes':
        header("Location: /uwuweb/admin/manage_classes.php");
        break;
    case 'subjects':
        header("Location: /uwuweb/admin/manage_subjects.php");
        break;
    case 'assign':
        header("Location: /uwuweb/admin/manage_assignments.php");
        break;
    case 'system':
    default:
        header("Location: /uwuweb/admin/system_settings.php");
        break;
}
exit;
