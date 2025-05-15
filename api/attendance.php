<?php
/**
 * Attendance API Endpoint
 *
 * File path: /api/attendance.php
 *
 * Handles CRUD operations for attendance data via AJAX requests.
 * Returns JSON responses for client-side processing.
 *
 * Endpoints:
 * - handleAddPeriodApi(): void - API handler for adding a new period
 * - handleGetPeriodAttendanceApi(): void - API handler for getting period attendance
 * - handleGetStudentAttendanceApi(): void - API handler for getting student attendance
 * - handleSaveAttendanceApi(): void - API handler for saving attendance record
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure the request is from a logged-in user
if (!isLoggedIn()) sendJsonErrorResponse('Unauthorized access', 401, 'attendance_api');

// Check for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) sendJsonErrorResponse('Invalid security token', 403, 'attendance_api');

    // Check if any data was submitted
    if (empty($_POST)) sendJsonErrorResponse('No data submitted', 400, 'attendance_api');

    // Handle different API actions
    if (isset($_POST['class_subject_id'], $_POST['period_date'], $_POST['period_label'])) handleAddPeriodApi(); elseif (isset($_POST['period_id'], $_POST['enroll_id'], $_POST['status'])) handleSaveAttendanceApi();
    elseif (isset($_POST['period_id']) && !isset($_POST['enroll_id'])) handleGetPeriodAttendanceApi();
    elseif (isset($_POST['student_id'], $_POST['start_date'], $_POST['end_date'])) handleGetStudentAttendanceApi();
    else sendJsonErrorResponse('Invalid action', 400, 'attendance_api');
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') if (isset($_GET['period_id'])) handleGetPeriodAttendanceApi(); elseif (isset($_GET['student_id'])) handleGetStudentAttendanceApi();
else sendJsonErrorResponse('Invalid request', 400, 'attendance_api');
else sendJsonErrorResponse('Method not allowed', 405, 'attendance_api');

/**
 * Handles API request to add a new period
 * @throws JsonException
 */
function handleAddPeriodApi(): void
{
    // Check if user has teacher role
    if (!hasRole(2)) sendJsonErrorResponse('Unauthorized access', 401, 'add_period_api');

    // Get teacher ID
    $teacherId = getTeacherId();
    if (!$teacherId) sendJsonErrorResponse('Teacher profile not found', 404, 'add_period_api');

    // Get and validate data
    $classSubjectId = isset($_POST['class_subject_id']) ? (int)$_POST['class_subject_id'] : 0;
    $periodDate = $_POST['period_date'] ?? '';
    $periodLabel = $_POST['period_label'] ?? '';

    // Validate class-subject access
    if (!teacherHasAccessToClassSubject($classSubjectId, $teacherId)) sendJsonErrorResponse('You do not have access to this class-subject', 403, 'add_period_api');

    // Get class ID for the class-subject
    $db = safeGetDBConnection('add_period_api');
    $stmt = $db->prepare('SELECT class_id FROM class_subjects WHERE class_subject_id = ?');
    $stmt->execute([$classSubjectId]);
    $classId = (int)$stmt->fetchColumn();

    // Check if class is empty (has no students)
    if ($classId && isClassEmpty($classId)) sendJsonErrorResponse('Cannot add a period to an empty class', 400, 'add_period_api');

    // Validate date format
    if (!validateDate($periodDate)) sendJsonErrorResponse('Invalid date format', 400, 'add_period_api');

    // Validate period date range
    if (!validatePeriodDate($periodDate)) sendJsonErrorResponse('Invalid date range. Date must be within 30 days in the future and 180 days in the past', 400, 'add_period_api');

    // Validate period label
    if (empty($periodLabel) || strlen($periodLabel) > 50) sendJsonErrorResponse('Invalid period label', 400, 'add_period_api');

    // Check for duplicate period
    $stmt = $db->prepare('SELECT COUNT(*) FROM periods WHERE class_subject_id = ? AND period_date = ? AND period_label = ?');
    $stmt->execute([$classSubjectId, $periodDate, $periodLabel]);
    if ((int)$stmt->fetchColumn() > 0) sendJsonErrorResponse('A period with the same date and label already exists', 409, 'add_period_api');

    // Add the period
    $periodId = addPeriod($classSubjectId, $periodDate, $periodLabel);
    if (!$periodId) sendJsonErrorResponse('Failed to add period', 500, 'add_period_api');

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'period_id' => $periodId,
        'message' => 'Period added successfully'
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Handles API request to get attendance for a period
 * @throws JsonException
 */
function handleGetPeriodAttendanceApi(): void
{
    // Check if user has teacher role
    if (!hasRole(2)) sendJsonErrorResponse('Unauthorized access', 401, 'get_period_attendance_api');

    // Get teacher ID
    $teacherId = getTeacherId();
    if (!$teacherId) sendJsonErrorResponse('Teacher profile not found', 404, 'get_period_attendance_api');

    // Get period ID
    $periodId = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0; else $periodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;

    // Validate period ID
    if ($periodId <= 0) sendJsonErrorResponse('Invalid period ID', 400, 'get_period_attendance_api');

    // Check if teacher has access to this period
    $db = safeGetDBConnection('get_period_attendance_api');
    $stmt = $db->prepare('
        SELECT cs.teacher_id, p.period_date
        FROM periods p
        JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
        WHERE p.period_id = ?
    ');
    $stmt->execute([$periodId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) sendJsonErrorResponse('Period not found', 404, 'get_period_attendance_api');

    if ($result['teacher_id'] != $teacherId) sendJsonErrorResponse('You do not have access to this period', 403, 'get_period_attendance_api');

    // Check if period is in the future
    $periodDate = $result['period_date'];
    if (strtotime($periodDate) > time()) sendJsonErrorResponse('Cannot view attendance for future periods', 400, 'get_period_attendance_api');

    // Get attendance records
    try {
        $attendance = getPeriodAttendance($periodId);
    } catch (Exception $e) {
        sendJsonErrorResponse('Failed to get attendance records: ' . $e->getMessage(), 500, 'get_period_attendance_api');
    }

    // Return response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Handles API request to get student attendance
 * @throws JsonException
 */
function handleGetStudentAttendanceApi(): void
{
    // Check if user has an appropriate role
    if (!hasRole(2) && !hasRole(3) && !hasRole(4)) sendJsonErrorResponse('Unauthorized access', 401, 'get_student_attendance_api');

    // Get student ID
    $studentId = 0;
    $startDate = null;
    $endDate = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
    } else {
        $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
    }

    // Validate student ID
    if ($studentId <= 0) sendJsonErrorResponse('Invalid student ID', 400, 'get_student_attendance_api');

    // Verify student exists
    $db = safeGetDBConnection('get_student_attendance_api');
    $stmt = $db->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $stmt->execute([$studentId]);
    if (!$stmt->fetchColumn()) sendJsonErrorResponse('Student not found', 404, 'get_student_attendance_api');

    // Verify access rights to student data
    if (hasRole(4)) { // Parent
        $parentId = getParentId();
        if (!$parentId || !parentHasAccessToStudent($studentId, $parentId)) sendJsonErrorResponse('You do not have access to this student\'s data', 403, 'get_student_attendance_api');
    } else if (hasRole(3)) { // Student
        // Students can only view their own data
        $loggedInStudentId = getStudentId();
        if ($loggedInStudentId != $studentId) sendJsonErrorResponse('You can only view your own attendance data', 403, 'get_student_attendance_api');
    }
    // Teachers can view any student's attendance data

    // Validate date format if provided
    if ($startDate && !validateDate($startDate)) sendJsonErrorResponse('Invalid start date format', 400, 'get_student_attendance_api');

    if ($endDate && !validateDate($endDate)) sendJsonErrorResponse('Invalid end date format', 400, 'get_student_attendance_api');

    // Validate date range
    if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) sendJsonErrorResponse('Start date cannot be after end date', 400, 'get_student_attendance_api');

    // Limit date range to reasonable values (e.g., 1 year max)
    if ($startDate && $endDate) {
        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        $maxRangeTs = 365 * 24 * 60 * 60; // 1 year in seconds

        if (($endTs - $startTs) > $maxRangeTs) sendJsonErrorResponse('Date range cannot exceed 1 year', 400, 'get_student_attendance_api');
    }

    // Get attendance records
    try {
        $attendance = getStudentAttendance($studentId, $startDate, $endDate);
    } catch (Exception $e) {
        sendJsonErrorResponse('Failed to get attendance records: ' . $e->getMessage(), 500, 'get_student_attendance_api');
    }

    // Return response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Handles API request to save attendance record
 * @throws JsonException
 */
function handleSaveAttendanceApi(): void
{
    // Check if user has teacher role
    if (!hasRole(2)) sendJsonErrorResponse('Unauthorized access', 401, 'save_attendance_api');

    // Get teacher ID
    $teacherId = getTeacherId();
    if (!$teacherId) sendJsonErrorResponse('Teacher profile not found', 404, 'save_attendance_api');

    // Get and validate data
    $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
    $enrollId = isset($_POST['enroll_id']) ? (int)$_POST['enroll_id'] : 0;
    $status = $_POST['status'] ?? '';
    $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;

    // Validate period ID
    if ($periodId <= 0) sendJsonErrorResponse('Invalid period ID', 400, 'save_attendance_api');

    // Check if teacher has access to this period
    $db = safeGetDBConnection('save_attendance_api');
    $stmt = $db->prepare('
        SELECT cs.teacher_id 
        FROM periods p
        JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
        WHERE p.period_id = ?
    ');
    $stmt->execute([$periodId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result || $result['teacher_id'] != $teacherId) sendJsonErrorResponse('You do not have access to this period', 403, 'save_attendance_api');

    // Validate status
    if (!in_array($status, ['P', 'A', 'L'])) sendJsonErrorResponse('Invalid attendance status', 400, 'save_attendance_api');

    // If enrollId is not provided, we need both studentId and classId to find it
    if ($enrollId <= 0) {
        if ($studentId <= 0 || $classId <= 0) sendJsonErrorResponse('Missing student or class information', 400, 'save_attendance_api');

        // Find the enrollment ID
        $stmt = $db->prepare('SELECT enroll_id FROM enrollments WHERE student_id = ? AND class_id = ?');
        $stmt->execute([$studentId, $classId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) sendJsonErrorResponse('Student is not enrolled in this class', 404, 'save_attendance_api');

        $enrollId = (int)$result['enroll_id'];
    }

    // Validate the student exists
    $stmt = $db->prepare('SELECT student_id FROM students WHERE student_id = ?');
    $stmt->execute([$studentId]);
    if (!$stmt->fetchColumn()) sendJsonErrorResponse('Student does not exist', 404, 'save_attendance_api');

    // Save the attendance record
    $result = saveAttendance($enrollId, $periodId, $status);
    if (!$result) sendJsonErrorResponse('Failed to save attendance', 500, 'save_attendance_api');

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'enroll_id' => $enrollId,
        'message' => 'Attendance saved successfully'
    ], JSON_THROW_ON_ERROR);
    exit;
}
