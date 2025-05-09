<?php
/**
 * Student Attendance View
 *
 * Allows students to view their own attendance records in read-only mode
 *
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'student_functions.php';

// Ensure only students can access this page
requireRole(ROLE_STUDENT);

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) die('Error: Student account not found.');

// Database connection
$pdo = safeGetDBConnection('student/attendance.php');

// Get attendance data
$attendance = getStudentAttendance($studentId);
$attendanceStats = calculateAttendanceStats($attendance);

?>

<!-- Page title and description card -->
<div class="card shadow mb-lg mt-lg">
    <div class="d-flex justify-between items-center">
        <div>
            <h2 class="mt-0 mb-xs">Moja prisotnost</h2>
            <p class="text-secondary mt-0 mb-0">Ogled evidenc prisotnosti za vse predmete</p>
        </div>
        <div class="role-badge role-student">Dijak</div>
    </div>
</div>

<!-- Attendance summary statistics card -->
<div class="row">
    <div class="col col-md-4">
        <div class="card shadow mb-lg">
            <h3 class="card__title">Povzetek prisotnosti</h3>
            <div class="card__content">
                <div class="d-flex flex-column gap-sm">
                    <?php if (!empty($attendanceStats)): ?>
                        <div class="d-flex justify-between">
                            <span>Prisoten:</span>
                            <span class="status-present attendance-status"><?= $attendanceStats['present_percent'] ?>%</span>
                        </div>
                        <div class="d-flex justify-between">
                            <span>Odsoten:</span>
                            <span class="status-absent attendance-status"><?= $attendanceStats['absent_percent'] ?>%</span>
                        </div>
                        <div class="d-flex justify-between">
                            <span>Zamuda:</span>
                            <span class="status-late attendance-status"><?= $attendanceStats['late_percent'] ?>%</span>
                        </div>
                        <div class="d-flex justify-between">
                            <span>Justified:</span>
                            <span class="badge badge-secondary">0%</span>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary">No attendance data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col col-md-8">
        <!-- Attendance records table -->
        <div class="card shadow mb-lg">
            <h3 class="card__title">Attendance Records</h3>
            <div class="card__content">
                <?php if (!empty($attendance)): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Period</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Justified</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($attendance as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['date']) ?></td>
                                    <td><?= htmlspecialchars($record['period']) ?></td>
                                    <td><?= htmlspecialchars($record['subject_name']) ?></td>
                                    <td>
                                        <?php if ($record['status'] === 'present'): ?>
                                            <span class="attendance-status status-present">Present</span>
                                        <?php elseif ($record['status'] === 'absent'): ?>
                                            <span class="attendance-status status-absent">Absent</span>
                                        <?php elseif ($record['status'] === 'late'): ?>
                                            <span class="attendance-status status-late">Late</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['is_justified']): ?>
                                            <span class="badge badge-success">Yes</span>
                                        <?php else: ?>
                                            <span class="badge badge-error">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['status'] !== 'present' && !$record['is_justified']): ?>
                                            <a href="/uwuweb/student/justification.php?id=<?= $record['attendance_id'] ?>"
                                               class="btn btn-primary btn-sm">Justify</a>
                                        <?php elseif ($record['is_justified']): ?>
                                            <span class="badge badge-secondary">Justified</span>
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
                        No attendance records found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include page footer
include '../includes/footer.php';
?>
