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
 * - getUserInfo(int $userId): ?array - Retrieves comprehensive user profile with role-specific data (teacher_id, student details, parent_id with children)
 *
 * Security Functions:
 * - sendJsonErrorResponse(string $message, int $statusCode = 400, string $context = ''): never - Sends a standardized JSON error response and exits
 * - validateDate(string $date): bool - Validates date format (YYYY-MM-DD)
 *
 * Formatting Functions:
 * - formatDateDisplay(string $date): string - Formats date from YYYY-MM-DD to DD.MM.YYYY
 * - formatDateTimeDisplay(string $datetime): string - Formats datetime to DD.MM.YYYY
 *
 * Navigation and Widgets:
 * - getNavItemsByRole(int $role): array - Returns navigation items based on user role
 * - getWidgetsByRole(int $role): array - Returns dashboard widgets based on user role
 * - renderPlaceholderWidget(string $message = 'Podatki trenutno niso na voljo.'): string - Renders a placeholder widget
 *
 * Activity Widgets:
 * - renderRecentActivityWidget(): string - Renders the recent activity widget
 *
 * Attendance Utilities:
 * - getAttendanceStatusLabel(string $status): string - Translates attendance status code to readable label
 * - calculateAttendanceStats(array $attendance): array - Calculates attendance statistics from a set of records
 * - calculateClassAverage(array $grades): float - Calculates overall grade average for a class
 * - getGradeLetter(float $percentage): string - Converts numerical percentage to letter grade
 * - getJustificationFileInfo(int $absenceId): ?string - Gets info about a justification file
 */

require_once __DIR__ . '/auth.php';

/**
 * Retrieves comprehensive user profile with role-specific data (teacher_id, student
 * details, parent_id with children)
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
        'title' => 'Nadzorna plošča',
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
                'title' => 'Razredi',
                'url' => '/uwuweb/admin/manage_classes.php',
                'icon' => 'school'
            ];
            $items[] = [
                'title' => 'Predmeti',
                'url' => '/uwuweb/admin/manage_subjects.php',
                'icon' => 'menu_book'
            ];
            $items[] = [
                'title' => 'Dodelitve predmetov',
                'url' => '/uwuweb/admin/manage_assignments.php',
                'icon' => 'assignment'
            ];
            $items[] = [
                'title' => 'Sistemske nastavitve',
                'url' => '/uwuweb/admin/system_settings.php',
                'icon' => 'settings'
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
            $widgets[] = [
                'title' => 'Nedavna aktivnost',
                'function' => 'renderRecentActivityWidget'
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
 * Creates a simple placeholder card for widgets without data
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

        $html = '</div><ul class="list-unstyled m-0 rounded">';

        foreach ($activities as $activity) {
            $html .= '<li class="d-flex items-start gap-md p-md' . (next($activities) ? ' border-bottom' : '') . '">';

            switch ($roleId) {
                case 1: // Admin
                    $iconClass = 'profile-admin';
                    $html .= '<div class="rounded-full p-sm ' . $iconClass . ' text-white">A</div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<span class="font-medium d-block">' . htmlspecialchars($activity['description']) . '</span>';
                    $html .= '<span class="text-sm text-secondary d-block">Uporabnik: ' . htmlspecialchars($activity['username']) .
                        ' (' . htmlspecialchars($activity['role_name']) . ')</span>';
                    $html .= '<span class="text-xs text-disabled d-block">' . date('d.m.Y H:i', strtotime($activity['activity_date'])) . '</span>';
                    $html .= '</div>';
                    break;

                case 2: // Teacher
                    $iconClass = 'profile-teacher';
                    $html .= '<div class="rounded-full p-sm ' . $iconClass . ' text-white">T</div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<span class="font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['grade_item']) . '</span>';
                    $html .= '<span class="text-sm text-secondary d-block">Dijak: ' . htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) $html .= '<span class="text-sm text-secondary d-block">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    $html .= '</div>';
                    break;

                case 3: // Student
                    $iconClass = 'profile-student';
                    $html .= '<div class="rounded-full p-sm ' . $iconClass . ' text-white">S</div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<span class="font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['subject_name']) . '</span>';
                    $html .= '<span class="text-sm text-secondary d-block">' . htmlspecialchars($activity['grade_item']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) $html .= '<span class="text-sm text-secondary d-block">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    $html .= '</div>';
                    break;

                case 4: // Parent
                    $iconClass = 'profile-parent';
                    $html .= '<div class="rounded-full p-sm ' . $iconClass . ' text-white">P</div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<span class="font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['first_name']) .
                        ' - ' . htmlspecialchars($activity['subject_name']) . '</span>';
                    $html .= '<span class="text-sm text-secondary d-block">' . htmlspecialchars($activity['grade_item']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    $html .= '</div>';
                    break;
            }

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;

    } catch (PDOException $e) {
        error_log("Database error in renderRecentActivityWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o nedavnih aktivnostih.');
    }
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
 * Calculate overall grade average for a class
 *
 * @param array $grades Array of grade records from getClassGrades()
 * @return float Overall percentage average
 */
function calculateClassAverage(array $grades): float
{
    $totalPoints = 0;
    $totalMaxPoints = 0;

    foreach ($grades as $subject) if (!empty($subject['grade_items'])) foreach ($subject['grade_items'] as $item) if (isset($item['points'])) {
        $totalPoints += ($item['points'] * $item['weight']);
        $totalMaxPoints += ($item['max_points'] * $item['weight']);
    }

    if ($totalMaxPoints > 0) return round(($totalPoints / $totalMaxPoints) * 100, 1);

    return 0.0;
}

/**
 * Converts a numerical percentage to a letter grade
 *
 * @param float $percentage Grade percentage (0-100)
 * @return string Letter grade (1-5)
 */
function getGradeLetter(float $percentage): string
{
    if ($percentage >= 90) return '5';

    if ($percentage >= 80) return '4';

    if ($percentage >= 70) return '3';

    if ($percentage >= 50) return '2';

    return '1';
}

/**
 * Get information about a saved justification file
 *
 * @param int $absenceId Attendance record ID
 * @return string|null Filename or null if no file exists
 */
function getJustificationFileInfo(int $absenceId): ?string
{
    try {
        $pdo = safeGetDBConnection('getJustificationFileInfo');

        if ($pdo === null) {
            logDBError("Failed to establish database connection in getJustificationFileInfo");
            return null;
        }

        $stmt = $pdo->prepare("
            SELECT justification_file
            FROM attendance
            WHERE att_id = ?
        ");
        $stmt->execute([$absenceId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result && isset($result['justification_file']) && $result['justification_file'] ? $result['justification_file'] : null;
    } catch (PDOException $e) {
        logDBError("Error in getJustificationFileInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Validates date format (YYYY-MM-DD)
 *
 * @param string $date Date string to validate
 * @return bool Returns true if date is valid
 */
function validateDate(string $date): bool
{
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    return $dateObj && $dateObj->format('Y-m-d') === $date;
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

/**
 * Format date for display
 *
 * @param string $date Date in YYYY-MM-DD format
 * @return string Formatted date in DD.MM.YYYY format
 */
function formatDateDisplay(string $date): string
{
    return date('d.m.Y', strtotime($date));
}

/**
 * Format datetime for display
 *
 * @param string $datetime Datetime in YYYY-MM-DD HH:MM:SS format
 * @return string Formatted datetime in DD.MM.YYYY format
 */
function formatDateTimeDisplay(string $datetime): string
{
    return date('d.m.Y', strtotime($datetime));
}
