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

<?php /* 
    [STUDENT ATTENDANCE PAGE PLACEHOLDER]
    Components:
    - Page title "My Attendance"
    
    - Term selection card:
      - Term dropdown with:
        - Term name and date range for each option
        - Auto-submit on change
    
    - Attendance summary statistics:
      - Statistics card grid with 4 cards:
        - Present: Percentage and count of periods
        - Absent: Percentage and count of periods
        - Late: Percentage and count of periods
        - Justified: Percentage of absences justified and count
    
    - Attendance records display:
      - Empty state message when no records available
      
      - When records exist:
        - Table with columns:
          - Date (formatted as dd.mm.yyyy)
          - Period
          - Subject
          - Status (color-coded badges: green for present, red for absent, yellow for late)
          - Justification status (Pending review, Approved, Rejected, Not justified, or N/A)
          - "Justify" button for unjustified absences
*/ ?>

<?php if (empty($attendance)): ?>
    <?php /* [EMPTY ATTENDANCE PLACEHOLDER] - "No attendance records found for the selected term." */ ?>
<?php else: ?>
    <?php /* [ATTENDANCE TABLE PLACEHOLDER] - Contains attendance records with colorful status indicators */ ?>
    
    <?php foreach ($attendance as $record): ?>
        <?php
        // Format date
        $formattedDate = date('d.m.Y', strtotime($record['period_date']));
        
        // Determine status display
        $statusDisplay = getAttendanceStatusLabel($record['status']);
        
        // Determine justification status
        $justificationStatus = 'N/A';
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
        }
        
        $needsJustification = ($record['status'] === 'A' && empty($record['justification']));
        ?>
        
        <?php /* [ATTENDANCE RECORD: 
            Date: <?= $formattedDate ?>, 
            Period: <?= htmlspecialchars($record['period_label']) ?>, 
            Subject: <?= htmlspecialchars($record['subject_name']) ?> - <?= htmlspecialchars($record['class_title']) ?>, 
            Status: <?= $statusDisplay ?>, 
            Justification: <?= $justificationStatus ?> 
            <?= $needsJustification ? '(Needs justification)' : '' ?>
        ] */ ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
