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

// Get attendance status label
function getAttendanceStatusLabel($status) {
    $labels = [
        'P' => 'Present',
        'A' => 'Absent',
        'L' => 'Late'
    ];

    return $labels[$status] ?? 'Unknown';
}

// Calculate attendance statistics
function calculateAttendanceStats($attendance) {
    $total = count($attendance);
    $present = 0;
    $absent = 0;
    $late = 0;
    $justified = 0;

    foreach ($attendance as $record) {
        if ($record['status'] === 'P') {
            $present++;
        } elseif ($record['status'] === 'A') {
            $absent++;
            if (!empty($record['justification']) && $record['approved'] == 1) {
                $justified++;
            }
        } elseif ($record['status'] === 'L') {
            $late++;
        }
    }

    return [
        'total' => $total,
        'present' => $present,
        'absent' => $absent,
        'late' => $late,
        'justified' => $justified,
        'present_percent' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        'absent_percent' => $total > 0 ? round(($absent / $total) * 100, 1) : 0,
        'late_percent' => $total > 0 ? round(($late / $total) * 100, 1) : 0,
        'justified_percent' => $absent > 0 ? round(($justified / $absent) * 100, 1) : 0
    ];
}

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

<div class="attendance-container">
    <h1>My Attendance</h1>

    <div class="attendance-filter">
        <form method="get" action="" class="term-filter-form">
            <div class="form-group">
                <label for="term_id">Term:</label>
                <select id="term_id" name="term_id" onchange="this.form.submit()">
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

    <div class="attendance-summary">
        <div class="stat-card">
            <div class="stat-value"><?= $attendanceStats['present_percent'] ?>%</div>
            <div class="stat-label">Present</div>
            <div class="stat-count">(<?= $attendanceStats['present'] ?> periods)</div>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?= $attendanceStats['absent_percent'] ?>%</div>
            <div class="stat-label">Absent</div>
            <div class="stat-count">(<?= $attendanceStats['absent'] ?> periods)</div>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?= $attendanceStats['late_percent'] ?>%</div>
            <div class="stat-label">Late</div>
            <div class="stat-count">(<?= $attendanceStats['late'] ?> periods)</div>
        </div>

        <div class="stat-card">
            <div class="stat-value"><?= $attendanceStats['justified_percent'] ?>%</div>
            <div class="stat-label">Justified</div>
            <div class="stat-count">(<?= $attendanceStats['justified'] ?> of <?= $attendanceStats['absent'] ?> absences)</div>
        </div>
    </div>

    <?php if (empty($attendance)): ?>
        <div class="attendance-list empty">
            <p class="empty-message">No attendance records found for the selected term.</p>
        </div>
    <?php else: ?>
        <div class="attendance-list">
            <h2>Attendance Records</h2>
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
                            $statusClass = strtolower($record['status']);
                            $justificationStatus = '';

                            if ($record['status'] === 'A') {
                                if (!empty($record['justification'])) {
                                    if ($record['approved'] === null) {
                                        $justificationStatus = 'Pending review';
                                    } elseif ($record['approved'] == 1) {
                                        $justificationStatus = 'Approved';
                                    } else {
                                        $justificationStatus = 'Rejected';
                                    }
                                } else {
                                    $justificationStatus = 'Not justified';
                                }
                            } elseif ($record['status'] === 'P' || $record['status'] === 'L') {
                                $justificationStatus = 'N/A';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($formattedDate) ?></td>
                            <td><?= htmlspecialchars($record['period_label']) ?></td>
                            <td><?= htmlspecialchars($record['subject_name'] . ' - ' . $record['class_title']) ?></td>
                            <td class="status-<?= $statusClass ?>">
                                <?= htmlspecialchars(getAttendanceStatusLabel($record['status'])) ?>
                            </td>
                            <td class="justification-status <?= strtolower(str_replace(' ', '-', $justificationStatus)) ?>">
                                <?= htmlspecialchars($justificationStatus) ?>
                                <?php if ($record['status'] === 'A' && empty($record['justification'])): ?>
                                    <a href="justification.php" class="btn btn-small">Justify</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Attendance page specific styles */
    .attendance-container {
        padding: 1rem 0;
    }

    .attendance-filter {
        margin-bottom: 2rem;
    }

    .term-filter-form {
        max-width: 400px;
    }

    .attendance-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background-color: #f9f9f9;
        padding: 1.5rem;
        border-radius: 4px;
        text-align: center;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: bold;
    }

    .stat-label {
        font-size: 1rem;
        margin-top: 0.5rem;
    }

    .stat-count {
        font-size: 0.85rem;
        color: #666;
        margin-top: 0.25rem;
    }

    .attendance-list {
        margin: 2rem 0;
    }

    .attendance-list h2 {
        margin-bottom: 1rem;
    }

    .attendance-list.empty {
        padding: 2rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        text-align: center;
    }

    .empty-message {
        color: #666;
        font-style: italic;
    }

    .status-p {
        color: #28a745;
    }

    .status-a {
        color: #dc3545;
    }

    .status-l {
        color: #ffc107;
    }

    .justification-status.not-justified {
        color: #dc3545;
    }

    .justification-status.pending-review {
        color: #ffc107;
    }

    .justification-status.approved {
        color: #28a745;
    }

    .justification-status.rejected {
        color: #dc3545;
    }
</style>

<?php
// Include page footer
include '../includes/footer.php';
?>
