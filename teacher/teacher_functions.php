<?php /** @noinspection ALL */
/**
 * Teacher Functions Library
 *
 * Centralized functions for teacher operations including grade management, attendance
 * tracking, and justification processing.
 *
 * File path: /teacher/teacher_functions.php
 *
 * Teacher Information Functions:
 * - getTeacherId(?int $userId = null): ?int - Gets teacher_id from user_id or current session user if null
 * - getTeacherClasses(int $teacherId): array - Returns classes taught by teacher with code, title and subject info
 * - teacherHasAccessToClassSubject(int $classSubjectId, ?int $teacherId = null): bool - Verifies teacher access to class-subject. Uses current teacher if $teacherId null
 *
 * Class & Student Management:
 * - getClassStudents(int $classId): array - Returns students enrolled in a class
 * - getClassPeriods(int $classSubjectId): array - Returns periods for a class-subject
 *
 * Attendance Management:
 * - getPeriodAttendance(int $periodId): array - Returns attendance records for a period
 * - addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): bool|int - Creates new period for class. Returns period_id or false
 * - saveAttendance(int $enrollId, int $periodId, string $status): bool - Records student attendance status
 * - getStudentAttendanceByDate(int $studentId, string $date): array - Gets attendance records for a student on a specific date
 *
 * Grade Management:
 * - getGradeItems(int $classSubjectId): array - Returns grade items for class-subject after permission check
 * - getClassGrades(int $classSubjectId): array - Returns all grades for students in a class
 * - addGradeItem(int $classSubjectId, string $name, float $maxPoints, float $weight = 1.00): bool|int - Creates grade item. Returns item_id or false
 * - saveGrade(int $enrollId, int $itemId, float $points, ?string $comment = null): bool - Creates or updates student grade
 *
 * Justification Management:
 * - getPendingJustifications(?int $teacherId = null): array - Returns pending justifications for teacher or all if admin
 * - getJustificationById(int $absenceId): ?array - Returns justification details with student info and attachments
 * - approveJustification(int $absenceId): bool - Approves justification and updates attendance
 * - rejectJustification(int $absenceId, string $reason): bool - Rejects justification with reason
 *
 * Dashboard Widget Functions:
 * - renderTeacherClassOverviewWidget(): string - Displays teacher's assigned classes with subject and student count information
 * - renderTeacherAttendanceWidget(): string - Shows today's classes with attendance recording status and quick action links
 * - renderTeacherPendingJustificationsWidget(): string - Lists pending absence justifications awaiting teacher approval
 * - renderTeacherClassAveragesWidget(): string - Visualizes academic performance averages across teacher's classes
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
 * Gets attendance records for a student on a specific date
 *
 * @param int $studentId Student ID
 * @param string $date Date in YYYY-MM-DD format
 * @return array Array of attendance records
 */
function getStudentAttendanceByDate(int $studentId, string $date): array
{
    try {
        $pdo = safeGetDBConnection('getStudentAttendanceByDate');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentAttendanceByDate");
            return [];
        }

        $query = "
            SELECT a.att_id, a.status, a.notes, 
                   p.period_date as date, p.period_label,
                   s.name as subject_name
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE e.student_id = ?
            AND DATE(p.period_date) = ?
            ORDER BY p.period_date, p.period_label
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentId, $date]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getStudentAttendanceByDate: " . $e->getMessage());
        return [];
    }
}

/**
 * Creates the HTML for the teacher's class overview dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderTeacherClassOverviewWidget(): string
{
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');

        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $query = "SELECT cs.class_subject_id, c.class_id, c.class_code, c.title as class_title,
                         s.subject_id, s.name as subject_name,
                         COUNT(DISTINCT e.student_id) as student_count
                  FROM class_subjects cs
                  JOIN classes c ON cs.class_id = c.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN enrollments e ON c.class_id = e.class_id
                  WHERE cs.teacher_id = :teacher_id
                  GROUP BY cs.class_subject_id, c.class_id, c.class_code, c.title, s.subject_id, s.name
                  ORDER BY c.title, s.name";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($classes)) return renderPlaceholderWidget('Trenutno ne poučujete nobenega razreda.');

        $output = '<div class="teacher-class-overview card card__content p-0">';
        $output .= '<ul class="class-list list-unstyled p-0 m-0">';

        foreach ($classes as $class) {
            $output .= '<li class="class-item p-md border-bottom">';

            $output .= '<div class="class-header d-flex justify-between items-center mb-sm">';
            $output .= '<span class="class-name font-medium">' . htmlspecialchars($class['class_title']) . '</span>';
            $output .= '<span class="class-code badge badge-secondary">' . htmlspecialchars($class['class_code']) . '</span>';
            $output .= '</div>';

            $output .= '<div class="class-details d-flex justify-between items-center text-sm text-secondary mb-md">';
            $output .= '<span class="subject">' . htmlspecialchars($class['subject_name']) . '</span>';
            $output .= '<span class="student-count d-flex items-center gap-xs">
                           <span class="material-icons-outlined text-sm">group</span> ' .
                htmlspecialchars($class['student_count']) . ' dijakov
                        </span>';
            $output .= '</div>';

            $output .= '<div class="class-actions d-flex gap-sm justify-end">';
            $output .= '<a href="/uwuweb/teacher/gradebook.php?class_subject_id=' . urlencode($class['class_subject_id']) . '" class="btn btn-sm btn-primary d-flex items-center gap-xs">
                           <span class="material-icons-outlined text-sm">grade</span> Redovalnica
                        </a>';
            $output .= '<a href="/uwuweb/teacher/attendance.php?class_subject_id=' . urlencode($class['class_subject_id']) . '" class="btn btn-sm btn-secondary d-flex items-center gap-xs">
                           <span class="material-icons-outlined text-sm">event_available</span> Prisotnost
                        </a>';
            $output .= '</div>';

            $output .= '</li>';
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    } catch (PDOException $e) {
        error_log("Error in renderTeacherClassOverviewWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o razredih.');
    }
}

/**
 * Shows attendance status for today's classes taught by the teacher
 *
 * @return string HTML content for the widget
 */
function renderTeacherAttendanceWidget(): string
{
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');

        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $todayQuery = "SELECT p.period_id, p.period_label, c.class_code, s.name as subject_name,
                             cs.class_subject_id,
                             (SELECT COUNT(*) FROM enrollments WHERE class_id = c.class_id) as total_students,
                             (SELECT COUNT(*) FROM attendance WHERE period_id = p.period_id) as recorded_attendance
                      FROM periods p
                      JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                      JOIN classes c ON cs.class_id = c.class_id
                      JOIN subjects s ON cs.subject_id = s.subject_id
                      WHERE cs.teacher_id = :teacher_id
                      AND DATE(p.period_date) = CURRENT_DATE()
                      ORDER BY p.period_label";

        $stmt = $db->prepare($todayQuery);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        $todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($todayClasses)) return renderPlaceholderWidget('Danes nimate načrtovanega pouka.');

        $output = '<div class="teacher-today-attendance card card__content p-0">';
        $output .= '<div class="table-responsive">';
        $output .= '<table class="attendance-table data-table w-100">';
        $output .= '<thead><tr>
                      <th>Ura</th>
                      <th>Razred</th>
                      <th>Predmet</th>
                      <th class="text-center">Status</th>
                      <th class="text-right">Akcija</th>
                    </tr></thead>';
        $output .= '<tbody>';

        foreach ($todayClasses as $class) {
            $recorded = (int)$class['recorded_attendance'];
            $total = (int)$class['total_students'];
            $completionPercent = $total > 0 ? round(($recorded / $total) * 100) : 0;

            $statusClass = 'badge-error';
            $statusText = 'Ne vneseno';
            $statusIcon = 'edit';

            if ($recorded > 0 && $recorded < $total) {
                $statusClass = 'badge-warning';
                $statusText = 'Delno (' . $completionPercent . '%)';
            } elseif ($recorded >= $total && $total > 0) {
                $statusClass = 'badge-success';
                $statusText = 'Zabeleženo';
                $statusIcon = 'check_circle';
            } elseif ($total == 0) {
                $statusClass = 'badge-secondary';
                $statusText = 'Ni dijakov';
                $statusIcon = 'info';
            }

            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars($class['period_label']) . '. ura</td>';
            $output .= '<td>' . htmlspecialchars($class['class_code']) . '</td>';
            $output .= '<td>' . htmlspecialchars($class['subject_name']) . '</td>';
            $output .= '<td class="text-center"><span class="attendance-status badge ' . $statusClass . '">' . $statusText . '</span></td>';
            $output .= '<td class="text-right">
                           <a href="/uwuweb/teacher/attendance.php?class_subject_id=' . urlencode($class['class_subject_id']) . '&period_id=' . urlencode($class['period_id']) . '"
                              class="btn btn-sm ' . ($statusIcon == 'check_circle' ? 'btn-secondary' : 'btn-primary') . ' d-inline-flex items-center gap-xs"
                              title="' . ($statusIcon == 'check_circle' ? 'Preglej' : 'Vnesi') . ' prisotnost">
                              <span class="material-icons-outlined text-sm">' . $statusIcon . '</span>
                           </a>
                        </td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';
        $output .= '</div>';

        $output .= '<div class="widget-footer mt-md text-right p-md border-top pt-md">';
        $output .= '<a href="/uwuweb/teacher/attendance.php" class="btn btn-secondary btn-sm">Pojdi na stran Prisotnost</a>';
        $output .= '</div>';

        $output .= '</div>';

        return $output;
    } catch (PDOException $e) {
        error_log("Error in renderTeacherAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o današnji prisotnosti.');
    }
}

/**
 * Shows absence justifications waiting for teacher approval
 *
 * @return string HTML content for the widget
 */
function renderTeacherPendingJustificationsWidget(): string
{
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');

        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $limit = 5;

        $query = "SELECT a.att_id, s.first_name, s.last_name, c.class_code, c.title as class_title,
                         p.period_date, p.period_label, a.status, a.justification, a.justification_file,
                         subj.name as subject_name
                  FROM attendance a
                  JOIN enrollments e ON a.enroll_id = e.enroll_id
                  JOIN students s ON e.student_id = s.student_id
                  JOIN periods p ON a.period_id = p.period_id
                  JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                  JOIN classes c ON cs.class_id = c.class_id
                  JOIN subjects subj ON cs.subject_id = subj.subject_id
                  WHERE cs.teacher_id = :teacher_id
                  AND a.status = 'A'
                  AND a.justification IS NOT NULL
                  AND a.approved IS NULL
                  ORDER BY p.period_date DESC, s.last_name, s.first_name
                  LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countQuery = "SELECT COUNT(*) as total
                      FROM attendance a
                      JOIN periods p ON a.period_id = p.period_id
                      JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                      WHERE cs.teacher_id = :teacher_id
                      AND a.status = 'A'
                      AND a.justification IS NOT NULL
                      AND a.approved IS NULL";

        $countStmt = $db->prepare($countQuery);
        $countStmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $countStmt->execute();
        $totalPending = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

        if ($totalPending == 0) return renderPlaceholderWidget('Trenutno ni čakajočih opravičil.');

        $output = '<div class="pending-justifications card card__content p-0">';

        $output .= '<div class="justifications-header d-flex justify-between items-center p-md border-bottom">';
        $output .= '<h5 class="m-0 font-medium">Čakajoča opravičila</h5>';
        $output .= '<span class="badge badge-warning">' . htmlspecialchars($totalPending) . '</span>';
        $output .= '</div>';

        $output .= '<ul class="justification-list list-unstyled p-0 m-0">';

        foreach ($justifications as $just) {
            $output .= '<li class="justification-item p-md border-bottom">';

            $output .= '<div class="student-info d-flex justify-between items-center mb-sm">';
            $output .= '<strong class="font-medium">' . htmlspecialchars($just['first_name'] . ' ' . $just['last_name']) . '</strong>';
            $output .= '<span class="class-code badge badge-secondary">' . htmlspecialchars($just['class_code']) . '</span>';
            $output .= '</div>';

            $formattedDate = date('d.m.Y', strtotime($just['period_date']));
            $output .= '<div class="absence-info d-flex justify-between flex-wrap gap-sm text-sm text-secondary mb-sm">';
            $output .= '<span class="d-flex items-center gap-xs"><span class="material-icons-outlined text-sm">calendar_today</span> ' . htmlspecialchars($formattedDate) . '</span>';
            $output .= '<span class="d-flex items-center gap-xs"><span class="material-icons-outlined text-sm">schedule</span> ' . htmlspecialchars($just['period_label']) . '. ura</span>';
            $output .= '<span class="d-flex items-center gap-xs"><span class="material-icons-outlined text-sm">book</span> ' . htmlspecialchars($just['subject_name']) . '</span>';
            $output .= '</div>';

            if (!empty($just['justification'])) {
                $justificationExcerpt = mb_strimwidth($just['justification'], 0, 80, '...');
                $output .= '<div class="justification-text text-sm mb-sm fst-italic bg-tertiary p-sm rounded">"' . htmlspecialchars($justificationExcerpt) . '"</div>';
            }

            if (!empty($just['justification_file'])) $output .= '<div class="attachment-indicator text-sm text-secondary d-flex items-center gap-xs mb-md">
                            <span class="material-icons-outlined text-sm">attachment</span> Priloga
                         </div>';

            $output .= '<div class="justification-actions d-flex gap-sm justify-end">';
            $output .= '<a href="/uwuweb/teacher/justifications.php?action=view&id=' . urlencode($just['att_id']) . '" class="btn btn-sm btn-secondary d-flex items-center gap-xs" title="Podrobnosti">
                           <span class="material-icons-outlined text-sm">visibility</span>
                        </a>';
            $output .= '<a href="/uwuweb/teacher/justifications.php?action=approve&id=' . urlencode($just['att_id']) . '" class="btn btn-sm btn-success d-flex items-center gap-xs" title="Odobri">
                           <span class="material-icons-outlined text-sm">check</span>
                        </a>';
            $output .= '<a href="/uwuweb/teacher/justifications.php?action=reject&id=' . urlencode($just['att_id']) . '" class="btn btn-sm btn-error d-flex items-center gap-xs" title="Zavrni">
                           <span class="material-icons-outlined text-sm">close</span>
                        </a>';
            $output .= '</div>';

            $output .= '</li>';
        }

        $output .= '</ul>';

        if ($totalPending > $limit) {
            $output .= '<div class="more-link mt-md text-center border-top pt-md p-md">';
            $output .= '<a href="/uwuweb/teacher/justifications.php" class="btn btn-sm btn-secondary">Prikaži vsa opravičila (' . $totalPending . ')</a>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    } catch (PDOException $e) {
        error_log("Error in renderTeacherPendingJustificationsWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o opravičilih.');
    }
}

/**
 * Creates the HTML for the teacher's class averages dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderTeacherClassAveragesWidget(): string
{
    $teacherId = getTeacherId();

    if (!$teacherId) return renderPlaceholderWidget('Za prikaz povprečij razredov se morate identificirati kot učitelj.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    try {
        $query = "SELECT
                    cs.class_subject_id,
                    c.class_id,
                    c.title AS class_title,
                    s.subject_id,
                    s.name AS subject_name,
                    COUNT(DISTINCT e.student_id) AS student_count,
                    (SELECT AVG(CASE WHEN gi.max_points > 0 THEN (g.points / gi.max_points) * 100 END)
                     FROM grades g
                     JOIN grade_items gi ON g.item_id = gi.item_id
                     WHERE gi.class_subject_id = cs.class_subject_id) AS avg_score
                  FROM class_subjects cs
                  JOIN classes c ON cs.class_id = c.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN enrollments e ON c.class_id = e.class_id
                  WHERE cs.teacher_id = :teacher_id
                  GROUP BY cs.class_subject_id, c.class_id, c.title, s.subject_id, s.name
                  ORDER BY s.name, c.title";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        $classAverages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderTeacherClassAveragesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o razrednih povprečjih.');
    }

    $html = '<div class="widget-content">';

    if (empty($classAverages)) $html .= '<div class="card card__content text-center p-md"><p class="m-0">Nimate razredov z ocenami.</p></div>'; else {
        $html .= '<div class="row">';

        foreach ($classAverages as $class) {
            $avgScoreFormatted = $class['avg_score'] !== null ? number_format($class['avg_score'], 1) : 'N/A';
            $scoreClass = 'text-secondary';

            if ($class['avg_score'] !== null) if ($class['avg_score'] >= 80) $scoreClass = 'grade-high'; elseif ($class['avg_score'] >= 60) $scoreClass = 'grade-medium';
            else $scoreClass = 'grade-low';

            $html .= '<div class="col-12 col-md-6 col-lg-4 mb-md">';
            $html .= '<div class="card class-average-card h-100 d-flex flex-column">';

            $html .= '<div class="card__header d-flex justify-between items-center p-md">';
            $html .= '<h5 class="card__title m-0 font-medium">' . htmlspecialchars($class['subject_name']) . '</h5>';
            $html .= '<span class="badge badge-secondary">' . htmlspecialchars($class['class_title']) . '</span>';
            $html .= '</div>';

            $html .= '<div class="card__content p-md flex-grow-1">';
            $html .= '<div class="average-stats d-flex flex-column items-center text-center gap-md">';

            $html .= '<div class="average-score ' . $scoreClass . '">';
            $html .= '<span class="score-value d-block font-size-xl font-bold">' . $avgScoreFormatted . ($avgScoreFormatted !== 'N/A' ? '%' : '') . '</span>';
            $html .= '<span class="text-sm text-secondary">Povprečje razreda</span>';
            $html .= '</div>';

            $html .= '<div class="stat-item">';
            $html .= '<div class="stat-value font-medium">' . (int)$class['student_count'] . '</div>';
            $html .= '<div class="stat-label text-sm text-secondary">Dijakov</div>';
            $html .= '</div>';

            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="card__footer p-md mt-auto text-right border-top pt-md">';
            $html .= '<a href="/uwuweb/teacher/gradebook.php?class_subject_id=' . (int)$class['class_subject_id'] . '" class="btn btn-sm btn-primary">Ogled redovalnice</a>';
            $html .= '</div>';

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}
