<?php /** @noinspection DuplicatedCode */
/**
 * Grades API Endpoint
 *
 * Handles CRUD operations for grade data via AJAX requests.
 * Returns JSON responses for client-side processing.
 * Restricted to teacher role access.
 *
 * File path: /api/grades.php
 *
 * Functions:
 * - addGradeItem() - Creates a new grade item
 * - updateGradeItem() - Updates an existing grade item
 * - deleteGradeItem() - Deletes a grade item and its grades
 * - saveGrade() - Saves or updates a student's grade
 * - teacherHasAccessToClass($classId) - Verifies teacher access to class
 * - teacherHasAccessToGradeItem($itemId) - Verifies teacher access to grade item
 * - teacherHasAccessToEnrollment($enrollId) - Verifies teacher access to enrollment
 * - teacherHasAccessToClassSubject($classSubjectId) - Verifies teacher access to class-subject
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../teacher/teacher_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || (!hasRole(2) && !hasRole(1))) { // Role 2 = Teacher, Role 1 = Admin
    http_response_code(403);
    try {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access'], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('API Error (grades.php): ' . $e->getMessage());
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    try {
        $requestData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
    } catch (JsonException $e) {
        error_log('API Error (grades.php): ' . $e->getMessage());
        http_response_code(400);
        exit;
    }
    $providedToken = $requestData['csrf_token'] ?? null;

    if (!$providedToken || !verifyCSRFToken($providedToken)) {
        http_response_code(403);
        try {
            echo json_encode(['status' => 'error', 'message' => 'Invalid security token'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (grades.php): ' . $e->getMessage());
        }
        exit;
    }
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
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action specified'], JSON_THROW_ON_ERROR);
    }
} catch (Exception $e) {
    http_response_code(500);
    try {
        echo json_encode(['status' => 'error', 'message' => 'Server error', 'details' => $e->getMessage()], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('API Error (grades.php): ' . $e->getMessage());
    }
    error_log('API Error (grades.php): ' . $e->getMessage());
}

/**
 * Creates a new grade item for a specific class-subject
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 * @throws JsonException
 * @throws Exception
 */
function addGradeItem(): void {
    $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!isset($data['class_subject_id'], $data['name'], $data['max_points'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields'], JSON_THROW_ON_ERROR);
        return;
    }

    $classSubjectId = (int)$data['class_subject_id'];
    $name = trim($data['name']);
    $maxPoints = (float)$data['max_points'];
    $weight = isset($data['weight']) ? (float)$data['weight'] : 1.0;

    if (empty($name) || $maxPoints <= 0 || $weight <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid input values'], JSON_THROW_ON_ERROR);
        return;
    }

    if (!teacherHasAccessToClassSubject($classSubjectId)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access to this class-subject'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = safeGetDBConnection('addGradeItem');
        if ($pdo === null) {
            throw new RuntimeException('Database connection failed');
        }

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
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to add grade item'], JSON_THROW_ON_ERROR);
        error_log('Error in addGradeItem: ' . $e->getMessage());
    }
}

/**
 * Updates name, max points, and weight for an existing grade item
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 * @throws Exception
 */
function updateGradeItem(): void {
    $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!isset($data['item_id'], $data['name'], $data['max_points'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields'], JSON_THROW_ON_ERROR);
        return;
    }

    $itemId = (int)$data['item_id'];
    $name = trim($data['name']);
    $maxPoints = (float)$data['max_points'];
    $weight = isset($data['weight']) ? (float)$data['weight'] : 1.0;

    if (empty($name) || $maxPoints <= 0 || $weight <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid input values'], JSON_THROW_ON_ERROR);
        return;
    }

    if (!teacherHasAccessToGradeItem($itemId)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access to this grade item'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = safeGetDBConnection('updateGradeItem');
        if ($pdo === null) {
            throw new RuntimeException('Database connection failed');
        }

        $stmt = $pdo->prepare("UPDATE grade_items 
                              SET name = ?, max_points = ?, weight = ? 
                              WHERE item_id = ?");
        $stmt->execute([$name, $maxPoints, $weight, $itemId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Grade item updated successfully',
                'data' => [
                    'item_id' => $itemId,
                    'name' => $name,
                    'max_points' => $maxPoints,
                    'weight' => $weight
                ]
            ], JSON_THROW_ON_ERROR);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Grade item not found or no changes made'], JSON_THROW_ON_ERROR);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update grade item'], JSON_THROW_ON_ERROR);
        error_log('Error in updateGradeItem: ' . $e->getMessage());
    }
}

/**
 * Removes a grade item and all associated grades
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 * @throws JsonException
 * @throws Exception
 */
function deleteGradeItem(): void {
    $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!isset($data['item_id'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing item_id'], JSON_THROW_ON_ERROR);
        return;
    }

    $itemId = (int)$data['item_id'];

    if (!teacherHasAccessToGradeItem($itemId)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access to this grade item'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = safeGetDBConnection('deleteGradeItem');
        if ($pdo === null) {
            throw new RuntimeException('Database connection failed');
        }

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
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Grade item not found'], JSON_THROW_ON_ERROR);
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            try {
                $pdo->rollBack();
            } catch (PDOException $innerException) {
                error_log('Error during rollback in deleteGradeItem: ' . $innerException->getMessage());
            }
        }

        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete grade item'], JSON_THROW_ON_ERROR);
        error_log('Error in deleteGradeItem: ' . $e->getMessage());
    }
}

/**
 * Creates or updates a grade for a student on a specific grade item
 *
 * @return void Outputs JSON response directly
 * @throws JsonException
 * @throws JsonException
 * @throws Exception
 */
function saveGrade(): void {
    $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!isset($data['enroll_id'], $data['item_id'], $data['points'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields'], JSON_THROW_ON_ERROR);
        return;
    }

    $enrollId = (int)$data['enroll_id'];
    $itemId = (int)$data['item_id'];
    $points = (float)$data['points'];
    $comment = $data['comment'] ?? '';

    if (!teacherHasAccessToEnrollment($enrollId) || !teacherHasAccessToGradeItem($itemId)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access'], JSON_THROW_ON_ERROR);
        return;
    }

    if ($points < 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Points cannot be negative'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = safeGetDBConnection('saveGrade');
        if ($pdo === null) {
            throw new RuntimeException('Database connection failed');
        }

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
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save grade'], JSON_THROW_ON_ERROR);
        error_log('Error in saveGrade: ' . $e->getMessage());
    }
}

/**
 * Verifies if the current teacher is assigned to the given class
 *
 * @param int $classId The class ID to check access for
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToClass(int $classId): bool {
    if (hasRole(1)) {
        return true;
    }

    $teacherId = getTeacherId();
    if (!$teacherId) {
        return false;
    }

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

        if ((int)$result['count'] > 0) {
            return true;
        }

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
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToGradeItem(int $itemId): bool {
    if (hasRole(1)) {
        return true;
    }

    $teacherId = getTeacherId();
    if (!$teacherId) {
        return false;
    }

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
function teacherHasAccessToEnrollment(int $enrollId): bool {
    if (hasRole(1)) {
        return true;
    }

    $teacherId = getTeacherId();
    if (!$teacherId) {
        return false;
    }

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

/**
 * Verifies if the current teacher is assigned to the given class-subject
 *
 * @param int $classSubjectId The class-subject ID to check access for
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToClassSubject(int $classSubjectId): bool {
    if (hasRole(1)) {
        return true;
    }

    $teacherId = getTeacherId();
    if (!$teacherId) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('teacherHasAccessToClassSubject');
        if ($pdo === null) {
            error_log("Error in teacherHasAccessToClassSubject: Database connection failed");
            return false;
        }

        $query = "SELECT COUNT(*) AS count 
                  FROM class_subjects 
                  WHERE class_subject_id = ? AND teacher_id = ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classSubjectId, $teacherId]);
        $result = $stmt->fetch();

        return (int)$result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error in teacherHasAccessToClassSubject: " . $e->getMessage());
        return false;
    }
}
