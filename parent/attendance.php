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

// CSS styles are included in header.php

// Ensure only parents can access this page
requireRole(ROLE_PARENT);

// Get the parent ID of the logged-in user
$parentId = getParentId();
if (!$parentId) {
    die('Error: Parent account not found.');
}

// Database connection
$pdo = safeGetDBConnection('parent/attendance.php');

// Get parent ID for the current user
function getParentId() {
    $pdo = safeGetDBConnection('getParentId()', false);
    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = :user_id");
    $stmt->execute(['user_id' => getUserId()]);
    $result = $stmt->fetch();
    return $result ? $result['parent_id'] : null;
}

// Get students linked to a parent
function getParentStudents($parentId) {
    $pdo = safeGetDBConnection('getParentStudents()', false);
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT s.student_id, s.first_name, s.last_name, s.class_code
         FROM students s
         JOIN student_parent ps ON s.student_id = ps.student_id
         WHERE ps.parent_id = :parent_id
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute(['parent_id' => $parentId]);
    return $stmt->fetchAll();
}

// Get attendance records for a student
function getStudentAttendance($studentId) {
    $pdo = safeGetDBConnection('getStudentAttendance() in parent/attendance.php', false);
    if (!$pdo) {
        error_log("Database connection failed in getStudentAttendance()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification, 
            a.approved, 
            p.period_date, 
            p.period_label, 
            c.title as class_title,
            s.name as subject_name
         FROM attendance a
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         WHERE e.student_id = :student_id
         ORDER BY p.period_date DESC, p.period_label"
    );
    $stmt->execute(['student_id' => $studentId]);
    return $stmt->fetchAll();
}

// Convert attendance status code to readable label
function getAttendanceStatusLabel($status) {
    switch ($status) {
        case 'P':
            return 'Present';
        case 'A':
            return 'Absent';
        case 'L':
            return 'Late';
        case 'E':
            return 'Excused';
        default:
            return 'Unknown';
    }
}

// Get students linked to the parent
$students = getParentStudents($parentId);

// Selected student for viewing attendance
$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isset($students[0]['student_id']) ? $students[0]['student_id'] : 0);

// Get attendance data if student is selected
$attendanceRecords = $selectedStudentId ? getStudentAttendance($selectedStudentId) : [];

// Find the selected student's name
$selectedStudent = null;
foreach ($students as $student) {
    if ($student['student_id'] == $selectedStudentId) {
        $selectedStudent = $student;
        break;
    }
}

// Include header
include '../includes/header.php';
?>

    <div class="card mb-lg">
        <h1 class="mt-0 mb-md">Attendance Records</h1>
        <p class="text-secondary mt-0 mb-0">View your child's attendance history</p>
    </div>

<?php if (count($students) > 1): ?>
    <!-- Student Selection Form -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">Select Student</h2>

        <form method="GET" action="attendance.php" class="mb-0">
            <div class="form-group mb-0">
                <select name="student_id" class="form-input" onchange="this.form.submit()">
                    <?php foreach ($students as $student): ?>
                        <option value="<?= $student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                            (<?= htmlspecialchars($student['class_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php if ($selectedStudent): ?>
    <!-- Student Information Card -->
    <div class="card mb-lg">
        <div class="d-flex items-center gap-md">
            <div class="profile-student" style="width: 48px; height: 48px; border-radius: 50%; border: 3px solid; display: flex; align-items: center; justify-content: center; font-size: var(--font-size-lg); font-weight: var(--font-weight-bold);">
                <?= strtoupper(substr($selectedStudent['first_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="mt-0 mb-xs"><?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></h2>
                <p class="text-secondary mb-0">Class: <?= htmlspecialchars($selectedStudent['class_code']) ?></p>
            </div>
        </div>
    </div>

    <!-- Attendance Summary Card -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">Attendance Summary</h2>

        <?php if (empty($attendanceRecords)): ?>
            <div class="bg-tertiary p-md text-center rounded">
                <p class="text-secondary mb-0">No attendance records found for this student.</p>
            </div>
        <?php else: ?>
            <?php
            // Calculate attendance statistics
            $totalRecords = count($attendanceRecords);
            $present = 0;
            $absent = 0;
            $late = 0;
            $justified = 0;

            foreach ($attendanceRecords as $record) {
                switch ($record['status']) {
                    case 'P':
                        $present++;
                        break;
                    case 'A':
                        $absent++;
                        if ($record['approved'] == 1) {
                            $justified++;
                        }
                        break;
                    case 'L':
                        $late++;
                        break;
                }
            }

            $presentPercentage = $totalRecords > 0 ? ($present / $totalRecords) * 100 : 0;
            $absentPercentage = $totalRecords > 0 ? ($absent / $totalRecords) * 100 : 0;
            $latePercentage = $totalRecords > 0 ? ($late / $totalRecords) * 100 : 0;
            $justifiedPercentage = $absent > 0 ? ($justified / $absent) * 100 : 0;
            ?>

            <div class="d-grid gap-md mb-md" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                <!-- Present Stats -->
                <div class="card" style="background-color: rgba(0, 200, 83, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Present</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #00c853;">
                            <?= number_format($presentPercentage, 1) ?>%
                        </div>
                        <p class="text-secondary mb-0"><?= $present ?> periods</p>
                    </div>
                </div>

                <!-- Absent Stats -->
                <div class="card" style="background-color: rgba(244, 67, 54, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Absent</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #f44336;">
                            <?= number_format($absentPercentage, 1) ?>%
                        </div>
                        <p class="text-secondary mb-0"><?= $absent ?> periods</p>
                    </div>
                </div>

                <!-- Late Stats -->
                <div class="card" style="background-color: rgba(255, 152, 0, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Late</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #ff9800;">
                            <?= number_format($latePercentage, 1) ?>%
                        </div>
                        <p class="text-secondary mb-0"><?= $late ?> periods</p>
                    </div>
                </div>

                <!-- Justified Stats -->
                <div class="card" style="background-color: rgba(33, 150, 243, 0.1);">
                    <div class="text-center">
                        <h3 class="mt-0 mb-xs">Justified</h3>
                        <div style="font-size: var(--font-size-xxl); font-weight: var(--font-weight-bold); color: #2196f3;">
                            <?= number_format($justifiedPercentage, 1) ?>%
                        </div>
                        <p class="text-secondary mb-0">of absences justified</p>
                    </div>
                </div>
            </div>

            <!-- Attendance Trend Visualization -->
            <div class="bg-tertiary p-md rounded">
                <h3 class="mt-0 mb-md text-center">Monthly Attendance Trend</h3>

                <?php
                // Group attendance records by month
                $monthlyData = [];
                $currentYear = date('Y');

                // Initialize months
                for ($m = 1; $m <= 12; $m++) {
                    $monthName = date('M', mktime(0, 0, 0, $m, 1));
                    $monthlyData[$monthName] = [
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'total' => 0
                    ];
                }

                // Fill in actual data
                foreach ($attendanceRecords as $record) {
                    $month = date('M', strtotime($record['period_date']));

                    $monthlyData[$month]['total']++;

                    switch ($record['status']) {
                        case 'P':
                            $monthlyData[$month]['present']++;
                            break;
                        case 'A':
                            $monthlyData[$month]['absent']++;
                            break;
                        case 'L':
                            $monthlyData[$month]['late']++;
                            break;
                    }
                }

                // Filter out months with no data
                $monthlyData = array_filter($monthlyData, function($data) {
                    return $data['total'] > 0;
                });
                ?>

                <div style="height: 200px; display: flex; align-items: flex-end; gap: 4px;">
                    <?php foreach ($monthlyData as $month => $data): ?>
                        <?php
                        $totalHeight = 180; // Maximum bar height in pixels
                        $presentHeight = $data['total'] > 0 ? ($data['present'] / $data['total']) * $totalHeight : 0;
                        $absentHeight = $data['total'] > 0 ? ($data['absent'] / $data['total']) * $totalHeight : 0;
                        $lateHeight = $data['total'] > 0 ? ($data['late'] / $data['total']) * $totalHeight : 0;
                        ?>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                            <div style="width: 80%; display: flex; flex-direction: column-reverse; height: 180px;">
                                <?php if ($presentHeight > 0): ?>
                                    <div style="height: <?= $presentHeight ?>px; background-color: #00c853; border-radius: 2px 2px 0 0;"></div>
                                <?php endif; ?>

                                <?php if ($lateHeight > 0): ?>
                                    <div style="height: <?= $lateHeight ?>px; background-color: #ff9800;"></div>
                                <?php endif; ?>

                                <?php if ($absentHeight > 0): ?>
                                    <div style="height: <?= $absentHeight ?>px; background-color: #f44336; border-radius: 0 0 2px 2px;"></div>
                                <?php endif; ?>
                            </div>
                            <div class="mt-xs text-secondary" style="font-size: var(--font-size-xs);"><?= $month ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-center gap-md mt-md">
                    <div class="d-flex items-center gap-xs">
                        <div style="width: 12px; height: 12px; background-color: #00c853; border-radius: 2px;"></div>
                        <span class="text-secondary" style="font-size: var(--font-size-xs);">Present</span>
                    </div>
                    <div class="d-flex items-center gap-xs">
                        <div style="width: 12px; height: 12px; background-color: #ff9800; border-radius: 2px;"></div>
                        <span class="text-secondary" style="font-size: var(--font-size-xs);">Late</span>
                    </div>
                    <div class="d-flex items-center gap-xs">
                        <div style="width: 12px; height: 12px; background-color: #f44336; border-radius: 2px;"></div>
                        <span class="text-secondary" style="font-size: var(--font-size-xs);">Absent</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Attendance Records Table -->
    <div class="card">
        <h2 class="mt-0 mb-md">Detailed Attendance Records</h2>

        <?php if (empty($attendanceRecords)): ?>
            <div class="bg-tertiary p-lg text-center rounded">
                <p class="mb-sm">No attendance records found for this student.</p>
                <p class="text-secondary mb-0">Records will appear here once they are entered by teachers.</p>
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
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attendanceRecords as $record): ?>
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
                                    <?php if (!empty($record['justification'])): ?>
                                        <button class="btn btn-secondary btn-sm mt-xs view-justification-btn"
                                                data-justification="<?= htmlspecialchars($record['justification']) ?>"
                                                data-status="<?= $justificationStatus ?>"
                                                data-date="<?= $formattedDate ?>"
                                                data-subject="<?= htmlspecialchars($record['subject_name'] . ' - ' . $record['class_title']) ?>"
                                                data-badge-class="<?= $justificationClass ?>">
                                            View Details
                                        </button>
                                    <?php endif; ?>
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
<?php endif; ?>

    <!-- View Justification Modal -->
    <div class="modal" id="viewJustificationModal" style="display: none;">
        <div class="modal-overlay" id="viewJustificationModalOverlay"></div>
        <div class="modal-container">
            <div class="card">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0">Justification Details</h3>
                    <button type="button" class="btn-close" id="closeViewJustificationModal">&times;</button>
                </div>

                <div class="bg-tertiary p-md rounded mb-lg">
                    <div class="d-flex justify-between">
                        <div>
                            <p class="mb-xs"><strong>Date:</strong> <span id="viewAbsenceDate"></span></p>
                            <p class="mb-0"><strong>Class:</strong> <span id="viewAbsenceClass"></span></p>
                        </div>
                        <div>
                            <span class="attendance-status" id="justificationStatusBadge"></span>
                        </div>
                    </div>
                </div>

                <h4 class="mt-0 mb-sm">Justification</h4>
                <div class="bg-tertiary p-md rounded mb-lg">
                    <p class="mb-0" id="viewJustificationText"></p>
                </div>

                <div class="d-flex justify-end mt-lg">
                    <button type="button" class="btn" id="closeViewDetailsBtn">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // View Justification Modal
            const viewJustificationBtns = document.querySelectorAll('.view-justification-btn');
            const viewJustificationModal = document.getElementById('viewJustificationModal');
            const closeViewJustificationModal = document.getElementById('closeViewJustificationModal');
            const closeViewDetailsBtn = document.getElementById('closeViewDetailsBtn');
            const viewJustificationModalOverlay = document.getElementById('viewJustificationModalOverlay');
            const viewAbsenceDate = document.getElementById('viewAbsenceDate');
            const viewAbsenceClass = document.getElementById('viewAbsenceClass');
            const viewJustificationText = document.getElementById('viewJustificationText');
            const justificationStatusBadge = document.getElementById('justificationStatusBadge');

            viewJustificationBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const justification = this.getAttribute('data-justification');
                    const status = this.getAttribute('data-status');
                    const date = this.getAttribute('data-date');
                    const subject = this.getAttribute('data-subject');
                    const badgeClass = this.getAttribute('data-badge-class');

                    viewAbsenceDate.textContent = date;
                    viewAbsenceClass.textContent = subject;
                    viewJustificationText.textContent = justification;
                    justificationStatusBadge.textContent = status;
                    justificationStatusBadge.className = 'attendance-status ' + badgeClass;

                    viewJustificationModal.style.display = 'flex';
                });
            });

            if (closeViewJustificationModal) {
                closeViewJustificationModal.addEventListener('click', function() {
                    viewJustificationModal.style.display = 'none';
                });
            }

            if (closeViewDetailsBtn) {
                closeViewDetailsBtn.addEventListener('click', function() {
                    viewJustificationModal.style.display = 'none';
                });
            }

            if (viewJustificationModalOverlay) {
                viewJustificationModalOverlay.addEventListener('click', function() {
                    viewJustificationModal.style.display = 'none';
                });
            }
        });
    </script>

<?php
// Include page footer
include '../includes/footer.php';
?>
