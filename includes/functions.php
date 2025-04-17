<?php
/**
 * uwuweb - Grade Management System
 * Common Utility Functions
 * 
 * Provides reusable functions used throughout the application
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

// Get navigation items based on user role
function getNavItemsByRole($roleId) {
    $navItems = [
        // Admin navigation
        ROLE_ADMIN => [
            ['url' => '/dashboard.php', 'title' => 'Dashboard', 'icon' => 'dashboard'],
            ['url' => '/admin/users.php', 'title' => 'User Management', 'icon' => 'users'],
            ['url' => '/admin/settings.php', 'title' => 'Settings', 'icon' => 'settings'],
            ['url' => '/teacher/gradebook.php', 'title' => 'Grade Book', 'icon' => 'gradebook'],
            ['url' => '/teacher/attendance.php', 'title' => 'Attendance', 'icon' => 'attendance']
        ],
        // Teacher navigation
        ROLE_TEACHER => [
            ['url' => '/dashboard.php', 'title' => 'Dashboard', 'icon' => 'dashboard'],
            ['url' => '/teacher/gradebook.php', 'title' => 'Grade Book', 'icon' => 'gradebook'],
            ['url' => '/teacher/attendance.php', 'title' => 'Attendance', 'icon' => 'attendance'],
            ['url' => '/teacher/justifications.php', 'title' => 'Justifications', 'icon' => 'justifications']
        ],
        // Student navigation
        ROLE_STUDENT => [
            ['url' => '/dashboard.php', 'title' => 'Dashboard', 'icon' => 'dashboard'],
            ['url' => '/student/grades.php', 'title' => 'My Grades', 'icon' => 'grades'],
            ['url' => '/student/attendance.php', 'title' => 'My Attendance', 'icon' => 'attendance'],
            ['url' => '/student/justification.php', 'title' => 'Submit Justification', 'icon' => 'justification']
        ],
        // Parent navigation
        ROLE_PARENT => [
            ['url' => '/dashboard.php', 'title' => 'Dashboard', 'icon' => 'dashboard'],
            ['url' => '/parent/grades.php', 'title' => 'Child Grades', 'icon' => 'grades'],
            ['url' => '/parent/attendance.php', 'title' => 'Child Attendance', 'icon' => 'attendance']
        ]
    ];
    
    return $navItems[$roleId] ?? [];
}

// Get dashboard widgets based on user role
function getWidgetsByRole($roleId) {
    $widgets = [
        // Admin widgets
        ROLE_ADMIN => [
            'school_stats' => [
                'title' => 'School Statistics',
                'function' => 'getSchoolStatisticsWidget'
            ],
            'recent_activity' => [
                'title' => 'Recent Activity',
                'function' => 'getRecentActivityWidget'
            ],
            'class_averages' => [
                'title' => 'Class Averages',
                'function' => 'getClassAveragesWidget'
            ]
        ],
        // Teacher widgets
        ROLE_TEACHER => [
            'my_classes' => [
                'title' => 'My Classes',
                'function' => 'getTeacherClassesWidget'
            ],
            'attendance_summary' => [
                'title' => 'Attendance Summary',
                'function' => 'getAttendanceSummaryWidget'
            ],
            'pending_justifications' => [
                'title' => 'Pending Justifications',
                'function' => 'getPendingJustificationsWidget'
            ]
        ],
        // Student widgets
        ROLE_STUDENT => [
            'my_grades' => [
                'title' => 'My Grades',
                'function' => 'getStudentGradesWidget'
            ],
            'my_attendance' => [
                'title' => 'My Attendance',
                'function' => 'getStudentAttendanceWidget'
            ]
        ],
        // Parent widgets
        ROLE_PARENT => [
            'child_grades' => [
                'title' => 'Child Grades',
                'function' => 'getChildGradesWidget'
            ],
            'child_attendance' => [
                'title' => 'Child Attendance',
                'function' => 'getChildAttendanceWidget'
            ]
        ]
    ];
    
    return $widgets[$roleId] ?? [];
}

// Placeholder widget rendering functions - to be implemented as needed
function getSchoolStatisticsWidget() {
    return '<div class="widget-content">School statistics will be shown here.</div>';
}

function getRecentActivityWidget() {
    return '<div class="widget-content">Recent activity will be shown here.</div>';
}

function getClassAveragesWidget() {
    return '<div class="widget-content">Class averages will be shown here.</div>';
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