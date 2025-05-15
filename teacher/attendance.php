<?php
/**
 * Teacher Attendance Form
 *
 * Provides interface for teachers to manage student attendance
 * File path: /teacher/attendance.php
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php'; // Contains calculateAttendanceStats, getAttendanceStatusLabel, formatDateDisplay, addPeriod, getClassPeriods, getPeriodAttendance, saveAttendance, getClassStudents
require_once '../includes/header.php';
require_once 'teacher_functions.php'; // Will define isHomeroomTeacher, getAllAttendanceForClass

requireRole(ROLE_TEACHER);

$teacherId = getUserId(); // Assuming getUserId() is appropriate here, or getTeacherId() if it specifically maps user_id to teacher_id
$actualTeacherId = getTeacherId($teacherId); // Ensure we have the teacher_id from the teachers table

if (!$actualTeacherId) {
    // Fallback or error if not a teacher, though requireRole should handle this.
    // This is more about getting the ID from the 'teachers' table.
    // If getTeacherId() can take no arguments and use session, that's fine.
    // For now, assuming getTeacherId() (with no args or from session) gives the correct `teachers.teacher_id`
    $actualTeacherId = getTeacherId(); // Corrected: getTeacherId() should fetch current user's teacher ID
    if (!$actualTeacherId) {
        echo generateAlert('Učiteljev račun ni bil najden ali pa niste prijavljeni kot učitelj.', 'error');
        include_once '../includes/footer.php';
        exit;
    }
}


$pdo = safeGetDBConnection('teacher/attendance.php');
$csrfToken = generateCSRFToken();

$selectedClassSubjectId = isset($_GET['class_subject_id']) ? (int)$_GET['class_subject_id'] : null;
$selectedPeriodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : null;

$teacherClassesSubjects = getTeacherClasses($actualTeacherId);
$currentSubjectName = '';
$currentClassId = null;
$currentClassName = '';

if ($selectedClassSubjectId && !empty($teacherClassesSubjects)) foreach ($teacherClassesSubjects as $cs) if ($cs['class_subject_id'] == $selectedClassSubjectId) {
    $currentSubjectName = $cs['subject_name'];
    $currentClassId = $cs['class_id'];
    $currentClassName = $cs['class_title']; // Assuming class_title from getTeacherClasses
    break;
}

// Handle Add Period
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_period'])) if (verifyCSRFToken($_POST['csrf_token'])) {
    $periodDate = $_POST['period_date'];
    $periodLabel = $_POST['period_label'];
    $classSubjectIdForPeriod = (int)$_POST['class_subject_id_for_modal'];

    if (!validateDate($periodDate)) echo generateAlert('Neveljaven format datuma.', 'error'); elseif (empty($periodLabel)) echo generateAlert('Oznaka ure ne sme biti prazna.', 'error');
    elseif ($classSubjectIdForPeriod !== $selectedClassSubjectId || !$selectedClassSubjectId) echo generateAlert('Neveljavna izbira predmeta/razreda za dodajanje ure.', 'error');
    else {
        $newPeriodId = addPeriod($classSubjectIdForPeriod, $periodDate, $periodLabel);
        if ($newPeriodId) echo generateAlert('Ura uspešno dodana.', 'success'); else echo generateAlert('Napaka pri dodajanju ure.', 'error');
    }
} else echo generateAlert('Neveljaven CSRF žeton.', 'error');

// Handle Save Attendance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) if (verifyCSRFToken($_POST['csrf_token'])) {
    $attendanceData = $_POST['attendance'] ?? [];
    $periodIdToSave = (int)$_POST['period_id_for_attendance'];

    if ($periodIdToSave !== $selectedPeriodId || !$selectedPeriodId) echo generateAlert('Neveljavna izbira ure za shranjevanje prisotnosti.', 'error'); else {
        $successCount = 0;
        $errorCount = 0;
        foreach ($attendanceData as $enrollId => $status) if (in_array($status, ['P', 'A', 'L'])) if (saveAttendance((int)$enrollId, $periodIdToSave, $status)) $successCount++; else $errorCount++;
        if ($errorCount > 0) echo generateAlert("Prisotnost delno shranjena. Uspešno: $successCount, Napake: $errorCount.", 'warning'); else echo generateAlert('Prisotnost uspešno shranjena.', 'success');
        // Data will be re-fetched on page load, showing updated statuses
    }
} else echo generateAlert('Neveljaven CSRF žeton.', 'error');

$periods = [];
if ($selectedClassSubjectId) $periods = getClassPeriods($selectedClassSubjectId);

$studentsForAttendance = [];
$periodAttendance = [];
if ($selectedClassSubjectId && $selectedPeriodId && $currentClassId) {
    $studentsForAttendance = getClassStudents($currentClassId); // Assumes this returns enroll_id
    $rawPeriodAttendance = getPeriodAttendance($selectedPeriodId);
    foreach ($rawPeriodAttendance as $att) $periodAttendance[$att['enroll_id']] = $att['status'];
}

$isHomeroom = false;
$classStudentsForStats = [];
$allClassAttendanceRecords = [];
if ($selectedClassSubjectId && $currentClassId) {
    $isHomeroom = isHomeroomTeacher($actualTeacherId, $currentClassId);
    if ($isHomeroom) {
        $classStudentsForStats = getClassStudents($currentClassId); // Re-fetch or use $studentsForAttendance if period also selected
        $allClassAttendanceRecords = getAllAttendanceForClass($currentClassId);
    }
}

?>
<div class="container">
    <header class="page-header">
        <h1 class="page-title">Evidenca Prisotnosti</h1>
        <?php renderHeaderCard('Evidenca Prisotnosti', 'Upravljajte prisotnost študentov za vaše predmete.', getRoleName(getUserRole())); ?>
    </header>

    <div class="alert-container">
        <?php
        // Placeholder for dynamic alerts via JS or PHP generated ones above
        if (isset($_SESSION['alert_message'])) {
            echo generateAlert($_SESSION['alert_message']['text'], $_SESSION['alert_message']['type']);
            unset($_SESSION['alert_message']);
        }
        ?>
    </div>


    <section class="content-section">
        <form method="GET" action="attendance.php" class="mb-lg">
            <div class="row">
                <div class="col col-md-6">
                    <div class="form-group">
                        <label for="class_subject_id" class="form-label">Izberite Razred in Predmet:</label>
                        <select name="class_subject_id" id="class_subject_id" class="form-select"
                                onchange="this.form.submit()">
                            <option value="">-- Izberite --</option>
                            <?php foreach ($teacherClassesSubjects as $cs): ?>
                                <option value="<?= $cs['class_subject_id'] ?>" <?= $selectedClassSubjectId == $cs['class_subject_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cs['class_title'] . ' - ' . $cs['subject_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php if ($selectedClassSubjectId && !empty($periods)): ?>
                    <div class="col col-md-6">
                        <div class="form-group">
                            <label for="period_id" class="form-label">Izberite Uro:</label>
                            <select name="period_id" id="period_id" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Izberite --</option>
                                <?php foreach ($periods as $period): ?>
                                    <option value="<?= $period['period_id'] ?>" <?= $selectedPeriodId == $period['period_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($period['period_label'] . ' (' . formatDateDisplay($period['period_date']) . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="class_subject_id" value="<?= $selectedClassSubjectId ?>">
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($selectedClassSubjectId): ?>
            <div class="mb-md">
                <button type="button" class="btn btn-primary" data-open-modal="addPeriodModal"
                        data-subject-name="<?= htmlspecialchars($currentSubjectName) ?>"
                        data-class-subject-id="<?= $selectedClassSubjectId ?>">
                    <span class="btn-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                                fill="currentColor" width="18" height="18"><path
                                    d="M11 11V5H13V11H19V13H13V19H11V13H5V11H11Z"></path></svg></span>
                    Dodaj Novo Uro
                </button>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($selectedClassSubjectId && $selectedPeriodId && !empty($studentsForAttendance)): ?>
        <section class="content-section card">
            <div class="card__title">
                <h3>Vnos Prisotnosti
                    za: <?php echo htmlspecialchars($currentClassName . ' - ' . $currentSubjectName); ?></h3>
                <p>
                    Ura: <?php echo htmlspecialchars((isset($periods)) ? (array_values(array_filter($periods, static fn($p) => $p['period_id'] == $selectedPeriodId))[0]['period_label'] ?? '') . ' (' . formatDateDisplay(array_values(array_filter($periods, static fn($p) => $p['period_id'] == $selectedPeriodId))[0]['period_date'] ?? '') . ')' : ''); ?></p>
            </div>
            <div class="card__content">
                <form method="POST"
                      action="attendance.php?class_subject_id=<?php echo $selectedClassSubjectId; ?>&period_id=<?php echo $selectedPeriodId; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="period_id_for_attendance" value="<?php echo $selectedPeriodId; ?>">
                    <input type="hidden" name="save_attendance" value="1">

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Študent</th>
                                <th class="text-center">Prisoten (P)</th>
                                <th class="text-center">Odsoten (A)</th>
                                <th class="text-center">Zamudil (L)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($studentsForAttendance as $student): ?>
                                <?php $currentStatus = $periodAttendance[$student['enroll_id']] ?? ''; ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td class="text-center">
                                        <input type="radio" id="status_p_<?php echo $student['enroll_id']; ?>"
                                               name="attendance[<?php echo $student['enroll_id']; ?>]"
                                               value="P" <?php echo $currentStatus == 'P' ? 'checked' : ''; ?> required>
                                        <label for="status_p_<?php echo $student['enroll_id']; ?>" class="sr-only">Prisoten</label>
                                    </td>
                                    <td class="text-center">
                                        <input type="radio" id="status_a_<?php echo $student['enroll_id']; ?>"
                                               name="attendance[<?php echo $student['enroll_id']; ?>]"
                                               value="A" <?php echo $currentStatus == 'A' ? 'checked' : ''; ?> required>
                                        <label for="status_a_<?php echo $student['enroll_id']; ?>" class="sr-only">Odsoten</label>
                                    </td>
                                    <td class="text-center">
                                        <input type="radio" id="status_l_<?php echo $student['enroll_id']; ?>"
                                               name="attendance[<?php echo $student['enroll_id']; ?>]"
                                               value="L" <?php echo $currentStatus == 'L' ? 'checked' : ''; ?> required>
                                        <label for="status_l_<?php echo $student['enroll_id']; ?>" class="sr-only">Zamudil</label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="form-group mt-md">
                        <button type="submit" class="btn btn-primary">Shrani Prisotnost</button>
                    </div>
                </form>
            </div>
        </section>        <?php elseif ($selectedClassSubjectId && empty($studentsForAttendance) && $selectedPeriodId): ?>
        <div class="alert status-info">Za ta razred ni vpisanih študentov ali pa za izbrano uro ni bilo mogoče pridobiti
            seznama.
        </div>
    <?php elseif ($selectedClassSubjectId && empty($periods)): ?>
        <div class="alert status-info">Za izbrani predmet še ni vpisanih ur. Prosimo, dodajte novo uro.</div>
    <?php endif; ?>


    <?php if ($isHomeroom && $selectedClassSubjectId && !empty($classStudentsForStats)): ?>
        <section class="content-section mt-lg">
            <h2 class="section-title">Statistika Prisotnosti za Razred: <?= htmlspecialchars($currentClassName) ?>
                (Celotno Leto)</h2>
            <p class="text-secondary mb-md">Prikazana je statistika prisotnosti za vse predmete za študente v tem
                razredu, kjer ste razrednik.</p>
            <div class="dashboard-grid">
                <?php foreach ($classStudentsForStats as $student): ?>
                    <div class="card shadow-sm mb-sm">
                        <div class="card__title p-md d-flex justify-between items-center">
                            <span><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span>
                            <?php
                            $studentAttendanceRecords = [];
                            foreach ($allClassAttendanceRecords as $record) if (isset($record['student_id']) && $record['student_id'] == $student['student_id']) $studentAttendanceRecords[] = $record;
                            $stats = calculateAttendanceStats($studentAttendanceRecords);
                            $presentPercent = $stats['present_percent'] ?? 0;
                            $rateClass = 'badge-info'; // Default
                            if ($stats['total'] > 0) $rateClass = $presentPercent >= 90 ? 'badge-success' :
                                ($presentPercent >= 75 ? 'badge-warning' : 'badge-error'); elseif (empty($studentAttendanceRecords)) $rateClass = 'badge-secondary';
                            ?>
                            <span class="badge <?= $rateClass ?>">
                            <?= $stats['total'] > 0 ? number_format($presentPercent, 1) . '%' : ($stats['total'] === 0 && empty($studentAttendanceRecords) ? 'N/A' : '0.0%') ?>
                        </span>
                        </div>
                        <div class="card__content p-md">
                            <div class="d-flex flex-column gap-sm">
                                <div class="d-flex justify-between">
                                    <span>Prisoten:</span>
                                    <span class="badge badge-success"><?= $stats['present_count'] ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-between">
                                    <span>Odsoten:</span>
                                    <span class="badge badge-error"><?= $stats['absent_count'] ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-between">
                                    <span>Zamuda:</span>
                                    <span class="badge badge-warning"><?= $stats['late_count'] ?? 0 ?></span>
                                </div>
                                <div class="d-flex justify-between mt-sm">
                                    <span>Skupaj zabeleženih ur:</span>
                                    <span class="font-bold"><?= $stats['total'] ?? 0 ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

</div>

<!-- Add Period Modal -->
<div class="modal" id="addPeriodModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="addPeriodModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="addPeriodModalTitle">Dodaj Novo Uro</h3>
        </div>
        <form id="addPeriodForm" method="POST"
              action="attendance.php?class_subject_id=<?= $selectedClassSubjectId ?><?= $selectedPeriodId ? '&period_id=' . $selectedPeriodId : '' ?>">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="add_period" value="1">
                <input type="hidden" name="class_subject_id_for_modal" id="class_subject_id_for_modal_input"
                       value="<?= $selectedClassSubjectId ?>">

                <div class="form-group">
                    <label class="form-label" for="period_date">Datum Ure:</label>
                    <input type="date" id="period_date" name="period_date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="period_label">Oznaka Ure (npr. Predavanje, Vaje):</label>
                    <input type="text" id="period_label" name="period_label" class="form-input" required>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-primary">Dodaj Uro</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Standard Modal JS (from modal-guidelines.md)
        const openModal = (modalId) => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                const firstFocusable = modal.querySelector('button, [href], input, select, textarea');
                if (firstFocusable) firstFocusable.focus();

                // Custom logic for addPeriodModal
                if (modalId === 'addPeriodModal') {
                    const dateInput = modal.querySelector('#period_date');
                    if (dateInput) {
                        dateInput.valueAsDate = new Date(); // Autofill today's date
                    }

                    const labelInput = modal.querySelector('#period_label');
                    const openButton = document.querySelector('[data-open-modal="addPeriodModal"]');
                    if (labelInput && openButton) {
                        labelInput.value = openButton.dataset.subjectName || 'Predmet';
                    }
                    const classSubjectIdInput = modal.querySelector('#class_subject_id_for_modal_input');
                    if (classSubjectIdInput && openButton && openButton.dataset.classSubjectId) {
                        classSubjectIdInput.value = openButton.dataset.classSubjectId;
                    }

                }
            }
        };

        const closeModal = (modal) => {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }
            if (modal) {
                modal.classList.remove('open');
                const form = modal.querySelector('form');
                if (form) form.reset();
                const errorMsgs = modal.querySelectorAll('.feedback-error');
                errorMsgs.forEach(msg => {
                    if (msg && msg.style) {
                        msg.style.display = 'none';
                    }
                });
            }
        };

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
                document.querySelectorAll('.modal.open').forEach(modal => closeModal(modal));
            }
        });

        // Auto-submit select forms for class/subject and period
        const classSubjectSelect = document.getElementById('class_subject_id');
        if (classSubjectSelect) {
            classSubjectSelect.addEventListener('change', function () {
                // Clear period_id when class_subject changes
                const periodSelectField = document.getElementById('period_id');
                if (periodSelectField) { // if it exists, remove it before submitting
                    // Create a temporary form to submit only class_subject_id
                    const tempForm = document.createElement('form');
                    tempForm.method = 'GET';
                    tempForm.action = 'attendance.php';
                    const csInput = document.createElement('input');
                    csInput.type = 'hidden';
                    csInput.name = 'class_subject_id';
                    csInput.value = this.value;
                    tempForm.appendChild(csInput);
                    document.body.appendChild(tempForm);
                    tempForm.submit();
                } else {
                    this.form.submit();
                }
            });
        }
        const periodSelect = document.getElementById('period_id');
        if (periodSelect) {
            periodSelect.addEventListener('change', function () {
                this.form.submit();
            });
        }
    });
</script>

<?php include_once '../includes/footer.php'; ?>
