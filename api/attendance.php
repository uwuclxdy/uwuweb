<?php
/**
 * Attendance API Endpoint
 *
 * Handles CRUD operations for attendance data
 * Returns JSON responses for AJAX requests
 *
 * Functions:
 * - addPeriod() - Creates a new period for a class
 * - updatePeriod() - Updates an existing period
 * - deletePeriod() - Deletes a period and related attendance records
 * - saveAttendance() - Saves attendance for a single student
 * - bulkAttendance() - Saves attendance for multiple students at once
 * - justifyAbsence() - Records or approves absence justification
 * - getStudentAttendance() - Gets attendance summary for a student
 * - teacherHasAccessToClass($classId) - Verifies teacher access to class
 * - teacherHasAccessToPeriod($periodId) - Verifies teacher access to period
 * - teacherHasAccessToEnrollment($enrollId) - Verifies teacher access to enrollment
 * - studentOwnsEnrollment($enrollId) - Checks if student owns enrollment
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are allowed'], JSON_THROW_ON_ERROR);
    exit;
}

// Ensure CSRF token is valid
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Invalid CSRF token'], JSON_THROW_ON_ERROR);
    exit;
}

// Require teacher or admin role for access
if (!isLoggedIn() || (!hasRole(ROLE_TEACHER) && !hasRole(ROLE_ADMIN))) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Unauthorized access'], JSON_THROW_ON_ERROR);
    exit;
}

// Get the action from the request
$action = $_POST['action'] ?? '';

// Process based on the action
switch ($action) {
    case 'add_period':
        addPeriod();
        break;
    case 'update_period':
        updatePeriod();
        break;
    case 'delete_period':
        deletePeriod();
        break;
    case 'save_attendance':
        saveAttendance();
        break;
    case 'bulk_attendance':
        bulkAttendance();
        break;
    case 'justify_absence':
        justifyAbsence();
        break;
    case 'get_student_attendance':
        getStudentAttendance();
        break;
    default:
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid action requested'], JSON_THROW_ON_ERROR);
        break;
}

/**
 * Add a new period to a class
 */
function addPeriod() {
    // Validate inputs
    $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $periodDate = trim($_POST['period_date'] ?? '');
    $periodLabel = trim($_POST['period_label'] ?? '');

    if (!$classId || empty($periodDate) || empty($periodLabel)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data'], JSON_THROW_ON_ERROR);
        return;
    }

    // Verify teacher has access to this class
    if (!teacherHasAccessToClass($classId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this class'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new PDOException("Failed to connect to database");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO periods (class_id, period_date, period_label) 
             VALUES (:class_id, :period_date, :period_label)"
        );

        $stmt->execute([
            'class_id' => $classId,
            'period_date' => $periodDate,
            'period_label' => $periodLabel
        ]);

        $periodId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Period added successfully',
            'period_id' => $periodId,
            'period' => [
                'period_id' => $periodId,
                'period_date' => $periodDate,
                'period_label' => $periodLabel
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}

/**
 * Update an existing period
 */
function updatePeriod() {
    // Validate inputs
    $periodId = filter_input(INPUT_POST, 'period_id', FILTER_VALIDATE_INT);
    $periodDate = trim($_POST['period_date'] ?? '');
    $periodLabel = trim($_POST['period_label'] ?? '');

    if (!$periodId || empty($periodDate) || empty($periodLabel)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data'], JSON_THROW_ON_ERROR);
        return;
    }

    // Verify teacher has access to this period
    if (!teacherHasAccessToPeriod($periodId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this period'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new PDOException("Failed to connect to database");
        }

        $stmt = $pdo->prepare(
            "UPDATE periods 
             SET period_date = :period_date, period_label = :period_label
             WHERE period_id = :period_id"
        );

        $stmt->execute([
            'period_id' => $periodId,
            'period_date' => $periodDate,
            'period_label' => $periodLabel
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Period updated successfully',
            'period' => [
                'period_id' => $periodId,
                'period_date' => $periodDate,
                'period_label' => $periodLabel
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}

/**
 * Delete a period
 */
function deletePeriod() {
    // Validate input
    $periodId = filter_input(INPUT_POST, 'period_id', FILTER_VALIDATE_INT);

    if (!$periodId) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid period ID'], JSON_THROW_ON_ERROR);
        return;
    }

    // Verify teacher has access to this period
    if (!teacherHasAccessToPeriod($periodId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this period'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new PDOException("Failed to connect to database");
        }

        // Start transaction to ensure data integrity
        $pdo->beginTransaction();

        // Delete related attendance records first
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE period_id = :period_id");
        $stmt->execute(['period_id' => $periodId]);

        // Then delete the period
        $stmt = $pdo->prepare("DELETE FROM periods WHERE period_id = :period_id");
        $stmt->execute(['period_id' => $periodId]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Period deleted successfully'
        ], JSON_THROW_ON_ERROR);
    } catch (PDOException $e) {
        // Rollback on error
        if (isset($pdo) && $pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}

/**
 * Save attendance for a single student
 */
function saveAttendance() {
    // Validate inputs
    $enrollId = filter_input(INPUT_POST, 'enroll_id', FILTER_VALIDATE_INT);
    $periodId = filter_input(INPUT_POST, 'period_id', FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';

    if (!$enrollId || !$periodId || !in_array($status, ['P', 'A', 'L'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data'], JSON_THROW_ON_ERROR);
        return;
    }

    // Verify teacher has access to this period and enrollment
    if (!teacherHasAccessToPeriod($periodId) || !teacherHasAccessToEnrollment($enrollId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this student or period'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new PDOException("Failed to connect to database");
        }

        // Check if attendance record already exists
        $stmt = $pdo->prepare(
            "SELECT att_id FROM attendance 
             WHERE enroll_id = :enroll_id AND period_id = :period_id"
        );

        $stmt->execute([
            'enroll_id' => $enrollId,
            'period_id' => $periodId
        ]);

        $existingRecord = $stmt->fetch();

        if ($existingRecord) {
            // Update existing record
            $stmt = $pdo->prepare(
                "UPDATE attendance 
                 SET status = :status
                 WHERE enroll_id = :enroll_id AND period_id = :period_id"
            );

            $stmt->execute([
                'enroll_id' => $enrollId,
                'period_id' => $periodId,
                'status' => $status
            ]);

            $attId = $existingRecord['att_id'];
        } else {
            // Insert new record
            $stmt = $pdo->prepare(
                "INSERT INTO attendance (enroll_id, period_id, status) 
                 VALUES (:enroll_id, :period_id, :status)"
            );

            $stmt->execute([
                'enroll_id' => $enrollId,
                'period_id' => $periodId,
                'status' => $status
            ]);

            $attId = $pdo->lastInsertId();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Attendance saved successfully',
            'attendance' => [
                'att_id' => $attId,
                'enroll_id' => $enrollId,
                'period_id' => $periodId,
                'status' => $status
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}

/**
 * Save attendance for multiple students at once
 */
function bulkAttendance() {
    // Validate inputs
    $periodId = filter_input(INPUT_POST, 'period_id', FILTER_VALIDATE_INT);
    $attendanceData = isset($_POST['attendance_data']) ? json_decode($_POST['attendance_data'], true, 512, JSON_THROW_ON_ERROR) : null;

    if (!$periodId || !is_array($attendanceData)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data'], JSON_THROW_ON_ERROR);
        return;
    }

    // Verify teacher has access to this period
    if (!teacherHasAccessToPeriod($periodId)) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'You do not have access to this period'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new PDOException("Failed to connect to database");
        }

        $pdo->beginTransaction();

        $saved = 0;
        $errors = [];

        foreach ($attendanceData as $data) {
            $enrollId = isset($data['enroll_id']) ? filter_var($data['enroll_id'], FILTER_VALIDATE_INT) : null;
            $status = $data['status'] ?? '';

            if (!$enrollId || !in_array($status, ['P', 'A', 'L'])) {
                $errors[] = "Invalid data for enrollment ID: $enrollId";
                continue;
            }

            // Verify teacher has access to this enrollment
            if (!teacherHasAccessToEnrollment($enrollId)) {
                $errors[] = "Access denied for enrollment ID: $enrollId";
                continue;
            }

            // Check if attendance record already exists
            $stmt = $pdo->prepare(
                "SELECT att_id FROM attendance 
                 WHERE enroll_id = :enroll_id AND period_id = :period_id"
            );

            $stmt->execute([
                'enroll_id' => $enrollId,
                'period_id' => $periodId
            ]);

            $existingRecord = $stmt->fetch();

            if ($existingRecord) {
                // Update existing record
                $stmt = $pdo->prepare(
                    "UPDATE attendance 
                     SET status = :status
                     WHERE enroll_id = :enroll_id AND period_id = :period_id"
                );

                $stmt->execute([
                    'enroll_id' => $enrollId,
                    'period_id' => $periodId,
                    'status' => $status
                ]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare(
                    "INSERT INTO attendance (enroll_id, period_id, status) 
                     VALUES (:enroll_id, :period_id, :status)"
                );

                $stmt->execute([
                    'enroll_id' => $enrollId,
                    'period_id' => $periodId,
                    'status' => $status
                ]);
            }

            $saved++;
        }

        if (!empty($errors)) {
            $pdo->rollBack();
            http_response_code(400); // Bad Request
            echo json_encode([
                'success' => false,
                'message' => 'Errors occurred while saving attendance',
                'errors' => $errors
            ], JSON_THROW_ON_ERROR);
        } else {
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => "Attendance saved successfully for $saved students",
                'saved_count' => $saved
            ], JSON_THROW_ON_ERROR);
        }
    } catch (PDOException $e) {
        // Rollback on error
        if (isset($pdo) && $pdo && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}

/**
 * Record or approve a justification for an absence
 */
function justifyAbsence() {
    // Validate inputs
    $attId = filter_input(INPUT_POST, 'att_id', FILTER_VALIDATE_INT);
    $justification = trim($_POST['justification'] ?? '');
    $approved = isset($_POST['approved']) ? filter_var($_POST['approved'], FILTER_VALIDATE_BOOLEAN) : null;

    if (!$attId || empty($justification)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid input data'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new PDOException("Failed to connect to database");
        }

        // Check if the attendance record exists and if the user has access
        $stmt = $pdo->prepare(
            "SELECT a.att_id, a.status, a.justification, e.enroll_id, p.period_id, c.class_id, c.teacher_id
             FROM attendance a
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             JOIN periods p ON a.period_id = p.period_id
             JOIN classes c ON p.class_id = c.class_id
             WHERE a.att_id = :att_id"
        );

        $stmt->execute(['att_id' => $attId]);
        $record = $stmt->fetch();

        if (!$record) {
            http_response_code(404); // Not Found
            echo json_encode(['error' => 'Attendance record not found'], JSON_THROW_ON_ERROR);
            return;
        }

        // Check if the current user is a teacher who can approve justifications
        $isTeacher = hasRole(ROLE_TEACHER);
        $isTeacherOfClass = $isTeacher && teacherHasAccessToClass($record['class_id']);

        // Check if the current user is a student who can submit justifications
        $isStudent = hasRole(ROLE_STUDENT);
        $isStudentOfEnrollment = $isStudent && studentOwnsEnrollment($record['enroll_id']);

        // Only allow teachers to approve justifications
        if ($approved !== null && (!$isTeacher || !$isTeacherOfClass)) {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'Only teachers can approve justifications'], JSON_THROW_ON_ERROR);
            return;
        }

        // Only allow students to submit justifications or teachers to modify them
        if (!$isStudentOfEnrollment && !$isTeacherOfClass) {
            http_response_code(403); // Forbidden
            echo json_encode(['error' => 'You do not have permission to modify this justification'], JSON_THROW_ON_ERROR);
            return;
        }

        // Update the justification
        $updateFields = ['justification = :justification'];
        $params = [
            'att_id' => $attId,
            'justification' => $justification
        ];

        // If approval status is provided (by a teacher)
        if ($approved !== null && $isTeacherOfClass) {
            $updateFields[] = 'approved = :approved';
            $params['approved'] = $approved ? 1 : 0;
        }

        $stmt = $pdo->prepare(
            "UPDATE attendance 
             SET " . implode(', ', $updateFields) . "
             WHERE att_id = :att_id"
        );

        $stmt->execute($params);

        echo json_encode([
            'success' => true,
            'message' => 'Justification ' . (static function() use ($approved) {
                            if ($approved === null) {
                                return 'saved';
                            }
                            return $approved ? 'approved' : 'rejected';
                        })() . ' successfully',
            'justification' => [
                'att_id' => $attId,
                'justification' => $justification,
                'approved' => $approved
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
    }
}

/**
 * Get attendance summary for a student
 */
function getStudentAttendance() {
    // Validate inputs
    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);

    if (!$studentId) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid student ID'], JSON_THROW_ON_ERROR);
        return;
    }

    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            throw new PDOException("Failed to connect to database");
        }

        // Build the query based on whether a specific class is requested
        $query = "
            SELECT p.period_date, p.period_label, c.title as class_title, 
                   s.name as subject_name, a.status, a.justification, a.approved
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN classes c ON p.class_id = c.class_id
            JOIN subjects s ON c.subject_id = s.subject_id
            JOIN students st ON e.student_id = st.student_id
            WHERE st.student_id = :student_id
        ";

        $params = ['student_id' => $studentId];

        if ($classId) {
            $query .= " AND c.class_id = :class_id";
            $params['class_id'] = $classId;
        }

        $query .= " ORDER BY p.period_date DESC, p.period_label ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $attendance = $stmt->fetchAll();

        // Calculate summary statistics
        $total = count($attendance);
        $present = 0;
        $absent = 0;
        $late = 0;
        $justified = 0;

        foreach ($attendance as $record) {
            switch ($record['status']) {
                case 'P':
                    $present++;
                    break;
                case 'A':
                    $absent++;
                    if (!empty($record['justification']) && $record['approved']) {
                        $justified++;
                    }
                    break;
                case 'L':
                    $late++;
                    break;
            }
        }

        $summary = [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'justified' => $justified,
            'attendance_rate' => $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0
        ];

        echo json_encode([
            'success' => true,
            'attendance' => $attendance,
            'summary' => $summary
        ], JSON_THROW_ON_ERROR);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
    }
}

/**
 * Check if the logged-in teacher has access to a specific class
 */
function teacherHasAccessToClass($classId) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return false; // Cannot verify access without DB connection
        }

        // Admins have access to all classes
        if (hasRole(ROLE_ADMIN)) {
            return true;
        }

        $stmt = $pdo->prepare(
            "SELECT c.class_id
             FROM classes c
             JOIN teachers t ON c.teacher_id = t.teacher_id
             WHERE t.user_id = :user_id AND c.class_id = :class_id"
        );

        $stmt->execute([
            'user_id' => getUserId(),
            'class_id' => $classId
        ]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if the logged-in teacher has access to a specific period
 */
function teacherHasAccessToPeriod($periodId) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return false; // Cannot verify access without DB connection
        }

        // Admins have access to all periods
        if (hasRole(ROLE_ADMIN)) {
            return true;
        }

        $stmt = $pdo->prepare(
            "SELECT p.period_id
             FROM periods p
             JOIN classes c ON p.class_id = c.class_id
             JOIN teachers t ON c.teacher_id = t.teacher_id
             WHERE t.user_id = :user_id AND p.period_id = :period_id"
        );

        $stmt->execute([
            'user_id' => getUserId(),
            'period_id' => $periodId
        ]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if the logged-in teacher has access to a specific enrollment
 */
function teacherHasAccessToEnrollment($enrollId) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return false; // Cannot verify access without DB connection
        }

        // Admins have access to all enrollments
        if (hasRole(ROLE_ADMIN)) {
            return true;
        }

        $stmt = $pdo->prepare(
            "SELECT e.enroll_id
             FROM enrollments e
             JOIN classes c ON e.class_id = c.class_id
             JOIN teachers t ON c.teacher_id = t.teacher_id
             WHERE t.user_id = :user_id AND e.enroll_id = :enroll_id"
        );

        $stmt->execute([
            'user_id' => getUserId(),
            'enroll_id' => $enrollId
        ]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if the logged-in student owns a specific enrollment
 */
function studentOwnsEnrollment($enrollId) {
    try {
        $pdo = getDBConnection();
        if (!$pdo) {
            return false; // Cannot verify ownership without DB connection
        }

        $stmt = $pdo->prepare(
            "SELECT e.enroll_id
             FROM enrollments e
             JOIN students s ON e.student_id = s.student_id
             WHERE s.user_id = :user_id AND e.enroll_id = :enroll_id"
        );

        $stmt->execute([
            'user_id' => getUserId(),
            'enroll_id' => $enrollId
        ]);

        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
