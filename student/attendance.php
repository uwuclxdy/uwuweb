<?php
/**
 * Student Attendance View
 *
 * Allows students to view their own attendance records in read-only mode
 *
 * Functions:
 * - getStudentAttendance($studentId) - Gets attendance records for a student
 * - getStudentId() - Gets the student ID for the current user
 * - getAttendanceStatusLabel($status) - Converts attendance status code to readable label
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Ensure only students can access this page
requireRole(ROLE_STUDENT);

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) {
    die('Error: Student account not found.');
}

// Database connection
$pdo = safeGetDBConnection('student/attendance.php');

// Get current term
$currentTerm = getCurrentTerm();
$termId = $currentTerm ? $currentTerm['term_id'] : null;

// Get student attendance for the current term
function getStudentAttendance($studentId, $termId = null) {
    $pdo = safeGetDBConnection('getStudentAttendance() in student/attendance.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getStudentAttendance()");
        return [];
    }

    $query = "SELECT 
                a.att_id, 
                a.status, 
                a.justification, 
                a.approved, 
                p.period_id,
                p.period_date, 
                p.period_label, 
                c.class_id,
                c.title as class_title, 
                s.subject_id,
                s.name as subject_name,
                e.enroll_id
             FROM attendance a
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             JOIN periods p ON a.period_id = p.period_id
             JOIN classes c ON p.class_id = c.class_id
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN terms t ON c.term_id = t.term_id
             WHERE e.student_id = :student_id";

    if ($termId) {
        $query .= " AND c.term_id = :term_id";
    }

    $query .= " ORDER BY p.period_date DESC, p.period_label ASC";

    $stmt = $pdo->prepare($query);

    $params = ['student_id' => $studentId];
    if ($termId) {
        $params['term_id'] = $termId;
    }

    $stmt->execute($params);
    return $stmt->fetchAll();
}

// getAttendanceStatusLabel and calculateAttendanceStats functions moved to includes/functions.php

// Get available terms for filtering
function getAvailableTerms() {
    $pdo = safeGetDBConnection('getAvailableTerms() in student/attendance.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getAvailableTerms()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT DISTINCT t.term_id, t.name, t.start_date, t.end_date
         FROM terms t
         JOIN classes c ON t.term_id = c.term_id
         JOIN enrollments e ON c.class_id = e.class_id
         JOIN students s ON e.student_id = s.student_id
         WHERE s.user_id = :user_id
         ORDER BY t.start_date DESC"
    );

    $stmt->execute(['user_id' => getUserId()]);
    return $stmt->fetchAll();
}

// Process term filter
$selectedTermId = null;
if (isset($_GET['term_id']) && is_numeric($_GET['term_id'])) {
    $selectedTermId = (int)$_GET['term_id'];
} else {
    $selectedTermId = $termId;
}

// Get attendance data
$attendance = getStudentAttendance($studentId, $selectedTermId);
$attendanceStats = calculateAttendanceStats($attendance);
$availableTerms = getAvailableTerms();

// Include header
include '../includes/header.php';
?>

    <div class="card mb-lg">
        <h1 class="mt-0 mb-md">My Attendance</h1>
        <p class="text-secondary mt-0 mb-0">View your attendance records across all classes</p>
    </div>

    <!-- Term Filter Card -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">Select Term</h2>

        <form method="GET" action="attendance.php" class="mb-0">
            <div class="form-group mb-0">
                <select name="term_id" id="term_id" class="form-input" onchange="this.form.submit()">
                    <?php foreach ($availableTerms as $term): ?>
                        <option value="<?= $term['term_id'] ?>" <?= $selectedTermId == $term['term_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($term['name']) ?>
                            (<?= date('d.m.Y', strtotime($term['start_date'])) ?> -
                            <?= date('d.m.Y', strtotime($term['end_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Attendance Summary Statistics -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">Attendance Summary</h2>

        <?php if (empty($attendance)): ?>
            <div class="bg-tertiary p-md text-center rounded">
                <p class="text-secondary mb-0">No attendance records found for the selected term.</p>
            </div>
        <?php else: ?>
            <div class="d-grid gap-md mb-md" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                <!-- Present Stats -->
                <div class="card" style="background-color: rgba(0, 200, 83, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Present</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #00c853;">
                            <?= number_format($attendanceStats['present_percentage'], 1) ?>%
                        </div>
                        <p class="text-secondary mb-0"><?= $attendanceStats['present_count'] ?> periods</p>
                    </div>
                </div>

                <!-- Absent Stats -->
                <div class="card" style="background-color: rgba(244, 67, 54, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Absent</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #f44336;">
                            <?= number_format($attendanceStats['absent_percentage'], 1) ?>%
                        </div>
                        <p class="text-secondary mb-0"><?= $attendanceStats['absent_count'] ?> periods</p>
                    </div>
                </div>

                <!-- Late Stats -->
                <div class="card" style="background-color: rgba(255, 152, 0, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Late</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #ff9800;">
                            <?= number_format($attendanceStats['late_percentage'], 1) ?>%
                        </div>
                        <p class="text-secondary mb-0"><?= $attendanceStats['late_count'] ?> periods</p>
                    </div>
                </div>

                <!-- Justified Stats -->
                <div class="card" style="background-color: rgba(33, 150, 243, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Justified</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #2196f3;">
                            <?= number_format($attendanceStats['justified_percentage'], 1) ?>%
                        </div>
                        <p class="text-secondary mb-0">of absences justified</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Attendance Records -->
    <div class="card">
        <h2 class="mt-0 mb-md">Attendance Records</h2>

        <?php if (empty($attendance)): ?>
            <div class="bg-tertiary p-lg text-center rounded">
                <p class="mb-sm">No attendance records found for the selected term.</p>
                <p class="text-secondary mb-0">Records will appear here once they are entered by your teachers.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Period</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Justification</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attendance as $record): ?>
                        <?php
                        // Format date
                        $formattedDate = date('d.m.Y', strtotime($record['period_date']));

                        // Determine status display and styling
                        $statusDisplay = getAttendanceStatusLabel($record['status']);
                        $statusClass = '';

                        switch($record['status']) {
                            case 'P':
                                $statusClass = 'status-present';
                                break;
                            case 'A':
                                $statusClass = 'status-absent';
                                break;
                            case 'L':
                                $statusClass = 'status-late';
                                break;
                        }

                        // Determine justification status
                        $justificationStatus = 'N/A';
                        $justificationClass = '';

                        if ($record['status'] === 'A') {
                            if (!empty($record['justification'])) {
                                if ($record['approved'] === null) {
                                    $justificationStatus = 'Pending review';
                                    $justificationClass = 'status-warning';
                                } elseif ($record['approved'] == 1) {
                                    $justificationStatus = 'Approved';
                                    $justificationClass = 'status-success';
                                } else {
                                    $justificationStatus = 'Rejected';
                                    $justificationClass = 'status-error';
                                }
                            } else {
                                $justificationStatus = 'Not justified';
                                $justificationClass = 'status-error';
                            }
                        }

                        $needsJustification = ($record['status'] === 'A' && empty($record['justification']));
                        ?>

                        <tr>
                            <td><?= $formattedDate ?></td>
                            <td><?= htmlspecialchars($record['period_label']) ?></td>
                            <td>
                                <?= htmlspecialchars($record['subject_name']) ?> -
                                <?= htmlspecialchars($record['class_title']) ?>
                            </td>
                            <td>
                                <span class="attendance-status <?= $statusClass ?>">
                                    <?= $statusDisplay ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($record['status'] === 'A'): ?>
                                    <span class="attendance-status <?= $justificationClass ?>">
                                        <?= $justificationStatus ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($needsJustification): ?>
                                    <a href="justification.php?absence_id=<?= $record['att_id'] ?>" class="btn btn-primary btn-sm">
                                        Justify
                                    </a>
                                <?php elseif ($record['status'] === 'A' && !empty($record['justification'])): ?>
                                    <a href="justification.php?view_id=<?= $record['att_id'] ?>" class="btn btn-secondary btn-sm">
                                        View
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php
// Include page footer
include '../includes/footer.php';
?>
