<?php
/**
 * Justification API
 * 
 * Handle AJAX requests for justification details
 * 
 * Functions:
 * - getJustificationById($absenceId) - Gets detailed information about a specific justification
 * - validateTeacherAccess($teacherId, $justification) - Validates if a teacher has access to a justification
 * - sendJsonResponse($data, $success = true) - Sends a JSON response
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Require AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    // Allow direct access for testing, but will check role
    // This makes it easier to debug without setting the header
}

// Require teacher role to access this API
requireRole(ROLE_TEACHER);

// Get teacher ID based on user ID
$teacherId = getTeacherId();

if (!$teacherId) {
    sendJsonResponse(['message' => 'Invalid teacher account.'], false);
    exit;
}

// Get detailed information about a specific justification
function getJustificationById($absenceId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification,
            a.justification_file,
            a.approved,
            a.reject_reason, 
            p.period_id,
            p.period_date, 
            p.period_label, 
            c.class_id,
            c.title as class_title, 
            s.subject_id,
            s.name as subject_name,
            e.enroll_id,
            st.student_id,
            st.first_name,
            st.last_name
         FROM attendance a
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN students st ON e.student_id = st.student_id
         WHERE a.att_id = :att_id"
    );
    
    $stmt->execute(['att_id' => $absenceId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Validate if a teacher has access to a justification
function validateTeacherAccess($teacherId, $justification) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT c.class_id
         FROM classes c
         WHERE c.teacher_id = :teacher_id
         AND c.class_id = :class_id"
    );
    
    $stmt->execute([
        'teacher_id' => $teacherId,
        'class_id' => $justification['class_id']
    ]);
    
    return $stmt->fetch() !== false;
}

// Send JSON response
function sendJsonResponse($data, $success = true) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data' => $data
    ]);
    exit;
}

// Process the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get') {
    $absenceId = isset($_GET['absence_id']) ? (int)$_GET['absence_id'] : 0;
    
    if ($absenceId <= 0) {
        sendJsonResponse(['message' => 'Invalid absence ID.'], false);
        exit;
    }
    
    $justification = getJustificationById($absenceId);
    
    if (!$justification) {
        sendJsonResponse(['message' => 'Justification not found.'], false);
        exit;
    }
    
    // Check if this teacher has access to the justification
    if (!validateTeacherAccess($teacherId, $justification)) {
        sendJsonResponse(['message' => 'You do not have permission to view this justification.'], false);
        exit;
    }
    
    // Return the justification data
    sendJsonResponse(['justification' => $justification]);
} else {
    sendJsonResponse(['message' => 'Invalid action.'], false);
}
?>