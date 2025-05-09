<?php
/**
 * Teacher Attendance Form
 *
 * Provides interface for teachers to manage student attendance
 * Supports tracking attendance for class periods
 *
 */

use Random\RandomException;

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'teacher_functions.php';

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId();
if (!$teacherId) die('Napaka: Učiteljev račun ni bil najden.');

// Database connection
$pdo = safeGetDBConnection('teacher/attendance.php');

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Invalid form submission. Please try again.';
    $messageType = 'error';
} else if (isset($_POST['add_period'])) {
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $periodDate = $_POST['period_date'] ?? '';
    $periodLabel = isset($_POST['period_label']) ? trim($_POST['period_label']) : '';

    if ($classId <= 0 || empty($periodDate) || empty($periodLabel)) {
        $message = 'Please fill out all period details.';
        $messageType = 'error';
    } else if (addPeriod($classId, $periodDate, $periodLabel)) {
        $message = 'New period added successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error adding period. Please try again.';
        $messageType = 'error';
    }
} else if (isset($_POST['save_attendance'])) {
    $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
    $status = $_POST['status'] ?? [];

    if ($periodId <= 0) {
        $message = 'Invalid attendance data.';
        $messageType = 'error';
    } else {
        $success = true;
        foreach ($status as $enrollId => $statusCode) if (!saveAttendance($enrollId, $periodId, $statusCode)) $success = false;

        if ($success) {
            $message = 'Attendance saved successfully.';
            $messageType = 'success';
        } else {
            $message = 'Some attendance records failed to save. Please try again.';
            $messageType = 'warning';
        }
    }
}

// Get teacher's classes
$classes = getTeacherClasses($teacherId);

// Selected class and period
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_id'] ?? 0);
$periods = $selectedClassId ? getClassPeriods($selectedClassId) : [];
$selectedPeriodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : ($periods[0]['period_id'] ?? 0);

// Get students and attendance if a class and period are selected
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$attendance = ($selectedClassId && $selectedPeriodId) ? getPeriodAttendance($selectedPeriodId) : [];

// Generate CSRF token
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    die('Error generating CSRF token.');
}

?>

<!-- Main title card with page heading -->
<div class="card shadow mb-lg mt-lg page-transition">
    <div class="d-flex justify-between items-center">
        <div>
            <h2 class="mt-0 mb-xs">Evidenca prisotnosti</h2>
            <p class="text-secondary mt-0 mb-0">Upravljanje prisotnosti za vaše razrede</p>
        </div>
        <div class="role-badge role-teacher">Učitelj</div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert status-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'error') ?> mb-lg">
        <div class="alert-icon">
            <?php if ($messageType === 'success'): ?>✓
            <?php elseif ($messageType === 'warning'): ?>⚠
            <?php else: ?>✕
            <?php endif; ?>
        </div>
        <div class="alert-content">
            <?= htmlspecialchars((string)$message) ?>
        </div>
    </div>
<?php endif; ?>

<!-- Class selection form -->
<div class="card shadow mb-lg">
    <div class="card__content">
        <form method="GET" action="/uwuweb/teacher/attendance.php" class="d-flex items-center gap-md flex-wrap">
            <div class="form-group mb-0" style="flex: 1;">
                <label for="class_id" class="form-label">Izberite razred:</label>
                <select id="class_id" name="class_id" class="form-input form-select">
                    <option value="">-- Izberite razred --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['class_id'] ?>" <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['class_title']) ?>
                            - <?= htmlspecialchars($class['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Izberi razred</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClassId): ?>
    <!-- Tab navigation -->
    <div class="card shadow mb-lg">
        <div class="d-flex mb-lg p-sm" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <button class="btn tab-btn active" data-tab="periods">Učne ure</button>
            <button class="btn tab-btn" data-tab="attendance">Zabeleži prisotnost</button>
            <button class="btn tab-btn" data-tab="report">Poročilo o prisotnosti</button>
        </div>

        <!-- Class Periods tab content -->
        <div class="tab-content active" id="periods">
            <div class="d-flex justify-between mb-md p-md">
                <h3 class="mt-0 mb-0">Učne ure</h3>
                <button class="btn btn-primary btn-sm" id="addPeriodBtn">
                    <span class="btn-icon">+</span> Dodaj učno uro
                </button>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Oznaka</th>
                        <th>Status</th>
                        <th>Dejanja</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($periods)): ?>
                        <tr>
                            <td colspan="4" class="text-center p-lg">
                                <div class="alert status-info mb-0">
                                    <div class="alert-icon">ℹ</div>
                                    <div class="alert-content">
                                        Za ta razred še ni učnih ur. Kliknite "Dodaj učno uro" za ustvarjanje prve učne
                                        ure.
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($periods as $period): ?>
                            <tr>
                                <td><?= htmlspecialchars($period['date']) ?></td>
                                <td><?= htmlspecialchars($period['label']) ?></td>
                                <td>
                                    <?php
                                    $completed = isset($period['attendance_count']) && $period['attendance_count'] > 0;
                                    echo $completed ?
                                        '<span class="badge badge-success">Zaključeno</span>' :
                                        '<span class="badge badge-warning">V teku</span>';
                                    ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-xs">
                                        <a href="/uwuweb/teacher/attendance.php?class_id=<?= $selectedClassId ?>&period_id=<?= $period['period_id'] ?>"
                                           class="btn btn-secondary btn-sm">Zabeleži prisotnost</a>
                                        <?php if (!$completed): ?>
                                            <button class="btn btn-sm delete-period-btn"
                                                    data-id="<?= $period['period_id'] ?>">Izbriši
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Take Attendance tab content -->
        <div class="tab-content" id="attendance">
            <?php if ($selectedPeriodId): ?>
                <form method="POST"
                      action="attendance.php?class_id=<?= $selectedClassId ?>&period_id=<?= $selectedPeriodId ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="save_attendance" value="1">
                    <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">

                    <div class="d-flex justify-between mb-md p-md">
                        <h3 class="mt-0 mb-0">Take Attendance</h3>
                        <button type="submit" class="btn btn-primary btn-sm">Save Attendance</button>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Student</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="3" class="text-center p-lg">
                                        <div class="alert status-info mb-0">
                                            <div class="alert-icon">ℹ</div>
                                            <div class="alert-content">
                                                No students enrolled in this class.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($students as $student): ?>
                                    <?php $status = $attendance[$student['enrollment_id']]['status'] ?? 'present'; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                        <td>
                                            <div class="d-flex gap-md">
                                                <label class="d-flex items-center gap-xs">
                                                    <input type="radio" name="status[<?= $student['enrollment_id'] ?>]"
                                                           value="present" <?= $status === 'present' ? 'checked' : '' ?>>
                                                    <span class="attendance-status status-present">Present</span>
                                                </label>

                                                <label class="d-flex items-center gap-xs">
                                                    <input type="radio" name="status[<?= $student['enrollment_id'] ?>]"
                                                           value="absent" <?= $status === 'absent' ? 'checked' : '' ?>>
                                                    <span class="attendance-status status-absent">Absent</span>
                                                </label>

                                                <label class="d-flex items-center gap-xs">
                                                    <input type="radio" name="status[<?= $student['enrollment_id'] ?>]"
                                                           value="late" <?= $status === 'late' ? 'checked' : '' ?>>
                                                    <span class="attendance-status status-late">Late</span>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <label>
                                                <input type="text" class="form-input"
                                                       name="notes[<?= $student['enrollment_id'] ?>]"
                                                       value="<?= htmlspecialchars($attendance[$student['enrollment_id']]['notes'] ?? '') ?>"
                                                       placeholder="Optional notes">
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php else: ?>
                <div class="p-lg text-center">
                    <div class="alert status-info">
                        <div class="alert-icon">ℹ</div>
                        <div class="alert-content">
                            Please select a period from the "Class Periods" tab first.
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Attendance Report tab content -->
        <div class="tab-content" id="report">
            <div class="p-md">
                <h3 class="mt-0 mb-lg">Attendance Overview</h3>

                <?php if (!empty($students)): ?>
                    <div class="dashboard-grid">
                        <?php foreach ($students as $student): ?>
                            <div class="card shadow mb-sm">
                                <div class="card__title"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                <div class="card__content">
                                    <?php
                                    // Get attendance records for this student
                                    $studentAttendance = [];
                                    foreach ($attendance as $record) if ($record['enrollment_id'] == $student['enrollment_id']) $studentAttendance[] = $record;
                                    // Calculate attendance stats using the standard function
                                    $stats = calculateAttendanceStats($studentAttendance);
                                    ?>
                                    <div class="d-flex flex-column gap-sm">
                                        <div class="d-flex justify-between">
                                            <span>Present:</span>
                                            <span class="badge badge-success"><?= $stats['present'] ?? 0 ?></span>
                                        </div>
                                        <div class="d-flex justify-between">
                                            <span>Absent:</span>
                                            <span class="badge badge-error"><?= $stats['absent'] ?? 0 ?></span>
                                        </div>
                                        <div class="d-flex justify-between">
                                            <span>Late:</span>
                                            <span class="badge badge-warning"><?= $stats['late'] ?? 0 ?></span>
                                        </div>
                                        <div class="d-flex justify-between mt-sm">
                                            <span>Attendance Rate:</span>
                                            <span class="attendance-rate <?= ($stats['rate'] ?? 0) >= 90 ? 'text-success' :
                                                (($stats['rate'] ?? 0) >= 75 ? 'text-warning' : 'text-error') ?>">
                                                <?= number_format($stats['rate'] ?? 0, 1) ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert status-info">
                        No students enrolled in this class.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Period Modal -->
    <div class="modal" id="addPeriodModal">
        <div class="modal-overlay"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Add New Class Period</h3>
                <button class="btn-close" id="closeAddPeriodModal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addPeriodForm" method="POST" action="attendance.php?class_id=<?= $selectedClassId ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="add_period" value="1">
                    <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">

                    <div class="form-group">
                        <label class="form-label" for="period_date">Date:</label>
                        <input type="date" id="period_date" name="period_date" class="form-input"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="period_label">Label/Period:</label>
                        <input type="text" id="period_label" name="period_label" class="form-input"
                               placeholder="e.g., 1st Period, Morning Class, etc." required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelAddPeriodBtn">Cancel</button>
                <button class="btn btn-primary" id="savePeriodBtn">Add Period</button>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow">
        <div class="card__content text-center p-xl">
            <div class="alert status-info mb-lg">
                Please select a class to manage attendance.
            </div>
            <p class="text-secondary mb-0">You can add class periods and track student attendance once a class is
                selected.</p>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                // Remove active class from all tabs
                document.querySelectorAll('.tab-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                document.querySelectorAll('.tab-content').forEach(function (c) {
                    c.classList.remove('active');
                });

                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                document.getElementById(this.dataset.tab).classList.add('active');
            });
        });

        // Add Period modal
        const addPeriodModal = document.getElementById('addPeriodModal');
        const addPeriodBtn = document.getElementById('addPeriodBtn');
        const closeAddPeriodModal = document.getElementById('closeAddPeriodModal');
        const cancelAddPeriodBtn = document.getElementById('cancelAddPeriodBtn');
        const savePeriodBtn = document.getElementById('savePeriodBtn');

        if (addPeriodBtn) {
            addPeriodBtn.addEventListener('click', function () {
                addPeriodModal.classList.add('open');
            });
        }

        if (closeAddPeriodModal) {
            closeAddPeriodModal.addEventListener('click', function () {
                addPeriodModal.classList.remove('open');
            });
        }

        if (cancelAddPeriodBtn) {
            cancelAddPeriodBtn.addEventListener('click', function () {
                addPeriodModal.classList.remove('open');
            });
        }

        if (savePeriodBtn) {
            savePeriodBtn.addEventListener('click', function () {
                document.getElementById('addPeriodForm').submit();
            });
        }

        // Close modal when clicking outside
        if (addPeriodModal) {
            addPeriodModal.querySelector('.modal-overlay').addEventListener('click', function () {
                addPeriodModal.classList.remove('open');
            });
        }

        // Delete period confirmation
        document.querySelectorAll('.delete-period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const periodId = this.dataset.id;
                if (confirm('Are you sure you want to delete this period? This cannot be undone.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'attendance.php?class_id=<?= $selectedClassId ?>';

                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = 'csrf_token';
                    csrfToken.value = '<?= htmlspecialchars($csrfToken) ?>';

                    const deletePeriod = document.createElement('input');
                    deletePeriod.type = 'hidden';
                    deletePeriod.name = 'delete_period';
                    deletePeriod.value = '1';

                    const periodIdInput = document.createElement('input');
                    periodIdInput.type = 'hidden';
                    periodIdInput.name = 'period_id';
                    periodIdInput.value = periodId;

                    form.appendChild(csrfToken);
                    form.appendChild(deletePeriod);
                    form.appendChild(periodIdInput);

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
