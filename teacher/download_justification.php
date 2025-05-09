<?php
/**
 * Teacher Justification File Download
 *
 * Securely serves uploaded justification files for authenticated teachers
 * Includes validation to ensure only authorized users can access files
 *
 * /uwuweb/teacher/download_justification.php
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'teacher_functions.php';

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId();
if (!$teacherId) die('Napaka: Učiteljski račun ni bil najden.');

// Validate request parameters
$absenceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($absenceId <= 0) die('Napaka: Neveljavna zahteva.');

// Check if the teacher has access to this justification
$justification = getJustificationById($absenceId);
if (!$justification) die('Napaka: Nimate dostopa do tega opravičila ali opravičilo ne obstaja.');

// Check if there's a file to download
if (empty($justification['justification_file'])) die('Napaka: Za to opravičilo ni priložene datoteke.');

// Get file path
$filePath = $justification['justification_file'];

// Verify the file exists
if (!file_exists($filePath)) die('Napaka: Datoteka ne obstaja ali je bila odstranjena.');

// Get file info
$fileName = basename($filePath);
$fileSize = filesize($filePath);
$fileType = mime_content_type($filePath) ?: 'application/octet-stream';

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: ' . $fileType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . $fileSize);

// Clear output buffer
ob_clean();
flush();

// Output file and stop script execution
readfile($filePath);
exit;
