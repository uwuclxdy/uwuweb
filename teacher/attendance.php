<?php
/**
 * Teacher Attendance Form
 *
 * Provides interface for teachers to manage student attendance
 * Supports tracking attendance for class periods
 *
 * Functions:
 * - getTeacherId($userId) - Retrieves teacher ID from user ID
 * - getTeacherClasses($teacherId) - Gets classes taught by a teacher
 * - getClassStudents($classId) - Gets students enrolled in a class
 * - getClassPeriods($classId) - Gets periods for a specific class
 * - getPeriodAttendance($periodId) - Gets attendance records for a period
 * - addPeriod($classId, $periodDate, $periodLabel) - Adds a new period to a class
 * - saveAttendance($enroll_id, $period_id, $status) - Saves attendance status for a student
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId();
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Database connection
$pdo = safeGetDBConnection('teacher/attendance.php');

// Get classes taught by teacher
function getTeacherClasses($teacherId) {
    $pdo = safeGetDBConnection('getTeacherClasses()', false);
    if (!$pdo) {
        error_log("Database connection failed in getTeacherClasses()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT c.class_id, c.title, s.name AS subject_name, t.name AS term_name
         FROM classes c
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN terms t ON c.term_id = t.term_id
         WHERE c.teacher_id = :teacher_id
         ORDER BY t.start_date DESC, s.name"
    );
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get students enrolled in a specific class
function getClassStudents($classId) {
    $pdo = safeGetDBConnection('getClassStudents()', false);
    if (!$pdo) {
        error_log("Database connection failed in getClassStudents()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT e.enroll_id, s.student_id, s.first_name, s.last_name, s.class_code
         FROM enrollments e
         JOIN students s ON e.student_id = s.student_id
         WHERE e.class_id = :class_id
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get periods for a specific class
function getClassPeriods($classId) {
    $pdo = safeGetDBConnection('getClassPeriods()', false);
    if (!$pdo) {
        error_log("Database connection failed in getClassPeriods()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT period_id, period_date, period_label 
         FROM periods 
         WHERE class_id = :class_id 
         ORDER BY period_date DESC, period_label"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get attendance records for a specific period
function getPeriodAttendance($periodId) {
    $pdo = safeGetDBConnection('getPeriodAttendance()', false);
    if (!$pdo) {
        error_log("Database connection failed in getPeriodAttendance()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT a.att_id, a.enroll_id, a.status
         FROM attendance a
         WHERE a.period_id = :period_id"
    );
    $stmt->execute(['period_id' => $periodId]);

    // Index by enrollment ID for easier lookup
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[$row['enroll_id']] = $row;
    }
    return $result;
}

// Add a new period to a class
function addPeriod($classId, $periodDate, $periodLabel) {
    $pdo = safeGetDBConnection('addPeriod()', false);
    if (!$pdo) {
        error_log("Database connection failed in addPeriod()");
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO periods (class_id, period_date, period_label)
             VALUES (:class_id, :period_date, :period_label)"
        );
        return $stmt->execute([
            'class_id' => $classId,
            'period_date' => $periodDate,
            'period_label' => $periodLabel
        ]);
    } catch (PDOException $e) {
        error_log("Error adding period: " . $e->getMessage());
        return false;
    }
}

// Save attendance status for a student
function saveAttendance($enroll_id, $period_id, $status) {
    $pdo = safeGetDBConnection('saveAttendance()', false);
    if (!$pdo) {
        error_log("Database connection failed in saveAttendance()");
        return false;
    }

    try {
        // Check if a record already exists
        $stmt = $pdo->prepare(
            "SELECT att_id FROM attendance 
             WHERE enroll_id = :enroll_id AND period_id = :period_id"
        );
        $stmt->execute([
            'enroll_id' => $enroll_id,
            'period_id' => $period_id
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing record
            $stmt = $pdo->prepare(
                "UPDATE attendance 
                 SET status = :status
                 WHERE att_id = :att_id"
            );
            return $stmt->execute([
                'att_id' => $existing['att_id'],
                'status' => $status
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare(
                "INSERT INTO attendance (enroll_id, period_id, status)
                 VALUES (:enroll_id, :period_id, :status)"
            );
            return $stmt->execute([
                'enroll_id' => $enroll_id,
                'period_id' => $period_id,
                'status' => $status
            ]);
        }
    } catch (PDOException $e) {
        error_log("Error saving attendance: " . $e->getMessage());
        return false;
    }
}

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        if (isset($_POST['add_period'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
            $periodDate = isset($_POST['period_date']) ? $_POST['period_date'] : '';
            $periodLabel = isset($_POST['period_label']) ? trim($_POST['period_label']) : '';

            if ($classId <= 0 || empty($periodDate) || empty($periodLabel)) {
                $message = 'Please fill out all period details.';
                $messageType = 'error';
            } else {
                if (addPeriod($classId, $periodDate, $periodLabel)) {
                    $message = 'New period added successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error adding period. Please try again.';
                    $messageType = 'error';
                }
            }
        } else if (isset($_POST['save_attendance'])) {
            $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
            $attendance = isset($_POST['attendance']) ? $_POST['attendance'] : [];

            if ($periodId <= 0 || empty($attendance)) {
                $message = 'Invalid attendance data.';
                $messageType = 'error';
            } else {
                $success = true;
                foreach ($attendance as $enrollId => $status) {
                    if (!saveAttendance($enrollId, $periodId, $status)) {
                        $success = false;
                    }
                }

                if ($success) {
                    $message = 'Attendance saved successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Some attendance records failed to save. Please try again.';
                    $messageType = 'warning';
                }
            }
        }
    }
}

// Get teacher's classes
$classes = getTeacherClasses($teacherId);

// Selected class and period
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (isset($classes[0]['class_id']) ? $classes[0]['class_id'] : 0);
$periods = $selectedClassId ? getClassPeriods($selectedClassId) : [];
$selectedPeriodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : (isset($periods[0]['period_id']) ? $periods[0]['period_id'] : 0);

// Get students and attendance data if a period is selected
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$attendanceData = $selectedPeriodId ? getPeriodAttendance($selectedPeriodId) : [];

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>
    <div class="card card-entrance mt-xl">
        <h1 class="mt-0 mb-md">Attendance Management</h1>
        <p class="text-secondary mt-0 mb-lg">Track and manage student attendance for your classes</p>

        <?php if (!empty($message)): ?>
            <div class="alert <?= strpos($message, 'successfully') !== false ? 'status-success' : ($messageType === 'warning' ? 'status-warning' : 'status-error') ?> mb-lg">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Class Selection Form -->
        <form method="GET" action="attendance.php" class="mb-lg">
            <div class="form-group mb-0">
                <label for="class_id" class="form-label">Select Class</label>
                <div class="d-flex gap-md">
                    <select id="class_id" name="class_id" class="form-input" style="flex: 1;">
                        <option value="">-- Select a class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>" <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['subject_name'] . ' - ' . $class['title']) ?> (<?= htmlspecialchars($class['term_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Select</button>
                </div>
            </div>
        </form>
    </div>

<?php if ($selectedClassId): ?>
    <!-- Period Selection and Management -->
    <div class="card mb-lg">
        <h2 class="mt-0 mb-md">Period Management</h2>

        <div class="d-flex flex-column flex-row@md gap-md mb-lg">
            <!-- Existing Periods -->
            <div style="flex: 1;">
                <h3 class="mt-0 mb-sm">Select Period</h3>
                <?php if (empty($periods)): ?>
                    <div class="bg-tertiary p-md text-center rounded">
                        <p class="text-secondary mb-0">No periods created yet</p>
                    </div>
                <?php else: ?>
                    <form method="GET" action="attendance.php">
                        <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                        <div class="form-group mb-0">
                            <select name="period_id" class="form-input">
                                <option value="">-- Select a period --</option>
                                <?php foreach ($periods as $period): ?>
                                    <option value="<?= $period['period_id'] ?>" <?= $selectedPeriodId == $period['period_id'] ? 'selected' : '' ?>>
                                        <?= date('d.m.Y', strtotime($period['period_date'])) ?> - <?= htmlspecialchars($period['period_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary mt-sm">View/Edit Attendance</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Add New Period -->
            <div style="flex: 1;">
                <h3 class="mt-0 mb-sm">Add New Period</h3>
                <form method="POST" action="attendance.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">

                    <div class="form-group">
                        <label for="period_date" class="form-label">Date</label>
                        <input type="date" id="period_date" name="period_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="period_label" class="form-label">Period Label</label>
                        <input type="text" id="period_label" name="period_label" class="form-input" placeholder="e.g. 1st Period, Morning, etc." required>
                    </div>

                    <button type="submit" name="add_period" class="btn btn-primary">Add Period</button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($selectedPeriodId): ?>
        <!-- Attendance Form -->
        <div class="card">
            <h2 class="mt-0 mb-md">Attendance Records</h2>

            <?php
            $periodInfo = null;
            foreach ($periods as $period) {
                if ($period['period_id'] == $selectedPeriodId) {
                    $periodInfo = $period;
                    break;
                }
            }
            ?>

            <?php if ($periodInfo): ?>
                <div class="bg-tertiary p-md rounded mb-lg">
                    <div class="d-flex justify-between items-center">
                        <div>
                            <p class="mb-xs"><strong>Date:</strong> <?= date('d.m.Y', strtotime($periodInfo['period_date'])) ?></p>
                            <p class="mb-0"><strong>Period:</strong> <?= htmlspecialchars($periodInfo['period_label']) ?></p>
                        </div>
                        <div>
                            <?php
                            $totalStudents = count($students);
                            $present = 0;
                            $absent = 0;
                            $late = 0;

                            foreach ($students as $student) {
                                $status = isset($attendanceData[$student['enroll_id']]) ?
                                    $attendanceData[$student['enroll_id']]['status'] : '';

                                if ($status === 'P') $present++;
                                else if ($status === 'A') $absent++;
                                else if ($status === 'L') $late++;
                            }
                            ?>
                            <p class="mb-xs"><span class="badge status-success">Present: <?= $present ?></span></p>
                            <p class="mb-xs"><span class="badge status-error">Absent: <?= $absent ?></span></p>
                            <p class="mb-0"><span class="badge status-warning">Late: <?= $late ?></span></p>
                        </div>
                    </div>
                </div>

                <?php if (empty($students)): ?>
                    <div class="bg-tertiary p-lg text-center rounded mb-lg">
                        <p class="text-secondary">No students enrolled in this class.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="attendance.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="period_id" value="<?= $selectedPeriodId ?>">

                        <div class="table-responsive mb-lg">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Status</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                    $status = isset($attendanceData[$student['enroll_id']]) ?
                                        $attendanceData[$student['enroll_id']]['status'] : 'P';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                        <td><?= htmlspecialchars($student['class_code']) ?></td>
                                        <td>
                                            <div class="d-flex gap-md">
                                                <label class="d-flex gap-xs items-center">
                                                    <input type="radio" name="attendance[<?= $student['enroll_id'] ?>]" value="P" <?= $status === 'P' ? 'checked' : '' ?>>
                                                    <span class="attendance-status status-present">Present</span>
                                                </label>

                                                <label class="d-flex gap-xs items-center">
                                                    <input type="radio" name="attendance[<?= $student['enroll_id'] ?>]" value="A" <?= $status === 'A' ? 'checked' : '' ?>>
                                                    <span class="attendance-status status-absent">Absent</span>
                                                </label>

                                                <label class="d-flex gap-xs items-center">
                                                    <input type="radio" name="attendance[<?= $student['enroll_id'] ?>]" value="L" <?= $status === 'L' ? 'checked' : '' ?>>
                                                    <span class="attendance-status status-late">Late</span>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-end">
                            <button type="submit" name="save_attendance" class="btn btn-primary">Save Attendance</button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-tertiary p-lg text-center rounded">
                    <p class="text-secondary">Period information not found.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit on class selection change
            const classSelect = document.getElementById('class_id');
            if (classSelect) {
                classSelect.addEventListener('change', function() {
                    if (this.value) {
                        this.form.submit();
                    }
                });
            }

            // Add period date validation
            const periodDateInput = document.getElementById('period_date');
            if (periodDateInput) {
                periodDateInput.max = new Date().toISOString().split('T')[0]; // Can't select future dates
            }
        });
    </script>

<?php
// Include page footer
include '../includes/footer.php';
?>
