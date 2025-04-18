<?php
/**
 * Parent Attendance View
 *
 * Allows parents to view attendance records for their linked students in read-only mode
 *
 * Functions:
 * - getParentStudents($parentId) - Gets list of students linked to a parent
 * - getParentId() - Gets the parent ID for the current user
 * - getStudentAttendance($studentId) - Gets attendance records for a student
 * - getAttendanceStatusLabel($status) - Converts attendance status code to readable label
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Ensure only parents can access this page
requireRole(ROLE_PARENT);

// Get the parent ID of the logged-in user
$parentId = getParentId();
if (!$parentId) {
    die('Error: Parent account not found.');
}

// Database connection
$pdo = safeGetDBConnection('parent/attendance.php');

// Get students linked to this parent
function getParentStudents($parentId) {
    $pdo = safeGetDBConnection('getParentStudents() in parent/attendance.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getParentStudents()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT 
            s.student_id, 
            s.first_name, 
            s.last_name,
            s.class_code
         FROM students s
         JOIN student_parent sp ON s.student_id = sp.student_id
         WHERE sp.parent_id = :parent_id
         ORDER BY s.last_name, s.first_name"
    );

    $stmt->execute(['parent_id' => $parentId]);
    return $stmt->fetchAll();
}

// Get current term
$currentTerm = getCurrentTerm();
$termId = $currentTerm ? $currentTerm['term_id'] : null;

// Get student attendance for the current term
function getStudentAttendance($studentId, $termId = null) {
    $pdo = safeGetDBConnection('getStudentAttendance() in parent/attendance.php', false);
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
    $pdo = safeGetDBConnection('getAvailableTerms() in parent/attendance.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getAvailableTerms()");
        return [];
    }

    $stmt = $pdo->query(
        "SELECT DISTINCT t.term_id, t.name, t.start_date, t.end_date
         FROM terms t
         JOIN classes c ON t.term_id = c.term_id
         ORDER BY t.start_date DESC"
    );
    return $stmt->fetchAll();
}

// Process student and term filter
$selectedStudentId = null;
$selectedTermId = null;

$students = getParentStudents($parentId);

if (empty($students)) {
    header("Location: ../dashboard.php?error=no_students");
    exit;
}

if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
    $selectedStudentId = (int)$_GET['student_id'];

    // Verify the selected student belongs to this parent
    $validStudent = false;
    foreach ($students as $student) {
        if ($student['student_id'] == $selectedStudentId) {
            $validStudent = true;
            break;
        }
    }

    if (!$validStudent) {
        $selectedStudentId = $students[0]['student_id'];
    }
} else {
    $selectedStudentId = $students[0]['student_id'];
}

if (isset($_GET['term_id']) && is_numeric($_GET['term_id'])) {
    $selectedTermId = (int)$_GET['term_id'];
} else {
    $selectedTermId = $termId;
}

// Get attendance data for the selected student
$attendance = getStudentAttendance($selectedStudentId, $selectedTermId);
$attendanceStats = calculateAttendanceStats($attendance);
$availableTerms = getAvailableTerms();

// Get selected student info
$selectedStudent = null;
foreach ($students as $student) {
    if ($student['student_id'] == $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

// Include page header
include '../includes/header.php';
?>

<div class="attendance-container">
    <h1>Student Attendance</h1>

    <div class="attendance-filter">
        <form method="get" action="" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="student_id">Student:</label>
                    <select id="student_id" name="student_id" onchange="this.form.submit()">
                        <?php foreach ($students as $student): ?>
                            <option value="<?= (int)$student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                (<?= htmlspecialchars($student['class_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

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
            </div>
        </form>
    </div>

    <?php if ($selectedStudent): ?>
        <div class="student-info">
            <h2>
                <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?>
                <span class="class-code">(<?= htmlspecialchars($selectedStudent['class_code']) ?>)</span>
            </h2>
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
                <p class="empty-message">No attendance records found for the selected student and term.</p>
            </div>
        <?php else: ?>
            <div class="attendance-list">
                <h3>Attendance Records</h3>
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
                                    <?php if ($record['status'] === 'A' && !empty($record['justification'])): ?>
                                        <button class="btn btn-small view-justification"
                                                data-att-id="<?= (int)$record['att_id'] ?>"
                                                data-justification="<?= htmlspecialchars($record['justification']) ?>">
                                            View
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Justification View Modal -->
    <div id="view-justification-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Absence Justification</h3>

            <div class="justification-text" id="justification-text"></div>

            <div class="form-actions">
                <button type="button" class="btn close-modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Function to view justification details
    function setupJustificationView() {
        const buttons = document.querySelectorAll('.view-justification');
        const modal = document.getElementById('view-justification-modal');
        const justificationText = document.getElementById('justification-text');

        buttons.forEach(button => {
            button.addEventListener('click', function() {
                const text = this.getAttribute('data-justification');
                justificationText.textContent = text;
                modal.style.display = 'flex';
            });
        });
    }

    // Function to close modals
    function setupModalClosing() {
        const closeButtons = document.querySelectorAll('.close-modal');

        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });

        // Close modal when clicking outside it
        window.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    }

    // Initialize modal functionality when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        setupJustificationView();
        setupModalClosing();
    });
</script>

<style>
    /* Attendance page specific styles */
    .attendance-container {
        padding: 1rem 0;
    }

    .attendance-filter {
        margin-bottom: 2rem;
    }

    .filter-form {
        max-width: 600px;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .form-group {
        flex: 1;
        min-width: 250px;
    }

    .student-info {
        margin-bottom: 1.5rem;
    }

    .class-code {
        font-weight: normal;
        color: #666;
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

    .attendance-list h3 {
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

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: #fff;
        padding: 2rem;
        border-radius: 4px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }

    .close-modal {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
    }

    .close-modal:hover {
        color: #000;
    }

    .justification-text {
        padding: 1.5rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        margin: 1rem 0;
        white-space: pre-wrap;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 1rem;
    }
</style>

<?php
// Include page footer
include '../includes/footer.php';
?>
