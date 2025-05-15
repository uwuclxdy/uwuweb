<?php
/**
 * Admin System Settings
 * /uwuweb/admin/system_settings.php
 *
 * Provides functionality for administrators to manage system-wide settings
 *
 */

declare(strict_types=1);

use Random\RandomException;

require_once 'admin_functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

$message = '';
$messageType = '';

// Initialize settings with default values
$settings = [
    'school_name' => 'High School Example',
    'current_year' => '2024/2025',
    'school_address' => '',
    'session_timeout' => 30,
    'grade_scale' => '1-5',
    'maintenance_mode' => false
];

// If there's a function to get settings, use it instead of defaults
if (function_exists('getSystemSettings')) $settings = getSystemSettings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
    $messageType = 'error';
    error_log("CSRF token validation failed for user ID: " . ($_SESSION['user_id'] ?? 'Unknown'));
} else {
    $action = array_key_first(array_intersect_key($_POST, [
        'update_settings' => 1
    ]));

    if ($action === 'update_settings') {
        $schoolName = trim($_POST['school_name'] ?? '');
        $currentYear = trim($_POST['current_year'] ?? '');
        $schoolAddress = trim($_POST['school_address'] ?? '');
        $sessionTimeout = filter_input(INPUT_POST, 'session_timeout', FILTER_VALIDATE_INT);
        $gradeScale = trim($_POST['grade_scale'] ?? '');
        $maintenanceMode = isset($_POST['maintenance_mode']);

        if (empty($schoolName) || empty($currentYear) || $sessionTimeout === false) {
            $message = 'Vsa obvezna polja morajo biti izpolnjena.';
            $messageType = 'error';
        } elseif (updateSystemSettings([
            'school_name' => $schoolName,
            'current_year' => $currentYear,
            'school_address' => $schoolAddress,
            'session_timeout' => $sessionTimeout,
            'grade_scale' => $gradeScale,
            'maintenance_mode' => $maintenanceMode
        ])) {
            $message = 'Nastavitve uspešno posodobljene.';
            $messageType = 'success';
            // Reload settings
            if (function_exists('getSystemSettings')) $settings = getSystemSettings();
        } else {
            $message = 'Napaka pri posodabljanju nastavitev.';
            $messageType = 'error';
        }
    }
}

try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    $csrfToken = '';
    error_log("CSRF token generation failed in admin/system_settings.php: " . $e->getMessage());
    $message = 'Generiranje varnostnega žetona ni uspelo. Poskusite znova kasneje.';
    $messageType = 'error';
}

?>

<div class="container mt-lg">
    <?php renderHeaderCard(
        'Sistemske Nastavitve',
        'Upravljanje sistemskih nastavitev aplikacije.',
        'admin'
    ); ?>

    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType === 'success' ? 'success' : 'error' ?> mb-lg d-flex items-center gap-sm">
             <span class="alert-icon text-lg">
                <?= $messageType === 'success' ? '✓' : '⚠' ?>
            </span>
            <div class="alert-content">
                <?= htmlspecialchars($message) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow rounded-lg mb-xl">
        <div class="card__content p-md">
            <form id="systemSettingsForm" method="POST" action="/uwuweb/admin/system_settings.php">
                <div class="card mb-lg rounded-lg shadow-sm">
                    <div class="card__title p-sm font-medium"
                         style="border-bottom: 1px solid var(--border-color-light);">Informacije o Šoli
                    </div>
                    <div class="card__content p-md">
                        <div class="row">
                            <div class="col col-md-6">
                                <div class="form-group mb-md">
                                    <label class="form-label" for="school_name">Ime Šole:</label>
                                    <input type="text" id="school_name" name="school_name" class="form-input"
                                           value="<?= htmlspecialchars($settings['school_name'] ?? 'High School Example') ?>">
                                </div>
                            </div>
                            <div class="col col-md-6">
                                <div class="form-group mb-md">
                                    <label class="form-label" for="current_year">Trenutno Šolsko Leto:</label>
                                    <input type="text" id="current_year" name="current_year" class="form-input"
                                           placeholder="LLLL/LLLL"
                                           value="<?= htmlspecialchars($settings['current_year'] ?? '2024/2025') ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <label class="form-label" for="school_address">Naslov Šole:</label>
                            <textarea id="school_address" name="school_address" class="form-input form-textarea"
                                      rows="2"><?= htmlspecialchars($settings['school_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-lg rounded-lg shadow-sm">
                    <div class="card__title p-sm font-medium"
                         style="border-bottom: 1px solid var(--border-color-light);">Sistemske Nastavitve
                    </div>
                    <div class="card__content p-md">
                        <div class="form-group mb-md">
                            <label class="form-label" for="session_timeout">Časovna Omejitev Seje (minute):</label>
                            <input type="number" id="session_timeout" name="session_timeout" class="form-input"
                                   value="<?= $settings['session_timeout'] ?? 30 ?>" min="5" max="120">
                            <small class="text-secondary d-block mt-xs">Čas v minutah, preden poteče neaktivna seja
                                (5-120).</small>
                        </div>

                        <div class="form-group mb-md">
                            <label class="form-label" for="grade_scale">Lestvica Ocen:</label>
                            <select id="grade_scale" name="grade_scale" class="form-input form-select">
                                <option value="1-5" <?= ($settings['grade_scale'] ?? '1-5') === '1-5' ? 'selected' : '' ?>>
                                    Lestvica 1-5 (5 je najvišja)
                                </option>
                                <option value="1-10" <?= ($settings['grade_scale'] ?? '') === '1-10' ? 'selected' : '' ?>>
                                    Lestvica 1-10 (10 je najvišja)
                                </option>
                            </select>
                        </div>

                        <div class="form-group mb-0">
                            <div class="d-flex items-center gap-sm">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode" value="1"
                                       class="form-input"
                                       style="width: auto; height: auto;" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>>
                                <label for="maintenance_mode" class="form-label mb-0">Omogoči Vzdrževalni
                                    Način</label>
                            </div>
                            <small class="text-secondary d-block mt-xs">Ko je omogočen, lahko do sistema dostopajo
                                samo administratorji.</small>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_settings" value="1">

                <div class="d-flex justify-end mt-lg">
                    <button type="submit" class="btn btn-primary d-flex items-center gap-xs">
                        <span class="text-md">💾</span> Shrani Nastavitve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
