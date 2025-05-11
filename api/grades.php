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
