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

// Require teacher role for access
requireRole(ROLE_TEACHER);

// Get teacher ID based on user ID
function getTeacherId($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();
    return $result ? $result['teacher_id'] : null;
}

// Get classes taught by teacher
function getTeacherClasses($teacherId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT c.class_id, c.title, s.name AS subject_name, t.name AS term_name
         FROM classes c
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN terms t ON c.term_id = t.term_id
         WHERE c.teacher_id = :teacher_id
         ORDER BY t.start_date DESC, s.name ASC"
    );
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get students enrolled in a specific class
function getClassStudents($classId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT e.enroll_id, s.student_id, s.first_name, s.last_name, s.class_code
         FROM enrollments e
         JOIN students s ON e.student_id = s.student_id
         WHERE e.class_id = :class_id
         ORDER BY s.last_name ASC, s.first_name ASC"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get periods for a specific class
function getClassPeriods($classId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT period_id, period_date, period_label 
         FROM periods 
         WHERE class_id = :class_id
         ORDER BY period_date DESC, period_label ASC"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get attendance records for a specific period
function getPeriodAttendance($periodId) {
    $pdo = getDBConnection();
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
    $pdo = getDBConnection();
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
    $pdo = getDBConnection();
    
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
    } else {
        // Insert new record
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

<div class="attendance-container">
    <h1>Teacher Attendance</h1>
    
    <?php if (!$hasClasses): ?>
        <div class="alert alert-error">
            You are not assigned to any classes. Please contact an administrator.
        </div>
    <?php else: ?>
        <!-- Class selector -->
        <div class="class-selector">
            <form method="get" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <label for="class_id">Select Class:</label>
                <select name="class_id" id="class_id" onchange="this.form.submit()">
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= htmlspecialchars($class['class_id']) ?>" 
                                <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars("{$class['subject_name']} - {$class['title']} ({$class['term_name']})") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <?php if ($selectedClassId): ?>
            <div class="class-details">
                <h2><?= htmlspecialchars($selectedClass['subject_name']) ?></h2>
                <h3><?= htmlspecialchars($selectedClass['title']) ?> - <?= htmlspecialchars($selectedClass['term_name']) ?></h3>
                
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
                <div class="period-management">
                    <h3>Period Management</h3>
                    
                    <div class="period-actions">
                        <button id="btn-add-period" class="btn">Add New Period</button>
                        
                        <?php if (!empty($periods)): ?>
                            <form method="get" action="" class="period-selector">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="class_id" value="<?= htmlspecialchars($selectedClassId) ?>">
                                <label for="period_id">Select Period:</label>
                                <select name="period_id" id="period_id" onchange="this.form.submit()">
                                    <?php foreach ($periods as $period): ?>
                                        <option value="<?= htmlspecialchars($period['period_id']) ?>" 
                                                <?= $selectedPeriodId == $period['period_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(date('Y-m-d', strtotime($period['period_date'])) . " - " . $period['period_label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Attendance Form -->
                <?php if ($selectedPeriodId && !empty($students)): ?>
                    <div class="attendance-form-container">
                        <h3>Attendance for <?= htmlspecialchars(date('Y-m-d', strtotime($selectedPeriod['period_date']))) ?> - <?= htmlspecialchars($selectedPeriod['period_label']) ?></h3>
                        
                        <form method="post" action="" class="attendance-form">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="class_id" value="<?= htmlspecialchars($selectedClassId) ?>">
                            <input type="hidden" name="period_id" value="<?= htmlspecialchars($selectedPeriodId) ?>">
                            
                            <table class="attendance-table">
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
                                                <span class="class-code">[<?= htmlspecialchars($student['class_code']) ?>]</span>
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
                                                    <div class="no-justification">No justification provided</div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="form-actions">
                                <button type="submit" name="save_attendance" class="btn">Save Attendance</button>
                            </div>
                        </form>
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
            </div>
            
            <!-- Add Period Form Modal -->
            <div id="add-period-form-container" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>Add New Period</h3>
                    <form method="post" action="">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($selectedClassId) ?>">
                        
                        <div class="form-group">
                            <label for="period_date">Date:</label>
                            <input type="date" id="period_date" name="period_date" required 
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="period_label">Label:</label>
                            <input type="text" id="period_label" name="period_label" required 
                                   placeholder="e.g., Period 1, Morning Session, etc.">
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-cancel" onclick="closePeriodForm()">Cancel</button>
                            <button type="submit" name="add_period" class="btn">Add Period</button>
                        </div>
                    </form>
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

<style>
    /* Attendance specific styles */
    .attendance-container {
        padding: 1rem 0;
    }
    
    .class-selector,
    .period-selector {
        margin: 1rem 0;
    }
    
    .class-selector select,
    .period-selector select {
        padding: 0.5rem;
        width: 100%;
        max-width: 400px;
    }
    
    .class-details {
        margin-top: 2rem;
    }
    
    .period-management {
        margin: 2rem 0;
    }
    
    .period-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 1rem;
    }
    
    .attendance-form-container {
        margin-top: 2rem;
    }
    
    .attendance-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
    }
    
    .attendance-table th,
    .attendance-table td {
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        text-align: left;
    }
    
    .attendance-table th {
        background-color: var(--primary-color);
        color: var(--text-light);
    }
    
    .attendance-table .student-name {
        white-space: nowrap;
    }
    
    .attendance-table .class-code {
        font-size: 0.8rem;
        color: #666;
    }
    
    .status-toggles {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .radio-label {
        display: flex;
        align-items: center;
        cursor: pointer;
    }
    
    .radio-label input {
        margin-right: 0.5rem;
    }
    
    .status-text {
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.9rem;
    }
    
    .status-present {
        background-color: #dff0d8;
        color: #3c763d;
    }
    
    .status-absent {
        background-color: #f2dede;
        color: #a94442;
    }
    
    .status-late {
        background-color: #fcf8e3;
        color: #8a6d3b;
    }
    
    .justification-text {
        padding: 0.5rem;
        background-color: #f8f9fa;
        border-left: 3px solid var(--primary-color);
        font-style: italic;
    }
    
    .no-justification {
        color: #999;
        font-style: italic;
    }
    
    .form-actions {
        margin-top: 1rem;
        text-align: right;
    }
    
    /* Modal styles already defined in gradebook.php */
</style>

<?php
// Include page footer
include '../includes/footer.php';
?>