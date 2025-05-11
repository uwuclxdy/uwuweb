<?php
/**
 * Attendance API Endpoint
 *
 * File path: /api/attendance.php
 *
 * Handles CRUD operations for attendance data via AJAX requests.
 * Returns JSON responses for client-side processing.
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify user is logged in
if (!isLoggedIn()) sendJsonErrorResponse('Authentication required', 401, 'attendance.php');

// Set JSON content type
header('Content-Type: application/json');

// Determine action based on request
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'saveAttendance':
            handleSaveAttendanceApi();
            break;

        case 'addPeriod':
            handleAddPeriodApi();
            break;

        case 'getPeriodAttendance':
            handleGetPeriodAttendanceApi();
            break;

        case 'getStudentAttendance':
            handleGetStudentAttendanceApi();
            break;

        default:
            sendJsonErrorResponse('Invalid action specified', 400, 'attendance.php');
    }
} catch (Exception $e) {
    sendJsonErrorResponse('Server error: ' . $e->getMessage(), 500, 'attendance.php');
}

/**
 * API handler for adding a new period
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleAddPeriodApi(): void
{
    // Only teachers can add periods
    if (getUserRole() !== ROLE_TEACHER && getUserRole() !== ROLE_ADMIN) sendJsonErrorResponse('Unauthorized action', 403, 'attendance.php/handleAddPeriodApi');

    // Validate parameters
    $classSubjectId = filter_input(INPUT_POST, 'class_subject_id', FILTER_VALIDATE_INT);
    $periodDate = filter_input(INPUT_POST, 'period_date');
    $periodLabel = filter_input(INPUT_POST, 'period_label');

    if (!$classSubjectId || !$periodDate || !$periodLabel) sendJsonErrorResponse('Missing or invalid parameters', 400, 'attendance.php/handleAddPeriodApi');

    // Validate date format
    if (!validateDate($periodDate)) sendJsonErrorResponse('Invalid date format (required: YYYY-MM-DD)', 400, 'attendance.php/handleAddPeriodApi');

    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) sendJsonErrorResponse('Invalid CSRF token', 403, 'attendance.php/handleAddPeriodApi');

    // Call the business logic function
    $periodId = addPeriod($classSubjectId, $periodDate, $periodLabel);

    if (!$periodId) sendJsonErrorResponse('Failed to create period', 500, 'attendance.php/handleAddPeriodApi');

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Period added successfully',
        'period_id' => $periodId
    ], JSON_THROW_ON_ERROR);
}

/**
 * API handler for getting period attendance
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleGetPeriodAttendanceApi(): void
{
    // Validate parameters
    $periodId = filter_input(INPUT_POST, 'period_id', FILTER_VALIDATE_INT);

    if (!$periodId) sendJsonErrorResponse('Missing or invalid period ID', 400, 'attendance.php/handleGetPeriodAttendanceApi');

    // Call the business logic function
    $attendance = getPeriodAttendance($periodId);

    // Return response
    echo json_encode([
        'success' => true,
        'attendance' => $attendance
    ], JSON_THROW_ON_ERROR);
}

/**
 * API handler for getting student attendance
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleGetStudentAttendanceApi(): void
{
    // Validate parameters
    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $startDate = filter_input(INPUT_POST, 'start_date');
    $endDate = filter_input(INPUT_POST, 'end_date');

    if (!$studentId) sendJsonErrorResponse('Missing or invalid student ID', 400, 'attendance.php/handleGetStudentAttendanceApi');

    // Validate dates if provided
    if ($startDate && !validateDate($startDate)) sendJsonErrorResponse('Invalid start date format (required: YYYY-MM-DD)', 400, 'attendance.php/handleGetStudentAttendanceApi');

    if ($endDate && !validateDate($endDate)) sendJsonErrorResponse('Invalid end date format (required: YYYY-MM-DD)', 400, 'attendance.php/handleGetStudentAttendanceApi');

    // Call the business logic function
    $attendance = getStudentAttendance($studentId, $startDate, $endDate);

    // Calculate statistics
    $stats = calculateAttendanceStats($attendance);

    // Return response
    echo json_encode([
        'success' => true,
        'attendance' => $attendance,
        'stats' => $stats
    ], JSON_THROW_ON_ERROR);
}
