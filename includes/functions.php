<?php
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
 */

// Get user information by ID
function getUserInfo($userId) {
    require_once __DIR__ . '/db.php';
    
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.role_id, r.name as role_name 
                          FROM users u 
                          JOIN roles r ON u.role_id = r.role_id 
                          WHERE u.user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    
    return $stmt->fetch();
}

// Placeholder widget rendering functions - to be implemented as needed
function getSchoolStatisticsWidget() {
    return '<div class="widget-content">School statistics will be shown here.</div>';
}

function getRecentActivityWidget() {
    return '<div class="widget-content">Recent activity will be shown here.</div>';
}

function getClassAveragesWidget() {
    $role = getUserRole();
    
    // Call the appropriate rendering function based on user role
    switch ($role) {
        case ROLE_ADMIN:
            return renderAdminClassAveragesWidget();
        case ROLE_TEACHER:
            return renderTeacherClassAveragesWidget();
        case ROLE_STUDENT:
            return renderStudentClassAveragesWidget();
        case ROLE_PARENT:
            return renderParentChildClassAveragesWidget();
        default:
            return '<div class="widget-content">Class averages not available for your role.</div>';
    }
}

function getTeacherClassesWidget() {
    return '<div class="widget-content">Teacher classes will be shown here.</div>';
}

function getAttendanceSummaryWidget() {
    return '<div class="widget-content">Attendance summary will be shown here.</div>';
}

function getPendingJustificationsWidget() {
    return '<div class="widget-content">Pending justifications will be shown here.</div>';
}

function getStudentGradesWidget() {
    return '<div class="widget-content">Student grades will be shown here.</div>';
}

function getStudentAttendanceWidget() {
    return '<div class="widget-content">Student attendance will be shown here.</div>';
}

function getChildGradesWidget() {
    return '<div class="widget-content">Child grades will be shown here.</div>';
}

function getChildAttendanceWidget() {
    return '<div class="widget-content">Child attendance will be shown here.</div>';
}

/**
 * Generate a CSRF token for form security
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a provided CSRF token against the stored one
 * @param string $token The token to verify
 * @return bool True if token is valid
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
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
function getRoleName($roleId) {
    $roleNames = [
        ROLE_ADMIN => 'Administrator',
        ROLE_TEACHER => 'Teacher',
        ROLE_STUDENT => 'Student',
        ROLE_PARENT => 'Parent/Guardian'
    ];
    
    return $roleNames[$roleId] ?? 'Unknown Role';
}

/**
 * Render placeholder for a widget
 * @return string HTML content for widget
 */
function renderPlaceholderWidget() {
    return '<div class="widget-content placeholder">Widget content coming soon</div>';
}

/**
 * Render the recent activity widget
 * @return string HTML content for widget
 */
function renderRecentActivityWidget() {
    // In a real implementation, this would query recent activities for the user
    $html = '<div class="widget-content">';
    $html .= '<div class="activity-list">';
    $html .= '<div class="activity-item">No recent activity to display</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Render user statistics widget for admins
 * @return string HTML content for widget
 */
function renderAdminUserStatsWidget() {
    // Get user counts by role
    $pdo = getDBConnection();
    
    $counts = [
        'total' => 0,
        'teachers' => 0,
        'students' => 0,
        'parents' => 0
    ];
    
    try {
        // Get total users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $counts['total'] = $stmt->fetchColumn();
        
        // Get user counts by role
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        
        $stmt->execute([ROLE_TEACHER]);
        $counts['teachers'] = $stmt->fetchColumn();
        
        $stmt->execute([ROLE_STUDENT]);
        $counts['students'] = $stmt->fetchColumn();
        
        $stmt->execute([ROLE_PARENT]);
        $counts['parents'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Log the error
    }
    
    $html = '<div class="widget-content">';
    $html .= '<div class="stat-grid">';
    
    $html .= '<div class="stat-item">';
    $html .= '<div class="stat-value">' . $counts['total'] . '</div>';
    $html .= '<div class="stat-label">Total Users</div>';
    $html .= '</div>';
    
    $html .= '<div class="stat-item">';
    $html .= '<div class="stat-value">' . $counts['teachers'] . '</div>';
    $html .= '<div class="stat-label">Teachers</div>';
    $html .= '</div>';
    
    $html .= '<div class="stat-item">';
    $html .= '<div class="stat-value">' . $counts['students'] . '</div>';
    $html .= '<div class="stat-label">Students</div>';
    $html .= '</div>';
    
    $html .= '<div class="stat-item">';
    $html .= '<div class="stat-value">' . $counts['parents'] . '</div>';
    $html .= '<div class="stat-label">Parents</div>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '<div class="widget-footer">';
    $html .= '<a href="admin/users.php" class="widget-link">Manage Users</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Render system status widget for admins
 * @return string HTML content for widget
 */
function renderAdminSystemStatusWidget() {
    // This would check various system statuses in a real implementation
    $html = '<div class="widget-content">';
    $html .= '<div class="status-list">';
    
    $html .= '<div class="status-item">';
    $html .= '<span class="status-label">Database:</span>';
    $html .= '<span class="status-value status-ok">Connected</span>';
    $html .= '</div>';
    
    $html .= '<div class="status-item">';
    $html .= '<span class="status-label">PHP Version:</span>';
    $html .= '<span class="status-value">' . phpversion() . '</span>';
    $html .= '</div>';
    
    $html .= '<div class="status-item">';
    $html .= '<span class="status-label">Server:</span>';
    $html .= '<span class="status-value">' . $_SERVER['SERVER_SOFTWARE'] . '</span>';
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '<div class="widget-footer">';
    $html .= '<a href="admin/settings.php" class="widget-link">System Settings</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Render school-wide attendance overview widget for admins
 * @return string HTML content for widget
 */
function renderAdminAttendanceWidget() {
    // Get attendance statistics
    $pdo = getDBConnection();
    
    $stats = [
        'total' => 0,
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'attendance_rate' => 0,
        'recent_periods' => []
    ];
    
    try {
        // Get overall attendance statistics
        $stmt = $pdo->query(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'P' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'L' THEN 1 ELSE 0 END) as late
             FROM attendance"
        );
        
        $result = $stmt->fetch();
        
        if ($result) {
            $stats['total'] = (int)$result['total'];
            $stats['present'] = (int)$result['present'];
            $stats['absent'] = (int)$result['absent'];
            $stats['late'] = (int)$result['late'];
            
            if ($stats['total'] > 0) {
                $stats['attendance_rate'] = round((($stats['present'] + $stats['late']) / $stats['total']) * 100, 1);
            }
        }
        
        // Get recent periods with attendance data
        $stmt = $pdo->query(
            "SELECT 
                p.period_date, 
                p.period_label, 
                c.title as class_title,
                s.name as subject_name,
                COUNT(a.att_id) as record_count,
                SUM(CASE WHEN a.status = 'P' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN a.status = 'L' THEN 1 ELSE 0 END) as late_count
             FROM periods p
             JOIN classes c ON p.class_id = c.class_id
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN attendance a ON p.period_id = a.period_id
             GROUP BY p.period_id
             ORDER BY p.period_date DESC, p.period_label ASC
             LIMIT 5"
        );
        
        $stats['recent_periods'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Log the error
    }
    
    // Generate widget HTML
    $html = '<div class="widget-content">';
    
    // Show attendance rate chart/summary
    $html .= '<div class="attendance-summary">';
    $html .= '<div class="attendance-rate">';
    $html .= '<div class="rate-circle" data-rate="' . $stats['attendance_rate'] . '">';
    $html .= '<svg viewBox="0 0 36 36" class="circular-chart">';
    $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
    $html .= '<path class="circle" stroke-dasharray="' . $stats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
    $html .= '<text x="18" y="20.35" class="percentage">' . $stats['attendance_rate'] . '%</text>';
    $html .= '</svg>';
    $html .= '</div>';
    $html .= '<div class="rate-label">Overall Attendance Rate</div>';
    $html .= '</div>';
    
    // Show attendance breakdown
    $html .= '<div class="attendance-breakdown">';
    $html .= '<div class="breakdown-item present-color">';
    $html .= '<span class="count">' . $stats['present'] . '</span>';
    $html .= '<span class="label">Present</span>';
    $html .= '</div>';
    $html .= '<div class="breakdown-item absent-color">';
    $html .= '<span class="count">' . $stats['absent'] . '</span>';
    $html .= '<span class="label">Absent</span>';
    $html .= '</div>';
    $html .= '<div class="breakdown-item late-color">';
    $html .= '<span class="count">' . $stats['late'] . '</span>';
    $html .= '<span class="label">Late</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Show recent periods if available
    if (!empty($stats['recent_periods'])) {
        $html .= '<div class="recent-periods">';
        $html .= '<h4>Recent Class Attendance</h4>';
        $html .= '<table class="mini-table">';
        $html .= '<thead><tr><th>Date</th><th>Class</th><th>Present</th><th>Absent</th><th>Late</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($stats['recent_periods'] as $period) {
            $date = date('m/d', strtotime($period['period_date']));
            $periodLabel = $period['period_label'];
            $classTitle = $period['subject_name'] . ' - ' . $period['class_title'];
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($date) . ' ' . htmlspecialchars($periodLabel) . '</td>';
            $html .= '<td title="' . htmlspecialchars($classTitle) . '">' . htmlspecialchars(substr($classTitle, 0, 20)) . (strlen($classTitle) > 20 ? '...' : '') . '</td>';
            $html .= '<td class="present-color">' . (int)$period['present_count'] . '</td>';
            $html .= '<td class="absent-color">' . (int)$period['absent_count'] . '</td>';
            $html .= '<td class="late-color">' . (int)$period['late_count'] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    }
    
    $html .= '<div class="widget-footer">';
    $html .= '<a href="admin/reports.php?type=attendance" class="widget-link">Full Attendance Reports</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Render class overview widget for teachers
 * @return string HTML content for widget
 */
function renderTeacherClassOverviewWidget() {
    // Get teacher's classes
    $teacherId = getTeacherId();
    
    if (!$teacherId) {
        return renderPlaceholderWidget();
    }
    
    $pdo = getDBConnection();
    $classes = [];
    
    try {
        $stmt = $pdo->prepare(
            "SELECT 
                c.class_id, 
                c.title, 
                s.name AS subject_name,
                t.name AS term_name,
                (SELECT COUNT(*) FROM enrollments e WHERE e.class_id = c.class_id) AS student_count
             FROM classes c
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN terms t ON c.term_id = t.term_id
             WHERE c.teacher_id = :teacher_id
             ORDER BY t.start_date DESC, s.name ASC
             LIMIT 5"
        );
        
        $stmt->execute(['teacher_id' => $teacherId]);
        $classes = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Log the error
    }
    
    $html = '<div class="widget-content">';
    
    if (empty($classes)) {
        $html .= '<div class="empty-message">You are not assigned to any classes yet.</div>';
    } else {
        $html .= '<div class="class-list">';
        
        foreach ($classes as $class) {
            $html .= '<div class="class-item">';
            $html .= '<div class="class-title">' . htmlspecialchars($class['subject_name']) . ' - ' . htmlspecialchars($class['title']) . '</div>';
            $html .= '<div class="class-meta">' . htmlspecialchars($class['term_name']) . ' · ' . (int)$class['student_count'] . ' students</div>';
            $html .= '<div class="class-links">';
            $html .= '<a href="teacher/gradebook.php?class_id=' . (int)$class['class_id'] . '">Grades</a>';
            $html .= '<a href="teacher/attendance.php?class_id=' . (int)$class['class_id'] . '">Attendance</a>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render today's attendance widget for teachers
 * @return string HTML content for widget
 */
function renderTeacherAttendanceWidget() {
    // Get teacher's classes with today's attendance
    $teacherId = getTeacherId();
    
    if (!$teacherId) {
        return renderPlaceholderWidget();
    }
    
    $today = date('Y-m-d');
    $pdo = getDBConnection();
    $attendanceData = [];
    $periodsToday = false;
    
    try {
        $stmt = $pdo->prepare(
            "SELECT 
                p.period_id,
                p.period_label,
                c.class_id,
                c.title,
                s.name AS subject_name,
                COUNT(DISTINCT e.enroll_id) AS total_students,
                COUNT(a.att_id) AS records_count,
                SUM(CASE WHEN a.status = 'P' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) AS absent_count,
                SUM(CASE WHEN a.status = 'L' THEN 1 ELSE 0 END) AS late_count
             FROM periods p
             JOIN classes c ON p.class_id = c.class_id
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN enrollments e ON c.class_id = e.class_id
             LEFT JOIN attendance a ON p.period_id = a.period_id AND e.enroll_id = a.enroll_id
             WHERE c.teacher_id = :teacher_id AND p.period_date = :today
             GROUP BY p.period_id
             ORDER BY p.period_label ASC"
        );
        
        $stmt->execute(['teacher_id' => $teacherId, 'today' => $today]);
        $attendanceData = $stmt->fetchAll();
        $periodsToday = !empty($attendanceData);
    } catch (PDOException $e) {
        // Log the error
    }
    
    $html = '<div class="widget-content">';
    
    if (!$periodsToday) {
        $html .= '<div class="empty-message">No classes scheduled for today.</div>';
        $html .= '<div class="widget-footer">';
        $html .= '<a href="teacher/attendance.php" class="widget-link">Manage Attendance</a>';
        $html .= '</div>';
    } else {
        $html .= '<div class="today-attendance">';
        
        foreach ($attendanceData as $period) {
            $needsAttention = $period['total_students'] > $period['records_count'];
            $html .= '<div class="period-item' . ($needsAttention ? ' needs-attention' : '') . '">';
            $html .= '<div class="period-header">';
            $html .= '<h4>' . htmlspecialchars($period['period_label']) . ': ' . htmlspecialchars($period['subject_name']) . '</h4>';
            
            if ($needsAttention) {
                $html .= '<span class="attention-badge">Needs Attention</span>';
            } else {
                $html .= '<span class="complete-badge">Complete</span>';
            }
            
            $html .= '</div>';
            
            $html .= '<div class="period-stats">';
            
            // Attendance completion progress bar
            $completionRate = $period['total_students'] > 0 ? round(($period['records_count'] / $period['total_students']) * 100) : 0;
            $html .= '<div class="completion-bar-container">';
            $html .= '<div class="completion-label">Completion: ' . $completionRate . '%</div>';
            $html .= '<div class="completion-bar"><div class="completion-fill" style="width: ' . $completionRate . '%;"></div></div>';
            $html .= '</div>';
            
            // Quick attendance stats
            if ($period['records_count'] > 0) {
                $html .= '<div class="attendance-stats">';
                $html .= '<div class="stat present-color">' . $period['present_count'] . ' Present</div>';
                $html .= '<div class="stat absent-color">' . $period['absent_count'] . ' Absent</div>';
                $html .= '<div class="stat late-color">' . $period['late_count'] . ' Late</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            
            $html .= '<div class="period-actions">';
            $html .= '<a href="teacher/attendance.php?class_id=' . (int)$period['class_id'] . '&period_id=' . (int)$period['period_id'] . '" class="btn btn-small">';
            $html .= $needsAttention ? 'Take Attendance' : 'View/Edit';
            $html .= '</a>';
            $html .= '</div>';
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render pending justifications widget for teachers
 * @return string HTML content for widget
 */
function renderTeacherPendingJustificationsWidget() {
    // Get pending justifications for the teacher's classes
    $teacherId = getTeacherId();
    
    if (!$teacherId) {
        return renderPlaceholderWidget();
    }
    
    $pdo = getDBConnection();
    $pendingJustifications = [];
    
    try {
        $stmt = $pdo->prepare(
            "SELECT 
                a.att_id, 
                a.justification,
                a.status,
                s.first_name,
                s.last_name,
                p.period_date,
                p.period_label,
                c.title as class_title,
                sub.name as subject_name
             FROM attendance a
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             JOIN students s ON e.student_id = s.student_id
             JOIN periods p ON a.period_id = p.period_id
             JOIN classes c ON p.class_id = c.class_id
             JOIN subjects sub ON c.subject_id = sub.subject_id
             WHERE c.teacher_id = :teacher_id 
               AND a.status = 'A' 
               AND a.justification IS NOT NULL
               AND a.justification != ''
               AND (a.approved IS NULL OR a.approved = 0)
             ORDER BY p.period_date DESC
             LIMIT 5"
        );
        
        $stmt->execute(['teacher_id' => $teacherId]);
        $pendingJustifications = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Log the error
    }
    
    $html = '<div class="widget-content">';
    
    if (empty($pendingJustifications)) {
        $html .= '<div class="empty-message">No pending absence justifications.</div>';
    } else {
        $html .= '<div class="justification-list">';
        
        foreach ($pendingJustifications as $item) {
            $date = date('m/d', strtotime($item['period_date']));
            $html .= '<div class="justification-item">';
            $html .= '<div class="student-name">' . htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) . '</div>';
            $html .= '<div class="absence-details">' . htmlspecialchars($date . ' - ' . $item['period_label']) . '</div>';
            $html .= '<div class="justification-text">' . htmlspecialchars(substr($item['justification'], 0, 50)) . (strlen($item['justification']) > 50 ? '...' : '') . '</div>';
            $html .= '<div class="justification-actions">';
            $html .= '<a href="teacher/attendance.php?justify=' . (int)$item['att_id'] . '" class="btn btn-small">Review</a>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '<div class="widget-footer">';
    $html .= '<a href="teacher/justifications.php" class="widget-link">All Justifications</a>';
    $html .= '</div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render attendance summary widget for students
 * @return string HTML content for widget
 */
function renderStudentAttendanceWidget() {
    // Get student's attendance summary
    $studentId = getStudentId();
    
    if (!$studentId) {
        return renderPlaceholderWidget();
    }
    
    $pdo = getDBConnection();
    $stats = [
        'total' => 0,
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'justified' => 0,
        'attendance_rate' => 0,
        'recent' => []
    ];
    
    try {
        // Get overall attendance statistics for the student
        $stmt = $pdo->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN a.status = 'P' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'L' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN a.status = 'A' AND a.approved = 1 THEN 1 ELSE 0 END) as justified
             FROM attendance a
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             WHERE e.student_id = :student_id"
        );
        
        $stmt->execute(['student_id' => $studentId]);
        $result = $stmt->fetch();
        
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
        
        // Get recent attendance records
        $stmt = $pdo->prepare(
            "SELECT 
                a.status,
                a.justification,
                a.approved,
                p.period_date,
                p.period_label,
                s.name as subject_name,
                c.title as class_title
             FROM attendance a
             JOIN periods p ON a.period_id = p.period_id
             JOIN classes c ON p.class_id = c.class_id
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             WHERE e.student_id = :student_id
             ORDER BY p.period_date DESC, p.period_label ASC
             LIMIT 5"
        );
        
        $stmt->execute(['student_id' => $studentId]);
        $stats['recent'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Log the error
    }
    
    // Generate widget HTML
    $html = '<div class="widget-content">';
    
    // Show attendance rate circle
    $html .= '<div class="attendance-summary">';
    $html .= '<div class="attendance-rate">';
    $html .= '<div class="rate-circle" data-rate="' . $stats['attendance_rate'] . '">';
    $html .= '<svg viewBox="0 0 36 36" class="circular-chart">';
    $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
    $html .= '<path class="circle" stroke-dasharray="' . $stats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
    $html .= '<text x="18" y="20.35" class="percentage">' . $stats['attendance_rate'] . '%</text>';
    $html .= '</svg>';
    $html .= '</div>';
    $html .= '<div class="rate-label">Your Attendance Rate</div>';
    $html .= '</div>';
    
    // Show attendance breakdown
    $html .= '<div class="attendance-breakdown">';
    $html .= '<div class="breakdown-item present-color">';
    $html .= '<span class="count">' . $stats['present'] . '</span>';
    $html .= '<span class="label">Present</span>';
    $html .= '</div>';
    $html .= '<div class="breakdown-item absent-color">';
    $html .= '<span class="count">' . $stats['absent'] . '</span>';
    $html .= '<span class="label">Absent</span>';
    $html .= '</div>';
    $html .= '<div class="breakdown-item late-color">';
    $html .= '<span class="count">' . $stats['late'] . '</span>';
    $html .= '<span class="label">Late</span>';
    $html .= '</div>';
    $html .= '<div class="breakdown-item justified-color">';
    $html .= '<span class="count">' . $stats['justified'] . '</span>';
    $html .= '<span class="label">Justified</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Show recent attendance records
    if (!empty($stats['recent'])) {
        $html .= '<div class="recent-attendance">';
        $html .= '<h4>Recent Attendance</h4>';
        $html .= '<table class="mini-table">';
        $html .= '<thead><tr><th>Date</th><th>Class</th><th>Status</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($stats['recent'] as $record) {
            $date = date('m/d', strtotime($record['period_date']));
            $classInfo = $record['subject_name'] . ' - ' . $record['class_title'];
            
            $statusClass = '';
            $statusLabel = '';
            
            switch ($record['status']) {
                case 'P':
                    $statusClass = 'present-status';
                    $statusLabel = 'Present';
                    break;
                case 'A':
                    $statusClass = 'absent-status';
                    $statusLabel = $record['approved'] ? 'Justified' : 'Absent';
                    break;
                case 'L':
                    $statusClass = 'late-status';
                    $statusLabel = 'Late';
                    break;
            }
            
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($date) . ' ' . htmlspecialchars($record['period_label']) . '</td>';
            $html .= '<td title="' . htmlspecialchars($classInfo) . '">' . htmlspecialchars(substr($classInfo, 0, 15)) . (strlen($classInfo) > 15 ? '...' : '') . '</td>';
            $html .= '<td class="' . $statusClass . '">' . $statusLabel . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
    }
    
    $html .= '<div class="widget-footer">';
    $html .= '<a href="student/attendance.php" class="widget-link">Full Attendance Record</a>';
    
    // Add a link to submit justification if there are unjustified absences
    if ($stats['absent'] - $stats['justified'] > 0) {
        $html .= '<a href="student/justification.php" class="widget-link">Submit Justification</a>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Render attendance summary widget for parents
 * @return string HTML content for widget
 */
function renderParentAttendanceWidget() {
    // Get parent's children
    $userId = getUserId();
    $pdo = getDBConnection();
    $children = [];
    
    try {
        $stmt = $pdo->prepare(
            "SELECT s.student_id, s.first_name, s.last_name
             FROM students s
             JOIN student_parent sp ON s.student_id = sp.student_id
             JOIN parents p ON sp.parent_id = p.parent_id
             WHERE p.user_id = :user_id"
        );
        
        $stmt->execute(['user_id' => $userId]);
        $children = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Log the error
        return renderPlaceholderWidget();
    }
    
    if (empty($children)) {
        return '<div class="widget-content"><div class="empty-message">No children assigned to your account.</div></div>';
    }
    
    // Get attendance summary for each child
    $childrenAttendance = [];
    
    foreach ($children as $child) {
        $stats = [
            'student_id' => $child['student_id'],
            'name' => $child['first_name'] . ' ' . $child['last_name'],
            'total' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'justified' => 0,
            'attendance_rate' => 0,
            'recent' => []
        ];
        
        try {
            // Get overall attendance statistics for the student
            $stmt = $pdo->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN a.status = 'P' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN a.status = 'A' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN a.status = 'L' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN a.status = 'A' AND a.approved = 1 THEN 1 ELSE 0 END) as justified
                 FROM attendance a
                 JOIN enrollments e ON a.enroll_id = e.enroll_id
                 WHERE e.student_id = :student_id"
            );
            
            $stmt->execute(['student_id' => $child['student_id']]);
            $result = $stmt->fetch();
            
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
            
            // Get most recent absences
            $stmt = $pdo->prepare(
                "SELECT 
                    a.status,
                    a.justification,
                    a.approved,
                    p.period_date,
                    p.period_label,
                    s.name as subject_name
                 FROM attendance a
                 JOIN periods p ON a.period_id = p.period_id
                 JOIN classes c ON p.class_id = c.class_id
                 JOIN subjects s ON c.subject_id = s.subject_id
                 JOIN enrollments e ON a.enroll_id = e.enroll_id
                 WHERE e.student_id = :student_id AND a.status = 'A'
                 ORDER BY p.period_date DESC
                 LIMIT 3"
            );
            
            $stmt->execute(['student_id' => $child['student_id']]);
            $stats['recent'] = $stmt->fetchAll();
            
            $childrenAttendance[] = $stats;
        } catch (PDOException $e) {
            // Log the error
        }
    }
    
    // Generate widget HTML
    $html = '<div class="widget-content">';
    
    foreach ($childrenAttendance as $childStats) {
        $html .= '<div class="child-attendance">';
        $html .= '<h4>' . htmlspecialchars($childStats['name']) . '</h4>';
        
        // Show attendance summary
        $html .= '<div class="attendance-summary">';
        
        // Attendance rate
        $html .= '<div class="summary-item">';
        $html .= '<div class="rate-circle mini" data-rate="' . $childStats['attendance_rate'] . '">';
        $html .= '<svg viewBox="0 0 36 36" class="circular-chart">';
        $html .= '<path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<path class="circle" stroke-dasharray="' . $childStats['attendance_rate'] . ', 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>';
        $html .= '<text x="18" y="20.35" class="percentage">' . $childStats['attendance_rate'] . '%</text>';
        $html .= '</svg>';
        $html .= '<div class="mini-label">Attendance Rate</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Simple stats
        $html .= '<div class="mini-stats">';
        $html .= '<div class="mini-stat">';
        $html .= '<span class="mini-value present-color">' . $childStats['present'] . '</span>';
        $html .= '<span class="mini-label">Present</span>';
        $html .= '</div>';
        
        $html .= '<div class="mini-stat">';
        $html .= '<span class="mini-value absent-color">' . $childStats['absent'] . '</span>';
        $html .= '<span class="mini-label">Absent</span>';
        $html .= '</div>';
        
        $html .= '<div class="mini-stat">';
        $html .= '<span class="mini-value late-color">' . $childStats['late'] . '</span>';
        $html .= '<span class="mini-label">Late</span>';
        $html .= '</div>';
        
        $html .= '<div class="mini-stat">';
        $html .= '<span class="mini-value justified-color">' . $childStats['justified'] . '</span>';
        $html .= '<span class="mini-label">Justified</span>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Recent absences if any
        if (!empty($childStats['recent'])) {
            $html .= '<div class="recent-absences">';
            $html .= '<h5>Recent Absences</h5>';
            $html .= '<ul class="absence-list">';
            
            foreach ($childStats['recent'] as $absence) {
                $date = date('m/d', strtotime($absence['period_date']));
                $html .= '<li>';
                $html .= '<span class="absence-date">' . htmlspecialchars($date) . ' ' . htmlspecialchars($absence['period_label']) . '</span>';
                $html .= '<span class="absence-class">' . htmlspecialchars($absence['subject_name']) . '</span>';
                $html .= '<span class="absence-status">' . ($absence['approved'] ? 'Justified' : 'Unjustified') . '</span>';
                $html .= '</li>';
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '<div class="child-footer">';
        $html .= '<a href="parent/attendance.php?student_id=' . (int)$childStats['student_id'] . '" class="widget-link">Full Record</a>';
        $html .= '</div>';
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
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
        $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        
        return $result ? $result['teacher_id'] : null;
    } catch (PDOException $e) {
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
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch();
        
        return $result ? $result['student_id'] : null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get the current user's ID from session
 * @return int|null User ID or null if not logged in
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Input Sanitization Helper Functions
 */

/**
 * Sanitize string input to prevent XSS attacks
 * @param string $input The input to sanitize
 * @return string Sanitized string
 */
function sanitizeString($input) {
    // First, trim whitespace
    $input = trim($input);
    
    // Convert special characters to HTML entities
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize HTML content allowing only safe tags and attributes
 * @param string $input The HTML input to sanitize
 * @return string Sanitized HTML
 */
function sanitizeHTML($input) {
    // First, trim whitespace
    $input = trim($input);
    
    // Define allowed HTML tags and attributes
    $allowedTags = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li'
    ];
    
    // Create tag string for strip_tags
    $allowedTagsString = '<' . implode('><', $allowedTags) . '>';
    
    // Strip all but allowed tags
    $stripped = strip_tags($input, $allowedTagsString);
    
    // Return the sanitized HTML
    return $stripped;
}

/**
 * Sanitize integer input
 * @param mixed $input The input to sanitize
 * @return int Sanitized integer (0 if invalid)
 */
function sanitizeInt($input) {
    return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Validate and sanitize integer input with optional min/max bounds
 * @param mixed $input The input to validate
 * @param int|null $min Minimum allowed value (null for no minimum)
 * @param int|null $max Maximum allowed value (null for no maximum)
 * @return int|false Sanitized integer or false if invalid
 */
function validateInt($input, $min = null, $max = null) {
    $options = [];
    
    if ($min !== null) {
        $options['min_range'] = $min;
    }
    
    if ($max !== null) {
        $options['max_range'] = $max;
    }
    
    $filteredValue = filter_var($input, FILTER_VALIDATE_INT, [
        'options' => $options
    ]);
    
    return $filteredValue;
}

/**
 * Validate email address
 * @param string $email The email to validate
 * @return string|false Sanitized email or false if invalid
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Sanitize and validate date in Y-m-d format
 * @param string $date The date string to validate
 * @return string|false Validated date or false if invalid
 */
function validateDate($date) {
    // First, sanitize the string
    $date = sanitizeString($date);
    
    // Try to convert to DateTime object to validate
    $dateTime = DateTime::createFromFormat('Y-m-d', $date);
    
    // Check if it's a valid date
    if ($dateTime && $dateTime->format('Y-m-d') === $date) {
        return $date;
    }
    
    return false;
}

/**
 * Sanitize and validate database table/column name
 * Prevents SQL injection in dynamic table/column names
 * @param string $name The table or column name to sanitize
 * @return string|false Sanitized name or false if invalid
 */
function sanitizeDbIdentifier($name) {
    // Only allow alphanumeric and underscore
    if (preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        return $name;
    }
    
    return false;
}

/**
 * Sanitize an array of inputs
 * @param array $inputArray The array to sanitize
 * @param string $type The type of sanitization ('string', 'int', 'html')
 * @return array Sanitized array
 */
function sanitizeArray($inputArray, $type = 'string') {
    if (!is_array($inputArray)) {
        return [];
    }
    
    $sanitized = [];
    
    foreach ($inputArray as $key => $value) {
        // Sanitize the key (always as string)
        $sanitizedKey = sanitizeString($key);
        
        if (is_array($value)) {
            // Recursively sanitize nested arrays
            $sanitized[$sanitizedKey] = sanitizeArray($value, $type);
        } else {
            // Sanitize based on type
            switch ($type) {
                case 'int':
                    $sanitized[$sanitizedKey] = sanitizeInt($value);
                    break;
                case 'html':
                    $sanitized[$sanitizedKey] = sanitizeHTML($value);
                    break;
                case 'string':
                default:
                    $sanitized[$sanitizedKey] = sanitizeString($value);
                    break;
            }
        }
    }
    
    return $sanitized;
}

/**
 * Sanitize uploaded filename to prevent path traversal attacks
 * @param string $filename The filename to sanitize
 * @return string Sanitized filename
 */
function sanitizeFilename($filename) {
    // Remove anything that isn't alphanumeric, dot, hyphen, or underscore
    $filename = preg_replace('/[^\w\.-]+/', '', $filename);
    
    // Remove leading dots to prevent hidden file issues
    $filename = ltrim($filename, '.');
    
    return $filename;
}

/**
 * Sanitize all request inputs ($_GET, $_POST)
 * @param array $request The request array ($_GET, $_POST)
 * @param array $exceptions Keys to exclude from sanitization
 * @return array Sanitized request data
 */
function sanitizeRequest($request, $exceptions = []) {
    $sanitized = [];
    
    foreach ($request as $key => $value) {
        // Skip sanitization for excepted keys
        if (in_array($key, $exceptions)) {
            $sanitized[$key] = $value;
            continue;
        }
        
        if (is_array($value)) {
            $sanitized[$key] = sanitizeArray($value);
        } else {
            $sanitized[$key] = sanitizeString($value);
        }
    }
    
    return $sanitized;
}

/**
 * Generate a safe redirect URL (prevent open redirect vulnerabilities)
 * @param string $url The URL to validate
 * @param string $default Default URL to use if provided URL is invalid
 * @return string Safe URL to redirect to
 */
function sanitizeRedirectUrl($url, $default = 'index.php') {
    // Remove any whitespace
    $url = trim($url);
    
    // Check if URL is relative (starts with / or doesn't have protocol)
    if (empty($url) || strpos($url, '://') !== false || strpos($url, '//') === 0) {
        // URL is empty or absolute, use default
        return $default;
    }
    
    // URL is relative, it's safe to use
    return $url;
}