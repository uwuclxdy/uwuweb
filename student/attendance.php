<?php
/**
 * Student Attendance View
 * File path: /student/attendance.php
 *
 * Allows students to view their own attendance records in read-only mode
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'student_functions.php';

// Ensure only students can access this page
requireRole(3); // ROLE_STUDENT

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) die('Napaka: Študentski račun ni bil najden.');

// Get attendance data
$attendance = getStudentAttendance($studentId);
$attendanceStats = calculateAttendanceStats($attendance);

// Include header
require_once '../includes/header.php';
?>

<div class="container section">
    <?php renderHeaderCard(
        'Moja prisotnost',
        'Ogled evidenc prisotnosti za vse predmete',
        'student'
    ); ?>

    <!-- Attendance summary statistics card -->
    <div class="row">
        <div class="col col-md-4">
            <div class="card mb-lg">
                <div class="card__title">
                    <h3>Povzetek prisotnosti</h3>
                </div>
                <div class="card__content">
                    <div class="d-flex flex-column gap-sm">
                        <?php if (!empty($attendanceStats)): ?>
                            <div class="d-flex justify-between">
                                <span>Prisoten:</span>
                                <span class="attendance-status status-present"><?= $attendanceStats['present_percent'] ?>%</span>
                            </div>
                            <div class="d-flex justify-between">
                                <span>Odsoten:</span>
                                <span class="attendance-status status-absent"><?= $attendanceStats['absent_percent'] ?>%</span>
                            </div>
                            <div class="d-flex justify-between">
                                <span>Zamuda:</span>
                                <span class="attendance-status status-late"><?= $attendanceStats['late_percent'] ?>%</span>
                            </div>
                            <div class="d-flex justify-between">
                                <span>Opravičeno:</span>
                                <span class="badge badge-secondary"><?= $attendanceStats['justified_percent'] ?? '0' ?>%</span>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary">Ni podatkov o prisotnosti.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col col-md-8">
            <!-- Attendance records table -->
            <div class="card mb-lg">
                <div class="card__title">
                    <h3>Evidence prisotnosti</h3>
                </div>
                <div class="card__content">
                    <?php if (!empty($attendance)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Datum</th>
                                    <th>Ura</th>
                                    <th>Predmet</th>
                                    <th>Status</th>
                                    <th>Dejanja</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($attendance as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(formatDateDisplay($record['date'])) ?></td>
                                        <td><?= htmlspecialchars($record['period_label']) ?></td>
                                        <td><?= htmlspecialchars($record['subject_name']) ?></td>
                                        <td>
                                            <?php
                                            $statusLabel = getAttendanceStatusLabel($record['status']);
                                            $statusClass = '';
                                            if ($record['status'] === 'P') $statusClass = 'status-present'; elseif ($record['status'] === 'A') $statusClass = 'status-absent';
                                            elseif ($record['status'] === 'L') $statusClass = 'status-late';
                                            ?>
                                            <span class="attendance-status <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($record['status'] !== 'P'): ?>
                                                <?php if (!isset($record['approved'])): ?>
                                                    <a href="justification.php?att_id=<?= $record['att_id'] ?>"
                                                       class="btn btn-primary btn-sm">Opraviči</a>
                                                <?php elseif ($record['approved'] === 0): ?>
                                                    <a href="justification.php?att_id=<?= $record['att_id'] ?>"
                                                       class="btn btn-secondary btn-sm">Razlog zavrnitve</a>
                                                <?php elseif ($record['approved'] === 1): ?>
                                                    <span class="text-disabled">Opravičeno</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span>—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert status-info">
                            <div class="alert-content">Ni zabeleženih prisotnosti.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include page footer
include '../includes/footer.php';
?>
