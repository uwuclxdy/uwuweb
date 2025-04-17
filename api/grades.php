<?php
/**
 * Grades API Endpoint
 * 
 * Handles CRUD operations for grade data
 * Returns JSON responses for AJAX requests
 * 
 * Functions:
 * - addGradeItem() - Creates a new grade item
 * - updateGradeItem() - Updates an existing grade item
 * - deleteGradeItem() - Deletes a grade item and its grades
 * - saveGrade() - Saves or updates a student's grade
 * - teacherHasAccessToClass($classId) - Verifies teacher access to class
 * - teacherHasAccessToGradeItem($itemId) - Verifies teacher access to grade item
 * - teacherHasAccessToEnrollment($enrollId) - Verifies teacher access to enrollment
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are allowed']);
    exit;
}

// Ensure CSRF token is valid
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Require teacher role for access
if (!isLoggedIn() || !hasRole(ROLE_TEACHER)) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get the action from the request
$action = $_POST['action'] ?? '';

// Process based on the action
switch ($action) {
    case 'add_grade_item':
        addGradeItem();
        break;
    case 'update_grade_item':
        updateGradeItem();
        break;
    case 'delete_grade_item':
        deleteGradeItem();
        break;
    case 'save_grade':
        saveGrade();
        break;
    default:
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid action requested']);
        break;
}

/**
 * Add a new grade item
 */
function addGradeItem() {
    // Validate inputs
    $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $maxPoints = filter_input(INPUT_POST, 'max_points', FILTER_VALIDATE_FLOAT);
    $weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);
    
    if (!$classId || empty($name) || $maxPoints === false || $maxPoints <= 0 || $weight === false || $weight <= 0) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }
    
    // Verify teacher has access to this class
    if (!teacherHasAccessToClass($classId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this class']);
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO grade_items (class_id, name, max_points, weight) 
             VALUES (:class_id, :name, :max_points, :weight)"
        );
        
        $stmt->execute([
            'class_id' => $classId,
            'name' => $name,
            'max_points' => $maxPoints,
            'weight' => $weight
        ]);
        
        $itemId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Grade item added successfully',
            'item_id' => $itemId,
            'item' => [
                'item_id' => $itemId,
                'name' => $name,
                'max_points' => $maxPoints,
                'weight' => $weight
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Update an existing grade item
 */
function updateGradeItem() {
    // Validate inputs
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $maxPoints = filter_input(INPUT_POST, 'max_points', FILTER_VALIDATE_FLOAT);
    $weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);
    
    if (!$itemId || empty($name) || $maxPoints === false || $maxPoints <= 0 || $weight === false || $weight <= 0) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }
    
    // Verify teacher has access to this grade item
    if (!teacherHasAccessToGradeItem($itemId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this grade item']);
        return;
    }
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "UPDATE grade_items 
             SET name = :name, max_points = :max_points, weight = :weight
             WHERE item_id = :item_id"
        );
        
        $stmt->execute([
            'item_id' => $itemId,
            'name' => $name,
            'max_points' => $maxPoints,
            'weight' => $weight
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Grade item updated successfully',
            'item' => [
                'item_id' => $itemId,
                'name' => $name,
                'max_points' => $maxPoints,
                'weight' => $weight
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Delete a grade item
 */
function deleteGradeItem() {
    // Validate input
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    
    if (!$itemId) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid grade item ID']);
        return;
    }
    
    // Verify teacher has access to this grade item
    if (!teacherHasAccessToGradeItem($itemId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this grade item']);
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Start transaction to ensure data integrity
        $pdo->beginTransaction();
        
        // Delete related grades first
        $stmt = $pdo->prepare("DELETE FROM grades WHERE item_id = :item_id");
        $stmt->execute(['item_id' => $itemId]);
        
        // Then delete the grade item
        $stmt = $pdo->prepare("DELETE FROM grade_items WHERE item_id = :item_id");
        $stmt->execute(['item_id' => $itemId]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Grade item deleted successfully'
        ]);
    } catch (PDOException $e) {
        // Rollback on error
        $pdo->rollBack();
        
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Save or update a student's grade
 */
function saveGrade() {
    // Validate inputs
    $enrollId = filter_input(INPUT_POST, 'enroll_id', FILTER_VALIDATE_INT);
    $itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
    $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_FLOAT);
    $comment = trim($_POST['comment'] ?? '');
    
    if (!$enrollId || !$itemId || $points === false) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data']);
        return;
    }
    
    // Verify teacher has access to this enrollment and grade item
    if (!teacherHasAccessToEnrollment($enrollId) || !teacherHasAccessToGradeItem($itemId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this student or grade item']);
        return;
    }
    
    try {
        $pdo = getDBConnection();
        
        // Check if grade already exists
        $stmt = $pdo->prepare("SELECT grade_id FROM grades WHERE enroll_id = :enroll_id AND item_id = :item_id");
        $stmt->execute([
            'enroll_id' => $enrollId,
            'item_id' => $itemId
        ]);
        $existingGrade = $stmt->fetch();
        
        if ($existingGrade) {
            // Update existing grade
            $stmt = $pdo->prepare(
                "UPDATE grades 
                 SET points = :points, comment = :comment
                 WHERE enroll_id = :enroll_id AND item_id = :item_id"
            );
            
            $stmt->execute([
                'enroll_id' => $enrollId,
                'item_id' => $itemId,
                'points' => $points,
                'comment' => $comment
            ]);
            
            $gradeId = $existingGrade['grade_id'];
        } else {
            // Insert new grade
            $stmt = $pdo->prepare(
                "INSERT INTO grades (enroll_id, item_id, points, comment) 
                 VALUES (:enroll_id, :item_id, :points, :comment)"
            );
            
            $stmt->execute([
                'enroll_id' => $enrollId,
                'item_id' => $itemId,
                'points' => $points,
                'comment' => $comment
            ]);
            
            $gradeId = $pdo->lastInsertId();
        }
        
        // Get the max points for this grade item to calculate percentage
        $stmt = $pdo->prepare("SELECT max_points FROM grade_items WHERE item_id = :item_id");
        $stmt->execute(['item_id' => $itemId]);
        $gradeItem = $stmt->fetch();
        $maxPoints = $gradeItem['max_points'];
        $percentage = ($maxPoints > 0) ? (($points / $maxPoints) * 100) : 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Grade saved successfully',
            'grade' => [
                'grade_id' => $gradeId,
                'points' => $points,
                'comment' => $comment,
                'percentage' => round($percentage, 1)
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Check if the logged-in teacher has access to a specific class
 */
function teacherHasAccessToClass($classId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "SELECT c.class_id
             FROM classes c
             JOIN teachers t ON c.teacher_id = t.teacher_id
             WHERE t.user_id = :user_id AND c.class_id = :class_id"
        );
        
        $stmt->execute([
            'user_id' => getUserId(),
            'class_id' => $classId
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if the logged-in teacher has access to a specific grade item
 */
function teacherHasAccessToGradeItem($itemId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "SELECT gi.item_id
             FROM grade_items gi
             JOIN classes c ON gi.class_id = c.class_id
             JOIN teachers t ON c.teacher_id = t.teacher_id
             WHERE t.user_id = :user_id AND gi.item_id = :item_id"
        );
        
        $stmt->execute([
            'user_id' => getUserId(),
            'item_id' => $itemId
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if the logged-in teacher has access to a specific enrollment
 */
function teacherHasAccessToEnrollment($enrollId) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "SELECT e.enroll_id
             FROM enrollments e
             JOIN classes c ON e.class_id = c.class_id
             JOIN teachers t ON c.teacher_id = t.teacher_id
             WHERE t.user_id = :user_id AND e.enroll_id = :enroll_id"
        );
        
        $stmt->execute([
            'user_id' => getUserId(),
            'enroll_id' => $enrollId
        ]);
        
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}