<?php
/**
 * Parent Functions Library
 *
 * File path: /parent/parent_functions.php
 *
 * Centralized functions for parent-specific functionality in the uwuweb system.
 * Includes functions for accessing parent ID, student data, grade data, attendance records,
 * and absence justifications for students linked to a parent.
 *
 * Parent Information Functions:
 * - getParentId(): ?int - Retrieves the parent_id from the parents table for the currently logged-in user
 * - getParentStudents(?int $parentId = null): array - Retrieves all students linked to a specific parent
 * - parentHasAccessToStudent(int $studentId, ?int $parentId = null): bool - Verifies parent has access to student data
 *
 * Student Data Access Functions:
 * - getStudentClasses(int $studentId): array - Gets classes that a student is enrolled in
 * - getClassGrades(int $studentId, int $classId): array - Gets grades for a specific student in a class
 *
 * Grade Analysis Functions:
 * - calculateClassAverage(array $grades): float - Calculates overall grade average for a class
 * - getGradeLetter(float $percentage): string - Converts numerical percentage to letter grade
 *
 * Attendance and Justification Functions:
 * - getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null): array - Gets attendance records for a student
 * - getAttendanceStatusLabel(string $status): string - Converts attendance status code to readable label
 * - parentHasAccessToJustification(int $attId): bool - Checks if parent has access to a justification
 * - getJustificationDetails(int $attId): ?array - Gets detailed information about a specific justification
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Retrieves the parent_id from the parents table for the currently logged-in user
 *
 * @return int|null Parent ID or null if not found
 */
function getParentId(): ?int {
    $userId = getUserId();

    if (!$userId) {
        return null;
    }

    try {
        $pdo = safeGetDBConnection('getParentId');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getParentId");
            sendJsonErrorResponse("Database connection error", 500, "getParentId");
        }

        $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['parent_id'] : null;
    } catch (PDOException $e) {
        logDBError("Error in getParentId: " . $e->getMessage());
        sendJsonErrorResponse("Database error in getParentId: " . $e->getMessage(), 500, "getParentId");
    }
}

/**
 * Retrieves all students that are linked to a specific parent through the student_parent relationship
 *
 * @param int|null $parentId Parent ID (null for current user)
 * @return array Array of student records
 */
function getParentStudents(?int $parentId = null): array {
    if ($parentId === null) {
        $parentId = getParentId();
    }

    if (!$parentId) {
        return [];
    }

    try {
        $pdo = safeGetDBConnection('getParentStudents');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getParentStudents");
            sendJsonErrorResponse("Database connection error", 500, "getParentStudents");
        }

        $query = "
            SELECT s.student_id, s.first_name, s.last_name, s.class_code,
                   u.username, u.user_id
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            JOIN student_parent sp ON s.student_id = sp.student_id
            WHERE sp.parent_id = ?
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$parentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getParentStudents: " . $e->getMessage());
        sendJsonErrorResponse("Database error in getParentStudents: " . $e->getMessage(), 500, "getParentStudents");
    }
}

/**
 * Verify if parent has access to a student's data
 *
 * @param int $studentId Student ID to check
 * @param int|null $parentId Parent ID (null for current user)
 * @return bool True if parent has access, false otherwise
 */
function parentHasAccessToStudent(int $studentId, ?int $parentId = null): bool {
    if ($parentId === null) {
        $parentId = getParentId();
    }

    if (!$parentId) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('parentHasAccessToStudent');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in parentHasAccessToStudent");
            sendJsonErrorResponse("Database connection error", 500, "parentHasAccessToStudent");
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM student_parent 
            WHERE student_id = ? AND parent_id = ?
        ");
        $stmt->execute([$studentId, $parentId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && $result['count'] > 0;
    } catch (PDOException $e) {
        logDBError("Error in parentHasAccessToStudent: " . $e->getMessage());
        sendJsonErrorResponse("Database error in parentHasAccessToStudent: " . $e->getMessage(), 500, "parentHasAccessToStudent");
    }
}

/**
 * Retrieves all classes and subjects for a specific student that the parent has access to
 *
 * @param int $studentId Student ID
 * @return array Array of class records with subjects
 */
function getStudentClasses(int $studentId): array {
    // Verify parent has access to this student
    if (!parentHasAccessToStudent($studentId)) {
        return [];
    }
    
    try {
        $pdo = safeGetDBConnection('getStudentClasses');
        
        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentClasses");
            return [];
        }
        
        $query = "
            SELECT c.class_id, c.class_code, c.title as class_title,
                   e.enroll_id,
                   cs.class_subject_id, 
                   s.subject_id, s.name as subject_name,
                   u.username as teacher_username
            FROM enrollments e
            JOIN classes c ON e.class_id = c.class_id
            JOIN class_subjects cs ON c.class_id = cs.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN teachers t ON cs.teacher_id = t.teacher_id
            JOIN users u ON t.user_id = u.user_id
            WHERE e.student_id = ?
            ORDER BY c.class_code, s.name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getStudentClasses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get grades for a specific student in a class
 *
 * Retrieves all grades for a student in a specific class
 * grouped by subject
 *
 * @param int $studentId Student ID
 * @param int $classId Class ID
 * @return array Array of grade records grouped by subject
 */
function getClassGrades(int $studentId, int $classId): array {
    // Verify parent has access to this student
    if (!parentHasAccessToStudent($studentId)) {
        return [];
    }
    
    try {
        $pdo = safeGetDBConnection('getClassGrades');
        
        if ($pdo === null) {
            logDBError("Failed to establish database connection in getClassGrades");
            return [];
        }
        
        // Get enrollment ID
        $stmt = $pdo->prepare("
            SELECT enroll_id
            FROM enrollments
            WHERE student_id = ? AND class_id = ?
        ");
        $stmt->execute([$studentId, $classId]);
        
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$enrollment) {
            return [];
        }
        
        $enrollId = $enrollment['enroll_id'];
        
        // Get all subjects for this class
        $stmt = $pdo->prepare("
            SELECT cs.class_subject_id, s.subject_id, s.name as subject_name
            FROM class_subjects cs
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE cs.class_id = ?
        ");
        $stmt->execute([$classId]);
        
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        
        // For each subject, get grade items and grades
        foreach ($subjects as $subject) {
            $subjectData = [
                'subject_id' => $subject['subject_id'],
                'subject_name' => $subject['subject_name'],
                'grade_items' => [],
                'average' => 0
            ];
            
            // Get grade items for this subject
            $stmt = $pdo->prepare("
                SELECT gi.item_id, gi.name, gi.max_points, gi.weight
                FROM grade_items gi
                WHERE gi.class_subject_id = ?
            ");
            $stmt->execute([$subject['class_subject_id']]);
            
            $gradeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $totalPoints = 0;
            $totalMaxPoints = 0;
            $totalWeight = 0;
            
            // For each grade item, get student's grade
            foreach ($gradeItems as $item) {
                $stmt = $pdo->prepare("
                    SELECT g.points, g.comment
                    FROM grades g
                    WHERE g.enroll_id = ? AND g.item_id = ?
                ");
                $stmt->execute([$enrollId, $item['item_id']]);
                
                $grade = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $itemData = [
                    'item_id' => $item['item_id'],
                    'name' => $item['name'],
                    'max_points' => $item['max_points'],
                    'weight' => $item['weight']
                ];
                
                if ($grade) {
                    $itemData['points'] = $grade['points'];
                    $itemData['comment'] = $grade['comment'];
                    
                    // Calculate weighted contribution to average
                    $totalPoints += ($grade['points'] * $item['weight']);
                    $totalMaxPoints += ($item['max_points'] * $item['weight']);
                    $totalWeight += $item['weight'];
                }
                
                $subjectData['grade_items'][] = $itemData;
            }
            
            // Calculate subject average
            if ($totalMaxPoints > 0) {
                $subjectData['average'] = ($totalPoints / $totalMaxPoints) * 100;
                $subjectData['weighted_average'] = $totalWeight > 0 ? 
                    ($totalPoints / $totalWeight) : 0;
            }
            
            $result[] = $subjectData;
        }
        
        return $result;
    } catch (PDOException $e) {
        logDBError("Error in getClassGrades: " . $e->getMessage());
        return [];
    }
}

/**
 * Calculate overall grade average for a class
 *
 * @param array $grades Array of grade records from getClassGrades()
 * @return float Overall percentage average
 */
function calculateClassAverage(array $grades): float {
    $totalPoints = 0;
    $totalMaxPoints = 0;

    foreach ($grades as $subject) {
        if (!empty($subject['grade_items'])) {
            foreach ($subject['grade_items'] as $item) {
                if (isset($item['points'])) {
                    $totalPoints += ($item['points'] * $item['weight']);
                    $totalMaxPoints += ($item['max_points'] * $item['weight']);
                }
            }
        }
    }

    if ($totalMaxPoints > 0) {
        return round(($totalPoints / $totalMaxPoints) * 100, 1);
    }

    return 0.0;
}

/**
 * Converts a numerical percentage to a letter grade
 *
 * @param float $percentage Grade percentage (0-100)
 * @return string Letter grade (1-5)
 */
function getGradeLetter(float $percentage): string {
    if ($percentage >= 90) {
        return '5';
    }

    if ($percentage >= 80) {
        return '4';
    }

    if ($percentage >= 70) {
        return '3';
    }

    if ($percentage >= 50) {
        return '2';
    }

    return '1';
}

/**
 * Get attendance records for a student
 *
 * Retrieves attendance records for a specific student
 * that the parent has access to
 *
 * @param int $studentId Student ID
 * @param string|null $startDate Optional start date for filtering (YYYY-MM-DD)
 * @param string|null $endDate Optional end date for filtering (YYYY-MM-DD)
 * @return array Array of attendance records
 */
function getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null): array {
    // Verify parent has access to this student
    if (!parentHasAccessToStudent($studentId)) {
        return [];
    }
    
    try {
        $pdo = safeGetDBConnection('getStudentAttendance');
        
        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentAttendance");
            return [];
        }
        
        $params = [$studentId];
        $dateCondition = '';
        
        if ($startDate) {
            $dateCondition .= " AND p.period_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $dateCondition .= " AND p.period_date <= ?";
            $params[] = $endDate;
        }
        
        $query = "
            SELECT a.att_id, a.status, a.justification, a.approved, a.reject_reason, 
                   a.justification_file,
                   p.period_id, p.period_date, p.period_label,
                   c.class_code, c.title as class_title,
                   s.name as subject_name
            FROM students st
            JOIN enrollments e ON st.student_id = e.student_id
            JOIN attendance a ON e.enroll_id = a.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE st.student_id = ? $dateCondition
            ORDER BY p.period_date DESC, s.name
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add status labels
        foreach ($attendance as &$record) {
            $record['status_label'] = getAttendanceStatusLabel($record['status']);
        }
        
        return $attendance;
    } catch (PDOException $e) {
        logDBError("Error in getStudentAttendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Converts attendance status code to a readable label
 *
 * @param string $status Attendance status code (P, A, L)
 * @return string Readable label in Slovenian
 */
function getAttendanceStatusLabel(string $status): string {
    return match ($status) {
        'P' => 'Prisoten',
        'A' => 'Odsoten',
        'L' => 'Zamuda',
        default => 'Neznano',
    };
}

/**
 * Check if parent has access to a justification
 *
 * @param int $attId Attendance record ID
 * @return bool True if parent has access, false otherwise
 */
function parentHasAccessToJustification(int $attId): bool {
    try {
        $pdo = safeGetDBConnection('parentHasAccessToJustification');
        
        if ($pdo === null) {
            logDBError("Failed to establish database connection in parentHasAccessToJustification");
            return false;
        }
        
        // First get the student ID for this attendance record
        $stmt = $pdo->prepare("
            SELECT s.student_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = ?
        ");
        $stmt->execute([$attId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return false;
        }
        
        // Now check if parent has access to this student
        return parentHasAccessToStudent($result['student_id']);
    } catch (PDOException $e) {
        logDBError("Error in parentHasAccessToJustification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get justification details
 *
 * Retrieves detailed information about a specific justification
 * that the parent has access to
 *
 * @param int $attId Attendance record ID
 * @return array|null Justification details or null if not found/no access
 */
function getJustificationDetails(int $attId): ?array {
    // Verify parent has access to this justification
    if (!parentHasAccessToJustification($attId)) {
        return null;
    }
    
    try {
        $pdo = safeGetDBConnection('getJustificationDetails');
        
        if ($pdo === null) {
            logDBError("Failed to establish database connection in getJustificationDetails");
            return null;
        }
        
        $query = "
            SELECT a.att_id, a.status, a.justification, a.approved, a.reject_reason,
                   a.justification_file,
                   p.period_id, p.period_date, p.period_label,
                   s.student_id, s.first_name, s.last_name,
                   c.class_code, c.title as class_title,
                   subj.name as subject_name
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects subj ON cs.subject_id = subj.subject_id
            WHERE a.att_id = ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$attId]);
        
        $justification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$justification) {
            return null;
        }
        
        return $justification;
    } catch (PDOException $e) {
        logDBError("Error in getJustificationDetails: " . $e->getMessage());
        return null;
    }
}
