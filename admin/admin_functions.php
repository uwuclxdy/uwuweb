<?php
/**
 * Admin Functions Library
 * /admin/admin_functions.php
 *
 * Provides centralized functions for administrative operations
 * including user management, system settings, and class-subject assignments.
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
 * Validation and Utility Functions:
 * - getAllStudentsBasicInfo(): array - Retrieves basic information for all students.
 * - validateUserForm(array $userData): bool|string - Validates user form data based on role. Returns true or error message.
 * - usernameExists(string $username, ?int $excludeUserId = null): bool - Checks if username already exists, optionally excluding a user.
 * - validateDate(string $date): bool - Validates date format (YYYY-MM-DD). Returns true if valid.
 * - classCodeExists(string $classCode): bool - Checks if class code exists. Returns true if found.
 * - subjectExists(int $subjectId): bool - Checks if subject exists. Returns true if found.
 * - studentExists(int $studentId): bool - Checks if student exists. Returns true if found.
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getAllUsers", 500, "admin_functions.php");
        }

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
        logDBError("Error retrieving users: " . $e->getMessage());
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

        if (!($user['role_id'] == ROLE_ADMIN && $user['user_id'] == 1) && $user['user_id'] != getUserId()) {
            echo '<a href="/uwuweb/admin/users.php?action=delete&user_id=' . $user['user_id'] . '" class="btn btn-error btn-sm"
                    onclick="return confirm(\'Ali ste prepričani, da želite izbrisati tega uporabnika?\');">Izbriši</a>';
        }

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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getUserDetails", 500, "admin_functions.php");
        }

        $query = "SELECT u.*, r.name as role_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

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
        logDBError("Error retrieving user details: " . $e->getMessage());
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
    if (empty($userData['username']) || empty($userData['password']) || empty($userData['role_id'])) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('createNewUser');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in createNewUser", 500, "admin_functions.php");
        }

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

                    foreach ($userData['student_ids'] as $studentId) {
                        $stmt->execute([$studentId, $parentId]);
                    }
                }
                break;
        }

        $pdo->commit();
        return $userId;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError("Error creating new user: " . $e->getMessage());
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
    if (empty($userId) || empty($userData)) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('updateUser');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in updateUser", 500, "admin_functions.php");
        }

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
                            foreach ($userData['student_ids'] as $studentId) {
                                $stmt->execute([$studentId, $parent['parent_id']]);
                            }
                        }
                    }
                }
                break;
        }

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError("Error updating user: " . $e->getMessage());
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
    if (empty($userId) || empty($newPassword)) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('resetUserPassword');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in resetUserPassword", 500, "admin_functions.php");
        }

        $passHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET pass_hash = ? WHERE user_id = ?");
        $stmt->execute([$passHash, $userId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error resetting user password: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in deleteUser", 500, "admin_functions.php");
        }

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
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError("Error deleting user: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getAllSubjects", 500, "admin_functions.php");
        }

        $query = "SELECT * FROM subjects ORDER BY name";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error retrieving subjects: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getSubjectDetails", 500, "admin_functions.php");
        }

        $query = "SELECT * FROM subjects WHERE subject_id = ?";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$subjectId]);

        $subject = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subject) {
            return null;
        }

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
        logDBError("Error retrieving subject details: " . $e->getMessage());
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
    if (empty($subjectData['name'])) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('createSubject');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in createSubject", 500, "admin_functions.php");
        }

        $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (?)");
        $stmt->execute([$subjectData['name']]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Error creating subject: " . $e->getMessage());
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
    if (empty($subjectId) || empty($subjectData) || empty($subjectData['name'])) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('updateSubject');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in updateSubject", 500, "admin_functions.php");
        }

        $stmt = $pdo->prepare("UPDATE subjects SET name = ? WHERE subject_id = ?");
        $stmt->execute([$subjectData['name'], $subjectId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error updating subject: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in deleteSubject", 500, "admin_functions.php");
        }

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
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError("Error deleting subject: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getAllClasses", 500, "admin_functions.php");
        }

        $query = "
            SELECT c.*, t.teacher_id, u.username as homeroom_teacher_name
            FROM classes c
            LEFT JOIN teachers t ON c.homeroom_teacher_id = t.teacher_id
            LEFT JOIN users u ON t.user_id = u.user_id
            ORDER BY c.class_code
        ";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error retrieving classes: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getClassDetails", 500, "admin_functions.php");
        }

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

        if (!$class) {
            return null;
        }

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
        logDBError("Error retrieving class details: " . $e->getMessage());
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
    if (empty($classData['class_code']) || empty($classData['title']) || empty($classData['homeroom_teacher_id'])) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('createClass');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in createClass", 500, "admin_functions.php");
        }

        $stmt = $pdo->prepare("INSERT INTO classes (class_code, title, homeroom_teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $classData['class_code'],
            $classData['title'],
            $classData['homeroom_teacher_id']
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Error creating class: " . $e->getMessage());
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
    if (empty($classId) || empty($classData)) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('updateClass');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in updateClass", 500, "admin_functions.php");
        }

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

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE classes SET " . implode(", ", $updates) . " WHERE class_id = ?";
        $params[] = $classId;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error updating class: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in deleteClass", 500, "admin_functions.php");
        }

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
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError("Error deleting class: " . $e->getMessage());
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
    if (empty($assignmentData['class_id']) || empty($assignmentData['subject_id']) || empty($assignmentData['teacher_id'])) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('assignSubjectToClass');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in assignSubjectToClass", 500, "admin_functions.php");
        }

        $stmt = $pdo->prepare("SELECT class_subject_id FROM class_subjects WHERE class_id = ? AND subject_id = ?");
        $stmt->execute([$assignmentData['class_id'], $assignmentData['subject_id']]);

        if ($stmt->fetch()) {
            return false; // Assignment already exists
        }

        $stmt = $pdo->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $assignmentData['class_id'],
            $assignmentData['subject_id'],
            $assignmentData['teacher_id']
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDBError("Error assigning subject to class: " . $e->getMessage());
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
    if (empty($assignmentId) || empty($assignmentData) || empty($assignmentData['teacher_id'])) {
        return false;
    }

    try {
        $pdo = safeGetDBConnection('updateClassSubjectAssignment');

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in updateClassSubjectAssignment", 500, "admin_functions.php");
        }

        $stmt = $pdo->prepare("UPDATE class_subjects SET teacher_id = ? WHERE class_subject_id = ?");
        $stmt->execute([$assignmentData['teacher_id'], $assignmentId]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        logDBError("Error updating class-subject assignment: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in removeSubjectFromClass", 500, "admin_functions.php");
        }

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
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError("Error removing subject from class: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getAllClassSubjectAssignments", 500, "admin_functions.php");
        }

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
        logDBError("Error getting class-subject assignments: " . $e->getMessage());
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

        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getAllTeachers", 500, "admin_functions.php");
        }

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
        logDBError("Error getting teachers: " . $e->getMessage());
        return [];
    }
}

/**
 * Gets basic information for all students
 *
 * @return array Array of students with basic information (student_id, user_id, first_name, last_name, class_code)
 */
function getAllStudentsBasicInfo(): array
{
    try {
        $pdo = safeGetDBConnection('getAllStudentsBasicInfo');
        if ($pdo === null) {
            sendJsonErrorResponse("Failed to establish database connection in getAllStudentsBasicInfo", 500, "admin_functions.php");
        }

        $query = "SELECT s.student_id, s.user_id, s.first_name, s.last_name, s.class_code, u.username
                 FROM students s
                 JOIN users u ON s.user_id = u.user_id
                 ORDER BY s.last_name, s.first_name";

        return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Error retrieving students basic info: " . $e->getMessage());
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
    if (empty($userData['username'])) {
        return 'Username is required.';
    }

    if (!preg_match('/^\w+$/', $userData['username'])) {
        return 'Username must contain only letters, numbers and underscores.';
    }

    if (strlen($userData['username']) < 3 || strlen($userData['username']) > 50) {
        return 'Username must be between 3 and 50 characters.';
    }

    if (!isset($userData['user_id']) && usernameExists($userData['username'])) {
        return 'Username is already taken.';
    }
    // Check if username exists when updating, excluding self
    if (isset($userData['user_id']) && usernameExists($userData['username'], $userData['user_id'])) {
        return 'Username is already taken.';
    }

    if (empty($userData['role_id']) || !in_array($userData['role_id'], [ROLE_ADMIN, ROLE_TEACHER, ROLE_STUDENT, ROLE_PARENT], true)) {
        return 'Invalid role selected.';
    }

    if (!isset($userData['user_id']) && empty($userData['password'])) {
        return 'Password is required for new users.';
    }

    if (!isset($userData['user_id']) && !empty($userData['password'])) {
        if (strlen($userData['password']) < 8) {
            return 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/[A-Za-z]/', $userData['password']) || !preg_match('/\d/', $userData['password'])) {
            return 'Password must contain at least one letter and one number.';
        }
    }

    switch ($userData['role_id']) {
        case ROLE_STUDENT:
            if (empty($userData['first_name'])) {
                return 'First name is required for students.';
            }

            if (empty($userData['last_name'])) {
                return 'Last name is required for students.';
            }

            if (empty($userData['class_code'])) {
                return 'Class is required for students.';
            }

            if (empty($userData['dob'])) {
                return 'Date of birth is required for students.';
            }

            if (!validateDate($userData['dob'])) {
                return 'Invalid date of birth format (YYYY-MM-DD).';
            }

            if (!classCodeExists($userData['class_code'])) {
                return 'Selected class does not exist.';
            }
            break;

        case ROLE_TEACHER:
            if (!empty($userData['teacher_subjects']) && is_array($userData['teacher_subjects'])) {
                foreach ($userData['teacher_subjects'] as $subjectId) {
                    if (!subjectExists($subjectId)) {
                        return 'One or more selected subjects do not exist.';
                    }
                }
            }
            break;

        case ROLE_PARENT:
            if (!empty($userData['student_ids']) && is_array($userData['student_ids'])) {
                foreach ($userData['student_ids'] as $studentId) {
                    if (!studentExists($studentId)) {
                        return 'One or more selected students do not exist.';
                    }
                }
            }
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
    if ($pdo === null) {
        sendJsonErrorResponse("Failed to establish database connection in usernameExists", 500, "admin_functions.php");
    }

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
 * Checks if a class code exists in the database
 *
 * @param string $classCode Class code to check
 * @return bool Returns true if class code exists
 */
function classCodeExists(string $classCode): bool
{
    $pdo = safeGetDBConnection('classCodeExists');
    if ($pdo === null) {
        sendJsonErrorResponse("Failed to establish database connection in classCodeExists", 500, "admin_functions.php");
    }

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
    if ($pdo === null) {
        sendJsonErrorResponse("Failed to establish database connection in subjectExists", 500, "admin_functions.php");
    }

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
    if ($pdo === null) {
        sendJsonErrorResponse("Failed to establish database connection in studentExists", 500, "admin_functions.php");
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_id = :student_id");
    $stmt->execute(['student_id' => $studentId]);

    return $stmt->fetchColumn() > 0;
}
