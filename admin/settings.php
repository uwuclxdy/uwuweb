<?php
/**
 * Admin Settings Management
 * /uwuweb/admin/settings.php
 *
 * Provides functionality for administrators to manage system settings,
 * subjects, classes (homeroom groups), and class-subject assignments
 *
 */

declare(strict_types=1);

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'admin_functions.php';

requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

$message = '';
$messageType = '';
$subjectDetails = null;
$classDetails = null;
$classSubjectDetails = null;
$currentTab = htmlspecialchars($_GET['tab'] ?? 'classes');

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

$subjects = getAllSubjects();
$classes = getAllClasses();
$classSubjects = getAllClassSubjectAssignments();
$teachers = getAllTeachers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
    $messageType = 'error';
    error_log("CSRF token validation failed for user ID: " . ($_SESSION['user_id'] ?? 'Unknown'));
} else {
    $action = array_key_first(array_intersect_key($_POST, [
        'create_subject' => 1, 'update_subject' => 1, 'delete_subject' => 1,
        'create_class' => 1, 'update_class' => 1, 'delete_class' => 1,
        'create_assignment' => 1, 'update_assignment' => 1, 'delete_assignment' => 1,
        'update_settings' => 1
    ]));

    switch ($action) {
        case 'create_subject':
            $subjectName = trim($_POST['subject_name'] ?? '');
            if (empty($subjectName)) {
                $message = 'Ime predmeta je obvezno.';
                $messageType = 'error';
            } elseif (createSubject(['name' => $subjectName])) {
                $message = 'Predmet uspešno ustvarjen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri ustvarjanju predmeta. Morda že obstaja.';
                $messageType = 'error';
            }
            $currentTab = 'subjects';
            break;

        case 'update_subject':
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            $subjectName = trim($_POST['subject_name'] ?? '');

            if (!$subjectId || $subjectId <= 0) {
                $message = 'Izbran neveljaven predmet za posodobitev.';
                $messageType = 'error';
            } elseif (empty($subjectName)) {
                $message = 'Ime predmeta je obvezno.';
                $messageType = 'error';
            } elseif (updateSubject($subjectId, ['name' => $subjectName])) {
                $message = 'Predmet uspešno posodobljen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri posodabljanju predmeta.';
                $messageType = 'error';
            }
            $currentTab = 'subjects';
            break;

        case 'update_class':
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $className = trim($_POST['class_name'] ?? '');
            $classCode = trim($_POST['class_code'] ?? '');
            $homeroomTeacherId = filter_input(INPUT_POST, 'homeroom_teacher_id', FILTER_VALIDATE_INT);

            if (!$classId || $classId <= 0) {
                $message = 'Izbran neveljaven razred za posodobitev.';
                $messageType = 'error';
            } elseif (empty($className) || empty($classCode) || !$homeroomTeacherId || $homeroomTeacherId <= 0) {
                $message = 'Ime razreda, koda razreda in razrednik so obvezni.';
                $messageType = 'error';
            } elseif (updateClass($classId, [
                'class_code' => $classCode,
                'title' => $className,
                'homeroom_teacher_id' => $homeroomTeacherId
            ])) {
                $message = 'Razred uspešno posodobljen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri posodabljanju razreda. Razred s tem imenom ali kodo morda že obstaja.';
                $messageType = 'error';
            }
            $currentTab = 'classes';
            break;

        case 'update_assignment':
            $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
            $schedule = trim($_POST['schedule'] ?? '');

            if (!$assignmentId || !$classId || !$subjectId || !$teacherId) {
                $message = 'ID dodelitve, razred, predmet in učitelj so obvezni.';
                $messageType = 'error';
            } elseif (updateClassSubjectAssignment($assignmentId, [
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'schedule' => $schedule
            ])) {
                $message = 'Dodelitev uspešno posodobljena.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri posodabljanju dodelitve. Ta kombinacija morda že obstaja.';
                $messageType = 'error';
            }
            $currentTab = 'assign';
            break;

        case 'delete_subject':
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            if (!$subjectId || $subjectId <= 0) {
                $message = 'Izbran neveljaven predmet za brisanje.';
                $messageType = 'error';
            } elseif (deleteSubject($subjectId)) {
                $message = 'Predmet uspešno izbrisan.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri brisanju predmeta. Morda je dodeljen razredu.';
                $messageType = 'error';
            }
            $currentTab = 'subjects';
            break;

        case 'create_class':
            $className = trim($_POST['class_name'] ?? '');
            $schoolYear = trim($_POST['school_year'] ?? '');
            $homeroomTeacherId = filter_input(INPUT_POST, 'homeroom_teacher_id', FILTER_VALIDATE_INT);

            if (empty($className) || empty($schoolYear) || !$homeroomTeacherId || $homeroomTeacherId <= 0) {
                $message = 'Ime razreda, šolsko leto (koda) in razrednik so obvezni.';
                $messageType = 'error';
            } elseif (createClass([
                'class_code' => $schoolYear,
                'title' => $className,
                'homeroom_teacher_id' => $homeroomTeacherId
            ])) {
                $message = 'Razred uspešno ustvarjen.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri ustvarjanju razreda. Razred s tem imenom ali kodo morda že obstaja.';
                $messageType = 'error';
            }
            $currentTab = 'classes';
            break;

        case 'delete_class':
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            if (!$classId || $classId <= 0) {
                $message = 'Izbran neveljaven razred za brisanje.';
                $messageType = 'error';
            } elseif (deleteClass($classId)) {
                $message = 'Razred uspešno izbrisan.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri brisanju razreda. Morda ima dodeljene učence ali predmete.';
                $messageType = 'error';
            }
            $currentTab = 'classes';
            break;

        case 'create_assignment':
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
            $schedule = trim($_POST['schedule'] ?? '');

            if (!$classId || !$subjectId || !$teacherId) {
                $message = 'Razred, predmet in učitelj so obvezni.';
                $messageType = 'error';
            } elseif (assignSubjectToClass([
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'schedule' => $schedule
            ])) {
                $message = 'Dodelitev uspešno ustvarjena.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri ustvarjanju dodelitve. Ta kombinacija morda že obstaja.';
                $messageType = 'error';
            }
            $currentTab = 'assign';
            break;

        case 'delete_assignment':
            $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
            if (!$assignmentId || $assignmentId <= 0) {
                $message = 'Izbrana neveljavna dodelitev za brisanje.';
                $messageType = 'error';
            } elseif (removeSubjectFromClass($assignmentId)) {
                $message = 'Dodelitev uspešno izbrisana.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri brisanju dodelitve. Morda ima ocene ali termine.';
                $messageType = 'error';
            }
            $currentTab = 'assign';
            break;

        case 'update_settings':
            $message = 'Funkcionalnost posodobitve sistemskih nastavitev še ni implementirana.';
            $messageType = 'info';
            $currentTab = 'system';
            break;

        default:
            break;
    }
}

if (isset($_GET['subject_id']) && $currentTab === 'subjects') {
    $subjectId = filter_input(INPUT_GET, 'subject_id', FILTER_VALIDATE_INT);
    if ($subjectId && $subjectId > 0) $subjectDetails = getSubjectDetails($subjectId);
} elseif (isset($_GET['class_id']) && $currentTab === 'classes') {
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    if ($classId && $classId > 0) $classDetails = getClassDetails($classId);
} elseif (isset($_GET['class_subject_id']) && $currentTab === 'assign') {
    $classSubjectId = filter_input(INPUT_GET, 'class_subject_id', FILTER_VALIDATE_INT);
    if ($classSubjectId && $classSubjectId > 0) foreach ($classSubjects as $assignment) if ($assignment['class_subject_id'] == $classSubjectId) {
        $classSubjectDetails = $assignment;
        break;
    }
}

try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    $csrfToken = '';
    error_log("CSRF token generation failed in admin/settings.php: " . $e->getMessage());
    $message = 'Generiranje varnostnega žetona ni uspelo. Poskusite znova kasneje.';
    $messageType = 'error';
}

?>

<div class="container mt-lg">
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">Sistemske Nastavitve</h1>
                <p class="text-secondary mt-0 mb-0">Upravljanje razredov, predmetov, dodelitev in konfiguracije
                    sistema.</p>
            </div>
            <div class="role-badge role-admin">Administrator</div>
        </div>
    </div>

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
        <div class="d-flex p-sm gap-sm" style="border-bottom: 1px solid var(--border-color-medium);">
            <a href="/uwuweb/admin/settings.php?tab=classes"
               class="btn btn-secondary <?= $currentTab === 'classes' ? 'active' : '' ?>">Razredi</a>
            <a href="/uwuweb/admin/settings.php?tab=subjects"
               class="btn btn-secondary <?= $currentTab === 'subjects' ? 'active' : '' ?>">Predmeti</a>
            <a href="/uwuweb/admin/settings.php?tab=assign"
               class="btn btn-secondary <?= $currentTab === 'assign' ? 'active' : '' ?>">Dodelitve</a>
            <a href="/uwuweb/admin/settings.php?tab=system"
               class="btn btn-secondary <?= $currentTab === 'system' ? 'active' : '' ?>">Konfiguracija Sistema</a>
        </div>

        <div class="card__content">

            <div class="tab-content p-md <?= $currentTab === 'classes' ? 'active' : '' ?>" id="classes">
                <div class="d-flex justify-between items-center mb-lg">
                    <h2 class="text-lg font-medium mt-0 mb-0">Upravljanje Razredov</h2>
                    <button class="btn btn-primary btn-sm d-flex items-center gap-xs" id="createClassBtn">
                        <span class="text-lg">+</span> Ustvari Nov Razred
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ime</th>
                            <th>Koda (Leto)</th>
                            <th>Razrednik</th>
                            <th class="text-center">Učenci</th>
                            <th class="text-center">Predmeti</th>
                            <th class="text-right">Dejanja</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="7" class="text-center p-lg">
                                    <div class="alert status-info mb-0">
                                        Ni še ustvarjenih razredov. Uporabite gumb zgoraj za dodajanje.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classes as $class): ?>
                                <tr>
                                    <td><?= $class['class_id'] ?></td>
                                    <td><?= htmlspecialchars($class['title']) ?></td>
                                    <td><?= htmlspecialchars($class['class_code']) ?></td>
                                    <td><?= htmlspecialchars($class['homeroom_teacher_name'] ?? 'N/A') ?></td>
                                    <td class="text-center"><?= $class['student_count'] ?? 0 ?></td>
                                    <td class="text-center"><?= $class['subject_count'] ?? 0 ?></td>
                                    <td>
                                        <div class="d-flex justify-end gap-xs">
                                            <button class="btn btn-secondary btn-sm edit-class-btn d-flex items-center gap-xs"
                                                    data-id="<?= $class['class_id'] ?>">
                                                <span class="text-md">✎</span> Uredi
                                            </button>
                                            <button class="btn btn-secondary btn-sm delete-class-btn d-flex items-center gap-xs"
                                                    data-id="<?= $class['class_id'] ?>"
                                                    data-name="<?= htmlspecialchars($class['title'] ?? '') ?>">
                                                <span class="text-md">🗑</span> Izbriši
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-content p-md <?= $currentTab === 'subjects' ? 'active' : '' ?>" id="subjects">
                <div class="d-flex justify-between items-center mb-lg">
                    <h2 class="text-lg font-medium mt-0 mb-0">Upravljanje Predmetov</h2>
                    <button class="btn btn-primary btn-sm d-flex items-center gap-xs" id="createSubjectBtn">
                        <span class="text-lg">+</span> Ustvari Nov Predmet
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ime</th>
                            <th class="text-center">Razredi</th>
                            <th class="text-center">Učitelji</th>
                            <th class="text-right">Dejanja</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="5" class="text-center p-lg">
                                    <div class="alert status-info mb-0">
                                        Ni še ustvarjenih predmetov. Uporabite gumb zgoraj za dodajanje.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?= $subject['subject_id'] ?></td>
                                    <td><?= htmlspecialchars($subject['name']) ?></td>
                                    <td class="text-center"><?= $subject['class_count'] ?? 0 ?></td>
                                    <td class="text-center"><?= $subject['teacher_count'] ?? 0 ?></td>
                                    <td>
                                        <div class="d-flex justify-end gap-xs">
                                            <a href="/uwuweb/admin/settings.php?tab=subjects&subject_id=<?= $subject['subject_id'] ?>"
                                               class="btn btn-secondary btn-sm d-flex items-center gap-xs">
                                                <span class="text-md">✎</span> Uredi
                                            </a>
                                            <button class="btn btn-secondary btn-sm delete-subject-btn d-flex items-center gap-xs"
                                                    data-id="<?= $subject['subject_id'] ?>"
                                                    data-name="<?= htmlspecialchars($subject['name'] ?? '') ?>">
                                                <span class="text-md">🗑</span> Izbriši
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-content p-md <?= $currentTab === 'assign' ? 'active' : '' ?>" id="assign">
                <div class="d-flex justify-between items-center mb-lg">
                    <h2 class="text-lg font-medium mt-0 mb-0">Upravljanje Dodelitev Razred-Predmet</h2>
                    <button class="btn btn-primary btn-sm d-flex items-center gap-xs" id="createAssignmentBtn">
                        <span class="text-lg">+</span> Ustvari Novo Dodelitev
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Razred</th>
                            <th>Predmet</th>
                            <th>Učitelj</th>
                            <th>Urnik</th>
                            <th class="text-right">Dejanja</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($classSubjects)): ?>
                            <tr>
                                <td colspan="6" class="text-center p-lg">
                                    <div class="alert status-info mb-0">
                                        Ni dodelitev razred-predmet. Uporabite gumb zgoraj za ustvarjanje.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classSubjects as $assignment): ?>
                                <tr>
                                    <td><?= $assignment['class_subject_id'] ?></td>
                                    <td><?= htmlspecialchars($assignment['class_title'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($assignment['subject_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($assignment['teacher_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($assignment['schedule'] ?? '—') ?></td>
                                    <td>
                                        <div class="d-flex justify-end gap-xs">
                                            <button class="btn btn-secondary btn-sm edit-assignment-btn d-flex items-center gap-xs"
                                                    data-id="<?= $assignment['class_subject_id'] ?>">
                                                <span class="text-md">✎</span> Uredi
                                            </button>
                                            <button class="btn btn-secondary btn-sm delete-assignment-btn d-flex items-center gap-xs"
                                                    data-id="<?= $assignment['class_subject_id'] ?>">
                                                <span class="text-md">🗑</span> Izbriši
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-content p-md <?= $currentTab === 'system' ? 'active' : '' ?>" id="system">
                <h2 class="text-lg font-medium mt-0 mb-lg">Konfiguracija Sistema</h2>

                <form method="POST" action="/uwuweb/admin/settings.php?tab=system">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="update_settings" value="1">

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

                    <div class="d-flex justify-end mt-lg">
                        <button type="submit" class="btn btn-primary d-flex items-center gap-xs">
                            <span class="text-md">💾</span> Shrani Nastavitve
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<div class="modal" id="createClassModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Ustvari Nov Razred</h3>
            <button class="btn-close" id="closeCreateClassModal" aria-label="Close modal">×</button>
        </div>
        <form id="createClassForm" method="POST" action="/uwuweb/admin/settings.php?tab=classes">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_class" value="1">

                <div class="form-group mb-md">
                    <label class="form-label" for="create_class_name">Ime Razreda:</label>
                    <input type="text" id="create_class_name" name="class_name" class="form-input" required
                           placeholder="npr. 1.A, 2.B, 3.C">
                    <small class="text-secondary d-block mt-xs">Edinstveno ime za razred.</small>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="create_school_year">Koda Razreda (Šolsko Leto):</label>
                    <input type="text" id="create_school_year" name="school_year" class="form-input" required
                           placeholder="npr. 2024/2025">
                    <small class="text-secondary d-block mt-xs">Običajno šolsko leto, uporabljeno kot koda.</small>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="create_homeroom_teacher_id">Razrednik:</label>
                    <select id="create_homeroom_teacher_id" name="homeroom_teacher_id" class="form-input form-select"
                            required>
                        <option value="">-- Izberi Učitelja --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username'] ?? 'Neznan Učitelj') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($teachers)): ?>
                        <small class="text-warning d-block mt-xs">Ni najdenih učiteljev. Najprej ustvarite
                            učitelje.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelCreateClassBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary d-flex items-center gap-xs" id="saveClassBtn">
                    <span class="text-lg">+</span> Ustvari Razred
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="editClassModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Uredi Razred</h3>
            <button class="btn-close" id="closeEditClassModal" aria-label="Close modal">×</button>
        </div>
        <form id="editClassForm" method="POST" action="/uwuweb/admin/settings.php?tab=classes">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_class" value="1">
                <input type="hidden" name="class_id" id="edit_class_id" value="">

                <div class="form-group mb-md">
                    <label class="form-label" for="edit_class_name">Ime Razreda:</label>
                    <input type="text" id="edit_class_name" name="class_name" class="form-input" required
                           placeholder="npr. 1.A, 2.B, 3.C">
                    <small class="text-secondary d-block mt-xs">Edinstveno ime za razred.</small>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="edit_school_year">Koda Razreda (Šolsko Leto):</label>
                    <input type="text" id="edit_school_year" name="class_code" class="form-input" required
                           placeholder="npr. 2024/2025">
                    <small class="text-secondary d-block mt-xs">Običajno šolsko leto, uporabljeno kot koda.</small>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="edit_homeroom_teacher_id">Razrednik:</label>
                    <select id="edit_homeroom_teacher_id" name="homeroom_teacher_id" class="form-input form-select"
                            required>
                        <option value="">-- Izberi Učitelja --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username'] ?? 'Neznan Učitelj') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelEditClassBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary">Posodobi Razred</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="editAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Uredi Dodelitev Razred-Predmet</h3>
            <button class="btn-close" id="closeEditAssignmentModal" aria-label="Close modal">×</button>
        </div>
        <form id="editAssignmentForm" method="POST" action="/uwuweb/admin/settings.php?tab=assign">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_assignment" value="1">
                <input type="hidden" name="assignment_id" id="edit_assignment_id" value="">

                <div class="form-group mb-md">
                    <label class="form-label" for="edit_assign_class_id">Razred:</label>
                    <select id="edit_assign_class_id" name="class_id" class="form-input form-select" required>
                        <option value="">-- Izberi Razred --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['title']) ?>
                                (<?= htmlspecialchars($class['class_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="edit_assign_subject_id">Predmet:</label>
                    <select id="edit_assign_subject_id" name="subject_id" class="form-input form-select" required>
                        <option value="">-- Izberi Predmet --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['name'] ?? 'Neznan Predmet') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="edit_assign_teacher_id">Učitelj:</label>
                    <select id="edit_assign_teacher_id" name="teacher_id" class="form-input form-select" required>
                        <option value="">-- Izberi Učitelja --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username'] ?? 'Neznan Učitelj') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="edit_assign_schedule">Urnik (Opcijsko):</label>
                    <input type="text" id="edit_assign_schedule" name="schedule" class="form-input"
                           placeholder="npr. Pon 9:00-10:30, Sre 13:00-14:30">
                    <small class="text-secondary d-block mt-xs">Informativno besedilo o času pouka.</small>
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelEditAssignmentBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary">Posodobi Dodelitev</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="createSubjectModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Ustvari Nov Predmet</h3>
            <button class="btn-close" id="closeCreateSubjectModal" aria-label="Close modal">×</button>
        </div>
        <form id="createSubjectForm" method="POST" action="/uwuweb/admin/settings.php?tab=subjects">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_subject" value="1">

                <div class="form-group mb-0">
                    <label class="form-label" for="create_subject_name">Ime Predmeta:</label>
                    <input type="text" id="create_subject_name" name="subject_name" class="form-input" required
                           placeholder="npr. Matematika, Fizika">
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelCreateSubjectBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary d-flex items-center gap-xs" id="saveSubjectBtn">
                    <span class="text-lg">+</span> Ustvari Predmet
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="createAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Ustvari Novo Dodelitev Razred-Predmet</h3>
            <button class="btn-close" id="closeCreateAssignmentModal" aria-label="Close modal">×</button>
        </div>
        <form id="createAssignmentForm" method="POST" action="/uwuweb/admin/settings.php?tab=assign">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_assignment" value="1">

                <div class="form-group mb-md">
                    <label class="form-label" for="assign_class_id">Razred:</label>
                    <select id="assign_class_id" name="class_id" class="form-input form-select" required>
                        <option value="">-- Izberi Razred --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['title']) ?>
                                (<?= htmlspecialchars($class['class_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($classes)): ?>
                        <small class="text-warning d-block mt-xs">Ni najdenih razredov. Najprej ustvarite
                            razrede.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="assign_subject_id">Predmet:</label>
                    <select id="assign_subject_id" name="subject_id" class="form-input form-select" required>
                        <option value="">-- Izberi Predmet --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['name'] ?? 'Neznan Predmet') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($subjects)): ?>
                        <small class="text-warning d-block mt-xs">Ni najdenih predmetov. Najprej ustvarite
                            predmete.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-md">
                    <label class="form-label" for="assign_teacher_id">Učitelj:</label>
                    <select id="assign_teacher_id" name="teacher_id" class="form-input form-select" required>
                        <option value="">-- Izberi Učitelja --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username'] ?? 'Neznan Učitelj') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($teachers)): ?>
                        <small class="text-warning d-block mt-xs">Ni najdenih učiteljev. Najprej ustvarite
                            učitelje.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="assign_schedule">Urnik (Opcijsko):</label>
                    <input type="text" id="assign_schedule" name="schedule" class="form-input"
                           placeholder="npr. Pon 9:00-10:30, Sre 13:00-14:30">
                    <small class="text-secondary d-block mt-xs">Informativno besedilo o času pouka.</small>
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelCreateAssignmentBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary d-flex items-center gap-xs" id="saveAssignmentBtn">
                    <span class="text-lg">+</span> Ustvari Dodelitev
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const csrfTokenValue = '<?= htmlspecialchars($csrfToken) ?>';

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable) {
                    focusable.focus();
                }
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('open');
            }
        }

        const modalTriggers = [
            {btnId: 'createClassBtn', modalId: 'createClassModal'},
            {btnId: 'createSubjectBtn', modalId: 'createSubjectModal'},
            {btnId: 'createAssignmentBtn', modalId: 'createAssignmentModal'}
        ];

        modalTriggers.forEach(trigger => {
            const btn = document.getElementById(trigger.btnId);
            if (btn) {
                btn.addEventListener('click', () => openModal(trigger.modalId));
            }
        });

        document.querySelectorAll('.modal').forEach(modal => {
            const modalId = modal.id;
            const closeBtn = modal.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => closeModal(modalId));
            }
            const cancelBtnId = `cancel${modalId.replace('Modal', '')}Btn`;
            const cancelBtn = document.getElementById(cancelBtnId);
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => closeModal(modalId));
            }
            const overlay = modal.querySelector('.modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', () => closeModal(modalId));
            }
        });

        function handleEditClass(classIdStr) {
            const classId = parseInt(classIdStr, 10);
            if (isNaN(classId)) {
                console.error('Invalid class ID:', classIdStr);
                return;
            }

            // Find class in the classes array
            const classes = <?php
                try {
                    echo json_encode($classes, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    echo '[]';
                }
                ?>;
            if (!classes) return;

            const classInfo = classes.find(c => parseInt(c.class_id, 10) === classId);

            if (classInfo) {
                document.getElementById('edit_class_id').value = classInfo.class_id;
                document.getElementById('edit_class_name').value = classInfo.title || '';
                document.getElementById('edit_school_year').value = classInfo.class_code || '';

                const teacherSelect = document.getElementById('edit_homeroom_teacher_id');
                if (teacherSelect && 'options' in teacherSelect && classInfo.homeroom_teacher_id) {
                    const teacherId = classInfo.homeroom_teacher_id.toString();
                    const options = Array.from(teacherSelect.options || []);

                    const matchingOption = options.find(option => option.value === teacherId);
                    if (matchingOption) {
                        matchingOption.selected = true;
                    }
                }

                openModal('editClassModal');
            }
        }

        function handleEditAssignment(assignmentIdStr) {
            const assignmentId = parseInt(assignmentIdStr, 10);
            if (isNaN(assignmentId)) {
                console.error('Invalid assignment ID:', assignmentIdStr);
                return;
            }

            const assignments = <?php
                try {
                    echo json_encode($classSubjects, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    echo '[]';
                }
                ?>;
            if (!assignments) return;

            const assignmentInfo = assignments.find(a => {
                if (a && typeof a === 'object' && 'class_subject_id' in a) {
                    return parseInt(a.class_subject_id, 10) === assignmentId;
                }
                return false;
            });

            if (assignmentInfo && 'class_subject_id' in assignmentInfo) {
                document.getElementById('edit_assignment_id').value = assignmentInfo.class_subject_id;

                const classSelect = document.getElementById('edit_assign_class_id');
                const subjectSelect = document.getElementById('edit_assign_subject_id');
                const teacherSelect = document.getElementById('edit_assign_teacher_id');

                if (classSelect && 'options' in classSelect && assignmentInfo.class_id) {
                    const classId = assignmentInfo.class_id.toString();
                    const classOptions = Array.from(classSelect.options || []);

                    const matchingClassOption = classOptions.find(option => option.value === classId);
                    if (matchingClassOption) {
                        matchingClassOption.selected = true;
                    }
                }

                if (subjectSelect && 'options' in subjectSelect && assignmentInfo.subject_id) {
                    const subjectId = assignmentInfo.subject_id.toString();
                    const subjectOptions = Array.from(subjectSelect.options || []);

                    const matchingSubjectOption = subjectOptions.find(option => option.value === subjectId);
                    if (matchingSubjectOption) {
                        matchingSubjectOption.selected = true;
                    }
                }

                if (teacherSelect && 'options' in teacherSelect && assignmentInfo.teacher_id) {
                    const teacherId = assignmentInfo.teacher_id.toString();
                    const teacherOptions = Array.from(teacherSelect.options || []);

                    const matchingTeacherOption = teacherOptions.find(option => option.value === teacherId);
                    if (matchingTeacherOption) {
                        matchingTeacherOption.selected = true;
                    }
                }

                document.getElementById('edit_assign_schedule').value = assignmentInfo.schedule || '';

                openModal('editAssignmentModal');
            }
        }

        function submitForm(form) {
            if (form) {
                form.submit();
            }
        }

        function handleDeleteClick(event, itemType, idAttribute, nameAttribute, formAction, hiddenInputName) {
            const button = event.target.closest('button');
            if (!button) return;

            const id = button.dataset[idAttribute];
            const name = button.dataset[nameAttribute] || `ID ${id}`;
            const confirmationMessage = `Ali ste prepričani, da želite izbrisati ${itemType === 'subject' ? 'predmet' : itemType === 'class' ? 'razred' : 'dodelitev'} "${name}"? Tega dejanja ni mogoče razveljaviti.`;

            if (confirm(confirmationMessage)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = formAction;

                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfTokenValue;
                form.appendChild(csrfInput);

                const deleteInput = document.createElement('input');
                deleteInput.type = 'hidden';
                deleteInput.name = hiddenInputName;
                deleteInput.value = '1';
                form.appendChild(deleteInput);

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = `${itemType}_id`;
                idInput.value = id;
                form.appendChild(idInput);

                document.body.appendChild(form);
                submitForm(form);
            }
        }

        const tablesContainer = document.querySelector('.card__content');

        if (tablesContainer) {
            tablesContainer.addEventListener('click', function (event) {
                if (event.target.closest('.delete-subject-btn')) {
                    handleDeleteClick(event, 'subject', 'id', 'name', '/uwuweb/admin/settings.php?tab=subjects', 'delete_subject');
                } else if (event.target.closest('.delete-class-btn')) {
                    handleDeleteClick(event, 'class', 'id', 'name', '/uwuweb/admin/settings.php?tab=classes', 'delete_class');
                } else if (event.target.closest('.delete-assignment-btn')) {
                    handleDeleteClick(event, 'assignment', 'id', 'id', '/uwuweb/admin/settings.php?tab=assign', 'delete_assignment');
                } else if (event.target.closest('.edit-class-btn')) {
                    const button = event.target.closest('.edit-class-btn');
                    const classId = button.dataset.id;
                    handleEditClass(classId);
                } else if (event.target.closest('.edit-assignment-btn')) {
                    const button = event.target.closest('.edit-assignment-btn');
                    const assignmentId = button.dataset.id;
                    handleEditAssignment(assignmentId);
                }
            });
        }
    });
</script>

<?php
include '../includes/footer.php';
?>
