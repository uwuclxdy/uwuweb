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
        if (!$parentInfo || empty($parentInfo['children'])) return renderPlaceholderWidget('Na vaš račun ni povezanih otrok ali podatkov o staršu.');
        $children = $parentInfo['children'];

        $html = '<div class="widget-content parent-attendance-widget">';

        foreach ($children as $child) {
            $childStats = [
                'student_id' => $child['student_id'],
                'name' => $child['first_name'] . ' ' . $child['last_name'],
                'class_code' => $child['class_code'],
                'total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0,
                'justified' => 0, 'rejected' => 0, 'pending' => 0, 'unjustified' => 0,
                'attendance_rate' => 0, 'recent_absences' => []
            ];

            $statsQuery = "SELECT
                COUNT(*) as total,
                SUM(IF(status = 'P', 1, 0)) as present,
                SUM(IF(status = 'A', 1, 0)) as absent,
                SUM(IF(status = 'L', 1, 0)) as late,
                SUM(IF(status = 'A' AND approved = 1, 1, 0)) as justified,
                SUM(IF(status = 'A' AND approved = 0, 1, 0)) as rejected,
                SUM(IF(status = 'A' AND justification IS NOT NULL AND approved IS NULL, 1, 0)) as pending
             FROM attendance a
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             WHERE e.student_id = :student_id";

            $statsStmt = $db->prepare($statsQuery);
            $statsStmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
            $statsStmt->execute();
            $statsResult = $statsStmt->fetch(PDO::FETCH_ASSOC);

            if ($statsResult) {
                $childStats['total'] = (int)$statsResult['total'];
                $childStats['present'] = (int)$statsResult['present'];
                $childStats['absent'] = (int)$statsResult['absent'];
                $childStats['late'] = (int)$statsResult['late'];
                $childStats['justified'] = (int)$statsResult['justified'];
                $childStats['rejected'] = (int)$statsResult['rejected'];
                $childStats['pending'] = (int)$statsResult['pending'];
                $childStats['unjustified'] = $childStats['absent'] - $childStats['justified'] - $childStats['rejected'] - $childStats['pending'];

                if ($childStats['total'] > 0) $childStats['attendance_rate'] = round((($childStats['present'] + $childStats['late']) / $childStats['total']) * 100, 1);
            }

            $absenceQuery = "SELECT
                a.att_id, a.status, a.justification, a.approved,
                p.period_date, p.period_label, s.name as subject_name
             FROM attendance a
             JOIN periods p ON a.period_id = p.period_id
             JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
             JOIN subjects s ON cs.subject_id = s.subject_id
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             WHERE e.student_id = :student_id AND a.status = 'A'
             ORDER BY p.period_date DESC, p.period_label DESC
             LIMIT 3";

            $absenceStmt = $db->prepare($absenceQuery);
            $absenceStmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
            $absenceStmt->execute();
            $childStats['recent_absences'] = $absenceStmt->fetchAll(PDO::FETCH_ASSOC);

            $html .= '<div class="child-attendance-summary card mb-lg">';
            $html .= '<div class="card__header p-md border-bottom d-flex justify-between items-center">';
            $html .= '<h5 class="card__title m-0 font-medium">' . htmlspecialchars($childStats['name']) . '</h5>';
            $html .= '<span class="badge badge-secondary">' . htmlspecialchars($childStats['class_code']) . '</span>';
            $html .= '</div>';

            $html .= '<div class="card__content p-lg">';

            $html .= '<div class="attendance-stats-row row align-items-center gap-lg mb-lg">';
            $html .= '<div class="mini-attendance-rate col-12 col-md-3 text-center">';
            $rateColorClass = match (true) {
                $childStats['attendance_rate'] >= 95 => 'text-success',
                $childStats['attendance_rate'] >= 85 => 'text-warning',
                default => 'text-error'
            };
            $html .= '<div class="mini-rate-circle mx-auto" data-percentage="' . $childStats['attendance_rate'] . '" style="width: 80px; height: 80px;">';
            $html .= '<svg viewBox="0 0 36 36" class="circular-chart mini ' . $rateColorClass . '">';
            $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
            $html .= '<path class="circle" stroke-dasharray="' . $childStats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
            $html .= '<text x="18" y="20.35" class="percentage">' . $childStats['attendance_rate'] . '%</text>';
            $html .= '</svg>';
            $html .= '</div>';
            $html .= '<span class="mini-label text-xs text-secondary mt-xs d-block">Prisotnost</span>';
            $html .= '</div>';

            $html .= '<div class="mini-stats col-12 col-md-8">';
            $html .= '<div class="row">';
            $html .= '<div class="col-6 col-lg-3 mb-md text-center"><span class="mini-count d-block font-size-md font-medium text-success">' . $childStats['present'] . '</span><span class="mini-label text-xs text-secondary">Prisoten</span></div>';
            $html .= '<div class="col-6 col-lg-3 mb-md text-center"><span class="mini-count d-block font-size-md font-medium text-warning">' . $childStats['late'] . '</span><span class="mini-label text-xs text-secondary">Zamuda</span></div>';
            $html .= '<div class="col-6 col-lg-3 mb-md text-center"><span class="mini-count d-block font-size-md font-medium text-info">' . $childStats['justified'] . '</span><span class="mini-label text-xs text-secondary">Opravičeno</span></div>';
            $html .= '<div class="col-6 col-lg-3 mb-md text-center"><span class="mini-count d-block font-size-md font-medium text-error">' . $childStats['unjustified'] . '</span><span class="mini-label text-xs text-secondary">Neopravičeno</span></div>';
            $html .= '</div>';
            if ($childStats['pending'] > 0 || $childStats['rejected'] > 0) {
                $html .= '<div class="row mt-xs">';
                if ($childStats['pending'] > 0) $html .= '<div class="col-6 text-center"><span class="text-xs text-secondary">V obdelavi: ' . $childStats['pending'] . '</span></div>';
                if ($childStats['rejected'] > 0) $html .= '<div class="col-6 text-center"><span class="text-xs text-secondary">Zavrnjeno: ' . $childStats['rejected'] . '</span></div>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';

            if (!empty($childStats['recent_absences'])) {
                $html .= '<div class="recent-absences border-top pt-lg">';
                $html .= '<h5 class="mb-md font-medium">Nedavne odsotnosti</h5>';
                $html .= '<ul class="absence-list list-unstyled p-0 m-0 d-flex flex-column gap-sm">';

                foreach ($childStats['recent_absences'] as $absence) {
                    $date = date('d.m.Y', strtotime($absence['period_date']));

                    if ($absence['approved'] === 1) {
                        $justificationStatus = 'Opravičeno';
                        $statusClass = 'badge-success';
                    } elseif ($absence['approved'] === 0) {
                        $justificationStatus = 'Zavrnjeno';
                        $statusClass = 'badge-error';
                    } elseif ($absence['justification'] !== null && $absence['approved'] === null) {
                        $justificationStatus = 'V obdelavi';
                        $statusClass = 'badge-warning';
                    } else {
                        $justificationStatus = 'Neopravičeno';
                        $statusClass = 'badge-error';
                    }

                    $html .= '<li class="d-flex justify-between items-center text-sm py-xs border-bottom">';
                    $html .= '<span class="d-flex items-center gap-xs"><span class="material-icons-outlined text-sm">calendar_today</span>' . htmlspecialchars($date) . '</span>';
                    $html .= '<span class="text-secondary">' . htmlspecialchars($absence['subject_name']) . ' (' . htmlspecialchars($absence['period_label']) . '. ura)</span>';
                    $html .= '<span><span class="badge ' . $statusClass . '">' . htmlspecialchars($justificationStatus) . '</span></span>';
                    $html .= '</li>';
                }

                $html .= '</ul>';
                $html .= '</div>';
            } elseif ($childStats['absent'] > 0) $html .= '<div class="recent-absences border-top pt-lg text-center text-secondary"><p>Ni nedavnih odsotnosti.</p></div>';

            $html .= '</div>';

            $html .= '<div class="card__footer p-md text-right border-top pt-md">';
            $html .= '<a href="/uwuweb/parent/attendance.php?student_id=' . $childStats['student_id'] . '" class="btn btn-sm btn-secondary">Celotna evidenca</a>';
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;

    } catch (PDOException $e) {
        error_log("Database error in renderParentAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o prisotnosti otrok.');
    } catch (Exception $e) {
        error_log("Error in renderParentAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Prišlo je do napake.');
    }
}

/**
 * Creates the HTML for the parent's view of their child's class averages
 * Shows academic performance for each child compared to class averages
 *
 * @return string HTML content for the widget
 */
function renderParentChildClassAveragesWidget(): string
{
    $userId = getUserId();
    if (!$userId) return renderPlaceholderWidget('Za prikaz povprečij razredov se morate prijaviti.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    $parentInfo = getUserInfo($userId);

    if (!$parentInfo || empty($parentInfo['children'])) return renderPlaceholderWidget('Na vaš račun ni povezanih otrok ali podatkov o staršu.');
    $children = $parentInfo['children'];


    $html = '<div class="widget-content">';

    foreach ($children as $child) {
        $html .= '<div class="child-grades-section card mb-lg">';
        $html .= '<div class="card__header p-md border-bottom d-flex justify-between items-center">';
        $html .= '<h5 class="card__title m-0 font-medium">' . htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) . '</h5>';
        $html .= '<span class="badge badge-secondary">' . htmlspecialchars($child['class_code']) . '</span>';
        $html .= '</div>';

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
                      FROM enrollments e
                      JOIN classes c ON e.class_id = c.class_id
                      JOIN class_subjects cs ON c.class_id = cs.class_id
                      JOIN subjects s ON cs.subject_id = s.subject_id
                      LEFT JOIN grade_items gi_student ON gi_student.class_subject_id = cs.class_subject_id
                      LEFT JOIN grades g_student ON g_student.item_id = gi_student.item_id AND e.enroll_id = g_student.enroll_id
                      WHERE e.student_id = :student_id
                      GROUP BY s.subject_id, s.name, c.class_id, c.title, cs.class_subject_id
                      ORDER BY s.name, c.title";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
            $stmt->execute();
            $childGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in renderParentChildClassAveragesWidget (getting grades for child " . $child['student_id'] . "): " . $e->getMessage());
            $html .= '<div class="card__content p-md"><p class="text-error m-0">Napaka pri pridobivanju podatkov o ocenah.</p></div>';
            $html .= '</div>';
            continue;
        }

        $html .= '<div class="card__content p-0">';

        if (empty($childGrades) || !array_filter($childGrades, static fn($g) => $g['student_avg_score'] !== null)) $html .= '<div class="p-md text-center"><p class="m-0 text-secondary">Za tega otroka ni podatkov o ocenah.</p></div>'; else {
            $html .= '<div class="child-grades-table table-responsive">';
            $html .= '<table class="data-table w-100">';
            $html .= '<thead><tr><th>Predmet</th><th class="text-center">Povprečje</th><th class="text-center">Povprečje razreda</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($childGrades as $grade) {
                if ($grade['student_avg_score'] === null && $grade['class_avg_score'] === null) continue;

                $studentAvgFormatted = $grade['student_avg_score'] !== null ? number_format($grade['student_avg_score'], 1) . '%' : 'N/A';
                $classAvgFormatted = $grade['class_avg_score'] !== null ? number_format($grade['class_avg_score'], 1) . '%' : 'N/A';

                $scoreClass = '';
                if ($grade['student_avg_score'] !== null) if ($grade['student_avg_score'] >= 80) $scoreClass = 'grade-high'; elseif ($grade['student_avg_score'] >= 60) $scoreClass = 'grade-medium';
                else $scoreClass = 'grade-low';

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($grade['subject_name']) . '<br><small class="text-disabled">' . htmlspecialchars($grade['class_title']) . '</small></td>';
                $html .= '<td class="text-center ' . $scoreClass . '">' . $studentAvgFormatted . '</td>';
                $html .= '<td class="text-center">' . $classAvgFormatted . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '<div class="card__footer p-md text-right border-top pt-md">';
        $html .= '<a href="/uwuweb/parent/grades.php?student_id=' . (int)$child['student_id'] . '" class="btn btn-sm btn-secondary">Ogled vseh ocen</a>';
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}
