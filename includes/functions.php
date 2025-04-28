<?php /** @noinspection ALL */

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
 * - getTeacherId(): int|null - Gets teacher ID for current user
 * - getStudentId(): int|null - Gets student ID for current user
 * - getRoleName(int $roleId): string - Returns the name of a role by ID
 *
 * Security Functions:
 * - generateCSRFToken(): string - Creates a CSRF token for form security
 * - verifyCSRFToken(string $token): bool - Validates submitted CSRF token
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

use Random\RandomException;

/**
 * Retrieves user profile information including username, role, and role name
 *
 * @param int $userId The user ID to look up
 * @return array|null User information array or null if not found
 */
function getUserInfo($userId) {
    try {
        $db = getDBConnection();
        if (!$db) {
            return null;
        }

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
                if ($teacherInfo) {
                    $user['teacher_id'] = $teacherInfo['teacher_id'];
                }
                break;

            case 3: // Student
                $studentQuery = "SELECT s.student_id, s.first_name, s.last_name, s.class_code 
                                FROM students s 
                                WHERE s.user_id = :user_id";
                $studentStmt = $db->prepare($studentQuery);
                $studentStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $studentStmt->execute();
                $studentInfo = $studentStmt->fetch(PDO::FETCH_ASSOC);
                if ($studentInfo) {
                    $user = array_merge($user, $studentInfo);
                }
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
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Database error in getUserInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Creates or retrieves a token stored in the session to prevent CSRF attacks
 *
 * @return string The generated CSRF token
 * @throws RandomException When secure random bytes cannot be generated
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION['csrf_token']) && !empty($_SESSION['csrf_token'])) {
            return $_SESSION['csrf_token'];
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        return $token;
    }
}

/**
 * Compares the provided token with the one stored in the session using constant-time comparison
 *
 * @param string $token The token to verify
 * @return bool True if token is valid, false otherwise
 */
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || empty($_SESSION['csrf_token'])) {
            return false;
        }

        if (isset($_SESSION['csrf_token_time']) &&
            time() - $_SESSION['csrf_token_time'] > 1800) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Returns an array of navigation menu items customized for the user's role
 *
 * @param int $role The user's role ID
 * @return array Array of navigation items with title, URL and icon
 */
function getNavItemsByRole(int $role): array {
    $items = [];

    $items[] = [
        'title' => 'Dashboard',
        'url' => '/dashboard.php',
        'icon' => 'dashboard'
    ];

    switch ($role) {
        case 1: // Administrator
            $items[] = [
                'title' => 'Upravljanje uporabnikov',
                'url' => '/admin/users.php',
                'icon' => 'people'
            ];
            $items[] = [
                'title' => 'Nastavitve',
                'url' => '/admin/settings.php',
                'icon' => 'settings'
            ];
            $items[] = [
                'title' => 'Razredi in predmeti',
                'url' => '/admin/class_subjects.php',
                'icon' => 'school'
            ];
            break;

        case 2: // Teacher
            $items[] = [
                'title' => 'Redovalnica',
                'url' => '/teacher/gradebook.php',
                'icon' => 'grade'
            ];
            $items[] = [
                'title' => 'Prisotnost',
                'url' => '/teacher/attendance.php',
                'icon' => 'event_available'
            ];
            $items[] = [
                'title' => 'Opravičila',
                'url' => '/teacher/justifications.php',
                'icon' => 'note'
            ];
            break;

        case 3: // Student
            $items[] = [
                'title' => 'Ocene',
                'url' => '/student/grades.php',
                'icon' => 'grade'
            ];
            $items[] = [
                'title' => 'Prisotnost',
                'url' => '/student/attendance.php',
                'icon' => 'event_available'
            ];
            $items[] = [
                'title' => 'Opravičila',
                'url' => '/student/justification.php',
                'icon' => 'note'
            ];
            break;

        case 4: // Parent
            $items[] = [
                'title' => 'Ocene otroka',
                'url' => '/parent/grades.php',
                'icon' => 'grade'
            ];
            $items[] = [
                'title' => 'Prisotnost otroka',
                'url' => '/parent/attendance.php',
                'icon' => 'event_available'
            ];
            break;
    }

    $items[] = [
        'title' => 'Odjava',
        'url' => '/includes/logout.php',
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
function getWidgetsByRole(int $role): array {
    $widgets = [];

    $widgets[] = [
        'title' => 'Nedavna aktivnost',
        'function' => 'renderRecentActivityWidget'
    ];

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
 * Translates a role ID to a human-readable role name
 *
 * @param int $roleId Role ID
 * @return string Role name or "Unknown Role" if not found
 */
if (!function_exists('getRoleName')) {
    function getRoleName($roleId): string {
        static $roleCache = [];

        if (isset($roleCache[$roleId])) {
            return $roleCache[$roleId];
        }

        try {
            $db = getDBConnection();
            if (!$db) {
                return "Unknown Role";
            }

            $query = "SELECT name FROM roles WHERE role_id = :role_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $roleName = $result ? $result['name'] : "Unknown Role";

            $roleCache[$roleId] = $roleName;

            return $roleName;
        } catch (PDOException $e) {
            error_log("Database error in getRoleName: " . $e->getMessage());
            return "Unknown Role";
        }
    }
}

/**
 * Creates a simple placeholder for widgets that don't have data or aren't implemented
 *
 * @param string $message Optional message to display in the placeholder
 * @return string HTML content for the placeholder widget
 */
function renderPlaceholderWidget(string $message = 'Podatki trenutno niso na voljo.'): string {
    return '<div class="widget-placeholder">
                <div class="placeholder-icon">📊</div>
                <p>' . htmlspecialchars($message) . '</p>
            </div>';
}

/**
 * Creates the HTML for the recent activity dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderRecentActivityWidget(): string {
    $userId = getUserId();
    $roleId = getUserRole();

    if (!$userId || !$roleId) {
        return renderPlaceholderWidget('Za prikaz nedavnih aktivnosti se prijavite.');
    }

    try {
        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');
        }

        $activities = [];

        switch ($roleId) {
            case 1: // Admin
                $query = "SELECT 'user_activity' as type, u.username, r.name as role_name, 
                          u.created_at as activity_date, 'Registracija novega uporabnika' as description
                          FROM users u
                          JOIN roles r ON u.role_id = r.role_id
                          ORDER BY u.created_at DESC
                          LIMIT 5";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 2: // Teacher
                $teacherId = function_exists('getTeacherId') ? getTeacherId() : null;
                if (!$teacherId) {
                    return renderPlaceholderWidget('Podatki učitelja niso na voljo.');
                }

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
                          LIMIT 5";
                $stmt = $db->prepare($gradeQuery);
                $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 3: // Student
                $studentId = function_exists('getStudentId') ? getStudentId() : null;
                if (!$studentId) {
                    return renderPlaceholderWidget('Podatki študenta niso na voljo.');
                }

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
                          LIMIT 5";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 4: // Parent
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
                          FROM parents p
                          JOIN student_parent sp ON p.parent_id = sp.parent_id
                          JOIN students s ON sp.student_id = s.student_id
                          JOIN enrollments e ON s.student_id = e.student_id
                          JOIN grades g ON e.enroll_id = g.enroll_id
                          JOIN grade_items gi ON g.item_id = gi.item_id
                          JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                          JOIN subjects subj ON cs.subject_id = subj.subject_id
                          WHERE p.user_id = :user_id
                          ORDER BY g.grade_id DESC
                          LIMIT 5";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }

        if (empty($activities)) {
            return renderPlaceholderWidget('Ni nedavnih aktivnosti.');
        }

        $html = '<div class="widget widget-recent-activity">
                    <ul class="activity-list">';

        foreach ($activities as $activity) {
            $html .= '<li class="activity-item">';

            switch ($roleId) {
                case 1: // Admin
                    $html .= '<div class="activity-icon admin-icon">👤</div>';
                    $html .= '<div class="activity-content">';
                    $html .= '<span class="activity-title">' . htmlspecialchars($activity['description']) . '</span>';
                    $html .= '<span class="activity-details">Uporabnik: ' . htmlspecialchars($activity['username']) .
                             ' (' . htmlspecialchars($activity['role_name']) . ')</span>';
                    $html .= '<span class="activity-time">' . date('d.m.Y H:i', strtotime($activity['activity_date'])) . '</span>';
                    $html .= '</div>';
                    break;

                case 2: // Teacher
                    $html .= '<div class="activity-icon teacher-icon">📝</div>';
                    $html .= '<div class="activity-content">';
                    $html .= '<span class="activity-title">Nova ocena: ' . htmlspecialchars($activity['grade_item']) . '</span>';
                    $html .= '<span class="activity-details">Dijak: ' . htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) .
                             ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) {
                        $html .= '<span class="activity-comment">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    }
                    $html .= '</div>';
                    break;

                case 3: // Student
                    $html .= '<div class="activity-icon student-icon">📚</div>';
                    $html .= '<div class="activity-content">';
                    $html .= '<span class="activity-title">Nova ocena: ' . htmlspecialchars($activity['subject_name']) . '</span>';
                    $html .= '<span class="activity-details">' . htmlspecialchars($activity['grade_item']) .
                             ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) {
                        $html .= '<span class="activity-comment">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    }
                    $html .= '</div>';
                    break;

                case 4: // Parent
                    $html .= '<div class="activity-icon parent-icon">👨‍👩‍👧‍👦</div>';
                    $html .= '<div class="activity-content">';
                    $html .= '<span class="activity-title">Nova ocena: ' . htmlspecialchars($activity['first_name']) .
                             ' - ' . htmlspecialchars($activity['subject_name']) . '</span>';
                    $html .= '<span class="activity-details">' . htmlspecialchars($activity['grade_item']) .
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
 * Retrieves the teacher ID associated with the current user
 *
 * @return int|null Teacher ID or null if not a teacher or not logged in
 */
function getTeacherId() {
    $userId = getUserId();
    if (!$userId) {
        return null;
    }

    try {
        $db = getDBConnection();
        if (!$db) {
            return null;
        }

        $query = "SELECT teacher_id FROM teachers WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['teacher_id'] : null;
    } catch (PDOException $e) {
        error_log("Database error in getTeacherId: " . $e->getMessage());
        return null;
    }
}

/**
 * Retrieves the student ID associated with the current user
 *
 * @return int|null Student ID or null if not a student or not logged in
 */
function getStudentId() {
    $userId = getUserId();
    if (!$userId) {
        return null;
    }

    try {
        $db = getDBConnection();
        if (!$db) {
            return null;
        }

        $query = "SELECT student_id FROM students WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['student_id'] : null;
    } catch (PDOException $e) {
        error_log("Database error in getStudentId: " . $e->getMessage());
        return null;
    }
}

/**
 * Retrieves the user ID from the session if it exists
 *
 * @return int|null User ID or null if not logged in
 */
function getUserId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Retrieves the user's role ID from the session if it exists
 *
 * @return int|null Role ID or null if not logged in
 */
function getUserRole() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : null;
}

/**
 * Creates the HTML for the teacher's class averages dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderTeacherClassAveragesWidget() {
    $teacherId = getTeacherId();

    if (!$teacherId) {
        return renderPlaceholderWidget('Za prikaz povprečij razredov se morate identificirati kot učitelj.');
    }

    $db = getDBConnection();
    if (!$db) {
        return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');
    }

    $classAverages = [];

    try {
        $query = "SELECT 
                    cs.class_subject_id,
                    c.class_id,
                    c.title AS class_title,
                    s.subject_id,
                    s.name AS subject_name,
                    COUNT(DISTINCT e.student_id) AS student_count,
                    AVG(
                        CASE WHEN g.points IS NOT NULL AND gi.max_points > 0 THEN 
                            (g.points / gi.max_points) * 100 
                        END
                    ) AS avg_score
                  FROM class_subjects cs
                  JOIN classes c ON cs.class_id = c.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN enrollments e ON c.class_id = e.class_id
                  LEFT JOIN grade_items gi ON gi.class_subject_id = cs.class_subject_id
                  LEFT JOIN grades g ON gi.item_id = g.item_id AND e.enroll_id = g.enroll_id
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

    if (empty($classAverages)) {
        $html .= '<div class="empty-message">Nimate razredov z ocenami.</div>';
    } else {
        $html .= '<div class="class-averages-container">';

        foreach ($classAverages as $class) {
            $avgScoreFormatted = $class['avg_score'] !== null ? number_format($class['avg_score'], 1) : 'N/A';
            $scoreClass = '';

            if ($class['avg_score'] !== null) {
                if ($class['avg_score'] >= 80) {
                    $scoreClass = 'score-excellent';
                } elseif ($class['avg_score'] >= 70) {
                    $scoreClass = 'score-good';
                } elseif ($class['avg_score'] >= 60) {
                    $scoreClass = 'score-fair';
                } else {
                    $scoreClass = 'score-poor';
                }
            }

            $html .= '<div class="class-average-card">';
            $html .= '<div class="class-title">';
            $html .= '<h4>' . htmlspecialchars($class['subject_name']) . ' - ' . htmlspecialchars($class['class_title']) . '</h4>';
            $html .= '</div>';

            $html .= '<div class="average-stats">';
            $html .= '<div class="average-score ' . $scoreClass . '">';
            $html .= '<span class="score-value">' . $avgScoreFormatted . '%</span>';
            $html .= '</div>';

            $html .= '<div class="stats">';
            $html .= '<div class="stat-item">';
            $html .= '<div class="stat-value">' . $avgScoreFormatted . '%</div>';
            $html .= '<div class="stat-label">Povprečje razreda</div>';
            $html .= '</div>';

            $html .= '<div class="stat-item">';
            $html .= '<div class="stat-value">' . (int)$class['student_count'] . '</div>';
            $html .= '<div class="stat-label">Dijakov</div>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '<div class="class-footer">';
            $html .= '<a href="teacher/gradebook.php?class_id=' . (int)$class['class_id'] . '" class="widget-link">Ogled redovalnice</a>';
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
function renderStudentClassAveragesWidget() {
    $studentId = getStudentId();

    if (!$studentId) {
        return renderPlaceholderWidget('Za prikaz povprečij razredov se morate identificirati kot dijak.');
    }

    $db = getDBConnection();
    if (!$db) {
        return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');
    }

    $studentGrades = [];

    try {
        $query = "SELECT 
                    s.subject_id,
                    s.name AS subject_name,
                    c.class_id,
                    c.title AS class_title,
                    cs.class_subject_id,
                    AVG(
                        CASE WHEN g.points IS NOT NULL AND gi.max_points > 0 
                        THEN (g.points / gi.max_points) * 100 
                        END
                    ) AS student_avg_score,
                    (SELECT AVG(
                        CASE WHEN gi2.max_points > 0 
                        THEN (g2.points / gi2.max_points) * 100 
                        END
                    )
                    FROM enrollments e2
                    JOIN grades g2 ON e2.enroll_id = g2.enroll_id
                    JOIN grade_items gi2 ON g2.item_id = gi2.item_id
                    WHERE gi2.class_subject_id = cs.class_subject_id
                    ) AS class_avg_score
                  FROM students st
                  JOIN enrollments e ON st.student_id = e.student_id
                  JOIN classes c ON e.class_id = c.class_id
                  JOIN class_subjects cs ON c.class_id = cs.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN grade_items gi ON gi.class_subject_id = cs.class_subject_id
                  LEFT JOIN grades g ON gi.item_id = g.item_id AND e.enroll_id = g.enroll_id
                  WHERE st.student_id = :student_id
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

    $html = '<div class="widget-content">';

    if (empty($studentGrades)) {
        $html .= '<div class="empty-message">Nimate razredov z ocenami.</div>';
    } else {
        $html .= '<div class="student-averages-table">';
        $html .= '<table class="data-table">';
        $html .= '<thead><tr><th>Predmet</th><th>Razred</th><th>Vaše povprečje</th><th>Povprečje razreda</th><th>Primerjava</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($studentGrades as $grade) {
            $studentAvgFormatted = $grade['student_avg_score'] !== null ? number_format($grade['student_avg_score'], 1) : 'N/A';
            $classAvgFormatted = $grade['class_avg_score'] !== null ? number_format($grade['class_avg_score'], 1) : 'N/A';

            $scoreClass = '';
            $comparisonText = '';
            $comparisonClass = '';

            if ($grade['student_avg_score'] !== null) {
                if ($grade['student_avg_score'] >= 80) {
                    $scoreClass = 'score-excellent';
                } elseif ($grade['student_avg_score'] >= 70) {
                    $scoreClass = 'score-good';
                } elseif ($grade['student_avg_score'] >= 60) {
                    $scoreClass = 'score-fair';
                } else {
                    $scoreClass = 'score-poor';
                }

                if ($grade['class_avg_score'] !== null) {
                    $diff = $grade['student_avg_score'] - $grade['class_avg_score'];

                    if ($diff > 5) {
                        $comparisonText = '+' . number_format($diff, 1) . '%';
                        $comparisonClass = 'above-average';
                    } elseif ($diff < -5) {
                        $comparisonText = number_format($diff, 1) . '%';
                        $comparisonClass = 'below-average';
                    } else {
                        $comparisonText = 'Povprečno';
                        $comparisonClass = 'at-average';
                    }
                }
            }

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($grade['subject_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($grade['class_title']) . '</td>';
            $html .= '<td class="' . $scoreClass . '">' . $studentAvgFormatted . '%</td>';
            $html .= '<td>' . $classAvgFormatted . '%</td>';
            $html .= '<td class="' . $comparisonClass . '">' . $comparisonText . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    }

    $html .= '<div class="widget-footer">';
    $html .= '<a href="../student/grades.php" class="widget-link">Ogled vseh ocen</a>';
    $html .= '</div>';

    return $html;
}

/**
 * Creates the HTML for the parent's view of their child's class averages
 *
 * @return string HTML content for the widget
 */
function renderParentChildClassAveragesWidget() {
    $userId = getUserId();
    if (!$userId) {
        return renderPlaceholderWidget('Za prikaz povprečij razredov se morate prijaviti.');
    }

    $db = getDBConnection();
    if (!$db) {
        return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');
    }

    $children = [];

    try {
        $query = "SELECT s.student_id, s.first_name, s.last_name
                 FROM students s
                 JOIN student_parent sp ON s.student_id = sp.student_id
                 JOIN parents p ON sp.parent_id = p.parent_id
                 WHERE p.user_id = :user_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderParentChildClassAveragesWidget (getting children): " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o otrocih.');
    }

    if (empty($children)) {
        return renderPlaceholderWidget('Na vaš račun ni povezanih otrok.');
    }

    $html = '<div class="widget-content">';

    foreach ($children as $child) {
        $html .= '<div class="child-grades-section">';
        $html .= '<h4>' . htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) . '</h4>';

        $childGrades = [];
        try {
            $query = "SELECT 
                        s.subject_id,
                        s.name AS subject_name,
                        c.class_id,
                        c.title AS class_title,
                        cs.class_subject_id,
                        AVG(
                            CASE WHEN g.points IS NOT NULL AND gi.max_points > 0 
                            THEN (g.points / gi.max_points) * 100 
                            END
                        ) AS student_avg_score,
                        (SELECT AVG(
                            IF(gi2.max_points > 0, (g2.points / gi2.max_points) * 100, NULL)
                        )
                        FROM enrollments e2
                        JOIN grades g2 ON e2.enroll_id = g2.enroll_id
                        JOIN grade_items gi2 ON g2.item_id = gi2.item_id
                        WHERE gi2.class_subject_id = cs.class_subject_id
                        ) AS class_avg_score
                      FROM enrollments e
                      JOIN classes c ON e.class_id = c.class_id
                      JOIN class_subjects cs ON c.class_id = cs.class_id
                      JOIN subjects s ON cs.subject_id = s.subject_id
                      LEFT JOIN grade_items gi ON gi.class_subject_id = cs.class_subject_id
                      LEFT JOIN grades g ON gi.item_id = g.item_id AND e.enroll_id = g.enroll_id
                      WHERE e.student_id = :student_id
                      GROUP BY s.subject_id, s.name, c.class_id, c.title, cs.class_subject_id
                      ORDER BY s.name, c.title";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
            $stmt->execute();
            $childGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in renderParentChildClassAveragesWidget (getting grades): " . $e->getMessage());
            $html .= '<div class="empty-message">Napaka pri pridobivanju podatkov o ocenah.</div>';
            continue;
        }

        if (empty($childGrades)) {
            $html .= '<div class="empty-message">Za tega otroka ni podatkov o ocenah.</div>';
        } else {
            $html .= '<div class="child-grades-table">';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Predmet</th><th>Razred</th><th>Povprečje</th><th>Povprečje razreda</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($childGrades as $grade) {
                $studentAvgFormatted = $grade['student_avg_score'] !== null ? number_format($grade['student_avg_score'], 1) : 'N/A';
                $classAvgFormatted = $grade['class_avg_score'] !== null ? number_format($grade['class_avg_score'], 1) : 'N/A';

                $scoreClass = '';

                if ($grade['student_avg_score'] !== null) {
                    if ($grade['student_avg_score'] >= 80) {
                        $scoreClass = 'score-excellent';
                    } elseif ($grade['student_avg_score'] >= 70) {
                        $scoreClass = 'score-good';
                    } elseif ($grade['student_avg_score'] >= 60) {
                        $scoreClass = 'score-fair';
                    } else {
                        $scoreClass = 'score-poor';
                    }
                }

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($grade['subject_name']) . '</td>';
                $html .= '<td>' . htmlspecialchars($grade['class_title']) . '</td>';
                $html .= '<td class="' . $scoreClass . '">' . $studentAvgFormatted . '%</td>';
                $html .= '<td>' . $classAvgFormatted . '%</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '<div class="child-footer">';
        $html .= '<a href="parent/grades.php?student_id=' . (int)$child['student_id'] . '" class="widget-link">Ogled vseh ocen</a>';
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
function renderUpcomingClassesWidget() {
    $studentId = getStudentId();

    if (!$studentId) {
        return renderPlaceholderWidget('Za prikaz prihajajočih ur se morate identificirati kot dijak.');
    }

    $db = getDBConnection();
    if (!$db) {
        return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');
    }

    $today = date('Y-m-d');
    $oneWeekLater = date('Y-m-d', strtotime('+7 days'));
    $upcomingClasses = [];

    try {
        $query = "SELECT 
                    p.period_id,
                    p.period_date,
                    p.period_label,
                    s.name AS subject_name,
                    u.username AS teacher_name,
                    c.title AS class_title
                  FROM students st
                  JOIN enrollments e ON st.student_id = e.student_id
                  JOIN classes c ON e.class_id = c.class_id
                  JOIN class_subjects cs ON c.class_id = cs.class_id
                  JOIN periods p ON cs.class_subject_id = p.class_subject_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  JOIN teachers t ON cs.teacher_id = t.teacher_id
                  JOIN users u ON t.user_id = u.user_id
                  WHERE st.student_id = :student_id
                    AND p.period_date BETWEEN :today AND :one_week_later
                  ORDER BY p.period_date , p.period_label
                  LIMIT 10";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->bindParam(':today', $today, PDO::PARAM_STR);
        $stmt->bindParam(':one_week_later', $oneWeekLater, PDO::PARAM_STR);
        $stmt->execute();

        $upcomingClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderUpcomingClassesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o prihajajočih urah.');
    }

    $html = '<div class="widget-content">';

    if (empty($upcomingClasses)) {
        $html .= '<div class="empty-message">Ni prihajajočih ur v naslednjem tednu.</div>';
    } else {
        $currentDay = '';
        $html .= '<div class="upcoming-classes">';

        foreach ($upcomingClasses as $class) {
            $classDate = date('Y-m-d', strtotime($class['period_date']));
            $formattedDate = date('d.m.Y', strtotime($class['period_date']));
            $dayName = date('l', strtotime($class['period_date']));

            switch ($dayName) {
                case 'Monday': $dayName = 'Ponedeljek'; break;
                case 'Tuesday': $dayName = 'Torek'; break;
                case 'Wednesday': $dayName = 'Sreda'; break;
                case 'Thursday': $dayName = 'Četrtek'; break;
                case 'Friday': $dayName = 'Petek'; break;
                case 'Saturday': $dayName = 'Sobota'; break;
                case 'Sunday': $dayName = 'Nedelja'; break;
            }

            if ($classDate != $currentDay) {
                if ($currentDay != '') {
                    $html .= '</div>';
                }

                $currentDay = $classDate;

                $html .= '<div class="day-group">';
                $html .= '<div class="day-header">';
                $html .= '<h4>' . $dayName . ', ' . $formattedDate . '</h4>';
                $html .= '</div>';
                $html .= '<div class="day-classes">';
            }

            $html .= '<div class="class-item">';
            $html .= '<div class="class-time">' . htmlspecialchars($class['period_label']) . '</div>';
            $html .= '<div class="class-details">';
            $html .= '<div class="class-subject">' . htmlspecialchars($class['subject_name']) . '</div>';
            $html .= '<div class="class-teacher">' . htmlspecialchars($class['teacher_name']) . '</div>';
            $html .= '<div class="class-room">' . htmlspecialchars($class['class_title']) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        if ($currentDay != '') {
            $html .= '</div></div>';
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
function renderStudentGradesWidget() {
    $studentId = getStudentId();

    if (!$studentId) {
        return renderPlaceholderWidget('Za prikaz ocen se morate identificirati kot dijak.');
    }

    $db = getDBConnection();
    if (!$db) {
        return renderPlaceholderWidget('Napaka pri povezovanju z bazo podatkov.');
    }

    $recentGrades = [];
    $subjectAverages = [];

    try {
        $query = "SELECT 
                    g.grade_id,
                    g.points,
                    gi.max_points,
                    gi.name AS grade_item_name,
                    s.name AS subject_name,
                    g.comment,
                    ROUND((g.points / gi.max_points) * 100, 1) AS percentage,
                    g.grade_id AS date_added
                  FROM grades g
                  JOIN grade_items gi ON g.item_id = gi.item_id
                  JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  JOIN enrollments e ON g.enroll_id = e.enroll_id
                  WHERE e.student_id = :student_id
                  ORDER BY g.grade_id DESC
                  LIMIT 5";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $recentGrades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $query = "SELECT 
                    s.subject_id,
                    s.name AS subject_name,
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
                  GROUP BY s.subject_id
                  ORDER BY avg_score DESC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $subjectAverages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in renderStudentGradesWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o ocenah.');
    }

    $html = '<div class="widget-content grades-widget">';

    $html .= '<div class="grades-section subject-averages">';
    $html .= '<h4>Povprečja predmetov</h4>';

    if (empty($subjectAverages)) {
        $html .= '<div class="empty-message">Ni podatkov o povprečjih predmetov.</div>';
    } else {
        $html .= '<div class="averages-list">';

        foreach ($subjectAverages as $subject) {
            if ($subject['avg_score'] === null) {
                continue;
            }

            $avgScore = number_format($subject['avg_score'], 1);
            $gradeCount = (int)$subject['grade_count'];

            $scoreClass = '';
            if ($subject['avg_score'] >= 80) {
                $scoreClass = 'score-excellent';
            } elseif ($subject['avg_score'] >= 70) {
                $scoreClass = 'score-good';
            } elseif ($subject['avg_score'] >= 60) {
                $scoreClass = 'score-fair';
            } else {
                $scoreClass = 'score-poor';
            }

            $html .= '<div class="subject-average-item">';
            $html .= '<div class="subject-name">' . htmlspecialchars($subject['subject_name']) . '</div>';
            if ($gradeCount == 1) {
                $gradeSuffix = 'ocena';
            } elseif ($gradeCount < 5) {
                $gradeSuffix = 'ocene';
            } else {
                $gradeSuffix = 'ocen';
            }
            $html .= '<div class="subject-grade-count">' . $gradeCount . ' ' . $gradeSuffix . '</div>';
            $html .= '<div class="subject-average ' . $scoreClass . '">' . $avgScore . '%</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';

    $html .= '<div class="grades-section recent-grades">';
    $html .= '<h4>Nedavne ocene</h4>';

    if (empty($recentGrades)) {
        $html .= '<div class="empty-message">Nimate nedavnih ocen.</div>';
    } else {
        $html .= '<div class="recent-grades-list">';

        foreach ($recentGrades as $grade) {
            if ($grade['percentage'] >= 80) {
                $scoreClass = 'score-excellent';
            } elseif ($grade['percentage'] >= 70) {
                $scoreClass = 'score-good';
            } elseif ($grade['percentage'] >= 60) {
                $scoreClass = 'score-fair';
            } else {
                $scoreClass = 'score-poor';
            }

            $html .= '<div class="grade-item">';
            $html .= '<div class="grade-header">';
            $html .= '<div class="grade-subject">' . htmlspecialchars($grade['subject_name']) . '</div>';
            $html .= '<div class="grade-score ' . $scoreClass . '">' .
                     htmlspecialchars($grade['points']) . '/' .
                     htmlspecialchars($grade['max_points']) .
                     ' (' . htmlspecialchars($grade['percentage']) . '%)</div>';
            $html .= '</div>';

            $html .= '<div class="grade-details">';
            $html .= '<div class="grade-name">' . htmlspecialchars($grade['grade_item_name']) . '</div>';

            if (!empty($grade['comment'])) {
                $html .= '<div class="grade-comment">' . htmlspecialchars($grade['comment']) . '</div>';
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';

    $html .= '<div class="widget-footer">';
    $html .= '<a href="../student/grades.php" class="widget-link">Ogled vseh ocen</a>';
    $html .= '</div>';

    return $html;
}

/**
 * Translates the single-letter status code into a human-readable label
 *
 * @param string $status The status code (P, A, L)
 * @return string Human-readable status label
 */
function getAttendanceStatusLabel(string $status): string {
    switch ($status) {
        case 'P':
            return 'Prisoten';
        case 'A':
            return 'Odsoten';
        case 'L':
            return 'Zamuda';
        default:
            return 'Neznano';
    }
}

/**
 * Calculates presence, absence, and lateness rates from attendance records
 *
 * @param array $attendance Array of attendance records to analyze
 * @return array Statistics including present_count, absent_count, late_count, and percentages
 */
function calculateAttendanceStats(array $attendance): array {
    $total = count($attendance);
    $present = 0;
    $absent = 0;
    $late = 0;

    foreach ($attendance as $record) {
        switch ($record['status']) {
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

    $presentPercent = $total > 0 ? round(($present / $total) * 100, 1) : 0;
    $absentPercent = $total > 0 ? round(($absent / $total) * 100, 1) : 0;
    $latePercent = $total > 0 ? round(($late / $total) * 100, 1) : 0;

    return [
        'total' => $total,
        'present_count' => $present,
        'absent_count' => $absent,
        'late_count' => $late,
        'present_percent' => $presentPercent,
        'absent_percent' => $absentPercent,
        'late_percent' => $latePercent
    ];
}

/**
 * Displays counts of users by role and recent user activity widget
 *
 * @return string HTML content for the widget
 */
function renderAdminUserStatsWidget(): string {
    try {
        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $roleQuery = "SELECT r.role_id, r.name, COUNT(u.user_id) as count 
                      FROM roles r 
                      LEFT JOIN users u ON r.role_id = u.role_id 
                      GROUP BY r.role_id 
                      ORDER BY r.role_id";
        $roleStmt = $db->prepare($roleQuery);
        $roleStmt->execute();
        $roleCounts = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentQuery = "SELECT COUNT(*) as new_users 
                        FROM users 
                        WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY)";
        $recentStmt = $db->prepare($recentQuery);
        $recentStmt->execute();
        $recentUsers = $recentStmt->fetch(PDO::FETCH_ASSOC)['new_users'];

        $output = '<div class="stats-container">';

        $totalUsers = array_sum(array_column($roleCounts, 'count'));
        $output .= '<div class="stat-item stat-total">
                        <span class="stat-number">' . htmlspecialchars($totalUsers) . '</span>
                        <span class="stat-label">Skupaj uporabnikov</span>
                    </div>';

        $output .= '<div class="stat-item stat-new">
                        <span class="stat-number">' . htmlspecialchars($recentUsers) . '</span>
                        <span class="stat-label">Novih uporabnikov (7 dni)</span>
                    </div>';

        $output .= '<div class="stat-breakdown">';
        $output .= '<h4>Uporabniki po vlogah</h4>';
        $output .= '<ul class="role-list">';
        foreach ($roleCounts as $role) {
            $output .= '<li>
                            <span class="role-name">' . htmlspecialchars($role['name']) . ':</span> 
                            <span class="role-count">' . htmlspecialchars($role['count']) . '</span>
                        </li>';
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
function renderAdminSystemStatusWidget(): string {
    try {
        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $tableQuery = "SELECT 
                          COUNT(DISTINCT TABLE_NAME) as table_count,
                          SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 as size_mb
                       FROM 
                          information_schema.TABLES 
                       WHERE 
                          TABLE_SCHEMA = DATABASE()";
        $tableStmt = $db->prepare($tableQuery);
        $tableStmt->execute();
        $tableStats = $tableStmt->fetch(PDO::FETCH_ASSOC);

        $sessionPath = session_save_path();
        $sessionCount = 0;
        if (is_dir($sessionPath)) {
            $sessionFiles = glob($sessionPath . "/sess_*");
            if ($sessionFiles !== false) {
                // Count only not expired sessions (last 30 minutes)
                $sessionCount = count(array_filter($sessionFiles, function($file) {
                    return (time() - filemtime($file)) < 1800;
                }));
            }
        }

        $output = '<div class="system-status">';

        $output .= '<div class="status-section">';
        $output .= '<h4>Podatkovna baza</h4>';
        $output .= '<ul>';
        $output .= '<li><strong>Tabele:</strong> ' . htmlspecialchars($tableStats['table_count']) . '</li>';
        $output .= '<li><strong>Velikost:</strong> ' . htmlspecialchars(round($tableStats['size_mb'], 2)) . ' MB</li>';
        $output .= '<li><strong>Tip:</strong> MySQL ' . htmlspecialchars($db->getAttribute(PDO::ATTR_SERVER_VERSION)) . '</li>';
        $output .= '</ul>';
        $output .= '</div>';

        $output .= '<div class="status-section">';
        $output .= '<h4>Strežnik</h4>';
        $output .= '<ul>';
        $output .= '<li><strong>PHP verzija:</strong> ' . htmlspecialchars(PHP_VERSION) . '</li>';
        $output .= '<li><strong>Aktivne seje:</strong> ' . htmlspecialchars($sessionCount) . '</li>';
        $output .= '<li><strong>Max upload:</strong> ' . htmlspecialchars(ini_get('upload_max_filesize')) . '</li>';
        $output .= '<li><strong>Čas strežnika:</strong> ' . htmlspecialchars(date('Y-m-d H:i')) . '</li>';
        $output .= '</ul>';
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
function renderAdminAttendanceWidget(): string {
    try {
        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $query = "SELECT a.status, COUNT(*) as count
                  FROM attendance a
                  JOIN periods p ON a.period_id = p.period_id
                  WHERE p.period_date BETWEEN DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY) AND CURRENT_DATE()
                  GROUP BY a.status";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $present = 0;
        $absent = 0;
        $late = 0;
        $total = 0;

        foreach ($attendanceData as $data) {
            switch ($data['status']) {
                case 'P':
                    $present = $data['count'];
                    break;
                case 'A':
                    $absent = $data['count'];
                    break;
                case 'L':
                    $late = $data['count'];
                    break;
            }
            $total += $data['count'];
        }

        $presentPercent = $total > 0 ? round(($present / $total) * 100, 1) : 0;
        $absentPercent = $total > 0 ? round(($absent / $total) * 100, 1) : 0;
        $latePercent = $total > 0 ? round(($late / $total) * 100, 1) : 0;

        $bestClassQuery = "SELECT c.class_code, c.title, 
                          COUNT(CASE WHEN a.status = 'P' THEN 1  END) as present_count,
                          COUNT(a.att_id) as total_count,
                          (COUNT(CASE WHEN a.status = 'P' THEN 1  END) / COUNT(a.att_id) * 100) as present_percent
                       FROM attendance a
                       JOIN periods p ON a.period_id = p.period_id
                       JOIN enrollments e ON a.enroll_id = e.enroll_id
                       JOIN students s ON e.student_id = s.student_id
                       JOIN classes c ON s.class_code = c.class_code
                       WHERE p.period_date BETWEEN DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY) AND CURRENT_DATE()
                       GROUP BY c.class_code, c.title
                       HAVING COUNT(a.att_id) > 0
                       ORDER BY present_percent DESC
                       LIMIT 1";
        $bestClassStmt = $db->prepare($bestClassQuery);
        $bestClassStmt->execute();
        $bestClass = $bestClassStmt->fetch(PDO::FETCH_ASSOC);

        $output = '<div class="attendance-stats">';

        $output .= '<div class="attendance-overview">';
        $output .= '<h4>Skupna prisotnost (zadnjih 30 dni)</h4>';

        $output .= '<div class="attendance-chart">';
        $output .= '<div class="chart-bar chart-present" style="width:' . $presentPercent . '%">' . $presentPercent . '%</div>';
        $output .= '<div class="chart-bar chart-absent" style="width:' . $absentPercent . '%">' . $absentPercent . '%</div>';
        $output .= '<div class="chart-bar chart-late" style="width:' . $latePercent . '%">' . $latePercent . '%</div>';
        $output .= '</div>';

        $output .= '<div class="attendance-legend">';
        $output .= '<span class="legend-item"><span class="legend-color bg-present"></span>Prisotni: ' . $present . '</span>';
        $output .= '<span class="legend-item"><span class="legend-color bg-absent"></span>Odsotni: ' . $absent . '</span>';
        $output .= '<span class="legend-item"><span class="legend-color bg-late"></span>Zamuda: ' . $late . '</span>';
        $output .= '</div>';
        $output .= '</div>';

        if ($bestClass) {
            $output .= '<div class="best-class">';
            $output .= '<h4>Razred z najboljšo prisotnostjo</h4>';
            $output .= '<div class="best-class-info">';
            $output .= '<p class="best-class-title">' . htmlspecialchars($bestClass['title']) . ' (' . htmlspecialchars($bestClass['class_code']) . ')</p>';
            $output .= '<p class="best-class-percent">' . htmlspecialchars(round($bestClass['present_percent'], 1)) . '% prisotnost</p>';
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
function renderTeacherClassOverviewWidget(): string {
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) {
            return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');
        }

        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $query = "SELECT c.class_id, c.class_code, c.title as class_title, 
                         s.subject_id, s.name as subject_name,
                         COUNT(DISTINCT e.student_id) as student_count
                  FROM class_subjects cs
                  JOIN classes c ON cs.class_id = c.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  LEFT JOIN enrollments e ON c.class_id = e.class_id
                  WHERE cs.teacher_id = :teacher_id
                  GROUP BY c.class_id, c.class_code, c.title, s.subject_id, s.name
                  ORDER BY c.title";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($classes)) {
            return renderPlaceholderWidget('Trenutno ne poučujete nobenega razreda.');
        }

        $output = '<div class="teacher-class-overview">';
        $output .= '<ul class="class-list">';

        foreach ($classes as $class) {
            $output .= '<li class="class-item">';
            $output .= '<div class="class-header">';
            $output .= '<span class="class-name">' . htmlspecialchars($class['class_title']) . '</span>';
            $output .= '<span class="class-code badge">' . htmlspecialchars($class['class_code']) . '</span>';
            $output .= '</div>';

            $output .= '<div class="class-details">';
            $output .= '<span class="subject">' . htmlspecialchars($class['subject_name']) . '</span>';
            $output .= '<span class="student-count"><i class="icon-user"></i> ' . htmlspecialchars($class['student_count']) . ' učencev</span>';
            $output .= '</div>';

            $output .= '<div class="class-actions">';
            $output .= '<a href="/teacher/gradebook.php?class_id=' . urlencode($class['class_id']) . '&subject_id=' . urlencode($class['subject_id']) . '" class="btn btn-sm">Redovalnica</a>';
            $output .= '<a href="/teacher/attendance.php?class_id=' . urlencode($class['class_id']) . '" class="btn btn-sm">Prisotnost</a>';
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
function renderTeacherAttendanceWidget(): string {
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) {
            return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');
        }

        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $todayQuery = "SELECT p.period_id, p.period_label, c.class_code, s.name as subject_name,
                             COUNT(DISTINCT e.student_id) as total_students,
                             COUNT(DISTINCT a.att_id) as recorded_attendance,
                             c.class_id, cs.subject_id
                      FROM periods p
                      JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                      JOIN classes c ON cs.class_id = c.class_id
                      JOIN subjects s ON cs.subject_id = s.subject_id
                      LEFT JOIN enrollments e ON c.class_id = e.class_id
                      LEFT JOIN attendance a ON p.period_id = a.period_id AND e.enroll_id = a.enroll_id
                      WHERE cs.teacher_id = :teacher_id
                      AND DATE(p.period_date) = CURRENT_DATE()
                      GROUP BY p.period_id, p.period_label, c.class_code, s.name, c.class_id, cs.subject_id
                      ORDER BY p.period_label";

        $stmt = $db->prepare($todayQuery);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();
        $todayClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($todayClasses)) {
            return renderPlaceholderWidget('Danes nimate pouka.');
        }

        $output = '<div class="teacher-today-attendance">';
        $output .= '<h4>Današnja prisotnost</h4>';
        $output .= '<table class="attendance-table">';
        $output .= '<thead><tr>
                      <th>Ura</th>
                      <th>Razred</th>
                      <th>Predmet</th>
                      <th>Status</th>
                    </tr></thead>';
        $output .= '<tbody>';

        foreach ($todayClasses as $class) {
            $recorded = $class['recorded_attendance'];
            $total = $class['total_students'];
            $completionPercent = $total > 0 ? round(($recorded / $total) * 100) : 0;

            $statusClass = 'status-incomplete';
            $statusText = 'Nepopolno';

            if ($completionPercent == 100) {
                $statusClass = 'status-complete';
                $statusText = 'Zabeleženo';
            } else if ($completionPercent > 0) {
                $statusClass = 'status-partial';
                $statusText = $completionPercent . '%';
            }

            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars($class['period_label']) . '</td>';
            $output .= '<td>' . htmlspecialchars($class['class_code']) . '</td>';
            $output .= '<td>' . htmlspecialchars($class['subject_name']) . '</td>';
            $output .= '<td><span class="attendance-status ' . $statusClass . '">' . $statusText . '</span></td>';
            $output .= '</tr>';
        }

        $output .= '</tbody>';
        $output .= '</table>';

        $output .= '<div class="mt-md text-right">';
        $output .= '<a href="/teacher/attendance.php" class="btn btn-secondary btn-sm">Zabeleži prisotnost</a>';
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
function renderTeacherPendingJustificationsWidget(): string {
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) {
            return renderPlaceholderWidget('Informacije o učitelju niso na voljo.');
        }

        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $query = "SELECT a.att_id, s.first_name, s.last_name, c.class_code, c.title as class_title,
                         p.period_date, p.period_label, a.status, a.justification, a.justification_file
                  FROM attendance a
                  JOIN enrollments e ON a.enroll_id = e.enroll_id
                  JOIN students s ON e.student_id = s.student_id
                  JOIN periods p ON a.period_id = p.period_id
                  JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                  JOIN classes c ON cs.class_id = c.class_id
                  WHERE cs.teacher_id = :teacher_id
                  AND a.status = 'A'
                  AND a.justification IS NOT NULL
                  AND a.approved IS NULL
                  ORDER BY p.period_date DESC, s.last_name, s.first_name
                  LIMIT 5";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
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
        $totalPending = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        if (empty($justifications)) {
            return renderPlaceholderWidget('Trenutno ni čakajočih opravičil.');
        }

        $output = '<div class="pending-justifications">';

        if ($totalPending > 0) {
            $output .= '<div class="justifications-header">';
            $output .= '<span class="badge badge-notification">' . htmlspecialchars($totalPending) . '</span>';
            $output .= ' čakajočih opravičil';
            $output .= '</div>';
        }

        $output .= '<ul class="justification-list">';

        foreach ($justifications as $just) {
            $output .= '<li class="justification-item">';

            $output .= '<div class="student-info">';
            $output .= '<strong>' . htmlspecialchars($just['first_name'] . ' ' . $just['last_name']) . '</strong>';
            $output .= '<span class="class-code">' . htmlspecialchars($just['class_code']) . '</span>';
            $output .= '</div>';

            $formattedDate = date('d.m.Y', strtotime($just['period_date']));
            $output .= '<div class="absence-info">';
            $output .= '<span class="date">' . htmlspecialchars($formattedDate) . '</span>';
            $output .= '<span class="period">' . htmlspecialchars($just['period_label']) . '. ura</span>';
            $output .= '</div>';

            if (!empty($just['justification'])) {
                $justificationExcerpt = mb_substr($just['justification'], 0, 60);
                if (mb_strlen($just['justification']) > 60) {
                    $justificationExcerpt .= '...';
                }
                $output .= '<div class="justification-text">' . htmlspecialchars($justificationExcerpt) . '</div>';
            }

            if (!empty($just['justification_file'])) {
                $output .= '<div class="attachment-indicator"><i class="icon-attachment"></i> Priloga</div>';
            }

            $output .= '<div class="justification-actions">';
            $output .= '<a href="/teacher/justifications.php?action=approve&id=' . urlencode($just['att_id']) . '" class="btn btn-sm btn-success">Odobri</a>';
            $output .= '<a href="/teacher/justifications.php?action=reject&id=' . urlencode($just['att_id']) . '" class="btn btn-sm btn-danger">Zavrni</a>';
            $output .= '</div>';

            $output .= '</li>';
        }

        $output .= '</ul>';

        if ($totalPending > count($justifications)) {
            $output .= '<div class="more-link">';
            $output .= '<a href="/teacher/justifications.php">Prikaži vsa opravičila (' . $totalPending . ')</a>';
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
function renderStudentAttendanceWidget(): string {
    $studentId = getStudentId();

    if (!$studentId) {
        return renderPlaceholderWidget('Za prikaz prisotnosti se morate identificirati kot dijak.');
    }

    try {
        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $stats = [
            'total' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'justified' => 0,
            'attendance_rate' => 0,
            'recent' => []
        ];

        $query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'L' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN status = 'A' AND justification IS NOT NULL AND approved = 1 THEN 1 ELSE 0 END) as justified
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
            $stats['absent'] = (int)$result['absent'];
            $stats['late'] = (int)$result['late'];
            $stats['justified'] = (int)$result['justified'];

            if ($stats['total'] > 0) {
                $stats['attendance_rate'] = round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1);
            }
        }

        $recentQuery = "SELECT 
            a.status,
            a.justification,
            a.approved,
            p.period_date,
            p.period_label,
            s.name as subject_name,
            c.title as class_title
         FROM attendance a
         JOIN periods p ON a.period_id = p.period_id
         JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
         JOIN subjects s ON cs.subject_id = s.subject_id
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         WHERE e.student_id = :student_id
         ORDER BY p.period_date DESC, p.period_label
         LIMIT 5";

        $stmt = $db->prepare($recentQuery);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        $stats['recent'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $html = '<div class="widget-content attendance-widget">';

        $html .= '<div class="attendance-summary">';
        $html .= '<div class="attendance-rate">';
        $html .= '<div class="rate-circle" data-percentage="' . $stats['attendance_rate'] . '">';
        $html .= '<svg viewBox="0 0 36 36" class="circular-chart">';
        $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<path class="circle" stroke-dasharray="' . $stats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<text x="18" y="20.35" class="percentage">' . $stats['attendance_rate'] . '%</text>';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="rate-label">Prisotnost</div>';
        $html .= '</div>';

        $html .= '<div class="attendance-breakdown">';
        $html .= '<div class="breakdown-item">';
        $html .= '<span class="count">' . $stats['present'] . '</span>';
        $html .= '<span class="label">Prisoten</span>';
        $html .= '</div>';
        $html .= '<div class="breakdown-item">';
        $html .= '<span class="count">' . $stats['absent'] . '</span>';
        $html .= '<span class="label">Odsoten</span>';
        $html .= '</div>';
        $html .= '<div class="breakdown-item">';
        $html .= '<span class="count">' . $stats['late'] . '</span>';
        $html .= '<span class="label">Zamuda</span>';
        $html .= '</div>';
        $html .= '<div class="breakdown-item">';
        $html .= '<span class="count">' . $stats['justified'] . '</span>';
        $html .= '<span class="label">Opravičeno</span>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        if (!empty($stats['recent'])) {
            $html .= '<div class="recent-attendance">';
            $html .= '<h4>Nedavne prisotnosti</h4>';
            $html .= '<table class="mini-table">';
            $html .= '<thead><tr><th>Datum</th><th>Predmet</th><th>Status</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($stats['recent'] as $record) {
                $date = date('d.m.', strtotime($record['period_date']));
                $classInfo = $record['subject_name'] . ' - ' . $record['period_label'];

                $statusClass = '';
                $statusLabel = '';

                switch ($record['status']) {
                    case 'P':
                        $statusClass = 'status-present';
                        $statusLabel = 'Prisoten';
                        break;
                    case 'A':
                        $statusClass = 'status-absent';
                        $statusLabel = ($record['approved'] == 1) ? 'Opravičeno' : 'Neopravičeno';
                        break;
                    case 'L':
                        $statusClass = 'status-late';
                        $statusLabel = 'Zamuda';
                        break;
                }

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($date) . '</td>';
                $html .= '<td>' . htmlspecialchars($classInfo) . '</td>';
                $html .= '<td class="' . $statusClass . '">' . htmlspecialchars($statusLabel) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '<div class="widget-footer">';
        $html .= '<a href="/student/attendance.php" class="widget-link">Popolna evidenca prisotnosti</a>';

        if ($stats['absent'] > $stats['justified']) {
            $html .= '<a href="/student/justification.php" class="widget-link">Oddaj opravičilo</a>';
        }

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
function renderParentAttendanceWidget(): string {
    $userId = getUserId();

    if (!$userId) {
        return renderPlaceholderWidget('Za prikaz prisotnosti otrok se morate prijaviti.');
    }

    try {
        $db = getDBConnection();
        if (!$db) {
            return renderPlaceholderWidget('Povezava s podatkovno bazo ni uspela.');
        }

        $parentQuery = "SELECT parent_id FROM parents WHERE user_id = :user_id";
        $parentStmt = $db->prepare($parentQuery);
        $parentStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $parentStmt->execute();
        $parentResult = $parentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$parentResult) {
            return renderPlaceholderWidget('Podatki o staršu niso na voljo.');
        }

        $parentId = $parentResult['parent_id'];

        $childrenQuery = "SELECT s.student_id, s.first_name, s.last_name, s.class_code
                          FROM students s
                          JOIN student_parent sp ON s.student_id = sp.student_id
                          WHERE sp.parent_id = :parent_id";
        $childrenStmt = $db->prepare($childrenQuery);
        $childrenStmt->bindParam(':parent_id', $parentId, PDO::PARAM_INT);
        $childrenStmt->execute();
        $children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($children)) {
            return renderPlaceholderWidget('Na vaš račun ni povezanih otrok.');
        }

        $html = '<div class="widget-content parent-attendance-widget">';

        foreach ($children as $child) {
            $childStats = [
                'student_id' => $child['student_id'],
                'name' => $child['first_name'] . ' ' . $child['last_name'],
                'class_code' => $child['class_code'],
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'justified' => 0,
                'attendance_rate' => 0,
                'recent_absences' => []
            ];

            $statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'L' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'A' AND justification IS NOT NULL AND approved = 1 THEN 1 ELSE 0 END) as justified
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

                if ($childStats['total'] > 0) {
                    $childStats['attendance_rate'] = round((($childStats['present'] + $childStats['late']) / $childStats['total']) * 100, 1);
                }
            }

            $absenceQuery = "SELECT 
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
             WHERE e.student_id = :student_id AND a.status = 'A'
             ORDER BY p.period_date DESC
             LIMIT 3";

            $absenceStmt = $db->prepare($absenceQuery);
            $absenceStmt->bindParam(':student_id', $child['student_id'], PDO::PARAM_INT);
            $absenceStmt->execute();
            $childStats['recent_absences'] = $absenceStmt->fetchAll(PDO::FETCH_ASSOC);

            $html .= '<div class="child-attendance-summary">';
            $html .= '<div class="child-header">';
            $html .= '<h4>' . htmlspecialchars($childStats['name']) . ' <span class="class-code">(' . htmlspecialchars($childStats['class_code']) . ')</span></h4>';
            $html .= '</div>';

            $html .= '<div class="attendance-stats-row">';

            $html .= '<div class="mini-attendance-rate">';
            $html .= '<div class="mini-rate-circle" data-percentage="' . $childStats['attendance_rate'] . '">';
            $html .= '<svg viewBox="0 0 36 36" class="circular-chart mini">';
            $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
            $html .= '<path class="circle" stroke-dasharray="' . $childStats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
            $html .= '<text x="18" y="20.35" class="percentage">' . $childStats['attendance_rate'] . '%</text>';
            $html .= '</svg>';
            $html .= '</div>';
            $html .= '<span class="mini-label">Prisotnost</span>';
            $html .= '</div>';

            $html .= '<div class="mini-stats">';
            $html .= '<div class="mini-stat">';
            $html .= '<span class="mini-count present">' . $childStats['present'] . '</span>';
            $html .= '<span class="mini-label">Prisoten</span>';
            $html .= '</div>';

            $html .= '<div class="mini-stat">';
            $html .= '<span class="mini-count absent">' . $childStats['absent'] . '</span>';
            $html .= '<span class="mini-label">Odsoten</span>';
            $html .= '</div>';

            $html .= '<div class="mini-stat">';
            $html .= '<span class="mini-count late">' . $childStats['late'] . '</span>';
            $html .= '<span class="mini-label">Zamuda</span>';
            $html .= '</div>';

            $html .= '<div class="mini-stat">';
            $html .= '<span class="mini-count justified">' . $childStats['justified'] . '</span>';
            $html .= '<span class="mini-label">Opravičeno</span>';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '</div>';

            if (!empty($childStats['recent_absences'])) {
                $html .= '<div class="recent-absences">';
                $html .= '<h5>Nedavne odsotnosti</h5>';
                $html .= '<ul class="absence-list">';

                foreach ($childStats['recent_absences'] as $absence) {
                    $date = date('d.m.', strtotime($absence['period_date']));
                    $justificationStatus = '';

                    if (!empty($absence['justification'])) {
                        if ($absence['approved'] === null) {
                            $justificationStatus = 'V obravnavi';
                        } else if ($absence['approved'] == 1) {
                            $justificationStatus = 'Opravičeno';
                        } else {
                            $justificationStatus = 'Zavrnjeno';
                        }
                    } else {
                        $justificationStatus = 'Neopravičeno';
                    }

                    $html .= '<li>';
                    $html .= '<span class="absence-date">' . htmlspecialchars($date) . '</span>';
                    $html .= '<span class="absence-class">' . htmlspecialchars($absence['subject_name']) . '</span>';
                    $html .= '<span class="absence-status ' . strtolower(str_replace(' ', '-', $justificationStatus)) . '">' .
                             htmlspecialchars($justificationStatus) . '</span>';
                    $html .= '</li>';
                }

                $html .= '</ul>';
                $html .= '</div>';
            }

            $html .= '<div class="child-footer">';
            $html .= '<a href="/parent/attendance.php?student_id=' . $childStats['student_id'] . '" class="widget-link">Popolna evidenca prisotnosti</a>';
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
function sendJsonErrorResponse(string $message, int $statusCode = 400, string $context = ''): never {
    http_response_code($statusCode);
    try {
        echo json_encode(['success' => false, 'message' => $message], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        $logContext = $context ? " ($context)" : '';
        error_log("API Error$logContext: " . $e->getMessage());
    }
    exit;
}
