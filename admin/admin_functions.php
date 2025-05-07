<?php
/**
 * Admin Functions Library
 * /admin/admin_functions.php
 *
 * Provides centralized functions for administrative operations
 * including user management, system settings, class-subject assignments,
 * and dashboard widgets.
 *
 * User Management Functions:
 * - getAllUsers(): array - Returns all users with role information.
 * - displayUserList(): void - Renders user management table with action buttons.
 * - getUserDetails(int $userId): ?array - Returns detailed user information with role-specific data.
 * - createNewUser(array $userData): bool|int - Creates user with role. Returns user_id or false.
 * - updateUser(int $userId, array $userData): bool - Updates user information. Returns success status.
 * - resetUserPassword(int $userId, string $newPassword): bool - Sets new user password. Returns success status.
 * - deleteUser(int $userId): bool - Removes user if no dependencies exist. Returns success status.
 *
 * Subject Management Functions:
 * - getAllSubjects(): array - Returns all subjects from the database.
 * - displaySubjectsList(): void - Renders subject management table with actions.
 * - getSubjectDetails(int $subjectId): ?array - Returns detailed subject information or null if not found.
 * - createSubject(array $subjectData): bool|int - Creates subject. Returns subject_id or false.
 * - updateSubject(int $subjectId, array $subjectData): bool - Updates subject information. Returns success status.
 * - deleteSubject(int $subjectId): bool - Removes subject if not in use. Returns success status.
 *
 * Class Management Functions:
 * - getAllClasses(): array - Returns all classes with homeroom teacher information.
 * - displayClassesList(): void - Renders class management table with actions.
 * - getClassDetails(int $classId): ?array - Returns detailed class information or null if not found.
 * - createClass(array $classData): bool|int - Creates class. Returns class_id or false.
 * - updateClass(int $classId, array $classData): bool - Updates class information. Returns success status.
 * - deleteClass(int $classId): bool - Removes class if no dependencies exist. Returns success status.
 *
 * Class-Subject Assignment Functions:
 * - assignSubjectToClass(array $assignmentData): bool|int - Assigns subject to class with teacher. Returns assignment_id or false.
 * - updateClassSubjectAssignment(int $assignmentId, array $assignmentData): bool - Updates class-subject assignment. Returns success status.
 * - removeSubjectFromClass(int $assignmentId): bool - Removes subject assignment from class. Returns success status.
 * - getAllClassSubjectAssignments(): array - Returns all class-subject assignments with related information.
 * - getAllTeachers(): array - Returns all teachers with their basic information.
 *
 * Dashboard Widget Functions:
 * - renderAdminUserStatsWidget(): string - Displays user statistics by role with counts and recent registrations.
 * - renderAdminSystemStatusWidget(): string - Shows system status including database stats, active sessions, and PHP configuration.
 * - renderAdminAttendanceWidget(): string - Visualizes school-wide attendance metrics with charts and highlights best-performing class.
 *
 * Validation and Utility Functions:
 * - getAllStudentsBasicInfo(): array - Retrieves basic information for all students.
 * - validateUserForm(array $userData): bool|string - Validates user form data based on role. Returns true or error message.
 * - usernameExists(string $username, ?int $excludeUserId = null): bool - Checks if username already exists, optionally excluding a user.
 * - subjectExists(int $subjectId): bool - Checks if subject exists. Returns true if found.
 * - studentExists(int $studentId): bool - Checks if student exists. Returns true if found.
 * - classCodeExists(string $classCode): bool - Checks if class code exists. Returns true if found.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// ===== User Management Functions =====

/**
 * Retrieves all users with their role information
 *
 * @return array Array of user records
 */
function getAllUsers(): array
{
    try {
        $pdo = safeGetDBConnection('getAllUsers');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getAllUsers", 500, "admin_functions.php");

        $query = "SELECT u.*, r.name as role_name,
                    CASE
                        WHEN u.role_id = 3 THEN s.first_name
                        END as first_name,
                    CASE
                        WHEN u.role_id = 3 THEN s.last_name
                        END as last_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                LEFT JOIN students s ON u.role_id = 3 AND u.user_id = s.user_id
                ORDER BY u.username";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju uporabnikov: " . $e->getMessage());
        return [];
    }
}

/**
 * Displays a table of all users with management actions
 *
 * @return void
 */
function displayUserList(): void
{
    $users = getAllUsers();

    echo '<div class="table-responsive mb-md">';
    echo '<table class="data-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Username</th>';
    echo '<th>Role</th>';
    echo '<th>Created</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($users as $user) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($user['user_id']) . '</td>';
        echo '<td>' . htmlspecialchars($user['username']) . '</td>';
        echo '<td>';
        echo '<span class="role-badge role-' . strtolower(htmlspecialchars($user['role_name'])) . '">' . htmlspecialchars($user['role_name']) . '</span>';
        echo '</td>';
        echo '<td>' . htmlspecialchars($user['created_at']) . '</td>';
        echo '<td class="actions d-flex flex-wrap gap-xs">';
        echo '<a href="/uwuweb/admin/users.php?action=edit&user_id=' . $user['user_id'] . '" class="btn btn-primary btn-sm">Uredi</a>';
        echo '<a href="/uwuweb/admin/users.php?action=reset&user_id=' . $user['user_id'] . '" class="btn btn-secondary btn-sm">Ponastavi geslo</a>';

        if (!($user['role_id'] == ROLE_ADMIN && $user['user_id'] == 1) && $user['user_id'] != getUserId()) echo '<a href="/uwuweb/admin/users.php?action=delete&user_id=' . $user['user_id'] . '" class="btn btn-error btn-sm"
                onclick="return confirm(\'Ali ste prepričani, da želite izbrisati tega uporabnika?\');">Izbriši</a>';

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

/**
 * Fetches detailed information about a specific user
 *
 * @param int $userId User ID to fetch details for
 * @return array|null User details or null if not found
 */
function getUserDetails(int $userId): ?array
{
    try {
        $pdo = safeGetDBConnection('getUserDetails');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getUserDetails", 500, "admin_functions.php");

        $query = "SELECT u.*, r.name as role_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return null;

        switch ($user['role_id']) {
            case ROLE_STUDENT:
                $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
                $stmt->execute([$userId]);
                $roleData = $stmt->fetch(PDO::FETCH_ASSOC);
                $user['student_data'] = $roleData;
                break;

            case ROLE_TEACHER:
                $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
                $stmt->execute([$userId]);
                $roleData = $stmt->fetch(PDO::FETCH_ASSOC);
                $user['teacher_data'] = $roleData;
                break;

            case ROLE_PARENT:
                $stmt = $pdo->prepare("SELECT * FROM parents WHERE user_id = ?");
                $stmt->execute([$userId]);
                $roleData = $stmt->fetch(PDO::FETCH_ASSOC);
                $user['parent_data'] = $roleData;

                if ($roleData) {
                    $stmt = $pdo->prepare("
                        SELECT s.*, u.username
                        FROM students s
                        JOIN users u ON s.user_id = u.user_id
                        JOIN student_parent sp ON s.student_id = sp.student_id
                        WHERE sp.parent_id = ?
                    ");
                    $stmt->execute([$roleData['parent_id']]);
                    $user['linked_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
        }

        return $user;
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju podatkov o uporabniku: " . $e->getMessage());
        return null;
    }
}

/**
 * Creates a new user with specified role
 *
 * @param array $userData User data including username, password, role, etc.
 * @return bool|int False on failure, user ID on success
 */
function createNewUser(array $userData): bool|int
{
    if (empty($userData['username']) || empty($userData['password']) || empty($userData['role_id'])) return false;

    try {
        $pdo = safeGetDBConnection('createNewUser');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija createNewUser", 500, "admin_functions.php");

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO users (username, pass_hash, role_id)
            VALUES (?, ?, ?)
        ");

        $passHash = password_hash($userData['password'], PASSWORD_DEFAULT);
        $stmt->execute([$userData['username'], $passHash, $userData['role_id']]);

        $userId = $pdo->lastInsertId();

        switch ($userData['role_id']) {
            case ROLE_STUDENT:
                if (empty($userData['first_name']) || empty($userData['last_name']) ||
                    empty($userData['dob']) || empty($userData['class_code'])) {
                    $pdo->rollBack();
                    return false;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO students (user_id, first_name, last_name, dob, class_code)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $userId,
                    $userData['first_name'],
                    $userData['last_name'],
                    $userData['dob'],
                    $userData['class_code']
                ]);
                break;

            case ROLE_TEACHER:
                $stmt = $pdo->prepare("INSERT INTO teachers (user_id) VALUES (?)");
                $stmt->execute([$userId]);
                break;

            case ROLE_PARENT:
                $stmt = $pdo->prepare("INSERT INTO parents (user_id) VALUES (?)");
                $stmt->execute([$userId]);

                if (!empty($userData['student_ids']) && is_array($userData['student_ids'])) {
                    $parentId = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("
                        INSERT INTO student_parent (student_id, parent_id)
                        VALUES (?, ?)
                    ");

                    foreach ($userData['student_ids'] as $studentId) $stmt->execute([$studentId, $parentId]);
                }
                break;
        }

        $pdo->commit();
        return $userId;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        logDBError("Napaka pri ustvarjanju novega uporabnika: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates an existing user's information
 *
 * @param int $userId User ID to update
 * @param array $userData Updated user data
 * @return bool Success or failure
 */
function updateUser(int $userId, array $userData): bool
{
    if (empty($userId) || empty($userData)) return false;

    try {
        $pdo = safeGetDBConnection('updateUser');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija updateUser", 500, "admin_functions.php");

        $pdo->beginTransaction();

        if (!empty($userData['username']) || isset($userData['role_id'])) {
            $updates = [];
            $params = [];

            if (!empty($userData['username'])) {
                $updates[] = "username = ?";
                $params[] = $userData['username'];
            }

            if (isset($userData['role_id'])) {
                $updates[] = "role_id = ?";
                $params[] = $userData['role_id'];
            }

            if (!empty($updates)) {
                $query = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?";
                $params[] = $userId;

                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
            }
        }

        $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $pdo->rollBack();
            return false;
        }

        switch ($user['role_id']) {
            case ROLE_STUDENT:
                $updates = [];
                $params = [];

                if (!empty($userData['first_name'])) {
                    $updates[] = "first_name = ?";
                    $params[] = $userData['first_name'];
                }

                if (!empty($userData['last_name'])) {
                    $updates[] = "last_name = ?";
                    $params[] = $userData['last_name'];
                }

                if (!empty($userData['dob'])) {
                    $updates[] = "dob = ?";
                    $params[] = $userData['dob'];
                }

                if (!empty($userData['class_code'])) {
                    $updates[] = "class_code = ?";
                    $params[] = $userData['class_code'];
                }

                if (!empty($updates)) {
                    $query = "UPDATE students SET " . implode(", ", $updates) . " WHERE user_id = ?";
                    $params[] = $userId;

                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                }
                break;

            case ROLE_PARENT:
                if (isset($userData['student_ids']) && is_array($userData['student_ids'])) {
                    $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $parent = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($parent) {
                        $stmt = $pdo->prepare("DELETE FROM student_parent WHERE parent_id = ?");
                        $stmt->execute([$parent['parent_id']]);

                        if (!empty($userData['student_ids'])) {
                            $stmt = $pdo->prepare("INSERT INTO student_parent (student_id, parent_id) VALUES (?, ?)");
                            foreach ($userData['student_ids'] as $studentId) $stmt->execute([$studentId, $parent['parent_id']]);
                        }
                    }
                }
                break;
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        logDBError("Napaka pri posodabljanju uporabnika: " . $e->getMessage());
        return false;
    }
}

/**
 * Resets a user's password
 *
 * @param int $userId User ID to reset password for
 * @param string $newPassword New password (will be hashed)
 * @return bool Success or failure
 */
function resetUserPassword(int $userId, string $newPassword): bool
{
    if (empty($userId) || empty($newPassword)) return false;

    try {
        $pdo = safeGetDBConnection('resetUserPassword');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija resetUserPassword", 500, "admin_functions.php");

        $passHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET pass_hash = ? WHERE user_id = ?");
        $stmt->execute([$passHash, $userId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Napaka pri ponastavitvi gesla uporabnika: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a user if they have no dependencies
 *
 * @param int $userId User ID to delete
 * @return bool Success or failure
 */
function deleteUser(int $userId): bool
{
    try {
        $pdo = safeGetDBConnection('deleteUser');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija deleteUser", 500, "admin_functions.php");

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $pdo->rollBack();
            return false;
        }

        switch ($user['role_id']) {
            case ROLE_STUDENT:
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS count FROM enrollments e
                    LEFT JOIN grades g ON e.enroll_id = g.enroll_id
                    LEFT JOIN attendance a ON e.enroll_id = a.enroll_id
                    JOIN students s ON e.student_id = s.student_id
                    WHERE s.user_id = ? AND (g.grade_id IS NOT NULL OR a.att_id IS NOT NULL)
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    $pdo->rollBack();
                    return false;
                }

                $stmt = $pdo->prepare("
                    DELETE sp FROM student_parent sp
                    JOIN students s ON sp.student_id = s.student_id
                    WHERE s.user_id = ?
                ");
                $stmt->execute([$userId]);

                $stmt = $pdo->prepare("
                    DELETE e FROM enrollments e
                    JOIN students s ON e.student_id = s.student_id
                    WHERE s.user_id = ?
                ");
                $stmt->execute([$userId]);

                $stmt = $pdo->prepare("DELETE FROM students WHERE user_id = ?");
                $stmt->execute([$userId]);
                break;

            case ROLE_TEACHER:
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS count
                    FROM class_subjects cs
                    JOIN teachers t ON cs.teacher_id = t.teacher_id
                    WHERE t.user_id = ?
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    $pdo->rollBack();
                    return false;
                }

                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS count
                    FROM classes c
                    JOIN teachers t ON c.homeroom_teacher_id = t.teacher_id
                    WHERE t.user_id = ?
                ");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result['count'] > 0) {
                    $pdo->rollBack();
                    return false;
                }

                $stmt = $pdo->prepare("DELETE FROM teachers WHERE user_id = ?");
                $stmt->execute([$userId]);
                break;

            case ROLE_PARENT:
                $stmt = $pdo->prepare("
                    DELETE sp FROM student_parent sp
                    JOIN parents p ON sp.parent_id = p.parent_id
                    WHERE p.user_id = ?
                ");
                $stmt->execute([$userId]);

                $stmt = $pdo->prepare("DELETE FROM parents WHERE user_id = ?");
                $stmt->execute([$userId]);
                break;
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        logDBError("Napaka pri brisanju uporabnika: " . $e->getMessage());
        return false;
    }
}

// ===== Subject Management Functions =====

/**
 * Retrieves all subjects
 *
 * @return array Array of subject records
 */
function getAllSubjects(): array
{
    try {
        $pdo = safeGetDBConnection('getAllSubjects');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getAllSubjects", 500, "admin_functions.php");

        $query = "SELECT * FROM subjects ORDER BY name";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju predmetov: " . $e->getMessage());
        return [];
    }
}

/**
 * Displays a table of all subjects with management actions
 *
 * @return void
 */
function displaySubjectsList(): void
{
    $subjects = getAllSubjects();

    echo '<div class="table-responsive mb-md">';
    echo '<table class="data-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Name</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($subjects as $subject) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($subject['subject_id']) . '</td>';
        echo '<td>' . htmlspecialchars($subject['name']) . '</td>';
        echo '<td class="actions d-flex gap-sm">'; // Use flex and gap for button spacing

        echo '<a href="/uwuweb/admin/settings.php?action=edit_subject&subject_id=' . $subject['subject_id'] . '" class="btn btn-primary btn-sm">Uredi</a>';

        echo '<a href="/uwuweb/admin/settings.php?action=delete_subject&subject_id=' . $subject['subject_id'] . '" class="btn btn-error btn-sm"
                onclick="return confirm(\'Ali ste prepričani, da želite izbrisati ta predmet?\');">Izbriši</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

/**
 * Fetches detailed information about a specific subject
 *
 * @param int $subjectId Subject ID to fetch details for
 * @return array|null Subject details or null if not found
 */
function getSubjectDetails(int $subjectId): ?array
{
    try {
        $pdo = safeGetDBConnection('getSubjectDetails');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getSubjectDetails", 500, "admin_functions.php");

        $query = "SELECT * FROM subjects WHERE subject_id = ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$subjectId]);

        $subject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subject) return null;

        $stmt = $pdo->prepare("
            SELECT cs.class_subject_id, c.class_code, c.title, t.teacher_id,
                   u.username as teacher_username
            FROM class_subjects cs
            JOIN classes c ON cs.class_id = c.class_id
            JOIN teachers t ON cs.teacher_id = t.teacher_id
            JOIN users u ON t.user_id = u.user_id
            WHERE cs.subject_id = ?
        ");
        $stmt->execute([$subjectId]);
        $subject['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $subject;
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju podatkov o predmetu: " . $e->getMessage());
        return null;
    }
}

/**
 * Creates a new subject
 *
 * @param array $subjectData Subject data
 * @return bool|int False on failure, subject ID on success
 */
function createSubject(array $subjectData): bool|int
{
    if (empty($subjectData['name'])) return false;

    try {
        $pdo = safeGetDBConnection('createSubject');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija createSubject", 500, "admin_functions.php");

        $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
        $stmt->execute([$subjectData['name']]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Napaka pri ustvarjanju predmeta: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates an existing subject
 *
 * @param int $subjectId Subject ID to update
 * @param array $subjectData Updated subject data
 * @return bool Success or failure
 */
function updateSubject(int $subjectId, array $subjectData): bool
{
    if (empty($subjectId) || empty($subjectData) || empty($subjectData['name'])) return false;

    try {
        $pdo = safeGetDBConnection('updateSubject');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija updateSubject", 500, "admin_functions.php");

        $stmt = $pdo->prepare("UPDATE subjects SET name = ? WHERE subject_id = ?");
        $stmt->execute([$subjectData['name'], $subjectId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Napaka pri posodabljanju predmeta: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a subject if it's not in use
 *
 * @param int $subjectId Subject ID to delete
 * @return bool Success or failure
 */
function deleteSubject(int $subjectId): bool
{
    try {
        $pdo = safeGetDBConnection('deleteSubject');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija deleteSubject", 500, "admin_functions.php");

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM class_subjects WHERE subject_id = ?");
        $stmt->execute([$subjectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM grade_items gi
            JOIN class_subjects cs ON gi.class_subject_id = cs.class_subject_id
            WHERE cs.subject_id = ?
        ");
        $stmt->execute([$subjectId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->execute([$subjectId]);

        $pdo->commit();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        logDBError("Napaka pri brisanju predmeta: " . $e->getMessage());
        return false;
    }
}

// ===== Class Management Functions =====

/**
 * Retrieves all classes
 *
 * @return array Array of class records
 */
function getAllClasses(): array
{
    try {
        $pdo = safeGetDBConnection('getAllClasses');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getAllClasses", 500, "admin_functions.php");

        $query = "
            SELECT c.*, t.teacher_id, u.username as homeroom_teacher_name
            FROM classes c
            LEFT JOIN teachers t ON c.homeroom_teacher_id = t.teacher_id
            LEFT JOIN users u ON t.user_id = u.user_id
            ORDER BY c.class_code
        ";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju razredov: " . $e->getMessage());
        return [];
    }
}

/**
 * Displays a table of all classes with management actions
 *
 * @return void
 */
function displayClassesList(): void
{
    $classes = getAllClasses();

    echo '<div class="table-responsive mb-md">';
    echo '<table class="data-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Code</th>';
    echo '<th>Title</th>';
    echo '<th>Homeroom Teacher</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($classes as $class) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($class['class_id']) . '</td>';
        echo '<td><span class="badge badge-primary">' . htmlspecialchars($class['class_code']) . '</span></td>';
        echo '<td>' . htmlspecialchars($class['title']) . '</td>';
        echo '<td>' . ($class['homeroom_teacher_name'] ? htmlspecialchars($class['homeroom_teacher_name']) : '<span class="text-disabled">Ni dodeljen</span>') . '</td>';
        echo '<td class="actions d-flex gap-sm">'; // Use flex and gap for button spacing
        echo '<a href="/uwuweb/admin/settings.php?action=edit_class&class_id=' . $class['class_id'] . '" class="btn btn-primary btn-sm">Uredi</a>';
        echo '<a href="/uwuweb/admin/settings.php?action=delete_class&class_id=' . $class['class_id'] . '" class="btn btn-error btn-sm"
                onclick="return confirm(\'Ali ste prepričani, da želite izbrisati ta razred?\');">Izbriši</a>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

/**
 * Fetches detailed information about a specific class
 *
 * @param int $classId Class ID to fetch details for
 * @return array|null Class details or null if not found
 */
function getClassDetails(int $classId): ?array
{
    try {
        $pdo = safeGetDBConnection('getClassDetails');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getClassDetails", 500, "admin_functions.php");

        $query = "
            SELECT c.*, t.teacher_id, u.username as homeroom_teacher_name
            FROM classes c
            LEFT JOIN teachers t ON c.homeroom_teacher_id = t.teacher_id
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE c.class_id = ?
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$classId]);

        $class = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$class) return null;

        $stmt = $pdo->prepare("
            SELECT cs.class_subject_id, s.subject_id, s.name as subject_name,
                   t.teacher_id, u.username as teacher_name
            FROM class_subjects cs
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN teachers t ON cs.teacher_id = t.teacher_id
            JOIN users u ON t.user_id = u.user_id
            WHERE cs.class_id = ?
        ");
        $stmt->execute([$classId]);
        $class['subjects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT s.student_id, s.first_name, s.last_name, u.username
            FROM students s
            JOIN users u ON s.user_id = u.user_id
            WHERE s.class_code = ?
        ");
        $stmt->execute([$class['class_code']]);
        $class['students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $class;
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju podatkov o razredu: " . $e->getMessage());
        return null;
    }
}

/**
 * Creates a new class
 *
 * @param array $classData Class data including class_code, title, homeroom_teacher_id
 * @return bool|int False on failure, class ID on success
 */
function createClass(array $classData): bool|int
{
    if (empty($classData['class_code']) || empty($classData['title']) || empty($classData['homeroom_teacher_id'])) return false;

    try {
        $pdo = safeGetDBConnection('createClass');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija createClass", 500, "admin_functions.php");

        $stmt = $pdo->prepare("INSERT INTO classes (class_code, title, homeroom_teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $classData['class_code'],
            $classData['title'],
            $classData['homeroom_teacher_id']
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Napaka pri ustvarjanju razreda: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates an existing class
 *
 * @param int $classId Class ID to update
 * @param array $classData Updated class data
 * @return bool Success or failure
 */
function updateClass(int $classId, array $classData): bool
{
    if (empty($classId) || empty($classData)) return false;

    try {
        $pdo = safeGetDBConnection('updateClass');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija updateClass", 500, "admin_functions.php");

        $updates = [];
        $params = [];

        if (!empty($classData['class_code'])) {
            $updates[] = "class_code = ?";
            $params[] = $classData['class_code'];
        }

        if (!empty($classData['title'])) {
            $updates[] = "title = ?";
            $params[] = $classData['title'];
        }

        if (isset($classData['homeroom_teacher_id'])) { // Allow setting to null or empty
            $updates[] = "homeroom_teacher_id = ?";
            $params[] = empty($classData['homeroom_teacher_id']) ? null : $classData['homeroom_teacher_id'];
        }

        if (empty($updates)) return false;

        $query = "UPDATE classes SET " . implode(", ", $updates) . " WHERE class_id = ?";
        $params[] = $classId;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Napaka pri posodabljanju razreda: " . $e->getMessage());
        return false;
    }
}

/**
 * Deletes a class if it has no dependencies
 *
 * @param int $classId Class ID to delete
 * @return bool Success or failure
 */
function deleteClass(int $classId): bool
{
    try {
        $pdo = safeGetDBConnection('deleteClass');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija deleteClass", 500, "admin_functions.php");

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE class_id = ?");
        $stmt->execute([$classId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM class_subjects WHERE class_id = ?");
        $stmt->execute([$classId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $pdo->rollBack();
            return false;
        }

        $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = ?");
        $stmt->execute([$classId]);

        $pdo->commit();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        logDBError("Napaka pri brisanju razreda: " . $e->getMessage());
        return false;
    }
}

// ===== Class-Subject Assignment Functions =====

/**
 * Assigns a subject to a class with a specific teacher
 *
 * @param array $assignmentData Assignment data including class_id, subject_id, teacher_id
 * @return bool|int False on failure, assignment ID on success
 */
function assignSubjectToClass(array $assignmentData): bool|int
{
    if (empty($assignmentData['class_id']) || empty($assignmentData['subject_id']) || empty($assignmentData['teacher_id'])) return false;

    try {
        $pdo = safeGetDBConnection('assignSubjectToClass');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija assignSubjectToClass", 500, "admin_functions.php");

        $stmt = $pdo->prepare("SELECT class_subject_id FROM class_subjects WHERE class_id = ? AND subject_id = ?");
        $stmt->execute([$assignmentData['class_id'], $assignmentData['subject_id']]);

        if ($stmt->fetch()) return false;

        $stmt = $pdo->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $assignmentData['class_id'],
            $assignmentData['subject_id'],
            $assignmentData['teacher_id']
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Napaka pri dodeljevanju predmeta razredu: " . $e->getMessage());
        return false;
    }
}

/**
 * Updates a class-subject assignment
 *
 * @param int $assignmentId Assignment ID to update
 * @param array $assignmentData Updated assignment data (only teacher_id can be updated)
 * @return bool Success or failure
 */
function updateClassSubjectAssignment(int $assignmentId, array $assignmentData): bool
{
    if (empty($assignmentId) || empty($assignmentData) || empty($assignmentData['teacher_id'])) return false;

    try {
        $pdo = safeGetDBConnection('updateClassSubjectAssignment');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija updateClassSubjectAssignment", 500, "admin_functions.php");

        $stmt = $pdo->prepare("UPDATE class_subjects SET teacher_id = ? WHERE class_subject_id = ?");
        $stmt->execute([$assignmentData['teacher_id'], $assignmentId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Napaka pri posodabljanju dodelitve predmeta razredu: " . $e->getMessage());
        return false;
    }
}

/**
 * Removes a subject assignment from a class
 *
 * @param int $assignmentId Assignment ID to remove
 * @return bool Success or failure
 */
function removeSubjectFromClass(int $assignmentId): bool
{
    try {
        $pdo = safeGetDBConnection('removeSubjectFromClass');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija removeSubjectFromClass", 500, "admin_functions.php");

        $pdo->beginTransaction();

        // Check for related grade items
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM grade_items WHERE class_subject_id = ?");
        $stmt->execute([$assignmentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $pdo->rollBack();
            return false; // Cannot delete if grade items exist
        }

        // Check for related periods (and implicitly attendance)
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM periods WHERE class_subject_id = ?");
        $stmt->execute([$assignmentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $pdo->rollBack();
            return false; // Cannot delete if periods exist
        }

        $stmt = $pdo->prepare("DELETE FROM class_subjects WHERE class_subject_id = ?");
        $stmt->execute([$assignmentId]);

        $pdo->commit();
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        logDBError("Napaka pri odstranjevanju predmeta iz razreda: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets all class-subject assignments
 *
 * @return array Array of class-subject assignments
 */
function getAllClassSubjectAssignments(): array
{
    try {
        $pdo = safeGetDBConnection('getAllClassSubjectAssignments');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getAllClassSubjectAssignments", 500, "admin_functions.php");

        $query = "
            SELECT cs.class_subject_id, c.class_id, c.class_code, c.title as class_title,
                   s.subject_id, s.name as subject_name,
                   t.teacher_id, u.username as teacher_name
            FROM class_subjects cs
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN teachers t ON cs.teacher_id = t.teacher_id
            JOIN users u ON t.user_id = u.user_id
            ORDER BY c.class_code, s.name
        ";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju dodelitev predmetov razredom: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets all available teachers
 *
 * @return array Array of teachers (teacher_id, user_id, username)
 */
function getAllTeachers(): array
{
    try {
        $pdo = safeGetDBConnection('getAllTeachers');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getAllTeachers", 500, "admin_functions.php");

        $query = "
            SELECT t.teacher_id, u.user_id, u.username
            FROM teachers t
            JOIN users u ON t.user_id = u.user_id
            WHERE u.role_id = ?
            ORDER BY u.username
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([ROLE_TEACHER]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju učiteljev: " . $e->getMessage());
        return [];
    }
}

// ===== Dashboard Widget Functions =====

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

// ===== Validation and Utility Functions =====

/**
 * Gets basic information for all students
 *
 * @return array Array of students with basic information (student_id, user_id, first_name, last_name, class_code)
 */
function getAllStudentsBasicInfo(): array
{
    try {
        $pdo = safeGetDBConnection('getAllStudentsBasicInfo');
        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getAllStudentsBasicInfo", 500, "admin_functions.php");

        $query = "SELECT s.student_id, s.user_id, s.first_name, s.last_name, s.class_code, u.username
                 FROM students s
                 JOIN users u ON s.user_id = u.user_id
                 ORDER BY s.last_name, s.first_name";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju osnovnih podatkov o dijakih: " . $e->getMessage());
        return [];
    }
}

/**
 * Validates user form data based on role
 *
 * @param array $userData Array containing user form data
 * @return bool|string Returns true if valid, error message string if invalid
 */
function validateUserForm(array $userData): bool|string
{
    if (empty($userData['username'])) return 'Uporabniško ime je obvezno.';

    if (!preg_match('/^\w+$/', $userData['username'])) return 'Uporabniško ime lahko vsebuje samo črke, številke in podčrtaje.';

    if (strlen($userData['username']) < 3 || strlen($userData['username']) > 50) return 'Uporabniško ime mora biti dolgo med 3 in 50 znakov.';

    if (!isset($userData['user_id']) && usernameExists($userData['username'])) return 'Uporabniško ime je že zasedeno.';
    // Check if username exists when updating, excluding self
    if (isset($userData['user_id']) && usernameExists($userData['username'], $userData['user_id'])) return 'Uporabniško ime je že zasedeno.';

    if (empty($userData['role_id']) || !in_array($userData['role_id'], [ROLE_ADMIN, ROLE_TEACHER, ROLE_STUDENT, ROLE_PARENT], true)) return 'Izbrana je neveljavna vloga.';

    if (!isset($userData['user_id']) && empty($userData['password'])) return 'Geslo je obvezno za nove uporabnike.';

    if (!isset($userData['user_id']) && !empty($userData['password'])) {
        if (strlen($userData['password']) < 6) return 'Geslo mora biti dolgo vsaj 6 znakov.';
    }

    switch ($userData['role_id']) {
        case ROLE_STUDENT:
            if (empty($userData['first_name'])) return 'Ime je obvezno za dijake.';

            if (empty($userData['last_name'])) return 'Priimek je obvezen za dijake.';

            if (empty($userData['class_code'])) return 'Razred je obvezen za dijake.';

            if (empty($userData['dob'])) return 'Datum rojstva je obvezen za dijake.';

            if (!validateDate($userData['dob'])) return 'Neveljaven format datuma rojstva (LLLL-MM-DD).';

            if (!classCodeExists($userData['class_code'])) return 'Izbrani razred ne obstaja.';
            break;

        case ROLE_TEACHER:
            if (!empty($userData['teacher_subjects']) && is_array($userData['teacher_subjects'])) foreach ($userData['teacher_subjects'] as $subjectId) if (!subjectExists($subjectId)) return 'Eden ali več izbranih predmetov ne obstaja.';
            break;

        case ROLE_PARENT:
            if (!empty($userData['student_ids']) && is_array($userData['student_ids'])) foreach ($userData['student_ids'] as $studentId) if (!studentExists($studentId)) return 'Eden ali več izbranih dijakov ne obstaja.';
            break;
    }

    return true;
}

/**
 * Checks if a username already exists in the database
 *
 * @param string $username Username to check
 * @param int|null $excludeUserId User ID to exclude from check (for updates)
 * @return bool Returns true if username exists
 */
function usernameExists(string $username, ?int $excludeUserId = null): bool
{
    $pdo = safeGetDBConnection('usernameExists');
    if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija usernameExists", 500, "admin_functions.php");

    $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
    $params = ['username' => $username];

    if ($excludeUserId !== null) {
        $sql .= " AND user_id != :user_id";
        $params['user_id'] = $excludeUserId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn() > 0;
}

/**
 * Checks if a class code exists in the database
 *
 * @param string $classCode Class code to check
 * @return bool Returns true if class code exists
 */
function classCodeExists(string $classCode): bool
{
    $pdo = safeGetDBConnection('classCodeExists');
    if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija classCodeExists", 500, "admin_functions.php");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_code = :class_code");
    $stmt->execute(['class_code' => $classCode]);

    return $stmt->fetchColumn() > 0;
}

/**
 * Checks if a subject exists in the database
 *
 * @param int $subjectId Subject ID to check
 * @return bool Returns true if subject exists
 */
function subjectExists(int $subjectId): bool
{
    $pdo = safeGetDBConnection('subjectExists');
    if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija subjectExists", 500, "admin_functions.php");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE subject_id = :subject_id");
    $stmt->execute(['subject_id' => $subjectId]);

    return $stmt->fetchColumn() > 0;
}

/**
 * Checks if a student exists in the database
 *
 * @param int $studentId Student ID to check
 * @return bool Returns true if student exists
 */
function studentExists(int $studentId): bool
{
    $pdo = safeGetDBConnection('studentExists');
    if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija studentExists", 500, "admin_functions.php");

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = :student_id");
    $stmt->execute(['student_id' => $studentId]);

    return $stmt->fetchColumn() > 0;
}
