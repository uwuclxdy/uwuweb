<?php
/**
 * Admin Class-Subject Management
 * /admin/class_subjects.php
 *
 * Provides functionality for administrators to manage class-subject assignments
 * including creating, editing, and deleting assignments
 *
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'admin_functions.php';

// Ensure only administrators can access this page
requireRole(ROLE_ADMIN);

// Initialize variables
$message = '';
$messageType = '';
$assignmentDetails = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
    $messageType = 'error';
    error_log("CSRF token validation failed for user ID: " . ($_SESSION['user_id'] ?? 'Unknown'));
} else if (isset($_POST['create_assignment'])) {
    // Create new class-subject assignment
    $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
    $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
    $schedule = trim($_POST['schedule'] ?? '');

    if (!$classId || !$subjectId || !$teacherId) {
        $message = 'Razred, predmet in učitelj so obvezni.';
        $messageType = 'error';
    } else {
        $assignmentData = [
            'class_id' => $classId,
            'subject_id' => $subjectId,
            'teacher_id' => $teacherId,
            'schedule' => $schedule
        ];

        $result = assignSubjectToClass($assignmentData);
        if ($result) {
            $message = 'Dodelitev predmeta razredu je bila uspešno ustvarjena.';
            $messageType = 'success';
        } else {
            $message = 'Napaka pri ustvarjanju dodelitve. Ta predmet je morda že dodeljen temu razredu.';
            $messageType = 'error';
        }
    }
} elseif (isset($_POST['update_assignment'])) {
    // Update existing class-subject assignment
    $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
    $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);

    if (!$assignmentId || !$teacherId) {
        $message = 'Neveljavna dodelitev ali izbran učitelj.';
        $messageType = 'error';
    } else {
        $assignmentData = [
            'teacher_id' => $teacherId
        ];

        if (updateClassSubjectAssignment($assignmentId, $assignmentData)) {
            $message = 'Dodelitev je bila uspešno posodobljena.';
            $messageType = 'success';
        } else {
            $message = 'Napaka pri posodabljanju dodelitve.';
            $messageType = 'error';
        }
    }
} elseif (isset($_POST['delete_assignment'])) {
    // Delete class-subject assignment
    $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);

    if (!$assignmentId) {
        $message = 'Neveljavna dodelitev izbrana za brisanje.';
        $messageType = 'error';
    } elseif (removeSubjectFromClass($assignmentId)) {
        $message = 'Dodelitev je bila uspešno izbrisana.';
        $messageType = 'success';
    } else {
        $message = 'Napaka pri brisanju dodelitve. Morda ima povezane ocene ali obveznosti.';
        $messageType = 'error';
    }
}

// Check if editing an existing assignment
if (isset($_GET['assignment_id'])) $assignmentId = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);

// Get data for dropdowns
$classes = getAllClasses();
$subjects = getAllSubjects();
$teachers = getAllTeachers();
$classSubjects = getAllClassSubjectAssignments();

// Generate CSRF token
try {
    $csrfToken = generateCSRFToken();
} catch (Exception $e) {
    $csrfToken = '';
    error_log("CSRF token generation failed in admin/class_subjects.php: " . $e->getMessage());
    $message = 'Generiranje varnostnega žetona ni uspelo. Poskusite znova kasneje.';
    $messageType = 'error';
}

// Include page header
require_once '../includes/header.php';
?>

<div class="container mt-lg">
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">Upravljanje predmetov in razredov</h1>
                <p class="text-secondary mt-0 mb-0">Upravljanje dodelitev predmetov razredom in dodelitev učiteljev</p>
            </div>
            <div class="role-badge role-admin">Administrator</div>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType ?> mb-lg">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-lg">
        <div class="card__header p-md d-flex justify-between items-center border-bottom">
            <h2 class="text-lg font-medium mt-0 mb-0">Dodelitve predmetov razredom</h2>
            <button class="btn btn-primary btn-sm d-flex items-center gap-xs" id="createAssignmentBtn">
                <span class="text-lg">+</span> Ustvari novo dodelitev
            </button>
        </div>

        <div class="card__content p-0">
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
                                    Ni dodelitev predmetov razredom. Uporabite gumb zgoraj za ustvarjanje novih.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classSubjects as $assignment): ?>
                            <tr>
                                <td><?= $assignment['class_subject_id'] ?></td>
                                <td><?= htmlspecialchars($assignment['class_title'] ?? 'N/A') ?>
                                    (<?= htmlspecialchars($assignment['class_code'] ?? 'N/A') ?>)
                                </td>
                                <td><?= htmlspecialchars($assignment['subject_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($assignment['teacher_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($assignment['schedule'] ?? '—') ?></td>
                                <td>
                                    <div class="d-flex justify-end gap-xs">
                                        <a href="/uwuweb/admin/class_subjects.php?assignment_id=<?= $assignment['class_subject_id'] ?>"
                                           class="btn btn-secondary btn-sm d-flex items-center gap-xs">
                                            <span class="text-md">✎</span> Uredi
                                        </a>
                                        <button class="btn btn-secondary btn-sm delete-assignment-btn d-flex items-center gap-xs"
                                                data-id="<?= $assignment['class_subject_id'] ?>"
                                                data-name="<?= htmlspecialchars($assignment['subject_name']) ?> - <?= htmlspecialchars($assignment['class_title']) ?>">
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
    </div>
</div>

<!-- Create Assignment Modal -->
<div class="modal" id="createAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Ustvari novo dodelitev predmeta razredu</h3>
            <button class="btn-close" id="closeCreateAssignmentModal" aria-label="Zapri">×</button>
        </div>
        <form id="createAssignmentForm" method="POST" action="/uwuweb/admin/class_subjects.php">
            <div class="modal-body p-md">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_assignment" value="1">

                <div class="form-group mb-md">
                    <label class="form-label" for="assign_class_id">Razred:</label>
                    <select id="assign_class_id" name="class_id" class="form-input form-select" required>
                        <option value="">-- Izberi razred --</option>
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
                        <option value="">-- Izberi predmet --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['name'] ?? 'Neznan predmet') ?></option>
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
                        <option value="">-- Izberi učitelja --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username'] ?? 'Neznan učitelj') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($teachers)): ?>
                        <small class="text-warning d-block mt-xs">Ni najdenih učiteljev. Najprej ustvarite
                            učitelje.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group mb-0">
                    <label class="form-label" for="assign_schedule">Urnik (Neobvezno):</label>
                    <input type="text" id="assign_schedule" name="schedule" class="form-input"
                           placeholder="npr., Pon 9:00-10:30, Sre 13:00-14:30">
                    <small class="text-secondary d-block mt-xs">Informativno besedilo o časih pouka.</small>
                </div>
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm"
                 style="border-top: 1px solid var(--border-color-medium);">
                <button type="button" class="btn btn-secondary" id="cancelCreateAssignmentBtn">Prekliči</button>
                <button type="submit" class="btn btn-primary d-flex items-center gap-xs" id="saveAssignmentBtn">
                    <span class="text-lg">+</span> Ustvari dodelitev
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Assignment Confirmation Modal -->
<div class="modal" id="deleteAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h3 class="modal-title text-lg font-medium mt-0 mb-0">Potrdi brisanje</h3>
            <button class="btn-close" id="closeDeleteAssignmentModal" aria-label="Zapri">×</button>
        </div>
        <div class="modal-body p-md">
            <p class="mb-md">Ali ste prepričani, da želite izbrisati dodelitev: <span id="deleteAssignmentName"
                                                                                      class="font-bold"></span>?</p>
            <p class="text-error mb-0">Tega dejanja ni mogoče razveljaviti. Vsi povezani podatki bodo lahko
                prizadeti.</p>
        </div>
        <div class="modal-footer p-md d-flex justify-end gap-sm"
             style="border-top: 1px solid var(--border-color-medium);">
            <button type="button" class="btn btn-secondary" id="cancelDeleteAssignmentBtn">Prekliči</button>
            <form id="deleteAssignmentForm" method="POST" action="/uwuweb/admin/class_subjects.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="delete_assignment" value="1">
                <input type="hidden" name="assignment_id" id="deleteAssignmentId" value="">
                <button type="submit" class="btn btn-error">Izbriši dodelitev</button>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for the page -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Create Assignment Modal
        const createAssignmentBtn = document.getElementById('createAssignmentBtn');
        const createAssignmentModal = document.getElementById('createAssignmentModal');
        const closeCreateAssignmentModal = document.getElementById('closeCreateAssignmentModal');
        const cancelCreateAssignmentBtn = document.getElementById('cancelCreateAssignmentBtn');

        if (createAssignmentBtn && createAssignmentModal) {
            createAssignmentBtn.addEventListener('click', function () {
                createAssignmentModal.classList.add('active');
            });
        }

        if (closeCreateAssignmentModal) {
            closeCreateAssignmentModal.addEventListener('click', function () {
                createAssignmentModal.classList.remove('active');
            });
        }

        if (cancelCreateAssignmentBtn) {
            cancelCreateAssignmentBtn.addEventListener('click', function () {
                createAssignmentModal.classList.remove('active');
            });
        }

        // Delete Assignment Modal
        const deleteAssignmentModal = document.getElementById('deleteAssignmentModal');
        const closeDeleteAssignmentModal = document.getElementById('closeDeleteAssignmentModal');
        const cancelDeleteAssignmentBtn = document.getElementById('cancelDeleteAssignmentBtn');
        const deleteAssignmentBtns = document.querySelectorAll('.delete-assignment-btn');
        const deleteAssignmentName = document.getElementById('deleteAssignmentName');
        const deleteAssignmentId = document.getElementById('deleteAssignmentId');

        if (deleteAssignmentBtns.length > 0) {
            deleteAssignmentBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');

                    if (deleteAssignmentName) deleteAssignmentName.textContent = name;
                    if (deleteAssignmentId) deleteAssignmentId.value = id;

                    if (deleteAssignmentModal) deleteAssignmentModal.classList.add('active');
                });
            });
        }

        if (closeDeleteAssignmentModal) {
            closeDeleteAssignmentModal.addEventListener('click', function () {
                deleteAssignmentModal.classList.remove('active');
            });
        }

        if (cancelDeleteAssignmentBtn) {
            cancelDeleteAssignmentBtn.addEventListener('click', function () {
                deleteAssignmentModal.classList.remove('active');
            });
        }

        // Close modals when overlay is clicked
        const modalOverlays = document.querySelectorAll('.modal-overlay');
        if (modalOverlays.length > 0) {
            modalOverlays.forEach(overlay => {
                overlay.addEventListener('click', function () {
                    this.closest('.modal').classList.remove('active');
                });
            });
        }
    });
</script>

<?php
// Include page footer
include '../includes/footer.php';
?>
