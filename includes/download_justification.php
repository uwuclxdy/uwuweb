<?php
/**
 * Secure Justification File Download Handler
 *
 * Provides secure download of justification files
 *
 * /uwuweb/teacher/download_justification.php
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../teacher/teacher_functions.php';

// Verify user is logged in and has the teacher role
requireRole(ROLE_TEACHER);

$teacherId = getTeacherId(getUserId());
if (!$teacherId) die('Napaka: Učiteljev račun ni bil najden.');

// Validate and sanitize input
$absenceId = filter_input(INPUT_GET, 'att_id', FILTER_VALIDATE_INT);
$csrfToken = filter_input(INPUT_GET, 'csrf_token');

// Validate CSRF token
if (!$csrfToken || !verifyCSRFToken($csrfToken)) {
    http_response_code(403);
    die('Napaka: Neveljaven varnostni žeton.');
}

// Validate absence ID
if (!$absenceId) {
    http_response_code(400);
    die('Napaka: Manjka ali neveljaven ID odsotnosti.');
}

try {
    $pdo = safeGetDBConnection('download_justification');

    // Get the justification file info with security checks
    $query = "
        SELECT a.justification_file, 
               c.homeroom_teacher_id, 
               s.first_name, 
               s.last_name,
               c.class_code
        FROM attendance a
        JOIN enrollments e ON a.enroll_id = e.enroll_id
        JOIN students s ON e.student_id = s.student_id
        JOIN classes c ON e.class_id = c.class_id
        WHERE a.att_id = ?
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$absenceId]);
    $fileInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify file exists and teacher has access (is homeroom teacher)
    if (!$fileInfo || empty($fileInfo['justification_file']) || $fileInfo['homeroom_teacher_id'] != $teacherId) {
        http_response_code(404);
        die('Napaka: Datoteka ni bila najdena ali nimate dostopa do nje.');
    }

    // Build the file path
    $uploadDir = __DIR__ . '/justifications/';
    $filePath = $uploadDir . $fileInfo['justification_file'];

    // Check if file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('Napaka: Datoteka ne obstaja.');
    }

    // Get file information
    $fileSize = filesize($filePath);
    $fileExt = pathinfo($filePath, PATHINFO_EXTENSION);

    // Sanitize filename for download
    $safeFilename = preg_replace('/[^a-z0-9_\-.]/i', '_',
        $fileInfo['first_name'] . '_' . $fileInfo['last_name'] . '_' .
        $fileInfo['class_code'] . '_opravicilo.' . $fileExt);

    // Determine MIME type
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'txt' => 'text/plain'
    ];

    $contentType = $mimeTypes[$fileExt] ?? 'application/octet-stream';

    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $fileSize);

    // Output file content
    readfile($filePath);
    exit;

} catch (PDOException $e) {
    logDBError("Error in download_justification.php: " . $e->getMessage());
    http_response_code(500);
    die('Napaka pri dostopu do podatkovne baze.');
}
