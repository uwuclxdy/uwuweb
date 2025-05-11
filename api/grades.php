<?php
/**
 * Grades API Endpoint
 *
 * File path: /api/grades.php
 *
 * Handles CRUD operations for grade data via AJAX requests.
 * Returns JSON responses for client-side processing.
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Verify user is logged in
if (!isLoggedIn()) sendJsonErrorResponse('Authentication required', 401, 'grades.php');

// Set JSON content type
header('Content-Type: application/json');

// Determine action based on request
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'addGradeItem':
            handleAddGradeItemApi();
            break;

        case 'saveGrade':
            handleSaveGradeApi();
            break;

        case 'getGradeItems':
            handleGetGradeItemsApi();
            break;

        case 'getClassGrades':
            handleGetClassGradesApi();
            break;

        default:
            sendJsonErrorResponse('Invalid action specified', 400, 'grades.php');
    }
} catch (Exception $e) {
    sendJsonErrorResponse('Server error: ' . $e->getMessage(), 500, 'grades.php');
}

/**
 * API handler for retrieving grade items
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleGetGradeItemsApi(): void
{
    // Validate parameters
    $classSubjectId = filter_input(INPUT_POST, 'class_subject_id', FILTER_VALIDATE_INT);

    if (!$classSubjectId) sendJsonErrorResponse('Missing or invalid class_subject_id', 400, 'grades.php/handleGetGradeItemsApi');

    // Check if user has proper role
    if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Unauthorized access to class', 403, 'grades.php/handleGetGradeItemsApi');

    // Get grade items using the standardized function
    $gradeItems = getGradeItems($classSubjectId);

    // Return response
    echo json_encode([
        'success' => true,
        'items' => $gradeItems
    ], JSON_THROW_ON_ERROR);
}

/**
 * API handler for retrieving class grades
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleGetClassGradesApi(): void
{
    // Validate parameters
    $classSubjectId = filter_input(INPUT_POST, 'class_subject_id', FILTER_VALIDATE_INT);

    if (!$classSubjectId) sendJsonErrorResponse('Missing or invalid class_subject_id', 400, 'grades.php/handleGetClassGradesApi');

    // Check if user has proper role
    if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Unauthorized access to class', 403, 'grades.php/handleGetClassGradesApi');

    // Get class grades using the standardized function
    $gradesData = getClassGrades($classSubjectId);

    // Return response
    echo json_encode([
        'success' => true,
        'data' => $gradesData
    ], JSON_THROW_ON_ERROR);
}

/**
 * API handler for adding grade item
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleAddGradeItemApi(): void
{
    // Ensure POST request with required parameters
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonErrorResponse('Invalid request method', 405, 'grades.php/handleAddGradeItemApi');

    // Extract and validate request parameters
    $classSubjectId = filter_input(INPUT_POST, 'class_subject_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name');
    $maxPoints = filter_input(INPUT_POST, 'max_points', FILTER_VALIDATE_FLOAT);
    $weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT) ?: 1.00;

    if (!$classSubjectId || !$name || !$maxPoints) sendJsonErrorResponse('Missing or invalid parameters', 400, 'grades.php/handleAddGradeItemApi');

    // Verify CSRF token
    $token = filter_input(INPUT_POST, 'csrf_token');
    if (!$token || !verifyCSRFToken($token)) sendJsonErrorResponse('Invalid CSRF token', 403, 'grades.php/handleAddGradeItemApi');

    // Call the business logic function
    $result = addGradeItem($classSubjectId, $name, $maxPoints, $weight);

    // Return appropriate response
    if ($result) echo json_encode(['success' => true, 'item_id' => $result, 'message' => 'Grade item added successfully'], JSON_THROW_ON_ERROR); else sendJsonErrorResponse('Failed to add grade item', 500, 'grades.php/handleAddGradeItemApi');
}

/**
 * API handler for saving grade
 *
 * @return void Outputs JSON response
 * @throws JsonException
 * @throws JsonException
 */
function handleSaveGradeApi(): void
{
    // Ensure POST request with required parameters
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendJsonErrorResponse('Invalid request method', 405, 'grades.php/handleSaveGradeApi');

    // Extract and validate request parameters
    $enrollId = filter_input(INPUT_POST, 'enroll_id', FILTER_VALIDATE_INT);
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_FLOAT);
    $comment = filter_input(INPUT_POST, 'comment');

    if (!$enrollId || !$itemId || $points === false) sendJsonErrorResponse('Missing or invalid parameters', 400, 'grades.php/handleSaveGradeApi');

    // Verify CSRF token
    $token = filter_input(INPUT_POST, 'csrf_token');
    if (!$token || !verifyCSRFToken($token)) sendJsonErrorResponse('Invalid CSRF token', 403, 'grades.php/handleSaveGradeApi');

    // Call the business logic function
    $result = saveGrade($enrollId, $itemId, $points, $comment);

    // Return appropriate response
    if ($result) echo json_encode(['success' => true, 'message' => 'Grade saved successfully'], JSON_THROW_ON_ERROR); else sendJsonErrorResponse('Failed to save grade', 500, 'grades.php/handleSaveGradeApi');
}
