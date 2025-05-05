<?php /** @noinspection PhpUnused */

/**
 * Common Utility Functions Library
 *
 * This file contains utility functions used throughout the uwuweb application.
 * It serves as a central repository for common functionality that can be reused
 * across different parts of the application.
 *
 * File path: /includes/functions.php
 *
 * Functions:
 * User and Role Management:
 * - getUserInfo(int $userId): array|null - Retrieves user profile information by user ID
 * - getUserId(): int|null - Gets current user ID from session
 * - getUserRole(): int|null - Gets the role ID of the current user from session
 * - getStudentId(): int|null - Gets student ID for current user
 * - getRoleName(int $roleId): string - Returns the name of a role by ID
 *
 * Security Functions:
 * - sendJsonErrorResponse(string $message, int $statusCode = 400, string $context = ''): never - Sends a standardized JSON error response and exits
 *
 * Navigation and Widgets:
 * - getNavItemsByRole(int $role): array - Returns navigation items based on user role
 * - getWidgetsByRole(int $role): array - Returns dashboard widgets based on user role
 * - renderPlaceholderWidget(string $message = 'Podatki trenutno niso na voljo.'): string - Renders a placeholder widget
 *
 * Activity Widgets:
 * - renderRecentActivityWidget(): string - Renders the recent activity widget
 *
 * Admin Widgets:
 * - renderAdminUserStatsWidget(): string - Renders user statistics widget for administrators
 * - renderAdminSystemStatusWidget(): string - Renders system status widget for administrators
 * - renderAdminAttendanceWidget(): string - Renders school-wide attendance statistics widget
 *
 * Teacher Widgets:
 * - renderTeacherClassOverviewWidget(): string - Renders class overview widget for teachers
 * - renderTeacherAttendanceWidget(): string - Renders daily attendance widget for teachers
 * - renderTeacherPendingJustificationsWidget(): string - Renders pending justifications widget
 * - renderTeacherClassAveragesWidget(): string - Renders class averages widget for teacher's classes
 *
 * Student Widgets:
 * - renderStudentGradesWidget(): string - Renders student grades dashboard widget
 * - renderStudentAttendanceWidget(): string - Renders attendance summary widget for students
 * - renderStudentClassAveragesWidget(): string - Renders class averages widget for student's classes
 * - renderUpcomingClassesWidget(): string - Renders upcoming classes widget for students
 *
 * Parent Widgets:
 * - renderParentAttendanceWidget(): string - Renders attendance summary widget for parents
 * - renderParentChildClassAveragesWidget(): string - Renders class averages widget for parent's children
 *
 * Attendance Utilities:
 * - getAttendanceStatusLabel(string $status): string - Converts attendance status code to readable label
 * - calculateAttendanceStats(array $attendance): array - Calculates attendance statistics from a set of records
 */

require_once __DIR__ . '/auth.php';

/**
 * Retrieves user profile information including username, role, and role name
 *
 * @param int $userId The user ID to look up
 * @return array|null User information array or null if not found
 */
function getUserInfo(int $userId): ?array
{
    try {
        $db = getDBConnection();
        if (!$db) return null;

        $db->beginTransaction();

        $query = "SELECT u.user_id, u.username, u.role_id, r.name as role_name
                  FROM users u
                  JOIN roles r ON u.role_id = r.role_id
                  WHERE u.user_id = :user_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $db->rollBack();
            return null;
        }

        switch ($user['role_id']) {
            case 2: // Teacher
                $teacherQuery = "SELECT t.teacher_id
                               FROM teachers t
                               WHERE t.user_id = :user_id";
                $teacherStmt = $db->prepare($teacherQuery);
                $teacherStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $teacherStmt->execute();
                $teacherInfo = $teacherStmt->fetch(PDO::FETCH_ASSOC);
                if ($teacherInfo) $user['teacher_id'] = $teacherInfo['teacher_id'];
                break;

            case 3: // Student
                $studentQuery = "SELECT s.student_id, s.first_name, s.last_name, s.class_code
                                FROM students s
                                WHERE s.user_id = :user_id";
                $studentStmt = $db->prepare($studentQuery);
                $studentStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $studentStmt->execute();
                $studentInfo = $studentStmt->fetch(PDO::FETCH_ASSOC);
                if ($studentInfo) $user = array_merge($user, $studentInfo);
                break;

            case 4: // Parent
                $parentQuery = "SELECT p.parent_id
                              FROM parents p
                              WHERE p.user_id = :user_id";
                $parentStmt = $db->prepare($parentQuery);
                $parentStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $parentStmt->execute();
                $parentInfo = $parentStmt->fetch(PDO::FETCH_ASSOC);

                if ($parentInfo) {
                    $user['parent_id'] = $parentInfo['parent_id'];

                    $childrenQuery = "SELECT
                                    s.student_id,
                                    s.first_name,
                                    s.last_name,
                                    s.class_code,
                                    s.dob,
                                    c.title as class_name,
                                    c.class_id
                                    FROM students s
                                    JOIN student_parent sp ON s.student_id = sp.student_id
                                    LEFT JOIN classes c ON s.class_code = c.class_code
                                    WHERE sp.parent_id = :parent_id
                                    ORDER BY s.last_name, s.first_name";
                    $childrenStmt = $db->prepare($childrenQuery);
                    $childrenStmt->bindParam(':parent_id', $parentInfo['parent_id'], PDO::PARAM_INT);
                    $childrenStmt->execute();
                    $user['children'] = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
        }

        $db->commit();
        return $user;

    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        error_log("Database error in getUserInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Returns an array of navigation menu items customized for the user's role
 *
 * @param int $role The user's role ID
 * @return array Array of navigation items with title, URL and icon
 */
function getNavItemsByRole(int $role): array
{
    $items = [];

    $items[] = [
        'title' => 'Dashboard',
        'url' => '/uwuweb/dashboard.php',
        'icon' => 'dashboard'
    ];

    switch ($role) {
        case 1: // Administrator
            $items[] = [
                'title' => 'Upravljanje uporabnikov',
                'url' => '/uwuweb/admin/users.php',
                'icon' => 'people'
            ];
            $items[] = [
                'title' => 'Nastavitve',
                'url' => '/uwuweb/admin/settings.php',
                'icon' => 'settings'
            ];
            $items[] = [
                'title' => 'Razredi in predmeti',
                'url' => '/uwuweb/admin/class_subjects.php',
                'icon' => 'school'
            ];
            break;

        case 2: // Teacher
            $items[] = [
                'title' => 'Redovalnica',
                'url' => '/uwuweb/teacher/gradebook.php',
                'icon' => 'grade'
            ];
            $items[] = [
                'title' => 'Prisotnost',
                'url' => '/uwuweb/teacher/attendance.php',
                'icon' => 'event_available'
            ];
            $items[] = [
                'title' => 'Opravičila',
                'url' => '/uwuweb/teacher/justifications.php',
                'icon' => 'note'
            ];
            break;

        case 3: // Student
            $items[] = [
                'title' => 'Ocene',
                'url' => '/uwuweb/student/grades.php',
                'icon' => 'grade'
            ];
            $items[] = [
                'title' => 'Prisotnost',
                'url' => '/uwuweb/student/attendance.php',
                'icon' => 'event_available'
            ];
            $items[] = [
                'title' => 'Opravičila',
                'url' => '/uwuweb/student/justification.php',
                'icon' => 'note'
            ];
            break;

        case 4: // Parent
            $items[] = [
                'title' => 'Ocene otroka',
                'url' => '/uwuweb/parent/grades.php',
                'icon' => 'grade'
            ];
            $items[] = [
                'title' => 'Prisotnost otroka',
                'url' => '/uwuweb/parent/attendance.php',
                'icon' => 'event_available'
            ];
            break;
    }

    $items[] = [
        'title' => 'Odjava',
        'url' => '/uwuweb/includes/logout.php',
        'icon' => 'logout'
    ];

    return $items;
}

/**
 * Returns an array of dashboard widgets appropriate for the user's role
 *
 * @param int $role The user's role ID
 * @return array Array of widgets with title and rendering function
 */
function getWidgetsByRole(int $role): array
{
    $widgets = [];

    switch ($role) {
        case 1: // Administrator
            $widgets[] = [
                'title' => 'Statistika uporabnikov',
                'function' => 'renderAdminUserStatsWidget'
            ];
            $widgets[] = [
                'title' => 'Status sistema',
                'function' => 'renderAdminSystemStatusWidget'
            ];
            $widgets[] = [
                'title' => 'Prisotnost na šoli',
                'function' => 'renderAdminAttendanceWidget'
            ];
            break;

        case 2: // Teacher
            $widgets[] = [
                'title' => 'Pregled razredov',
                'function' => 'renderTeacherClassOverviewWidget'
            ];
            $widgets[] = [
                'title' => 'Današnja prisotnost',
                'function' => 'renderTeacherAttendanceWidget'
            ];
            $widgets[] = [
                'title' => 'Čakajoča opravičila',
                'function' => 'renderTeacherPendingJustificationsWidget'
            ];
            $widgets[] = [
                'title' => 'Povprečja razredov',
                'function' => 'renderTeacherClassAveragesWidget'
            ];
            break;

        case 3: // Student
            $widgets[] = [
                'title' => 'Povzetek prisotnosti',
                'function' => 'renderStudentAttendanceWidget'
            ];
            $widgets[] = [
                'title' => 'Prihajajoče ure',
                'function' => 'renderUpcomingClassesWidget'
            ];
            $widgets[] = [
                'title' => 'Moje ocene',
                'function' => 'renderStudentGradesWidget'
            ];
            $widgets[] = [
                'title' => 'Povprečja',
                'function' => 'renderStudentClassAveragesWidget'
            ];
            break;

        case 4: // Parent
            $widgets[] = [
                'title' => 'Prisotnost otroka',
                'function' => 'renderParentAttendanceWidget'
            ];
            $widgets[] = [
                'title' => 'Povprečja otroka',
                'function' => 'renderParentChildClassAveragesWidget'
            ];
            break;
    }

    return $widgets;
}

/**
 * Creates a simple placeholder for widgets that don't have data or aren't implemented
 *
 * @param string $message Optional message to display in the placeholder
 * @return string HTML content for the placeholder widget
 */
function renderPlaceholderWidget(string $message = 'Podatki trenutno niso na voljo.'): string
{
    return '<div class="card card__content p-lg text-center text-secondary">
                <span class="material-icons-outlined font-size-xxl mb-md text-disabled">info</span>
                <p class="m-0">' . htmlspecialchars($message) . '</p>
            </div>';
}

/**
 * Creates the HTML for the recent activity dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderRecentActivityWidget(): string
{
    $userId = getUserId();
    $roleId = getUserRole();

    if (!$userId || !$roleId) return renderPlaceholderWidget('Za prikaz nedavnih aktivnosti se prijavite.');

    try {
        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

        $activities = [];
        $limit = 5;

        switch ($roleId) {
            case 1: // Admin
                $query = "SELECT 'user_activity' as type, u.username, r.name as role_name,
                          u.created_at as activity_date, 'Registracija novega uporabnika' as description
                          FROM users u
                          JOIN roles r ON u.role_id = r.role_id
                          ORDER BY u.created_at DESC
                          LIMIT :limit";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 2: // Teacher
                $teacherId = function_exists('getTeacherId') ? getTeacherId() : null;
                if (!$teacherId) return renderPlaceholderWidget('Podatki učitelja niso na voljo.');

                $gradeQuery = "SELECT
                          'grade' as activity_type,
                          g.grade_id,
                          gi.name as grade_item,
                          CONCAT(s.first_name, ' ', s.last_name) as student_name,
                          s.first_name,
                          s.last_name,
                          sub.name as subject_name,
                          g.points,
                          gi.max_points,
                          COALESCE(g.comment, '') as comment,
                          NOW() as activity_date
                          FROM grades g
                          JOIN grade_items gi ON g.item_id = gi.item_id
                          JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                          JOIN subjects sub ON cs.subject_id = sub.subject_id
                          JOIN enrollments e ON g.enroll_id = e.enroll_id
                          JOIN students s ON e.student_id = s.student_id
                          WHERE cs.teacher_id = :teacher_id
                          ORDER BY g.grade_id DESC
                          LIMIT :limit";
                $stmt = $db->prepare($gradeQuery);
                $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 3: // Student
                $studentId = function_exists('getStudentId') ? getStudentId() : null;
                if (!$studentId) return renderPlaceholderWidget('Podatki dijaka niso na voljo.');

                $query = "SELECT
                          g.grade_id,
                          gi.name as grade_item,
                          sub.name as subject_name,
                          g.points,
                          gi.max_points,
                          COALESCE(g.comment, '') as comment,
                          NOW() as activity_date
                          FROM grades g
                          JOIN grade_items gi ON g.item_id = gi.item_id
                          JOIN enrollments e ON g.enroll_id = e.enroll_id
                          JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                          JOIN subjects sub ON cs.subject_id = sub.subject_id
                          WHERE e.student_id = :student_id
                          ORDER BY g.grade_id DESC
                          LIMIT :limit";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 4: // Parent
                $parentInfo = getUserInfo($userId);
                if (!$parentInfo || empty($parentInfo['children'])) return renderPlaceholderWidget('Ni povezanih otrok ali podatkov o staršu.');
                $childIds = array_column($parentInfo['children'], 'student_id');
                if (empty($childIds)) return renderPlaceholderWidget('Ni povezanih otrok.');
                $placeholders = implode(',', array_fill(0, count($childIds), '?'));

                $query = "SELECT
                          g.grade_id,
                          gi.name as grade_item,
                          s.first_name,
                          s.last_name,
                          subj.name as subject_name,
                          g.points,
                          gi.max_points,
                          COALESCE(g.comment, '') as comment,
                          NOW() as activity_date
                          FROM students s
                          JOIN enrollments e ON s.student_id = e.student_id
                          JOIN grades g ON e.enroll_id = g.enroll_id
                          JOIN grade_items gi ON g.item_id = gi.item_id
                          JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                          JOIN subjects subj ON cs.subject_id = subj.subject_id
                          WHERE s.student_id IN ($placeholders)
                          ORDER BY g.grade_id DESC
                          LIMIT :limit";
                $stmt = $db->prepare($query);
                foreach ($childIds as $k => $id) $stmt->bindValue(($k + 1), $id, PDO::PARAM_INT);
                $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }

        if (empty($activities)) return renderPlaceholderWidget('Ni nedavnih aktivnosti.');

        $html = '<div class="card card__content p-0">';
        $html .= '<ul class="list-unstyled m-0 activity-list">';

        foreach ($activities as $activity) {
            $html .= '<li class="activity-item d-flex items-start gap-md p-md border-bottom">';

            switch ($roleId) {
                case 1: // Admin
                    $icon = 'admin_panel_settings';
                    $iconClass = 'profile-admin';
                    $html .= '<div class="activity-icon rounded-full p-sm ' . $iconClass . ' text-white"><span class="material-icons-outlined">' . $icon . '</span></div>';
                    $html .= '<div class="activity-content flex-grow-1">';
                    $html .= '<span class="activity-title font-medium d-block">' . htmlspecialchars($activity['description']) . '</span>';
                    $html .= '<span class="activity-details text-sm text-secondary d-block">Uporabnik: ' . htmlspecialchars($activity['username']) .
                        ' (' . htmlspecialchars($activity['role_name']) . ')</span>';
                    $html .= '<span class="activity-time text-xs text-disabled d-block">' . date('d.m.Y H:i', strtotime($activity['activity_date'])) . '</span>';
                    $html .= '</div>';
                    break;

                case 2: // Teacher
                    $icon = 'edit_note';
                    $iconClass = 'profile-teacher';
                    $html .= '<div class="activity-icon rounded-full p-sm ' . $iconClass . ' text-white"><span class="material-icons-outlined">' . $icon . '</span></div>';
                    $html .= '<div class="activity-content flex-grow-1">';
                    $html .= '<span class="activity-title font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['grade_item']) . '</span>';
                    $html .= '<span class="activity-details text-sm text-secondary d-block">Dijak: ' . htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) $html .= '<span class="activity-comment text-sm text-secondary fst-italic d-block">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    $html .= '</div>';
                    break;

                case 3: // Student
                    $icon = 'school';
                    $iconClass = 'profile-student';
                    $html .= '<div class="activity-icon rounded-full p-sm ' . $iconClass . ' text-white"><span class="material-icons-outlined">' . $icon . '</span></div>';
                    $html .= '<div class="activity-content flex-grow-1">';
                    $html .= '<span class="activity-title font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['subject_name']) . '</span>';
                    $html .= '<span class="activity-details text-sm text-secondary d-block">' . htmlspecialchars($activity['grade_item']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) $html .= '<span class="activity-comment text-sm text-secondary fst-italic d-block">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    $html .= '</div>';
                    break;

                case 4: // Parent
                    $icon = 'family_restroom';
                    $iconClass = 'profile-parent';
                    $html .= '<div class="activity-icon rounded-full p-sm ' . $iconClass . ' text-white"><span class="material-icons-outlined">' . $icon . '</span></div>';
                    $html .= '<div class="activity-content flex-grow-1">';
                    $html .= '<span class="activity-title font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['first_name']) .
                        ' - ' . htmlspecialchars($activity['subject_name']) . '</span>';
                    $html .= '<span class="activity-details text-sm text-secondary d-block">' . htmlspecialchars($activity['grade_item']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    $html .= '</div>';
                    break;
            }

            $html .= '</li>';
        }

        $html .= '</ul></div>';

        return $html;

    } catch (PDOException $e) {
        error_log("Database error in renderRecentActivityWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o nedavnih aktivnostih.');
    }
}

/**
 * Retrieves the student ID associated with the current user
 *
 * @return int|null Student ID or null if not a student or not logged in
 */
function getStudentId(): ?int
{
    $userId = getUserId();
    if (!$userId) return null;

    static $studentIdCache = null;
    if ($studentIdCache !== null && isset($studentIdCache[$userId])) return $studentIdCache[$userId];

    try {
        $db = getDBConnection();
        if (!$db) return null;

        $query = "SELECT student_id FROM students WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $result ? (int)$result['student_id'] : null;

        if ($studentIdCache === null) $studentIdCache = [];
        $studentIdCache[$userId] = $id;

        return $id;
    } catch (PDOException $e) {
        error_log("Database error in getStudentId: " . $e->getMessage());
        return null;
    }
}

/**
 * Creates the HTML for the teacher's class averages dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderTeacherClassAveragesWidget(): string
{
    $teacherId = function_exists('getTeacherId') ? getTeacherId() : null;

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

/**
 * Creates the HTML for the student's class averages dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderStudentClassAveragesWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz povprečij razredov se morate identificirati kot dijak.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

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
                  FROM students st
                  JOIN enrollments e ON st.student_id = e.student_id AND e.student_id = :student_id
                  JOIN classes c ON e.class_id = c.class_id
                  JOIN class_subjects cs ON c.class_id = cs.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN grade_items gi_student ON gi_student.class_subject_id = cs.class_subject_id
                  LEFT JOIN grades g_student ON g_student.item_id = gi_student.item_id AND e.enroll_id = g_student.enroll_id
                  GROUP BY s.subject_id, s.name, c.class_id, c.title, cs.class_subject_id
                  ORDER BY s.name, c.title";


        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $studentGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderStudentClassAveragesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o povprečjih.');
    }

    $html = '<div class="widget-content card card__content p-0">';

    if (empty($studentGrades) || !array_filter($studentGrades, static fn($g) => $g['student_avg_score'] !== null)) $html .= '<div class="text-center p-md"><p class="m-0">Nimate razredov z ocenami.</p></div>'; else {
        $html .= '<div class="student-averages-table table-responsive">';
        $html .= '<table class="data-table w-100">';
        $html .= '<thead><tr><th>Predmet</th><th class="text-center">Vaše povprečje</th><th class="text-center">Povprečje razreda</th><th class="text-center">Primerjava</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($studentGrades as $grade) {
            if ($grade['student_avg_score'] === null && $grade['class_avg_score'] === null) continue;

            $studentAvgFormatted = $grade['student_avg_score'] !== null ? number_format($grade['student_avg_score'], 1) . '%' : 'N/A';
            $classAvgFormatted = $grade['class_avg_score'] !== null ? number_format($grade['class_avg_score'], 1) . '%' : 'N/A';

            $scoreClass = '';
            $comparisonText = '-';
            $comparisonClass = 'text-secondary';

            if ($grade['student_avg_score'] !== null) {
                if ($grade['student_avg_score'] >= 80) $scoreClass = 'grade-high'; elseif ($grade['student_avg_score'] >= 60) $scoreClass = 'grade-medium';
                else $scoreClass = 'grade-low';

                if ($grade['class_avg_score'] !== null) {
                    $diff = $grade['student_avg_score'] - $grade['class_avg_score'];
                    $diffFormatted = number_format($diff, 1);

                    if ($diff > 5) {
                        $comparisonText = '+' . $diffFormatted . '%';
                        $comparisonClass = 'text-success';
                    } elseif ($diff < -5) {
                        $comparisonText = $diffFormatted . '%';
                        $comparisonClass = 'text-error';
                    } else $comparisonText = '≈';
                }
            }

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($grade['subject_name']) . '<br><small class="text-disabled">' . htmlspecialchars($grade['class_title']) . '</small></td>';
            $html .= '<td class="text-center ' . $scoreClass . '">' . $studentAvgFormatted . '</td>';
            $html .= '<td class="text-center">' . $classAvgFormatted . '</td>';
            $html .= '<td class="text-center ' . $comparisonClass . '">' . $comparisonText . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    }

    $html .= '<div class="widget-footer mt-md text-right border-top pt-md p-md">';
    $html .= '<a href="/uwuweb/student/grades.php" class="btn btn-sm btn-primary">Ogled vseh ocen</a>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}

/**
 * Creates the HTML for the parent's view of their child's class averages
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

/**
 * Creates the HTML for a student's upcoming classes widget
 *
 * @return string HTML content for the widget
 */
function renderUpcomingClassesWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz prihajajočih ur se morate identificirati kot dijak.');

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

    $html = '<div class="widget-content card card__content p-0">';

    if (empty($upcomingClasses)) $html .= '<div class="text-center p-md"><p class="m-0">Ni prihajajočih ur v naslednjem tednu.</p></div>'; else {
        $currentDay = '';
        $html .= '<div class="upcoming-classes p-md">';

        foreach ($upcomingClasses as $class) {
            $classDate = date('Y-m-d', strtotime($class['period_date']));
            $formattedDate = date('d.m.Y', strtotime($class['period_date']));
            $dayName = match (date('N', strtotime($class['period_date']))) {
                '1' => 'Ponedeljek',
                '2' => 'Torek',
                '3' => 'Sreda',
                '4' => 'Četrtek',
                '5' => 'Petek',
                '6' => 'Sobota',
                '7' => 'Nedelja',
                default => ''
            };

            if ($classDate != $currentDay) {
                if ($currentDay != '') {
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $currentDay = $classDate;
                $html .= '<div class="day-group mb-lg">';
                $html .= '<div class="day-header border-bottom pb-sm mb-md">';
                $html .= '<h5 class="m-0 font-medium">' . $dayName . ', ' . $formattedDate . '</h5>';
                $html .= '</div>';
                $html .= '<div class="day-classes d-flex flex-column gap-md">';
            }

            $html .= '<div class="class-item d-flex gap-md p-sm rounded bg-secondary shadow-sm">';
            $html .= '<div class="class-time font-medium text-center p-sm bg-tertiary rounded" style="min-width: 60px;">' . htmlspecialchars($class['period_label']) . '. ura</div>';
            $html .= '<div class="class-details flex-grow-1">';
            $html .= '<div class="class-subject font-medium d-block">' . htmlspecialchars($class['subject_name']) . '</div>';
            $html .= '<div class="class-teacher text-sm text-secondary">Profesor: ' . htmlspecialchars($class['teacher_name']) . '</div>';
            $html .= '<div class="class-room text-sm text-secondary">Razred: ' . htmlspecialchars($class['class_title']) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        if ($currentDay != '') {
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Creates the HTML for the student's grades summary widget
 *
 * @return string HTML content for the widget
 */
function renderStudentGradesWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz ocen se morate identificirati kot dijak.');

    $db = getDBConnection();
    if (!$db) return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');

    try {
        $queryRecent = "SELECT
                    g.grade_id, g.points, gi.max_points, gi.name AS grade_item_name,
                    s.name AS subject_name, g.comment,
                    CASE WHEN gi.max_points > 0 THEN ROUND((g.points / gi.max_points) * 100, 1) END AS percentage,
                    g.grade_id AS date_added
                  FROM grades g
                  JOIN grade_items gi ON g.item_id = gi.item_id
                  JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  JOIN enrollments e ON g.enroll_id = e.enroll_id
                  WHERE e.student_id = :student_id
                  ORDER BY g.grade_id DESC
                  LIMIT 5";

        $stmtRecent = $db->prepare($queryRecent);
        $stmtRecent->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmtRecent->execute();
        $recentGrades = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        $queryAvg = "SELECT
                    s.subject_id, s.name AS subject_name,
                    AVG(CASE WHEN g.points IS NOT NULL AND gi.max_points > 0
                         THEN (g.points / gi.max_points) * 100
                         END) AS avg_score,
                    COUNT(g.grade_id) AS grade_count
                  FROM enrollments e
                  JOIN classes c ON e.class_id = c.class_id
                  JOIN class_subjects cs ON c.class_id = cs.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN grade_items gi ON gi.class_subject_id = cs.class_subject_id
                  LEFT JOIN grades g ON gi.item_id = g.item_id AND e.enroll_id = g.enroll_id
                  WHERE e.student_id = :student_id
                  GROUP BY s.subject_id, s.name
                  HAVING COUNT(g.grade_id) > 0
                  ORDER BY avg_score DESC";

        $stmtAvg = $db->prepare($queryAvg);
        $stmtAvg->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmtAvg->execute();
        $subjectAverages = $stmtAvg->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderStudentGradesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o ocenah.');
    }

    $html = '<div class="widget-content grades-widget card card__content">';

    $html .= '<div class="row gap-lg">';

    $html .= '<div class="grades-section subject-averages col-12 col-md-5">';
    $html .= '<h5 class="mb-md font-medium">Povprečja predmetov</h5>';

    if (empty($subjectAverages)) $html .= '<div class="text-center p-md"><p class="m-0 text-secondary">Ni podatkov o povprečjih predmetov.</p></div>'; else {
        $html .= '<div class="averages-list d-flex flex-column gap-sm">';
        foreach ($subjectAverages as $subject) {
            if ($subject['avg_score'] === null) continue;

            $avgScore = number_format($subject['avg_score'], 1);
            $gradeCount = (int)$subject['grade_count'];
            $gradeSuffix = match (true) {
                $gradeCount == 1 => 'ocena',
                $gradeCount >= 2 && $gradeCount <= 4 => 'oceni',
                default => 'ocen'
            };

            $scoreClass = match (true) {
                $subject['avg_score'] >= 80 => 'grade-high',
                $subject['avg_score'] >= 60 => 'grade-medium',
                default => 'grade-low'
            };

            $html .= '<div class="subject-average-item d-flex justify-between items-center p-sm rounded bg-secondary shadow-sm">';
            $html .= '<div class="subject-info flex-grow-1">';
            $html .= '<div class="subject-name font-medium">' . htmlspecialchars($subject['subject_name']) . '</div>';
            $html .= '<div class="subject-grade-count text-xs text-disabled">' . $gradeCount . ' ' . $gradeSuffix . '</div>';
            $html .= '</div>';
            $html .= '<div class="subject-average badge ' . $scoreClass . '">' . $avgScore . '%</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '<div class="grades-section recent-grades col-12 col-md-7">';
    $html .= '<h5 class="mb-md font-medium">Nedavne ocene</h5>';

    if (empty($recentGrades)) $html .= '<div class="text-center p-md"><p class="m-0 text-secondary">Nimate nedavnih ocen.</p></div>'; else {
        $html .= '<div class="recent-grades-list d-flex flex-column gap-md">';
        foreach ($recentGrades as $grade) {
            $percentage = $grade['percentage'];
            $scoreClass = 'badge-secondary';
            if ($percentage !== null) $scoreClass = match (true) {
                $percentage >= 80 => 'grade-high',
                $percentage >= 60 => 'grade-medium',
                default => 'grade-low'
            };
            $percentageFormatted = $percentage !== null ? '(' . htmlspecialchars($percentage) . '%)' : '';

            $html .= '<div class="grade-item p-md rounded bg-secondary shadow-sm">';
            $html .= '<div class="grade-header d-flex justify-between items-center mb-sm">';
            $html .= '<div class="grade-subject font-medium">' . htmlspecialchars($grade['subject_name']) . '</div>';
            $html .= '<div class="grade-score badge ' . $scoreClass . '">' .
                htmlspecialchars($grade['points']) . '/' .
                htmlspecialchars($grade['max_points']) . ' ' . $percentageFormatted . '</div>';
            $html .= '</div>';

            $html .= '<div class="grade-details">';
            $html .= '<div class="grade-name text-sm">' . htmlspecialchars($grade['grade_item_name']) . '</div>';
            if (!empty($grade['comment'])) $html .= '<div class="grade-comment text-sm text-secondary fst-italic mt-xs">"' . htmlspecialchars($grade['comment']) . '"</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '</div>';

    $html .= '<div class="widget-footer mt-lg text-right border-top pt-md">';
    $html .= '<a href="/uwuweb/student/grades.php" class="btn btn-sm btn-primary">Ogled vseh ocen</a>';
    $html .= '</div>';

    $html .= '</div>';

    return $html;
}

/**
 * Translates the single-letter status code into a human-readable label
 *
 * @param string $status The status code (P, A, L)
 * @return string Human-readable status label
 */
function getAttendanceStatusLabel(string $status): string
{
    return match (strtoupper($status)) {
        'P' => 'Prisoten',
        'A' => 'Odsoten',
        'L' => 'Zamuda',
        default => 'Neznano',
    };
}

/**
 * Calculates presence, absence, and lateness rates from attendance records
 *
 * @param array $attendance Array of attendance records to analyze
 * @return array Statistics including present_count, absent_count, late_count, and percentages
 */
function calculateAttendanceStats(array $attendance): array
{
    $total = count($attendance);
    $present = 0;
    $absent = 0;
    $late = 0;

    foreach ($attendance as $record) {
        if (!isset($record['status'])) continue;
        switch (strtoupper($record['status'])) {
            case 'P':
                $present++;
                break;
            case 'A':
                $absent++;
                break;
            case 'L':
                $late++;
                break;
        }
    }

    return [
        'total' => $total,
        'present_count' => $present,
        'absent_count' => $absent,
        'late_count' => $late,
        'present_percent' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        'absent_percent' => $total > 0 ? round(($absent / $total) * 100, 1) : 0,
        'late_percent' => $total > 0 ? round(($late / $total) * 100, 1) : 0,
    ];
}

/**
 * Displays counts of users by role and recent user activity widget
 *
 * @return string HTML content for the widget
 */
function renderAdminUserStatsWidget(): string
{
    try {
        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $roleQuery = "SELECT r.role_id, r.name, COUNT(u.user_id) as count
                      FROM roles r
                      LEFT JOIN users u ON r.role_id = u.role_id
                      GROUP BY r.role_id, r.name
                      ORDER BY r.role_id";
        $roleStmt = $db->query($roleQuery);
        $roleCounts = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentQuery = "SELECT COUNT(*) as new_users
                        FROM users
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $recentStmt = $db->query($recentQuery);
        $recentUsers = $recentStmt->fetch(PDO::FETCH_ASSOC)['new_users'] ?? 0;

        $output = '<div class="stats-container card card__content d-flex flex-column gap-lg">';

        $totalUsers = array_sum(array_column($roleCounts, 'count'));
        $output .= '<div class="d-flex justify-around text-center mb-md">';
        $output .= '<div class="stat-item">';
        $output .= '<span class="stat-number d-block font-size-xl font-bold">' . htmlspecialchars($totalUsers) . '</span>';
        $output .= '<span class="stat-label text-sm text-secondary">Skupaj uporabnikov</span>';
        $output .= '</div>';
        $output .= '<div class="stat-item">';
        $output .= '<span class="stat-number d-block font-size-xl font-bold">' . htmlspecialchars($recentUsers) . '</span>';
        $output .= '<span class="stat-label text-sm text-secondary">Novih (7 dni)</span>';
        $output .= '</div>';
        $output .= '</div>';

        $output .= '<div class="stat-breakdown border-top pt-md">';
        $output .= '<h5 class="mb-md text-center font-medium">Uporabniki po vlogah</h5>';
        $output .= '<ul class="role-list list-unstyled p-0 m-0 d-flex flex-column gap-sm">';
        foreach ($roleCounts as $role) {
            $roleClass = match ($role['role_id']) {
                1 => 'profile-admin',
                2 => 'profile-teacher',
                3 => 'profile-student',
                4 => 'profile-parent',
                default => 'bg-secondary'
            };
            $textColor = in_array($roleClass, ['profile-admin', 'profile-teacher', 'profile-student', 'profile-parent']) ? 'text-white' : '';
            $output .= '<li class="d-flex justify-between items-center p-sm rounded ' . $roleClass . ' ' . $textColor . '">';
            $output .= '<span class="role-name font-medium">' . htmlspecialchars($role['name']) . '</span>';
            $output .= '<span class="role-count badge badge-light">' . htmlspecialchars($role['count']) . '</span>';
            $output .= '</li>';
        }
        $output .= '</ul></div>';

        $output .= '</div>';

        return $output;
    } catch (PDOException $e) {
        error_log("Error in renderAdminUserStatsWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o uporabnikih.');
    }
}

/**
 * Shows database statistics, session count, and PHP version information widget
 *
 * @return string HTML content for the widget
 */
function renderAdminSystemStatusWidget(): string
{
    try {
        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $dbName = $db->query('select database()')->fetchColumn();
        $tableQuery = "SELECT
                          COUNT(DISTINCT TABLE_NAME) as table_count,
                          SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 as size_mb
                       FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = :dbName";
        $tableStmt = $db->prepare($tableQuery);
        $tableStmt->bindParam(':dbName', $dbName);
        $tableStmt->execute();
        $tableStats = $tableStmt->fetch(PDO::FETCH_ASSOC);

        $sessionPath = session_save_path();
        $sessionCount = 0;
        if (!empty($sessionPath) && is_dir($sessionPath) && is_readable($sessionPath)) {
            $sessionFiles = glob($sessionPath . "/sess_*");
            if ($sessionFiles !== false) {
                $sessionLifetime = (int)ini_get('session.gc_maxlifetime');
                if ($sessionLifetime <= 0) $sessionLifetime = 1800;
                $sessionCount = count(array_filter($sessionFiles, static fn($file) => (time() - filemtime($file)) < $sessionLifetime));
            }
        }

        $output = '<div class="system-status card card__content">';
        $output .= '<div class="row gap-lg">';

        $output .= '<div class="status-section col-12 col-md-6">';
        $output .= '<h5 class="mb-md border-bottom pb-sm font-medium d-flex items-center gap-xs"><span class="material-icons-outlined align-middle">database</span> Podatkovna baza</h5>';
        $output .= '<ul class="list-unstyled p-0 m-0 d-flex flex-column gap-sm text-sm">';
        $output .= '<li class="d-flex justify-between"><span>Tabele:</span> <strong class="font-medium">' . htmlspecialchars($tableStats['table_count'] ?? 'N/A') . '</strong></li>';
        $output .= '<li class="d-flex justify-between"><span>Velikost:</span> <strong class="font-medium">' . htmlspecialchars(round($tableStats['size_mb'] ?? 0, 2)) . ' MB</strong></li>';
        $output .= '<li class="d-flex justify-between"><span>Tip:</span> <strong class="font-medium">MySQL ' . htmlspecialchars($db->getAttribute(PDO::ATTR_SERVER_VERSION)) . '</strong></li>';
        $output .= '<li class="d-flex justify-between"><span>Ime:</span> <strong class="font-medium">' . htmlspecialchars($dbName) . '</strong></li>';
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '<div class="status-section col-12 col-md-6">';
        $output .= '<h5 class="mb-md border-bottom pb-sm font-medium d-flex items-center gap-xs"><span class="material-icons-outlined align-middle">dns</span> Strežnik & PHP</h5>';
        $output .= '<ul class="list-unstyled p-0 m-0 d-flex flex-column gap-sm text-sm">';
        $output .= '<li class="d-flex justify-between"><span>PHP verzija:</span> <strong class="font-medium">' . htmlspecialchars(PHP_VERSION) . '</strong></li>';
        $output .= '<li class="d-flex justify-between"><span>Aktivne seje:</span> <strong class="font-medium">' . htmlspecialchars($sessionCount) . '</strong></li>';
        $output .= '<li class="d-flex justify-between"><span>Max upload:</span> <strong class="font-medium">' . htmlspecialchars(ini_get('upload_max_filesize')) . '</strong></li>';
        $output .= '<li class="d-flex justify-between"><span>Čas strežnika:</span> <strong class="font-medium">' . htmlspecialchars(date('Y-m-d H:i:s')) . '</strong></li>';
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    } catch (Exception $e) {
        error_log("Error in renderAdminSystemStatusWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o sistemu.');
    }
}

/**
 * Displays school-wide attendance statistics and trends
 *
 * @return string HTML content for the widget
 */
function renderAdminAttendanceWidget(): string
{
    try {
        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $intervalDays = 30;
        $startDate = date('Y-m-d', strtotime("-$intervalDays days"));
        $endDate = date('Y-m-d');

        $query = "SELECT a.status, COUNT(*) as count
                  FROM attendance a
                  JOIN periods p ON a.period_id = p.period_id
                  WHERE p.period_date BETWEEN :start_date AND :end_date
                  GROUP BY a.status";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $attendanceData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $present = $attendanceData['P'] ?? 0;
        $absent = $attendanceData['A'] ?? 0;
        $late = $attendanceData['L'] ?? 0;
        $total = $present + $absent + $late;

        $presentPercent = $total > 0 ? round(($present / $total) * 100, 1) : 0;
        $absentPercent = $total > 0 ? round(($absent / $total) * 100, 1) : 0;
        $latePercent = $total > 0 ? round(($late / $total) * 100, 1) : 0;

        $bestClassQuery = "SELECT c.class_code, c.title,
                          COUNT(CASE WHEN a.status = 'P' THEN 1 END) as present_count,
                          COUNT(a.att_id) as total_count,
                          (COUNT(CASE WHEN a.status = 'P' THEN 1 END) * 100.0 / COUNT(a.att_id)) as present_percent
                       FROM attendance a
                       JOIN periods p ON a.period_id = p.period_id
                       JOIN enrollments e ON a.enroll_id = e.enroll_id
                       JOIN classes c ON e.class_id = c.class_id
                       WHERE p.period_date BETWEEN :start_date AND :end_date
                       GROUP BY c.class_id, c.class_code, c.title
                       HAVING COUNT(a.att_id) > 10
                       ORDER BY present_percent DESC
                       LIMIT 1";
        $bestClassStmt = $db->prepare($bestClassQuery);
        $bestClassStmt->bindParam(':start_date', $startDate);
        $bestClassStmt->bindParam(':end_date', $endDate);
        $bestClassStmt->execute();
        $bestClass = $bestClassStmt->fetch(PDO::FETCH_ASSOC);

        $output = '<div class="attendance-stats card card__content">';

        $output .= '<div class="attendance-overview mb-lg">';
        $output .= '<h5 class="mb-md text-center font-medium">Skupna prisotnost (zadnjih ' . $intervalDays . ' dni)</h5>';

        $output .= '<div class="progress-chart d-flex rounded overflow-hidden mb-sm bg-tertiary shadow-sm" style="height: 20px;">';
        $output .= '<div class="progress-bar status-present" style="width:' . $presentPercent . '%" title="Prisotni: ' . $presentPercent . '%"></div>';
        $output .= '<div class="progress-bar status-late" style="width:' . $latePercent . '%" title="Zamude: ' . $latePercent . '%"></div>';
        $output .= '<div class="progress-bar status-absent" style="width:' . $absentPercent . '%" title="Odsotni: ' . $absentPercent . '%"></div>';
        $output .= '</div>';

        $output .= '<div class="attendance-legend d-flex justify-center flex-wrap gap-md text-sm">';
        $output .= '<span class="legend-item d-flex items-center gap-xs"><span class="legend-color d-inline-block rounded-full status-present" style="width: 10px; height: 10px;"></span>Prisotni: ' . $present . ' (' . $presentPercent . '%)</span>';
        $output .= '<span class="legend-item d-flex items-center gap-xs"><span class="legend-color d-inline-block rounded-full status-late" style="width: 10px; height: 10px;"></span>Zamude: ' . $late . ' (' . $latePercent . '%)</span>';
        $output .= '<span class="legend-item d-flex items-center gap-xs"><span class="legend-color d-inline-block rounded-full status-absent" style="width: 10px; height: 10px;"></span>Odsotni: ' . $absent . ' (' . $absentPercent . '%)</span>';
        $output .= '</div>';
        $output .= '</div>';

        if ($bestClass) {
            $output .= '<div class="best-class mt-lg p-md bg-secondary rounded border-start border-success border-4 shadow-sm">';
            $output .= '<h5 class="mb-md font-medium d-flex items-center gap-xs"><span class="material-icons-outlined align-middle">emoji_events</span> Razred z najboljšo prisotnostjo</h5>';
            $output .= '<div class="best-class-info d-flex justify-between items-center">';
            $output .= '<span class="best-class-title font-medium">' . htmlspecialchars($bestClass['title']) . ' (' . htmlspecialchars($bestClass['class_code']) . ')</span>';
            $output .= '<span class="best-class-percent badge grade-high">' . htmlspecialchars(round($bestClass['present_percent'], 1)) . '%</span>';
            $output .= '</div>';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    } catch (PDOException $e) {
        error_log("Error in renderAdminAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o prisotnosti.');
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
        $teacherId = function_exists('getTeacherId') ? getTeacherId() : null;
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
        $teacherId = function_exists('getTeacherId') ? getTeacherId() : null;
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
        $teacherId = function_exists('getTeacherId') ? getTeacherId() : null;
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
 * Creates the HTML for the student's attendance dashboard widget, showing attendance statistics and recent attendance records
 *
 * @return string HTML content for the widget
 */
function renderStudentAttendanceWidget(): string
{
    $studentId = getStudentId();

    if (!$studentId) return renderPlaceholderWidget('Za prikaz prisotnosti se morate identificirati kot dijak.');

    try {
        $db = getDBConnection();
        if (!$db) return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');

        $stats = [
            'total' => 0, 'present' => 0, 'late' => 0,
            'justified' => 0, 'rejected' => 0, 'pending' => 0, 'needs_justification' => 0,
            'unjustified' => 0, 'attendance_rate' => 0, 'recent' => []
        ];

        $query = "SELECT
            COUNT(*) as total,
            SUM(IF(status = 'P', 1, 0)) as present,
            SUM(IF(status = 'A', 1, 0)) as absent,
            SUM(IF(status = 'L', 1, 0)) as late,
            SUM(IF(status = 'A' AND approved = 1, 1, 0)) as justified,
            SUM(IF(status = 'A' AND approved = 0, 1, 0)) as rejected,
            SUM(IF(status = 'A' AND justification IS NOT NULL AND approved IS NULL, 1, 0)) as pending,
            SUM(IF(status = 'A' AND justification IS NULL AND approved IS NULL, 1, 0)) as needs_justification
         FROM attendance a
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         WHERE e.student_id = :student_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $stats['total'] = (int)$result['total'];
            $stats['present'] = (int)$result['present'];
            $stats['late'] = (int)$result['late'];
            $stats['justified'] = (int)$result['justified'];
            $stats['rejected'] = (int)$result['rejected'];
            $stats['pending'] = (int)$result['pending'];
            $stats['needs_justification'] = (int)$result['needs_justification'];
            $stats['unjustified'] = $stats['needs_justification'] + $stats['rejected'];

            if ($stats['total'] > 0) $stats['attendance_rate'] = round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1);
        }

        $recentQuery = "SELECT
            a.att_id,
            a.status,
            a.justification,
            a.approved,
            p.period_date,
            p.period_label,
            s.name as subject_name
         FROM attendance a
         JOIN periods p ON a.period_id = p.period_id
         JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
         JOIN subjects s ON cs.subject_id = s.subject_id
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         WHERE e.student_id = :student_id
         ORDER BY p.period_date DESC, p.period_label DESC
         LIMIT 5";

        $stmt = $db->prepare($recentQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="widget-content attendance-widget card card__content">';

        $html .= '<div class="attendance-summary row align-items-center gap-lg mb-lg">';

        $html .= '<div class="attendance-rate col-12 col-md-4 text-center">';
        $rateColorClass = match (true) {
            $stats['attendance_rate'] >= 95 => 'text-success',
            $stats['attendance_rate'] >= 85 => 'text-warning',
            default => 'text-error'
        };
        $html .= '<div class="rate-circle mx-auto" data-percentage="' . $stats['attendance_rate'] . '" style="width: 100px; height: 100px;">';
        $html .= '<svg viewBox="0 0 36 36" class="circular-chart ' . $rateColorClass . '">';
        $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<path class="circle" stroke-dasharray="' . $stats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<text x="18" y="20.35" class="percentage">' . $stats['attendance_rate'] . '%</text>';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="rate-label text-sm text-secondary mt-sm">Skupna prisotnost</div>';
        $html .= '</div>';

        $html .= '<div class="attendance-breakdown col-12 col-md-7">';
        $html .= '<div class="row">';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-success">' . $stats['present'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Prisoten</span>';
        $html .= '</div>';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-warning">' . $stats['late'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Zamuda</span>';
        $html .= '</div>';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-info">' . $stats['justified'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Opravičeno</span>';
        $html .= '</div>';
        $html .= '<div class="col-6 col-lg-3 mb-md text-center">';
        $html .= '<span class="count d-block font-size-lg font-medium text-error">' . $stats['unjustified'] . '</span>';
        $html .= '<span class="label text-sm text-secondary">Neopravičeno</span>';
        $html .= '</div>';
        $html .= '</div>';
        if ($stats['pending'] > 0) {
            $html .= '<div class="row mt-sm">';
            $html .= '<div class="col-12 text-center"><span class="text-sm text-secondary">V obdelavi: ' . $stats['pending'] . '</span></div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</div>';

        if (!empty($stats['recent'])) {
            $html .= '<div class="recent-attendance border-top pt-lg">';
            $html .= '<h5 class="mb-md font-medium">Nedavna evidenca</h5>';
            $html .= '<div class="table-responsive">';
            $html .= '<table class="mini-table data-table w-100 text-sm">';
            $html .= '<thead><tr><th>Datum</th><th>Predmet</th><th class="text-center">Status</th><th class="text-center">Opravičilo</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($stats['recent'] as $record) {
                $date = date('d.m.Y', strtotime($record['period_date']));
                $classInfo = htmlspecialchars($record['subject_name']) . ' (' . htmlspecialchars($record['period_label']) . '. ura)';

                $statusBadge = '';
                $justificationHtml = '<span class="text-disabled">-</span>';

                switch ($record['status']) {
                    case 'P':
                        $statusBadge = '<span class="badge status-present">Prisoten</span>';
                        break;
                    case 'L':
                        $statusBadge = '<span class="badge status-late">Zamuda</span>';
                        break;
                    case 'A':
                        $statusBadge = '<span class="badge status-absent">Odsoten</span>';
                        if ($record['approved'] === 1) $justificationHtml = '<span class="badge badge-success">Opravičeno</span>'; elseif ($record['approved'] === 0) $justificationHtml = '<span class="badge badge-error">Zavrnjeno</span>';
                        elseif ($record['justification'] !== null && $record['approved'] === null) $justificationHtml = '<span class="badge badge-warning">V obdelavi</span>';
                        else $justificationHtml = '<a href="/uwuweb/student/justification.php?att_id=' . $record['att_id'] . '" class="btn btn-xs btn-warning d-inline-flex items-center gap-xs"><span class="material-icons-outlined text-xs">edit</span> Oddaj</a>';
                        break;
                }

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($date) . '</td>';
                $html .= '<td>' . $classInfo . '</td>';
                $html .= '<td class="text-center">' . $statusBadge . '</td>';
                $html .= '<td class="text-center">' . $justificationHtml . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '<div class="widget-footer d-flex justify-between items-center mt-lg border-top pt-md">';
        $html .= '<a href="/uwuweb/student/attendance.php" class="btn btn-sm btn-secondary">Celotna evidenca</a>';
        if ($stats['needs_justification'] > 0) $html .= '<a href="/uwuweb/student/justification.php" class="btn btn-sm btn-primary">Oddaj opravičilo (' . $stats['needs_justification'] . ')</a>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;

    } catch (PDOException $e) {
        error_log("Database error in renderStudentAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o prisotnosti.');
    } catch (Exception $e) {
        error_log("Error in renderStudentAttendanceWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Prišlo je do napake.');
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
 * Sends a standardized JSON error response with the specified HTTP status code, error message, and context. Handles JSON exceptions internally and logs errors.
 *
 * @param string $message The error message to send to the client
 * @param int $statusCode HTTP status code to send (default: 400)
 * @param string $context Additional context for error logging (e.g., 'attendance.php/addPeriod')
 * @return never This function will terminate script execution
 */
function sendJsonErrorResponse(string $message, int $statusCode = 400, string $context = ''): never
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($statusCode);
    }

    $logContext = $context ? " Context: [$context]" : '';
    error_log("API Error Response (HTTP $statusCode): $message$logContext");

    try {
        echo json_encode(['success' => false, 'message' => $message], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $e) {
        error_log("Failed to encode JSON error response: " . $e->getMessage());
        if (!headers_sent()) header('Content-Type: text/plain; charset=utf-8');
        echo "{\"success\": false, \"message\": \"Internal Server Error: Failed to create JSON response.\"}";
    }
    exit;
}
