<?php
/**
 * Teacher Functions Library
 *
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

    $html = '<div class="d-flex flex-column h-full">';
    $html .= '  <div class="rounded p-0 shadow flex-grow-1 d-flex flex-column">';
    $html .= '    <h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Pregled mojih predmetov/razredov</h5>';
    $html .= '    <div class="p-0 flex-grow-1" style="overflow-y:auto;">';

    if (empty($classes)) $html .= '      <p class="p-md text-secondary text-center">Trenutno ne poučujete nobenega predmeta/razreda.</p>'; else {
        $html .= '      <ul class="list-unstyled m-0">';
        foreach ($classes as $class) {
            $html .= '        <li class="p-md border-bottom">';
            $html .= '          <div class="d-flex justify-between items-center mb-sm">';
            $html .= '<span class="font-medium">' . htmlspecialchars($class['class_title']) . ' (' . htmlspecialchars($class['subject_name']) . ')</span>';
            $html .= '<span class="badge badge-secondary">' . htmlspecialchars($class['class_code']) . '</span>';
            $html .= '          </div>';
            $html .= '          <div class="d-flex justify-between items-center text-sm text-secondary mb-md">';
            $html .= '<span>' . htmlspecialchars($class['student_count']) . ' dijakov</span>';
            $html .= '          </div>';
            $html .= '          <div class="d-flex gap-sm justify-end">';
            $html .= '<a href="/uwuweb/teacher/gradebook.php?class_id=' . (int)$class['class_subject_id'] . '" class="btn btn-sm btn-primary">Redovalnica</a>';
            $html .= '<a href="/uwuweb/teacher/attendance.php?class_id=' . (int)$class['class_subject_id'] . '" class="btn btn-sm btn-secondary">Prisotnost</a>';
            $html .= '          </div>';
            $html .= '        </li>';
        }
        $html .= '      </ul>';
    }

    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';

    return $html;
}

/**
 * Shows attendance status for today's classes taught by the teacher
 *
 * @return string HTML content for the widget
 */
function renderTeacherAttendanceWidget(): string
{
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
    $html .= '  <div class="rounded p-0 shadow flex-grow-1 d-flex flex-column">';
    $html .= '    <h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Današnja prisotnost</h5>';

    $html .= '    <div class="p-0 flex-grow-1" style="overflow-y:auto;">';
    if (empty($todayClasses)) $html .= '      <p class="p-md text-secondary text-center">Danes nimate načrtovanega pouka.</p>'; else {
        $html .= '      <div class="table-responsive">';
        $html .= '        <table class="data-table w-100 text-sm">';
        $html .= '          <thead><tr><th>Ura</th><th>Razred</th><th>Predmet</th><th class="text-center">Status</th><th class="text-right">Akcija</th></tr></thead><tbody>';

        foreach ($todayClasses as $class) {
            $recorded = (int)$class['recorded_attendance'];
            $total = (int)$class['total_students'];

            // Determine status label & style
            if ($total === 0) {
                $statusText = 'Ni dijakov';
                $statusClass = 'badge-secondary';
                $btnClass = 'btn-secondary';
                $btnLabel = 'Pogled';
            } elseif ($recorded >= $total) {
                $statusText = 'Zabeleženo';
                $statusClass = 'badge-success';
                $btnClass = 'btn-secondary';
                $btnLabel = 'Pogled';
            } elseif ($recorded > 0) {
                $statusText = 'Delno (' . round($recorded / $total * 100) . '%)';
                $statusClass = 'badge-warning';
                $btnClass = 'btn-primary';
                $btnLabel = 'Uredi';
            } else {
                $statusText = 'Ne vneseno';
                $statusClass = 'badge-error';
                $btnClass = 'btn-primary';
                $btnLabel = 'Uredi';
            }

            $html .= '            <tr>';
            $html .= '              <td>' . htmlspecialchars($class['period_label']) . '. ura</td>';
            $html .= '              <td>' . htmlspecialchars($class['class_code']) . '</td>';
            $html .= '              <td>' . htmlspecialchars($class['subject_name']) . '</td>';
            $html .= '              <td class="text-center"><span class="badge ' . $statusClass . '">' . $statusText . '</span></td>';
            $html .= '              <td class="text-right">';
            $html .= '                <a href="/uwuweb/teacher/attendance.php?class_subject_id=' . (int)$class['class_subject_id'] . '&period_id=' . (int)$class['period_id'] . '" class="btn btn-xs ' . $btnClass . '">' . $btnLabel . '</a>';
            $html .= '              </td>';
            $html .= '            </tr>';
        }
        $html .= '          </tbody></table>';
        $html .= '      </div>';// /table-responsive
    }
    $html .= '    </div>';

    $html .= '    <div class="p-md border-top text-right mt-auto">';
    $html .= '      <a href="/uwuweb/teacher/attendance.php" class="btn btn-sm btn-secondary">Vsa prisotnost</a>';
    $html .= '    </div>';

    $html .= '  </div>';
    $html .= '</div>';

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
    $html .= '  <div class="rounded p-0 shadow flex-grow-1 d-flex flex-column">';

    $html .= '    <div class="d-flex justify-between items-center px-md py-sm border-bottom">';
    $html .= '      <h5 class="m-0 card-subtitle font-medium">Čakajoča opravičila</h5>';
    if ($totalPending > 0) $html .= '  <span class="badge badge-warning">' . $totalPending . '</span>';
    $html .= '    </div>';

    $html .= '    <div class="p-0 flex-grow-1" style="overflow-y:auto;">';
    if ($totalPending === 0) $html .= '      <p class="p-md text-secondary text-center">Trenutno ni čakajočih opravičil.</p>'; else {
        $html .= '      <ul class="list-unstyled m-0">';
        foreach ($justifications as $just) {
            $html .= '        <li class="p-md border-bottom">';
            $html .= '          <div class="d-flex justify-between items-center mb-sm">';
            $html .= '            <strong class="font-medium">' . htmlspecialchars($just['first_name'] . ' ' . $just['last_name']) . '</strong>';
            $html .= '            <span class="badge badge-secondary">' . htmlspecialchars($just['class_code']) . '</span>';
            $html .= '          </div>';
            $html .= '          <div class="text-sm text-secondary mb-sm">';
            $html .= 'Datum: ' . date('d.m.Y', strtotime($just['period_date'])) . ' (' . htmlspecialchars($just['period_label']) . '. ura, ' . htmlspecialchars($just['subject_name']) . ')';
            $html .= '          </div>';
            if (!empty($just['justification'])) $html .= '      <p class="text-sm fst-italic p-sm rounded bg-tertiary mb-sm">"' . htmlspecialchars(mb_strimwidth($just['justification'], 0, 70, '...')) . '"</p>';
            if (!empty($just['justification_file'])) $html .= '      <div class="text-sm text-secondary mb-md">Priloga na voljo</div>';
            $html .= '          <div class="d-flex gap-sm justify-end">';
            $html .= '            <a href="/uwuweb/teacher/justifications.php?action=view&id=' . (int)$just['att_id'] . '" class="btn btn-xs btn-secondary">Pogled</a>';
            $html .= '            <a href="/uwuweb/teacher/justifications.php?action=approve&id=' . (int)$just['att_id'] . '" class="btn btn-xs btn-success">Odobri</a>';
            $html .= '            <a href="/uwuweb/teacher/justifications.php?action=reject&id=' . (int)$just['att_id'] . '" class="btn btn-xs btn-error">Zavrni</a>';
            $html .= '          </div>';
            $html .= '        </li>';
        }
        $html .= '      </ul>';
    }
    $html .= '    </div>';

    if ($totalPending > $limit) {
        $html .= '  <div class="p-md border-top text-center mt-auto">';
        $html .= '    <a href="/uwuweb/teacher/justifications.php" class="btn btn-sm btn-secondary">Prikaži vsa (' . $totalPending . ')</a>';
        $html .= '  </div>';
    }

    $html .= '  </div>';
    $html .= '</div>';

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
    $html .= '  <div class="rounded p-0 shadow flex-grow-1 d-flex flex-column">';
    $html .= '    <h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Povprečja mojih razredov</h5>';
    $html .= '    <div class="p-md flex-grow-1" style="overflow-y:auto;">';

    // todo: use all 5 grade colors
    if (empty($classAverages) || !array_filter($classAverages, static fn($ca) => $ca['avg_score'] !== null)) $html .= '      <p class="text-secondary text-center">Nimate razredov z ocenami za prikaz povprečij.</p>'; else {
        $html .= '      <div class="row gap-md">';
        foreach ($classAverages as $class) {
            if ($class['avg_score'] === null) continue;
            $avg = (float)$class['avg_score'];
            $avgF = number_format($avg, 1);
            $badge = $avg >= 80 ? 'grade-5' : ($avg >= 60 ? 'grade-3' : 'grade-1');

            $html .= '        <div class="col-12 col-md-6 mb-md">';
            $html .= '          <div class="rounded p-0 shadow h-100 d-flex flex-column">';
            $html .= '            <div class="d-flex justify-between items-center p-md border-bottom">';
            $html .= '              <h6 class="m-0 font-medium">' . htmlspecialchars($class['subject_name']) . '</h6>';
            $html .= '              <span class="badge badge-secondary">' . htmlspecialchars($class['class_title']) . '</span>';
            $html .= '            </div>';
            $html .= '            <div class="p-md flex-grow-1 d-flex flex-column justify-center items-center text-center">';
            $html .= '              <div class="' . $badge . ' mb-sm">';
            $html .= '                <span class="font-size-xl font-bold">' . $avgF . '%</span><br>';
            $html .= '                <span class="text-sm text-secondary">Povprečje</span>';
            $html .= '              </div>';
            $html .= '              <div class="text-sm">';
            $html .= '                <span class="font-medium">' . (int)$class['student_count'] . '</span> <span class="text-secondary">dijakov</span>';
            $html .= '              </div>';
            $html .= '            </div>';
            $html .= '            <div class="p-md border-top text-right mt-auto">';
            $html .= '              <a href="/uwuweb/teacher/gradebook.php?class_subject_id=' . (int)$class['class_subject_id'] . '" class="btn btn-sm btn-primary">Redovalnica</a>';
            $html .= '            </div>';
            $html .= '          </div>';// /mini-card
            $html .= '        </div>';// /col
        }
        $html .= '      </div>';// /row
    }

    $html .= '    </div>';
    $html .= '  </div>';
    $html .= '</div>';

    return $html;
}

///**
// * Creates the HTML for the teacher's class overview dashboard widget.
// * @return string HTML content for the widget.
// */
//function renderTeacherClassOverviewWidget(): string
//{
//    // Implementation depends on what data needs to be shown.
//    // Example: List of classes, number of students.
//    // This function might need access to getTeacherClasses().
//    $teacherId = getTeacherId();
//    if (!$teacherId) return renderPlaceholderWidget('Podatki o razredih niso na voljo.');
//
//    $classes = getTeacherClasses($teacherId);
//    if (empty($classes)) {
//        return renderPlaceholderWidget('Nimate dodeljenih nobenih predmetov ali razredov.');
//    }
//
//    $html = '<ul class="list-unstyled">';
//    foreach ($classes as $class) {
//        $html .= '<li class="mb-sm">';
//        $html .= '<a href="/teacher/gradebook.php?class_subject_id=' . $class['class_subject_id'] . '" class="text-primary hover-underline">';
//        $html .= htmlspecialchars($class['class_title'] . ' - ' . $class['subject_name']);
//        $html .= '</a>';
//        // Could add more info, e.g., student count if readily available
//        $html .= '</li>';
//    }
//    $html .= '</ul>';
//    return $html;
//}
//
///**
// * Shows attendance status for today's classes taught by the teacher.
// * This is a simplified version for a dashboard widget.
// * @return string HTML content for the widget.
// */
//function renderTeacherAttendanceWidget(): string
//{
//    $teacherId = getTeacherId();
//    if (!$teacherId) return renderPlaceholderWidget('Podatki o prisotnosti niso na voljo.');
//
//    $pdo = safeGetDBConnection();
//    // This is a conceptual query; actual implementation might be more complex
//    // to find "today's classes" and their status.
//    // For simplicity, this might link to the main attendance page.
//
//    // Let's list subjects and link to attendance page
//    $teacherClasses = getTeacherClasses($teacherId);
//    if (empty($teacherClasses)) {
//        return renderPlaceholderWidget('Nimate urnika za danes ali nimate dodeljenih predmetov.');
//    }
//
//    $html = '<p class="text-secondary mb-md">Hitri dostop do vodenja prisotnosti za vaše predmete.</p>';
//    $html .= '<ul class="list-styled">';
//    foreach ($teacherClasses as $class) {
//        $html .= '<li><a href="/teacher/attendance.php?class_subject_id=' . $class['class_subject_id'] . '">';
//        $html .= htmlspecialchars($class['class_title'] . ' - ' . $class['subject_name']) . '</a></li>';
//    }
//    $html .= '</ul>';
//
//    return $html;
//}
//
//
///**
// * Shows absence justifications waiting for teacher approval.
// * @return string HTML content for the widget.
// */
//function renderTeacherPendingJustificationsWidget(): string
//{
//    $teacherId = getTeacherId();
//    if (!$teacherId) return renderPlaceholderWidget('Podatki o opravičilih niso na voljo.');
//
//    $pendingJustifications = getPendingJustifications($teacherId);
//
//    if (empty($pendingJustifications)) {
//        return '<p>Ni čakajočih opravičil.</p>';
//    }
//
//    $html = '<ul class="list-unstyled">';
//    foreach ($pendingJustifications as $justification) {
//        // Note: Structure of $justification depends on getPendingJustifications()
//        $studentName = htmlspecialchars($justification['student_first_name'] . ' ' . $justification['student_last_name']);
//        $subjectName = htmlspecialchars($justification['subject_name']);
//        $periodDate = formatDateDisplay($justification['period_date']);
//        $html .= '<li class="mb-sm p-sm border rounded-sm">';
//        $html .= "<strong>{$studentName}</strong> - {$subjectName} ({$periodDate})";
//        $html .= ' <a href="/teacher/justifications.php?absence_id=' . $justification['att_id'] . '" class="btn btn-secondary btn-sm ml-sm">Preglej</a>';
//        $html .= '</li>';
//    }
//    $html .= '</ul>';
//    $html .= '<div class="mt-md"><a href="/teacher/justifications.php" class="btn btn-primary">Vsa Opravičila</a></div>';
//    return $html;
//}
//
///**
// * Creates the HTML for the teacher's class averages dashboard widget.
// * @return string HTML content for the widget.
// */
//function renderTeacherClassAveragesWidget(): string
//{
//    $teacherId = getTeacherId();
//    if (!$teacherId) return renderPlaceholderWidget('Podatki o povprečjih niso na voljo.');
//
//    $teacherClasses = getTeacherClasses($teacherId);
//    if (empty($teacherClasses)) {
//        return renderPlaceholderWidget('Nimate dodeljenih predmetov za prikaz povprečij.');
//    }
//
//    $html = '<ul class="list-unstyled">';
//    foreach ($teacherClasses as $cs) {
//        // Fetch grades for this class_subject_id
//        $grades = getClassGrades($cs['class_subject_id']); // This function gets all grades for all students
//
//        $classAverage = 0;
//        if (!empty($grades)) {
//            $classAverage = calculateClassAverage($grades); // This function needs to be robust
//        }
//
//        $html .= '<li class="d-flex justify-between items-center mb-sm p-sm border rounded-sm">';
//        $html .= '<span>' . htmlspecialchars($cs['class_title'] . ' - ' . $cs['subject_name']) . '</span>';
//        if ($classAverage > 0) {
//            $html .= '<span class="badge badge-info">' . number_format($classAverage, 2) . '%</span>';
//        } else {
//            $html .= '<span class="badge badge-secondary">Ni ocen</span>';
//        }
//        $html .= '</li>';
//    }
//    $html .= '</ul>';
//    return $html;
//}

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
