<?php
/**
 * Student Functions Library
 *
 * Provides functions for retrieving and managing student grades, attendance,
 * and absence justifications. This file serves as the complete API for the
 * student module functionality.
 *
 * Available functions:
 * - getStudentAttendance(int $studentId): array - Retrieves attendance records for a student
 * - getStudentGrades(int $studentId): array - Retrieves grades for a student
 * - getClassAverage(int $classId): array - Retrieves class average for a specific class
 * - calculateWeightedAverage(array $grades): float - Calculates weighted average for grades
 * - calculateGradeStatistics(array $grades): array - Calculates statistics for student grades
 * - getStudentAbsences(int $studentId): array - Retrieves all absences for a student
 * - uploadJustification(int $absenceId, string $justification): bool - Uploads a justification for an absence
 * - validateJustificationFile(array $file): bool - Validates an uploaded justification file
 * - saveJustificationFile(array $file, int $absenceId): bool - Saves a justification file
 * - getJustificationFileInfo(int $absenceId): ?string - Retrieves information about a justification file
 *
 * File: /student/student_functions.php
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

/**
 * Gets attendance records for a student
 *
 * @param int $studentId Student ID
 * @return array Attendance records
 */
function getStudentAttendance(int $studentId): array
{
    try {
        global $pdo_options, $db_config;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
            $db_config['username'],
            $db_config['password'],
            $pdo_options
        );

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
        global $pdo_options, $db_config;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
            $db_config['username'],
            $db_config['password'],
            $pdo_options
        );

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
        global $pdo_options, $db_config;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
            $db_config['username'],
            $db_config['password'],
            $pdo_options
        );

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
    if (empty($grades)) {
        return 0.0;
    }

    $totalWeightedPoints = 0;
    $totalWeight = 0;

    foreach ($grades as $grade) {
        $percentage = ($grade['points'] / $grade['max_points']) * 100;

        $weight = isset($grade['weight']) ? (float)$grade['weight'] : 1.0;

        $totalWeightedPoints += $percentage * $weight;
        $totalWeight += $weight;
    }

    if ($totalWeight <= 0) {
        return 0.0;
    }

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
    if (empty($grades)) {
        return [];
    }

    $statistics = [];

    foreach ($grades as $grade) {
        $subject = $grade['subject_name'];
        $class = $grade['class_code'];

        if (!isset($statistics[$subject])) {
            $statistics[$subject] = [
                'subject_name' => $subject,
                'classes' => [],
                'total_points' => 0,
                'total_max_points' => 0,
                'total_weight' => 0,
                'weighted_average' => 0,
                'grade_count' => 0
            ];
        }

        if (!isset($statistics[$subject]['classes'][$class])) {
            $statistics[$subject]['classes'][$class] = [
                'class_code' => $class,
                'grades' => [],
                'total_points' => 0,
                'total_max_points' => 0,
                'total_weight' => 0,
                'weighted_average' => 0,
                'grade_count' => 0
            ];
        }

        $statistics[$subject]['classes'][$class]['grades'][] = $grade;

        $weight = isset($grade['weight']) ? (float)$grade['weight'] : 1.0;
        $statistics[$subject]['classes'][$class]['total_points'] += $grade['points'] * $weight;
        $statistics[$subject]['classes'][$class]['total_max_points'] += $grade['max_points'] * $weight;
        $statistics[$subject]['classes'][$class]['total_weight'] += $weight;
        $statistics[$subject]['classes'][$class]['grade_count']++;

        $statistics[$subject]['total_points'] += $grade['points'] * $weight;
        $statistics[$subject]['total_max_points'] += $grade['max_points'] * $weight;
        $statistics[$subject]['total_weight'] += $weight;
        $statistics[$subject]['grade_count']++;
    }

    foreach ($statistics as &$subjectData) {
        if ($subjectData['total_max_points'] > 0) {
            $subjectData['weighted_average'] =
                ($subjectData['total_points'] / $subjectData['total_max_points']) * 100;
        }

        foreach ($subjectData['classes'] as &$classData) {
            if ($classData['total_max_points'] > 0) {
                $classData['weighted_average'] =
                    ($classData['total_points'] / $classData['total_max_points']) * 100;
            }
        }
    }

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
        global $pdo_options, $db_config;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
            $db_config['username'],
            $db_config['password'],
            $pdo_options
        );

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
    try {
        global $pdo_options, $db_config;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
            $db_config['username'],
            $db_config['password'],
            $pdo_options
        );

        $verifyQuery = "
            SELECT a.att_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = :absence_id
            AND s.user_id = :user_id";

        $verifyStmt = $pdo->prepare($verifyQuery);
        $verifyStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);
        $verifyStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $verifyStmt->execute();

        if ($verifyStmt->rowCount() === 0) {
            return false;
        }

        $updateQuery = "
            UPDATE attendance
            SET justification = :justification,
                approved = NULL
            WHERE att_id = :absence_id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':justification', $justification);
        $updateStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);

        return $updateStmt->execute();
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return false;
    }
}

/**
 * Validate justification file
 *
 * @param array $file Uploaded file data
 * @return bool Validation result
 */
function validateJustificationFile(array $file): bool
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }

    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];

    $info = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $info->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes, true)) {
        return false;
    }

    return true;
}

/**
 * Save uploaded justification file
 *
 * @param array $file Uploaded file data
 * @param int $absenceId Absence ID
 * @return bool Success status
 */
function saveJustificationFile(array $file, int $absenceId): bool
{
    if (!validateJustificationFile($file)) {
        return false;
    }

    try {
        global $pdo_options, $db_config;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
            $db_config['username'],
            $db_config['password'],
            $pdo_options
        );

        $verifyQuery = "
            SELECT a.att_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = :absence_id
            AND s.user_id = :user_id";

        $verifyStmt = $pdo->prepare($verifyQuery);
        $verifyStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);
        $verifyStmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $verifyStmt->execute();

        if ($verifyStmt->rowCount() === 0) {
            return false;
        }

        $uploadDir = '../uploads/justifications/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = 'justification_' . $absenceId . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFilename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return false;
        }

        $updateQuery = "
            UPDATE attendance
            SET justification_file = :filename,
                approved = NULL
            WHERE att_id = :absence_id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':filename', $newFilename);
        $updateStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);

        return $updateStmt->execute();
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return false;
    }
}

/**
 * Get justification file information
 *
 * @param int $absenceId Absence ID
 * @return string|null Filename or null
 */
function getJustificationFileInfo(int $absenceId): ?string
{
    try {
        global $pdo_options, $db_config;
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}",
            $db_config['username'],
            $db_config['password'],
            $pdo_options
        );

        $query = "
            SELECT a.justification_file
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = :absence_id
            AND s.user_id = :user_id";

        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && $result['justification_file']) {
            return $result['justification_file'];
        }

        return null;
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return null;
    }
}
