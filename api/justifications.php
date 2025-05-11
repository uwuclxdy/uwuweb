<?php
/**
 * Justifications API Endpoint
 *
 * File path: /api/justifications.php
 *
 * Handles CRUD operations for absence justifications via AJAX requests.
 * Returns JSON responses for client-side processing.
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify user is logged in
if (!isLoggedIn()) sendJsonErrorResponse('Authentication required', 401, 'justifications.php');

// Set JSON content type
header('Content-Type: application/json');

// Determine action based on request
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'submitJustification':
            handleSubmitJustificationApi();
            break;

        case 'approveJustification':
            handleApproveJustificationApi();
            break;

        case 'rejectJustification':
            handleRejectJustificationApi();
            break;

        case 'getJustifications':
            handleGetJustificationsApi();
            break;

        case 'getJustificationDetails':
            handleGetJustificationDetailsApi();
            break;

        default:
            sendJsonErrorResponse('Invalid action specified', 400, 'justifications.php');
    }
} catch (Exception $e) {
    sendJsonErrorResponse('Server error: ' . $e->getMessage(), 500, 'justifications.php');
}

/**
 * API handler for submitting justification
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleSubmitJustificationApi(): void
{
    // Only students can submit justifications
    if (getUserRole() !== ROLE_STUDENT) sendJsonErrorResponse('Only students can submit justifications', 403, 'justifications.php/handleSubmitJustificationApi');

    // Validate parameters
    $absenceId = filter_input(INPUT_POST, 'att_id', FILTER_VALIDATE_INT);
    $justificationText = $_POST['justification'] ?? '';

    if (!$absenceId) sendJsonErrorResponse('Missing or invalid attendance ID', 400, 'justifications.php/handleSubmitJustificationApi');

    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) sendJsonErrorResponse('Invalid CSRF token', 403, 'justifications.php/handleSubmitJustificationApi');

    // Process file upload if exists
    $fileUploaded = !empty($_FILES['justification_file']['name']);
    $fileName = null;

    if ($fileUploaded) {
        $fileName = saveJustificationFile($_FILES['justification_file'], $absenceId);
        if (!$fileName) sendJsonErrorResponse('Failed to upload justification file', 500, 'justifications.php/handleSubmitJustificationApi');
    }

    // Save justification text if provided
    if (!empty($justificationText)) {
        $result = uploadJustification($absenceId, $justificationText);
        if (!$result) sendJsonErrorResponse('Failed to save justification text', 500, 'justifications.php/handleSubmitJustificationApi');
    } else if (!$fileUploaded) sendJsonErrorResponse('No justification text or file provided', 400, 'justifications.php/handleSubmitJustificationApi');

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Justification submitted successfully',
        'file_uploaded' => $fileUploaded,
        'filename' => $fileName
    ], JSON_THROW_ON_ERROR);
}

/**
 * API handler for retrieving justifications
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleGetJustificationsApi(): void
{
    $userRole = getUserRole();
    $result = [];

    // Get justifications based on user role
    switch ($userRole) {
        case ROLE_TEACHER:
            $teacherId = getTeacherId();
            if (!$teacherId) sendJsonErrorResponse('Teacher ID not found', 404, 'justifications.php/handleGetJustificationsApi');
            $result = getPendingJustifications($teacherId);
            break;

        case ROLE_STUDENT:
            $studentId = getStudentId();
            if (!$studentId) sendJsonErrorResponse('Student ID not found', 404, 'justifications.php/handleGetJustificationsApi');
            // This would need to be implemented - get justifications for a student
            $result = []; // Placeholder
            break;

        case ROLE_PARENT:
            // Get student ID from parameters for parent
            $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
            if (!$studentId || !parentHasAccessToStudent($studentId)) sendJsonErrorResponse('Invalid or unauthorized student ID', 403, 'justifications.php/handleGetJustificationsApi');
            // This would need to be implemented - get justifications for a student
            $result = []; // Placeholder
            break;

        case ROLE_ADMIN:
            // Admin can see all justifications
            // This would need to be implemented - get all justifications
            $result = []; // Placeholder
            break;

        default:
            sendJsonErrorResponse('Unauthorized role', 403, 'justifications.php/handleGetJustificationsApi');
    }

    // Return response
    echo json_encode([
        'success' => true,
        'justifications' => $result
    ], JSON_THROW_ON_ERROR);
}

/**
 * API handler for getting justification details
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleGetJustificationDetailsApi(): void
{
    // Validate parameters
    $absenceId = filter_input(INPUT_POST, 'att_id', FILTER_VALIDATE_INT);

    if (!$absenceId) sendJsonErrorResponse('Missing or invalid attendance ID', 400, 'justifications.php/handleGetJustificationDetailsApi');

    // Get justification details using standardized function
    $justification = getJustificationById($absenceId);

    if (!$justification) sendJsonErrorResponse('Justification not found or access denied', 404, 'justifications.php/handleGetJustificationDetailsApi');

    // Return response
    echo json_encode([
        'success' => true,
        'justification' => $justification
    ], JSON_THROW_ON_ERROR);
}
