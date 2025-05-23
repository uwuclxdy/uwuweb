<?php
/**
 * Admin Functions Library
 *
 * File path: /admin/admin_functions.php
 *
 * Provides centralized functions for administrative operations
 * including user management, system settings, class-subject assignments,
 * and dashboard widgets.
 *
 * User Management Functions:
 * - getAllUsers(): array - Returns all users with role information
 * - displayUserList(): void - Renders user management table with action buttons
 * - getUserDetails(int $userId): ?array - Returns detailed user information with role-specific data
 * - createNewUser(array $userData): bool|int - Creates user with role. Returns user_id or false
 * - updateUser(int $userId, array $userData): bool - Updates user information. Returns success status
 * - resetUserPassword(int $userId, string $newPassword): bool - Sets new user password. Returns success status
 * - deleteUser(int $userId): bool - Removes user if no dependencies exist. Returns success status
 * - handleCreateUser(): void - Processes user creation form submission
 * - handleUpdateUser(): void - Processes user update form submission
 * - handleResetPassword(): void - Processes password reset form submission
 * - handleDeleteUser(): void - Processes user deletion confirmation
 *
 * Subject Management Functions:
 * - getAllSubjects(): array - Returns all subjects from the database
 * - displaySubjectsList(): void - Renders subject management table with actions
 * - getSubjectDetails(int $subjectId): ?array - Returns detailed subject information or null if not found
 * - createSubject(array $subjectData): bool|int - Creates subject. Returns subject_id or false
 * - updateSubject(int $subjectId, array $subjectData): bool - Updates subject information. Returns success status
 * - deleteSubject(int $subjectId): bool - Removes subject if not in use. Returns success status
 *
 * Class Management Functions:
 * - getAllClasses(): array - Returns all classes with homeroom teacher information
 * - displayClassesList(): void - Renders class management table with actions
 * - getClassDetails(int $classId): ?array - Returns detailed class information or null if not found
 * - createClass(array $classData): bool|int - Creates class. Returns class_id or false
 * - updateClass(int $classId, array $classData): bool - Updates class information. Returns success status
 * - deleteClass(int $classId): bool - Removes class if no dependencies exist. Returns success status
 *
 * Class-Subject Assignment Functions:
 * - assignSubjectToClass(array $assignmentData): bool|int - Assigns subject to class with teacher. Returns assignment_id or false
 * - updateClassSubjectAssignment(int $assignmentId, array $assignmentData): bool - Updates class-subject assignment. Returns success status
 * - removeSubjectFromClass(int $assignmentId): bool - Removes subject assignment from class. Returns success status
 * - getAllClassSubjectAssignments(): array - Returns all class-subject assignments with related information
 * - getAllTeachers(): array - Returns all teachers with their basic information
 *
 * System Settings Functions:
 * - getSystemSettings(): array - Retrieves system-wide settings
 * - updateSystemSettings(array $settings): bool - Updates system-wide settings. Returns success status
 *
 * Dashboard Widget Functions:
 * - renderAdminUserStatsWidget(): string - Displays user statistics by role with counts and recent registrations
 * - renderAdminSystemStatusWidget(): string - Shows system status including database stats, active sessions, and PHP configuration
 * - renderAdminAttendanceWidget(): string - Visualizes school-wide attendance metrics with charts and highlights best-performing class
 *
 * Validation and Utility Functions:
 * - getAllStudentsBasicInfo(): array - Retrieves basic information for all students
 * - validateUserForm(array $userData): bool|string - Validates user form data based on role. Returns true or error message
 * - usernameExists(string $username, ?int $excludeUserId = null): bool - Checks if username already exists, optionally excluding a user
 * - subjectExists(int $subjectId): bool - Checks if subject exists. Returns true if found
 * - studentExists(int $studentId): bool - Checks if student exists. Returns true if found
 * - classCodeExists(string $classCode): bool - Checks if class code exists. Returns true if found
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

                if ($roleData) {
                    // Add the student data directly to the user array
                    $user['first_name'] = $roleData['first_name'];
                    $user['last_name'] = $roleData['last_name'];
                    $user['dob'] = $roleData['dob'];
                    $user['class_code'] = $roleData['class_code'];
                }
                break;

            case ROLE_TEACHER:
                $stmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = ?");
                $stmt->execute([$userId]);
                $roleData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($roleData) {
                    // Add the teacher data directly to the user array
                    $user['first_name'] = $roleData['first_name'];
                    $user['last_name'] = $roleData['last_name'];
                    $user['teacher_id'] = $roleData['teacher_id'];

                    // Get teacher's subjects from assigned classes
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT s.subject_id 
                        FROM subjects s
                        JOIN class_subjects cs ON s.subject_id = cs.subject_id
                        WHERE cs.teacher_id = ?
                    ");
                    $stmt->execute([$roleData['teacher_id']]);
                    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    // Also get teacher's qualified subjects (that may not be assigned to any classes yet)
                    try {
                        $stmt = $pdo->prepare("
                            SELECT subject_id 
                            FROM teacher_subject_qualifications 
                            WHERE teacher_id = ?
                        ");
                        $stmt->execute([$roleData['teacher_id']]);
                        $qualifiedSubjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        // Merge both subject lists, removing duplicates
                        $subjects = array_values(array_unique(array_merge($subjects, $qualifiedSubjects)));
                    } catch (PDOException $e) {
                        // If the table doesn't exist, just use the subjects we already found
                        if ($e->getCode() != '42S02') throw $e;
                    }

                    $user['subjects'] = $subjects;
                }
                break;

            case ROLE_PARENT:
                $stmt = $pdo->prepare("SELECT * FROM parents WHERE user_id = ?");
                $stmt->execute([$userId]);
                $roleData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($roleData) {
                    $user['parent_id'] = $roleData['parent_id'];

                    // Get linked students
                    $stmt = $pdo->prepare("
                        SELECT sp.student_id
                        FROM student_parent sp
                        WHERE sp.parent_id = ?
                    ");
                    $stmt->execute([$roleData['parent_id']]);
                    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $user['children'] = $children;
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
                if (empty($userData['first_name']) || empty($userData['last_name'])) {
                    $pdo->rollBack();
                    return false;
                }

                $stmt = $pdo->prepare("
                    INSERT INTO teachers (user_id, first_name, last_name)
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $userId,
                    $userData['first_name'],
                    $userData['last_name']
                ]);

                // Get the newly created teacher_id
                $teacherId = $pdo->lastInsertId();

                // Handle teacher subject qualifications without creating table
                if (!empty($userData['teacher_subjects']) && is_array($userData['teacher_subjects'])) {
                    // Check if teacher_subject_qualifications table exists
                    $tableExists = false;
                    try {
                        $checkTable = $pdo->query("SHOW TABLES LIKE 'teacher_subject_qualifications'");
                        $tableExists = ($checkTable && $checkTable->rowCount() > 0);
                    } catch (PDOException $e) {
                        error_log("Error checking for teacher_subject_qualifications table: " . $e->getMessage());
                    }

                    // Only try to insert subject qualifications if the table exists
                    if ($tableExists) try {
                        $stmt = $pdo->prepare("
                            INSERT INTO teacher_subject_qualifications (teacher_id, subject_id)
                            VALUES (?, ?)
                        ");

                        foreach ($userData['teacher_subjects'] as $subjectId) if (is_numeric($subjectId)) $stmt->execute([$teacherId, (int)$subjectId]);
                    } catch (PDOException $e) {
                        // Log the error but don't fail the entire operation
                        error_log("Error inserting teacher subject qualifications: " . $e->getMessage());
                    }
                }
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

        // Update username and role in users table
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

        // Get current user role
        $stmt = $pdo->prepare("SELECT role_id FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $pdo->rollBack();
            return false;
        }

        // Handle role-specific data
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
                    // Check if student record exists
                    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $student = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($student) {
                        // Update existing student record
                        $query = "UPDATE students SET " . implode(", ", $updates) . " WHERE user_id = ?";
                        $params[] = $userId;

                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                    } else {
                        // Create new student record if changed from another role
                        $stmt = $pdo->prepare("INSERT INTO students (user_id, first_name, last_name, dob, class_code) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $userId,
                            $userData['first_name'] ?? '',
                            $userData['last_name'] ?? '',
                            $userData['dob'] ?? '',
                            $userData['class_code'] ?? ''
                        ]);
                    }
                }
                break;

            case ROLE_TEACHER:
                // Handle teacher data
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

                // Check if teacher record exists
                $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
                $stmt->execute([$userId]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($teacher) {
                    // Update existing teacher record (if there are updates to make)
                    if (!empty($updates)) {
                        $query = "UPDATE teachers SET " . implode(", ", $updates) . " WHERE user_id = ?";
                        $params[] = $userId;

                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                    }

                    // Handle teacher subjects (even if no other updates)
                    $teacherId = $teacher['teacher_id'];

                    // Check if teacher_subject_qualifications table exists
                    $tableExists = false;
                    try {
                        $checkTable = $pdo->query("SHOW TABLES LIKE 'teacher_subject_qualifications'");
                        $tableExists = ($checkTable && $checkTable->rowCount() > 0);
                    } catch (PDOException $e) {
                        error_log("Error checking for teacher_subject_qualifications table: " . $e->getMessage());
                    }

                    // If table doesn't exist, store subjects in user_meta or similar
                    if (!$tableExists) error_log("Teacher subjects not saved - table doesn't exist"); else if (isset($userData['teacher_subjects']) && is_array($userData['teacher_subjects'])) try {
                        // Delete existing qualifications
                        $stmt = $pdo->prepare("DELETE FROM teacher_subject_qualifications WHERE teacher_id = ?");
                        $stmt->execute([$teacherId]);

                        // Insert new qualifications (only if array is not empty)
                        if (!empty($userData['teacher_subjects'])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO teacher_subject_qualifications (teacher_id, subject_id)
                                VALUES (?, ?)
                            ");

                            foreach ($userData['teacher_subjects'] as $subjectId) if (is_numeric($subjectId)) $stmt->execute([$teacherId, (int)$subjectId]);
                        }
                    } catch (PDOException $e) {
                        // Log the error but don't fail the entire update
                        error_log("Error updating teacher subject qualifications: " . $e->getMessage());
                    }
                } else {
                    // Create new teacher record if changed from another role
                    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, first_name, last_name) VALUES (?, ?, ?)");
                    $stmt->execute([
                        $userId,
                        $userData['first_name'] ?? '',
                        $userData['last_name'] ?? ''
                    ]);

                    // Get the newly created teacher_id
                    $teacherId = $pdo->lastInsertId();

                    // Check if teacher_subject_qualifications table exists
                    $tableExists = false;
                    try {
                        $checkTable = $pdo->query("SHOW TABLES LIKE 'teacher_subject_qualifications'");
                        $tableExists = ($checkTable && $checkTable->rowCount() > 0);
                    } catch (PDOException $e) {
                        error_log("Error checking for teacher_subject_qualifications table: " . $e->getMessage());
                    }

                    // Handle teacher subjects if the table exists
                    if ($tableExists && is_array($userData['teacher_subjects']) && !empty($userData['teacher_subjects'])) try {
                        $stmt = $pdo->prepare("
                            INSERT INTO teacher_subject_qualifications (teacher_id, subject_id)
                            VALUES (?, ?)
                        ");

                        foreach ($userData['teacher_subjects'] as $subjectId) if (is_numeric($subjectId)) $stmt->execute([$teacherId, (int)$subjectId]);
                    } catch (PDOException $e) {
                        // Log the error but don't fail the entire create
                        error_log("Error inserting teacher subject qualifications: " . $e->getMessage());
                    }
                }
                break;

            case ROLE_PARENT:
                // Handle parent data
                $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
                $stmt->execute([$userId]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$parent) {
                    // Create new parent record if it doesn't exist
                    $stmt = $pdo->prepare("INSERT INTO parents (user_id) VALUES (?)");
                    $stmt->execute([$userId]);

                    // Get the newly created parent_id
                    $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if ($parent && isset($userData['student_ids']) && is_array($userData['student_ids'])) {
                    // Delete existing relationships
                    $stmt = $pdo->prepare("DELETE FROM student_parent WHERE parent_id = ?");
                    $stmt->execute([$parent['parent_id']]);

                    // Add new relationships
                    if (!empty($userData['student_ids'])) {
                        $stmt = $pdo->prepare("INSERT INTO student_parent (student_id, parent_id) VALUES (?, ?)");
                        foreach ($userData['student_ids'] as $studentId) $stmt->execute([$studentId, $parent['parent_id']]);
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

/**
 * Creates a new user based on form data
 * @return void
 */
function handleCreateUser(): void
{
    global $message, $messageType;

    $userData = [
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? '',
        'email' => $_POST['email'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'role_id' => (int)($_POST['role_id'] ?? 0)
    ];

    if (strlen($userData['password']) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $messageType = 'error';
        return;
    }

    if ($userData['role_id'] === ROLE_STUDENT) {
        $userData['class_code'] = $_POST['student_class'] ?? '';
        $userData['dob'] = $_POST['dob'] ?? '';
        if (empty($userData['dob'])) {
            $message = 'Date of birth is required for students.';
            $messageType = 'error';
            return;
        }
    } elseif ($userData['role_id'] === ROLE_TEACHER) $userData['teacher_subjects'] = $_POST['teacher_subjects'] ?? [];
    elseif ($userData['role_id'] === ROLE_PARENT) $userData['student_ids'] = $_POST['parent_children'] ?? [];

    $validationResult = validateUserForm($userData);
    if ($validationResult !== true) {
        $message = $validationResult;
        $messageType = 'error';
    } else if (createNewUser($userData)) {
        $message = 'User created successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error creating user. Please check the form and try again.';
        $messageType = 'error';
    }
}

/**
 * Updates an existing user based on form data
 * @return void
 */
function handleUpdateUser(): void
{
    global $message, $messageType;

    $userId = (int)($_POST['user_id'] ?? 0);
    if (!$userId) {
        $message = 'Invalid user ID.';
        $messageType = 'error';
        return;
    }

    $userData = [
        'user_id' => $userId, // Make sure user_id is included in userData
        'username' => $_POST['username'] ?? '',
        'email' => $_POST['email'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'role_id' => (int)($_POST['role_id'] ?? 0)
    ];

    if ($userData['role_id'] === ROLE_STUDENT) {
        $userData['class_code'] = $_POST['student_class'] ?? '';
        $userData['dob'] = $_POST['dob'] ?? '';
        if (empty($userData['dob'])) {
            $message = 'Date of birth is required for students.';
            $messageType = 'error';
            return;
        }
    } elseif ($userData['role_id'] === ROLE_TEACHER) $userData['teacher_subjects'] = $_POST['teacher_subjects'] ?? [];
    elseif ($userData['role_id'] === ROLE_PARENT) $userData['student_ids'] = $_POST['parent_children'] ?? [];

    $validationResult = validateUserForm($userData);
    if ($validationResult !== true) {
        $message = $validationResult;
        $messageType = 'error';
    } else if (updateUser($userId, $userData)) {
        $message = 'User updated successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error updating user. Please check the form and try again.';
        $messageType = 'error';
    }
}

/**
 * Resets a user's password
 * @return void
 */
function handleResetPassword(): void
{
    global $message, $messageType;

    $userId = (int)($_POST['user_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match. Please try again.';
        $messageType = 'error';
    } else if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $messageType = 'error';
    } else if (resetUserPassword($userId, $newPassword)) {
        $message = 'Password reset successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error resetting password. Please try again.';
        $messageType = 'error';
    }
}

/**
 * Deletes a user after confirmation
 * @return void
 */
function handleDeleteUser(): void
{
    global $message, $messageType;

    $userId = (int)($_POST['user_id'] ?? 0);
    $confirmation = $_POST['delete_confirmation'] ?? '';

    if ($confirmation !== 'DELETE') {
        $message = 'Invalid confirmation. User was not deleted.';
        $messageType = 'error';
    } else if ($userId == $_SESSION['user_id']) {
        $message = 'You cannot delete your own account.';
        $messageType = 'error';
    } else if (deleteUser($userId)) {
        $message = 'User deleted successfully.';
        $messageType = 'success';
    } else {
        $message = 'Error deleting user. The user may have associated data that prevents deletion.';
        $messageType = 'error';
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

        echo '<a href="/uwuweb/admin/manage_subjects.php?subject_id=' . $subject['subject_id'] . '" class="btn btn-primary btn-sm">Uredi</a>';

        echo '<a href="/uwuweb/admin/manage_subjects.php?action=delete_subject&subject_id=' . $subject['subject_id'] . '" class="btn btn-error btn-sm"
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
            SELECT c.*, t.teacher_id, u.username as homeroom_teacher_name,
                   (SELECT COUNT(*) FROM students s WHERE s.class_code = c.class_code) as student_count,
                   (SELECT COUNT(*) FROM class_subjects cs WHERE cs.class_id = c.class_id) as subject_count
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

    echo '<table class="data-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Ime</th>';
    echo '<th>Koda (Leto)</th>';
    echo '<th>Razrednik</th>';
    echo '<th class="text-center">Učenci</th>';
    echo '<th class="text-center">Predmeti</th>';
    echo '<th class="text-right">Dejanja</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if (empty($classes)) {
        echo '<tr>';
        echo '<td colspan="6" class="text-center p-lg">';
        echo '<div class="alert status-info mb-0">';
        echo 'Ni še ustvarjenih razredov. Uporabite gumb zgoraj za dodajanje.';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    } else foreach ($classes as $class) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($class['title']) . '</td>';
        echo '<td>' . htmlspecialchars($class['class_code']) . '</td>';
        echo '<td>' . htmlspecialchars($class['homeroom_teacher_name'] ?? 'N/A') . '</td>';
        echo '<td class="text-center">' . ($class['student_count'] ?? 0) . '</td>';
        echo '<td class="text-center">' . ($class['subject_count'] ?? 0) . '</td>';
        echo '<td>';
        echo '<div class="d-flex justify-end gap-xs">';
        echo '<button class="btn btn-secondary btn-sm edit-class-btn d-flex items-center gap-xs" ';
        echo 'data-id="' . $class['class_id'] . '">';
        echo '<span class="text-md">✎</span> Uredi';
        echo '</button>';
        echo '<button class="btn btn-secondary btn-sm delete-class-btn d-flex items-center gap-xs" ';
        echo 'data-id="' . $class['class_id'] . '" ';
        echo 'data-name="' . htmlspecialchars($class['title'] ?? '') . '">';
        echo '<span class="text-md">🗑</span> Izbriši';
        echo '</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
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
    if (empty($classData['class_code']) || empty($classData['homeroom_teacher_id'])) return false;

    try {
        $pdo = safeGetDBConnection('createClass');

        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija createClass", 500, "admin_functions.php");

        // Use empty string for title if it's empty
        $title = !empty($classData['title']) ? $classData['title'] : "";

        $stmt = $pdo->prepare("INSERT INTO classes (class_code, title, homeroom_teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([
            $classData['class_code'],
            $title,
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

        // Handle title separately to properly accommodate it being optional
        if (isset($classData['title'])) {
            // If title is empty, set it to empty string
            $updates[] = "title = ?";
            $params[] = $classData['title']; // This will be empty string if title is empty
        }

        if (!empty($classData['homeroom_teacher_id'])) {
            $updates[] = "homeroom_teacher_id = ?";
            $params[] = $classData['homeroom_teacher_id'];
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
            SELECT t.teacher_id, u.user_id, u.username, t.first_name, t.last_name
            FROM teachers t
            JOIN users u ON t.user_id = u.user_id
            WHERE u.role_id = ?
            ORDER BY t.last_name, t.first_name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([ROLE_TEACHER]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju učiteljev: " . $e->getMessage());
        return [];
    }
}

// ===== System Settings Functions =====

/**
 * Retrieves system-wide settings
 *
 * @return array Array containing system settings with defaults if not found
 */
function getSystemSettings(): array
{
    try {
        $pdo = safeGetDBConnection('getSystemSettings');
        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija getSystemSettings", 500, "admin_functions.php");

        $stmt = $pdo->query("SELECT * FROM system_settings LIMIT 1");

        if ($stmt->rowCount() > 0) return $stmt->fetch(PDO::FETCH_ASSOC);

        // Default settings if no settings found
        return [
            'school_name' => 'ŠCC Celje',
            'current_year' => '2024/2025',
            'school_address' => '',
            'session_timeout' => 30,
            'grade_scale' => '1-5',
            'maintenance_mode' => false
        ];
    } catch (PDOException $e) {
        logDBError("Napaka pri pridobivanju sistemskih nastavitev: " . $e->getMessage());

        // Return defaults in case of error
        return [
            'school_name' => 'ŠCC Celje',
            'current_year' => '2024/2025',
            'school_address' => '',
            'session_timeout' => 30,
            'grade_scale' => '1-5',
            'maintenance_mode' => false
        ];
    }
}

/**
 * Updates system-wide settings
 *
 * @param array $settings Associative array of settings to update
 * @return bool Returns true on success, false on failure
 */
function updateSystemSettings(array $settings): bool
{
    try {
        $pdo = safeGetDBConnection('updateSystemSettings');
        if ($pdo === null) sendJsonErrorResponse("Povezava s podatkovno bazo ni uspela - funkcija updateSystemSettings", 500, "admin_functions.php");

        // Check if settings table exists and has records
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
        $settingsExist = (int)$stmt->fetchColumn() > 0;

        if ($settingsExist) {
            // Update existing settings
            $sql = "UPDATE system_settings SET
                    school_name = :school_name,
                    current_year = :current_year,
                    school_address = :school_address,
                    session_timeout = :session_timeout,
                    grade_scale = :grade_scale,
                    maintenance_mode = :maintenance_mode,
                    updated_at = NOW()";

            $stmt = $pdo->prepare($sql);
            return $stmt->execute([
                'school_name' => $settings['school_name'],
                'current_year' => $settings['current_year'],
                'school_address' => $settings['school_address'],
                'session_timeout' => (int)$settings['session_timeout'],
                'grade_scale' => $settings['grade_scale'],
                'maintenance_mode' => $settings['maintenance_mode'] ? 1 : 0
            ]);
        }

        // Insert new settings
        $sql = "INSERT INTO system_settings (
                school_name, current_year, school_address,
                session_timeout, grade_scale, maintenance_mode,
                created_at, updated_at)
               VALUES (
                :school_name, :current_year, :school_address,
                :session_timeout, :grade_scale, :maintenance_mode,
                NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            'school_name' => $settings['school_name'],
            'current_year' => $settings['current_year'],
            'school_address' => $settings['school_address'],
            'session_timeout' => (int)$settings['session_timeout'],
            'grade_scale' => $settings['grade_scale'],
            'maintenance_mode' => $settings['maintenance_mode'] ? 1 : 0
        ]);
    } catch (PDOException $e) {
        logDBError("Napaka pri posodabljanju sistemskih nastavitev: " . $e->getMessage());
        return false;
    }
}

// ===== Dashboard Widget Functions =====

/**
 * Displays user statistics by role with counts and recent registrations
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
        $roleStats = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentQuery = "SELECT u.user_id, u.username, u.created_at, r.name as role_name
                       FROM users u
                       JOIN roles r ON u.role_id = r.role_id
                       WHERE u.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                       ORDER BY u.created_at DESC
                       LIMIT 5";
        $recentStmt = $db->query($recentQuery);
        $recentUsers = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalUsers = array_sum(array_column($roleStats, 'count'));

        $output = '<div class="d-flex flex-column h-full">'; // Main widget container

        // User stats summary section (expanding)
        $output .= '<div class="rounded p-0 shadow-sm flex-grow-1 d-flex flex-column">';
        $output .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Pregled uporabnikov</h5>';
        $output .= '<div class="p-md flex-grow-1" style="overflow-y: auto;">'; // Content wrapper, scrollable

        // Role statistics
        $output .= '<div class="mb-lg">';
        $output .= '<div class="d-flex justify-between items-center py-sm border-bottom">';
        $output .= '<span class="font-medium">Vsi uporabniki:</span>';
        $output .= '<span class="badge badge-primary">' . $totalUsers . '</span>';
        $output .= '</div>';

        foreach ($roleStats as $role) {
            $roleName = strtolower($role['name']);
            $roleClass = match ($roleName) {
                'teacher' => 'role-teacher',
                'student' => 'role-student',
                'parent' => 'role-parent',
                default => 'role-admin', // Assuming 'admin' or other roles
            };

            $output .= '<div class="d-flex justify-between items-center py-sm border-bottom">';
            $output .= '<div class="d-flex items-center">';
            $output .= '<span class="role-badge ' . $roleClass . ' mr-sm">' . strtoupper(substr($role['name'], 0, 1)) . '</span>';
            $output .= '<span>' . htmlspecialchars(ucfirst($role['name'])) . ':</span>';
            $output .= '</div>';
            $output .= '<span class="badge badge-secondary">' . $role['count'] . '</span>';
            $output .= '</div>';
        }
        $output .= '</div>'; // End role statistics

        // Recent users section if available
        if (!empty($recentUsers)) {
            $output .= '<div class="mt-lg">';
            $output .= '<h6 class="font-medium mb-md">Novi uporabniki (zadnjih 7 dni)</h6>';

            foreach ($recentUsers as $user) {
                $roleName = strtolower($user['role_name']);
                $roleClass = match ($roleName) {
                    'teacher' => 'role-teacher',
                    'student' => 'role-student',
                    'parent' => 'role-parent',
                    default => 'role-admin',
                };

                $output .= '<div class="d-flex justify-between items-center py-sm border-bottom">';
                $output .= '<div class="d-flex items-center">';
                $output .= '<span class="role-badge ' . $roleClass . ' mr-sm">' . strtoupper(substr($user['role_name'], 0, 1)) . '</span>';
                $output .= '<span>' . htmlspecialchars($user['username']) . '</span>';
                $output .= '</div>';
                $output .= '<span class="text-sm text-secondary">' . date('d.m.Y', strtotime($user['created_at'])) . '</span>';
                $output .= '</div>';
            }
            $output .= '</div>'; // End recent users
        } elseif (empty($roleStats)) $output .= '<p class="text-secondary text-center">Ni podatkov o uporabnikih.</p>';

        $output .= '</div>'; // End content wrapper
        $output .= '</div>'; // End section
        $output .= '</div>'; // End flex container

        return $output;
    } catch (Exception $e) {
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

        $output = '<div class="d-flex flex-column h-full">'; // Main widget container

        // Database status section
        $output .= '<div class="rounded p-0 shadow-sm mb-lg">';
        $output .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Podatkovna baza</h5>';
        $output .= '<div class="p-md">';
        $dbItems = [
            ['label' => 'Ime baze:', 'value' => $dbName ?: 'N/A', 'badge' => 'primary'],
            ['label' => 'Tip strežnika:', 'value' => 'MySQL ' . $db->getAttribute(PDO::ATTR_SERVER_VERSION), 'badge' => 'primary'],
            ['label' => 'Št. tabel:', 'value' => $tableStats['table_count'] ?? 'N/A', 'badge' => 'secondary'],
            ['label' => 'Velikost baze:', 'value' => round($tableStats['size_mb'] ?? 0, 2) . ' MB', 'badge' => 'secondary']
        ];
        foreach ($dbItems as $item) {
            $output .= '<div class="d-flex justify-between items-center py-xs">';
            $output .= '<span class="text-secondary">' . $item['label'] . '</span>';
            $output .= '<span class="badge badge-' . $item['badge'] . '">' . htmlspecialchars($item['value']) . '</span>';
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';

        // Server status section (expanding)
        $output .= '<div class="rounded p-0 shadow-sm flex-grow-1 d-flex flex-column">';
        $output .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Strežnik & PHP</h5>';
        $output .= '<div class="p-md flex-grow-1">';
        $serverItems = [
            ['label' => 'PHP verzija:', 'value' => PHP_VERSION, 'badge' => 'info'],
            ['label' => 'Aktivne seje:', 'value' => $sessionCount, 'badge' => ($sessionCount > 10 ? 'warning' : 'info')], // Adjusted threshold
            ['label' => 'Max. nalaganje:', 'value' => ini_get('upload_max_filesize'), 'badge' => 'secondary'],
            ['label' => 'Čas strežnika:', 'value' => date('d.m.Y H:i:s'), 'badge' => 'secondary']
        ];
        foreach ($serverItems as $item) {
            $output .= '<div class="d-flex justify-between items-center py-xs">';
            $output .= '<span class="text-secondary">' . $item['label'] . '</span>';
            $output .= '<span class="badge badge-' . $item['badge'] . '">' . htmlspecialchars($item['value']) . '</span>';
            $output .= '</div>';
        }
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>'; // End flex container

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
                          (COUNT(CASE WHEN a.status = 'P' THEN 1 END) * 100.0 / NULLIF(COUNT(a.att_id),0)) as present_percent
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

        $output = '<div class="d-flex flex-column h-full">'; // Main widget container

        // Overall attendance section

        $output .= '<div class="rounded p-0 shadow-sm mb-lg">';
        $output .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Prisotnost (zadnjih ' . $intervalDays . ' dni)</h5>';
        $output .= '<div class="p-md">';
        if ($total > 0) {
            $output .= '<div class="rounded overflow-hidden mb-md" style="height: 24px;">';
            $output .= '<div class="d-flex w-full h-full">';
            if ($presentPercent > 0) $output .= '<div class="status-present d-flex items-center justify-center text-xs" style="width:' . $presentPercent . '%">' . ($presentPercent > 10 ? $presentPercent . '%' : '') . '</div>';
            if ($latePercent > 0) $output .= '<div class="status-late d-flex items-center justify-center text-xs" style="width:' . $latePercent . '%">' . ($latePercent > 10 ? $latePercent . '%' : '') . '</div>';
            if ($absentPercent > 0) $output .= '<div class="status-absent d-flex items-center justify-center text-xs" style="width:' . $absentPercent . '%">' . ($absentPercent > 10 ? $absentPercent . '%' : '') . '</div>';
            $output .= '</div>';
            $output .= '</div>';

            $legendItems = [
                ['class' => 'status-present', 'label' => 'Prisotni', 'count' => $present, 'percent' => $presentPercent],
                ['class' => 'status-late', 'label' => 'Zamude', 'count' => $late, 'percent' => $latePercent],
                ['class' => 'status-absent', 'label' => 'Odsotni', 'count' => $absent, 'percent' => $absentPercent]
            ];
            $output .= '<div class="d-flex flex-wrap gap-md justify-around">'; // justify-around for better spacing
            foreach ($legendItems as $item) {
                $output .= '<div class="d-flex items-center gap-xs">';
                $output .= '<span class="d-inline-block rounded-full ' . $item['class'] . '" style="width: 12px; height: 12px;"></span>';
                $output .= '<span class="text-sm">' . $item['label'] . ': <span class="font-medium">' . $item['count'] . '</span> (' . $item['percent'] . '%)</span>';
                $output .= '</div>';
            }
            $output .= '</div>';
        } else $output .= '<p class="text-secondary text-center m-0">Ni podatkov o prisotnosti za izbrano obdobje.</p>';
        $output .= '</div>';
        $output .= '</div>';

        // Best class section (expanding)
        $output .= '<div class="rounded p-0 shadow-sm flex-grow-1 d-flex flex-column">';
        $output .= '<h5 class="m-0 mt-md ml-md mb-sm card-subtitle font-medium border-bottom">Razred z najboljšo prisotnostjo</h5>';
        $output .= '<div class="p-md flex-grow-1 d-flex flex-column justify-center">';
        if ($bestClass && $bestClass['present_percent'] !== null) {
            $output .= '<div class="d-flex justify-between items-center mb-md">';
            $output .= '<div>';
            $output .= '<span class="text-lg font-medium">' . htmlspecialchars($bestClass['title']) . '</span>';
            $output .= '<span class="d-block text-sm text-secondary mt-xs">(' . htmlspecialchars($bestClass['class_code']) . ')</span>';
            $output .= '</div>';
            $output .= '<div class="text-right">';
            $output .= '<span class="grade grade-5 d-block text-xl">' . htmlspecialchars(round((float)$bestClass['present_percent'], 1)) . '%</span>';
            $output .= '<span class="d-block text-sm text-secondary mt-xs">prisotnost</span>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="d-flex justify-around text-center text-sm">';
            $output .= '<div><span class="d-block font-medium">' . htmlspecialchars($bestClass['present_count']) . '</span><span class="text-secondary">prisotnih</span></div>';
            $output .= '<div><span class="d-block font-medium">' . htmlspecialchars($bestClass['total_count'] - $bestClass['present_count']) . '</span><span class="text-secondary">odsotnih/zamud</span></div>';
            $output .= '<div><span class="d-block font-medium">' . htmlspecialchars($bestClass['total_count']) . '</span><span class="text-secondary">vseh vnosov</span></div>';
            $output .= '</div>';
        } else $output .= '<p class="text-secondary text-center m-0">Ni dovolj podatkov za prikaz najboljšega razreda.</p>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>'; // End flex container

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

    if (!preg_match('/^[\w.]+$/', $userData['username'])) return 'Uporabniško ime lahko vsebuje samo črke, številke, podčrtaje in pike.';

    if (strlen($userData['username']) < 3 || strlen($userData['username']) > 50) return 'Uporabniško ime mora biti dolgo med 3 in 50 znakov.';

    if (!isset($userData['user_id']) && usernameExists($userData['username'])) return 'Uporabniško ime je že zasedeno.';
    // Check if username exists when updating, excluding self
    if (!empty($userData['user_id']) && usernameExists($userData['username'], (int)$userData['user_id'])) return 'Uporabniško ime je že zasedeno.';

    if (empty($userData['role_id']) || !in_array($userData['role_id'], [ROLE_ADMIN, ROLE_TEACHER, ROLE_STUDENT, ROLE_PARENT], true)) return 'Izbrana je neveljavna vloga.';

    if (!isset($userData['user_id']) && empty($userData['password'])) return 'Geslo je obvezno za nove uporabnike.';

    if (!isset($userData['user_id']) && !empty($userData['password'])) if (strlen($userData['password']) < 6) return 'Geslo mora biti dolgo vsaj 6 znakov.';

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
