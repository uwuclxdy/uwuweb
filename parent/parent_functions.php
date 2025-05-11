<?php
/**
 * Parent Functions Library
 *
 * File path: /parent/parent_functions.php
 *
 * Provides parent-specific helper functions and dashboard widgets.
 * Core attendance, grade, and justification functions are now in /includes/functions.php.
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
            return null;
        }

        $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['parent_id'] : null;
    } catch (PDOException $e) {
        logDBError("Error in getParentId: " . $e->getMessage());
        return null;
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
            return [];
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
        return [];
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
            return false;
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
        return false;
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
 * Creates the HTML for the parent's attendance dashboard widget
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
            // Fetch stats (simplified)
            $statsQuery = "SELECT COUNT(*) as total, SUM(IF(status = 'P', 1, 0)) as present, SUM(IF(status = 'A', 1, 0)) as absent, SUM(IF(status = 'L', 1, 0)) as late, SUM(IF(status = 'A' AND approved = 1, 1, 0)) as justified, SUM(IF(status = 'A' AND justification IS NOT NULL AND approved IS NULL, 1, 0)) as pending, SUM(IF(status = 'A' AND approved = 0, 1, 0)) as rejected, SUM(IF(status = 'A' AND justification IS NULL AND approved IS NULL, 1, 0)) as needs_justification FROM attendance a JOIN enrollments e ON a.enroll_id = e.enroll_id WHERE e.student_id = :student_id";
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
        // Fetch grades (simplified)
        $query = "SELECT s.name AS subject_name, c.title AS class_title, cs.class_subject_id, AVG(CASE WHEN g_student.points IS NOT NULL AND gi_student.max_points > 0 THEN (g_student.points / gi_student.max_points) * 100 END) AS student_avg_score, (SELECT AVG(CASE WHEN gi_class.max_points > 0 THEN (g_class.points / gi_class.max_points) * 100 END) FROM enrollments e_class JOIN grades g_class ON e_class.enroll_id = g_class.enroll_id JOIN grade_items gi_class ON g_class.item_id = gi_class.item_id WHERE gi_class.class_subject_id = cs.class_subject_id) AS class_avg_score FROM enrollments e JOIN classes c ON e.class_id = c.class_id JOIN class_subjects cs ON c.class_id = cs.class_id JOIN subjects s ON cs.subject_id = s.subject_id LEFT JOIN grade_items gi_student ON gi_student.class_subject_id = cs.class_subject_id LEFT JOIN grades g_student ON g_student.item_id = gi_student.item_id AND e.enroll_id = g_student.enroll_id WHERE e.student_id = :student_id GROUP BY s.subject_id, s.name, c.class_id, c.title, cs.class_subject_id ORDER BY s.name";
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
            $html .= '<thead><tr><th>Predmet (Razred)</th><th class="text-center">Povprečje</th><th class="text-center">Povp. razreda</th><th class="text-center">Razlika</th></tr></thead><tbody>';
            foreach ($childGrades as $grade) {
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
