<?php
/**
 * Attendance API Endpoint
 *
 * Handles CRUD operations for attendance data via AJAX requests.
 * Returns JSON responses for client-side processing.
 * Implements role-based access control for different attendance management functions.
 *
 * Main functions:
 * - handleAddPeriod(): void - Creates a new period for a class with initial attendance records
 * - handleUpdatePeriod(): void - Updates date and label information for an existing period
 * - handleDeletePeriod(): void - Deletes a period and all associated attendance records
 * - handleSaveAttendance(): void - Saves attendance status for a single student
 * - handleBulkAttendance(): void - Saves attendance status for multiple students at once
 * - handleJustifyAbsence(): void - Records or approves absence justification based on user role
 * - handleGetStudentAttendance(): void - Gets attendance summary and statistics for a student
 *
 * All functions process data from $_POST and output JSON responses directly.
 * Role permissions: Admin and Teacher can manage all attendance data.
 * Students can submit justifications and view their own attendance.
 * Parents can view their children's attendance.
 *
 * File path: /api/attendance.php
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../teacher/teacher_functions.php';
require_once '../student/student_functions.php';
require_once '../parent/parent_functions.php';

header('Content-Type: application/json');

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') sendJsonErrorResponse('Only AJAX requests are allowed', 403, 'attendance.php');

// Check if user is logged in
if (!isLoggedIn()) sendJsonErrorResponse('Niste prijavljeni', 401, 'attendance.php');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'addPeriod':
        requireRole(ROLE_ADMIN) || requireRole(ROLE_TEACHER);
        handleAddPeriod();
        break;

    case 'updatePeriod':
        requireRole(ROLE_ADMIN) || requireRole(ROLE_TEACHER);
        handleUpdatePeriod();
        break;

    case 'deletePeriod':
        requireRole(ROLE_ADMIN) || requireRole(ROLE_TEACHER);
        handleDeletePeriod();
        break;

    case 'saveAttendance':
        requireRole(ROLE_ADMIN) || requireRole(ROLE_TEACHER);
        handleSaveAttendance();
        break;

    case 'bulkAttendance':
        requireRole(ROLE_ADMIN) || requireRole(ROLE_TEACHER);
        handleBulkAttendance();
        break;

    case 'justifyAbsence':
        // No requireRole() here as it depends on user role - handled inside the function
        handleJustifyAbsence();
        break;

    case 'getStudentAttendance':
        // No requireRole() here as it can be accessed by multiple roles - checks inside the function
        handleGetStudentAttendance();
        break;

    default:
        sendJsonErrorResponse('Neveljavna zahteva', 400, 'attendance.php');
}

/**
 * Handles adding a new period to a class with initial attendance records
 * Uses teacher_functions.php: addPeriod()
 *
 * @return void Outputs JSON response directly
 */
function handleAddPeriod(): void
{
    try {
        if (!isset($_POST['class_subject_id'], $_POST['period_date'], $_POST['period_label'])) sendJsonErrorResponse('Manjkajo zahtevani podatki', 400, 'attendance.php/handleAddPeriod');

        $classSubjectId = filter_var($_POST['class_subject_id'], FILTER_VALIDATE_INT);
        $periodDate = htmlspecialchars($_POST['period_date'], ENT_QUOTES, 'UTF-8');
        $periodLabel = htmlspecialchars($_POST['period_label'], ENT_QUOTES, 'UTF-8');

        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $periodDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $periodDate) sendJsonErrorResponse('Neveljaven format datuma', 400, 'attendance.php/handleAddPeriod');

        // Check teacher's access to the class
        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega razreda', 403, 'attendance.php/handleAddPeriod');

        // Use centralized function from teacher_functions.php
        $periodId = addPeriod($classSubjectId, $periodDate, $periodLabel);

        if (!$periodId) sendJsonErrorResponse('Napaka pri dodajanju obdobja', 500, 'attendance.php/handleAddPeriod');

        try {
            echo json_encode([
                'success' => true,
                'message' => 'Obdobje uspešno dodano',
                'period_id' => $periodId
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/handleAddPeriod): ' . $e->getMessage());
            sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleAddPeriod');
        }
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        sendJsonErrorResponse('Napaka pri dodajanju obdobja', 500, 'attendance.php/handleAddPeriod');
    }
}

/**
 * Handles updating date and label information for an existing period
 *
 * @return void Outputs JSON response directly
 */
function handleUpdatePeriod(): void
{
    try {
        if (!isset($_POST['period_id'], $_POST['period_date'], $_POST['period_label'])) sendJsonErrorResponse('Manjkajo zahtevani podatki', 400, 'attendance.php/handleUpdatePeriod');

        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);
        $periodDate = htmlspecialchars($_POST['period_date'], ENT_QUOTES, 'UTF-8');
        $periodLabel = htmlspecialchars($_POST['period_label'], ENT_QUOTES, 'UTF-8');

        // Validate date format
        $dateObj = DateTime::createFromFormat('Y-m-d', $periodDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $periodDate) sendJsonErrorResponse('Neveljaven format datuma', 400, 'attendance.php/handleUpdatePeriod');

        // Check access through the teacher functions
        if (hasRole(ROLE_TEACHER)) {
            $pdo = safeGetDBConnection('check_period_access', false);
            if (!$pdo) sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/handleUpdatePeriod');

            $stmt = $pdo->prepare("
                SELECT cs.class_subject_id 
                FROM periods p
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                WHERE p.period_id = :period_id
            ");
            $stmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
            $stmt->execute();

            $classSubjectId = $stmt->fetchColumn();
            if (!$classSubjectId || !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleUpdatePeriod');
        }

        $pdo = safeGetDBConnection('update_period');

        // Check for duplicates
        $checkStmt = $pdo->prepare("
            SELECT class_subject_id FROM periods 
            WHERE period_id = :period_id
        ");
        $checkStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $checkStmt->execute();

        $classSubjectId = $checkStmt->fetchColumn();
        if (!$classSubjectId) sendJsonErrorResponse('Obdobje ne obstaja', 404, 'attendance.php/handleUpdatePeriod');

        $checkDuplicateStmt = $pdo->prepare("
            SELECT COUNT(*) FROM periods 
            WHERE class_subject_id = :class_subject_id 
            AND period_date = :period_date 
            AND period_label = :period_label
            AND period_id != :period_id
        ");
        $checkDuplicateStmt->bindParam(':class_subject_id', $classSubjectId, PDO::PARAM_INT);
        $checkDuplicateStmt->bindParam(':period_date', $periodDate);
        $checkDuplicateStmt->bindParam(':period_label', $periodLabel);
        $checkDuplicateStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $checkDuplicateStmt->execute();

        if ($checkDuplicateStmt->fetchColumn() > 0) sendJsonErrorResponse('To obdobje že obstaja za ta razred in predmet', 400, 'attendance.php/handleUpdatePeriod');

        // Update the period
        $updateStmt = $pdo->prepare("
            UPDATE periods 
            SET period_date = :period_date, period_label = :period_label 
            WHERE period_id = :period_id
        ");
        $updateStmt->bindParam(':period_date', $periodDate);
        $updateStmt->bindParam(':period_label', $periodLabel);
        $updateStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);

        if (!$updateStmt->execute()) sendJsonErrorResponse('Napaka pri posodabljanju obdobja', 500, 'attendance.php/handleUpdatePeriod');

        try {
            echo json_encode([
                'success' => true,
                'message' => 'Obdobje uspešno posodobljeno'
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/handleUpdatePeriod): ' . $e->getMessage());
            sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleUpdatePeriod');
        }
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        sendJsonErrorResponse('Napaka pri posodabljanju obdobja', 500, 'attendance.php/handleUpdatePeriod');
    }
}

/**
 * Handles deleting a period and all associated attendance records
 *
 * @return void Outputs JSON response directly
 */
function handleDeletePeriod(): void
{
    try {
        if (!isset($_POST['period_id'])) sendJsonErrorResponse('Manjka ID obdobja', 400, 'attendance.php/handleDeletePeriod');

        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);

        // Check access through the teacher functions
        if (hasRole(ROLE_TEACHER)) {
            $pdo = safeGetDBConnection('check_period_access', false);
            if (!$pdo) sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/handleDeletePeriod');

            $stmt = $pdo->prepare("
                SELECT cs.class_subject_id 
                FROM periods p
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                WHERE p.period_id = :period_id
            ");
            $stmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
            $stmt->execute();

            $classSubjectId = $stmt->fetchColumn();
            if (!$classSubjectId || !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleDeletePeriod');
        }

        $pdo = safeGetDBConnection('delete_period');
        $pdo->beginTransaction();

        // Check if period exists
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM periods 
            WHERE period_id = :period_id
        ");
        $checkStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() == 0) {
            $pdo->rollBack();
            sendJsonErrorResponse('Obdobje ne obstaja', 404, 'attendance.php/handleDeletePeriod');
        }

        // Delete attendance records first
        $deleteAttStmt = $pdo->prepare("
            DELETE FROM attendance 
            WHERE period_id = :period_id
        ");
        $deleteAttStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        if (!$deleteAttStmt->execute()) {
            $pdo->rollBack();
            sendJsonErrorResponse('Napaka pri brisanju povezanih zapisov prisotnosti', 500, 'attendance.php/handleDeletePeriod');
        }

        // Delete the period
        $deletePeriodStmt = $pdo->prepare("
            DELETE FROM periods 
            WHERE period_id = :period_id
        ");
        $deletePeriodStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        if (!$deletePeriodStmt->execute()) {
            $pdo->rollBack();
            sendJsonErrorResponse('Napaka pri brisanju obdobja', 500, 'attendance.php/handleDeletePeriod');
        }

        $pdo->commit();

        try {
            echo json_encode([
                'success' => true,
                'message' => 'Obdobje uspešno izbrisano'
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/handleDeletePeriod): ' . $e->getMessage());
            sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleDeletePeriod');
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        logDBError($e->getMessage());
        sendJsonErrorResponse('Napaka pri brisanju obdobja', 500, 'attendance.php/handleDeletePeriod');
    }
}

/**
 * Handles saving attendance status for a single student
 * Uses teacher_functions.php: saveAttendance()
 *
 * @return void Outputs JSON response directly
 */
function handleSaveAttendance(): void
{
    try {
        if (!isset($_POST['enroll_id'], $_POST['period_id'], $_POST['status'])) sendJsonErrorResponse('Manjkajo zahtevani podatki', 400, 'attendance.php/handleSaveAttendance');

        $enrollId = filter_var($_POST['enroll_id'], FILTER_VALIDATE_INT);
        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);
        $status = htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8');

        if (!in_array($status, ['P', 'A', 'L'])) sendJsonErrorResponse('Neveljaven status prisotnosti', 400, 'attendance.php/handleSaveAttendance');

        // Check teacher's access
        if (hasRole(ROLE_TEACHER)) {
            // Check period access
            $pdo = safeGetDBConnection('check_access', false);
            if ($pdo == null) sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/handleSaveAttendance');
            $stmt = $pdo->prepare("
                SELECT cs.class_subject_id 
                FROM periods p
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                WHERE p.period_id = :period_id
            ");

            $stmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
            $stmt->execute();
            $classSubjectId = $stmt->fetchColumn();

            if (!$classSubjectId || !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleSaveAttendance');

            // Check enrollment access
            $stmt = $pdo->prepare("
                SELECT cs.class_subject_id 
                FROM enrollments e
                JOIN class_subjects cs ON e.class_id = cs.class_id
                WHERE e.enroll_id = :enroll_id
            ");
            $stmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
            $stmt->execute();
            $classSubjectIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $hasAccess = false;
            foreach ($classSubjectIds as $csId) if (teacherHasAccessToClassSubject($csId)) {
                $hasAccess = true;
                break;
            }

            if (!$hasAccess) sendJsonErrorResponse('Nimate dostopa do tega vpisa študenta', 403, 'attendance.php/handleSaveAttendance');
        }

        // Use centralized function from teacher_functions.php
        $result = saveAttendance($enrollId, $periodId, $status);

        if (!$result) sendJsonErrorResponse('Napaka pri shranjevanju prisotnosti', 500, 'attendance.php/handleSaveAttendance');

        // Check if this was an update or insert
        $pdo = safeGetDBConnection('check_attendance');
        $stmt = $pdo->prepare("
            SELECT att_id FROM attendance 
            WHERE enroll_id = :enroll_id AND period_id = :period_id
        ");
        $stmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
        $stmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $stmt->execute();
        $attId = $stmt->fetchColumn();

        try {
            echo json_encode([
                'success' => true,
                'message' => 'Prisotnost uspešno ' . ($attId ? 'posodobljena' : 'zabeležena'),
                'mode' => $attId ? 'update' : 'insert',
                'att_id' => $attId
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/handleSaveAttendance): ' . $e->getMessage());
            sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleSaveAttendance');
        }
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        sendJsonErrorResponse('Napaka pri shranjevanju prisotnosti', 500, 'attendance.php/handleSaveAttendance');
    }
}

/**
 * Handles saving attendance status for multiple students at once
 *
 * @return void Outputs JSON response directly
 */
function handleBulkAttendance(): void
{
    try {
        if (!isset($_POST['period_id'], $_POST['attendance_data']) || !is_array($_POST['attendance_data'])) sendJsonErrorResponse('Manjkajo zahtevani podatki', 400, 'attendance.php/handleBulkAttendance');

        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);
        $attendanceData = $_POST['attendance_data'];

        // Check teacher's access to period
        if (hasRole(ROLE_TEACHER)) {
            $pdo = safeGetDBConnection('check_access', false);
            if ($pdo == null) sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/handleSaveAttendance');
            $stmt = $pdo->prepare("
                SELECT cs.class_subject_id 
                FROM periods p
                JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                WHERE p.period_id = :period_id
            ");
            $stmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
            $stmt->execute();
            $classSubjectId = $stmt->fetchColumn();

            if (!$classSubjectId || !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleBulkAttendance');
        }

        $successCount = 0;
        $failCount = 0;

        // Process each attendance record
        foreach ($attendanceData as $record) {
            if (!isset($record['enroll_id'], $record['status'])) {
                $failCount++;
                continue;
            }

            $enrollId = filter_var($record['enroll_id'], FILTER_VALIDATE_INT);
            $status = htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8');

            if (!in_array($status, ['P', 'A', 'L'])) {
                $failCount++;
                continue;
            }

            // Check teacher's access to enrollment
            if (hasRole(ROLE_TEACHER)) {
                $pdo = safeGetDBConnection('check_enrollment', false);
                $stmt = $pdo->prepare("
                    SELECT cs.class_subject_id 
                    FROM enrollments e
                    JOIN class_subjects cs ON e.class_id = cs.class_id
                    WHERE e.enroll_id = :enroll_id
                ");
                $stmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
                $stmt->execute();
                $classSubjectIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $hasAccess = false;
                foreach ($classSubjectIds as $csId) if (teacherHasAccessToClassSubject($csId)) {
                    $hasAccess = true;
                    break;
                }

                if (!$hasAccess) {
                    $failCount++;
                    continue;
                }
            }

            // Use centralized function from teacher_functions.php
            if (saveAttendance($enrollId, $periodId, $status)) $successCount++; else $failCount++;
        }

        try {
            echo json_encode([
                'success' => true,
                'message' => "Prisotnost uspešno posodobljena za $successCount učencev" .
                    ($failCount > 0 ? ", $failCount posodobitev ni uspelo" : "")
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/handleBulkAttendance): ' . $e->getMessage());
            sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleBulkAttendance');
        }
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        sendJsonErrorResponse('Napaka pri shranjevanju prisotnosti', 500, 'attendance.php/handleBulkAttendance');
    }
}

/**
 * Handles recording or approving absence justification based on user role
 *
 * @return void Outputs JSON response directly
 */
function handleJustifyAbsence(): void
{
    try {
        if (!isset($_POST['att_id'])) sendJsonErrorResponse('Manjka ID prisotnosti', 400, 'attendance.php/handleJustifyAbsence');

        $attId = filter_var($_POST['att_id'], FILTER_VALIDATE_INT);
        $justification = isset($_POST['justification']) ? htmlspecialchars($_POST['justification'], ENT_QUOTES, 'UTF-8') : null;
        $approved = isset($_POST['approved']) ? (bool)$_POST['approved'] : null;
        $rejectReason = isset($_POST['reject_reason']) ? htmlspecialchars($_POST['reject_reason'], ENT_QUOTES, 'UTF-8') : null;

        $pdo = safeGetDBConnection('check_attendance');
        if ($pdo == null) sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/handleSaveAttendance');
        $checkStmt = $pdo->prepare("
            SELECT a.enroll_id, a.period_id, a.status, a.justification, a.approved, e.student_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            WHERE a.att_id = :att_id
        ");
        $checkStmt->bindParam(':att_id', $attId, PDO::PARAM_INT);
        $checkStmt->execute();
        $attRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$attRecord) sendJsonErrorResponse('Zapis o prisotnosti ne obstaja', 404, 'attendance.php/handleJustifyAbsence');

        if (hasRole(ROLE_STUDENT)) {
            // Student submitting justification
            $studentId = getStudentId();
            if ($studentId !== $attRecord['student_id']) sendJsonErrorResponse('Nimate dovoljenja za opravičilo te odsotnosti', 403, 'attendance.php/handleJustifyAbsence');

            if ($attRecord['status'] !== 'A' && $attRecord['status'] !== 'L') sendJsonErrorResponse('Opravičilo se lahko doda samo za odsotnost ali zamudo', 400, 'attendance.php/handleJustifyAbsence');

            // Submit justification using student function
            $result = uploadJustification($attId, $justification);

            if (!$result) sendJsonErrorResponse('Napaka pri oddaji opravičila', 500, 'attendance.php/handleJustifyAbsence');

            try {
                echo json_encode([
                    'success' => true,
                    'message' => 'Opravičilo uspešno oddano'
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/handleJustifyAbsence): ' . $e->getMessage());
                sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleJustifyAbsence');
            }
        } elseif (hasRole(ROLE_TEACHER) || hasRole(ROLE_ADMIN)) {
            // Teacher/Admin approving or rejecting justification
            if (hasRole(ROLE_TEACHER)) {
                // Check if teacher has access to this period
                $stmt = $pdo->prepare("
                    SELECT cs.class_subject_id 
                    FROM periods p
                    JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
                    WHERE p.period_id = :period_id
                ");
                $stmt->bindParam(':period_id', $attRecord['period_id'], PDO::PARAM_INT);
                $stmt->execute();
                $classSubjectId = $stmt->fetchColumn();

                if (!$classSubjectId || !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleJustifyAbsence');
            }

            if ($approved === null) sendJsonErrorResponse('Manjka status odobritve', 400, 'attendance.php/handleJustifyAbsence');

            if ($approved === false && empty($rejectReason)) sendJsonErrorResponse('Pri zavrnitvi opravičila je potreben razlog', 400, 'attendance.php/handleJustifyAbsence');

            // Update approval status
            $updateStmt = $pdo->prepare("
                UPDATE attendance
                SET approved = :approved, reject_reason = :reject_reason
                WHERE att_id = :att_id
            ");
            $updateStmt->bindParam(':approved', $approved, PDO::PARAM_BOOL);
            $updateStmt->bindParam(':reject_reason', $rejectReason);
            $updateStmt->bindParam(':att_id', $attId, PDO::PARAM_INT);

            if (!$updateStmt->execute()) sendJsonErrorResponse('Napaka pri obdelavi opravičila', 500, 'attendance.php/handleJustifyAbsence');

            try {
                echo json_encode([
                    'success' => true,
                    'message' => $approved ? 'Opravičilo uspešno odobreno' : 'Opravičilo zavrnjeno'
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/handleJustifyAbsence): ' . $e->getMessage());
                sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleJustifyAbsence');
            }
        } else sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php/handleJustifyAbsence');
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi opravičila', 500, 'attendance.php/handleJustifyAbsence');
    }
}

/**
 * Handles getting attendance summary and statistics for a student
 * Uses student_functions.php: getStudentAttendance()
 *
 * @return void Outputs JSON response directly
 */
function handleGetStudentAttendance(): void
{
    try {
        if (!isset($_POST['student_id'])) sendJsonErrorResponse('Manjka ID učenca', 400, 'attendance.php/handleGetStudentAttendance');

        $studentId = filter_var($_POST['student_id'], FILTER_VALIDATE_INT);
        $dateFrom = isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from'], ENT_QUOTES, 'UTF-8') : null;
        $dateTo = isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to'], ENT_QUOTES, 'UTF-8') : null;

        // Validate date formats
        if ($dateFrom) {
            $dateObjFrom = DateTime::createFromFormat('Y-m-d', $dateFrom);
            if (!$dateObjFrom || $dateObjFrom->format('Y-m-d') !== $dateFrom) sendJsonErrorResponse('Neveljaven format začetnega datuma', 400, 'attendance.php/handleGetStudentAttendance');
        }

        if ($dateTo) {
            $dateObjTo = DateTime::createFromFormat('Y-m-d', $dateTo);
            if (!$dateObjTo || $dateObjTo->format('Y-m-d') !== $dateTo) sendJsonErrorResponse('Neveljaven format končnega datuma', 400, 'attendance.php/handleGetStudentAttendance');
        }

        // Check access permissions based on role
        if (hasRole(ROLE_STUDENT)) {
            $currentStudentId = getStudentId();
            if ($currentStudentId != $studentId) sendJsonErrorResponse('Nimate dovoljenja za ogled prisotnosti drugega učenca', 403, 'attendance.php/handleGetStudentAttendance');
        } elseif (hasRole(ROLE_PARENT)) if (!parentHasAccessToStudent($studentId)) sendJsonErrorResponse('Nimate dovoljenja za ogled prisotnosti tega učenca', 403, 'attendance.php/handleGetStudentAttendance');

        // Use centralized function from student_functions.php
        // Adjust the call based on the parameters
        $attendanceData = $dateFrom || $dateTo
            ? getStudentAttendance($studentId, $dateFrom, $dateTo)
            : getStudentAttendance($studentId);

        if (empty($attendanceData)) {
            try {
                echo json_encode([
                    'success' => true,
                    'records' => [],
                    'statistics' => [
                        'total' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'justified' => 0,
                        'present_percent' => 0,
                        'absent_percent' => 0,
                        'late_percent' => 0,
                    ]
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/handleGetStudentAttendance): ' . $e->getMessage());
                sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleGetStudentAttendance');
            }
            return;
        }

        // Calculate statistics
        $statistics = calculateAttendanceStats($attendanceData);

        try {
            echo json_encode([
                'success' => true,
                'records' => $attendanceData,
                'statistics' => $statistics
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/handleGetStudentAttendance): ' . $e->getMessage());
            sendJsonErrorResponse('Napaka pri obdelavi odgovora', 500, 'attendance.php/handleGetStudentAttendance');
        }
    } catch (PDOException $e) {
        logDBError($e->getMessage());
        sendJsonErrorResponse('Napaka pri pridobivanju podatkov o prisotnosti', 500, 'attendance.php/handleGetStudentAttendance');
    }
}
