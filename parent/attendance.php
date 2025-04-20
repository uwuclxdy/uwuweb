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

// Add CSS link for this specific page
echo '<link rel="stylesheet" href="/uwuweb/assets/css/parent-attendance.css">';

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

// getAttendanceStatusLabel and calculateAttendanceStats functions moved to includes/functions.php

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

<div class="page-container">
    <h1 class="page-title">Student Attendance</h1>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filter Options</h3>
        </div>
        <div class="card-body">
            <form method="get" action="/uwuweb/parent/attendance.php" class="filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="student_id" class="form-label">Student:</label>
                        <select id="student_id" name="student_id" class="form-input" onchange="this.form.submit()">
                            <?php foreach ($students as $student): ?>
                                <option value="<?= (int)$student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                    (<?= htmlspecialchars($student['class_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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
                </div>
            </form>
        </div>
    </div>

    <?php if ($selectedStudent): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?>
                    <span class="badge"><?= htmlspecialchars($selectedStudent['class_code']) ?></span>
                </h2>
            </div>
        </div>

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
                    <p class="text-secondary">No attendance records found for the selected student and term.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Attendance Records</h3>
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
                                        
                                        // Determine status badge class
                                        $statusBadge = '';
                                        if ($record['status'] === 'P') {
                                            $statusBadge = 'badge badge-success';
                                        } elseif ($record['status'] === 'A') {
                                            $statusBadge = 'badge badge-error';
                                        } elseif ($record['status'] === 'L') {
                                            $statusBadge = 'badge badge-warning';
                                        }
                                        
                                        // Determine justification status and badge
                                        $justificationStatus = '';
                                        $justificationBadge = '';

                                        if ($record['status'] === 'A') {
                                            if (!empty($record['justification'])) {
                                                if ($record['approved'] === null) {
                                                    $justificationStatus = 'Pending review';
                                                    $justificationBadge = 'badge badge-warning';
                                                } elseif ($record['approved'] == 1) {
                                                    $justificationStatus = 'Approved';
                                                    $justificationBadge = 'badge badge-success';
                                                } else {
                                                    $justificationStatus = 'Rejected';
                                                    $justificationBadge = 'badge badge-error';
                                                }
                                            } else {
                                                $justificationStatus = 'Not justified';
                                                $justificationBadge = 'badge badge-error';
                                            }
                                        } elseif ($record['status'] === 'P' || $record['status'] === 'L') {
                                            $justificationStatus = 'N/A';
                                            $justificationBadge = 'text-secondary';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($formattedDate) ?></td>
                                        <td><?= htmlspecialchars($record['period_label']) ?></td>
                                        <td><?= htmlspecialchars($record['subject_name'] . ' - ' . $record['class_title']) ?></td>
                                        <td>
                                            <span class="<?= $statusBadge ?>">
                                                <?= htmlspecialchars(getAttendanceStatusLabel($record['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?= $justificationBadge ?>"><?= htmlspecialchars($justificationStatus) ?></span>
                                            <?php if ($record['status'] === 'A' && !empty($record['justification'])): ?>
                                                <button class="btn btn-secondary btn-sm view-justification"
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
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Justification View Modal -->
    <div id="view-justification-modal" class="modal" style="display: none;">
        <div class="modal-content card">
            <div class="card-header">
                <h3 class="card-title">Absence Justification</h3>
                <span class="close-modal">&times;</span>
            </div>
            <div class="card-body">
                <div id="justification-text" class="justification-text"></div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close-modal">Close</button>
                </div>
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

<?php
// Include page footer
include '../includes/footer.php';
?>
