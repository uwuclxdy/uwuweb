<?php
/**
 * Admin API Endpoints
 * /api/admin.php
 *
 * Provides API endpoints for admin-related operations in the uwuweb system.
 */

declare(strict_types=1);

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../admin/admin_functions.php';

// Ensure only authenticated administrators can access these endpoints
requireRole(ROLE_ADMIN);

// Process API requests
$action = $_GET['action'] ?? '';

if ($action === 'getClassDetails') {
    $classId = (int)($_GET['id'] ?? 0);

    if ($classId <= 0) sendJsonErrorResponse('Neveljaven ID razreda.');

    $classDetails = getClassDetails($classId);

    if (!$classDetails) sendJsonErrorResponse('Razred ni bil najden.');

    header('Content-Type: application/json');
    try {
        echo json_encode([
            'success' => true,
            'class' => $classDetails
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        sendJsonErrorResponse('Napaka pri kodiranju JSON.');
    }
    exit;
} else sendJsonErrorResponse('Zahtevana akcija ni veljavna.');
