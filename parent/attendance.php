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
         JOIN parent_student ps ON s.student_id = ps.student_id
         WHERE ps.parent_id = :parent_id
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute(['parent_id' => $parentId]);
    return $stmt->fetchAll();
}

// Get attendance records for a student
function getStudentAttendance($studentId) {
    $pdo = safeGetDBConnection('getStudentAttendance()', false);
    if (!$pdo) {
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

<?php /* 
    [PARENT ATTENDANCE PAGE PLACEHOLDER]
    Components:
    - Page container with attendance view layout
    
    - Page title "Attendance Records"
    
    - If multiple students are linked to parent:
      - Student selection form with dropdown of students
    
    - Selected student information:
      - Student name display
      - Class code display
    
    - Attendance summary card with:
      - Statistics showing total absences, lates, etc.
      - Visual representation of attendance trends
    
    - Attendance records table with:
      - Headers: Date, Period, Subject, Status, Justification
      - For each attendance record:
        - Formatted date
        - Period label
        - Subject name and class
        - Status indicator with appropriate styling
        - Justification status display (if absent)
          - Approved/Rejected/Pending indicators
    
    - Responsive design for mobile viewing
*/ ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
