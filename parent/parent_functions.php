<?php
/**
 * Parent Functions Library
 *
 * File path: /parent/parent_functions.php
 *
 * Centralized functions for parent-specific functionality in the uwuweb system.
 * Includes functions for accessing parent ID, student data, grade data, attendance records,
 * and absence justifications for students linked to a parent, as well as parent dashboard widgets.
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
 * Attendance and Justification Functions:
 * - getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null): array - Gets attendance records for a student
 * - parentHasAccessToJustification(int $attId): bool - Checks if parent has access to a justification
 * - getJustificationDetails(int $attId): ?array - Gets detailed information about a specific justification
 * - getStudentJustifications(int $studentId): array - Gets all justifications for a student
 *
 * Dashboard Widget Functions:
 * - renderParentAttendanceWidget(): string - Renders attendance summary widget for parents
 * - renderParentChildClassAveragesWidget(): string - Renders class averages widget for parent's children
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Retrieves the parent_id from the parents table for the currently logged-in user
 *
 * @return int|null Parent ID or null if not found
 */
function getParentId(): ?int
{
    $userId = getUserId();

    if (!$userId) return null;

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
function getParentStudents(?int $parentId = null): array
{
    if ($parentId === null) $parentId = getParentId();

    if (!$parentId) return [];

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
function parentHasAccessToStudent(int $studentId, ?int $parentId = null): bool
{
    if ($parentId === null) $parentId = getParentId();

    if (!$parentId) return false;

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
function getStudentClasses(int $studentId): array
{
    // Verify parent has access to this student
    if (!parentHasAccessToStudent($studentId)) return [];

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
                   t.teacher_id,
                   CONCAT(u.username) as teacher_name
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
function getClassGrades(int $studentId, int $classId): array
{
    // Verify parent has access to this student
    if (!parentHasAccessToStudent($studentId)) return [];

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

        if (!$enrollment) return [];

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
function getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null): array
{
    // Verify parent has access to this student
    if (!parentHasAccessToStudent($studentId)) return [];

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
                   p.period_id, p.period_date as date, p.period_label,
                   c.class_code, c.title as class_title, c.class_id,
                   s.name as subject_name, s.subject_id,
                   CONCAT(c.title, ' - ', s.name) as class_name
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
        foreach ($attendance as &$record) $record['status_label'] = getAttendanceStatusLabel($record['status']);

        return $attendance;
    } catch (PDOException $e) {
        logDBError("Error in getStudentAttendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if parent has access to a justification
 *
 * @param int $attId Attendance record ID
 * @return bool True if parent has access, false otherwise
 */
function parentHasAccessToJustification(int $attId): bool
{
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

        if (!$result) return false;

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
function getJustificationDetails(int $attId): ?array
{
    // Verify parent has access to this justification
    if (!parentHasAccessToJustification($attId)) return null;

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

        if (!$justification) return null;

        return $justification;
    } catch (PDOException $e) {
        logDBError("Error in getJustificationDetails: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all justifications for a student
 *
 * Retrieves all justifications for absences submitted by a student
 * that the parent has access to
 *
 * @param int $studentId Student ID
 * @return array Array of justification records
 */
function getStudentJustifications(int $studentId): array
{
    // Verify parent has access to this student
    if (!parentHasAccessToStudent($studentId)) return [];

    try {
        $pdo = safeGetDBConnection('getStudentJustifications');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getStudentJustifications");
            return [];
        }

        $query = "
            SELECT a.att_id, a.justification as reason, a.approved, 
                   p.period_date as absence_date,
                   p.period_date as submitted_date,
                   CASE 
                      WHEN a.approved IS NULL THEN 'pending'
                      WHEN a.approved = 1 THEN 'approved'
                      ELSE 'rejected'
                   END as status,
                   a.reject_reason,
                   subj.name as subject_name
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects subj ON cs.subject_id = subj.subject_id
            WHERE e.student_id = ? AND a.justification IS NOT NULL
            ORDER BY p.period_date DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getStudentJustifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Creates the HTML for the parent's dashboard widget, showing their children's attendance statistics and recent absences
 *
 * @return string HTML content for the widget
 */
function renderParentAttendanceWidget(): string
{
    $userId = getUserId();
    if (!$userId) return renderPlaceholderWidget('Za prikaz prisotnosti otrok se morate prijaviti.');

    try {
        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $parentInfo = getUserInfo($userId);
        if (!$parentInfo || empty($parentInfo['children'])) return renderPlaceholderWidget('Na vaš račun ni povezanih otrok.');

        $children = $parentInfo['children'];
        $html = '<div class="d-flex flex-column gap-lg" style="overflow-y: auto; max-height: 400px;">'; // Scrollable container for children

        if (empty($children)) $html .= renderPlaceholderWidget('Na vaš račun ni povezanih otrok.'); else foreach ($children as $child) {
            // Fetch stats (simplified from original for brevity, assuming logic is sound)
            $statsQuery = "SELECT COUNT(*) as total, SUM(IF(status = 'P', 1, 0)) as present, SUM(IF(status = 'A', 1, 0)) as absent, SUM(IF(status = 'L', 1, 0)) as late, SUM(IF(status = 'A' AND approved = 1, 1, 0)) as justified, SUM(IF(status = 'A' AND justification IS NOT NULL AND approved IS NULL, 1, 0)) as pending, SUM(IF(status = 'A' AND approved = 0, 1, 0)) as rejected FROM attendance a JOIN enrollments e ON a.enroll_id = e.enroll_id WHERE e.student_id = :student_id";
            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
            $statsStmt->execute();
            $s = $statsStmt->fetch(PDO::FETCH_ASSOC);
            $s['unjustified'] = ($s['absent'] ?? 0) - ($s['justified'] ?? 0) - ($s['pending'] ?? 0) - ($s['rejected'] ?? 0);
            $s['attendance_rate'] = ($s['total'] ?? 0) > 0 ? round((($s['present'] ?? 0) + ($s['late'] ?? 0)) / $s['total'] * 100, 1) : 0;

            $absenceQuery = "SELECT a.att_id, a.status, a.justification, a.approved, p.period_date, p.period_label, subj.name as subject_name FROM attendance a JOIN periods p ON a.period_id = p.period_id JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id JOIN subjects subj ON cs.subject_id = subj.subject_id JOIN enrollments e ON a.enroll_id = e.enroll_id WHERE e.student_id = :student_id AND a.status = 'A' ORDER BY p.period_date DESC LIMIT 3";
            $absenceStmt = $db->prepare($absenceQuery);
            $absenceStmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
            $absenceStmt->execute();
            $recentAbsences = $absenceStmt->fetchAll(PDO::FETCH_ASSOC);

            $html .= '<div class="rounded p-0 shadow-sm">'; // Child's attendance block
            $html .= '<div class="d-flex justify-between items-center p-md border-bottom">';
            $html .= '<h5 class="m-0 font-medium">' . htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) . '</h5>';
            $html .= '<span class="badge badge-secondary">' . htmlspecialchars($child['class_code']) . '</span>';
            $html .= '</div>';

            $html .= '<div class="p-md">';
            // Attendance Rate and Stats
            $html .= '<div class="row items-center gap-md mb-md">';
            $html .= '<div class="col-12 col-md-3 text-center">';
            $rateColor = $s['attendance_rate'] >= 95 ? 'text-success' : ($s['attendance_rate'] >= 85 ? 'text-warning' : 'text-error');
            $html .= '<div class="font-size-xxl font-bold ' . $rateColor . '">' . $s['attendance_rate'] . '%</div>';
            $html .= '<div class="text-sm text-secondary">Prisotnost</div>';
            $html .= '</div>';
            $html .= '<div class="col-12 col-md-8">';
            $html .= '<div class="row text-center text-sm">';
            $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . ($s['present'] ?? 0) . '</span><span class="text-secondary">Prisoten</span></div>';
            $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . ($s['late'] ?? 0) . '</span><span class="text-secondary">Zamuda</span></div>';
            $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . ($s['justified'] ?? 0) . '</span><span class="text-secondary">Opravičeno</span></div>';
            $html .= '<div class="col-6 col-lg-3 mb-sm"><span class="d-block font-medium">' . (max($s['unjustified'], 0)) . '</span><span class="text-secondary">Neopravičeno</span></div>';
            $html .= '</div>';
            if (($s['pending'] ?? 0) > 0) $html .= '<div class="text-center text-xs text-warning mt-xs">V obdelavi: ' . $s['pending'] . '</div>';
            $html .= '</div>';
            $html .= '</div>';

            // Recent Absences
            if (!empty($recentAbsences)) {
                $html .= '<h6 class="font-medium mb-sm mt-md pt-md border-top">Nedavne odsotnosti</h6>';
                $html .= '<ul class="list-unstyled m-0 p-0 d-flex flex-column gap-sm">';
                foreach ($recentAbsences as $absence) {
                    $statusBadge = getAttendanceStatusLabel($absence['status']); // Helper for badge class + text
                    $jStatus = $absence['approved'] === 1 ? '<span class="badge badge-success">Opravičeno</span>' : ($absence['approved'] === 0 ? '<span class="badge badge-error">Zavrnjeno</span>' : ($absence['justification'] ? '<span class="badge badge-warning">V obdelavi</span>' : '<span class="badge badge-secondary">Ni oddano</span>'));
                    $html .= '<li class="d-flex justify-between items-center text-sm py-xs border-bottom">';
                    $html .= '<span>' . date('d.m.Y', strtotime($absence['period_date'])) . ' - ' . htmlspecialchars($absence['subject_name']) . '</span>';
                    $html .= $jStatus;
                    $html .= '</li>';
                }
                $html .= '</ul>';
            } elseif (($s['absent'] ?? 0) > 0) $html .= '<p class="text-secondary text-center mt-md pt-md border-top">Ni nedavnih odsotnosti za prikaz.</p>';
            $html .= '</div>'; // end p-md

            $html .= '<div class="p-md text-right border-top">';
            $html .= '<a href="/uwuweb/parent/attendance.php?student_id=' . $child['student_id'] . '" class="btn btn-sm btn-secondary">Celotna evidenca</a>';
            $html .= '</div>';
            $html .= '</div>'; // end child block
        }
        $html .= '</div>'; // end main flex container
        return $html;
    } catch (PDOException $e) {
        error_log("Database error in renderParentAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o prisotnosti otrok.');
    }
}


/**
 * Creates the HTML for the parent's view of their child's class averages
 *
 * @return string HTML content for the widget
 */
function renderParentChildClassAveragesWidget(): string
{
    $userId = getUserId();
    if (!$userId) return renderPlaceholderWidget('Za prikaz povprečij se morate prijaviti.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    $parentInfo = getUserInfo($userId);
    if (!$parentInfo || empty($parentInfo['children'])) return renderPlaceholderWidget('Na vaš račun ni povezanih otrok.');

    $children = $parentInfo['children'];
    $html = '<div class="d-flex flex-column gap-lg" style="overflow-y: auto; max-height: 400px;">'; // Scrollable container

    foreach ($children as $child) {
        // Fetch grades (simplified from original, assuming logic is sound)
        $query = "SELECT s.name AS subject_name, c.title AS class_title, AVG(CASE WHEN gi.max_points > 0 THEN (g.points / gi.max_points) * 100 END) AS student_avg_score, (SELECT AVG(CASE WHEN gi_c.max_points > 0 THEN (g_c.points / gi_c.max_points) * 100 END) FROM enrollments e_c JOIN grades g_c ON e_c.enroll_id = g_c.enroll_id JOIN grade_items gi_c ON g_c.item_id = gi_c.item_id WHERE gi_c.class_subject_id = cs.class_subject_id) AS class_avg_score FROM enrollments e JOIN classes c ON e.class_id = c.class_id JOIN class_subjects cs ON c.class_id = cs.class_id JOIN subjects s ON cs.subject_id = s.subject_id LEFT JOIN grade_items gi ON gi.class_subject_id = cs.class_subject_id LEFT JOIN grades g ON g.item_id = gi.item_id AND e.enroll_id = g.enroll_id WHERE e.student_id = :student_id GROUP BY s.subject_id, s.name, c.class_id, c.title, cs.class_subject_id ORDER BY s.name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
        $stmt->execute();
        $childGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html .= '<div class="rounded p-0 shadow-sm">'; // Child's grades block
        $html .= '<div class="d-flex justify-between items-center p-md border-bottom">';
        $html .= '<h5 class="m-0 font-medium">' . htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) . '</h5>';
        $html .= '<span class="badge badge-secondary">' . htmlspecialchars($child['class_code']) . '</span>';
        $html .= '</div>';

        $html .= '<div class="p-0">'; // p-0 for table-responsive
        if (empty($childGrades) || !array_filter($childGrades, static fn($g) => $g['student_avg_score'] !== null)) $html .= '<div class="p-md text-center"><p class="m-0 text-secondary">Ni podatkov o ocenah.</p></div>'; else {
            $html .= '<div class="table-responsive">';
            $html .= '<table class="data-table w-100 text-sm">';
            $html .= '<thead><tr><th>Predmet</th><th class="text-center">Povprečje</th><th class="text-center">Povprečje razreda</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($childGrades as $grade) {
                if ($grade['student_avg_score'] === null && $grade['class_avg_score'] === null) continue;
                $sAvg = $grade['student_avg_score'] !== null ? number_format($grade['student_avg_score'], 1) . '%' : 'N/A';
                $cAvg = $grade['class_avg_score'] !== null ? number_format($grade['class_avg_score'], 1) . '%' : 'N/A';
                $sClass = $grade['student_avg_score'] === null ? '' : ($grade['student_avg_score'] >= 80 ? 'grade-high' : ($grade['student_avg_score'] >= 60 ? 'grade-medium' : 'grade-low'));
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($grade['subject_name']) . '<br><small class="text-disabled">' . htmlspecialchars($grade['class_title']) . '</small></td>';
                $html .= '<td class="text-center ' . $sClass . '">' . $sAvg . '</td>';
                $html .= '<td class="text-center">' . $cAvg . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table></div>';
        }
        $html .= '</div>'; // end p-0

        $html .= '<div class="p-md text-right border-top">';
        $html .= '<a href="/uwuweb/parent/grades.php?student_id=' . (int)$child['student_id'] . '" class="btn btn-sm btn-secondary">Vse ocene</a>';
        $html .= '</div>';
        $html .= '</div>'; // end child block
    }
    if (empty($children)) $html .= renderPlaceholderWidget('Na vaš račun ni povezanih otrok.');

    $html .= '</div>'; // end main flex container
    return $html;
}
