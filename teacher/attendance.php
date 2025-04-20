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

// Include header
include '../includes/header.php';
?>

<?php /* 
    [TEACHER ATTENDANCE PAGE PLACEHOLDER]
    Components:
    - Page container with teacher attendance layout
    
    - Page title "Attendance Management"
    
    - Alert message display (when $message is not empty)
      - Different styling based on $messageType (success, error, warning)
    
    - Class selection form:
      - Dropdown list of classes taught by teacher
      - Label "Select Class"
      - Submit button to select class
    
    - If class is selected:
      - Period selection panel with:
        - Existing periods dropdown
        - Form to add new period with date and label inputs
      
      - If period is selected:
        - Attendance form with:
          - Table of students with attendance status options
            (Present/Absent/Late/Excused)
          - For each student row:
            - Student name
            - Radio buttons for attendance status
          - Save button for submitting attendance
*/ ?>

<?php
// Include footer
include '../includes/footer.php';
?>
