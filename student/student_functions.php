<?php
/**
 * Student Functions Library
 *
 * File path: /student/student_functions.php
 *
 * Provides functions for retrieving and managing student grades, attendance,
 * and absence justifications. This file serves as the complete API for the
 * student module functionality.
 *
 * Student Data Retrieval:
 * - getStudentId(): ?int - Returns student ID for current user, with caching for optimization
 * - getStudentAttendance(int $studentId): array - Returns student's attendance records
 * - getStudentGrades(int $studentId): array - Returns student's grades
 * - getClassAverage(int $classId): array - Returns academic averages for class
 * - getStudentAbsences(int $studentId): array - Returns student's absence records
 * - getStudentJustifications(int $studentId): array - Gets justifications submitted by a student
 *
 * Grade Analysis:
 * - calculateWeightedAverage(array $grades): float - Computes weighted grade average
 * - calculateGradeStatistics(array $grades): array - Analyzes grades by subject and class
 *
 * Absence Justifications:
 * - uploadJustification(int $absenceId, string $justification): bool - Stores absence explanation text
 * - validateJustificationFile(array $file): bool - Checks justification file validity
 * - saveJustificationFile(array $file, int $absenceId): bool - Stores justification file securely
 * - getJustificationFileInfo(int $absenceId): ?string - Returns justification file metadata
 *
 * Dashboard Widgets:
 * - renderStudentGradesWidget(): string - Displays student's recent grades and subject performance statistics
 * - renderStudentAttendanceWidget(): string - Shows attendance summary with statistics and recent attendance records
 * - renderStudentClassAveragesWidget(): string - Compares student's performance against class averages across subjects
 * - renderUpcomingClassesWidget(): string - Lists student's scheduled classes for the next week organized by day
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Retrieves the student ID associated with the current user
 *
 * @return int|null Student ID or null if not a student or not logged in
 */
function getStudentId(): ?int
{
    $userId = getUserId();
    if (!$userId) return null;

    static $studentIdCache = null;
    if ($studentIdCache !== null && isset($studentIdCache[$userId])) return $studentIdCache[$userId];

    try {
        $db = getDBConnection();
        if (!$db) return null;

        $query = "SELECT student_id FROM students WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $result ? (int)$result['student_id'] : null;

        if ($studentIdCache === null) $studentIdCache = [];
        $studentIdCache[$userId] = $id;

        return $id;
    } catch (PDOException $e) {
        error_log("Database error in getStudentId: " . $e->getMessage());
        return null;
    }
}

/**
 * Gets attendance records for a student
 *
 * @param int $studentId Student ID
 * @return array Attendance records
 */
function getStudentAttendance(int $studentId): array
{
    try {
        $pdo = safeGetDBConnection('getStudentAttendance');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentAttendance");
            return [];
        }

        $query = "
            SELECT a.att_id, a.status, a.justification, a.approved, a.reject_reason,
                   p.period_date, p.period_label,
                   s.name as subject_name,
                   c.class_code, c.title as class_title
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN classes c ON cs.class_id = c.class_id
            WHERE e.student_id = :student_id
            ORDER BY p.period_date DESC, p.period_label";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return [];
    }
}

/**
 * Gets grades for a student
 *
 * @param int $studentId Student ID
 * @return array Student grades
 */
function getStudentGrades(int $studentId): array
{
    try {
        $pdo = safeGetDBConnection('getStudentGrades');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentGrades");
            return [];
        }

        $query = "
            SELECT g.grade_id, g.points, g.comment,
                   gi.name as item_name, gi.max_points, gi.weight,
                   s.name as subject_name,
                   c.class_code, c.title as class_title
            FROM grades g
            JOIN enrollments e ON g.enroll_id = e.enroll_id
            JOIN grade_items gi ON g.item_id = gi.item_id
            JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN classes c ON cs.class_id = c.class_id
            WHERE e.student_id = :student_id
            ORDER BY s.name, gi.name";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return [];
    }
}

/**
 * Gets class average for a specific class
 *
 * @param int $classId Class ID
 * @return array Class averages by grade item
 */
function getClassAverage(int $classId): array
{
    try {
        $pdo = safeGetDBConnection('getClassAverage');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getClassAverage");
            return [];
        }

        $query = "
            SELECT
                gi.item_id,
                gi.name as item_name,
                gi.max_points,
                gi.weight,
                s.subject_id,
                s.name as subject_name,
                AVG(g.points) as average_points,
                AVG(g.points / gi.max_points * 100) as average_percentage,
                COUNT(g.grade_id) as grade_count
            FROM grade_items gi
            JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            LEFT JOIN grades g ON gi.item_id = g.item_id
            WHERE cs.class_id = :class_id
            GROUP BY gi.item_id, gi.name, gi.max_points, gi.weight, s.subject_id, s.name
            ORDER BY s.name, gi.name";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return [];
    }
}

/**
 * Calculate weighted average for a set of grades
 *
 * @param array $grades Grade records
 * @return float|int Weighted average percentage
 */
function calculateWeightedAverage(array $grades): float|int
{
    if (empty($grades)) return 0.0;

    $totalWeightedPoints = 0;
    $totalWeight = 0;

    foreach ($grades as $grade) {
        // Ensure max_points is not zero to avoid division by zero error
        if (isset($grade['max_points']) && $grade['max_points'] != 0) $percentage = ($grade['points'] / $grade['max_points']) * 100; else $percentage = 0;

        $weight = isset($grade['weight']) ? (float)$grade['weight'] : 1.0;

        $totalWeightedPoints += $percentage * $weight;
        $totalWeight += $weight;
    }

    if ($totalWeight <= 0) return 0.0;

    return $totalWeightedPoints / $totalWeight;
}

/**
 * Calculate grade statistics grouped by subject and class
 *
 * @param array $grades Grade records
 * @return array Statistics by subject and class
 */
function calculateGradeStatistics(array $grades): array
{
    if (empty($grades)) return [];

    $statistics = [];

    foreach ($grades as $grade) {
        $subject = $grade['subject_name'];
        $class = $grade['class_code'];

        if (!isset($statistics[$subject])) $statistics[$subject] = [
            'subject_name' => $subject,
            'classes' => [],
            'total_points' => 0,
            'total_max_points' => 0,
            'total_weight' => 0,
            'weighted_average' => 0,
            'grade_count' => 0
        ];

        if (!isset($statistics[$subject]['classes'][$class])) $statistics[$subject]['classes'][$class] = [
            'class_code' => $class,
            'grades' => [],
            'total_points' => 0,
            'total_max_points' => 0,
            'total_weight' => 0,
            'weighted_average' => 0,
            'grade_count' => 0
        ];

        $statistics[$subject]['classes'][$class]['grades'][] = $grade;

        $weight = isset($grade['weight']) ? (float)$grade['weight'] : 1.0;
        $points = isset($grade['points']) ? (float)$grade['points'] : 0.0;
        $max_points = isset($grade['max_points']) ? (float)$grade['max_points'] : 0.0;

        // Only add to totals if max_points is valid
        if ($max_points > 0) {
            $statistics[$subject]['classes'][$class]['total_points'] += $points * $weight;
            $statistics[$subject]['classes'][$class]['total_max_points'] += $max_points * $weight;
            $statistics[$subject]['classes'][$class]['total_weight'] += $weight;
            $statistics[$subject]['classes'][$class]['grade_count']++;

            $statistics[$subject]['total_points'] += $points * $weight;
            $statistics[$subject]['total_max_points'] += $max_points * $weight;
            $statistics[$subject]['total_weight'] += $weight;
            $statistics[$subject]['grade_count']++;
        }
    }

    // Calculate averages only where valid data exists
    foreach ($statistics as &$subjectData) {
        if ($subjectData['total_max_points'] > 0 && $subjectData['total_weight'] > 0) $subjectData['weighted_average'] =
            ($subjectData['total_points'] / $subjectData['total_max_points']) * 100; else $subjectData['weighted_average'] = 0.0;

        foreach ($subjectData['classes'] as &$classData) if ($classData['total_max_points'] > 0 && $classData['total_weight'] > 0) $classData['weighted_average'] =
            ($classData['total_points'] / $classData['total_max_points']) * 100; else $classData['weighted_average'] = 0.0;
        // Release reference
        unset($classData);
    }
    // Release reference
    unset($subjectData);

    return $statistics;
}

/**
 * Gets absences for a student
 *
 * @param int $studentId Student ID
 * @return array Absence records
 */
function getStudentAbsences(int $studentId): array
{
    try {
        $pdo = safeGetDBConnection('getStudentAbsences');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentAbsences");
            return [];
        }

        $query = "
            SELECT a.att_id, a.status, a.justification, a.approved, a.reject_reason,
                   a.justification_file, p.period_date, p.period_label,
                   s.name as subject_name,
                   c.class_code, c.title as class_title
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN classes c ON cs.class_id = c.class_id
            WHERE e.student_id = :student_id
            AND a.status = 'A'
            ORDER BY p.period_date DESC, p.period_label";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return [];
    }
}

/**
 * Upload justification for an absence
 *
 * @param int $absenceId Absence ID
 * @param string $justification Justification text
 * @return bool Success status
 */
function uploadJustification(int $absenceId, string $justification): bool
{
    // Basic check for current user ID existence in session
    if (!isset($_SESSION['user_id'])) {
        // Log error or handle appropriately
        error_log("User ID not found in session during justification upload.");
        return false;
    }
    $currentUserId = $_SESSION['user_id'];

    try {
        $pdo = safeGetDBConnection('uploadJustification');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in uploadJustification");
            return false;
        }

        // Verify the current user (student) owns this absence record
        $verifyQuery = "
            SELECT a.att_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = :absence_id
            AND s.user_id = :user_id";

        $verifyStmt = $pdo->prepare($verifyQuery);
        $verifyStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);
        $verifyStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
        $verifyStmt->execute();

        if ($verifyStmt->rowCount() === 0) {
            // Log attempt to modify unowned record
            error_log("User $currentUserId attempted to upload justification for unowned absence ID $absenceId.");
            return false; // User does not own this absence record
        }

        // Proceed with update
        $updateQuery = "
            UPDATE attendance
            SET justification = :justification,
                approved = NULL, -- Reset approval status when new justification is submitted
                reject_reason = NULL -- Clear previous rejection reason
            WHERE att_id = :absence_id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':justification', $justification);
        $updateStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);

        return $updateStmt->execute();
    } catch (PDOException $e) {
        logDBError("PDOException in uploadJustification for absence ID $absenceId: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate justification file
 *
 * @param array $file Uploaded file data from $_FILES superglobal
 * @return bool Validation result
 */
function validateJustificationFile(array $file): bool
{
    // Check for upload errors
    if (!isset($file['error']) || is_array($file['error'])) {
        error_log("Invalid parameters received for file upload validation.");
        return false; // Invalid parameters
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break; // No error, continue validation
        case UPLOAD_ERR_NO_FILE:
            error_log("No file sent during justification upload.");
            return false; // Or handle as needed, maybe allow text-only justification
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            error_log("Exceeded filesize limit during justification upload.");
            return false;
        default:
            error_log("Unknown upload error: " . $file['error']);
            return false;
    }

    // Check file size (e.g., 5MB maximum)
    $maxSize = 5 * 1024 * 1024;
    if (!isset($file['size']) || $file['size'] > $maxSize) {
        error_log("File size exceeds limit ($maxSize bytes) or size not available.");
        return false;
    }
    if ($file['size'] === 0) {
        error_log("Uploaded file is empty.");
        return false;
    }

    // Check MIME type using "finfo" for better security than relying on extension or client-provided type
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif']; // Add/remove allowed types as needed
    if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        error_log("Temporary file path is missing or file does not exist.");
        return false;
    }

    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if ($mimeType === false) {
            error_log("Could not determine MIME type for uploaded file.");
            return false; // Could not determine type
        }

        if (!in_array($mimeType, $allowedTypes, true)) {
            error_log("Invalid file type uploaded: " . $mimeType);
            return false; // Disallowed type
        }
    } catch (Exception $e) {
        error_log("Error checking file MIME type: " . $e->getMessage());
        return false;
    }

    // Optional: Add more checks like file name sanitization if needed before saving

    return true; // File is valid
}

/**
 * Save uploaded justification file
 *
 * @param array $file Uploaded file data from $_FILES
 * @param int $absenceId Absence ID
 * @return bool|string Returns the saved filename on success, false on failure.
 */
function saveJustificationFile(array $file, int $absenceId): bool|string
{
    // First, validate the file
    if (!validateJustificationFile($file)) {
        error_log("Justification file validation failed for absence ID $absenceId.");
        return false;
    }

    // Check for current user ID existence in session
    if (!isset($_SESSION['user_id'])) {
        error_log("User ID not found in session during justification file save.");
        return false;
    }
    $currentUserId = $_SESSION['user_id'];

    try {
        $pdo = safeGetDBConnection('saveJustificationFile');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in saveJustificationFile");
            return false;
        }

        // Verify the current user (student) owns this absence record
        $verifyQuery = "
            SELECT a.att_id, a.justification_file -- Select existing file to potentially delete later
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = :absence_id
            AND s.user_id = :user_id";

        $verifyStmt = $pdo->prepare($verifyQuery);
        $verifyStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);
        $verifyStmt->bindParam(':user_id', $currentUserId, PDO::PARAM_INT);
        $verifyStmt->execute();
        $attendanceRecord = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendanceRecord) {
            error_log("User $currentUserId attempted to save justification file for unowned absence ID $absenceId.");
            return false; // User does not own this absence record
        }

        // Define upload directory relative to *this* script's location
        // Correct path should be relative to the project root or an absolute path
        // Assuming project root is one level up from 'student' directory
        $projectRoot = dirname(__DIR__); // Gets /path/to/uwuweb
        $uploadDir = $projectRoot . '/uploads/justifications/';

        // Ensure the upload directory exists and is writable
        if (!is_dir($uploadDir)) if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            error_log(sprintf('Failed to create upload directory: "%s"', $uploadDir));
            return false; // Directory creation failed
        }
        if (!is_writable($uploadDir)) {
            error_log(sprintf('Upload directory is not writable: "%s"', $uploadDir));
            return false;
        }

        // Generate a unique filename to prevent overwrites and potential security issues
        // Use original extension, but sanitize it
        $originalName = $file['name'];
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        // Basic sanitization for extension (allow only alphanumeric)
        $safeExtension = preg_replace('/[^a-z0-9]/', '', $fileExtension);
        if (empty($safeExtension)) $safeExtension = 'bin'; // Default if extension is weird

        $newFilename = 'justification_' . $absenceId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExtension;
        $targetPath = $uploadDir . $newFilename;

        // Move the uploaded file from tmp location to the target path
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            error_log("Failed to move uploaded file to $targetPath. Check permissions and paths.");
            return false; // File move failed
        }

        // --- Optional: Delete old file if it exists ---
        $oldFilename = $attendanceRecord['justification_file'];
        if (!empty($oldFilename)) {
            $oldFilePath = $uploadDir . $oldFilename;
            if (file_exists($oldFilePath)) unlink($oldFilePath);
        }
        // --- End Optional Delete ---

        // Update the database record with the new filename
        $updateQuery = "
            UPDATE attendance
            SET justification_file = :filename,
                approved = NULL, -- Reset approval status
                reject_reason = NULL -- Clear rejection reason
            WHERE att_id = :absence_id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':filename', $newFilename);
        $updateStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);

        if ($updateStmt->execute()) return $newFilename; else {
            error_log("Database update failed after saving justification file $newFilename for absence ID $absenceId.");
            // Attempt to delete the newly saved file if DB update fails to prevent orphans
            if (file_exists($targetPath)) unlink($targetPath);
            return false; // DB update failed
        }

    } catch (PDOException $e) {
        logDBError("PDOException in saveJustificationFile for absence ID $absenceId: " . $e->getMessage());
        // Attempt to delete the file if it was moved before the exception
        if (isset($targetPath) && file_exists($targetPath)) unlink($targetPath);
        return false;
    } catch (Exception $e) { // Catch other potential errors like random_bytes failure
        error_log("General Exception in saveJustificationFile for absence ID $absenceId: " . $e->getMessage());
        if (isset($targetPath) && file_exists($targetPath)) unlink($targetPath);
        return false;
    }
}

/**
 * Get justification file information (filename)
 *
 * @param int $absenceId Absence ID
 * @return string|null Filename or null if no file or access denied
 */
function getJustificationFileInfo(int $absenceId): ?string
{
    try {
        $pdo = safeGetDBConnection('getJustificationFileInfo');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getJustificationFileInfo");
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT justification_file
            FROM attendance
            WHERE att_id = ?
        ");
        $stmt->execute([$absenceId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && isset($result['justification_file']) && $result['justification_file'] ? $result['justification_file'] : null;
    } catch (PDOException $e) {
        logDBError("Error in getJustificationFileInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Gets justifications submitted by a specific student
 *
 * @param int $studentId The ID of the student whose justifications are requested.
 * @return array Array of justification records, potentially empty.
 */
function getStudentJustifications(int $studentId): array
{
    try {
        $pdo = safeGetDBConnection('getStudentJustifications');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentJustifications");
            return [];
        }

        // Query to retrieve justification details linked to the student
        $query = "
            SELECT
                a.att_id as justification_id, -- Alias for clarity
                a.justification as justification_text, -- Alias for clarity
                a.justification_file,
                a.approved,
                a.reject_reason,
                p.period_date as absence_date,
                p.period_label as absence_period_label, -- More specific alias
                s.name as subject_name,
                c.title as class_title,
                c.class_code,
                -- Determine submission date (could be complex, using period date as proxy)
                p.period_date as submitted_date_proxy -- Indicate this is an approximation
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN classes c ON cs.class_id = c.class_id -- Changed join condition to class_subjects
            WHERE e.student_id = :student_id
              AND (a.justification IS NOT NULL OR a.justification_file IS NOT NULL) -- Ensure there's a justification
            ORDER BY p.period_date DESC, p.period_label"; // Order by date, then period label

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all matching records

    } catch (PDOException $e) {
        // Log the database error for debugging
        logDBError("PDOException in getStudentJustifications for student ID $studentId: " . $e->getMessage());
        // Return an empty array in case of error to maintain consistent return type
        return [];
    }
}

/**
 * Creates the HTML for the student's grades dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderStudentGradesWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz ocen se morate identificirati kot dijak.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    try {
        $queryRecent = "SELECT
                    g.grade_id, g.points, gi.max_points, gi.name AS grade_item_name,
                    s.name AS subject_name, g.comment,
                    CASE WHEN gi.max_points > 0 THEN ROUND((g.points / gi.max_points) * 100, 1) END AS percentage,
                    g.grade_id AS date_added
                  FROM grades g
                  JOIN grade_items gi ON g.item_id = gi.item_id
                  JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  JOIN enrollments e ON g.enroll_id = e.enroll_id
                  WHERE e.student_id = :student_id
                  ORDER BY g.grade_id DESC
                  LIMIT 5";

        $stmtRecent = $db->prepare($queryRecent);
        $stmtRecent->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmtRecent->execute();
        $recentGrades = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        $queryAvg = "SELECT
                    s.subject_id, s.name AS subject_name,
                    AVG(CASE WHEN g.points IS NOT NULL AND gi.max_points > 0
                         THEN (g.points / gi.max_points) * 100
                         END) AS avg_score,
                    COUNT(g.grade_id) AS grade_count
                  FROM enrollments e
                  JOIN classes c ON e.class_id = c.class_id
                  JOIN class_subjects cs ON c.class_id = cs.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN grade_items gi ON gi.class_subject_id = cs.class_subject_id
                  LEFT JOIN grades g ON gi.item_id = g.item_id AND e.enroll_id = g.enroll_id
                  WHERE e.student_id = :student_id
                  GROUP BY s.subject_id, s.name
                  HAVING COUNT(g.grade_id) > 0
                  ORDER BY avg_score DESC";

        $stmtAvg = $db->prepare($queryAvg);
        $stmtAvg->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmtAvg->execute();
        $subjectAverages = $stmtAvg->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderStudentGradesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o ocenah.');
    }

    $html = '<div class="widget-content grades-widget card card__content">';

    $html .= '<div class="row gap-lg">';

    $html .= '<div class="grades-section subject-averages col-12 col-md-5">';
    $html .= '<h5 class="mb-md font-medium">Povprečja predmetov</h5>';

    if (empty($subjectAverages)) $html .= '<div class="text-center p-md"><p class="m-0 text-secondary">Ni podatkov o povprečjih predmetov.</p></div>'; else {
        $html .= '<div class="averages-list d-flex flex-column gap-sm">';
        foreach ($subjectAverages as $subject) {
            if ($subject['avg_score'] === null) continue;

            $avgScore = number_format($subject['avg_score'], 1);
            $gradeCount = (int)$subject['grade_count'];
            $gradeSuffix = match (true) {
                $gradeCount == 1 => 'ocena',
                $gradeCount >= 2 && $gradeCount <= 4 => 'oceni',
                default => 'ocen'
            };

            $scoreClass = match (true) {
                $subject['avg_score'] >= 80 => 'grade-high',
                $subject['avg_score'] >= 60 => 'grade-medium',
                default => 'grade-low'
            };

            $html .= '<div class="subject-average-item d-flex justify-between items-center p-sm rounded bg-secondary shadow-sm">';
            $html .= '<div class="subject-info flex-grow-1">';
            $html .= '<div class="subject-name font-medium">' . htmlspecialchars($subject['subject_name']) . '</div>';
            $html .= '<div class="subject-grade-count text-xs text-disabled">' . $gradeCount . ' ' . $gradeSuffix . '</div>';
            $html .= '</div>';
            $html .= '<div class="subject-average badge ' . $scoreClass . '">' . $avgScore . '%</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '<div class="grades-section recent-grades col-12 col-md-7">';
    $html .= '<h5 class="mb-md font-medium">Nedavne ocene</h5>';

    if (empty($recentGrades)) $html .= '<div class="text-center p-md"><p class="m-0 text-secondary">Nimate nedavnih ocen.</p></div>'; else {
        $html .= '<div class="recent-grades-list d-flex flex-column gap-md">';
        foreach ($recentGrades as $grade) {
            $percentage = $grade['percentage'];
            $scoreClass = 'badge-secondary';
            if ($percentage !== null) $scoreClass = match (true) {
                $percentage >= 80 => 'grade-high',
                $percentage >= 60 => 'grade-medium',
                default => 'grade-low'
            };
            $percentageFormatted = $percentage !== null ? '(' . htmlspecialchars($percentage) . '%)' : '';

            $html .= '<div class="grade-item p-md rounded bg-secondary shadow-sm">';
            $html .= '<div class="grade-header d-flex justify-between items-center mb-sm">';
            $html .= '<div class="grade-subject font-medium">' . htmlspecialchars($grade['subject_name']) . '</div>';
            $html .= '<div class="grade-score badge ' . $scoreClass . '">' .
                htmlspecialchars($grade['points']) . '/' .
                htmlspecialchars($grade['max_points']) . ' ' . $percentageFormatted . '</div>';
            $html .= '</div>';

            $html .= '<div class="grade-details">';
            $html .= '<div class="grade-name text-sm">' . htmlspecialchars($grade['grade_item_name']) . '</div>';
            if (!empty($grade['comment'])) $html .= '<div class="grade-comment text-sm text-secondary fst-italic mt-xs">"' . htmlspecialchars($grade['comment']) . '"</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</div>';

    $html .= '<div class="widget-footer mt-lg text-right border-top pt-md">';
    $html .= '<a href="/uwuweb/student/grades.php" class="btn btn-sm btn-primary">Ogled vseh ocen</a>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}

/**
 * Creates the HTML for the student's attendance dashboard widget, showing attendance statistics and recent attendance records
 *
 * @return string HTML content for the widget
 */
function renderStudentAttendanceWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz prisotnosti se morate identificirati kot dijak.');

    try {
        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

        $stats = [
            'total' => 0, 'present' => 0, 'late' => 0,
            'justified' => 0, 'rejected' => 0, 'pending' => 0, 'needs_justification' => 0,
            'unjustified' => 0, 'attendance_rate' => 0, 'recent' => []
        ];

        $query = "SELECT
            COUNT(*) as total,
            SUM(IF(status = 'P', 1, 0)) as present,
            SUM(IF(status = 'A', 1, 0)) as absent,
            SUM(IF(status = 'L', 1, 0)) as late,
            SUM(IF(status = 'A' AND approved = 1, 1, 0)) as justified,
            SUM(IF(status = 'A' AND approved = 0, 1, 0)) as rejected,
            SUM(IF(status = 'A' AND justification IS NOT NULL AND approved IS NULL, 1, 0)) as pending,
            SUM(IF(status = 'A' AND justification IS NULL AND approved IS NULL, 1, 0)) as needs_justification
         FROM attendance a
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         WHERE e.student_id = :student_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $stats['total'] = (int)$result['total'];
            $stats['present'] = (int)$result['present'];
            $stats['late'] = (int)$result['late'];
            $stats['justified'] = (int)$result['justified'];
            $stats['rejected'] = (int)$result['rejected'];
            $stats['pending'] = (int)$result['pending'];
            $stats['needs_justification'] = (int)$result['needs_justification'];
            $stats['unjustified'] = $stats['needs_justification'] + $stats['rejected'];

            if ($stats['total'] > 0) $stats['attendance_rate'] = round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1);
        }

        $recentQuery = "SELECT
            a.att_id,
            a.status,
            a.justification,
            a.approved,
            p.period_date,
            p.period_label,
            s.name as subject_name
         FROM attendance a
         JOIN periods p ON a.period_id = p.period_id
         JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
         JOIN subjects s ON cs.subject_id = s.subject_id
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         WHERE e.student_id = :student_id
         ORDER BY p.period_date DESC, p.period_label DESC
         LIMIT 5";

        $stmt = $db->prepare($recentQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="widget-content attendance-widget card card__content">';

        $html .= '<div class="attendance-summary row align-items-center gap-lg mb-lg">';

        $html .= '<div class="attendance-rate col-12 col-md-4 text-center">';
        $rateColorClass = match (true) {
            $stats['attendance_rate'] >= 95 => 'text-success',
            $stats['attendance_rate'] >= 85 => 'text-warning',
            default => 'text-error'
        };
        $html .= '<div class="rate-circle mx-auto" data-percentage="' . $stats['attendance_rate'] . '" style="width: 100px; height: 100px;">';
        $html .= '<svg viewBox="0 0 36 36" class="circular-chart ' . $rateColorClass . '">';
        $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<path class="circle" stroke-dasharray="' . $stats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<text x="18" y="20.35" class="percentage">' . $stats['attendance_rate'] . '%</text>';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="rate-label text-sm text-secondary mt-sm">Skupna prisotnost</div>';
        $html .= '</div>';

        $html .= '<div class="attendance-breakdown col-12 col-md-7">';
        $html .= '<div class="row">';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-success">' . $stats['present'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Prisoten</span>';
        $html .= '</div>';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-warning">' . $stats['late'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Zamuda</span>';
        $html .= '</div>';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-info">' . $stats['justified'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Opravičeno</span>';
        $html .= '</div>';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-error">' . $stats['unjustified'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Neopravičeno</span>';
        $html .= '</div>';
        $html .= '</div>';
        if ($stats['pending'] > 0) {
            $html .= '<div class="row mt-sm">';
            $html .= '<div class="col-12 text-center"><span class="text-sm text-secondary">V obdelavi: ' . $stats['pending'] . '</span></div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</div>';

        if (!empty($stats['recent'])) {
            $html .= '<div class="recent-attendance border-top pt-lg">';
            $html .= '<h5 class="mb-md font-medium">Nedavna evidenca</h5>';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="mini-table data-table w-100 text-sm">';
            $html .= '<thead><tr><th>Datum</th><th>Predmet</th><th class="text-center">Status</th><th class="text-center">Opravičilo</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($stats['recent'] as $record) {
                $date = date('d.m.Y', strtotime($record['period_date']));
                $classInfo = htmlspecialchars($record['subject_name']) . ' (' . htmlspecialchars($record['period_label']) . '. ura)';

                $statusBadge = '';
                $justificationHtml = '<span class="text-disabled">-</span>';

                switch ($record['status']) {
                    case 'P':
                        $statusBadge = '<span class="badge status-present">Prisoten</span>';
                        break;
                    case 'L':
                        $statusBadge = '<span class="badge status-late">Zamuda</span>';
                        break;
                    case 'A':
                        $statusBadge = '<span class="badge status-absent">Odsoten</span>';
                        if ($record['approved'] === 1) $justificationHtml = '<span class="badge badge-success">Opravičeno</span>'; elseif ($record['approved'] === 0) $justificationHtml = '<span class="badge badge-error">Zavrnjeno</span>';
                        elseif ($record['justification'] !== null && $record['approved'] === null) $justificationHtml = '<span class="badge badge-warning">V obdelavi</span>';
                        else $justificationHtml = '<a href="/uwuweb/student/justification.php?att_id=' . $record['att_id'] . '" class="btn btn-xs btn-warning d-inline-flex items-center gap-xs"><span class="material-icons-outlined text-xs">edit</span> Oddaj</a>';
                        break;
                }

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($date) . '</td>';
                $html .= '<td>' . $classInfo . '</td>';
                $html .= '<td class="text-center">' . $statusBadge . '</td>';
                $html .= '<td class="text-center">' . $justificationHtml . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<div class="widget-footer d-flex justify-between items-center mt-lg border-top pt-md">';
        $html .= '<a href="/uwuweb/student/attendance.php" class="btn btn-sm btn-secondary">Celotna evidenca</a>';
        if ($stats['needs_justification'] > 0) $html .= '<a href="/uwuweb/student/justification.php" class="btn btn-sm btn-primary">Oddaj opravičilo (' . $stats['needs_justification'] . ')</a>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;

    } catch (PDOException $e) {
        error_log("Database error in renderStudentAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o prisotnosti.');
    } catch (Exception $e) {
        error_log("Error in renderStudentAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Prišlo je do napake.');
    }
}

/**
 * Creates the HTML for the student's class averages dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderStudentClassAveragesWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz povprečij razredov se morate identificirati kot dijak.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    try {
        $query = "SELECT
                    s.subject_id,
                    s.name AS subject_name,
                    c.class_id,
                    c.title AS class_title,
                    cs.class_subject_id,
                    AVG(
                        CASE WHEN g_student.points IS NOT NULL AND gi_student.max_points > 0
                        THEN (g_student.points / gi_student.max_points) * 100
                        END
                    ) AS student_avg_score,
                    (SELECT AVG(
                        CASE WHEN gi_class.max_points > 0
                        THEN (g_class.points / gi_class.max_points) * 100
                        END
                    )
                    FROM enrollments e_class
                    JOIN grades g_class ON e_class.enroll_id = g_class.enroll_id
                    JOIN grade_items gi_class ON g_class.item_id = gi_class.item_id
                    WHERE gi_class.class_subject_id = cs.class_subject_id
                    ) AS class_avg_score
                  FROM students st
                  JOIN enrollments e ON st.student_id = e.student_id AND e.student_id = :student_id
                  JOIN classes c ON e.class_id = c.class_id
                  JOIN class_subjects cs ON c.class_id = cs.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN grade_items gi_student ON gi_student.class_subject_id = cs.class_subject_id
                  LEFT JOIN grades g_student ON g_student.item_id = gi_student.item_id AND e.enroll_id = g_student.enroll_id
                  GROUP BY s.subject_id, s.name, c.class_id, c.title, cs.class_subject_id
                  ORDER BY s.name, c.title";


        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $studentGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderStudentClassAveragesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o povprečjih.');
    }

    $html = '<div class="widget-content card card__content p-0">';

    if (empty($studentGrades) || !array_filter($studentGrades, static fn($g) => $g['student_avg_score'] !== null)) $html .= '<div class="text-center p-md"><p class="m-0">Nimate razredov z ocenami.</p></div>'; else {
        $html .= '<div class="student-averages-table table-responsive">';
        $html .= '<table class="data-table w-100">';
        $html .= '<thead><tr><th>Predmet</th><th class="text-center">Vaše povprečje</th><th class="text-center">Povprečje razreda</th><th class="text-center">Primerjava</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($studentGrades as $grade) {
            if ($grade['student_avg_score'] === null && $grade['class_avg_score'] === null) continue;

            $studentAvgFormatted = $grade['student_avg_score'] !== null ? number_format($grade['student_avg_score'], 1) . '%' : 'N/A';
            $classAvgFormatted = $grade['class_avg_score'] !== null ? number_format($grade['class_avg_score'], 1) . '%' : 'N/A';

            $scoreClass = '';
            $comparisonText = '-';
            $comparisonClass = 'text-secondary';

            if ($grade['student_avg_score'] !== null) {
                if ($grade['student_avg_score'] >= 80) $scoreClass = 'grade-high'; elseif ($grade['student_avg_score'] >= 60) $scoreClass = 'grade-medium';
                else $scoreClass = 'grade-low';

                if ($grade['class_avg_score'] !== null) {
                    $diff = $grade['student_avg_score'] - $grade['class_avg_score'];
                    $diffFormatted = number_format($diff, 1);

                    if ($diff > 5) {
                        $comparisonText = '+' . $diffFormatted . '%';
                        $comparisonClass = 'text-success';
                    } elseif ($diff < -5) {
                        $comparisonText = $diffFormatted . '%';
                        $comparisonClass = 'text-error';
                    } else $comparisonText = '≈';
                }
            }

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($grade['subject_name']) . '<br><small class="text-disabled">' . htmlspecialchars($grade['class_title']) . '</small></td>';
            $html .= '<td class="text-center ' . $scoreClass . '">' . $studentAvgFormatted . '</td>';
            $html .= '<td class="text-center">' . $classAvgFormatted . '</td>';
            $html .= '<td class="text-center ' . $comparisonClass . '">' . $comparisonText . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    }

    $html .= '<div class="widget-footer mt-md text-right border-top pt-md p-md">';
    $html .= '<a href="/uwuweb/student/grades.php" class="btn btn-sm btn-primary">Ogled vseh ocen</a>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}

/**
 * Creates the HTML for a student's upcoming classes widget
 *
 * @return string HTML content for the widget
 */
function renderUpcomingClassesWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz prihajajočih ur se morate identificirati kot dijak.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    $today = date('Y-m-d');
    $oneWeekLater = date('Y-m-d', strtotime('+7 days'));

    try {
        $query = "SELECT
                    p.period_id,
                    p.period_date,
                    p.period_label,
                    s.name AS subject_name,
                    t_user.username AS teacher_name,
                    c.title AS class_title
                  FROM periods p
                  JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  JOIN classes c ON cs.class_id = c.class_id
                  JOIN teachers t ON cs.teacher_id = t.teacher_id
                  JOIN users t_user ON t.user_id = t_user.user_id
                  JOIN enrollments e ON cs.class_id = e.class_id
                  WHERE e.student_id = :student_id
                    AND p.period_date BETWEEN :today AND :one_week_later
                  ORDER BY p.period_date, p.period_label
                  LIMIT 10";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':today', $today);
        $stmt->bindParam(':one_week_later', $oneWeekLater);
        $stmt->execute();

        $upcomingClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderUpcomingClassesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o prihajajočih urah.');
    }

    $html = '<div class="widget-content card card__content p-0">';

    if (empty($upcomingClasses)) $html .= '<div class="text-center p-md"><p class="m-0">Ni prihajajočih ur v naslednjem tednu.</p></div>'; else {
        $currentDay = '';
        $html .= '<div class="upcoming-classes p-md">';

        foreach ($upcomingClasses as $class) {
            $classDate = date('Y-m-d', strtotime($class['period_date']));
            $formattedDate = date('d.m.Y', strtotime($class['period_date']));
            $dayName = match (date('N', strtotime($class['period_date']))) {
                '1' => 'Ponedeljek',
                '2' => 'Torek',
                '3' => 'Sreda',
                '4' => 'Četrtek',
                '5' => 'Petek',
                '6' => 'Sobota',
                '7' => 'Nedelja',
                default => ''
            };

            if ($classDate != $currentDay) {
                if ($currentDay != '') {
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $currentDay = $classDate;
                $html .= '<div class="day-group mb-lg">';
                $html .= '<div class="day-header border-bottom pb-sm mb-md">';
                $html .= '<h5 class="m-0 font-medium">' . $dayName . ', ' . $formattedDate . '</h5>';
                $html .= '</div>';
                $html .= '<div class="day-classes d-flex flex-column gap-md">';
            }

            $html .= '<div class="class-item d-flex gap-md p-sm rounded bg-secondary shadow-sm">';
            $html .= '<div class="class-time font-medium text-center p-sm bg-tertiary rounded" style="min-width: 60px;">' . htmlspecialchars($class['period_label']) . '. ura</div>';
            $html .= '<div class="class-details flex-grow-1">';
            $html .= '<div class="class-subject font-medium d-block">' . htmlspecialchars($class['subject_name']) . '</div>';
            $html .= '<div class="class-teacher text-sm text-secondary">Profesor: ' . htmlspecialchars($class['teacher_name']) . '</div>';
            $html .= '<div class="class-room text-sm text-secondary">Razred: ' . htmlspecialchars($class['class_title']) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        if ($currentDay != '') {
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}
