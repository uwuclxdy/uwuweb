<?php /** @noinspection ALL */
/**
 * Teacher Functions Library
 *
 * Centralized library of functions used by teacher module pages
 *
 * Teacher Information Functions:
 * - getTeacherId(?int $userId = null): ?int - Retrieves teacher_id from user_id
 * - getTeacherClasses(int $teacherId): array - Gets classes taught by a teacher
 * - teacherHasAccessToClassSubject(int $classSubjectId, ?int $teacherId = null): bool - Checks if teacher has access to a class-subject
 *
 * Class & Student Management:
 * - getClassStudents(int $classId): array - Gets students enrolled in a specific class
 * - getClassPeriods(int $classSubjectId): array - Gets periods for a class-subject
 *
 * Attendance Management:
 * - getPeriodAttendance(int $periodId): array - Gets attendance records for a period
 * - addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): bool|int - Adds a new period to a class
 * - saveAttendance(int $enrollId, int $periodId, string $status): bool - Saves attendance status for a student
 *
 * Grade Management:
 * - getGradeItems(int $classSubjectId): array - Gets grade items for a class-subject
 * - getClassGrades(int $classSubjectId): array - Gets all grades for students in a class
 * - addGradeItem(int $classSubjectId, string $name, float $maxPoints, float $weight = 1.00): bool|int - Adds a new grade item
 * - saveGrade(int $enrollId, int $itemId, float $points, ?string $comment = null): bool - Saves a grade for a student
 *
 * Justification Management:
 * - getPendingJustifications(?int $teacherId = null): array - Gets pending absence justifications
 * - getJustificationById(int $absenceId): ?array - Gets details about a specific justification
 * - approveJustification(int $absenceId): bool - Approves a justification
 * - rejectJustification(int $absenceId, string $reason): bool - Rejects a justification with reason
 * - getJustificationFileInfo(int $absenceId): ?string - Gets info about a justification file
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Retrieves the teacher_id from the teachers table for a given user_id. If userId is null, uses the current logged-in user
 *
 * @param int|null $userId User ID (if null, uses current user)
 * @return int|null Teacher ID or null if not found
 */
function getTeacherId(?int $userId = null): ?int
{
    if ($userId === null) {
        $userId = getUserId();
    }

    if (!$userId) {
        return null;
    }

    try {
        $pdo = safeGetDBConnection('getTeacherId');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getTeacherId");
            return null;
        }

        $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['teacher_id'] : null;
    } catch (PDOException $e) {
        logDBError("Error in getTeacherId: " . $e->getMessage());
        return null;
    }
}

/**
 * Retrieves all classes assigned to a specific teacher through the class_subjects table. Includes class code, title, and subject information
 *
 * @param int $teacherId Teacher ID
 * @return array Array of class records or empty array if none found
 */
function getTeacherClasses(int $teacherId): array
{
    try {
        $pdo = safeGetDBConnection('getTeacherClasses');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getTeacherClasses");
            return [];
        }

        $query = "
            SELECT cs.class_subject_id, c.class_id, c.class_code, c.title as class_title,
                   s.subject_id, s.name as subject_name
            FROM class_subjects cs
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE cs.teacher_id = ?
            ORDER BY c.class_code, s.name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$teacherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getTeacherClasses: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves student information for all students enrolled in a specific class
 *
 * @param int $classId Class ID
 * @return array Array of student records
 */
function getClassStudents(int $classId): array
{
    try {
        $pdo = safeGetDBConnection('getClassStudents');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getClassStudents");
            return [];
        }

        $query = "
            SELECT e.enroll_id, s.student_id, s.first_name, s.last_name, 
                   u.username, u.user_id
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            WHERE e.class_id = ?
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getClassStudents: " . $e->getMessage());
        return [];
    }
}

/**
 * Checks if a teacher has access to a specific class-subject
 *
 * @param int $classSubjectId The class-subject ID to check
 * @param int|null $teacherId The teacher ID (or null to use current user)
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToClassSubject(int $classSubjectId, ?int $teacherId = null): bool
{
    if ($teacherId === null) {
        $teacherId = getTeacherId();
    }

    if (!$teacherId) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('teacherHasAccessToClassSubject');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in teacherHasAccessToClassSubject");
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM class_subjects 
            WHERE class_subject_id = ? AND teacher_id = ?
        ");
        $stmt->execute([$classSubjectId, $teacherId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['count'] > 0;
    } catch (PDOException $e) {
        logDBError("Error in teacherHasAccessToClassSubject: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves all periods for a class-subject with their dates and labels
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of period records
 */
function getClassPeriods(int $classSubjectId): array
{
    if (!teacherHasAccessToClassSubject($classSubjectId)) {
        return [];
    }

    try {
        $pdo = safeGetDBConnection('getClassPeriods');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getClassPeriods");
            return [];
        }

        $query = "
            SELECT period_id, period_date, period_label
            FROM periods
            WHERE class_subject_id = ?
            ORDER BY period_date DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classSubjectId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getClassPeriods: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves attendance status for all students in a period
 *
 * @param int $periodId Period ID
 * @return array Array of attendance records
 */
function getPeriodAttendance(int $periodId): array
{
    try {
        $pdo = safeGetDBConnection('getPeriodAttendance');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getPeriodAttendance");
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT class_subject_id 
            FROM periods 
            WHERE period_id = ?
        ");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$period || !isset($period['class_subject_id'])) {
            return [];
        }

        if (!teacherHasAccessToClassSubject($period['class_subject_id'])) {
            return [];
        }

        $query = "
            SELECT a.att_id, a.enroll_id, a.status, a.justification, a.approved,
                   a.reject_reason, a.justification_file,
                   s.student_id, s.first_name, s.last_name, e.class_id
            FROM periods p
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN enrollments e ON cs.class_id = e.class_id
            LEFT JOIN attendance a ON e.enroll_id = a.enroll_id AND a.period_id = p.period_id
            JOIN students s ON e.student_id = s.student_id
            WHERE p.period_id = ?
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$periodId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getPeriodAttendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Creates a new period entry and initializes attendance records
 *
 * @param int $classSubjectId Class-Subject ID
 * @param string $periodDate Date in YYYY-MM-DD format
 * @param string $periodLabel Label for the period
 * @return bool|int False on failure, period ID on success
 */
function addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): bool|int
{
    if (!teacherHasAccessToClassSubject($classSubjectId)) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('addPeriod');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in addPeriod");
            sendJsonErrorResponse("Database connection failed", 500, "addPeriod");
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO periods (class_subject_id, period_date, period_label)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$classSubjectId, $periodDate, $periodLabel]);

        $periodId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            SELECT class_id 
            FROM class_subjects 
            WHERE class_subject_id = ?
        ");
        $stmt->execute([$classSubjectId]);
        $classSubject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$classSubject) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT enroll_id 
            FROM enrollments 
            WHERE class_id = ?
        ");
        $stmt->execute([$classSubject['class_id']]);
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            INSERT INTO attendance (enroll_id, period_id, status)
            VALUES (?, ?, 'P')
        ");

        foreach ($enrollments as $enrollment) {
            $stmt->execute([$enrollment['enroll_id'], $periodId]);
        }

        $pdo->commit();
        return $periodId;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError("Error in addPeriod: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates or creates an attendance record for a student in a specific period
 *
 * @param int $enrollId Enrollment ID
 * @param int $periodId Period ID
 * @param string $status Attendance status (P, A, L)
 * @return bool Success or failure
 */
function saveAttendance(int $enrollId, int $periodId, string $status): bool
{
    // Validate status
    if (!in_array($status, ['P', 'A', 'L'])) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('saveAttendance');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in saveAttendance");
            return false;
        }

        // First verify teacher has access to this period
        $stmt = $pdo->prepare("
            SELECT p.class_subject_id
            FROM periods p
            WHERE p.period_id = ?
        ");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$period || !teacherHasAccessToClassSubject($period['class_subject_id'])) {
            return false;
        }

        // Check if attendance record already exists
        $stmt = $pdo->prepare("
            SELECT att_id 
            FROM attendance 
            WHERE enroll_id = ? AND period_id = ?
        ");
        $stmt->execute([$enrollId, $periodId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attendance) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = ? 
                WHERE enroll_id = ? AND period_id = ?
            ");
            $stmt->execute([$status, $enrollId, $periodId]);
        } else {
            // Create new record
            $stmt = $pdo->prepare("
                INSERT INTO attendance (enroll_id, period_id, status)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$enrollId, $periodId, $status]);
        }

        return true;
    } catch (PDOException $e) {
        logDBError("Error in saveAttendance: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves all grade items defined for a specific class-subject
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of grade item records
 */
function getGradeItems(int $classSubjectId): array
{
    // Verify teacher has access to this class-subject
    if (!teacherHasAccessToClassSubject($classSubjectId)) {
        return [];
    }

    try {
        $pdo = safeGetDBConnection('getGradeItems');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getGradeItems");
            return [];
        }

        $query = "
            SELECT item_id, name, max_points, weight
            FROM grade_items
            WHERE class_subject_id = ?
            ORDER BY item_id
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classSubjectId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getGradeItems: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves grades for all students and grade items in a class-subject
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of grade records grouped by student
 */
function getClassGrades(int $classSubjectId): array
{
    if (!teacherHasAccessToClassSubject($classSubjectId)) {
        return [];
    }

    try {
        $pdo = safeGetDBConnection('getClassGrades');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getClassGrades");
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT class_id 
            FROM class_subjects 
            WHERE class_subject_id = ?
        ");
        $stmt->execute([$classSubjectId]);
        $classSubject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$classSubject || !isset($classSubject['class_id'])) {
            return [];
        }

        $students = getClassStudents($classSubject['class_id']);

        $gradeItems = getGradeItems($classSubjectId);

        $result = [
            'students' => $students,
            'grade_items' => $gradeItems,
            'grades' => []
        ];

        $stmt = $pdo->prepare("
            SELECT g.grade_id, g.enroll_id, g.item_id, g.points, g.comment
            FROM grades g
            JOIN enrollments e ON g.enroll_id = e.enroll_id
            WHERE e.class_id = ? AND g.item_id IN (
                SELECT item_id FROM grade_items WHERE class_subject_id = ?
            )
        ");
        $stmt->execute([$classSubject['class_id'], $classSubjectId]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($grades as $grade) {
            if (!isset($result['grades'][$grade['enroll_id']])) {
                $result['grades'][$grade['enroll_id']] = [];
            }
            $result['grades'][$grade['enroll_id']][$grade['item_id']] = [
                'points' => $grade['points'],
                'comment' => $grade['comment']
            ];
        }

        return $result;
    } catch (PDOException $e) {
        logDBError("Error in getClassGrades: " . $e->getMessage());
        return [];
    }
}

/**
 * Creates a new grade item entry for a class-subject
 *
 * @param int $classSubjectId Class-Subject ID
 * @param string $name Name of the grade item
 * @param float $maxPoints Maximum points possible
 * @param float $weight Weight of the grade item
 * @return bool|int False on failure, grade item ID on success
 */
function addGradeItem(int $classSubjectId, string $name, float $maxPoints, float $weight = 1.00): bool|int
{
    if (!teacherHasAccessToClassSubject($classSubjectId)) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('addGradeItem');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in addGradeItem");
            return false;
        }

        $stmt = $pdo->prepare("
            INSERT INTO grade_items (class_subject_id, name, max_points, weight)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$classSubjectId, $name, $maxPoints, $weight]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Error in addGradeItem: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates or creates a grade record for a student and grade item
 *
 * @param int $enrollId Enrollment ID
 * @param int $itemId Grade Item ID
 * @param float $points Points earned
 * @param string|null $comment Optional comment/feedback
 * @return bool Success or failure
 */
function saveGrade(int $enrollId, int $itemId, float $points, ?string $comment = null): bool
{
    try {
        $pdo = safeGetDBConnection('saveGrade');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in saveGrade");
            return false;
        }

        // Verify teacher has access to this grade item
        $stmt = $pdo->prepare("
            SELECT gi.class_subject_id
            FROM grade_items gi
            WHERE gi.item_id = ?
        ");
        $stmt->execute([$itemId]);
        $gradeItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gradeItem || !teacherHasAccessToClassSubject($gradeItem['class_subject_id'])) {
            return false;
        }

        // Check if points exceed maximum
        $stmt = $pdo->prepare("SELECT max_points FROM grade_items WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $maxPoints = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$maxPoints || $points > $maxPoints['max_points']) {
            return false;
        }

        // Check if grade already exists
        $stmt = $pdo->prepare("
            SELECT grade_id 
            FROM grades 
            WHERE enroll_id = ? AND item_id = ?
        ");
        $stmt->execute([$enrollId, $itemId]);
        $grade = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($grade) {
            // Update existing grade
            $stmt = $pdo->prepare("
                UPDATE grades 
                SET points = ?, comment = ? 
                WHERE enroll_id = ? AND item_id = ?
            ");
            $stmt->execute([$points, $comment, $enrollId, $itemId]);
        } else {
            // Create new grade
            $stmt = $pdo->prepare("
                INSERT INTO grades (enroll_id, item_id, points, comment)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$enrollId, $itemId, $points, $comment]);
        }

        return true;
    } catch (PDOException $e) {
        logDBError("Error in saveGrade: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieves all absence justifications pending approval for classes taught by a specific teacher
 *
 * @param int|null $teacherId Teacher ID (null for current user)
 * @return array Array of pending justification records
 */
function getPendingJustifications(?int $teacherId = null): array
{
    if ($teacherId === null) {
        $teacherId = getTeacherId();
    }

    if (!$teacherId) {
        return [];
    }

    try {
        $pdo = safeGetDBConnection('getPendingJustifications');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getPendingJustifications");
            return [];
        }

        $query = "
            SELECT a.att_id, a.status, a.justification, a.justification_file,
                   p.period_id, p.period_date, p.period_label,
                   s.first_name, s.last_name, s.student_id,
                   c.class_code, c.title as class_title,
                   subj.name as subject_name
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            JOIN classes c ON e.class_id = c.class_id
            JOIN subjects subj ON cs.subject_id = subj.subject_id
            WHERE cs.teacher_id = ?
              AND a.status = 'A'
              AND a.justification IS NOT NULL
              AND a.approved IS NULL
            ORDER BY p.period_date DESC, c.class_code
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$teacherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getPendingJustifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Get detailed information about a specific justification
 *
 * @param int $absenceId Attendance record ID
 * @return array|null Justification details or null if not found
 */
function getJustificationById(int $absenceId): ?array
{
    try {
        $pdo = safeGetDBConnection('getJustificationById');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getJustificationById");
            return null;
        }

        $query = "
            SELECT a.att_id, a.status, a.justification, a.justification_file,
                   p.period_id, p.period_date, p.period_label,
                   s.first_name, s.last_name, s.student_id,
                   c.class_code, c.title as class_title,
                   subj.name as subject_name,
                   cs.class_subject_id, cs.teacher_id
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            JOIN classes c ON e.class_id = c.class_id
            JOIN subjects subj ON cs.subject_id = subj.subject_id
            WHERE a.att_id = ?
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$absenceId]);

        $justification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$justification) {
            return null;
        }

        $teacherId = getTeacherId();
        if ($teacherId === null || $justification['teacher_id'] != $teacherId) {
            return null;
        }

        return $justification;
    } catch (PDOException $e) {
        logDBError("Error in getJustificationById: " . $e->getMessage());
        return null;
    }
}

/**
 * Sets the approved flag to true for an absence justification
 *
 * @param int $absenceId Attendance record ID
 * @return bool Success or failure
 */
function approveJustification(int $absenceId): bool
{
    // Verify teacher has access to this justification
    $justification = getJustificationById($absenceId);
    if (!$justification) {
        sendJsonErrorResponse('Unauthorized access to justification', 403, 'approveJustification');
    }

    try {
        $pdo = safeGetDBConnection('approveJustification');

        if ($pdo === null) {
            sendJsonErrorResponse('Database connection error', 500, 'approveJustification');
        }

        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET approved = 1, reject_reason = NULL
            WHERE att_id = ?
        ");
        $stmt->execute([$absenceId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error in approveJustification: " . $e->getMessage());
        sendJsonErrorResponse('Error updating justification status', 500, 'approveJustification');
    }
}

/**
 * Sets the approved flag to false and adds a rejection reason
 *
 * @param int $absenceId Attendance record ID
 * @param string $reason Reason for rejection
 * @return bool Success or failure
 */
function rejectJustification(int $absenceId, string $reason): bool
{
    // Verify teacher has access to this justification
    $justification = getJustificationById($absenceId);
    if (!$justification) {
        sendJsonErrorResponse('Unauthorized access to justification', 403, 'rejectJustification');
    }

    if (empty($reason)) {
        sendJsonErrorResponse('Reason for rejection is required', 400, 'rejectJustification');
    }

    try {
        $pdo = safeGetDBConnection('rejectJustification');

        if ($pdo === null) {
            sendJsonErrorResponse('Database connection error', 500, 'rejectJustification');
        }

        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET approved = 0, reject_reason = ?
            WHERE att_id = ?
        ");
        $stmt->execute([$reason, $absenceId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error in rejectJustification: " . $e->getMessage());
        sendJsonErrorResponse('Error updating justification status', 500, 'rejectJustification');
    }
}

/**
 * Get information about a saved justification file
 *
 * @param int $absenceId Attendance record ID
 * @return string|null Filename or null if no file exists
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
