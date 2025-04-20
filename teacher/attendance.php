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

// Add CSS link for this specific page
echo '<link rel="stylesheet" href="/uwuweb/assets/css/teacher-attendance.css">';

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId(getUserId());
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Database connection
$pdo = safeGetDBConnection('teacher/attendance.php');

// Get teacher ID based on user ID
function getTeacherId($userId) {
    $pdo = safeGetDBConnection('getTeacherId()', false);
    if (!$pdo) {
        error_log("Database connection failed in getTeacherId()");
        return null;
    }

    $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();
    return $result ? $result['teacher_id'] : null;
}

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
        "SELECT a.att_id, a.enroll_id, a.status, a.justification
         FROM attendance a
         WHERE a.period_id = :period_id"
    );
    $stmt->execute(['period_id' => $periodId]);

    // Index attendance by enrollment ID for easier access
    $attendance = [];
    foreach ($stmt->fetchAll() as $record) {
        $attendance[$record['enroll_id']] = [
            'att_id' => $record['att_id'],
            'status' => $record['status'],
            'justification' => $record['justification']
        ];
    }

    return $attendance;
}

// Add a new period to the class
function addPeriod($classId, $periodDate, $periodLabel) {
    $pdo = safeGetDBConnection('addPeriod()', false);
    if (!$pdo) {
        error_log("Database connection failed in addPeriod()");
        throw new PDOException("Failed to connect to database");
    }

    $stmt = $pdo->prepare(
        "INSERT INTO periods (class_id, period_date, period_label)
         VALUES (:class_id, :period_date, :period_label)"
    );

    $stmt->execute([
        'class_id' => $classId,
        'period_date' => $periodDate,
        'period_label' => $periodLabel
    ]);

    return $pdo->lastInsertId();
}

// Save attendance status for a student
function saveAttendance($enroll_id, $period_id, $status) {
    $pdo = safeGetDBConnection('saveAttendance()', false);
    if (!$pdo) {
        error_log("Database connection failed in saveAttendance()");
        throw new PDOException("Failed to connect to database");
    }

    // Check if record already exists
    $stmt = $pdo->prepare(
        "SELECT att_id FROM attendance 
         WHERE enroll_id = :enroll_id AND period_id = :period_id"
    );

    $stmt->execute([
        'enroll_id' => $enroll_id,
        'period_id' => $period_id
    ]);

    $record = $stmt->fetch();

    if ($record) {
        // Update existing record
        $stmt = $pdo->prepare(
            "UPDATE attendance
             SET status = :status
             WHERE enroll_id = :enroll_id AND period_id = :period_id"
        );

        $stmt->execute([
            'enroll_id' => $enroll_id,
            'period_id' => $period_id,
            'status' => $status
        ]);

        return $record['att_id'];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO attendance (enroll_id, period_id, status)
         VALUES (:enroll_id, :period_id, :status)"
    );

    $stmt->execute([
        'enroll_id' => $enroll_id,
        'period_id' => $period_id,
        'status' => $status
    ]);

    return $pdo->lastInsertId();
}

// Get selected class and period from request
$teacherId = getTeacherId(getUserId());
$classes = getTeacherClasses($teacherId);
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_id'] ?? null);

// Only proceed if teacher has classes
$hasClasses = !empty($classes);

// If a class is selected, get students and periods
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$periods = $selectedClassId ? getClassPeriods($selectedClassId) : [];

// Get selected period from request or use the most recent
$selectedPeriodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : ($periods[0]['period_id'] ?? null);

// Get attendance for selected period
$attendance = $selectedPeriodId ? getPeriodAttendance($selectedPeriodId) : [];

// Get selected class and period details for display
$selectedClass = null;
$selectedPeriod = null;

if ($selectedClassId) {
    foreach ($classes as $class) {
        if ($class['class_id'] == $selectedClassId) {
            $selectedClass = $class;
            break;
        }
    }
}

if ($selectedPeriodId && !empty($periods)) {
    foreach ($periods as $period) {
        if ($period['period_id'] == $selectedPeriodId) {
            $selectedPeriod = $period;
            break;
        }
    }
}

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new period
    if (isset($_POST['add_period']) && $selectedClassId) {
        $periodDate = $_POST['period_date'] ?? '';
        $periodLabel = $_POST['period_label'] ?? '';

        if (empty($periodDate) || empty($periodLabel)) {
            $message = 'Date and label are required for a new period.';
            $messageType = 'error';
        } else {
            try {
                $newPeriodId = addPeriod($selectedClassId, $periodDate, $periodLabel);
                $message = 'Period added successfully.';
                $messageType = 'success';

                // Redirect to the new period
                header("Location: attendance.php?class_id=$selectedClassId&period_id=$newPeriodId&success=1");
                exit;
            } catch (PDOException $e) {
                $message = 'Error adding period: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }

    // Save attendance
    if (isset($_POST['save_attendance']) && $selectedPeriodId) {
        try {
            foreach ($students as $student) {
                $enroll_id = $student['enroll_id'];
                $status = $_POST["status_$enroll_id"] ?? 'P'; // Default to Present

                saveAttendance($enroll_id, $selectedPeriodId, $status);
            }

            $message = 'Attendance saved successfully.';
            $messageType = 'success';

            // Refresh attendance data
            $attendance = getPeriodAttendance($selectedPeriodId);
        } catch (PDOException $e) {
            $message = 'Error saving attendance: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include page header
include '../includes/header.php';
?>

<div class="page-container">
    <h1 class="page-title">Teacher Attendance</h1>

    <?php if (!$hasClasses): ?>
        <div class="alert alert-error">
            You are not assigned to any classes. Please contact an administrator.
        </div>
    <?php else: ?>
        <!-- Class selector -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Select Class</h3>
            </div>
            <div class="card-body">
                <form method="get" action="/uwuweb/teacher/attendance.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="form-group">
                        <label for="class_id" class="form-label">Select Class:</label>
                        <select name="class_id" id="class_id" class="form-input" onchange="this.form.submit()">
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= htmlspecialchars($class['class_id']) ?>"
                                        <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars("{$class['subject_name']} - {$class['title']} ({$class['term_name']})") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selectedClassId): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><?= htmlspecialchars($selectedClass['subject_name']) ?></h2>
                    <h3 class="card-subtitle"><?= htmlspecialchars($selectedClass['title']) ?> - <?= htmlspecialchars($selectedClass['term_name']) ?></h3>
                </div>

                <div class="card-body">
                    <!-- Message display -->
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php elseif (isset($_GET['success'])): ?>
                        <div class="alert alert-success">
                            Period added successfully.
                        </div>
                    <?php endif; ?>

                    <!-- Period management -->
                    <div class="card stat-card">
                        <div class="card-header">
                            <h3 class="card-title">Period Management</h3>
                        </div>
                        <div class="card-body">
                            <div class="period-actions">
                                <button id="btn-add-period" class="btn btn-primary">Add New Period</button>

                                <?php if (!empty($periods)): ?>
                                    <form method="get" action="/uwuweb/teacher/attendance.php" class="period-selector">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($selectedClassId) ?>">
                                        <div class="form-group">
                                            <label for="period_id" class="form-label">Select Period:</label>
                                            <select name="period_id" id="period_id" class="form-input" onchange="this.form.submit()">
                                                <?php foreach ($periods as $period): ?>
                                                    <option value="<?= htmlspecialchars($period['period_id']) ?>"
                                                            <?= $selectedPeriodId == $period['period_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars(date('Y-m-d', strtotime($period['period_date'])) . " - " . $period['period_label']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Form -->
                    <?php if ($selectedClassId): ?>
                        <!-- Attendance Form -->
                        <?php if ($selectedPeriodId && !empty($students)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Attendance for <?= htmlspecialchars(date('Y-m-d', strtotime($selectedPeriod['period_date']))) ?> - <?= htmlspecialchars($selectedPeriod['period_label']) ?></h3>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="/uwuweb/teacher/attendance.php" class="attendance-form">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($selectedClassId) ?>">
                                        <input type="hidden" name="period_id" value="<?= htmlspecialchars($selectedPeriodId) ?>">

                                        <div class="table-wrapper">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Status</th>
                                                        <th>Justification</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($students as $student): ?>
                                                        <?php
                                                            $enrollId = $student['enroll_id'];
                                                            $currentStatus = isset($attendance[$enrollId]) ? $attendance[$enrollId]['status'] : 'P';
                                                            $justification = isset($attendance[$enrollId]) ? $attendance[$enrollId]['justification'] : '';
                                                        ?>
                                                        <tr>
                                                            <td class="student-name">
                                                                <?= htmlspecialchars("{$student['last_name']}, {$student['first_name']}") ?>
                                                                <span class="badge"><?= htmlspecialchars($student['class_code']) ?></span>
                                                            </td>
                                                            <td class="attendance-status">
                                                                <div class="status-toggles">
                                                                    <label class="radio-label">
                                                                        <input type="radio" name="status_<?= htmlspecialchars($enrollId) ?>"
                                                                            value="P" <?= $currentStatus === 'P' ? 'checked' : '' ?>>
                                                                        <span class="status-text status-present">Present</span>
                                                                    </label>
                                                                    <label class="radio-label">
                                                                        <input type="radio" name="status_<?= htmlspecialchars($enrollId) ?>"
                                                                            value="A" <?= $currentStatus === 'A' ? 'checked' : '' ?>>
                                                                        <span class="status-text status-absent">Absent</span>
                                                                    </label>
                                                                    <label class="radio-label">
                                                                        <input type="radio" name="status_<?= htmlspecialchars($enrollId) ?>"
                                                                            value="L" <?= $currentStatus === 'L' ? 'checked' : '' ?>>
                                                                        <span class="status-text status-late">Late</span>
                                                                    </label>
                                                                </div>
                                                            </td>
                                                            <td class="justification">
                                                                <?php if (!empty($justification)): ?>
                                                                    <div class="justification-text">
                                                                        <?= htmlspecialchars($justification) ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="no-justification text-secondary">No justification provided</div>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="form-actions">
                                            <button type="submit" name="save_attendance" class="btn btn-primary">Save Attendance</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php elseif ($selectedClassId && empty($periods)): ?>
                            <div class="alert alert-info">
                                No periods have been created for this class yet. Use the "Add New Period" button to create one.
                            </div>
                        <?php elseif ($selectedClassId && empty($students)): ?>
                            <div class="alert alert-info">
                                No students are enrolled in this class.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Add Period Form Modal -->
            <div id="add-period-form-container" class="modal" style="display: none;">
                <div class="modal-content card">
                    <div class="card-header">
                        <h3 class="card-title">Add New Period</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="/uwuweb/teacher/attendance.php">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="class_id" value="<?= htmlspecialchars($selectedClassId) ?>">

                            <div class="form-group">
                                <label for="period_date" class="form-label">Date:</label>
                                <input type="date" id="period_date" name="period_date" class="form-input" required
                                       value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group">
                                <label for="period_label" class="form-label">Label:</label>
                                <input type="text" id="period_label" name="period_label" class="form-input" required
                                       placeholder="e.g., Period 1, Morning Session, etc.">
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary" onclick="closePeriodForm()">Cancel</button>
                                <button type="submit" name="add_period" class="btn btn-primary">Add Period</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // Function to show the add period form
    function showPeriodForm() {
        document.getElementById('add-period-form-container').style.display = 'flex';
    }

    // Function to close the add period form
    function closePeriodForm() {
        document.getElementById('add-period-form-container').style.display = 'none';
    }

    // Event listener for the Add Period button
    document.getElementById('btn-add-period')?.addEventListener('click', showPeriodForm);
</script>

<?php
// Include page footer
include '../includes/footer.php';
?>
