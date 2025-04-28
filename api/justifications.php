<?php /** @noinspection DuplicatedCode */
/**
 * Justifications API Endpoint
 *
 * Handles CRUD operations for absence justifications via AJAX requests.
 * Returns JSON responses for client-side processing.
 * Access control based on user role: students can submit, teachers can approve.
 *
 * File path: /api/justifications.php
 *
 * Functions:
 * - submitJustification(): void - Creates a new justification submitted by a student with optional file attachment
 * - approveJustification(): void - Updates an existing justification as approved/rejected by teacher with optional rejection reason
 * - getJustifications(): void - Retrieves justifications based on user role with appropriate filtering
 * - getJustificationDetails(): void - Gets detailed information about a specific justification including all related data
 * - handleJustificationFileUpload(int $attId): bool - Handles file upload for justification attachment with validation
 * - teacherHasAccessToJustification(int $attId): bool - Verifies if a teacher has access to specific justification through class assignment
 * - studentOwnsJustification(int $attId): bool - Verifies if a student owns the justification through enrollment
 * - parentHasAccessToJustification(int $attId): bool - Verifies if a parent has access to the justification through student relationship
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (hasRole(2)) {
    require_once '../teacher/teacher_functions.php';
} elseif (hasRole(3)) {
    require_once '../student/student_functions.php';
} elseif (hasRole(4)) {
    require_once '../parent/parent_functions.php';
}

header('Content-Type: application/json');

if (!isLoggedIn()) {
    sendJsonErrorResponse('Unauthorized access', 403, 'justifications.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    try {
        $requestData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR) ?? [];
    } catch (JsonException $e) {
        sendJsonErrorResponse('Invalid JSON data', 400, 'justifications.php');
    }

    $providedToken = $requestData['csrf_token'] ?? null;

    if (!$providedToken || !verifyCSRFToken($providedToken)) {
        sendJsonErrorResponse('Invalid security token', 403, 'justifications.php');
    }
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'submit':
            if (!hasRole(3)) {
                sendJsonErrorResponse('Only students can submit justifications', 403, 'justifications.php');
            }
            submitJustification();
            break;

        case 'approve':
            if (!hasRole(2) && !hasRole(1)) {
                sendJsonErrorResponse('Only teachers can approve justifications', 403, 'justifications.php');
            }
            approveJustification();
            break;

        case 'get':
            getJustifications();
            break;

        case 'details':
            getJustificationDetails();
            break;

        default:
            sendJsonErrorResponse('Invalid action specified', 400, 'justifications.php');
    }
} catch (Exception $e) {
    error_log('API Error (justifications.php): ' . $e->getMessage());
    sendJsonErrorResponse('Server error', 500, 'justifications.php');
}

/**
 * Accessible to students only. Processes JSON data from request body with attendance_id and justification text. Can also process a file upload for supporting documentation.
 *
 * @return void Outputs JSON response directly
 */
function submitJustification(): void
{
    try {
        $requestData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

        if (empty($requestData['attendance_id']) || empty($requestData['justification'])) {
            sendJsonErrorResponse('Missing required fields', 400, 'justifications.php');
        }

        $attId = filter_var($requestData['attendance_id'], FILTER_VALIDATE_INT);
        $justification = trim($requestData['justification']);

        if ($attId === false || $attId <= 0) {
            sendJsonErrorResponse('Invalid attendance ID', 400, 'justifications.php');
        }

        if (!studentOwnsJustification($attId)) {
            sendJsonErrorResponse('You do not have permission to justify this absence', 403, 'justifications.php');
        }

        $pdo = safeGetDBConnection('justifications.php');
        if ($pdo === null) {
            sendJsonErrorResponse('Database connection error', 500, 'justifications.php');
        }

        $stmt = $pdo->prepare("
            SELECT a.status, a.justification, a.approved 
            FROM attendance a
            WHERE a.att_id = :att_id
        ");
        $stmt->execute(['att_id' => $attId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            sendJsonErrorResponse('Attendance record not found', 404, 'justifications.php');
        }

        if ($attendance['status'] === 'P') {
            sendJsonErrorResponse('Cannot justify presence', 400, 'justifications.php');
        }

        if ($attendance['approved'] !== null) {
            sendJsonErrorResponse('This absence already has a processed justification', 400, 'justifications.php');
        }

        $stmt = $pdo->prepare("
            UPDATE attendance
            SET justification = :justification, approved = NULL
            WHERE att_id = :att_id
        ");

        $success = $stmt->execute([
            'justification' => $justification,
            'att_id' => $attId
        ]);

        if (!$success) {
            sendJsonErrorResponse('Failed to submit justification', 500, 'justifications.php');
        }

        $fileUploaded = false;
        if (isset($_FILES['justification_file']) && $_FILES['justification_file']['error'] === UPLOAD_ERR_OK) {
            $fileUploaded = handleJustificationFileUpload($attId);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Justification submitted successfully',
            'file_uploaded' => $fileUploaded
        ], JSON_THROW_ON_ERROR);

    } catch (JsonException) {
        sendJsonErrorResponse('Invalid request format', 400, 'justifications.php');
    } catch (PDOException) {
        sendJsonErrorResponse('Database error', 500, 'justifications.php');
    } catch (Exception) {
        sendJsonErrorResponse('An unexpected error occurred', 500, 'justifications.php');
    }
}

/**
 * Accessible to teachers and administrators only. Processes JSON data from request body with attendance_id, approved status, and reject_reason (if not approved).
 *
 * @return void Outputs JSON response directly
 */
function approveJustification(): void
{
    try {
        $requestData = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($requestData['attendance_id'], $requestData['approved'])) {
            sendJsonErrorResponse('Missing required fields', 400, 'justifications.php');
        }

        $attId = filter_var($requestData['attendance_id'], FILTER_VALIDATE_INT);
        $approved = filter_var($requestData['approved'], FILTER_VALIDATE_BOOLEAN);
        $rejectReason = isset($requestData['reject_reason']) ? trim($requestData['reject_reason']) : null;

        if ($attId === false || $attId <= 0) {
            sendJsonErrorResponse('Invalid attendance ID', 400, 'justifications.php');
        }

        if (!$approved && empty($rejectReason)) {
            sendJsonErrorResponse('Reason for rejection is required', 400, 'justifications.php');
        }

        if (!hasRole(1) && !teacherHasAccessToJustification($attId)) {
            sendJsonErrorResponse('You do not have permission to approve this justification', 403, 'justifications.php');
        }

        $pdo = safeGetDBConnection('justifications.php');
        if ($pdo === null) {
            sendJsonErrorResponse('Database connection error', 500, 'justifications.php');
        }

        $stmt = $pdo->prepare("
            SELECT a.justification
            FROM attendance a
            WHERE a.att_id = :att_id
        ");
        $stmt->execute(['att_id' => $attId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendance) {
            sendJsonErrorResponse('Attendance record not found', 404, 'justifications.php');
        }

        if (empty($attendance['justification'])) {
            sendJsonErrorResponse('This absence has no justification to approve', 400, 'justifications.php');
        }

        $stmt = $pdo->prepare("
            UPDATE attendance
            SET approved = :approved, reject_reason = :reject_reason
            WHERE att_id = :att_id
        ");

        $success = $stmt->execute([
            'approved' => $approved ? 1 : 0,
            'reject_reason' => $approved ? null : $rejectReason,
            'att_id' => $attId
        ]);

        if (!$success) {
            sendJsonErrorResponse('Failed to update justification status', 500, 'justifications.php');
        }

        echo json_encode([
            'success' => true,
            'message' => $approved ? 'Justification approved' : 'Justification rejected',
        ], JSON_THROW_ON_ERROR);

    } catch (JsonException) {
        sendJsonErrorResponse('Invalid request format', 400, 'justifications.php');
    } catch (PDOException) {
        sendJsonErrorResponse('Database error', 500, 'justifications.php');
    } catch (Exception) {
        sendJsonErrorResponse('An unexpected error occurred', 500, 'justifications.php');
    }
}

/**
 * Returns filtered results with formatted dates and status labels.
 *
 * For students: their own justifications
 * For teachers: justifications from their classes
 * For administrators: all justifications
 * For parents: justifications of their children
 *
 * @return void Outputs JSON response directly
 */
function getJustifications(): void
{
    try {
        $pdo = safeGetDBConnection('justifications.php');
        if ($pdo === null) {
            sendJsonErrorResponse('Database connection error', 500, 'justifications.php');
        }
        $justifications = [];

        if (hasRole(1)) {
            $stmt = $pdo->query("
                SELECT 
                    a.att_id,
                    a.status,
                    a.justification,
                    a.approved,
                    a.reject_reason,
                    a.justification_file,
                    p.period_date,
                    p.period_label,
                    s.first_name,
                    s.last_name,
                    s.student_id,
                    c.class_code,
                    c.title AS class_title,
                    subj.name AS subject_name
                FROM attendance a
                JOIN periods p ON a.period_id = p.period_id
                JOIN enrollments e ON a.enroll_id = e.enroll_id
                JOIN students s ON e.student_id = s.student_id
                JOIN classes c ON e.class_id = c.class_id
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                JOIN subjects subj ON cs.subject_id = subj.subject_id
                WHERE a.justification IS NOT NULL
                ORDER BY p.period_date DESC, s.last_name, s.first_name
            ");
            $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (hasRole(2)) {
            $teacherId = getTeacherId();
            if (!$teacherId) {
                sendJsonErrorResponse('Teacher profile not found', 404, 'justifications.php');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    a.att_id,
                    a.status,
                    a.justification,
                    a.approved,
                    a.reject_reason,
                    a.justification_file,
                    p.period_date,
                    p.period_label,
                    s.first_name,
                    s.last_name,
                    s.student_id,
                    c.class_code,
                    c.title AS class_title,
                    subj.name AS subject_name
                FROM attendance a
                JOIN periods p ON a.period_id = p.period_id
                JOIN enrollments e ON a.enroll_id = e.enroll_id
                JOIN students s ON e.student_id = s.student_id
                JOIN classes c ON e.class_id = c.class_id
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                JOIN subjects subj ON cs.subject_id = subj.subject_id
                WHERE a.justification IS NOT NULL
                AND cs.teacher_id = :teacher_id
                ORDER BY a.approved, p.period_date DESC, s.last_name, s.first_name
            ");
            $stmt->execute(['teacher_id' => $teacherId]);
            $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (hasRole(3)) {
            $studentId = getStudentId();
            if (!$studentId) {
                sendJsonErrorResponse('Student profile not found', 404, 'justifications.php');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    a.att_id,
                    a.status,
                    a.justification,
                    a.approved,
                    a.reject_reason,
                    a.justification_file,
                    p.period_date,
                    p.period_label,
                    c.class_code,
                    c.title AS class_title,
                    subj.name AS subject_name
                FROM attendance a
                JOIN periods p ON a.period_id = p.period_id
                JOIN enrollments e ON a.enroll_id = e.enroll_id
                JOIN classes c ON e.class_id = c.class_id
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                JOIN subjects subj ON cs.subject_id = subj.subject_id
                WHERE e.student_id = :student_id
                AND a.justification IS NOT NULL
                ORDER BY p.period_date DESC
            ");
            $stmt->execute(['student_id' => $studentId]);
            $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (hasRole(4)) {
            $parentId = getParentId();
            if (!$parentId) {
                sendJsonErrorResponse('Parent profile not found', 404, 'justifications.php');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    a.att_id,
                    a.status,
                    a.justification,
                    a.approved,
                    a.reject_reason,
                    a.justification_file,
                    p.period_date,
                    p.period_label,
                    s.first_name,
                    s.last_name,
                    s.student_id,
                    c.class_code,
                    c.title AS class_title,
                    subj.name AS subject_name
                FROM attendance a
                JOIN periods p ON a.period_id = p.period_id
                JOIN enrollments e ON a.enroll_id = e.enroll_id
                JOIN students s ON e.student_id = s.student_id
                JOIN classes c ON e.class_id = c.class_id
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                JOIN subjects subj ON cs.subject_id = subj.subject_id
                JOIN student_parent sp ON s.student_id = sp.student_id
                WHERE sp.parent_id = :parent_id
                AND a.justification IS NOT NULL
                ORDER BY p.period_date DESC, s.last_name, s.first_name
            ");
            $stmt->execute(['parent_id' => $parentId]);
            $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($justifications as &$justification) {
            if ($justification['status'] === 'A') {
                $justification['status_label'] = 'Odsoten';
            } elseif ($justification['status'] === 'L') {
                $justification['status_label'] = 'Zamuda';
            }

            if ($justification['approved'] === NULL) {
                $justification['approval_status'] = 'pending';
                $justification['approval_label'] = 'V obdelavi';
            } elseif ($justification['approved'] == 1) {
                $justification['approval_status'] = 'approved';
                $justification['approval_label'] = 'Opravičeno';
            } else {
                $justification['approval_status'] = 'rejected';
                $justification['approval_label'] = 'Zavrnjeno';
            }

            $date = null;
            try {
                $date = new DateTime($justification['period_date']);
                $justification['formatted_date'] = $date->format('d.m.Y');
            } catch (Exception $e) {
                $justification['formatted_date'] = $justification['period_date'] ?? 'N/A';
                error_log('Date formatting error (justifications.php): ' . $e->getMessage());
            }
        }

        unset($justification);

        echo json_encode([
            'success' => true,
            'justifications' => $justifications
        ], JSON_THROW_ON_ERROR);

    } catch (PDOException) {
        sendJsonErrorResponse('Database error', 500, 'justifications.php');
    } catch (Exception) {
        sendJsonErrorResponse('An unexpected error occurred', 500, 'justifications.php');
    }
}

/**
 * Retrieves complete details about a justification by ID, including student, class, period, teacher information, and status. Access is role-based.
 *
 * @return void Outputs JSON response directly
 */
function getJustificationDetails(): void
{
    try {
        if (!isset($_GET['id'])) {
            sendJsonErrorResponse('Missing justification ID', 400, 'justifications.php');
        }

        $attId = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if ($attId === false || $attId <= 0) {
            sendJsonErrorResponse('Invalid justification ID', 400, 'justifications.php');
        }

        $hasAccess = false;

        if (hasRole(1)) {
            $hasAccess = true;
        } elseif (hasRole(2)) {
            $hasAccess = teacherHasAccessToJustification($attId);
        } elseif (hasRole(3)) {
            $hasAccess = studentOwnsJustification($attId);
        } elseif (hasRole(4)) {
            $hasAccess = parentHasAccessToJustification($attId);
        }

        if (!$hasAccess) {
            sendJsonErrorResponse('You do not have permission to view this justification', 403, 'justifications.php');
        }

        $pdo = safeGetDBConnection('justifications.php');
        if ($pdo === null) {
            sendJsonErrorResponse('Database connection error', 500, 'justifications.php');
        }

        $stmt = $pdo->prepare("
            SELECT 
                a.att_id,
                a.status,
                a.justification,
                a.approved,
                a.reject_reason,
                a.justification_file,
                p.period_date,
                p.period_label,
                s.first_name,
                s.last_name,
                s.student_id,
                c.class_code,
                c.title AS class_title,
                subj.name AS subject_name,
                t.teacher_id,
                u.username AS teacher_username
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN students s ON e.student_id = s.student_id
            JOIN classes c ON e.class_id = c.class_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects subj ON cs.subject_id = subj.subject_id
            JOIN teachers t ON cs.teacher_id = t.teacher_id
            JOIN users u ON t.user_id = u.user_id
            WHERE a.att_id = :att_id
        ");
        $stmt->execute(['att_id' => $attId]);

        $justification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$justification) {
            sendJsonErrorResponse('Justification not found', 404, 'justifications.php');
        }

        if ($justification['status'] === 'A') {
            $justification['status_label'] = 'Odsoten';
        } elseif ($justification['status'] === 'L') {
            $justification['status_label'] = 'Zamuda';
        }

        if ($justification['approved'] === NULL) {
            $justification['approval_status'] = 'pending';
            $justification['approval_label'] = 'V obdelavi';
        } elseif ($justification['approved'] == 1) {
            $justification['approval_status'] = 'approved';
            $justification['approval_label'] = 'Opravičeno';
        } else {
            $justification['approval_status'] = 'rejected';
            $justification['approval_label'] = 'Zavrnjeno';
        }

        try {
            $date = new DateTime($justification['period_date']);
            $justification['formatted_date'] = $date->format('d.m.Y');
        } catch (Exception $e) {
            $justification['formatted_date'] = $justification['period_date'] ?? 'N/A';
            error_log('Date formatting error (justifications.php): ' . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'justification' => $justification
        ], JSON_THROW_ON_ERROR);

    } catch (PDOException) {
        sendJsonErrorResponse('Database error', 500, 'justifications.php');
    } catch (Exception) {
        sendJsonErrorResponse('An unexpected error occurred', 500, 'justifications.php');
    }
}

/**
 * Processes file upload for supporting documentation of an absence justification.
 * Validates file type (PDF, JPEG, PNG), creates directory if needed, generates unique filename, and updates database with file reference.
 *
 * @param int $attId Attendance ID to attach the file to
 * @return bool True if file was uploaded successfully, false otherwise
 */
function handleJustificationFileUpload(int $attId): bool
{
    try {
        $uploadDir = '../uploads/justifications/';
        if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
        }

        if (!isset($_FILES['justification_file']) || $_FILES['justification_file']['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $file = $_FILES['justification_file'];

        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$fileInfo) {
            error_log('File info error (justifications.php): Could not open file info');
            return false;
        }

        $detectedType = finfo_file($fileInfo, $file['tmp_name']);
        finfo_close($fileInfo);

        if (!in_array($detectedType, $allowedTypes, true)) {
            return false;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = 'justification_' . $attId . '_' . uniqid('', true) . '.' . $extension;
        $uploadPath = $uploadDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $pdo = safeGetDBConnection('justifications.php', false);
            if ($pdo === null) {
                error_log('Database error (justifications.php/handleJustificationFileUpload): Could not connect to database');
                return false;
            }

            $stmt = $pdo->prepare("
                UPDATE attendance
                SET justification_file = :justification_file
                WHERE att_id = :att_id
            ");

            return $stmt->execute([
                'justification_file' => $newFilename,
                'att_id' => $attId
            ]);
        }

        return false;
    } catch (Exception $e) {
        error_log('File upload error (justifications.php): ' . $e->getMessage());
        return false;
    }
}

/**
 * Checks if the currently logged-in teacher has permission to access a specific justification based on class-subject assignment.
 *
 * @param int $attId Attendance ID
 * @return bool True if teacher has access, false otherwise
 */
function teacherHasAccessToJustification(int $attId): bool
{
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) {
            return false;
        }

        $pdo = safeGetDBConnection('justifications.php', false);
        if ($pdo === null) {
            error_log('Database error (justifications.php/teacherHasAccessToJustification): Could not connect to database');
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            WHERE a.att_id = :att_id
            AND cs.teacher_id = :teacher_id
        ");

        $stmt->execute([
            'att_id' => $attId,
            'teacher_id' => $teacherId
        ]);

        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        error_log('Access check error (justifications.php): ' . $e->getMessage());
        return false;
    }
}

/**
 * Checks if the currently logged-in student is the owner of  a specific attendance record/justification.
 *
 * @param int $attId Attendance ID
 * @return bool True if student owns the justification, false otherwise
 */
function studentOwnsJustification(int $attId): bool
{
    try {
        $studentId = getStudentId();
        if (!$studentId) {
            return false;
        }

        $pdo = safeGetDBConnection('justifications.php', false);
        if ($pdo === null) {
            error_log('Database error (justifications.php/studentOwnsJustification): Could not connect to database');
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            WHERE a.att_id = :att_id
            AND e.student_id = :student_id
        ");

        $stmt->execute([
            'att_id' => $attId,
            'student_id' => $studentId
        ]);

        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        error_log('Access check error (justifications.php): ' . $e->getMessage());
        return false;
    }
}

/**
 * Checks if the currently logged-in parent has permission to access a specific justification through the student-parent relationship.
 *
 * @param int $attId Attendance ID
 * @return bool True if parent has access to the justification, false otherwise
 */
function parentHasAccessToJustification(int $attId): bool
{
    try {
        $parentId = getParentId();
        if (!$parentId) {
            return false;
        }

        $pdo = safeGetDBConnection('justifications.php', false);
        if ($pdo === null) {
            error_log('Database error (justifications.php/parentHasAccessToJustification): Could not connect to database');
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN student_parent sp ON e.student_id = sp.student_id
            WHERE a.att_id = :att_id
            AND sp.parent_id = :parent_id
        ");

        $stmt->execute([
            'att_id' => $attId,
            'parent_id' => $parentId
        ]);

        return $stmt->fetchColumn() !== false;
    } catch (Exception $e) {
        error_log('Access check error (justifications.php): ' . $e->getMessage());
        return false;
    }
}
