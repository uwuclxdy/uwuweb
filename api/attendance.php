<?php
/**
 * Attendance API Endpoint
 *
 * Handles CRUD operations for attendance data via AJAX requests.
 * Returns JSON responses for client-side processing.
 * Access control varies by function - some functions restricted to teacher/admin roles,
 * while others are available to students for justification submissions.
 *
 * File path: /api/attendance.php
 *
 * Period Management:
 * - addPeriod(): void - Creates a new period for a class with initial attendance records
 * - updatePeriod(): void - Updates date and label information for an existing period
 * - deletePeriod(): void - Deletes a period and all associated attendance records
 *
 * Attendance Recording:
 * - saveAttendance(): void - Saves attendance status for a single student
 * - bulkAttendance(): void - Saves attendance status for multiple students at once
 *
 * Justification Management:
 * - justifyAbsence(): void - Records or approves absence justification based on user role
 * - getStudentAttendance(): void - Gets attendance summary and statistics for a student
 *
 * Access Control Helpers:
 * - teacherHasAccessToClass(int $classSubjectId): bool - Verifies teacher has access to class-subject
 * - teacherHasAccessToPeriod(int $periodId): bool - Verifies teacher has access to specific period
 * - teacherHasAccessToEnrollment(int $enrollId): bool - Verifies teacher has access to student enrollment
 * - studentOwnsEnrollment(int $enrollId): bool - Checks if current student owns the enrollment record
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'unrequested') {
    sendJsonErrorResponse('Only AJAX requests are allowed', 403, 'attendance.php');
}

if (!isLoggedIn()) {
    sendJsonErrorResponse('Niste prijavljeni', 401, 'attendance.php');
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'addPeriod':
        if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) {
            sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
        }
        addPeriod();
        break;

    case 'updatePeriod':
        if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) {
            sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
        }
        updatePeriod();
        break;

    case 'deletePeriod':
        if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) {
            sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
        }
        deletePeriod();
        break;

    case 'saveAttendance':
        if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) {
            sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
        }
        saveAttendance();
        break;

    case 'bulkAttendance':
        if (!hasRole(ROLE_ADMIN) && !hasRole(ROLE_TEACHER)) {
            sendJsonErrorResponse('Nimate dovoljenja za to dejanje', 403, 'attendance.php');
        }
        bulkAttendance();
        break;

    case 'justifyAbsence':
        justifyAbsence();
        break;

    case 'getStudentAttendance':
        getStudentAttendance();
        break;

    default:
        sendJsonErrorResponse('Neveljavna zahteva', 400, 'attendance.php');
}

/**
 * Adds a new period to a class with initial attendance records
 *
 * @return void Outputs JSON response directly
 */
function addPeriod(): void {
    try {
        if (!isset($_POST['class_subject_id'], $_POST['period_date'], $_POST['period_label'])) {
            sendJsonErrorResponse('Manjkajo zahtevani podatki', 400, 'attendance.php/addPeriod');
        }

        $classSubjectId = filter_var($_POST['class_subject_id'], FILTER_VALIDATE_INT);
        $periodDate = htmlspecialchars($_POST['period_date'], ENT_QUOTES, 'UTF-8');
        $periodLabel = htmlspecialchars($_POST['period_label'], ENT_QUOTES, 'UTF-8');

        $dateObj = DateTime::createFromFormat('Y-m-d', $periodDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $periodDate) {
            sendJsonErrorResponse('Neveljaven format datuma', 400, 'attendance.php/addPeriod');
        }

        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToClass($classSubjectId)) {
            sendJsonErrorResponse('Nimate dostopa do tega razreda', 403, 'attendance.php/addPeriod');
        }

        $pdo = safeGetDBConnection('add_period', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/addPeriod');
        }

        $pdo->beginTransaction();

        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM periods 
            WHERE class_subject_id = :class_subject_id 
            AND period_date = :period_date 
            AND period_label = :period_label
        ");
        $checkStmt->bindParam(':class_subject_id', $classSubjectId, PDO::PARAM_INT);
        $checkStmt->bindParam(':period_date', $periodDate);
        $checkStmt->bindParam(':period_label', $periodLabel);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() > 0) {
            $pdo->rollBack();
            sendJsonErrorResponse('To obdobje že obstaja za ta razred in predmet', 400, 'attendance.php/addPeriod');
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO periods (class_subject_id, period_date, period_label) 
            VALUES (:class_subject_id, :period_date, :period_label)
        ");
        $insertStmt->bindParam(':class_subject_id', $classSubjectId, PDO::PARAM_INT);
        $insertStmt->bindParam(':period_date', $periodDate);
        $insertStmt->bindParam(':period_label', $periodLabel);
        $insertStmt->execute();

        $periodId = $pdo->lastInsertId();

        $enrollStmt = $pdo->prepare("
            SELECT e.enroll_id 
            FROM enrollments e
            JOIN class_subjects cs ON e.class_id = cs.class_id
            WHERE cs.class_subject_id = :class_subject_id
        ");
        $enrollStmt->bindParam(':class_subject_id', $classSubjectId, PDO::PARAM_INT);
        $enrollStmt->execute();

        $attendanceStmt = $pdo->prepare("
            INSERT INTO attendance (enroll_id, period_id, status)
            VALUES (:enroll_id, :period_id, 'P')
        ");

        while ($enrollment = $enrollStmt->fetch(PDO::FETCH_ASSOC)) {
            $attendanceStmt->bindParam(':enroll_id', $enrollment['enroll_id'], PDO::PARAM_INT);
            $attendanceStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
            $attendanceStmt->execute();
        }

        $pdo->commit();

        try {
            echo json_encode([
                'success' => true,
                'message' => 'Obdobje uspešno dodano',
                'period_id' => $periodId
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/addPeriod): ' . $e->getMessage());
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError($e->getMessage());
        http_response_code(500);
        try {
            echo json_encode(['success' => false, 'message' => 'Napaka pri dodajanju obdobja'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/addPeriod): ' . $e->getMessage());
        }
    }
}

/**
 * Updates date and label information for an existing period
 *
 * @return void Outputs JSON response directly
 */
function updatePeriod(): void {
    try {
        if (!isset($_POST['period_id'], $_POST['period_date'], $_POST['period_label'])) {
            sendJsonErrorResponse('Manjkajo zahtevani podatki', 400, 'attendance.php/updatePeriod');
        }

        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);
        $periodDate = htmlspecialchars($_POST['period_date'], ENT_QUOTES, 'UTF-8');
        $periodLabel = htmlspecialchars($_POST['period_label'], ENT_QUOTES, 'UTF-8');

        $dateObj = DateTime::createFromFormat('Y-m-d', $periodDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $periodDate) {
            sendJsonErrorResponse('Neveljaven format datuma', 400, 'attendance.php/updatePeriod');
        }

        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToPeriod($periodId)) {
            sendJsonErrorResponse('Nimate dostopa do tega obdobja', 403, 'attendance.php/updatePeriod');
        }

        $pdo = safeGetDBConnection('update_period', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/updatePeriod');
        }

        $checkStmt = $pdo->prepare("
            SELECT class_subject_id FROM periods 
            WHERE period_id = :period_id
        ");
        $checkStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $checkStmt->execute();

        $classSubjectId = $checkStmt->fetchColumn();
        if (!$classSubjectId) {
            sendJsonErrorResponse('Obdobje ne obstaja', 404, 'attendance.php/updatePeriod');
        }

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

        if ($checkDuplicateStmt->fetchColumn() > 0) {
            sendJsonErrorResponse('To obdobje že obstaja za ta razred in predmet', 400, 'attendance.php/updatePeriod');
        }

        $updateStmt = $pdo->prepare("
            UPDATE periods 
            SET period_date = :period_date, period_label = :period_label 
            WHERE period_id = :period_id
        ");
        $updateStmt->bindParam(':period_date', $periodDate);
        $updateStmt->bindParam(':period_label', $periodLabel);
        $updateStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $updateStmt->execute();

        try {
            echo json_encode([
                'success' => true,
                'message' => 'Obdobje uspešno posodobljeno'
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/updatePeriod): ' . $e->getMessage());
        }

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        http_response_code(500);
        try {
            echo json_encode(['success' => false, 'message' => 'Napaka pri posodabljanju obdobja'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/updatePeriod): ' . $e->getMessage());
        }
    }
}

/**
 * Deletes a period and all associated attendance records
 *
 * @return void Outputs JSON response directly
 */
function deletePeriod(): void {
    try {
        if (!isset($_POST['period_id'])) {
            http_response_code(400);
            try {
                echo json_encode(['success' => false, 'message' => 'Manjka ID obdobja'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/deletePeriod): ' . $e->getMessage());
            }
            exit;
        }

        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);

        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToPeriod($periodId)) {
            http_response_code(403);
            try {
                echo json_encode(['success' => false, 'message' => 'Nimate dostopa do tega obdobja'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/deletePeriod): ' . $e->getMessage());
            }
            exit;
        }

        $pdo = safeGetDBConnection('delete_period', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/deletePeriod');
        }

        $pdo->beginTransaction();

        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM periods 
            WHERE period_id = :period_id
        ");
        $checkStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() == 0) {
            $pdo->rollBack();
            http_response_code(404);
            try {
                echo json_encode(['success' => false, 'message' => 'Obdobje ne obstaja'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/deletePeriod): ' . $e->getMessage());
            }
            exit;
        }

        $deleteAttStmt = $pdo->prepare("
            DELETE FROM attendance 
            WHERE period_id = :period_id
        ");
        $deleteAttStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $deleteAttStmt->execute();

        $deletePeriodStmt = $pdo->prepare("
            DELETE FROM periods 
            WHERE period_id = :period_id
        ");
        $deletePeriodStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $deletePeriodStmt->execute();

        $pdo->commit();

        try {
            echo json_encode([
                'success' => true,
                'message' => 'Obdobje uspešno izbrisano'
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/deletePeriod): ' . $e->getMessage());
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError($e->getMessage());
        http_response_code(500);
        try {
            echo json_encode(['success' => false, 'message' => 'Napaka pri brisanju obdobja'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/deletePeriod): ' . $e->getMessage());
        }
    }
}

/**
 * Saves attendance status for a single student
 *
 * @return void Outputs JSON response directly
 */
function saveAttendance(): void {
    try {
        if (!isset($_POST['enroll_id'], $_POST['period_id'], $_POST['status'])) {
            http_response_code(400);
            try {
                echo json_encode(['success' => false, 'message' => 'Manjkajo zahtevani podatki'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/saveAttendance): ' . $e->getMessage());
            }
            exit;
        }

        $enrollId = filter_var($_POST['enroll_id'], FILTER_VALIDATE_INT);
        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);
        $status = htmlspecialchars($_POST['status'], ENT_QUOTES, 'UTF-8');

        if (!in_array($status, ['P', 'A', 'L'])) {
            http_response_code(400);
            try {
                echo json_encode(['success' => false, 'message' => 'Neveljaven status prisotnosti'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/saveAttendance): ' . $e->getMessage());
            }
            exit;
        }

        if (hasRole(ROLE_TEACHER)) {
            if (!teacherHasAccessToPeriod($periodId)) {
                http_response_code(403);
                try {
                    echo json_encode(['success' => false, 'message' => 'Nimate dostopa do tega obdobja'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/saveAttendance): ' . $e->getMessage());
                }
                exit;
            }
            if (!teacherHasAccessToEnrollment($enrollId)) {
                http_response_code(403);
                try {
                    echo json_encode(['success' => false, 'message' => 'Nimate dostopa do tega vpisa študenta'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/saveAttendance): ' . $e->getMessage());
                }
                exit;
            }
        }

        $pdo = safeGetDBConnection('save_attendance', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/saveAttendance');
        }

        $checkStmt = $pdo->prepare("
            SELECT att_id FROM attendance 
            WHERE enroll_id = :enroll_id AND period_id = :period_id
        ");
        $checkStmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
        $checkStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $checkStmt->execute();

        $attId = $checkStmt->fetchColumn();

        if ($attId) {
            $updateStmt = $pdo->prepare("
                UPDATE attendance 
                SET status = :status 
                WHERE att_id = :att_id
            ");
            $updateStmt->bindParam(':status', $status);
            $updateStmt->bindParam(':att_id', $attId, PDO::PARAM_INT);
            $updateStmt->execute();

            try {
                echo json_encode([
                    'success' => true,
                    'message' => 'Prisotnost uspešno posodobljena',
                    'mode' => 'update'
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/saveAttendance): ' . $e->getMessage());
            }
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO attendance (enroll_id, period_id, status) 
                VALUES (:enroll_id, :period_id, :status)
            ");
            $insertStmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
            $insertStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
            $insertStmt->bindParam(':status', $status);
            $insertStmt->execute();

            try {
                echo json_encode([
                    'success' => true,
                    'message' => 'Prisotnost uspešno zabeležena',
                    'mode' => 'insert',
                    'att_id' => $pdo->lastInsertId()
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/saveAttendance): ' . $e->getMessage());
            }
        }

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        http_response_code(500);
        try {
            echo json_encode(['success' => false, 'message' => 'Napaka pri shranjevanju prisotnosti'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/saveAttendance): ' . $e->getMessage());
        }
    }
}

/**
 * Saves attendance status for multiple students at once
 *
 * @return void Outputs JSON response directly
 */
function bulkAttendance(): void {
    try {
        if (!isset($_POST['period_id'], $_POST['attendance_data']) || !is_array($_POST['attendance_data'])) {
            http_response_code(400);
            try {
                echo json_encode(['success' => false, 'message' => 'Manjkajo zahtevani podatki'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/bulkAttendance): ' . $e->getMessage());
            }
            exit;
        }

        $periodId = filter_var($_POST['period_id'], FILTER_VALIDATE_INT);
        $attendanceData = $_POST['attendance_data'];

        if (hasRole(ROLE_TEACHER) && !teacherHasAccessToPeriod($periodId)) {
            http_response_code(403);
            try {
                echo json_encode(['success' => false, 'message' => 'Nimate dostopa do tega obdobja'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/bulkAttendance): ' . $e->getMessage());
            }
            exit;
        }

        $pdo = safeGetDBConnection('bulk_attendance', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/bulkAttendance');
        }

        $pdo->beginTransaction();

        $insertStmt = $pdo->prepare("
            INSERT INTO attendance (enroll_id, period_id, status)
            VALUES (:enroll_id, :period_id, :status)
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");

        $successCount = 0;
        $failCount = 0;

        foreach ($attendanceData as $record) {
            if (!isset($record['enroll_id'], $record['status'])) {
                continue;
            }

            $enrollId = filter_var($record['enroll_id'], FILTER_VALIDATE_INT);
            $status = htmlspecialchars($record['status'], ENT_QUOTES, 'UTF-8');

            if (!in_array($status, ['P', 'A', 'L'])) {
                $failCount++;
                continue;
            }

            if (hasRole(ROLE_TEACHER) && !teacherHasAccessToEnrollment($enrollId)) {
                $failCount++;
                continue;
            }

            $insertStmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
            $insertStmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
            $insertStmt->bindParam(':status', $status);

            if ($insertStmt->execute()) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        $pdo->commit();

        try {
            echo json_encode([
                'success' => true,
                'message' => "Prisotnost uspešno posodobljena za $successCount učencev" .
                    ($failCount > 0 ? ", $failCount posodobitev ni uspelo" : "")
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/bulkAttendance): ' . $e->getMessage());
        }

    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logDBError($e->getMessage());
        http_response_code(500);
        try {
            echo json_encode(['success' => false, 'message' => 'Napaka pri shranjevanju prisotnosti'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/bulkAttendance): ' . $e->getMessage());
        }
    }
}

/**
 * Records or approves absence justification based on user role
 *
 * @return void Outputs JSON response directly
 */
function justifyAbsence(): void {
    try {
        if (!isset($_POST['att_id'])) {
            http_response_code(400);
            try {
                echo json_encode(['success' => false, 'message' => 'Manjka ID prisotnosti'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
            }
            exit;
        }

        $attId = filter_var($_POST['att_id'], FILTER_VALIDATE_INT);
        $justification = isset($_POST['justification']) ? htmlspecialchars($_POST['justification'], ENT_QUOTES, 'UTF-8') : null;
        $approved = isset($_POST['approved']) ? (bool)$_POST['approved'] : null;
        $rejectReason = isset($_POST['reject_reason']) ? htmlspecialchars($_POST['reject_reason'], ENT_QUOTES, 'UTF-8') : null;

        $pdo = safeGetDBConnection('justify_absence', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/justifyAbsence');
        }

        $checkStmt = $pdo->prepare("
            SELECT a.enroll_id, a.period_id, a.status, a.justification, a.approved, e.student_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            WHERE a.att_id = :att_id
        ");
        $checkStmt->bindParam(':att_id', $attId, PDO::PARAM_INT);
        $checkStmt->execute();

        $attRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$attRecord) {
            http_response_code(404);
            try {
                echo json_encode(['success' => false, 'message' => 'Zapis o prisotnosti ne obstaja'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
            }
            exit;
        }

        if (hasRole(ROLE_STUDENT)) {
            if (!studentOwnsEnrollment($attRecord['enroll_id'])) {
                http_response_code(403);
                try {
                    echo json_encode(['success' => false, 'message' => 'Nimate dovoljenja za opravičilo te odsotnosti'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
                }
                exit;
            }

            if ($attRecord['status'] !== 'A' && $attRecord['status'] !== 'L') {
                http_response_code(400);
                try {
                    echo json_encode(['success' => false, 'message' => 'Opravičilo se lahko doda samo za odsotnost ali zamudo'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
                }
                exit;
            }

            $updateStmt = $pdo->prepare("
                UPDATE attendance
                SET justification = :justification
                WHERE att_id = :att_id
            ");
            $updateStmt->bindParam(':justification', $justification);
            $updateStmt->bindParam(':att_id', $attId, PDO::PARAM_INT);
            $updateStmt->execute();

            try {
                echo json_encode([
                    'success' => true,
                    'message' => 'Opravičilo uspešno oddano'
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
            }
        }
        elseif (hasRole(ROLE_TEACHER) || hasRole(ROLE_ADMIN)) {
            if (hasRole(ROLE_TEACHER) && !teacherHasAccessToPeriod($attRecord['period_id'])) {
                http_response_code(403);
                try {
                    echo json_encode(['success' => false, 'message' => 'Nimate dostopa do tega obdobja'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
                }
                exit;
            }

            if ($approved === null) {
                http_response_code(400);
                try {
                    echo json_encode(['success' => false, 'message' => 'Manjka status odobritve'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
                }
                exit;
            }

            if ($approved === false && empty($rejectReason)) {
                http_response_code(400);
                try {
                    echo json_encode(['success' => false, 'message' => 'Pri zavrnitvi opravičila je potreben razlog'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
                }
                exit;
            }

            $updateStmt = $pdo->prepare("
                UPDATE attendance
                SET approved = :approved, reject_reason = :reject_reason
                WHERE att_id = :att_id
            ");
            $updateStmt->bindParam(':approved', $approved, PDO::PARAM_BOOL);
            $updateStmt->bindParam(':reject_reason', $rejectReason);
            $updateStmt->bindParam(':att_id', $attId, PDO::PARAM_INT);
            $updateStmt->execute();

            try {
                echo json_encode([
                    'success' => true,
                    'message' => $approved ? 'Opravičilo uspešno odobreno' : 'Opravičilo zavrnjeno'
                ], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
            }
        } else {
            http_response_code(403);
            try {
                echo json_encode(['success' => false, 'message' => 'Nimate dovoljenja za to dejanje'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
            }
            exit;
        }

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        http_response_code(500);
        try {
            echo json_encode(['success' => false, 'message' => 'Napaka pri obdelavi opravičila'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/justifyAbsence): ' . $e->getMessage());
        }
    }
}

/**
 * Gets attendance summary and statistics for a student
 *
 * @return void Outputs JSON response directly
 */
function getStudentAttendance(): void {
    try {
        if (!isset($_POST['student_id'])) {
            http_response_code(400);
            try {
                echo json_encode(['success' => false, 'message' => 'Manjka ID učenca'], JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                error_log('API Error (attendance.php/getStudentAttendance): ' . $e->getMessage());
            }
            exit;
        }

        $studentId = filter_var($_POST['student_id'], FILTER_VALIDATE_INT);
        $dateFrom = isset($_POST['date_from']) ? htmlspecialchars($_POST['date_from'], ENT_QUOTES, 'UTF-8') : null;
        $dateTo = isset($_POST['date_to']) ? htmlspecialchars($_POST['date_to'], ENT_QUOTES, 'UTF-8') : null;

        if ($dateFrom) {
            $dateObjFrom = DateTime::createFromFormat('Y-m-d', $dateFrom);
            if (!$dateObjFrom || $dateObjFrom->format('Y-m-d') !== $dateFrom) {
                http_response_code(400);
                try {
                    echo json_encode(['success' => false, 'message' => 'Neveljaven format začetnega datuma'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/getStudentAttendance): ' . $e->getMessage());
                }
                exit;
            }
        }

        if ($dateTo) {
            $dateObjTo = DateTime::createFromFormat('Y-m-d', $dateTo);
            if (!$dateObjTo || $dateObjTo->format('Y-m-d') !== $dateTo) {
                http_response_code(400);
                try {
                    echo json_encode(['success' => false, 'message' => 'Neveljaven format končnega datuma'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/getStudentAttendance): ' . $e->getMessage());
                }
                exit;
            }
        }

        $pdo = safeGetDBConnection('get_student_attendance', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/getStudentAttendance');
        }

        if (hasRole(ROLE_STUDENT)) {
            $userId = getUserId();

            $checkStmt = $pdo->prepare("
                SELECT student_id FROM students WHERE user_id = :user_id
            ");
            $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->execute();

            $currentStudentId = $checkStmt->fetchColumn();

            if ($currentStudentId != $studentId) {
                http_response_code(403);
                try {
                    echo json_encode(['success' => false, 'message' => 'Nimate dovoljenja za ogled prisotnosti drugega učenca'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/getStudentAttendance): ' . $e->getMessage());
                }
                exit;
            }
        } elseif (hasRole(ROLE_PARENT)) {
            $userId = getUserId();

            $checkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM student_parent sp
                JOIN parents p ON sp.parent_id = p.parent_id
                WHERE p.user_id = :user_id AND sp.student_id = :student_id
            ");
            $checkStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $checkStmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
            $checkStmt->execute();

            if ($checkStmt->fetchColumn() == 0) {
                http_response_code(403);
                try {
                    echo json_encode(['success' => false, 'message' => 'Nimate dovoljenja za ogled prisotnosti tega učenca'], JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    error_log('API Error (attendance.php/getStudentAttendance): ' . $e->getMessage());
                }
                exit;
            }
        }

        $sql = "
            SELECT a.att_id, a.status, a.justification, a.approved, a.reject_reason,
                   p.period_date, p.period_label, 
                   s.name as subject_name, 
                   cs.class_subject_id
            FROM attendance a
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            WHERE e.student_id = :student_id
        ";

        $params = [':student_id' => $studentId];

        if ($dateFrom) {
            $sql .= " AND p.period_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND p.period_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        $sql .= " ORDER BY p.period_date DESC, s.name";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($attendanceRecords);
        $present = 0;
        $absent = 0;
        $late = 0;
        $justified = 0;

        foreach ($attendanceRecords as $record) {
            if ($record['status'] === 'P') {
                $present++;
            } elseif ($record['status'] === 'A') {
                $absent++;
                if ($record['approved'] === 1) {
                    $justified++;
                }
            } elseif ($record['status'] === 'L') {
                $late++;
            }
        }

        $stats = [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'justified' => $justified,
            'present_percent' => $total > 0 ? round(($present / $total) * 100, 1) : 0,
            'absent_percent' => $total > 0 ? round(($absent / $total) * 100, 1) : 0,
            'late_percent' => $total > 0 ? round(($late / $total) * 100, 1) : 0,
        ];

        try {
            echo json_encode([
                'success' => true,
                'records' => $attendanceRecords,
                'statistics' => $stats
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/getStudentAttendance): ' . $e->getMessage());
        }

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        http_response_code(500);
        try {
            echo json_encode(['success' => false, 'message' => 'Napaka pri pridobivanju podatkov o prisotnosti'], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            error_log('API Error (attendance.php/getStudentAttendance): ' . $e->getMessage());
        }
    }
}

/**
 * Verifies teacher has access to a class-subject
 *
 * @param int $classSubjectId The class-subject ID to check access for
 * @return bool True if the teacher has access, false otherwise
 */
function teacherHasAccessToClass(int $classSubjectId): bool {
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) {
            return false;
        }

        $pdo = safeGetDBConnection('check_teacher_class_access', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/teacherHasAccessToClass');
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM class_subjects 
            WHERE class_subject_id = :class_subject_id 
            AND teacher_id = :teacher_id
        ");
        $stmt->bindParam(':class_subject_id', $classSubjectId, PDO::PARAM_INT);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return false;
    }
}

/**
 * Verifies teacher has access to a specific period
 *
 * @param int $periodId The period ID to check access for
 * @return bool True if the teacher has access, false otherwise
 */
function teacherHasAccessToPeriod(int $periodId): bool {
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) {
            return false;
        }

        $pdo = safeGetDBConnection('check_teacher_period_access', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/teacherHasAccessToPeriod');
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM periods p
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            WHERE p.period_id = :period_id 
            AND cs.teacher_id = :teacher_id
        ");
        $stmt->bindParam(':period_id', $periodId, PDO::PARAM_INT);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return false;
    }
}

/**
 * Verifies teacher has access to a student enrollment
 *
 * @param int $enrollId The enrollment ID to check access for
 * @return bool True if the teacher has access, false otherwise
 */
function teacherHasAccessToEnrollment(int $enrollId): bool {
    try {
        $teacherId = getTeacherId();
        if (!$teacherId) {
            return false;
        }

        $pdo = safeGetDBConnection('check_teacher_enrollment_access', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/teacherHasAccessToEnrollment');
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM enrollments e
            JOIN class_subjects cs ON e.class_id = cs.class_id
            WHERE e.enroll_id = :enroll_id 
            AND cs.teacher_id = :teacher_id
        ");
        $stmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
        $stmt->bindParam(':teacher_id', $teacherId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return false;
    }
}

/**
 * Checks if current student owns the enrollment record
 *
 * @param int $enrollId The enrollment ID to check ownership for
 * @return bool True if the student owns the record, false otherwise
 */
function studentOwnsEnrollment(int $enrollId): bool {
    try {
        $studentId = getStudentId();
        if (!$studentId) {
            return false;
        }

        $pdo = safeGetDBConnection('check_student_enrollment_owner', false);

        if (!$pdo) {
            sendJsonErrorResponse('Napaka pri povezavi z bazo podatkov', 500, 'attendance.php/studentOwnsEnrollment');
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM enrollments 
            WHERE enroll_id = :enroll_id 
            AND student_id = :student_id
        ");
        $stmt->bindParam(':enroll_id', $enrollId, PDO::PARAM_INT);
        $stmt->bindParam(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool)$stmt->fetchColumn();

    } catch (PDOException $e) {
        logDBError($e->getMessage());
        return false;
    }
}
