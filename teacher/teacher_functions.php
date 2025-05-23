<?php
/**
 * Teacher Functions Library
 * File path: /teacher/teacher_functions.php
 *
 * Provides teacher-specific helper functions and dashboard widgets.
 * Core attendance, grade, and justification functions are now in /includes/functions.php.
 *
 *
 * Utility Functions:
 * - findClassSubjectById(array $teacherClasses, int $classSubjectId): ?array - Finds a class-subject by its ID from the teacher's classes
 * - isHomeroomTeacher(int $teacherId, int $classId): bool - Checks if a teacher is the homeroom teacher for a specific class
 * - getAllAttendanceForClass(int $classId): array - Retrieves all attendance records for all students in a specific class
 *
 * Dashboard Widgets:
 * - renderTeacherClassOverviewWidget(): string - Creates the HTML for the teacher's class overview dashboard widget
 * - renderTeacherAttendanceWidget(): string - Shows attendance status for today's classes taught by the teacher
 * - renderTeacherPendingJustificationsWidget(): string - Shows absence justifications waiting for teacher approval
 * - renderTeacherClassAveragesWidget(): string - Creates the HTML for the teacher's class averages dashboard widget
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Finds a class-subject by its ID from the teacher's classes
 *
 * @param array $teacherClasses Array of classes the teacher has access to
 * @param int $classSubjectId The ID of the class-subject to find
 * @return array|null The selected class-subject or null if not found
 */
function findClassSubjectById(array $teacherClasses, int $classSubjectId): ?array
{
    // First check nested structure (classes with subjects array)
    foreach ($teacherClasses as $class) if (isset($class['subjects']) && is_array($class['subjects'])) foreach ($class['subjects'] as $subject) if (isset($subject['class_subject_id']) && $subject['class_subject_id'] == $classSubjectId) return [
        'class_id' => $class['class_id'] ?? null,
        'class_code' => $class['class_code'] ?? '',
        'class_title' => $class['title'] ?? '',
        'subject_id' => $subject['subject_id'] ?? null,
        'subject_name' => $subject['subject_name'] ?? '',
        'class_subject_id' => $subject['class_subject_id']
    ];

    // Then check flat structure (direct class-subject entries)
    foreach ($teacherClasses as $class) if (isset($class['class_subject_id']) && $class['class_subject_id'] == $classSubjectId) return [
        'class_id' => $class['class_id'] ?? null,
        'class_code' => $class['class_code'] ?? '',
        'class_title' => $class['title'] ?? $class['class_title'] ?? 'Razred',
        'subject_id' => $class['subject_id'] ?? null,
        'subject_name' => $class['subject_name'] ?? '',
        'class_subject_id' => $class['class_subject_id']
    ];

    return null;
}

/**
 * Creates the HTML for the teacher's class overview dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderTeacherClassOverviewWidget(): string
{
    $teacherId = getTeacherId();
    if (!$teacherId) return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

    $query = "SELECT cs.class_subject_id, c.class_code, c.title AS class_title, s.name AS subject_name, COUNT(DISTINCT e.student_id) AS student_count
              FROM class_subjects cs
              JOIN classes c   ON cs.class_id   = c.class_id
              JOIN subjects s  ON cs.subject_id = s.subject_id
              LEFT JOIN enrollments e ON c.class_id = e.class_id
              WHERE cs.teacher_id = :teacher_id
              GROUP BY cs.class_subject_id, c.class_code, c.title, s.name
              ORDER BY c.title, s.name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($classes)) return '<div class="d-flex flex-column h-full justify-center items-center text-disabled">
                <p>Ni dodeljenih razredov/predmetov</p>
            </div>';

    $html = '<div class="d-flex flex-column h-full">';

    // Classes section
    $html .= '<div class="rounded p-0 shadow mb-lg flex-grow-1 d-flex flex-column">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Dodeljeni razredi in predmeti</h5>';
    $html .= '<div class="px-md py-sm flex-grow-1 overflow-auto">';
    $html .= '<ul class="p-0 m-0">';

    foreach ($classes as $class) {
        $html .= '<li class="d-flex justify-between items-center py-sm border-bottom">';
        $html .= '<div>';
        $html .= '<div class="font-medium">' . htmlspecialchars($class['subject_name']) . '</div>';
        $html .= '<div class="text-sm text-secondary">' . htmlspecialchars($class['class_code']) . ' - ' . htmlspecialchars($class['class_title']) . '</div>';
        $html .= '</div>';
        $html .= '<div class="d-flex items-center">';
        $html .= '<span class="badge badge-primary">' . htmlspecialchars($class['student_count']) . ' učencev</span>';
        $html .= '</div>';
        $html .= '</li>';
    }

    $html .= '</ul>';
    $html .= '</div>';
    $html .= '</div>';

    $html .= '</div>'; // Close main container

    return $html;
}

/**
 * Shows attendance status for today's classes taught by the teacher
 *
 * @return string HTML content for the widget
 */
function renderTeacherAttendanceWidget(): string
{
    // todo: simplify the view to "[class_code] + [status on the right]" and make it linked to "/teacher/attendance.php?class_subject_id=[class_subject_id]&period_id=[period_id]"
    $teacherId = getTeacherId();
    if (!$teacherId) return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

    $query = "SELECT p.period_id, p.period_label, c.class_code, s.name AS subject_name, cs.class_subject_id,
                     (SELECT COUNT(*) FROM enrollments WHERE class_id = c.class_id)        AS total_students,
                     (SELECT COUNT(*) FROM attendance  WHERE period_id = p.period_id)      AS recorded_attendance
              FROM periods p
              JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
              JOIN classes c        ON cs.class_id        = c.class_id
              JOIN subjects s       ON cs.subject_id      = s.subject_id
              WHERE cs.teacher_id = :teacher_id AND DATE(p.period_date) = CURRENT_DATE()
              ORDER BY p.period_label";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
    $stmt->execute();
    $todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<div class="d-flex flex-column h-full">';

    // Today's classes header
    $html .= '<div class="rounded p-0 shadow mb-lg">';

    if (empty($todayClasses)) {
        $html .= '<div class="px-md py-lg d-flex flex-column items-center justify-center text-disabled">';
        $html .= '<p class="m-0">Danes nimate predvidenih ur</p>';
    } else {
        $html .= '<div class="px-md py-sm">';
        $html .= '<div class="d-flex flex-column gap-xs">';

        foreach ($todayClasses as $class) {
            $attendanceComplete = ($class['recorded_attendance'] == $class['total_students']);
            $attendanceStatus = $attendanceComplete ? 'status-success' : 'status-warning';
            $attendanceText = $attendanceComplete ? 'Vpisana prisotnost' : 'Manjka prisotnost';
            $attendanceProgress = $class['total_students'] > 0
                ? round(($class['recorded_attendance'] / $class['total_students']) * 100)
                : 0;

            $html .= '<div class="d-flex flex-column px-sm py-sm border rounded-sm ' . ($attendanceComplete ? 'border-success' : '') . '">';
            $html .= '<div class="d-flex justify-between items-center">';
            $html .= '<div class="font-medium">' . htmlspecialchars($class['period_label']) . '. ura - ' . htmlspecialchars($class['subject_name']) . '</div>';
            $html .= '<div class="badge ' . ($attendanceComplete ? 'badge-success' : 'badge-warning') . '">' . $attendanceText . '</div>';
            $html .= '</div>';

            $html .= '<div class="text-sm">' . htmlspecialchars($class['class_code']) . ' - ' . $class['recorded_attendance'] . '/' . $class['total_students'] . ' učencev</div>';

            // Progress bar
            $html .= '<div class="mt-xs w-full h-xs rounded-full bg-secondary">';
            $html .= '<div class="h-full rounded-full ' . ($attendanceComplete ? 'bg-success' : 'bg-warning') . '" style="width: ' . $attendanceProgress . '%"></div>';
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</div>'; // Close today's classes

    $html .= '</div>'; // Close main container

    return $html;
}

/**
 * Shows absence justifications waiting for teacher approval
 *
 * @return string HTML content for the widget
 */
function renderTeacherPendingJustificationsWidget(): string
{
    $teacherId = getTeacherId();
    if (!$teacherId) return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

    $limit = 5;
    $query = "SELECT a.att_id, s.first_name, s.last_name, c.class_code, p.period_date, p.period_label,
                     a.justification, a.justification_file, subj.name AS subject_name
              FROM attendance a
              JOIN enrollments e ON a.enroll_id = e.enroll_id
              JOIN students s    ON e.student_id   = s.student_id
              JOIN periods p     ON a.period_id    = p.period_id
              JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
              JOIN classes c     ON cs.class_id     = c.class_id
              JOIN subjects subj ON cs.subject_id   = subj.subject_id
              WHERE cs.teacher_id = :teacher_id AND a.status = 'A' AND a.justification IS NOT NULL AND a.approved IS NULL
              ORDER BY p.period_date DESC
              LIMIT :limit";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countQuery = "SELECT COUNT(*)
                   FROM attendance a
                   JOIN periods p ON a.period_id = p.period_id
                   JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                   WHERE cs.teacher_id = :teacher_id AND a.status = 'A' AND a.justification IS NOT NULL AND a.approved IS NULL";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
    $countStmt->execute();
    $totalPending = (int)$countStmt->fetchColumn();

    $html = '<div class="d-flex flex-column h-full">';

    // Stats section
    $html .= '<div class="rounded p-0 shadow mb-lg">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Pregled opravičil</h5>';
    $html .= '<div class="px-md py-md">';

    if ($totalPending > 0) {
        $html .= '<div class="d-flex justify-between items-center">';
        $html .= '<span>Čakajoča opravičila:</span>';
        $html .= '<span class="badge badge-primary">' . $totalPending . '</span>';
        $html .= '</div>';
    } else $html .= '<div class="text-center text-secondary">Ni čakajočih opravičil</div>';

    $html .= '</div>';
    $html .= '</div>';

    // Justifications section (expandable)
    $html .= '<div class="rounded p-0 shadow flex-grow-1 d-flex flex-column">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Zadnja opravičila</h5>';

    $html .= '<div class="flex-grow-1 overflow-auto">';

    if (empty($justifications)) {
        $html .= '<div class="px-md py-lg d-flex flex-column items-center justify-center text-disabled h-full">';
        $html .= '<p class="m-0">Trenutno ni čakajočih opravičil</p>';
    } else {
        $html .= '<div class="px-md py-sm">';

        foreach ($justifications as $item) {
            $html .= '<div class="border-bottom py-sm">';
            $html .= '<div class="d-flex justify-between">';
            $html .= '<div class="font-medium">' . htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) . '</div>';
            $html .= '<div class="text-sm badge badge-secondary">' . htmlspecialchars($item['class_code']) . '</div>';
            $html .= '</div>';

            $html .= '<div class="text-sm">' . formatDateDisplay($item['period_date']) . ' - ' . htmlspecialchars($item['period_label']) . '. ura - ' . htmlspecialchars($item['subject_name']) . '</div>';

            // Truncate justification text if too long
            $justificationText = htmlspecialchars($item['justification']);
            if (strlen($justificationText) > 100) $justificationText = substr($justificationText, 0, 97) . '...';

            $html .= '<div class="text-sm text-secondary mt-xs">' . $justificationText . '</div>';

            // File indicator and action buttons
            $html .= '<div class="d-flex justify-between items-center mt-xs">';

            // File indicator
            if (!empty($item['justification_file'])) $html .= '<div class="text-xs badge badge-info">Priložena datoteka</div>'; else $html .= '<div></div>';

            // Actions
            $html .= '<div class="d-flex gap-xs">';
            $html .= '<a href="/uwuweb/teacher/justifications.php?id=' . $item['att_id'] . '" class="btn btn-sm btn-primary">Preglej</a>';
            $html .= '</div>';

            $html .= '</div>'; // End actions row
            $html .= '</div>'; // End justification item
        }

    }
    $html .= '</div>';

    $html .= '</div>'; // End scrollable container

    // View all link
    if ($totalPending > 0) {
        $html .= '<div class="border-top p-sm d-flex justify-end">';
        $html .= '<a href="/uwuweb/teacher/justifications.php" class="text-accent text-sm">Preglej vsa opravičila (' . $totalPending . ')</a>';
        $html .= '</div>';
    }

    $html .= '</div>'; // End justifications section

    $html .= '</div>'; // Close main container

    return $html;
}

/**
 * Creates the HTML for the teacher's class averages dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderTeacherClassAveragesWidget(): string
{
    $teacherId = getTeacherId();
    if (!$teacherId) return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    $query = "SELECT cs.class_subject_id, c.title AS class_title, s.name AS subject_name,
                     COUNT(DISTINCT e.student_id) AS student_count,
                     (SELECT AVG(CASE WHEN gi.max_points > 0 THEN (g.points / gi.max_points) * 100 END)
                      FROM grades g
                      JOIN grade_items gi ON g.item_id = gi.item_id
                      WHERE gi.class_subject_id = cs.class_subject_id) AS avg_score
              FROM class_subjects cs
              JOIN classes c ON cs.class_id   = c.class_id
              JOIN subjects s ON cs.subject_id = s.subject_id
              LEFT JOIN enrollments e ON c.class_id = e.class_id
              WHERE cs.teacher_id = :teacher_id
              GROUP BY cs.class_subject_id, c.title, s.name
              ORDER BY s.name, c.title";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
    $stmt->execute();
    $classAverages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html = '<div class="d-flex flex-column h-full">';

    // Find best and worst classes
    $bestClass = null;
    $worstClass = null;
    $bestScore = -1;
    $worstScore = 101;

    foreach ($classAverages as $class) if ($class['avg_score'] !== null) {
        if ($class['avg_score'] > $bestScore) {
            $bestScore = $class['avg_score'];
            $bestClass = $class;
        }
        if ($class['avg_score'] < $worstScore) {
            $worstScore = $class['avg_score'];
            $worstClass = $class;
        }
    }

    // Performance highlights section
    if (!empty($classAverages) && $bestClass && $worstClass) {
        $html .= '<div class="rounded p-0 shadow mb-lg">';
        $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Pregled uspešnosti</h5>';
        $html .= '<div class="px-md py-sm">';

        // Best performing class
        $html .= '<div class="mb-md">';
        $html .= '<div class="text-sm text-secondary">Najvišja povprečna ocena</div>';
        $html .= '<div class="d-flex justify-between items-center">';
        $html .= '<div class="font-medium">' . htmlspecialchars($bestClass['subject_name']) . ' - ' . htmlspecialchars($bestClass['class_title']) . '</div>';

        $bestGrade = getGradeLetter($bestScore);
        $gradeClass = 'grade-' . $bestGrade;

        $html .= '<div class="badge ' . $gradeClass . '">' . round($bestScore, 1) . '% (' . $bestGrade . ')</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Lowest performing class
        $html .= '<div>';
        $html .= '<div class="text-sm text-secondary">Najnižja povprečna ocena</div>';
        $html .= '<div class="d-flex justify-between items-center">';
        $html .= '<div class="font-medium">' . htmlspecialchars($worstClass['subject_name']) . ' - ' . htmlspecialchars($worstClass['class_title']) . '</div>';

        $worstGrade = getGradeLetter($worstScore);
        $gradeClass = 'grade-' . $worstGrade;

        $html .= '<div class="badge ' . $gradeClass . '">' . round($worstScore, 1) . '% (' . $worstGrade . ')</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</div>';
    }

    // All classes section (expandable)
    $html .= '<div class="rounded p-0 shadow flex-grow-1 d-flex flex-column">';
    $html .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Povprečja po razredih</h5>';
    $html .= '<div class="px-md py-sm flex-grow-1 overflow-auto">';

    if (empty($classAverages)) {
        $html .= '<div class="d-flex flex-column items-center justify-center text-disabled h-full">';
        $html .= '<p class="m-0">Ni podatkov o razrednih povprečjih</p>';
    } else {
        $html .= '<div class="d-flex flex-column gap-xs">';

        // Group by subject for better organization
        $subjectGroups = [];
        foreach ($classAverages as $class) {
            if (!isset($subjectGroups[$class['subject_name']])) $subjectGroups[$class['subject_name']] = [];
            $subjectGroups[$class['subject_name']][] = $class;
        }

        foreach ($subjectGroups as $subjectName => $classes) {
            $html .= '<div class="mb-sm">';
            $html .= '<div class="font-medium mb-xs">' . htmlspecialchars($subjectName) . '</div>';

            foreach ($classes as $class) {
                // Calculate grade
                $gradeScore = $class['avg_score'] ?? 0;
                $grade = getGradeLetter($gradeScore);
                $scoreText = $class['avg_score'] !== null ? round($class['avg_score'], 1) . '%' : 'N/A';

                $gradeClass = $class['avg_score'] !== null ? 'grade-' . $grade : 'badge-secondary';

                $html .= '<div class="d-flex justify-between items-center py-xs border-bottom">';
                $html .= '<div class="d-flex items-center">';
                $html .= '<div>' . htmlspecialchars($class['class_title']) . '</div>';
                $html .= '<div class="text-sm text-secondary ml-sm">' . $class['student_count'] . ' učencev</div>';
                $html .= '</div>';

                $html .= '<div class="badge ' . $gradeClass . '">' . $scoreText . ($class['avg_score'] !== null ? ' (' . $grade . ')' : '') . '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

    }
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>'; // End all classes section

    $html .= '</div>'; // Close main container

    return $html;
}

/**
 * Checks if a teacher is the homeroom teacher for a specific class.
 *
 * @param int $teacherId The ID of the teacher.
 * @param int $classId The ID of the class.
 * @return bool True if the teacher is the homeroom teacher, false otherwise.
 */
function isHomeroomTeacher(int $teacherId, int $classId): bool
{
    $pdo = safeGetDBConnection('isHomeroomTeacher');
    if (!$pdo) return false;

    try {
        $stmt = $pdo->prepare("SELECT homeroom_teacher_id FROM classes WHERE class_id = :class_id");
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        return $class && isset($class['homeroom_teacher_id']) && $class['homeroom_teacher_id'] == $teacherId;
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return false;
    }
}

/**
 * Retrieves all attendance records for all students in a specific class.
 *
 * @param int $classId The ID of the class.
 * @return array An array of attendance records. Each record includes student_id.
 */
function getAllAttendanceForClass(int $classId): array
{
    $pdo = safeGetDBConnection('getAllAttendanceForClass');
    if (!$pdo) return [];

    try {
        // Fetches all attendance records for all students in a specific class
        // Joins with enrollments to link attendance to student_id via class_id
        $sql = "SELECT att.*, e.student_id
                FROM attendance att
                JOIN enrollments e ON att.enroll_id = e.enroll_id
                WHERE e.class_id = :class_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':class_id', $classId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return [];
    }
}

/**
 * Gets justifications for classes where the teacher is the homeroom teacher
 *
 * @param int|null $teacherId Teacher ID (uses current user if null)
 * @param bool $includingProcessed Whether to include processed justifications
 * @return array Array of justification records
 */
function getHomeroomTeacherJustifications(?int $teacherId = null, bool $includingProcessed = false): array
{
    if ($teacherId === null) $teacherId = getTeacherId();
    if (!$teacherId) return [];

    try {
        $pdo = safeGetDBConnection('getHomeroomTeacherJustifications');
        if ($pdo === null) return [];

        $query = "
            SELECT a.att_id, a.status, a.justification, a.justification_file, a.approved, a.reject_reason,
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
            WHERE c.homeroom_teacher_id = ?
              AND a.status != 'P'  -- Not present
              AND a.justification IS NOT NULL
        ";

        // Add condition for pending justifications if not including processed ones
        if (!$includingProcessed) $query .= " AND a.approved IS NULL"; else $query .= " AND a.approved IS NOT NULL";

        $query .= " ORDER BY p.period_date DESC, c.class_code";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$teacherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getHomeroomTeacherJustifications: " . $e->getMessage());
        return [];
    }
}

///**
// * Creates the HTML for the teacher's class overview dashboard widget
// *
// * @return string HTML content for the widget
// */
//function renderTeacherClassOverviewWidget(): string
//{
//    $teacherId = getTeacherId();
//    if (!$teacherId) {
//        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o učitelju.');
//    }
//
//    $classes = getTeacherClasses($teacherId);
//    if (empty($classes)) {
//        return renderPlaceholderWidget('Nimate dodeljenih razredov.');
//    }
//
//    $html = '<div class="card">';
//    $html .= '<div class="card__title">Pregled razredov</div>';
//    $html .= '<div class="card__content">';
//
//    foreach ($classes as $class) {
//        $html .= '<div class="mb-md">';
//        $html .= '<h4>' . htmlspecialchars($class['class_code']) . ' - ' . htmlspecialchars($class['title']) . '</h4>';
//
//        if (empty($class['subjects'])) {
//            $html .= '<p class="text-disabled">Ni dodeljenih predmetov.</p>';
//        } else {
//            $html .= '<ul class="ml-md">';
//            foreach ($class['subjects'] as $subject) {
//                $html .= '<li>' . htmlspecialchars($subject['name']) . '</li>';
//            }
//            $html .= '</ul>';
//        }
//
//        $html .= '</div>';
//    }
//
//    $html .= '</div>'; // card__content
//    $html .= '</div>'; // card
//
//    return $html;
//}
//
///**
// * Shows attendance status for today's classes taught by the teacher
// *
// * @return string HTML content for the widget
// */
//function renderTeacherAttendanceWidget(): string
//{
//    $teacherId = getTeacherId();
//    if (!$teacherId) {
//        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o učitelju.');
//    }
//
//    // Today's date in database format
//    $today = date('Y-m-d');
//
//    // Get classes and periods for today
//    try {
//        $pdo = safeGetDBConnection('renderTeacherAttendanceWidget');
//
//        $query = "
//            SELECT p.period_id, p.period_label, c.class_code, subj.name as subject_name,
//                   COUNT(DISTINCT e.student_id) as total_students,
//                   SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) as absent_count,
//                   SUM(CASE WHEN a.status = 'L' THEN 1 ELSE 0 END) as late_count
//            FROM periods p
//            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
//            JOIN classes c ON cs.class_id = c.class_id
//            JOIN subjects subj ON cs.subject_id = subj.subject_id
//            LEFT JOIN enrollments e ON cs.class_id = e.class_id
//            LEFT JOIN attendance a ON p.period_id = a.period_id AND e.enroll_id = a.enroll_id
//            WHERE cs.teacher_id = ? AND p.period_date = ?
//            GROUP BY p.period_id, p.period_label, c.class_code, subj.name
//            ORDER BY p.period_label
//        ";
//
//        $stmt = $pdo->prepare($query);
//        $stmt->execute([$teacherId, $today]);
//
//        $todayPeriods = $stmt->fetchAll(PDO::FETCH_ASSOC);
//    } catch (PDOException $e) {
//        logDBError("Error in renderTeacherAttendanceWidget: " . $e->getMessage());
//        $todayPeriods = [];
//    }
//
//    $html = '<div class="card">';
//    $html .= '<div class="card__title">Prisotnost danes</div>';
//    $html .= '<div class="card__content">';
//
//    if (empty($todayPeriods)) {
//        $html .= '<p>Danes nimate vpisanih ur.</p>';
//    } else {
//        $html .= '<table class="data-table">';
//        $html .= '<thead><tr><th>Ura</th><th>Razred</th><th>Predmet</th><th>Prisotnost</th></tr></thead>';
//        $html .= '<tbody>';
//
//        foreach ($todayPeriods as $period) {
//            $html .= '<tr>';
//            $html .= '<td>' . htmlspecialchars($period['period_label']) . '</td>';
//            $html .= '<td>' . htmlspecialchars($period['class_code']) . '</td>';
//            $html .= '<td>' . htmlspecialchars($period['subject_name']) . '</td>';
//
//            $totalStudents = (int)$period['total_students'];
//            $absentCount = (int)$period['absent_count'];
//            $lateCount = (int)$period['late_count'];
//            $presentCount = $totalStudents - $absentCount - $lateCount;
//
//            $attendanceHtml = '';
//            if ($totalStudents > 0) {
//                $attendanceHtml .= '<div class="d-flex gap-sm">';
//                if ($presentCount > 0) {
//                    $attendanceHtml .= '<span class="badge badge-success">' . $presentCount . ' prisotnih</span>';
//                }
//                if ($absentCount > 0) {
//                    $attendanceHtml .= '<span class="badge badge-error">' . $absentCount . ' odsotnih</span>';
//                }
//                if ($lateCount > 0) {
//                    $attendanceHtml .= '<span class="badge badge-warning">' . $lateCount . ' zamud</span>';
//                }
//                $attendanceHtml .= '</div>';
//            } else {
//                $attendanceHtml = '<span class="text-disabled">Ni podatkov</span>';
//            }
//
//            $html .= '<td>' . $attendanceHtml . '</td>';
//            $html .= '</tr>';
//        }
//
//        $html .= '</tbody>';
//        $html .= '</table>';
//    }
//
//    $html .= '</div>'; // card__content
//    $html .= '</div>'; // card
//
//    return $html;
//}
//
///**
// * Shows absence justifications waiting for teacher approval
// *
// * @return string HTML content for the widget
// */
//function renderTeacherPendingJustificationsWidget(): string
//{
//    $teacherId = getTeacherId();
//    if (!$teacherId) {
//        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o učitelju.');
//    }
//
//    $pendingJustifications = getHomeroomTeacherJustifications($teacherId);
//
//    $html = '<div class="card">';
//    $html .= '<div class="card__title">Čakajoča opravičila</div>';
//    $html .= '<div class="card__content">';
//
//    if (empty($pendingJustifications)) {
//        $html .= '<p>Trenutno ni čakajočih opravičil.</p>';
//    } else {
//        $html .= '<ul class="mt-0 mb-md">';
//        $count = 0;
//        foreach ($pendingJustifications as $item) {
//            if ($count++ >= 5) break; // Show only first 5
//
//            $html .= '<li class="mb-sm">';
//            $html .= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']);
//            $html .= ' (' . htmlspecialchars($item['class_code']) . ') - ';
//            $html .= formatDateDisplay($item['period_date']);
//            $html .= '</li>';
//        }
//        $html .= '</ul>';
//
//        $total = count($pendingJustifications);
//        if ($total > 5) {
//            $html .= '<p>+ ' . ($total - 5) . ' več</p>';
//        }
//
//        $html .= '<div class="text-center mt-md">';
//        $html .= '<a href="justifications.php" class="btn btn-primary">Preglej vsa opravičila</a>';
//        $html .= '</div>';
//    }
//
//    $html .= '</div>'; // card__content
//    $html .= '</div>'; // card
//
//    return $html;
//}
//
///**
// * Creates the HTML for the teacher's class averages dashboard widget
// *
// * @return string HTML content for the widget
// */
//function renderTeacherClassAveragesWidget(): string
//{
//    $teacherId = getTeacherId();
//    if (!$teacherId) {
//        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o učitelju.');
//    }
//
//    $classes = getTeacherClasses($teacherId);
//    if (empty($classes)) {
//        return renderPlaceholderWidget('Nimate dodeljenih razredov.');
//    }
//
//    $html = '<div class="card">';
//    $html .= '<div class="card__title">Povprečja razredov</div>';
//    $html .= '<div class="card__content">';
//
//    // Display data for up to 3 classes
//    $displayClasses = array_slice($classes, 0, 3);
//
//    foreach ($displayClasses as $class) {
//        if (empty($class['subjects'])) continue;
//
//        $html .= '<div class="mb-md">';
//        $html .= '<h4>' . htmlspecialchars($class['class_code']) . ' - ' . htmlspecialchars($class['title']) . '</h4>';
//
//        foreach ($class['subjects'] as $subject) {
//            // Get grades for this class-subject
//            $grades = getClassGrades($subject['class_subject_id']);
//            if (empty($grades)) {
//                $html .= '<p>' . htmlspecialchars($subject['name']) . ': <span class="text-disabled">Ni ocen</span></p>';
//                continue;
//            }
//
//            // Calculate average
//            $average = calculateClassAverage($grades);
//
//            // Determine color class based on average
//            $colorClass = '';
//            if ($average >= 4.5) $colorClass = 'text-success';
//            else if ($average >= 3.5) $colorClass = 'text-accent';
//            else if ($average >= 2.5) $colorClass = 'text-warning';
//            else $colorClass = 'text-error';
//
//            $html .= '<p>' . htmlspecialchars($subject['name']) . ': ';
//            $html .= '<span class="' . $colorClass . ' font-bold">' . number_format($average, 2) . '</span>';
//            $html .= '</p>';
//        }
//
//        $html .= '</div>';
//    }
//
//    // If there are more classes, show a message
//    if (count($classes) > 3) {
//        $html .= '<p class="text-center">+ ' . (count($classes) - 3) . ' več razredov</p>';
//    }
//
//    $html .= '</div>'; // card__content
//    $html .= '</div>'; // card
//
//    return $html;
//}
