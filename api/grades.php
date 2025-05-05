<?php /** @noinspection DuplicatedCode */
/**
 * Grades API Endpoint
 *
 * Handles CRUD operations for grade data via AJAX requests.
 * Returns JSON responses for client-side processing.
 * Restricted to teacher role access.
 *
 * Functions:
 * - addGradeItem(): void - Creates a new grade item using JSON request data.
 * - updateGradeItem(): void - Updates an existing grade item using JSON request data.
 * - deleteGradeItem(): void - Removes a grade item and all associated grades.
 * - saveGrade(): void - Creates or updates a grade for a student on a specific grade item.
 * - teacherHasAccessToClass(int $classId): bool - Verifies if the current teacher is assigned to the given class.
 * - teacherHasAccessToGradeItem(int $itemId, int $teacherId): bool - Verifies if the teacher is authorized to modify the given grade item.
 * - teacherHasAccessToEnrollment(int $enrollId): bool - Verifies if the current teacher is authorized to modify grades for the given enrollment.
 *
 * File path: /api/grades.php
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../teacher/teacher_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!hasRole(ROLE_TEACHER) && !hasRole(ROLE_ADMIN))) {
    http_response_code(403);
    sendJsonErrorResponse('Unauthorized access', 403, 'grades.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    try {
        $requestData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
    } catch (JsonException $e) {
        error_log('API Error (grades.php): ' . $e->getMessage());
        sendJsonErrorResponse('Invalid JSON data', 400, 'grades.php');
    }
    $providedToken = $requestData['csrf_token'] ?? null;

    if (!$providedToken || !verifyCSRFToken($providedToken)) sendJsonErrorResponse('Invalid security token', 403, 'grades.php');
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'addGradeItem':
            addGradeItem();
            break;
        case 'updateGradeItem':
            updateGradeItem();
            break;
        case 'deleteGradeItem':
            deleteGradeItem();
            break;
        case 'saveGrade':
            saveGrade();
            break;
        default:
            sendJsonErrorResponse('Invalid action specified', 400, 'grades.php');
    }
} catch (Exception $e) {
    error_log('API Error (grades.php): ' . $e->getMessage());
    sendJsonErrorResponse('Server error: ' . $e->getMessage(), 500, 'grades.php');
}

/**
 * Creates a new grade item for a specific class-subject
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 */
function addGradeItem(): void
{
    try {
        $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendJsonErrorResponse('Invalid JSON data', 400, 'addGradeItem');
    }

    if (!isset($data['class_subject_id'], $data['name'], $data['max_points'])) sendJsonErrorResponse('Missing required fields', 400, 'addGradeItem');

    $classSubjectId = (int)$data['class_subject_id'];
    $name = trim($data['name']);
    $maxPoints = (float)$data['max_points'];
    $weight = isset($data['weight']) ? (float)$data['weight'] : 1.0;

    if (empty($name) || $maxPoints <= 0 || $weight <= 0) sendJsonErrorResponse('Invalid input values', 400, 'addGradeItem');

    if (!teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Unauthorized access to this class-subject', 403, 'addGradeItem');

    try {
        $pdo = safeGetDBConnection('addGradeItem');
        if ($pdo === null) throw new RuntimeException('Database connection failed');

        $stmt = $pdo->prepare("INSERT INTO grade_items (class_subject_id, name, max_points, weight) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$classSubjectId, $name, $maxPoints, $weight]);

        $newItemId = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => 'Grade item added successfully',
            'data' => [
                'item_id' => $newItemId,
                'class_subject_id' => $classSubjectId,
                'name' => $name,
                'max_points' => $maxPoints,
                'weight' => $weight
            ]
        ], JSON_THROW_ON_ERROR);

    } catch (PDOException $e) {
        error_log('Error in addGradeItem: ' . $e->getMessage());
        sendJsonErrorResponse('Failed to add grade item', 500, 'addGradeItem');
    }
}

/**
 * Updates name, max points, and weight for an existing grade item
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 */
function updateGradeItem(): void
{
    try {
        $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendJsonErrorResponse('Invalid JSON data', 400, 'updateGradeItem');
    }

    if (!isset($data['item_id'], $data['name'], $data['max_points'])) sendJsonErrorResponse('Missing required fields', 400, 'updateGradeItem');

    $itemId = (int)$data['item_id'];
    $name = trim($data['name']);
    $maxPoints = (float)$data['max_points'];
    $weight = isset($data['weight']) ? (float)$data['weight'] : 1.0;

    if (empty($name) || $maxPoints <= 0 || $weight <= 0) sendJsonErrorResponse('Invalid input values', 400, 'updateGradeItem');

    $teacherId = getTeacherId();
    if (!teacherHasAccessToGradeItem($itemId, $teacherId)) sendJsonErrorResponse('Unauthorized access to this grade item', 403, 'updateGradeItem');

    try {
        $pdo = safeGetDBConnection('updateGradeItem');
        if ($pdo === null) throw new RuntimeException('Database connection failed');

        $stmt = $pdo->prepare("UPDATE grade_items 
                              SET name = ?, max_points = ?, weight = ? 
                              WHERE item_id = ?");
        $stmt->execute([$name, $maxPoints, $weight, $itemId]);

        if ($stmt->rowCount() > 0) echo json_encode([
            'status' => 'success',
            'message' => 'Grade item updated successfully',
            'data' => [
                'item_id' => $itemId,
                'name' => $name,
                'max_points' => $maxPoints,
                'weight' => $weight
            ]
        ], JSON_THROW_ON_ERROR); else sendJsonErrorResponse('Grade item not found or no changes made', 404, 'updateGradeItem');

    } catch (PDOException $e) {
        error_log('Error in updateGradeItem: ' . $e->getMessage());
        sendJsonErrorResponse('Failed to update grade item', 500, 'updateGradeItem');
    }
}

/**
 * Removes a grade item and all associated grades
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 */
function deleteGradeItem(): void
{
    try {
        $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendJsonErrorResponse('Invalid JSON data', 400, 'deleteGradeItem');
    }

    if (!isset($data['item_id'])) sendJsonErrorResponse('Missing item_id', 400, 'deleteGradeItem');

    $itemId = (int)$data['item_id'];
    $teacherId = getTeacherId();

    if (!teacherHasAccessToGradeItem($itemId, $teacherId)) sendJsonErrorResponse('Unauthorized access to this grade item', 403, 'deleteGradeItem');

    try {
        $pdo = safeGetDBConnection('deleteGradeItem');
        if ($pdo === null) throw new RuntimeException('Database connection failed');

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM grades WHERE item_id = ?");
        $stmt->execute([$itemId]);

        $stmt = $pdo->prepare("DELETE FROM grade_items WHERE item_id = ?");
        $stmt->execute([$itemId]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'Grade item and associated grades deleted successfully'
            ], JSON_THROW_ON_ERROR);
        } else {
            $pdo->rollBack();
            sendJsonErrorResponse('Grade item not found', 404, 'deleteGradeItem');
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) try {
            $pdo->rollBack();
        } catch (PDOException $innerException) {
            error_log('Error during rollback in deleteGradeItem: ' . $innerException->getMessage());
        }

        error_log('Error in deleteGradeItem: ' . $e->getMessage());
        sendJsonErrorResponse('Failed to delete grade item', 500, 'deleteGradeItem');
    }
}

/**
 * Creates or updates a grade for a student on a specific grade item
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 */
function saveGrade(): void
{
    try {
        $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        sendJsonErrorResponse('Invalid JSON data', 400, 'saveGrade');
    }

    if (!isset($data['enroll_id'], $data['item_id'], $data['points'])) sendJsonErrorResponse('Missing required fields', 400, 'saveGrade');

    $enrollId = (int)$data['enroll_id'];
    $itemId = (int)$data['item_id'];
    $points = (float)$data['points'];
    $comment = $data['comment'] ?? '';
    $teacherId = getTeacherId();

    if (!teacherHasAccessToEnrollment($enrollId) || !teacherHasAccessToGradeItem($itemId, $teacherId)) sendJsonErrorResponse('Unauthorized access', 403, 'saveGrade');

    if ($points < 0) sendJsonErrorResponse('Points cannot be negative', 400, 'saveGrade');

    try {
        $pdo = safeGetDBConnection('saveGrade');
        if ($pdo === null) throw new RuntimeException('Database connection failed');

        $stmt = $pdo->prepare("SELECT grade_id FROM grades WHERE enroll_id = ? AND item_id = ?");
        $stmt->execute([$enrollId, $itemId]);
        $existingGrade = $stmt->fetch();

        if ($existingGrade) {
            $stmt = $pdo->prepare("UPDATE grades 
                                  SET points = ?, comment = ? 
                                  WHERE enroll_id = ? AND item_id = ?");
            $stmt->execute([$points, $comment, $enrollId, $itemId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Grade updated successfully',
                'data' => [
                    'grade_id' => $existingGrade['grade_id'],
                    'enroll_id' => $enrollId,
                    'item_id' => $itemId,
                    'points' => $points,
                    'comment' => $comment
                ]
            ], JSON_THROW_ON_ERROR);
        } else {
            $stmt = $pdo->prepare("INSERT INTO grades (enroll_id, item_id, points, comment) 
                                  VALUES (?, ?, ?, ?)");
            $stmt->execute([$enrollId, $itemId, $points, $comment]);

            $newGradeId = $pdo->lastInsertId();

            echo json_encode([
                'status' => 'success',
                'message' => 'Grade added successfully',
                'data' => [
                    'grade_id' => $newGradeId,
                    'enroll_id' => $enrollId,
                    'item_id' => $itemId,
                    'points' => $points,
                    'comment' => $comment
                ]
            ], JSON_THROW_ON_ERROR);
        }

    } catch (PDOException $e) {
        error_log('Error in saveGrade: ' . $e->getMessage());
        sendJsonErrorResponse('Failed to save grade', 500, 'saveGrade');
    }
}

/**
 * Verifies if the current teacher is assigned to the given class
 *
 * @param int $classId The class ID to check access for
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToClass(int $classId): bool
{
    if (hasRole(1)) return true;

    $teacherId = getTeacherId();
    if (!$teacherId) return false;

    try {
        $pdo = safeGetDBConnection('teacherHasAccessToClass');
        if ($pdo === null) {
            error_log("Error in teacherHasAccessToClass: Database connection failed");
            return false;
        }

        $query1 = "SELECT COUNT(*) AS count FROM classes 
                  WHERE class_id = ? AND homeroom_teacher_id = ?";

        $stmt = $pdo->prepare($query1);
        $stmt->execute([$classId, $teacherId]);
        $result = $stmt->fetch();

        if ((int)$result['count'] > 0) return true;

        $query2 = "SELECT COUNT(*) AS count FROM class_subjects 
                  WHERE class_id = ? AND teacher_id = ?";

        $stmt = $pdo->prepare($query2);
        $stmt->execute([$classId, $teacherId]);
        $result = $stmt->fetch();

        return (int)$result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error in teacherHasAccessToClass: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifies if the current teacher is authorized to modify the given grade item
 *
 * @param int $itemId The grade item ID to check access for
 * @param int $teacherId The teacher ID to check
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToGradeItem(int $itemId, int $teacherId): bool
{
    if (hasRole(1)) return true;

    if (!$teacherId) return false;

    try {
        $pdo = safeGetDBConnection('teacherHasAccessToGradeItem');
        if ($pdo === null) {
            error_log("Error in teacherHasAccessToGradeItem: Database connection failed");
            return false;
        }

        $query = "SELECT COUNT(*) AS count 
                 FROM grade_items gi
                 JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                 WHERE gi.item_id = ? AND cs.teacher_id = ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$itemId, $teacherId]);
        $result = $stmt->fetch();

        return (int)$result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error in teacherHasAccessToGradeItem: " . $e->getMessage());
        return false;
    }
}

/**
 * Verifies if the current teacher is authorized to modify grades for the given enrollment
 *
 * @param int $enrollId The enrollment ID to check access for
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToEnrollment(int $enrollId): bool
{
    if (hasRole(1)) return true;

    $teacherId = getTeacherId();
    if (!$teacherId) return false;

    try {
        $pdo = safeGetDBConnection('teacherHasAccessToEnrollment');
        if ($pdo === null) {
            error_log("Error in teacherHasAccessToEnrollment: Database connection failed");
            return false;
        }

        $query = "SELECT COUNT(*) AS count
                  FROM enrollments e
                  JOIN class_subjects cs ON e.class_id = cs.class_id
                  WHERE e.enroll_id = ? AND cs.teacher_id = ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$enrollId, $teacherId]);
        $result = $stmt->fetch();

        return (int)$result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error in teacherHasAccessToEnrollment: " . $e->getMessage());
        return false;
    }
}
