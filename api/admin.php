<?php
/**
 * Admin API Endpoints
 *
 * File path: /api/admin.php
 *
 * Provides centralized API endpoints for administrator operations, including
 * class management, teacher assignments, and system settings.
 *
 * Endpoints:
 * - handleGetClassDetails(): void - Returns detailed information about a class including enrolled students and subject assignments
 * - handleGetSubjectDetails(): void - Returns detailed information about a subject including assigned classes
 * - handleGetTeacherDetails(): void - Returns detailed information about a teacher including assigned classes and subjects
 * - handleGetUserDetails(): void - Returns detailed information about any user for the admin panel
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../admin/admin_functions.php';

// Ensure only authenticated administrators can access these endpoints
requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

// Verify CSRF token for non-GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    try {
        $requestData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
    } catch (JsonException $e) {
        sendJsonErrorResponse('Neveljaven JSON', 400, 'admin.php');
    }
    $providedToken = $requestData['csrf_token'] ?? null;

    if (!$providedToken || !verifyCSRFToken($providedToken)) sendJsonErrorResponse('Neveljaven varnostni žeton', 403, 'admin.php');
}

// Process API requests
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'getClassDetails':
            handleGetClassDetails();
            break;

        case 'getSubjectDetails':
            handleGetSubjectDetails();
            break;

        case 'getTeacherDetails':
            handleGetTeacherDetails();
            break;

        case 'getUserDetails':
            handleGetUserDetails();
            break;

        default:
            sendJsonErrorResponse('Neveljavna dejanja zahtevana', 400, 'admin.php');
    }
} catch (PDOException $e) {
    error_log('Database error (admin.php): ' . $e->getMessage());
    sendJsonErrorResponse('Napaka baze podatkov', 500, 'admin.php');
} catch (Exception $e) {
    error_log('API Error (admin.php): ' . $e->getMessage());
    sendJsonErrorResponse('Napaka strežnika: ' . $e->getMessage(), 500, 'admin.php');
}

/**
 * Handles the getClassDetails API endpoint
 * Returns detailed information about a class including students and subjects
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 * @throws JsonException
 */
function handleGetClassDetails(): void
{
    $classId = filter_var($_GET['id'] ?? '0', FILTER_VALIDATE_INT);

    if ($classId === false || $classId <= 0) sendJsonErrorResponse('Neveljaven ID razreda', 400, 'admin.php');

    $classDetails = getClassDetails($classId);

    if (!$classDetails) sendJsonErrorResponse('Razred ni bil najden', 404, 'admin.php');

    echo json_encode([
        'success' => true,
        'data' => $classDetails
    ], JSON_THROW_ON_ERROR);
}

/**
 * Handles the getSubjectDetails API endpoint
 * Returns detailed information about a subject including assigned classes
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 * @throws JsonException
 */
function handleGetSubjectDetails(): void
{
    $subjectId = filter_var($_GET['id'] ?? '0', FILTER_VALIDATE_INT);

    if ($subjectId === false || $subjectId <= 0) sendJsonErrorResponse('Neveljaven ID predmeta', 400, 'admin.php');

    $subjectDetails = getSubjectDetails($subjectId);

    if (!$subjectDetails) sendJsonErrorResponse('Predmet ni bil najden', 404, 'admin.php');

    echo json_encode([
        'success' => true,
        'data' => $subjectDetails
    ], JSON_THROW_ON_ERROR);
}

/**
 * Handles the getTeacherDetails API endpoint
 * Returns detailed information about a teacher including classes and assignments
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 * @throws JsonException
 */
function handleGetTeacherDetails(): void
{
    $teacherId = filter_var($_GET['id'] ?? '0', FILTER_VALIDATE_INT);

    if ($teacherId === false || $teacherId <= 0) sendJsonErrorResponse('Neveljaven ID učitelja', 400, 'admin.php');

    $teacherDetails = getUserDetails($teacherId);

    if (!$teacherDetails || $teacherDetails['role_id'] !== ROLE_TEACHER) sendJsonErrorResponse('Učitelj ni bil najden', 404, 'admin.php');

    echo json_encode([
        'success' => true,
        'data' => $teacherDetails
    ], JSON_THROW_ON_ERROR);
}

/**
 * Handles the getUserDetails API endpoint
 * Returns detailed information about any user for admin panel operations
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 */
function handleGetUserDetails(): void
{
    $userId = filter_var($_GET['id'] ?? '0', FILTER_VALIDATE_INT);

    if ($userId === false || $userId <= 0) sendJsonErrorResponse('Neveljaven ID uporabnika', 400, 'admin.php');

    $userDetails = getUserDetails($userId);

    if (!$userDetails) sendJsonErrorResponse('Uporabnik ni bil najden', 404, 'admin.php');

    echo json_encode($userDetails, JSON_THROW_ON_ERROR);
}
