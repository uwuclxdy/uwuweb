<?php
/**
 * Teacher Attendance Form
 *
 * Provides interface for teachers to manage student attendance
 * Supports tracking attendance for class periods
 *
 * /teacher/attendance.php
 */

use Random\RandomException;

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'teacher_functions.php';

// CSS styles are included in header.php

requireRole(ROLE_TEACHER);

$teacherId = getTeacherId();
if (!$teacherId) die('Napaka: Račun ni bil najden.');

$pdo = safeGetDBConnection('teacher/attendance.php');

// Get the CSRF token for form security
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse('Napaka pri ustvarjanju token-a za zaščito oblike.', 500);
}

// Get classes assigned to the teacher
$teacherClasses = getTeacherClasses($teacherId);

// Restructure teacher classes for dropdown display
$groupedClasses = [];
foreach ($teacherClasses as $class) {
    $classId = $class['class_id'];
    if (!isset($groupedClasses[$classId])) {
        $groupedClasses[$classId] = [
            'class_id' => $classId,
            'class_name' => $class['class_title'] . ' (' . $class['class_code'] . ')',
            'subjects' => []
        ];
    }
    $groupedClasses[$classId]['subjects'][] = [
        'class_subject_id' => $class['class_subject_id'],
        'subject_name' => $class['subject_name']
    ];
}
$teacherClasses = array_values($groupedClasses);

// Default values
$selectedClassSubject = isset($_GET['class_subject_id']) ? (int)$_GET['class_subject_id'] : 0;
$classSubjectName = '';
$periods = [];

// If a class-subject is selected, get periods and name
if ($selectedClassSubject > 0) if (!teacherHasAccessToClassSubject($selectedClassSubject, $teacherId)) {
    echo "Nimate dostopa do izbranega razreda ali predmeta.";
    $selectedClassSubject = 0;
} else {
    // Get the periods for this class-subject
    $periods = getClassPeriods($selectedClassSubject);

    // Get class-subject name for display
    foreach ($teacherClasses as $class) foreach ($class['subjects'] as $subject) if ($subject['class_subject_id'] == $selectedClassSubject) {
        $classSubjectName = $class['class_name'] . ' - ' . $subject['subject_name'];
        break 2;
    }
}

// Handle period selection
$selectedPeriod = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;
$periodAttendance = [];
$periodInfo = null;

if ($selectedPeriod > 0 && $selectedClassSubject > 0) {
    // Get attendance for this period
    $periodAttendance = getPeriodAttendance($selectedPeriod);

    // Get period info for display
    foreach ($periods as $period) if ($period['period_id'] == $selectedPeriod) {
        $periodInfo = $period;
        break;
    }
}

// Generate header card
renderHeaderCard(
    'Vodenje prisotnosti',
    'Upravljajte prisotnost učencev po posameznih urah',
    'Učitelj'
);
?>

<div class="container">
    <div class="section">
        <!-- Class selection -->
        <div class="card mb-lg">
            <div class="card__title">Izberi razred in predmet</div>
            <div class="card__content">
                <form method="GET" action="attendance.php" class="mb-md">
                    <div class="form-group">
                        <label for="class_subject_selector" class="form-label">Razred in predmet:</label>
                        <select id="class_subject_selector" name="class_subject_id" class="form-select"
                                onchange="this.form.submit()">
                            <option value="">-- Izberi razred in predmet --</option>
                            <?php foreach ($teacherClasses as $class): ?>
                                <optgroup label="<?= htmlspecialchars($class['class_name']) ?>">
                                    <?php foreach ($class['subjects'] as $subject): ?>
                                        <option value="<?= $subject['class_subject_id'] ?>"
                                            <?= ($selectedClassSubject == $subject['class_subject_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['subject_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedClassSubject > 0): ?>
            <!-- Periods list -->
            <div class="card mb-lg">
                <div class="card__title">
                    <div class="d-flex items-center justify-between">
                        <span>Ure za <?= htmlspecialchars($classSubjectName) ?></span>
                        <button data-open-modal="addPeriodModal" class="btn btn-primary btn-sm">
                            Dodaj novo uro
                        </button>
                    </div>
                </div>
                <div class="card__content">
                    <?php if (empty($periods)): ?>
                        <p class="text-disabled">Ni zabeleženih ur za ta razred in predmet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Oznaka</th>
                                    <th>Prisotnost</th>
                                    <th>Akcije</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($periods as $period): ?>
                                    <tr>
                                        <td><?= formatDateDisplay($period['period_date']) ?></td>
                                        <td><?= htmlspecialchars($period['period_label']) ?></td>
                                        <td>
                                            <?php
                                            $presentCount = $period['present_count'] ?? 0;
                                            $absentCount = $period['absent_count'] ?? 0;
                                            $lateCount = $period['late_count'] ?? 0;
                                            $total = $presentCount + $absentCount + $lateCount;

                                            if ($total > 0) {
                                                $presentPercentage = round(($presentCount / $total) * 100);
                                                echo "<div class='d-flex items-center gap-sm'>";
                                                echo "<div class='attendance-status status-present'></div> $presentCount";
                                                echo "<div class='attendance-status status-absent'></div> $absentCount";
                                                echo "<div class='attendance-status status-late'></div> $lateCount";
                                                echo "</div>";
                                            } else echo "<span class='text-disabled'>Ni podatkov</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <a href="attendance.php?class_subject_id=<?= $selectedClassSubject ?>&period_id=<?= $period['period_id'] ?>"
                                               class="btn btn-secondary btn-sm">Uredi prisotnost</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($selectedPeriod > 0 && $periodInfo): ?>
                <!-- Attendance management -->
                <div class="card">
                    <div class="card__title">
                        <div class="d-flex items-center justify-between">
                            <span>Prisotnost za <?= formatDateDisplay($periodInfo['period_date']) ?> (<?= htmlspecialchars($periodInfo['period_label']) ?>)</span>
                            <a href="attendance.php?class_subject_id=<?= $selectedClassSubject ?>"
                               class="btn btn-secondary btn-sm">Nazaj na seznam</a>
                        </div>
                    </div>
                    <div class="card__content">
                        <?php if (empty($periodAttendance)): ?>
                            <p class="text-disabled">Ni učencev za ta razred in predmet.</p>
                        <?php else: ?>
                            <form id="attendanceForm" method="POST">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="period_id" value="<?= $selectedPeriod ?>">

                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                        <tr>
                                            <th>Učenec</th>
                                            <th>Status</th>
                                            <th>Opravičilo</th>
                                            <th>Akcije</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($periodAttendance as $record): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?></td>
                                                <td>
                                                    <div class="attendance-status status-<?= strtolower($record['status']) ?>"
                                                         data-status="<?= $record['status'] ?>">
                                                        <?= getAttendanceStatusLabel($record['status']) ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($record['status'] === 'A' || $record['status'] === 'L'): ?>
                                                        <?php if ($record['justification']): ?>
                                                            <?php if ($record['approved'] === null): ?>
                                                                <span class="badge badge-warning">Čaka na odobritev</span>
                                                            <?php elseif ($record['approved']): ?>
                                                                <span class="badge badge-success">Odobreno</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-error">Zavrnjeno: <?= htmlspecialchars($record['reject_reason']) ?></span>
                                                            <?php endif; ?>

                                                            <?php if ($record['justification_file']): ?>
                                                                <a href="download_justification.php?att_id=<?= $record['att_id'] ?>"
                                                                   class="text-accent ml-sm" target="_blank">
                                                                    Datoteka
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-disabled">Ni opravičila</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-disabled">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button"
                                                                class="btn btn-sm attendance-btn <?= $record['status'] === 'P' ? 'btn-accent' : '' ?>"
                                                                data-enroll-id="<?= $record['enroll_id'] ?>"
                                                                data-status="P">P
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-sm attendance-btn <?= $record['status'] === 'A' ? 'btn-accent' : '' ?>"
                                                                data-enroll-id="<?= $record['enroll_id'] ?>"
                                                                data-status="A">A
                                                        </button>
                                                        <button type="button"
                                                                class="btn btn-sm attendance-btn <?= $record['status'] === 'L' ? 'btn-accent' : '' ?>"
                                                                data-enroll-id="<?= $record['enroll_id'] ?>"
                                                                data-status="L">L
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="mt-lg">
                                    <div class="d-flex flex-wrap gap-md mb-md">
                                        <button type="button" class="btn btn-secondary" id="markAllPresent">Vsi
                                            prisotni
                                        </button>
                                        <button type="button" class="btn btn-secondary" id="saveAttendance">Shrani
                                        </button>
                                    </div>

                                    <div id="attendanceStatusMessage"></div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Period Modal -->
<div class="modal" id="addPeriodModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="addPeriodModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="addPeriodModalTitle">Dodaj novo uro</h3>
        </div>
        <form id="addPeriodForm" method="POST">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="class_subject_id" value="<?= $selectedClassSubject ?>">

                <div class="form-group">
                    <label for="period_date" class="form-label">Datum:</label>
                    <input type="date" id="period_date" name="period_date" class="form-input"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label for="period_label" class="form-label">Oznaka ure:</label>
                    <input type="text" id="period_label" name="period_label" class="form-input"
                           placeholder="npr. 1. ura, 2. ura, itd." required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Dodaj</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- Modal Management Functions ---
        const openModal = (modalId) => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                // Focus the first focusable element
                const firstFocusable = modal.querySelector('button, [href], input, select, textarea');
                if (firstFocusable) firstFocusable.focus();
            }
        };

        const closeModal = (modal) => {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }

            if (modal) {
                modal.classList.remove('open');
                // Reset forms if present
                const form = modal.querySelector('form');
                if (form) form.reset();

                // Clear any error messages
                const errorMsgs = modal.querySelectorAll('.feedback-error');
                errorMsgs.forEach(msg => {
                    if (msg && msg.style) {
                        msg.style.display = 'none';
                    }
                });
            }
        };

        // --- Event Listeners for Modals ---
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;
                openModal(modalId);
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.open').forEach(modal => {
                    closeModal(modal);
                });
            }
        });

        // --- Add Period Form ---
        const addPeriodForm = document.getElementById('addPeriodForm');
        if (addPeriodForm) {
            addPeriodForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('action', 'addPeriod');

                fetch('../api/attendance.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = `attendance.php?class_subject_id=${formData.get('class_subject_id')}&period_id=${data.period_id}`;
                        } else {
                            // Show error message
                            createAlert(data.message || 'Napaka pri dodajanju ure.', 'error', '#addPeriodForm .modal-body');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        createAlert('Prišlo je do napake pri komunikaciji s strežnikom.', 'error', '#addPeriodForm .modal-body');
                    });
            });
        }

        // --- Attendance Management ---
        document.getElementById('attendanceForm');
        const attendanceStatusMessage = document.getElementById('attendanceStatusMessage');

        // Mark attendance status
        const attendanceButtons = document.querySelectorAll('.attendance-btn');
        if (attendanceButtons.length > 0) {
            attendanceButtons.forEach(btn => {
                btn.addEventListener('click', function () {
                    const enrollId = this.dataset.enrollId;
                    const status = this.dataset.status;
                    const row = this.closest('tr');

                    // Update UI immediately
                    row.querySelectorAll('.attendance-btn').forEach(b => {
                        b.classList.remove('btn-accent');
                    });
                    this.classList.add('btn-accent');

                    const statusDisplay = row.querySelector('.attendance-status');
                    statusDisplay.className = `attendance-status status-${status.toLowerCase()}`;
                    statusDisplay.dataset.status = status;
                    statusDisplay.textContent = getStatusLabel(status);

                    // Save change to server
                    saveAttendanceRecord(enrollId, status);
                });
            });
        }

        // Mark all present button
        const markAllPresentBtn = document.getElementById('markAllPresent');
        if (markAllPresentBtn) {
            markAllPresentBtn.addEventListener('click', function () {
                const enrollIds = [];

                document.querySelectorAll('.attendance-btn[data-status="P"]').forEach(btn => {
                    const enrollId = btn.dataset.enrollId;

                    // Only select unmarked or different status
                    const row = btn.closest('tr');
                    const currentStatus = row.querySelector('.attendance-status').dataset.status;

                    if (currentStatus !== 'P') {
                        enrollIds.push(enrollId);

                        // Update UI
                        row.querySelectorAll('.attendance-btn').forEach(b => {
                            b.classList.remove('btn-accent');
                            if (b.dataset.status === 'P') {
                                b.classList.add('btn-accent');
                            }
                        });

                        const statusDisplay = row.querySelector('.attendance-status');
                        statusDisplay.className = 'attendance-status status-p';
                        statusDisplay.dataset.status = 'P';
                        statusDisplay.textContent = getStatusLabel('P');
                    }
                });

                // Save all changes
                if (enrollIds.length > 0) {
                    saveBulkAttendanceRecords(enrollIds, 'P');
                } else {
                    createAlert('Vsi učenci so že označeni kot prisotni.', 'info', '#attendanceStatusMessage');
                }
            });
        }

        // Save all attendance button
        const saveAttendanceBtn = document.getElementById('saveAttendance');
        if (saveAttendanceBtn) {
            saveAttendanceBtn.addEventListener('click', function () {
                createAlert('Vse spremembe so bile shranjene.', 'success', '#attendanceStatusMessage');
            });
        }

        // Helper functions
        function getStatusLabel(status) {
            switch (status) {
                case 'P':
                    return 'Prisoten';
                case 'A':
                    return 'Odsoten';
                case 'L':
                    return 'Zamudil';
                default:
                    return status;
            }
        }

        function saveAttendanceRecord(enrollId, status) {
            const formData = new FormData();
            formData.append('action', 'saveAttendance');
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
            formData.append('enroll_id', enrollId);
            formData.append('period_id', document.querySelector('input[name="period_id"]').value);
            formData.append('status', status);

            fetch('../api/attendance.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        createAlert(data.message || 'Napaka pri shranjevanju prisotnosti.', 'error', '#attendanceStatusMessage');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    createAlert('Prišlo je do napake pri komunikaciji s strežnikom.', 'error', '#attendanceStatusMessage');
                });
        }

        function saveBulkAttendanceRecords(enrollIds, status) {
            let saved = 0;
            let errors = 0;

            enrollIds.forEach(enrollId => {
                const formData = new FormData();
                formData.append('action', 'saveAttendance');
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                formData.append('enroll_id', enrollId);
                formData.append('period_id', document.querySelector('input[name="period_id"]').value);
                formData.append('status', status);

                fetch('../api/attendance.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            saved++;
                        } else {
                            errors++;
                        }

                        if (saved + errors === enrollIds.length) {
                            if (errors === 0) {
                                createAlert(`Uspešno posodobljeno ${saved} zapisov prisotnosti.`, 'success', '#attendanceStatusMessage');
                            } else {
                                createAlert(`Uspešno posodobljeno ${saved} zapisov, napake pri ${errors} zapisih.`, 'warning', '#attendanceStatusMessage');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        errors++;

                        if (saved + errors === enrollIds.length) {
                            createAlert(`Uspešno posodobljeno ${saved} zapisov, napake pri ${errors} zapisih.`, 'warning', '#attendanceStatusMessage');
                        }
                    });
            });
        }

        function createAlert(message, type = 'info', container = '#attendanceStatusMessage', autoRemove = true, duration = 5000) {
            const alertElement = document.createElement('div');
            alertElement.className = `alert status-${type} card-entrance`;

            const contentElement = document.createElement('div');
            contentElement.className = 'alert-content';
            contentElement.innerHTML = message;

            alertElement.appendChild(contentElement);

            const containerElement = document.querySelector(container);
            containerElement.innerHTML = '';
            containerElement.appendChild(alertElement);

            if (autoRemove) {
                setTimeout(() => {
                    alertElement.classList.add('closing');
                    setTimeout(() => {
                        if (containerElement.contains(alertElement)) {
                            containerElement.removeChild(alertElement);
                        }
                    }, 300);
                }, duration);
            }

            return alertElement;
        }
    });
</script>

<?php
include_once '../includes/footer.php';
?>
