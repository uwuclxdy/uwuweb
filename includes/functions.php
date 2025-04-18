<?php /** @noinspection ForgottenDebugOutputInspection */
/**
 * Common Utility Functions
 *
 * Provides reusable functions used throughout the application
 *
 * Functions:
 * - getUserInfo($userId) - Retrieves user information by ID
 * - generateCSRFToken() - Creates a CSRF token for form security
 * - verifyCSRFToken($token) - Validates submitted CSRF token
 * - getNavItemsByRole($role) - Returns navigation items based on user role
 * - getWidgetsByRole($role) - Returns dashboard widgets based on user role
 * - getRoleName($roleId) - Returns the name of a role by ID
 * - renderPlaceholderWidget() - Renders a placeholder widget
 * - renderRecentActivityWidget() - Renders the recent activity widget
 * - renderAdminUserStatsWidget() - Renders admin user statistics widget
 * - renderAdminSystemStatusWidget() - Renders system status widget for admins
 * - renderAdminAttendanceWidget() - Renders school-wide attendance widget
 * - renderTeacherClassOverviewWidget() - Renders class overview for teachers
 * - renderTeacherAttendanceWidget() - Renders today's attendance widget
 * - renderTeacherPendingJustificationsWidget() - Renders pending justifications
 * - renderStudentAttendanceWidget() - Renders student attendance summary
 * - renderParentAttendanceWidget() - Renders parent view of child attendance
 * - getTeacherId() - Gets teacher ID for current user
 * - getStudentId() - Gets student ID for current user
 * - getUserId() - Gets current user ID from session
 * - getUserRole() - Gets the role ID of the current user from session
 * - getSchoolStatisticsWidget() - Renders school statistics widget
 * - getRecentActivityWidget() - Renders recent activity widget
 * - getClassAveragesWidget() - Renders class averages widget based on user role
 * - renderAdminClassAveragesWidget() - Renders school-wide class averages for admin
 * - renderTeacherClassAveragesWidget() - Renders class averages for teacher's classes
 * - renderStudentClassAveragesWidget() - Renders class averages for student's classes
 * - renderParentChildClassAveragesWidget() - Renders class averages for parent's children
 * - getTeacherClassesWidget() - Renders teacher classes widget
 * - getAttendanceSummaryWidget() - Renders attendance summary widget
 * - getPendingJustificationsWidget() - Renders pending justifications widget
 * - getStudentGradesWidget() - Renders student grades widget
 * - getStudentAttendanceWidget() - Renders student attendance widget
 * - getChildGradesWidget() - Renders parent view of child grades
 * - getChildAttendanceWidget() - Renders parent view of child attendance
 * - sanitizeString($input) - Sanitizes string input
 * - sanitizeHTML($input) - Sanitizes HTML input, allowing safe tags
 * - sanitizeInt($input) - Sanitizes integer input
 * - validateInt($input, $min, $max) - Validates integer input with optional range
 * - validateEmail($email) - Validates email address
 * - validateDate($date) - Validates date in Y-m-d format
 * - sanitizeDbIdentifier($name) - Sanitizes database table/column names
 * - sanitizeArray($inputArray, $type) - Sanitizes an array of inputs
 * - sanitizeFilename($filename) - Sanitizes uploaded filenames
 * - sanitizeRequest($request, $exceptions) - Sanitizes $_GET or $_POST data
 * - sanitizeRedirectUrl($url, $default) - Validates a URL for safe redirection
 */

// Get user information by ID
function getUserInfo($userId) {
    require_once __DIR__ . '/db.php';

    $pdo = getDBConnection();
    if (!$pdo) {
        error_log("Database connection failed in getUserInfo()");
        return null;
    }

    $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.role_id, r.name as role_name 
                          FROM users u 
                          JOIN roles r ON u.role_id = r.role_id 
                          WHERE u.user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetch();
}

/**
 * Generate a CSRF token for form security
 * @return string CSRF token
 */
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * Verify a provided CSRF token against the stored one
 * @param string $token The token to verify
 * @return bool True if token is valid
 */
if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

/**
 * Get navigation items based on the user's role
 * @param int $role The user's role ID
 * @return array Array of navigation items
 */
function getNavItemsByRole($role) {
    $navItems = [];

    // Items for all authenticated users
    $navItems[] = [
        'title' => 'Dashboard',
        'url' => 'dashboard.php',
        'icon' => 'dashboard'
    ];

    // Role-specific items
    switch ($role) {
        case ROLE_ADMIN:
            $navItems[] = [
                'title' => 'Users',
                'url' => 'admin/users.php',
                'icon' => 'users'
            ];
            $navItems[] = [
                'title' => 'Settings',
                'url' => 'admin/settings.php',
                'icon' => 'settings'
            ];
            // Admin also sees the teacher views
            $navItems[] = [
                'title' => 'Grade Book',
                'url' => 'teacher/gradebook.php',
                'icon' => 'grade'
            ];
            $navItems[] = [
                'title' => 'Attendance',
                'url' => 'teacher/attendance.php',
                'icon' => 'attendance'
            ];
            break;

        case ROLE_TEACHER:
            $navItems[] = [
                'title' => 'Grade Book',
                'url' => 'teacher/gradebook.php',
                'icon' => 'grade'
            ];
            $navItems[] = [
                'title' => 'Attendance',
                'url' => 'teacher/attendance.php',
                'icon' => 'attendance'
            ];
            break;

        case ROLE_STUDENT:
            $navItems[] = [
                'title' => 'My Grades',
                'url' => 'student/grades.php',
                'icon' => 'grade'
            ];
            $navItems[] = [
                'title' => 'My Attendance',
                'url' => 'student/attendance.php',
                'icon' => 'attendance'
            ];
            $navItems[] = [
                'title' => 'Justifications',
                'url' => 'student/justification.php',
                'icon' => 'justification'
            ];
            break;

        case ROLE_PARENT:
            $navItems[] = [
                'title' => 'Child Grades',
                'url' => 'parent/grades.php',
                'icon' => 'grade'
            ];
            $navItems[] = [
                'title' => 'Child Attendance',
                'url' => 'parent/attendance.php',
                'icon' => 'attendance'
            ];
            break;
    }

    $navItems[] = [
        'title' => 'Logout',
        'url' => 'includes/logout.php',
        'icon' => 'logout'
    ];

    return $navItems;
}

/**
 * Get widgets to display based on the user's role
 * @param int $role The user's role ID
 * @return array Array of widgets with their functions to render
 */
function getWidgetsByRole($role) {
    $widgets = [];

    // Common widgets for all roles
    $widgets['recent_activity'] = [
        'title' => 'Recent Activity',
        'function' => 'renderRecentActivityWidget'
    ];

    // Role-specific widgets
    switch ($role) {
        case ROLE_ADMIN:
            $widgets['user_stats'] = [
                'title' => 'User Statistics',
                'function' => 'renderAdminUserStatsWidget'
            ];
            $widgets['system_status'] = [
                'title' => 'System Status',
                'function' => 'renderAdminSystemStatusWidget'
            ];
            $widgets['attendance_overview'] = [
                'title' => 'School Attendance Overview',
                'function' => 'renderAdminAttendanceWidget'
            ];
            $widgets['class_averages'] = [
                'title' => 'School-wide Class Averages',
                'function' => 'renderAdminClassAveragesWidget'
            ];
            break;

        case ROLE_TEACHER:
            $widgets['class_overview'] = [
                'title' => 'Class Overview',
                'function' => 'renderTeacherClassOverviewWidget'
            ];
            $widgets['attendance_today'] = [
                'title' => 'Today\'s Attendance',
                'function' => 'renderTeacherAttendanceWidget'
            ];
            $widgets['pending_justifications'] = [
                'title' => 'Pending Justifications',
                'function' => 'renderTeacherPendingJustificationsWidget'
            ];
            $widgets['class_averages'] = [
                'title' => 'My Class Averages',
                'function' => 'renderTeacherClassAveragesWidget'
            ];
            break;

        case ROLE_STUDENT:
            $widgets['my_grades'] = [
                'title' => 'My Recent Grades',
                'function' => 'renderStudentGradesWidget'
            ];
            $widgets['my_attendance'] = [
                'title' => 'My Attendance Summary',
                'function' => 'renderStudentAttendanceWidget'
            ];
            $widgets['upcoming_classes'] = [
                'title' => 'Upcoming Classes',
                'function' => 'renderUpcomingClassesWidget'
            ];
            $widgets['class_averages'] = [
                'title' => 'My Class Averages',
                'function' => 'renderStudentClassAveragesWidget'
            ];
            break;

        case ROLE_PARENT:
            $widgets['child_grades'] = [
                'title' => 'Child\'s Recent Grades',
                'function' => 'renderStudentGradesWidget' // This will be implemented later
            ];
            $widgets['child_attendance'] = [
                'title' => 'Child\'s Attendance Summary',
                'function' => 'renderParentAttendanceWidget'
            ];
            $widgets['child_class_averages'] = [
                'title' => 'Child\'s Class Averages',
                'function' => 'renderParentChildClassAveragesWidget'
            ];
            break;
    }

    return $widgets;
}

/**
 * Get the name of a role by ID
 * @param int $roleId Role ID
 * @return string Role name
 */
if (!function_exists('getRoleName')) {
    function getRoleName($roleId) {
        $roleNames = [
            ROLE_ADMIN => 'Administrator',
            ROLE_TEACHER => 'Teacher',
            ROLE_STUDENT => 'Student',
            ROLE_PARENT => 'Parent/Guardian'
        ];

        return $roleNames[$roleId] ?? 'Unknown Role';
    }
}

/**
 * Get the teacher ID for the currently logged-in user
 * @return int|null Teacher ID or null if not found
 */
function getTeacherId() {
    $userId = getUserId();

    if (!$userId) {
        return null;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            error_log("Database connection failed in getTeacherId()");
            return null;
        }
        $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();

        return $result ? (int)$result['teacher_id'] : null;
    } catch (PDOException $e) {
        error_log("Database error in getTeacherId(): " . $e->getMessage());
        return null;
    }
}

/**
 * Get the student ID for the currently logged-in user
 * @return int|null Student ID or null if not found
 */
function getStudentId() {
    $userId = getUserId();

    if (!$userId) {
        return null;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            error_log("Database connection failed in getStudentId()");
            return null; // Return null if database connection fails
        }

        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();

        return $result ? $result['student_id'] : null;
    } catch (PDOException $e) {
        error_log("Database error in getStudentId(): " . $e->getMessage());
        return null;
    }
}

if (!function_exists('getUserRole')) {
    /**
     * Get user's role from session
     * @return int|null User's role ID or null if not found
     */
    function getUserRole() {
        return $_SESSION['role_id'] ?? null;
    }
}

// Input Sanitization Helper Functions

/**
 * Convert attendance status code to readable label
 *
 * @param string $status Attendance status code ('P', 'A', 'L')
 * @return string Readable status label
 */
function getAttendanceStatusLabel($status) {
    $labels = [
        'P' => 'Present',
        'A' => 'Absent',
        'L' => 'Late'
    ];
    return $labels[$status] ?? 'Unknown';
}

/**
 * Calculate attendance statistics for a set of attendance records
 *
 * @param array $attendance Array of attendance records
 * @return array Statistics including total, present, absent, late, justified, and percentages
 */
function calculateAttendanceStats($attendance) {
    $total = count($attendance);
    $present = 0;
    $absent = 0;
    $late = 0;
    $justified = 0;

    foreach ($attendance as $record) {
        if ($record['status'] === 'P') {
            $present++;
        } elseif ($record['status'] === 'A') {
            $absent++;
            if (!empty($record['justification']) && $record['approved'] == 1) {
                $justified++;
            }
        } elseif ($record['status'] === 'L') {
            $late++;
        }
    }

    return [
        'total' => $total,
        'present' => $present,
        'absent' => $absent,
        'late' => $late,
        'justified' => $justified,
        'present_percent' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
        'absent_percent' => $total > 0 ? round(($absent / $total) * 100, 1) : 0,
        'late_percent' => $total > 0 ? round(($late / $total) * 100, 1) : 0,
        'justified_percent' => $absent > 0 ? round(($justified / $absent) * 100, 1) : 0,
    ];
}
