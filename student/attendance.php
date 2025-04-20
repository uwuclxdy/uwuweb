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

// Add CSS link for this specific page
echo '<link rel="stylesheet" href="/uwuweb/assets/css/student-attendance.css">';

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
?>

<div class="page-container">
    <h1 class="page-title">My Attendance</h1>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Term Selection</h3>
        </div>
        <div class="card-body">
            <form method="get" action="/uwuweb/student/attendance.php" class="term-filter-form">
                <div class="form-group">
                    <label for="term_id" class="form-label">Term:</label>
                    <select id="term_id" name="term_id" class="form-input" onchange="this.form.submit()">
                        <?php foreach ($availableTerms as $term): ?>
                            <option value="<?= (int)$term['term_id'] ?>" <?= $selectedTermId == $term['term_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($term['name']) ?>
                                (<?= date('d.m.Y', strtotime($term['start_date'])) ?> -
                                 <?= date('d.m.Y', strtotime($term['end_date'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="attendance-summary">
        <div class="stats-container">
        <div class="card-grid">
            <div class="card stat-card">
                <div class="card-header">
                    <h3 class="card-title">Present</h3>
                </div>
                <div class="card-body">
                    <div class="stat-value"><?= $attendanceStats['present_percent'] ?>%</div>
                    <div class="stat-count">(<?= $attendanceStats['present'] ?> periods)</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-header">
                    <h3 class="card-title">Absent</h3>
                </div>
                <div class="card-body">
                    <div class="stat-value"><?= $attendanceStats['absent_percent'] ?>%</div>
                    <div class="stat-count">(<?= $attendanceStats['absent'] ?> periods)</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-header">
                    <h3 class="card-title">Late</h3>
                </div>
                <div class="card-body">
                    <div class="stat-value"><?= $attendanceStats['late_percent'] ?>%</div>
                    <div class="stat-count">(<?= $attendanceStats['late'] ?> periods)</div>
                </div>
            </div>

            <div class="card stat-card">
                <div class="card-header">
                    <h3 class="card-title">Justified</h3>
                </div>
                <div class="card-body">
                    <div class="stat-value"><?= $attendanceStats['justified_percent'] ?>%</div>
                    <div class="stat-count">(<?= $attendanceStats['justified'] ?> of <?= $attendanceStats['absent'] ?> absences)</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($attendance)): ?>
        <div class="card">
            <div class="card-body">
                <p class="text-secondary">No attendance records found for the selected term.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Attendance Records</h2>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Period</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Justification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance as $record): ?>
                                <?php
                                    $formattedDate = date('d.m.Y', strtotime($record['period_date']));
                                    $statusClass = "";
                                    
                                    if ($record['status'] === 'P') {
                                        $statusClass = "badge badge-success";
                                    } elseif ($record['status'] === 'A') {
                                        $statusClass = "badge badge-error";
                                    } elseif ($record['status'] === 'L') {
                                        $statusClass = "badge badge-warning";
                                    }
                                    
                                    $justificationStatus = '';
                                    $justificationClass = '';

                                    if ($record['status'] === 'A') {
                                        if (!empty($record['justification'])) {
                                            if ($record['approved'] === null) {
                                                $justificationStatus = 'Pending review';
                                                $justificationClass = 'badge badge-warning';
                                            } elseif ($record['approved'] == 1) {
                                                $justificationStatus = 'Approved';
                                                $justificationClass = 'badge badge-success';
                                            } else {
                                                $justificationStatus = 'Rejected';
                                                $justificationClass = 'badge badge-error';
                                            }
                                        } else {
                                            $justificationStatus = 'Not justified';
                                            $justificationClass = 'badge badge-error';
                                        }
                                    } elseif ($record['status'] === 'P' || $record['status'] === 'L') {
                                        $justificationStatus = 'N/A';
                                        $justificationClass = 'text-secondary';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($formattedDate) ?></td>
                                    <td><?= htmlspecialchars($record['period_label']) ?></td>
                                    <td><?= htmlspecialchars($record['subject_name'] . ' - ' . $record['class_title']) ?></td>
                                    <td><span class="<?= $statusClass ?>"><?= htmlspecialchars(getAttendanceStatusLabel($record['status'])) ?></span></td>
                                    <td>
                                        <span class="<?= $justificationClass ?>"><?= htmlspecialchars($justificationStatus) ?></span>
                                        <?php if ($record['status'] === 'A' && empty($record['justification'])): ?>
                                            <a href="/uwuweb/student/justification.php?att_id=<?= $record['att_id'] ?>" class="btn btn-primary btn-sm">Justify</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Include page footer
include '../includes/footer.php';
?>
