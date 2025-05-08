<?php
/**
 * Admin Class-Subject Assignment Management
 * /uwuweb/admin/manage_assignments.php
 *
 * Provides functionality for administrators to manage class-subject assignments,
 * linking classes, subjects, and teachers together.
 *
 */

declare(strict_types=1);

require_once 'admin_functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

$message = '';
$messageType = '';
$classSubjectDetails = null;

// Load data
$classes = getAllClasses();
$subjects = getAllSubjects();
$teachers = getAllTeachers();
$classSubjects = getAllClassSubjectAssignments();



include '../includes/footer.php';
?>
