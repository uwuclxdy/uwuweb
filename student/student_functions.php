<?php
/**
 * Student Functions Library
 *
 * File path: /student/student_functions.php
 *
 * Provides student-specific helper functions and dashboard widgets.
 * Core attendance, grade, and justification functions are now in /includes/functions.php.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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
        if ($subjectData['total_max_points'] > 0 && $subjectData['total_weight'] > 0) $subjectData['weighted_average'] = ($subjectData['total_points'] / $subjectData['total_max_points']) * 100; else $subjectData['weighted_average'] = 0.0;

        foreach ($subjectData['classes'] as &$classData) if ($classData['total_max_points'] > 0 && $classData['total_weight'] > 0) $classData['weighted_average'] = ($classData['total_points'] / $classData['total_max_points']) * 100; else $classData['weighted_average'] = 0.0;
        unset($classData); // Release reference
    }
    unset($subjectData); // Release reference

    return $statistics;
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
            if ($rec['status'] == 'A') if ($rec['approved'] === 1) $justHtml = '<span class="badge badge-success">Opravičeno</span>'; elseif ($rec['approved'] === 0) $justHtml = '<span class="badge badge-error">Zavrnjeno</span>';
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
        $html .= '<thead><tr><th>Predmet (Razred)</th><th class="text-center">Vaše povprečje</th><th class="text-center">Povprečje razreda</th><th class="text-center">Razlika</th></tr></thead><tbody>';
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
                $html .= '<div class="text-secondary">Prof.: ' . htmlspecialchars($class['teacher_name']) . '</div>';
                $html .= '<div class="text-secondary">Razred: ' . htmlspecialchars($class['class_title']) . '</div>';
                $html .= '</div></div>';
            }
            $html .= '</div></div>';
        }
    }
    $html .= '</div></div></div>';
    return $html;
}
