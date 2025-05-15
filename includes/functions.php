<?php
/**
 * Core Utility Functions Library
 *
 * File path: /includes/functions.php
 *
 * This file contains the core utility functions for the uwuweb application.
 * It serves as a central repository for common functionality that is reused
 * across different parts of the application by all user roles.
 *
 * User Management Functions:
 * - getUserInfo(int $userId): ?array - Retrieves comprehensive user profile with role-specific data
 * - getStudentId(): ?int - Retrieves the student ID associated with the current user
 * - getTeacherId(?int $userId = null): ?int - Retrieves the teacher ID associated with a user
 * - getParentId(): ?int - Retrieves the parent ID associated with the current user
 * - parentHasAccessToStudent(int $studentId, ?int $parentId = null): bool - Checks if a parent has access to a student
 * - teacherHasAccessToClassSubject(int $classSubjectId, ?int $teacherId = null): bool - Checks teacher access to class-subject
 *
 * Navigation & UI Functions:
 * - getNavItemsByRole(int $role): array - Returns navigation menu items based on user role
 * - getWidgetsByRole(int $role): array - Returns dashboard widgets based on user role
 * - renderPlaceholderWidget(string $message = ''): string - Renders a placeholder widget when data is unavailable
 * - renderHeaderCard(string $title, string $description, string $role, ?string $roleText = null): void - Renders a header card
 * - renderRecentActivityWidget(): string - Renders the recent activity widget
 *
 * Class/Subject Functions:
 * - getTeacherClasses(int $teacherId): array - Retrieves all classes assigned to a teacher
 * - getClassStudents(int $classId): array - Gets all students enrolled in a specific class
 * - getClassPeriods(int $classSubjectId): array - Retrieves periods for a specific class-subject
 * - getParentStudents(?int $parentId = null): array - Retrieves students linked to a parent
 * - getStudentClasses(int $studentId): array - Gets classes and subjects for a specific student
 *
 * Attendance Functions:
 * - getAttendanceStatusLabel(string $status): string - Translates attendance status code to readable label
 * - calculateAttendanceStats(array $attendance): array - Calculates statistics from attendance records
 * - getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null, bool $checkAccess = true): array - Gets attendance for a student
 * - getStudentAttendanceByDate(int $studentId, string $date): array - Gets student attendance for a specific date
 * - getPeriodAttendance(int $periodId): array - Retrieves attendance for all students in a period
 * - addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): int|false - Creates a new period
 * - saveAttendance(int $enrollId, int $periodId, string $status): bool - Updates or creates an attendance record
 *
 * Grade Functions:
 * - getGradeItems(int $classSubjectId): array - Gets grade items for a class-subject
 * - getClassGrades(int $classSubjectId): array - Gets grades for all students and grade items in a class-subject
 * - addGradeItem(int $classSubjectId, string $name, float $maxPoints, string $date): int|false - Creates a new grade item
 *  - updateGradeItem(int $itemId, string $name, float $maxPoints, string $date): bool - Updates a grade item
 * - saveGrade(int $enrollId, int $itemId, float $points, ?string $comment = null): bool - Updates or creates a grade
 * - deleteGradeItem(int $enrollId, int $itemId): bool - Deletes a grade, or entire grade item (if $enrollId is 0)
 * - calculateAverage(array $grades): float - Calculate average for a set of grades
 * - calculateClassAverage(array $grades): float - Calculate overall grade average for a class
 * - getGradeLetter(float $percentage): string - Converts a numerical percentage to a letter grade
 *
 * Justification Functions:
 * - getJustificationFileInfo(int $absenceId): ?string - Gets information about a justification file
 * - uploadJustification(int $absenceId, string $justification): bool - Uploads a justification for an absence
 * - validateJustificationFile(array $file): bool - Validates an uploaded justification file
 * - saveJustificationFile(array $file, int $absenceId): string|false - Saves an uploaded justification file
 * - getPendingJustifications(?int $teacherId = null): array - Gets pending justifications for a teacher
 * - getJustificationById(int $absenceId): ?array - Gets detailed information about a justification
 * - approveJustification(int $absenceId): bool - Approves a justification
 * - rejectJustification(int $absenceId, string $reason): bool - Rejects a justification
 *
 * Utility Functions:
 * - validateDate(string $date): bool - Validates a date format (YYYY-MM-DD)
 * - formatDateDisplay(string $date): string - Formats date for display (YYYY-MM-DD to DD.MM.YYYY)
 * - formatDateTimeDisplay(string $datetime): string - Formats datetime for display
 * - formatFileSize(int $bytes): string - Formats file size to human-readable string
 * - sendJsonErrorResponse(string $message, int $statusCode = 400, string $context = ''): never - Sends standardized JSON error response
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/************************
 * USER MANAGEMENT FUNCTIONS
 ************************/

/**
 * Retrieves comprehensive user profile with role-specific data
 *
 * @param int $userId The user ID to retrieve
 * @return array|null User information or null if not found
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
            case ROLE_TEACHER:
                $teacherQuery = "SELECT t.teacher_id, t.first_name, t.last_name
                               FROM teachers t
                               WHERE t.user_id = :user_id";
                $teacherStmt = $db->prepare($teacherQuery);
                $teacherStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $teacherStmt->execute();
                $teacherInfo = $teacherStmt->fetch(PDO::FETCH_ASSOC);
                if ($teacherInfo) $user = array_merge($user, $teacherInfo);
                break;

            case ROLE_STUDENT:
                $studentQuery = "SELECT s.student_id, s.first_name, s.last_name, s.class_code, s.dob
                                FROM students s
                                WHERE s.user_id = :user_id";
                $studentStmt = $db->prepare($studentQuery);
                $studentStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $studentStmt->execute();
                $studentInfo = $studentStmt->fetch(PDO::FETCH_ASSOC);
                if ($studentInfo) $user = array_merge($user, $studentInfo);
                break;

            case ROLE_PARENT:
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
        logDBError("Error in getUserInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Retrieves the student ID associated with the current user
 *
 * @return int|null Student ID or null if not found
 */
function getStudentId(): ?int
{
    $userId = getUserId();
    if (!$userId) return null;

    static $studentIdCache = null;
    if ($studentIdCache !== null && isset($studentIdCache[$userId])) return $studentIdCache[$userId];

    try {
        $pdo = safeGetDBConnection('getStudentId');
        if ($pdo === null) return null;

        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $result ? (int)$result['student_id'] : null;

        if ($studentIdCache === null) $studentIdCache = [];
        $studentIdCache[$userId] = $id;

        return $id;
    } catch (PDOException $e) {
        logDBError("Error in getStudentId: " . $e->getMessage());
        return null;
    }
}

/**
 * Retrieves the teacher ID associated with the current user
 *
 * @param int|null $userId Optional user ID, uses current user if null
 * @return int|null Teacher ID or null if not found
 */
function getTeacherId(?int $userId = null): ?int
{
    if ($userId === null) $userId = getUserId();
    if (!$userId) return null;

    static $teacherIdCache = null;
    if ($teacherIdCache !== null && isset($teacherIdCache[$userId])) return $teacherIdCache[$userId];

    try {
        $pdo = safeGetDBConnection('getTeacherId');
        if ($pdo === null) return null;

        $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $result ? (int)$result['teacher_id'] : null;

        if ($teacherIdCache === null) $teacherIdCache = [];
        $teacherIdCache[$userId] = $id;

        return $id;
    } catch (PDOException $e) {
        logDBError("Error in getTeacherId: " . $e->getMessage());
        return null;
    }
}

/**
 * Retrieves the parent ID associated with the current user
 *
 * @return int|null Parent ID or null if not found
 */
function getParentId(): ?int
{
    $userId = getUserId();
    if (!$userId) return null;

    static $parentIdCache = null;
    if ($parentIdCache !== null && isset($parentIdCache[$userId])) return $parentIdCache[$userId];

    try {
        $pdo = safeGetDBConnection('getParentId');
        if ($pdo === null) return null;

        $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $result ? (int)$result['parent_id'] : null;

        if ($parentIdCache === null) $parentIdCache = [];
        $parentIdCache[$userId] = $id;

        return $id;
    } catch (PDOException $e) {
        logDBError("Error in getParentId: " . $e->getMessage());
        return null;
    }
}

/**
 * Checks if a parent has access to a specific student's data
 *
 * @param int $studentId Student ID to check
 * @param int|null $parentId Optional parent ID, uses current user if null
 * @return bool True if parent has access, false otherwise
 */
function parentHasAccessToStudent(int $studentId, ?int $parentId = null): bool
{
    if ($parentId === null) $parentId = getParentId();
    if (!$parentId) return false;

    try {
        $pdo = safeGetDBConnection('parentHasAccessToStudent');
        if ($pdo === null) return false;

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
 * Checks if a teacher has access to a specific class-subject combination
 *
 * @param int $classSubjectId Class-subject ID to check
 * @param int|null $teacherId Optional teacher ID, uses current user if null
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToClassSubject(int $classSubjectId, ?int $teacherId = null): bool
{
    if ($teacherId === null) $teacherId = getTeacherId();
    if (!$teacherId) return false;

    try {
        $pdo = safeGetDBConnection('teacherHasAccessToClassSubject');
        if ($pdo === null) return false;

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM class_subjects
            WHERE class_subject_id = ? AND teacher_id = ?
        ");
        $stmt->execute([$classSubjectId, $teacherId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['count'] > 0;
    } catch (PDOException $e) {
        logDBError("Error in teacherHasAccessToClassSubject: " . $e->getMessage());
        return false;
    }
}

/************************
 * NAVIGATION & UI FUNCTIONS
 ************************/

/**
 * Returns navigation menu items based on user role
 *
 * @param int $role User role ID
 * @return array Array of navigation items
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
        case ROLE_ADMIN:
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

        case ROLE_TEACHER:
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

        case ROLE_STUDENT:
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

        case ROLE_PARENT:
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
 * Returns dashboard widgets based on user role
 *
 * @param int $role User role ID
 * @return array Array of widget definitions
 */
function getWidgetsByRole(int $role): array
{
    $widgets = [];

    switch ($role) {
        case ROLE_ADMIN:
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

        case ROLE_TEACHER:
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

        case ROLE_STUDENT:
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

        case ROLE_PARENT:
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
 * Renders a placeholder widget when data is unavailable
 *
 * @param string $message Optional message to display
 * @return string HTML for the placeholder widget
 */
function renderPlaceholderWidget(string $message = 'Podatki trenutno niso na voljo.'): string
{
    return '<div class="card card__content p-lg text-center text-secondary">
                <span class="material-icons-outlined font-size-xxl mb-md text-disabled">info</span>
                <p class="m-0">' . htmlspecialchars($message) . '</p>
            </div>';
}

/**
 * Renders a header card with title, description and role badge
 *
 * @param string $title Card title
 * @param string $description Card description
 * @param string $role User role to display
 * @param string|null $roleText Optional custom text for role badge
 * @return void Outputs HTML directly
 */
function renderHeaderCard(string $title, string $description, string $role, ?string $roleText = null): void
{
    $roleClass = strtolower($role);
    if ($roleClass === 'administrator') $roleClass = 'admin';

    $displayRoleText = $roleText ?? ucfirst($role);

    echo <<<HTML
    <!-- Header Card -->
    <div class="card shadow mb-lg page-transition">
        <div class="card__content p-md d-flex justify-between items-center">
            <div>
                <h1 class="text-xl font-bold mt-0 mb-xs">$title</h1>
                <p class="text-secondary mt-0 mb-0">$description</p>
            </div>
            <div class="role-badge role-$roleClass">$displayRoleText</div>
        </div>
    </div>
HTML;
}

/**
 * Renders the recent activity widget
 *
 * @return string HTML for the activity widget
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
            case ROLE_ADMIN:
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

            case ROLE_TEACHER:
                $teacherId = getTeacherId();
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

            case ROLE_STUDENT:
                $studentId = getStudentId();
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

            case ROLE_PARENT:
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
                case ROLE_ADMIN:
                    $iconClass = 'profile-admin';
                    $html .= '<div class="rounded-full p-sm ' . $iconClass . ' text-white">A</div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<span class="font-medium d-block">' . htmlspecialchars($activity['description']) . '</span>';
                    $html .= '<span class="text-sm text-secondary d-block">Uporabnik: ' . htmlspecialchars($activity['username']) .
                        ' (' . htmlspecialchars($activity['role_name']) . ')</span>';
                    $html .= '<span class="text-xs text-disabled d-block">' . date('d.m.Y H:i', strtotime($activity['activity_date'])) . '</span>';
                    $html .= '</div>';
                    break;

                case ROLE_TEACHER:
                    $iconClass = 'profile-teacher';
                    $html .= '<div class="rounded-full p-sm ' . $iconClass . ' text-white">T</div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<span class="font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['grade_item']) . '</span>';
                    $html .= '<span class="text-sm text-secondary d-block">Dijak: ' . htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) $html .= '<span class="text-sm text-secondary d-block">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    $html .= '</div>';
                    break;

                case ROLE_STUDENT:
                    $iconClass = 'profile-student';
                    $html .= '<div class="rounded-full p-sm ' . $iconClass . ' text-white">S</div>';
                    $html .= '<div class="flex-grow-1">';
                    $html .= '<span class="font-medium d-block">Nova ocena: ' . htmlspecialchars($activity['subject_name']) . '</span>';
                    $html .= '<span class="text-sm text-secondary d-block">' . htmlspecialchars($activity['grade_item']) .
                        ', Točke: ' . htmlspecialchars($activity['points'] . '/' . $activity['max_points']) . '</span>';
                    if (!empty($activity['comment'])) $html .= '<span class="text-sm text-secondary d-block">"' . htmlspecialchars($activity['comment']) . '"</span>';
                    $html .= '</div>';
                    break;

                case ROLE_PARENT:
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
        logDBError("Error in renderRecentActivityWidget: " . $e->getMessage());
        return renderPlaceholderWidget('Napaka pri pridobivanju podatkov o nedavnih aktivnostih.');
    }
}

/************************
 * CLASS/SUBJECT FUNCTIONS
 ************************/

/**
 * Retrieves all classes assigned to a teacher
 *
 * @param int $teacherId Teacher ID
 * @return array Array of class records
 */
function getTeacherClasses(int $teacherId): array
{
    try {
        $pdo = safeGetDBConnection('getTeacherClasses');
        if ($pdo === null) return [];

        $query = "
            SELECT cs.class_subject_id, c.class_id, c.class_code, c.title as class_title,
                   s.subject_id, s.name as subject_name
            FROM class_subjects cs
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE cs.teacher_id = ?
            ORDER BY c.class_code, s.name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$teacherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getTeacherClasses: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets all students enrolled in a specific class
 *
 * @param int $classId Class ID
 * @return array Array of student records
 */
function getClassStudents(int $classId): array
{
    try {
        $pdo = safeGetDBConnection('getClassStudents');
        if ($pdo === null) return [];

        $query = "
            SELECT e.enroll_id, s.student_id, s.first_name, s.last_name,
                   u.username, u.user_id
            FROM enrollments e
            JOIN students s ON e.student_id = s.student_id
            JOIN users u ON s.user_id = u.user_id
            WHERE e.class_id = ?
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getClassStudents: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves periods for a specific class-subject
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of period records
 */
function getClassPeriods(int $classSubjectId): array
{
    if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($classSubjectId)) return [];

    try {
        $pdo = safeGetDBConnection('getClassPeriods');
        if ($pdo === null) return [];

        $query = "
            SELECT period_id, period_date, period_label
            FROM periods
            WHERE class_subject_id = ?
            ORDER BY period_date DESC
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classSubjectId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getClassPeriods: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves students linked to a parent
 *
 * @param int|null $parentId Parent ID (uses current user if null)
 * @return array Array of student records
 */
function getParentStudents(?int $parentId = null): array
{
    if ($parentId === null) $parentId = getParentId();
    if (!$parentId) return [];

    try {
        $pdo = safeGetDBConnection('getParentStudents');
        if ($pdo === null) return [];

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
 * Gets classes and subjects for a specific student
 *
 * @param int $studentId Student ID
 * @return array Array of class records with subjects
 */
function getStudentClasses(int $studentId): array
{
    // Verify parent has access to this student if the current user is a parent
    if (getUserRole() === ROLE_PARENT && !parentHasAccessToStudent($studentId)) return [];

    try {
        $pdo = safeGetDBConnection('getStudentClasses');
        if ($pdo === null) return [];

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

/************************
 * ATTENDANCE FUNCTIONS
 ************************/

/**
 * Translates attendance status code to readable label
 *
 * @param string $status Status code (P, A, L)
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
 * Calculates statistics from attendance records
 *
 * @param array $attendance Array of attendance records
 * @return array Statistics including counts and percentages
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
 * Gets attendance records for a student
 *
 * @param int $studentId Student ID
 * @param string|null $startDate Optional start date for filtering
 * @param string|null $endDate Optional end date for filtering
 * @param bool $checkAccess Whether to check if user has access to this data
 * @return array Array of attendance records
 */
function getStudentAttendance(int $studentId, ?string $startDate = null, ?string $endDate = null, bool $checkAccess = true): array
{
    // Check if current user has access to this student's data
    if ($checkAccess) {
        $userId = getUserId();
        $userRole = getUserRole();

        if (!$userId || !$userRole) return [];

        // Student can only access their own data
        if ($userRole === ROLE_STUDENT) {
            $currentStudentId = getStudentId();
            if ($currentStudentId !== $studentId) return [];
        }

        // Parent can only access their children's data
        if ($userRole === ROLE_PARENT && !parentHasAccessToStudent($studentId)) return [];

        // Teacher and admin can access all student data
    }

    try {
        $pdo = safeGetDBConnection('getStudentAttendance');
        if ($pdo === null) return [];

        $params = [$studentId];
        $dateCondition = '';

        if ($startDate) {
            $dateCondition .= " AND p.period_date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $dateCondition .= " AND p.period_date <= ?";
            $params[] = $endDate;
        }

        $query = "
            SELECT a.att_id, a.status, a.justification, a.approved, a.reject_reason,
                   a.justification_file,
                   p.period_id, p.period_date as date, p.period_label,
                   c.class_code, c.title as class_title, c.class_id,
                   s.name as subject_name, s.subject_id,
                   CONCAT(c.title, ' - ', s.name) as class_name
            FROM students st
            JOIN enrollments e ON st.student_id = e.student_id
            JOIN attendance a ON e.enroll_id = a.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE st.student_id = ? $dateCondition
            ORDER BY p.period_date DESC, p.period_label";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add status labels
        foreach ($attendance as &$record) $record['status_label'] = getAttendanceStatusLabel($record['status']);

        return $attendance;
    } catch (PDOException $e) {
        logDBError("Error in getStudentAttendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets student attendance for a specific date
 *
 * @param int $studentId Student ID
 * @param string $date Date in YYYY-MM-DD format
 * @return array Array of attendance records
 */
function getStudentAttendanceByDate(int $studentId, string $date): array
{
    try {
        $pdo = safeGetDBConnection('getStudentAttendanceByDate');
        if ($pdo === null) return [];

        $query = "
            SELECT a.att_id, a.status, a.justification,
                   p.period_date as date, p.period_label,
                   s.name as subject_name
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE e.student_id = ?
            AND DATE(p.period_date) = ?
            ORDER BY p.period_date, p.period_label
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$studentId, $date]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getStudentAttendanceByDate: " . $e->getMessage());
        return [];
    }
}

/**
 * Retrieves attendance for all students in a period
 *
 * @param int $periodId Period ID
 * @return array Array of attendance records with student details
 */
function getPeriodAttendance(int $periodId): array
{
    try {
        $pdo = safeGetDBConnection('getPeriodAttendance');
        if ($pdo === null) return [];

        // Get class_subject_id for this period
        $stmt = $pdo->prepare("
            SELECT class_subject_id
            FROM periods
            WHERE period_id = ?
        ");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$period || !isset($period['class_subject_id'])) return [];

        // Check if teacher has access to this class-subject
        if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($period['class_subject_id'])) return [];

        $query = "
            SELECT a.att_id, a.enroll_id, a.status, a.justification, a.approved,
                   a.reject_reason, a.justification_file,
                   s.student_id, s.first_name, s.last_name, e.class_id
            FROM periods p
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN enrollments e ON cs.class_id = e.class_id
            LEFT JOIN attendance a ON e.enroll_id = a.enroll_id AND a.period_id = p.period_id
            JOIN students s ON e.student_id = s.student_id
            WHERE p.period_id = ?
            ORDER BY s.last_name, s.first_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$periodId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getPeriodAttendance: " . $e->getMessage());
        return [];
    }
}

/**
 * Creates a new period and initializes attendance records for all students
 *
 * @param int $classSubjectId Class-Subject ID
 * @param string $periodDate Date in YYYY-MM-DD format
 * @param string $periodLabel Label for the period
 * @return int|false Period ID on success, false on failure
 */
function addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): int|false
{
    // Verify date format
    if (!validateDate($periodDate)) {
        logDBError("Invalid date format in addPeriod: $periodDate");
        return false;
    }

    // Check if current user is a teacher and has access to this class-subject
    if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($classSubjectId)) return false;

    try {
        $pdo = safeGetDBConnection('addPeriod');
        if ($pdo === null) return false;

        $pdo->beginTransaction();

        // Create the period
        $stmt = $pdo->prepare("
            INSERT INTO periods (class_subject_id, period_date, period_label)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$classSubjectId, $periodDate, $periodLabel]);

        $periodId = (int)$pdo->lastInsertId();

        // Get the class ID for this class-subject
        $stmt = $pdo->prepare("
            SELECT class_id
            FROM class_subjects
            WHERE class_subject_id = ?
        ");
        $stmt->execute([$classSubjectId]);
        $classSubject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$classSubject) {
            $pdo->rollBack();
            return false;
        }

        // Get all enrollments for this class
        $stmt = $pdo->prepare("
            SELECT enroll_id
            FROM enrollments
            WHERE class_id = ?
        ");
        $stmt->execute([$classSubject['class_id']]);
        $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Initialize attendance records for all enrolled students
        $stmt = $pdo->prepare("
            INSERT INTO attendance (enroll_id, period_id, status)
            VALUES (?, ?, 'P')
        ");

        foreach ($enrollments as $enrollment) $stmt->execute([$enrollment['enroll_id'], $periodId]);

        $pdo->commit();
        return $periodId;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        logDBError("Error in addPeriod: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates or creates an attendance record
 *
 * @param int $enrollId Enrollment ID
 * @param int $periodId Period ID
 * @param string $status Attendance status (P, A, L)
 * @return bool Success status
 */
function saveAttendance(int $enrollId, int $periodId, string $status): bool
{
    // Validate status
    if (!in_array($status, ['P', 'A', 'L'], true)) return false;

    try {
        $pdo = safeGetDBConnection('saveAttendance');
        if ($pdo === null) return false;

        // Verify teacher has access to this period
        $stmt = $pdo->prepare("
            SELECT p.class_subject_id
            FROM periods p
            WHERE p.period_id = ?
        ");
        $stmt->execute([$periodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$period) return false;

        // Check if current user is a teacher and has access to this class-subject
        if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($period['class_subject_id'])) return false;

        // Check if attendance record already exists
        $stmt = $pdo->prepare("
            SELECT att_id
            FROM attendance
            WHERE enroll_id = ? AND period_id = ?
        ");
        $stmt->execute([$enrollId, $periodId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attendance) {
            // Update existing record
            $stmt = $pdo->prepare("
                UPDATE attendance
                SET status = ?
                WHERE enroll_id = ? AND period_id = ?
            ");
            $stmt->execute([$status, $enrollId, $periodId]);
        } else {
            // Create new record
            $stmt = $pdo->prepare("
                INSERT INTO attendance (enroll_id, period_id, status)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$enrollId, $periodId, $status]);
        }

        return true;
    } catch (PDOException $e) {
        logDBError("Error in saveAttendance: " . $e->getMessage());
        return false;
    }
}

/************************
 * GRADE FUNCTIONS
 ************************/

/**
 * Gets grade items for a class-subject
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of grade item records
 */
function getGradeItems(int $classSubjectId): array
{
    // Verify teacher has access to this class-subject if user is a teacher
    if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($classSubjectId)) return [];

    try {
        $pdo = safeGetDBConnection('getGradeItems');
        if ($pdo === null) return [];

        $query = "
            SELECT item_id, name, max_points, date
            FROM grade_items
            WHERE class_subject_id = ?
            ORDER BY item_id
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classSubjectId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getGradeItems: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets grades for all students and grade items in a class-subject
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of grade records grouped by student
 */
function getClassGrades(int $classSubjectId): array
{
    // Verify teacher has access to this class-subject if user is a teacher
    if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($classSubjectId)) return [];

    try {
        $pdo = safeGetDBConnection('getClassGrades');
        if ($pdo === null) return [];

        // Get class ID for this class-subject
        $stmt = $pdo->prepare("
            SELECT class_id
            FROM class_subjects
            WHERE class_subject_id = ?
        ");
        $stmt->execute([$classSubjectId]);
        $classSubject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$classSubject || !isset($classSubject['class_id'])) return [];

        // Get all students in this class
        $students = getClassStudents($classSubject['class_id']);

        // Get all grade items for this class-subject
        $gradeItems = getGradeItems($classSubjectId);

        $result = [
            'students' => $students,
            'grade_items' => $gradeItems,
            'grades' => []
        ];

        // Get all grades for these students and grade items
        $stmt = $pdo->prepare("
            SELECT g.grade_id, g.enroll_id, g.item_id, g.points, g.comment
            FROM grades g
            JOIN enrollments e ON g.enroll_id = e.enroll_id
            WHERE e.class_id = ? AND g.item_id IN (
                SELECT item_id FROM grade_items WHERE class_subject_id = ?
            )
        ");
        $stmt->execute([$classSubject['class_id'], $classSubjectId]);
        $grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organize grades by enrollment and item
        foreach ($grades as $grade) {
            if (!isset($result['grades'][$grade['enroll_id']])) $result['grades'][$grade['enroll_id']] = [];
            $result['grades'][$grade['enroll_id']][$grade['item_id']] = [
                'points' => $grade['points'],
                'comment' => $grade['comment']
            ];
        }

        return $result;
    } catch (PDOException $e) {
        logDBError("Error in getClassGrades: " . $e->getMessage());
        return [];
    }
}

/**
 * Creates a new grade item
 *
 * @param int $classSubjectId Class-Subject ID
 * @param string $name Name of the grade item
 * @param float $maxPoints Maximum points
 * @param string $date Test date in YYYY-MM-DD format
 * @return int|false Grade item ID on success, false on failure
 */
function addGradeItem(int $classSubjectId, string $name, float $maxPoints, string $date): int|false
{
    // Check if current user is a teacher and has access to this class-subject
    if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($classSubjectId)) return false;

    // Validate date if provided
    if (!validateDate($date)) {
        logDBError("Invalid date format in addGradeItem: $date");
        return false;
    }

    try {
        $pdo = safeGetDBConnection('addGradeItem');
        if ($pdo === null) return false;

        if ($date) {
            $stmt = $pdo->prepare("
                INSERT INTO grade_items (class_subject_id, name, max_points, date)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$classSubjectId, $name, $maxPoints, $date]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO grade_items (class_subject_id, name, max_points)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$classSubjectId, $name, $maxPoints]);
        }

        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Error in addGradeItem: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a grade item or a specific grade
 *
 * @param int $enrollId The enrollment ID (0 to delete entire grade item)
 * @param int $itemId The grade item ID
 * @return bool True if deletion was successful, false otherwise
 */
function deleteGradeItem(int $enrollId, int $itemId): bool
{
    $pdo = safeGetDBConnection('deleteGradeItem');
    if (!$pdo) sendJsonErrorResponse('Database connection error', 500, 'deleteGradeItem');
    try {
        $pdo->beginTransaction();

        // Delete a specific grade
        if ($enrollId > 0) {
            $stmt = $pdo->prepare(
                "DELETE FROM grades 
                 WHERE enroll_id = :enroll_id AND item_id = :item_id"
            );
            $stmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
            $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
            $result = $stmt->execute();

            $pdo->commit();
            return $result;
        }

        // Delete an entire grade item and all associated grades
        $deleteGradesStmt = $pdo->prepare("DELETE FROM grades WHERE item_id = :item_id");
        $deleteGradesStmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $deleteGradesStmt->execute();
        $deleteItemStmt = $pdo->prepare("DELETE FROM grade_items WHERE item_id = :item_id");
        $deleteItemStmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $result = $deleteItemStmt->execute();

        $pdo->commit();
        return $result;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        logDBError('Error in deleteGradeItem: ' . $e->getMessage());
        return false;
    }
}

/**
 * Updates a grade item's details
 *
 * @param int $itemId The grade item ID to update
 * @param string $name The new name for the grade item
 * @param float $maxPoints The new maximum points for the grade item
 * @param string $date The new test date (YYYY-MM-DD format)
 * @return bool True if update was successful, false otherwise
 */
function updateGradeItem(int $itemId, string $name, float $maxPoints, string $date): bool
{
    // Validate date if provided
    if (!empty($date) && !validateDate($date)) {
        logDBError("Invalid date format in updateGradeItem: $date");
        return false;
    }

    try {
        $pdo = safeGetDBConnection('updateGradeItem');

        // First check if the grade item exists
        $checkStmt = $pdo->prepare(
            "SELECT item_id FROM grade_items 
             WHERE item_id = :item_id"
        );
        $checkStmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->rowCount() === 0) return false;

        if (empty($date)) $stmt = $pdo->prepare(
            "UPDATE grade_items 
             SET name = :name, max_points = :max_points
             WHERE item_id = :item_id"
        ); else {
            $stmt = $pdo->prepare(
                "UPDATE grade_items 
                 SET name = :name, max_points = :max_points, date = :date
                 WHERE item_id = :item_id"
            );
            $stmt->bindParam(':date', $date);
        }

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':max_points', $maxPoints);
        $stmt->bindParam(':item_id', $itemId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        logDBError('Error updating grade item: ' . $e->getMessage());
        return false;
    }
}

/**
 * Updates or creates a grade record
 *
 * @param int $enrollId Enrollment ID
 * @param int $itemId Grade Item ID
 * @param float $points Points earned
 * @param string|null $comment Optional comment
 * @return bool Success status
 */
function saveGrade(int $enrollId, int $itemId, float $points, ?string $comment = null): bool
{
    try {
        $pdo = safeGetDBConnection('saveGrade');
        if ($pdo === null) return false;

        // Verify teacher has access to this grade item
        $stmt = $pdo->prepare("
            SELECT gi.class_subject_id
            FROM grade_items gi
            WHERE gi.item_id = ?
        ");
        $stmt->execute([$itemId]);
        $gradeItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gradeItem) return false;

        // Check if current user is a teacher and has access to this class-subject
        if (getUserRole() === ROLE_TEACHER && !teacherHasAccessToClassSubject($gradeItem['class_subject_id'])) return false;

        // Check if points exceed maximum
        $stmt = $pdo->prepare("SELECT max_points FROM grade_items WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $maxPoints = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$maxPoints || $points > $maxPoints['max_points']) return false;

        // Check if grade already exists
        $stmt = $pdo->prepare("
            SELECT grade_id
            FROM grades
            WHERE enroll_id = ? AND item_id = ?
        ");
        $stmt->execute([$enrollId, $itemId]);
        $grade = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($grade) {
            // Update existing grade
            $stmt = $pdo->prepare("
                UPDATE grades
                SET points = ?, comment = ?
                WHERE enroll_id = ? AND item_id = ?
            ");
            $stmt->execute([$points, $comment, $enrollId, $itemId]);
        } else {
            // Create new grade
            $stmt = $pdo->prepare("
                INSERT INTO grades (enroll_id, item_id, points, comment)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$enrollId, $itemId, $points, $comment]);
        }

        return true;
    } catch (PDOException $e) {
        logDBError("Error in saveGrade: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate average for a set of grades
 *
 * @param array $grades Grade records
 * @return float Average percentage
 */
function calculateAverage(array $grades): float
{
    if (empty($grades)) return 0.0;

    $totalPoints = 0;
    $totalMaxPoints = 0;

    foreach ($grades as $grade) if (isset($grade['max_points'], $grade['points']) && $grade['max_points'] > 0) {
        $totalPoints += $grade['points'];
        $totalMaxPoints += $grade['max_points'];
    }

    if ($totalMaxPoints <= 0) return 0.0;

    return ($totalPoints / $totalMaxPoints) * 100;
}

/**
 * Calculate overall grade average for a class
 *
 * @param array $grades Array of grade records
 * @return float Overall percentage average
 */
function calculateClassAverage(array $grades): float
{
    $totalPoints = 0;
    $totalMaxPoints = 0;

    foreach ($grades as $subject) if (!empty($subject['grade_items'])) foreach ($subject['grade_items'] as $item) if (isset($item['points'])) {
        $totalPoints += $item['points'];
        $totalMaxPoints += $item['max_points'];
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

/************************
 * JUSTIFICATION FUNCTIONS
 ************************/

/**
 * Gets information about a justification file
 *
 * @param int $absenceId Attendance record ID
 * @return string|null Filename or null if no file found or access denied
 */
function getJustificationFileInfo(int $absenceId): ?string
{
    try {
        $pdo = safeGetDBConnection('getJustificationFileInfo');
        if ($pdo === null) return null;

        // Get the student ID for this absence to check access
        $stmt = $pdo->prepare("
            SELECT s.student_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = ?
        ");
        $stmt->execute([$absenceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) return null;

        $userRole = getUserRole();

        // Access control based on role
        if ($userRole === ROLE_STUDENT) {
            $currentStudentId = getStudentId();
            if ($currentStudentId !== $result['student_id']) return null;
        } else if ($userRole === ROLE_PARENT) if (!parentHasAccessToStudent($result['student_id'])) return null;

        // Get the file info
        $stmt = $pdo->prepare("
            SELECT justification_file
            FROM attendance
            WHERE att_id = ?
        ");
        $stmt->execute([$absenceId]);
        $fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fileInfo && !empty($fileInfo['justification_file']) ? $fileInfo['justification_file'] : null;
    } catch (PDOException $e) {
        logDBError("Error in getJustificationFileInfo: " . $e->getMessage());
        return null;
    }
}

/**
 * Uploads a justification for an absence
 *
 * @param int $absenceId Absence ID
 * @param string $justification Justification text
 * @return bool Success status
 */
function uploadJustification(int $absenceId, string $justification): bool
{
    // Check if user is logged in
    $userId = getUserId();
    if (!$userId) return false;

    try {
        $pdo = safeGetDBConnection('uploadJustification');
        if ($pdo === null) return false;

        // Verify the current user (student) owns this absence record
        $verifyQuery = "
            SELECT a.att_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = :absence_id
            AND s.user_id = :user_id";

        $verifyStmt = $pdo->prepare($verifyQuery);
        $verifyStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);
        $verifyStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $verifyStmt->execute();

        if ($verifyStmt->rowCount() === 0) {
            error_log("User $userId attempted to upload justification for unowned absence ID $absenceId.");
            return false; // User does not own this absence record
        }

        // Proceed with update
        $updateQuery = "
            UPDATE attendance
            SET justification = :justification,
                approved = NULL, -- Reset approval status when new justification is submitted
                reject_reason = NULL -- Clear previous rejection reason
            WHERE att_id = :absence_id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':justification', $justification);
        $updateStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);

        return $updateStmt->execute();
    } catch (PDOException $e) {
        logDBError("Error in uploadJustification for absence ID $absenceId: " . $e->getMessage());
        return false;
    }
}

/**
 * Validates an uploaded justification file
 *
 * @param array $file Uploaded file data from $_FILES
 * @return bool Validation result
 */
function validateJustificationFile(array $file): bool
{
    // Check for upload errors
    if (!isset($file['error']) || is_array($file['error'])) {
        error_log("Invalid parameters received for file upload validation.");
        return false;
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break; // No error, continue validation
        case UPLOAD_ERR_NO_FILE:
            error_log("No file sent during justification upload.");
            return false;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            error_log("Exceeded filesize limit during justification upload.");
            return false;
        default:
            error_log("Unknown upload error: " . $file['error']);
            return false;
    }

    // Check file size (e.g., 5MB maximum)
    $maxSize = 5 * 1024 * 1024;
    if (!isset($file['size']) || $file['size'] > $maxSize) {
        error_log("File size exceeds limit ($maxSize bytes) or size not available.");
        return false;
    }
    if ($file['size'] === 0) {
        error_log("Uploaded file is empty.");
        return false;
    }

    // Check MIME type
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
    if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        error_log("Temporary file path is missing or file does not exist.");
        return false;
    }

    try {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if ($mimeType === false) {
            error_log("Could not determine MIME type for uploaded file.");
            return false; // Could not determine type
        }

        if (!in_array($mimeType, $allowedTypes, true)) {
            error_log("Invalid file type uploaded: " . $mimeType);
            return false; // Disallowed type
        }
    } catch (Exception $e) {
        error_log("Error checking file MIME type: " . $e->getMessage());
        return false;
    }

    return true; // File is valid
}

/**
 * Saves an uploaded justification file
 *
 * @param array $file Uploaded file data from $_FILES
 * @param int $absenceId Absence ID
 * @return string|false Saved filename or false on failure
 */
function saveJustificationFile(array $file, int $absenceId): string|false
{
    // First, validate the file
    if (!validateJustificationFile($file)) {
        error_log("Justification file validation failed for absence ID $absenceId.");
        return false;
    }

    // Check if user is logged in
    $userId = getUserId();
    if (!$userId) {
        error_log("User ID not found in session during justification file save.");
        return false;
    }

    try {
        $pdo = safeGetDBConnection('saveJustificationFile');
        if ($pdo === null) return false;

        // Verify the current user (student) owns this absence record
        $verifyQuery = "
            SELECT a.att_id, a.justification_file
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            WHERE a.att_id = :absence_id
            AND s.user_id = :user_id";

        $verifyStmt = $pdo->prepare($verifyQuery);
        $verifyStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);
        $verifyStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $verifyStmt->execute();
        $attendanceRecord = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendanceRecord) {
            error_log("User $userId attempted to save justification file for unowned absence ID $absenceId.");
            return false; // User does not own this absence record
        }

        // Set up upload directory
        $projectRoot = dirname(__DIR__);
        $uploadDir = $projectRoot . '/uploads/justifications/';

        // Ensure the upload directory exists and is writable
        if (!is_dir($uploadDir)) if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            error_log(sprintf('Failed to create upload directory: "%s"', $uploadDir));
            return false; // Directory creation failed
        }

        if (!is_writable($uploadDir)) {
            error_log(sprintf('Upload directory is not writable: "%s"', $uploadDir));
            return false;
        }

        // Generate a unique filename
        $originalName = $file['name'];
        $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $safeExtension = preg_replace('/[^a-z0-9]/', '', $fileExtension);
        if (empty($safeExtension)) $safeExtension = 'bin';

        $newFilename = 'justification_' . $absenceId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safeExtension;
        $targetPath = $uploadDir . $newFilename;

        // Move the uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            error_log("Failed to move uploaded file to $targetPath.");
            return false;
        }

        // Delete old file if it exists
        $oldFilename = $attendanceRecord['justification_file'];
        if (!empty($oldFilename)) {
            $oldFilePath = $uploadDir . $oldFilename;
            if (file_exists($oldFilePath)) unlink($oldFilePath);
        }

        // Update the database record
        $updateQuery = "
            UPDATE attendance
            SET justification_file = :filename,
                approved = NULL,
                reject_reason = NULL
            WHERE att_id = :absence_id";

        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->bindParam(':filename', $newFilename);
        $updateStmt->bindParam(':absence_id', $absenceId, PDO::PARAM_INT);

        if ($updateStmt->execute()) return $newFilename; else {
            error_log("Database update failed after saving justification file $newFilename for absence ID $absenceId.");
            if (file_exists($targetPath)) unlink($targetPath);
            return false;
        }
    } catch (PDOException $e) {
        logDBError("Error in saveJustificationFile for absence ID $absenceId: " . $e->getMessage());
        if (isset($targetPath) && file_exists($targetPath)) unlink($targetPath);
        return false;
    } catch (Exception $e) {
        error_log("General Exception in saveJustificationFile for absence ID $absenceId: " . $e->getMessage());
        if (isset($targetPath) && file_exists($targetPath)) unlink($targetPath);
        return false;
    }
}

/**
 * Gets pending justifications for a teacher
 *
 * @param int|null $teacherId Teacher ID (uses current user if null)
 * @return array Array of pending justification records
 */
function getPendingJustifications(?int $teacherId = null): array
{
    if ($teacherId === null) $teacherId = getTeacherId();
    if (!$teacherId) return [];

    try {
        $pdo = safeGetDBConnection('getPendingJustifications');
        if ($pdo === null) return [];

        $query = "
            SELECT a.att_id, a.status, a.justification, a.justification_file,
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
            WHERE cs.teacher_id = ?
              AND a.status = 'A'
              AND a.justification IS NOT NULL
              AND a.approved IS NULL
            ORDER BY p.period_date DESC, c.class_code
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$teacherId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error in getPendingJustifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets detailed information about a justification
 *
 * @param int $absenceId Attendance record ID
 * @return array|null Justification details or null if not found/no access
 */
function getJustificationById(int $absenceId): ?array
{
    try {
        $pdo = safeGetDBConnection('getJustificationById');
        if ($pdo === null) return null;

        $query = "
            SELECT a.att_id, a.status, a.justification, a.justification_file,
                   p.period_id, p.period_date, p.period_label,
                   s.first_name, s.last_name, s.student_id,
                   c.class_code, c.title as class_title,
                   subj.name as subject_name,
                   cs.class_subject_id, cs.teacher_id
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            JOIN classes c ON e.class_id = c.class_id
            JOIN subjects subj ON cs.subject_id = subj.subject_id
            WHERE a.att_id = ?
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$absenceId]);

        $justification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$justification) return null;

        // Access control based on role
        $userRole = getUserRole();

        if ($userRole === ROLE_TEACHER) {
            $teacherId = getTeacherId();
            if ($teacherId === null || $justification['teacher_id'] != $teacherId) return null;
        } else if ($userRole === ROLE_STUDENT) {
            $studentId = getStudentId();
            if ($studentId !== $justification['student_id']) return null;
        } else if ($userRole === ROLE_PARENT) if (!parentHasAccessToStudent($justification['student_id'])) return null;
        // Admin can access all justifications

        return $justification;
    } catch (PDOException $e) {
        logDBError("Error in getJustificationById: " . $e->getMessage());
        return null;
    }
}

/**
 * Approves a justification
 *
 * @param int $absenceId Attendance record ID
 * @return bool Success status
 */
function approveJustification(int $absenceId): bool
{
    // Verify teacher has access to this justification
    $justification = getJustificationById($absenceId);
    if (!$justification) return false;

    try {
        $pdo = safeGetDBConnection('approveJustification');
        if ($pdo === null) return false;

        $stmt = $pdo->prepare("
            UPDATE attendance
            SET approved = 1, reject_reason = NULL
            WHERE att_id = ?
        ");
        $stmt->execute([$absenceId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error in approveJustification: " . $e->getMessage());
        return false;
    }
}

/**
 * Rejects a justification
 *
 * @param int $absenceId Attendance record ID
 * @param string $reason Reason for rejection
 * @return bool Success status
 */
function rejectJustification(int $absenceId, string $reason): bool
{
    // Verify teacher has access to this justification
    $justification = getJustificationById($absenceId);
    if (!$justification) return false;

    if (empty($reason)) return false;

    try {
        $pdo = safeGetDBConnection('rejectJustification');
        if ($pdo === null) return false;

        $stmt = $pdo->prepare("
            UPDATE attendance
            SET approved = 0, reject_reason = ?
            WHERE att_id = ?
        ");
        $stmt->execute([$reason, $absenceId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error in rejectJustification: " . $e->getMessage());
        return false;
    }
}

/************************
 * UTILITY FUNCTIONS
 ************************/

/**
 * Validates a date format (YYYY-MM-DD)
 *
 * @param string $date Date string to validate
 * @return bool True if valid date format
 */
function validateDate(string $date): bool
{
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    return $dateObj && $dateObj->format('Y-m-d') === $date;
}

/**
 * Formats date for display (YYYY-MM-DD to DD.MM.YYYY)
 *
 * @param string $date Date in YYYY-MM-DD format
 * @return string Formatted date
 */
function formatDateDisplay(string $date): string
{
    return date('d.m.Y', strtotime($date));
}

/**
 * Formats datetime for display
 *
 * @param string $datetime Datetime string
 * @return string Formatted date
 */
function formatDateTimeDisplay(string $datetime): string
{
    return date('d.m.Y', strtotime($datetime));
}

/**
 * Formats file size to human-readable string
 *
 * @param int $bytes File size in bytes
 * @return string Formatted file size with units
 */
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.2f %s", $bytes / (1024 ** $factor), $units[$factor]);
}

/**
 * Sends a standardized JSON error response
 *
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param string $context Context for error logging
 * @return never (exits script execution)
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
 * Generates HTML for an alert message
 * @param string $message The alert message
 * @param string $type Alert type: 'success', 'warning', 'error', 'info'
 * @param bool $animate Whether to add animation class
 * @param string $animation Animation class to use
 * @return string HTML for the alert
 */
function generateAlert(string $message, string $type = 'info', bool $animate = true, string $animation = 'card-entrance'): string
{
    $animClass = $animate ? ' ' . $animation : '';
    $iconMap = [
        'success' => '✓',
        'warning' => '⚠',
        'error' => '✕',
        'info' => 'ℹ',
    ];

    $icon = $iconMap[$type] ?? $iconMap['info'];

    return <<<HTML
    <div class="alert status-$type$animClass">
        <div class="alert-icon">$icon</div>
        <div class="alert-content">$message</div>
    </div>
    HTML;
}
