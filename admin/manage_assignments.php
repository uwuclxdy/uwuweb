<?php
/**
 * Admin Class-Subject Assignment Management
 * /uwuweb/admin/manage_assignments.php
 *
 * Provides functionality for administrators to manage class-subject assignments,
 * linking classes, subjects, and teachers together.
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
$classSubjectDetails = null;

// Load data
$classes = getAllClasses();
$subjects = getAllSubjects();
$teachers = getAllTeachers();
$classSubjects = getAllClassSubjectAssignments();

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
    $messageType = 'error';
    error_log("CSRF token validation failed for user ID: " . ($_SESSION['user_id'] ?? 'Unknown'));
} else {
    $action = array_key_first(array_intersect_key($_POST, [
        'create_assignment' => 1, 'update_assignment' => 1, 'delete_assignment' => 1,
    ]));

    switch ($action) {
        case 'create_assignment':
            $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
            $subjectId = filter_input(INPUT_POST, 'subject_id', FILTER_VALIDATE_INT);
            $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
            $schedule = trim($_POST['schedule'] ?? '');

            if (!$classId || !$subjectId || !$teacherId) {
                $message = 'Vsa polja so obvezna.';
                $messageType = 'error';
            } elseif (assignSubjectToClass([
                'class_id' => $classId,
                'subject_id' => $subjectId,
                'teacher_id' => $teacherId,
                'schedule' => $schedule
            ])) {
                $message = 'Povezava uspešno ustvarjena.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri ustvarjanju povezave. Preverite, da povezava še ne obstaja.';
                $messageType = 'error';
            }
            break;

        case 'update_assignment':
            $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
            $teacherId = filter_input(INPUT_POST, 'teacher_id', FILTER_VALIDATE_INT);
            $schedule = trim($_POST['schedule'] ?? '');

            if (!$assignmentId || !$teacherId) {
                $message = 'Vsa polja so obvezna.';
                $messageType = 'error';
            } elseif (updateClassSubjectAssignment($assignmentId, [
                'teacher_id' => $teacherId,
                'schedule' => $schedule
            ])) {
                $message = 'Povezava uspešno posodobljena.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri posodabljanju povezave.';
                $messageType = 'error';
            }
            break;

        case 'delete_assignment':
            $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
            if (!$assignmentId) {
                $message = 'Neveljaven ID povezave.';
                $messageType = 'error';
            } elseif (removeSubjectFromClass($assignmentId)) {
                $message = 'Povezava uspešno izbrisana.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri brisanju povezave. Preverite, ali obstajajo odvisni podatki.';
                $messageType = 'error';
            }
            break;
    }

    // Reload data after changes
    $classSubjects = getAllClassSubjectAssignments();
}

if (isset($_GET['class_subject_id'])) {
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
    error_log("CSRF token generation failed in admin/manage_assignments.php: " . $e->getMessage());
    $message = 'Generiranje varnostnega žetona ni uspelo. Poskusite znova kasneje.';
    $messageType = 'error';
}

?>

<div class="container mt-lg">
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">Upravljanje Dodelitev</h1>
                <p class="text-secondary mt-0 mb-0">Povezovanje razredov, predmetov in učiteljev.</p>
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
        <div class="d-flex justify-between items-center p-md"
             style="border-bottom: 1px solid var(--border-color-medium);">
            <h2 class="text-lg font-medium mt-0 mb-0">Seznam dodelitev</h2>
            <button class="btn btn-primary btn-sm d-flex items-center gap-xs" data-modal-target="createAssignmentModal">
                <span class="text-lg">+</span> Dodaj Novo Dodelitev
            </button>
        </div>
        <div class="card__content">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Razred</th>
                    <th>Predmet</th>
                    <th>Učitelj</th>
                    <th>Urnik</th>
                    <th>Akcije</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($classSubjects)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-lg">Ni dodelitev za prikaz.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classSubjects as $assignment): ?>
                        <tr>
                            <td><?= htmlspecialchars($assignment['class_title']) ?></td>
                            <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                            <td><?= htmlspecialchars($assignment['teacher_name']) ?></td>
                            <td><?= htmlspecialchars($assignment['schedule'] ?? 'Ni določeno') ?></td>
                            <td class="actions">
                                <button type="button" class="btn btn-sm btn-icon"
                                        data-edit="assignment"
                                        data-id="<?= $assignment['class_subject_id'] ?>"
                                        aria-label="Uredi dodelitev">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <button type="button" class="btn btn-sm btn-icon btn-danger"
                                        data-delete="assignment"
                                        data-id="<?= $assignment['class_subject_id'] ?>"
                                        data-class="<?= htmlspecialchars($assignment['class_title']) ?>"
                                        data-subject="<?= htmlspecialchars($assignment['subject_name']) ?>"
                                        aria-label="Izbriši dodelitev">
                                    <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Assignment Modal -->
<div class="modal" id="createAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center">
            <h2 class="h3 mb-0">Dodaj novo dodelitev</h2>
            <button type="button" class="btn btn-sm btn-icon modal-close" aria-label="Zapri">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form id="createAssignmentForm" method="POST" action="/uwuweb/admin/manage_assignments.php">
            <div class="modal-content p-md">
                <div class="form-group mb-md">
                    <label for="class_id">Razred</label>
                    <select id="class_id" name="class_id" class="form-control" required>
                        <option value="">Izberi razred</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>"><?= htmlspecialchars($class['title']) ?>
                                (<?= htmlspecialchars($class['class_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-md">
                    <label for="subject_id">Predmet</label>
                    <select id="subject_id" name="subject_id" class="form-control" required>
                        <option value="">Izberi predmet</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= $subject['subject_id'] ?>"><?= htmlspecialchars($subject['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-md">
                    <label for="teacher_id">Učitelj</label>
                    <select id="teacher_id" name="teacher_id" class="form-control" required>
                        <option value="">Izberi učitelja</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-md">
                    <label for="schedule">Urnik (neobvezno)</label>
                    <input type="text" id="schedule" name="schedule" class="form-control"
                           placeholder="npr. PON 8:00-9:30, SRE 10:00-11:30">
                </div>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="create_assignment" value="1">
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm">
                <button type="button" class="btn btn-secondary modal-close">Prekliči</button>
                <button type="submit" class="btn btn-primary d-flex items-center gap-xs">
                    <span class="text-lg">+</span> Shrani
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal" id="editAssignmentModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container card shadow-lg rounded-lg">
        <div class="modal-header p-md d-flex justify-between items-center">
            <h2 class="h3 mb-0">Uredi dodelitev</h2>
            <button type="button" class="btn btn-sm btn-icon modal-close" aria-label="Zapri">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form id="editAssignmentForm" method="POST" action="/uwuweb/admin/manage_assignments.php">
            <div class="modal-content p-md">
                <div class="form-group mb-md">
                    <label for="edit_class_subject_info">Razred - Predmet</label>
                    <input type="text" id="edit_class_subject_info" class="form-control" disabled>
                </div>
                <div class="form-group mb-md">
                    <label for="edit_teacher_id">Učitelj</label>
                    <select id="edit_teacher_id" name="teacher_id" class="form-control" required>
                        <option value="">Izberi učitelja</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?= $teacher['teacher_id'] ?>"><?= htmlspecialchars($teacher['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group mb-md">
                    <label for="edit_schedule">Urnik (neobvezno)</label>
                    <input type="text" id="edit_schedule" name="schedule" class="form-control"
                           placeholder="npr. PON 8:00-9:30, SRE 10:00-11:30">
                </div>
                <input type="hidden" name="assignment_id" id="edit_assignment_id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="update_assignment" value="1">
            </div>
            <div class="modal-footer p-md d-flex justify-end gap-sm">
                <button type="button" class="btn btn-secondary modal-close">Prekliči</button>
                <button type="submit" class="btn btn-primary">Posodobi</button>
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
                modal.classList.add('show');
                document.body.classList.add('modal-open');
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
                document.body.classList.remove('modal-open');
            }
        }

        const modalTriggers = document.querySelectorAll('[data-modal-target]');
        modalTriggers.forEach(trigger => {
            trigger.addEventListener('click', () => {
                const modal = trigger.getAttribute('data-modal-target');
                openModal(modal);
            });
        });

        document.querySelectorAll('.modal-close, .modal-overlay').forEach(closer => {
            closer.addEventListener('click', (event) => {
                const modal = event.target.closest('.modal');
                if (modal) {
                    modal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                }
            });
        });

        // Handle edit buttons
        document.querySelectorAll('[data-edit="assignment"]').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');

                // Find assignment details
                <?php
                $assignmentsJson = json_encode($classSubjects, JSON_THROW_ON_ERROR);
                echo "const assignments = " . $assignmentsJson . ";";
                ?>

                const assignment = assignments.find(a => a.class_subject_id === id);
                if (assignment) {
                    document.getElementById('edit_assignment_id').value = assignment.class_subject_id;
                    document.getElementById('edit_class_subject_info').value = `${assignment.class_name} - ${assignment.subject_name}`;
                    document.getElementById('edit_teacher_id').value = assignment.teacher_id;
                    document.getElementById('edit_schedule').value = assignment.schedule || '';

                    openModal('editAssignmentModal');
                }
            });
        });

        // Handle delete buttons
        document.querySelectorAll('[data-delete="assignment"]').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const className = button.getAttribute('data-class');
                const subjectName = button.getAttribute('data-subject');

                if (confirm(`Ali ste prepričani, da želite izbrisati dodelitev "${className} - ${subjectName}"? Ta operacija je dokončna.`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/uwuweb/admin/manage_assignments.php';

                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = csrfTokenValue;
                    form.appendChild(csrfInput);

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'assignment_id';
                    idInput.value = id;
                    form.appendChild(idInput);

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_assignment';
                    actionInput.value = '1';
                    form.appendChild(actionInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
</script>

<?php
include '../includes/footer.php';
?>
