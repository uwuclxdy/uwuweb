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
 * - saveJustificationFile(array $file, int $absenceId): bool|string - Stores justification file securely
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
    if (!$studentId) return renderPlaceholderWidget('Za prikaz ocen se morate identificirati.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    // Fetch data (simplified, assuming original queries are correct)
    $queryRecent = "SELECT g.points, gi.max_points, gi.name AS grade_item_name, s.name AS subject_name, g.comment, CASE WHEN gi.max_points > 0 THEN ROUND((g.points / gi.max_points) * 100, 1) END AS percentage FROM grades g JOIN grade_items gi ON g.item_id = gi.item_id JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id JOIN subjects s ON cs.subject_id = s.subject_id JOIN enrollments e ON g.enroll_id = e.enroll_id WHERE e.student_id = :student_id ORDER BY g.grade_id DESC LIMIT 3";
    $stmtRecent = $db->prepare($queryRecent);
    $stmtRecent->bindParam(':student_id', $studentId, PDO::PARAM_INT);
    $stmtRecent->execute();
    $recentGrades = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    $queryAvg = "SELECT s.name AS subject_name, AVG(CASE WHEN g.points IS NOT NULL AND gi.max_points > 0 THEN (g.points / gi.max_points) * 100 END) AS avg_score, COUNT(g.grade_id) AS grade_count FROM enrollments e JOIN class_subjects cs ON e.class_id = cs.class_id JOIN subjects s ON cs.subject_id = s.subject_id LEFT JOIN grade_items gi ON gi.class_subject_id = cs.class_subject_id LEFT JOIN grades g ON gi.item_id = g.item_id AND e.enroll_id = g.enroll_id WHERE e.student_id = :student_id GROUP BY s.subject_id, s.name HAVING COUNT(g.grade_id) > 0 ORDER BY avg_score DESC LIMIT 5";
    $stmtAvg = $db->prepare($queryAvg);
    $stmtAvg->bindParam(':student_id', $studentId, PDO::PARAM_INT);
    $stmtAvg->execute();
    $subjectAverages = $stmtAvg->fetchAll(PDO::FETCH_ASSOC);

    $html = '<div class="d-flex flex-column h-full">'; // Main widget container

    // Combined section for averages and recent grades, scrollable
    $html .= '<div class="rounded p-0 shadow-sm flex-grow-1 d-flex flex-column">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Pregled ocen</h5>';
    $html .= '<div class="p-md flex-grow-1" style="overflow-y: auto;">'; // Scrollable content

    // Subject Averages
    $html .= '<div class="mb-lg">';
    $html .= '<h6 class="font-medium mb-sm">Povprečja po predmetih</h6>';
    if (empty($subjectAverages)) $html .= '<p class="text-secondary text-sm">Ni podatkov o povprečjih.</p>'; else {
        $html .= '<div class="d-flex flex-column gap-sm">';
        foreach ($subjectAverages as $avg) {
            if ($avg['avg_score'] === null) continue;
            $score = number_format($avg['avg_score'], 1);
            $sClass = $avg['avg_score'] >= 80 ? 'grade-high' : ($avg['avg_score'] >= 60 ? 'grade-medium' : 'grade-low');
            $html .= '<div class="d-flex justify-between items-center p-sm rounded shadow-sm">';
            $html .= '<span>' . htmlspecialchars($avg['subject_name']) . ' <small class="text-disabled">(' . $avg['grade_count'] . ' ocen)</small></span>';
            $html .= '<span class="badge ' . $sClass . '">' . $score . '%</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    // Recent Grades
    $html .= '<div>';
    $html .= '<h6 class="font-medium mb-sm">Najnovejše ocene</h6>';
    if (empty($recentGrades)) $html .= '<p class="text-secondary text-sm">Ni nedavnih ocen.</p>'; else {
        $html .= '<div class="d-flex flex-column gap-md">';
        foreach ($recentGrades as $grade) {
            $perc = $grade['percentage'];
            $sClass = $perc === null ? 'badge-secondary' : ($perc >= 80 ? 'grade-high' : ($perc >= 60 ? 'grade-medium' : 'grade-low'));
            $percFormatted = $perc !== null ? ' (' . $perc . '%)' : '';
            $html .= '<div class="p-sm rounded shadow-sm">';
            $html .= '<div class="d-flex justify-between items-center mb-xs">';
            $html .= '<span class="font-medium">' . htmlspecialchars($grade['subject_name']) . '</span>';
            $html .= '<span class="badge ' . $sClass . '">' . htmlspecialchars($grade['points']) . '/' . htmlspecialchars($grade['max_points']) . $percFormatted . '</span>';
            $html .= '</div>';
            $html .= '<div class="text-sm">' . htmlspecialchars($grade['grade_item_name']) . '</div>';
            if (!empty($grade['comment'])) $html .= '<div class="text-xs text-secondary mt-xs fst-italic">"' . htmlspecialchars($grade['comment']) . '"</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</div>'; // end scrollable content
    $html .= '</div>'; // end rounded shadow section

    $html .= '<div class="mt-auto text-right border-top pt-md p-md">';
    $html .= '<a href="/uwuweb/student/grades.php" class="btn btn-sm btn-primary">Vse ocene</a>';
    $html .= '</div>';
    $html .= '</div>'; // end main widget container
    return $html;
}


/**
 * Creates the HTML for the student's attendance dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderStudentAttendanceWidget(): string
{
    $studentId = getStudentId();
    if (!$studentId) return renderPlaceholderWidget('Za prikaz prisotnosti se morate identificirati.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    // Fetch stats (simplified)
    $query = "SELECT COUNT(*) as total, SUM(IF(status = 'P', 1, 0)) as present, SUM(IF(status = 'A', 1, 0)) as absent, SUM(IF(status = 'L', 1, 0)) as late, SUM(IF(status = 'A' AND approved = 1, 1, 0)) as justified, SUM(IF(status = 'A' AND justification IS NOT NULL AND approved IS NULL, 1, 0)) as pending, SUM(IF(status = 'A' AND approved = 0, 1, 0)) as rejected, SUM(IF(status = 'A' AND justification IS NULL AND approved IS NULL, 1, 0)) as needs_justification FROM attendance a JOIN enrollments e ON a.enroll_id = e.enroll_id WHERE e.student_id = :student_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
    $stmt->execute();
    $s = $stmt->fetch(PDO::FETCH_ASSOC);
    $s['unjustified'] = ($s['needs_justification'] ?? 0) + ($s['rejected'] ?? 0);
    $s['attendance_rate'] = ($s['total'] ?? 0) > 0 ? round((($s['present'] ?? 0) + ($s['late'] ?? 0)) / $s['total'] * 100, 1) : 0;

    $recentQuery = "SELECT a.att_id, a.status, a.justification, a.approved, p.period_date, p.period_label, subj.name as subject_name FROM attendance a JOIN periods p ON a.period_id = p.period_id JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id JOIN subjects subj ON cs.subject_id = subj.subject_id JOIN enrollments e ON a.enroll_id = e.enroll_id WHERE e.student_id = :student_id ORDER BY p.period_date DESC, p.period_label DESC LIMIT 5";
    $stmtRecent = $db->prepare($recentQuery);
    $stmtRecent->bindParam(':student_id', $studentId, PDO::PARAM_INT);
    $stmtRecent->execute();
    $recentRecords = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

    $html = '<div class="d-flex flex-column h-full">';

    // Attendance Summary Section
    $html .= '<div class="rounded p-0 shadow-sm mb-lg">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Povzetek Prisotnosti</h5>';
    $html .= '<div class="p-md">';
    $html .= '<div class="row items-center gap-md">';
    $html .= '<div class="col-12 col-md-4 text-center mb-md md-mb-0">'; // mb-md for mobile, md-mb-0 for larger
    $rateColor = $s['attendance_rate'] >= 95 ? 'text-success' : ($s['attendance_rate'] >= 85 ? 'text-warning' : 'text-error');
    $html .= '<div class="font-size-xxl font-bold ' . $rateColor . '">' . $s['attendance_rate'] . '%</div>';
    $html .= '<div class="text-sm text-secondary">Skupna prisotnost</div>';
    $html .= '</div>';
    $html .= '<div class="col-12 col-md-7">';
    $html .= '<div class="row text-center text-sm">';
    $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . ($s['present'] ?? 0) . '</span><span class="text-secondary">Prisoten</span></div>';
    $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . ($s['late'] ?? 0) . '</span><span class="text-secondary">Zamuda</span></div>';
    $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . ($s['justified'] ?? 0) . '</span><span class="text-secondary">Opravičeno</span></div>';
    $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . (max($s['unjustified'], 0)) . '</span><span class="text-secondary">Neopravičeno</span></div>';
    $html .= '</div>';
    if (($s['pending'] ?? 0) > 0) $html .= '<div class="text-center text-xs text-warning mt-xs">V obdelavi: ' . $s['pending'] . '</div>';
    $html .= '</div></div></div></div>';

    // Recent Attendance Section (Expanding)
    $html .= '<div class="rounded p-0 shadow-sm flex-grow-1 d-flex flex-column">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Najnovejši vnosi</h5>';
    $html .= '<div class="p-0 flex-grow-1" style="overflow-y: auto;">'; // p-0 for table-responsive
    if (empty($recentRecords)) $html .= '<p class="p-md text-secondary text-center">Ni nedavnih vnosov.</p>'; else {
        $html .= '<div class="table-responsive">';
        $html .= '<table class="data-table w-100 text-sm">';
        $html .= '<thead><tr><th>Datum</th><th>Predmet (ura)</th><th class="text-center">Status</th><th class="text-center">Opravičilo</th></tr></thead><tbody>';
        foreach ($recentRecords as $rec) {
            $statusBadge = getAttendanceStatusLabel($rec['status']); // This needs to return HTML badge
            $justHtml = '<span class="text-disabled">-</span>';
            if ($rec['status'] == 'A') if ($rec['approved'] === 1) $justHtml = '<span class="badge badge-success">Opravičeno</span>';
            elseif ($rec['approved'] === 0) $justHtml = '<span class="badge badge-error">Zavrnjeno</span>';
            elseif ($rec['justification'] !== null) $justHtml = '<span class="badge badge-warning">V obdelavi</span>';
            else $justHtml = '<a href="/uwuweb/student/justification.php?att_id=' . $rec['att_id'] . '" class="btn btn-xs btn-warning d-inline-flex items-center gap-xs"><span class="material-icons-outlined text-xs">edit</span> Oddaj</a>';
            $html .= '<tr><td>' . date('d.m.Y', strtotime($rec['period_date'])) . '</td><td>' . htmlspecialchars($rec['subject_name']) . ' (' . htmlspecialchars($rec['period_label']) . ')</td><td class="text-center">' . $statusBadge . '</td><td class="text-center">' . $justHtml . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    $html .= '</div></div>';

    $html .= '<div class="d-flex justify-between items-center mt-auto border-top pt-md p-md">';
    $html .= '<a href="/uwuweb/student/attendance.php" class="btn btn-sm btn-secondary">Celotna evidenca</a>';
    if (($s['needs_justification'] ?? 0) > 0) $html .= '<a href="/uwuweb/student/justification.php" class="btn btn-sm btn-primary">Oddaj opravičilo (' . $s['needs_justification'] . ')</a>';
    $html .= '</div></div>';
    return $html;
}

/**
 * Creates the HTML for the student's class averages dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderStudentClassAveragesWidget(): string
{
    $studentId = getStudentId();
    if (!$studentId) return renderPlaceholderWidget('Za prikaz povprečij se morate identificirati.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    // Fetch grades (simplified)
    $query = "SELECT s.name AS subject_name, c.title AS class_title, cs.class_subject_id, AVG(CASE WHEN g_student.points IS NOT NULL AND gi_student.max_points > 0 THEN (g_student.points / gi_student.max_points) * 100 END) AS student_avg_score, (SELECT AVG(CASE WHEN gi_class.max_points > 0 THEN (g_class.points / gi_class.max_points) * 100 END) FROM enrollments e_class JOIN grades g_class ON e_class.enroll_id = g_class.enroll_id JOIN grade_items gi_class ON g_class.item_id = gi_class.item_id WHERE gi_class.class_subject_id = cs.class_subject_id) AS class_avg_score FROM students st JOIN enrollments e ON st.student_id = e.student_id AND e.student_id = :student_id JOIN classes c ON e.class_id = c.class_id JOIN class_subjects cs ON c.class_id = cs.class_id JOIN subjects s ON cs.subject_id = s.subject_id LEFT JOIN grade_items gi_student ON gi_student.class_subject_id = cs.class_subject_id LEFT JOIN grades g_student ON g_student.item_id = gi_student.item_id AND e.enroll_id = g_student.enroll_id GROUP BY s.subject_id, s.name, c.class_id, c.title, cs.class_subject_id ORDER BY s.name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
    $stmt->execute();
    $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<div class="d-flex flex-column h-full">';

    // Table Section (Expanding)
    $html .= '<div class="rounded p-0 shadow-sm flex-grow-1 d-flex flex-column">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Primerjava s povprečji razreda</h5>';
    $html .= '<div class="p-0 flex-grow-1" style="overflow-y: auto;">';
    if (empty($grades) || !array_filter($grades, static fn($g) => $g['student_avg_score'] !== null)) $html .= '<p class="p-md text-secondary text-center">Nimate razredov z ocenami za primerjavo.</p>'; else {
        $html .= '<div class="table-responsive">';
        $html .= '<table class="data-table w-100 text-sm">';
        $html .= '<thead><tr><th>Predmet (Razred)</th><th class="text-center">Vaše povp.</th><th class="text-center">Povp. razreda</th><th class="text-center">Razlika</th></tr></thead><tbody>';
        foreach ($grades as $grade) {
            if ($grade['student_avg_score'] === null && $grade['class_avg_score'] === null) continue;
            $sAvgF = $grade['student_avg_score'] !== null ? number_format($grade['student_avg_score'], 1) . '%' : 'N/A';
            $cAvgF = $grade['class_avg_score'] !== null ? number_format($grade['class_avg_score'], 1) . '%' : 'N/A';
            $sClass = '';
            $compText = '-';
            $compClass = 'text-secondary';
            if ($grade['student_avg_score'] !== null) {
                $sClass = $grade['student_avg_score'] >= 80 ? 'grade-high' : ($grade['student_avg_score'] >= 60 ? 'grade-medium' : 'grade-low');
                if ($grade['class_avg_score'] !== null) {
                    $diff = $grade['student_avg_score'] - $grade['class_avg_score'];
                    $diffF = number_format($diff, 1);
                    if ($diff > 2) {
                        $compText = '+' . $diffF . '%';
                        $compClass = 'text-success';
                    } elseif ($diff < -2) {
                        $compText = $diffF . '%';
                        $compClass = 'text-error';
                    } else $compText = '≈';
                }
            }
            $html .= '<tr><td>' . htmlspecialchars($grade['subject_name']) . '<br><small class="text-disabled">' . htmlspecialchars($grade['class_title']) . '</small></td><td class="text-center ' . $sClass . '">' . $sAvgF . '</td><td class="text-center">' . $cAvgF . '</td><td class="text-center ' . $compClass . '">' . $compText . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }
    $html .= '</div></div>';

    $html .= '<div class="mt-auto text-right border-top pt-md p-md">';
    $html .= '<a href="/uwuweb/student/grades.php" class="btn btn-sm btn-primary">Vse ocene</a>';
    $html .= '</div></div>';
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
    if (!$studentId) return renderPlaceholderWidget('Za prikaz prihajajočih ur se morate identificirati.');

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

    $html = '<div class="d-flex flex-column h-full">';
    $html .= '<div class="rounded p-0 shadow-sm flex-grow-1 d-flex flex-column">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Prihajajoče ure (naslednjih 7 dni)</h5>';
    $html .= '<div class="p-md flex-grow-1" style="overflow-y: auto;">';

    if (empty($upcomingClasses)) $html .= '<p class="text-secondary text-center">Ni prihajajočih ur v naslednjem tednu.</p>'; else {
        $groupedClasses = [];
        foreach ($upcomingClasses as $class) $groupedClasses[$class['period_date']][] = $class;
        $slovenianDays = ['Nedelja', 'Ponedeljek', 'Torek', 'Sreda', 'Četrtek', 'Petek', 'Sobota'];

        foreach ($groupedClasses as $date => $classesOnDate) {
            $dayName = $slovenianDays[date('w', strtotime($date))];
            $html .= '<div class="mb-lg">';
            $html .= '<div class="day-header border-bottom pb-xs mb-sm">';
            $html .= '<h6 class="m-0 font-medium">' . $dayName . ', ' . date('d.m.Y', strtotime($date)) . '</h6>';
            $html .= '</div>';
            $html .= '<div class="d-flex flex-column gap-md">';
            foreach ($classesOnDate as $class) {
                $html .= '<div class="d-flex gap-md p-sm rounded shadow-sm items-center">';
                $html .= '<div class="class-time font-medium text-center p-sm rounded bg-tertiary" style="min-width: 60px;">' . htmlspecialchars($class['period_label']) . '. ura</div>';
                $html .= '<div class="class-details flex-grow-1 text-sm">';
                $html .= '<div class="font-medium">' . htmlspecialchars($class['subject_name']) . '</div>';
                $html .= '<div class="text-secondary">Prof.: ' . htmlspecialchars($class['teacher_fname'] . ' ' . $class['teacher_lname']) . '</div>';
                $html .= '<div class="text-secondary">Razred: ' . htmlspecialchars($class['class_title']) . '</div>';
                $html .= '</div></div>';
            }
            $html .= '</div></div>';
        }
    }
    $html .= '</div></div></div>';
    return $html;
}
