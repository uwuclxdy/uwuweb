<?php
/**
 * Attendance API Endpoint
 *
 * Handles CRUD operations for attendance data via AJAX requests.
 * Returns JSON responses for client-side processing.
 * Implements role-based access control for different attendance management functions.
 *
 * /uwuweb/api/attendance.php
 *
 * Functions:
 * - handleAddPeriod(): void - Creates a new period for a class with initial attendance records
 * - handleUpdatePeriod(): void - Updates date and label information for an existing period
 * - handleDeletePeriod(): void - Deletes a period and all associated attendance records
 * - handleSaveAttendance(): void - Saves attendance status for a single student
 * - handleBulkAttendance(): void - Saves attendance status for multiple students at once
 * - handleJustifyAbsence(): void - Records or approves absence justification based on user role
 * - handleGetStudentAttendance(): void - Gets attendance summary and statistics for a student
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../teacher/teacher_functions.php';
require_once '../student/student_functions.php';
require_once '../parent/parent_functions.php';

header('Content-Type: application/json');

// Check if this is an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') sendJsonErrorResponse('Dovoljene so samo zahteve AJAX', 403, 'attendance.php');

// Check if user is logged in
if (!isLoggedIn()) sendJsonErrorResponse('Niste prijavljeni', 401, 'attendance.php');

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) sendJsonErrorResponse('Neveljaven varnostni žeton', 403, 'attendance.php');

// Determine which action to perform
$action = $_POST['action'] ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'addPeriod':
            if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
            handleAddPeriod();
            break;

        case 'updatePeriod':
            if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
            handleUpdatePeriod();
            break;

        case 'deletePeriod':
            if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
            handleDeletePeriod();
            break;

        case 'saveAttendance':
            if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
            handleSaveAttendance();
            break;

        case 'bulkAttendance':
            if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
            handleBulkAttendance();
            break;

        case 'justifyAbsence':
            // Permissions are checked inside the function based on role
            handleJustifyAbsence();
            break;

        case 'getStudentAttendance':
            // Permissions are checked inside the function based on role
            handleGetStudentAttendance();
            break;

        default:
            sendJsonErrorResponse('Neveljavna zahteva', 400, 'attendance.php');
    }
} catch (PDOException $e) {
    error_log('Database error (attendance.php): ' . $e->getMessage());
    sendJsonErrorResponse('Napaka baze podatkov', 500, 'attendance.php');
} catch (Exception $e) {
    error_log('API Error (attendance.php): ' . $e->getMessage());
    sendJsonErrorResponse('Napaka strežnika: ' . $e->getMessage(), 500, 'attendance.php');
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

        // Validate inputs
        if (!$classSubjectId || $classSubjectId <= 0) sendJsonErrorResponse('Neveljaven ID predmeta razreda', 400, 'attendance.php/handleAddPeriod');

        // Validate date format
        if (!validateDate($periodDate)) sendJsonErrorResponse('Neveljaven format datuma', 400, 'attendance.php/handleAddPeriod');

        if (empty($periodLabel)) sendJsonErrorResponse('Oznaka obdobja ne sme biti prazna', 400, 'attendance.php/handleAddPeriod');

        // Check teacher's access to the class
        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega razreda', 403, 'attendance.php/handleAddPeriod');

        // Check if period with same date and label already exists
        $pdo = safeGetDBConnection('check_period_exists');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM periods 
            WHERE class_subject_id = ? AND period_date = ? AND period_label = ?
        ");
        $stmt->execute([$classSubjectId, $periodDate, $periodLabel]);

        if ($stmt->fetchColumn() > 0) sendJsonErrorResponse('Obdobje s tem datumom in oznako že obstaja za ta razred', 400, 'attendance.php/handleAddPeriod');

        // Use centralized function from teacher_functions.php
        $periodId = addPeriod($classSubjectId, $periodDate, $periodLabel);

        if (!$periodId) sendJsonErrorResponse('Napaka pri dodajanju obdobja', 500, 'attendance.php/handleAddPeriod');

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Obdobje uspešno dodano',
            'data' => [
                'period_id' => $periodId,
                'class_subject_id' => $classSubjectId,
                'period_date' => $periodDate,
                'period_label' => $periodLabel
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('JSON Error in handleAddPeriod: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi podatkov JSON', 500, 'attendance.php/handleAddPeriod');
    } catch (PDOException $e) {
        error_log('Database Error in handleAddPeriod: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka baze podatkov pri dodajanju obdobja', 500, 'attendance.php/handleAddPeriod');
    } catch (Exception $e) {
        error_log('Error in handleAddPeriod: ' . $e->getMessage());
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

        // Validate inputs
        if (!$periodId || $periodId <= 0) sendJsonErrorResponse('Neveljaven ID obdobja', 400, 'attendance.php/handleUpdatePeriod');

        // Validate date format
        if (!validateDate($periodDate)) sendJsonErrorResponse('Neveljaven format datuma', 400, 'attendance.php/handleUpdatePeriod');

        if (empty($periodLabel)) sendJsonErrorResponse('Oznaka obdobja ne sme biti prazna', 400, 'attendance.php/handleUpdatePeriod');

        // Get database connection
        $pdo = safeGetDBConnection('update_period');

        // First check if period exists and get class_subject_id
        $stmt = $pdo->prepare("
            SELECT class_subject_id 
            FROM periods 
            WHERE period_id = ?
        ");
        $stmt->execute([$periodId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) sendJsonErrorResponse('Obdobje ni bilo najdeno', 404, 'attendance.php/handleUpdatePeriod');

        $classSubjectId = $result['class_subject_id'];

        // Check teacher's access to this period
        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleUpdatePeriod');

        // Check for duplicates
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM periods 
            WHERE class_subject_id = ? AND period_date = ? AND period_label = ? AND period_id != ?
        ");
        $stmt->execute([$classSubjectId, $periodDate, $periodLabel, $periodId]);

        if ($stmt->fetchColumn() > 0) sendJsonErrorResponse('Obdobje s tem datumom in oznako že obstaja za ta razred', 400, 'attendance.php/handleUpdatePeriod');

        // Update the period
        $stmt = $pdo->prepare("
            UPDATE periods 
            SET period_date = ?, period_label = ? 
            WHERE period_id = ?
        ");
        $stmt->execute([$periodDate, $periodLabel, $periodId]);

        if ($stmt->rowCount() === 0) sendJsonErrorResponse('Obdobje ni bilo posodobljeno ali pa ni bilo sprememb', 400, 'attendance.php/handleUpdatePeriod');

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Obdobje uspešno posodobljeno',
            'data' => [
                'period_id' => $periodId,
                'class_subject_id' => $classSubjectId,
                'period_date' => $periodDate,
                'period_label' => $periodLabel
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('JSON Error in handleUpdatePeriod: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi podatkov JSON', 500, 'attendance.php/handleUpdatePeriod');
    } catch (PDOException $e) {
        error_log('Database Error in handleUpdatePeriod: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka baze podatkov pri posodabljanju obdobja', 500, 'attendance.php/handleUpdatePeriod');
    } catch (Exception $e) {
        error_log('Error in handleUpdatePeriod: ' . $e->getMessage());
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

        // Validate input
        if (!$periodId || $periodId <= 0) sendJsonErrorResponse('Neveljaven ID obdobja', 400, 'attendance.php/handleDeletePeriod');

        // Get database connection
        $pdo = safeGetDBConnection('delete_period');

        // First check if period exists and get class_subject_id
        $stmt = $pdo->prepare("
            SELECT class_subject_id 
            FROM periods 
            WHERE period_id = ?
        ");
        $stmt->execute([$periodId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) sendJsonErrorResponse('Obdobje ni bilo najdeno', 404, 'attendance.php/handleDeletePeriod');

        $classSubjectId = $result['class_subject_id'];

        // Check teacher's access to this period
        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClassSubject($classSubjectId)) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleDeletePeriod');

        // Check if there are approved justifications for this period
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM attendance 
            WHERE period_id = ? AND approved = 1
        ");
        $stmt->execute([$periodId]);

        if ($stmt->fetchColumn() > 0 && !hasRole(ROLE_ADMIN)) sendJsonErrorResponse('Obdobja ni mogoče izbrisati, ker vsebuje odobrena opravičila. Kontaktirajte administratorja.', 400, 'attendance.php/handleDeletePeriod');

        // Begin transaction for deleting both attendance records and period
        $pdo->beginTransaction();

        try {
            // Delete attendance records first
            $stmt = $pdo->prepare("
                DELETE FROM attendance 
                WHERE period_id = ?
            ");
            $stmt->execute([$periodId]);

            // Then delete the period
            $stmt = $pdo->prepare("
                DELETE FROM periods 
                WHERE period_id = ?
            ");
            $stmt->execute([$periodId]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                sendJsonErrorResponse('Obdobje ni bilo izbrisano', 500, 'attendance.php/handleDeletePeriod');
            }

            // Commit the transaction
            $pdo->commit();

            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Obdobje in vsi povezani zapisi o prisotnosti so bili uspešno izbrisani',
                'data' => [
                    'period_id' => $periodId,
                    'class_subject_id' => $classSubjectId
                ]
            ], JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            // Roll back the transaction if an error occurs
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    } catch (JsonException $e) {
        error_log('JSON Error in handleDeletePeriod: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi podatkov JSON', 500, 'attendance.php/handleDeletePeriod');
    } catch (PDOException $e) {
        error_log('Database Error in handleDeletePeriod: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka baze podatkov pri brisanju obdobja', 500, 'attendance.php/handleDeletePeriod');
    } catch (Exception $e) {
        error_log('Error in handleDeletePeriod: ' . $e->getMessage());
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
        $justification = isset($_POST['notes']) ? htmlspecialchars($_POST['notes'], ENT_QUOTES, 'UTF-8') : null;

        // Validate inputs
        if (!$enrollId || $enrollId <= 0) sendJsonErrorResponse('Neveljaven ID vpisa', 400, 'attendance.php/handleSaveAttendance');

        if (!$periodId || $periodId <= 0) sendJsonErrorResponse('Neveljaven ID obdobja', 400, 'attendance.php/handleSaveAttendance');

        if (!in_array($status, ['P', 'A', 'L'])) sendJsonErrorResponse('Neveljaven status prisotnosti', 400, 'attendance.php/handleSaveAttendance');

        // Get database connection for permission checks
        $pdo = safeGetDBConnection('check_access');

        // Get period information to check access
        $stmt = $pdo->prepare("
            SELECT p.class_subject_id 
            FROM periods p
            WHERE p.period_id = ?
        ");
        $stmt->execute([$periodId]);
        $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$periodInfo) sendJsonErrorResponse('Obdobje ni bilo najdeno', 404, 'attendance.php/handleSaveAttendance');

        // Check teacher's access to this period
        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClassSubject($periodInfo['class_subject_id'])) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleSaveAttendance');

        // Verify enrollment exists and belongs to the right class
        $stmt = $pdo->prepare("
            SELECT e.class_id 
            FROM enrollments e
            WHERE e.enroll_id = ?
        ");
        $stmt->execute([$enrollId]);
        $enrollmentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$enrollmentInfo) sendJsonErrorResponse('Vpis ni bil najden', 404, 'attendance.php/handleSaveAttendance');

        // Use centralized function from teacher_functions.php
        if (!saveAttendance($enrollId, $periodId, $status)) sendJsonErrorResponse('Napaka pri shranjevanju prisotnosti', 500, 'attendance.php/handleSaveAttendance');

        // If justification is provided, save it separately
        if ($justification !== null) {
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET justification = ? 
                WHERE enroll_id = ? AND period_id = ?
            ");
            $stmt->execute([$justification, $enrollId, $periodId]);
        }

        // Check if this was an update or insert
        $stmt = $pdo->prepare("
            SELECT att_id 
            FROM attendance 
            WHERE enroll_id = ? AND period_id = ?
        ");
        $stmt->execute([$enrollId, $periodId]);
        $attId = $stmt->fetchColumn();

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Prisotnost uspešno shranjena',
            'data' => [
                'att_id' => $attId,
                'enroll_id' => $enrollId,
                'period_id' => $periodId,
                'status' => $status,
                'justification' => $justification
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('JSON Error in handleSaveAttendance: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi podatkov JSON', 500, 'attendance.php/handleSaveAttendance');
    } catch (PDOException $e) {
        error_log('Database Error in handleSaveAttendance: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka baze podatkov pri shranjevanju prisotnosti', 500, 'attendance.php/handleSaveAttendance');
    } catch (Exception $e) {
        error_log('Error in handleSaveAttendance: ' . $e->getMessage());
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

        // Validate input
        if (!$periodId || $periodId <= 0) sendJsonErrorResponse('Neveljaven ID obdobja', 400, 'attendance.php/handleBulkAttendance');

        // Get database connection for permission checks
        $pdo = safeGetDBConnection('check_period_access');

        // Get period information to check access
        $stmt = $pdo->prepare("
            SELECT p.class_subject_id 
            FROM periods p
            WHERE p.period_id = ?
        ");
        $stmt->execute([$periodId]);
        $periodInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$periodInfo) sendJsonErrorResponse('Obdobje ni bilo najdeno', 404, 'attendance.php/handleBulkAttendance');

        // Check teacher's access to this period
        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClassSubject($periodInfo['class_subject_id'])) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleBulkAttendance');

        $successCount = 0;
        $failedCount = 0;
        $updatedRecords = [];

        // Process each attendance record
        foreach ($attendanceData as $record) {
            // Validate each record's data
            if (!isset($record['enroll_id'], $record['status'])) {
                $failedCount++;
                continue;
            }

            $enrollId = filter_var($record['enroll_id'], FILTER_VALIDATE_INT);
            $status = htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8');
            $justification = isset($record['notes']) ? htmlspecialchars($record['notes'], ENT_QUOTES, 'UTF-8') : null;

            if (!$enrollId || !in_array($status, ['P', 'A', 'L'])) {
                $failedCount++;
                continue;
            }

            // Use centralized function from teacher_functions.php
            if (saveAttendance($enrollId, $periodId, $status)) {
                // If justification is provided, save it separately
                if ($justification !== null) {
                    $stmt = $pdo->prepare("
                        UPDATE attendance 
                        SET justification = ? 
                        WHERE enroll_id = ? AND period_id = ?
                    ");
                    $stmt->execute([$justification, $enrollId, $periodId]);
                }

                $successCount++;

                // Get the attendance record ID
                $stmt = $pdo->prepare("
                    SELECT att_id 
                    FROM attendance 
                    WHERE enroll_id = ? AND period_id = ?
                ");
                $stmt->execute([$enrollId, $periodId]);
                $attId = $stmt->fetchColumn();

                $updatedRecords[] = [
                    'att_id' => $attId,
                    'enroll_id' => $enrollId,
                    'status' => $status,
                    'justification' => $justification
                ];
            } else $failedCount++;
        }

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => "Prisotnost uspešno shranjena za $successCount študentov" .
                ($failedCount > 0 ? ", $failedCount zapisov ni bilo mogoče shraniti" : ""),
            'data' => [
                'period_id' => $periodId,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'updated_records' => $updatedRecords
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('JSON Error in handleBulkAttendance: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi podatkov JSON', 500, 'attendance.php/handleBulkAttendance');
    } catch (PDOException $e) {
        error_log('Database Error in handleBulkAttendance: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka baze podatkov pri shranjevanju prisotnosti', 500, 'attendance.php/handleBulkAttendance');
    } catch (Exception $e) {
        error_log('Error in handleBulkAttendance: ' . $e->getMessage());
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
        $approved = isset($_POST['approved']) ? filter_var($_POST['approved'], FILTER_VALIDATE_BOOLEAN) : null;
        $rejectReason = isset($_POST['reject_reason']) ? htmlspecialchars($_POST['reject_reason'], ENT_QUOTES, 'UTF-8') : null;

        // Validate input
        if (!$attId || $attId <= 0) sendJsonErrorResponse('Neveljaven ID prisotnosti', 400, 'attendance.php/handleJustifyAbsence');

        // Get database connection
        $pdo = safeGetDBConnection('justify_absence');

        // Check if attendance record exists and get related information
        $stmt = $pdo->prepare("
            SELECT a.enroll_id, a.period_id, a.status, a.justification, a.approved,
                   e.student_id, p.class_subject_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            WHERE a.att_id = ?
        ");
        $stmt->execute([$attId]);
        $attendanceRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attendanceRecord) sendJsonErrorResponse('Zapis o prisotnosti ni bil najden', 404, 'attendance.php/handleJustifyAbsence');

        // Check if status is actually an absence or late (can't justify presence)
        if ($attendanceRecord['status'] === 'P') sendJsonErrorResponse('Ni mogoče opravičiti prisotnosti', 400, 'attendance.php/handleJustifyAbsence');

        // Handle based on user role
        if (hasRole(ROLE_STUDENT)) {
            // Student submitting justification
            $studentId = getStudentId();

            if ($studentId != $attendanceRecord['student_id']) sendJsonErrorResponse('Nimate dovoljenja za opravičilo te odsotnosti', 403, 'attendance.php/handleJustifyAbsence');

            if (empty($justification)) sendJsonErrorResponse('Besedilo opravičila je obvezno', 400, 'attendance.php/handleJustifyAbsence');

            if ($attendanceRecord['justification'] !== null) sendJsonErrorResponse('Ta odsotnost že ima opravičilo', 400, 'attendance.php/handleJustifyAbsence');

            // Update the justification
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET justification = ?, 
                    approved = NULL
                WHERE att_id = ?
            ");
            $stmt->execute([$justification, $attId]);

            if ($stmt->rowCount() === 0) sendJsonErrorResponse('Napaka pri posodabljanju opravičila', 500, 'attendance.php/handleJustifyAbsence');

            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Opravičilo uspešno oddano',
                'data' => [
                    'att_id' => $attId,
                    'justification' => $justification
                ]
            ], JSON_THROW_ON_ERROR);

        } elseif (hasRole(ROLE_TEACHER) || hasRole(ROLE_ADMIN)) {
            // Teacher/Admin approving or rejecting justification

            // Check teacher's access to this period
            if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClassSubject($attendanceRecord['class_subject_id'])) sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/handleJustifyAbsence');

            // Ensure there's a justification to approve/reject
            if ($attendanceRecord['justification'] === null) sendJsonErrorResponse('Ta odsotnost nima opravičila za odobritev', 400, 'attendance.php/handleJustifyAbsence');

            if ($approved === null) sendJsonErrorResponse('Manjka status odobritve (odobreno/zavrnjeno)', 400, 'attendance.php/handleJustifyAbsence');

            // If rejecting, require a reason
            if ($approved === false && empty($rejectReason)) sendJsonErrorResponse('Pri zavrnitvi opravičila je potreben razlog', 400, 'attendance.php/handleJustifyAbsence');

            // Update the approval status
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET approved = ?,
                    reject_reason = ?
                WHERE att_id = ?
            ");
            $stmt->execute([$approved, $approved ? null : $rejectReason, $attId]);

            if ($stmt->rowCount() === 0) sendJsonErrorResponse('Napaka pri posodabljanju statusa opravičila', 500, 'attendance.php/handleJustifyAbsence');

            // Return success response
            echo json_encode([
                'success' => true,
                'message' => $approved ? 'Opravičilo uspešno odobreno' : 'Opravičilo zavrnjeno',
                'data' => [
                    'att_id' => $attId,
                    'approved' => $approved,
                    'reject_reason' => $approved ? null : $rejectReason
                ]
            ], JSON_THROW_ON_ERROR);

        } else sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php/handleJustifyAbsence');
    } catch (JsonException $e) {
        error_log('JSON Error in handleJustifyAbsence: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi podatkov JSON', 500, 'attendance.php/handleJustifyAbsence');
    } catch (PDOException $e) {
        error_log('Database Error in handleJustifyAbsence: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka baze podatkov pri obdelavi opravičila', 500, 'attendance.php/handleJustifyAbsence');
    } catch (Exception $e) {
        error_log('Error in handleJustifyAbsence: ' . $e->getMessage());
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
        if (!isset($_POST['student_id'])) sendJsonErrorResponse('Manjka ID študenta', 400, 'attendance.php/handleGetStudentAttendance');

        $studentId = filter_var($_POST['student_id'], FILTER_VALIDATE_INT);
        $dateFrom = isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from'], ENT_QUOTES, 'UTF-8') : null;
        $dateTo = isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to'], ENT_QUOTES, 'UTF-8') : null;

        // Validate input
        if (!$studentId || $studentId <= 0) sendJsonErrorResponse('Neveljaven ID študenta', 400, 'attendance.php/handleGetStudentAttendance');

        // Validate date formats if provided
        if ($dateFrom !== null && !validateDate($dateFrom)) sendJsonErrorResponse('Neveljaven format začetnega datuma', 400, 'attendance.php/handleGetStudentAttendance');

        if ($dateTo !== null && !validateDate($dateTo)) sendJsonErrorResponse('Neveljaven format končnega datuma', 400, 'attendance.php/handleGetStudentAttendance');

        // Check access permissions
        if (hasRole(ROLE_STUDENT)) {
            // Students can only view their own attendance
            $currentStudentId = getStudentId();
            if ($currentStudentId != $studentId) sendJsonErrorResponse('Nimate dovoljenja za ogled prisotnosti drugega študenta', 403, 'attendance.php/handleGetStudentAttendance');
        } elseif (hasRole(ROLE_PARENT)) if (!parentHasAccessToStudent($studentId)) sendJsonErrorResponse('Nimate dovoljenja za ogled prisotnosti tega študenta', 403, 'attendance.php/handleGetStudentAttendance');
        elseif (hasRole(ROLE_TEACHER)) {
            // Teachers can view attendance for students in their classes
            $pdo = safeGetDBConnection('check_student_access');
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM enrollments e
                JOIN class_subjects cs ON e.class_id = cs.class_id
                WHERE e.student_id = ? AND cs.teacher_id = ?
            ");
            $stmt->execute([$studentId, getTeacherId()]);

            if ($stmt->fetchColumn() == 0) sendJsonErrorResponse('Nimate dovoljenja za ogled prisotnosti tega študenta', 403, 'attendance.php/handleGetStudentAttendance');
        }
        // Admins have access to all student attendance data

        // Use centralized function from student_functions.php
        $attendanceData = $dateFrom || $dateTo
            ? getStudentAttendance($studentId, $dateFrom, $dateTo)
            : getStudentAttendance($studentId);

        // Handle empty result
        if (empty($attendanceData)) {
            echo json_encode([
                'success' => true,
                'message' => 'Za izbrano obdobje ni zapisov o prisotnosti',
                'data' => [
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
                ]
            ], JSON_THROW_ON_ERROR);
            return;
        }

        // Calculate statistics
        $statistics = calculateAttendanceStats($attendanceData);

        // Return success response
        echo json_encode([
            'success' => true,
            'data' => [
                'student_id' => $studentId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'records' => $attendanceData,
                'statistics' => $statistics
            ]
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('JSON Error in handleGetStudentAttendance: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri obdelavi podatkov JSON', 500, 'attendance.php/handleGetStudentAttendance');
    } catch (PDOException $e) {
        error_log('Database Error in handleGetStudentAttendance: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka baze podatkov pri pridobivanju podatkov o prisotnosti', 500, 'attendance.php/handleGetStudentAttendance');
    } catch (Exception $e) {
        error_log('Error in handleGetStudentAttendance: ' . $e->getMessage());
        sendJsonErrorResponse('Napaka pri pridobivanju podatkov o prisotnosti', 500, 'attendance.php/handleGetStudentAttendance');
    }
}
