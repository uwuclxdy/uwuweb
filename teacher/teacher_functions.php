<?php
/**
 * Teacher Functions Library
 *
 * Centralized library of functions used by teacher module pages
 *
 * Functions:
 * - getTeacherId($userId = null) - Gets teacher ID from user ID
 * - getTeacherClasses($teacherId) - Gets classes taught by a teacher
 * - getClassStudents($classId) - Gets students enrolled in a class
 * - getClassPeriods($classSubjectId) - Gets periods for a specific class-subject
 * - getPeriodAttendance($periodId) - Gets attendance records for a period
 * - addPeriod($classSubjectId, $periodDate, $periodLabel) - Adds a new period to a class
 * - saveAttendance($enroll_id, $period_id, $status) - Saves attendance status for a student
 * - getGradeItems($classSubjectId) - Gets grade items for a class-subject
 * - getClassGrades($classSubjectId) - Gets all grades for a class
 * - addGradeItem($classSubjectId, $name, $description, $maxPoints, $weight, $date) - Adds a new grade item
 * - saveGrade($enrollId, $itemId, $points, $feedback) - Saves a grade
 * - getPendingJustifications($teacherId) - Gets pending justifications for a teacher's classes
 * - getJustificationById($absenceId) - Gets detailed information about a specific justification
 * - approveJustification($absenceId) - Approves a justification
 * - rejectJustification($absenceId, $reason) - Rejects a justification with a reason
 * - getJustificationFileInfo($absenceId) - Gets information about a saved justification file
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

/**
 * Get teacher ID from user ID
 *
 * Retrieves the teacher_id from the teachers table for a given user_id
 * If userId is null, uses the current logged-in user
 *
 * @param int|null $userId User ID (if null, uses current user)
 * @return int|false Teacher ID or false if not found
 */
function getTeacherId(int $userId = null): false|int
{
    // TODO: Implementation needed
    // Should query the teachers table to find teacher_id where user_id matches the given or current user
    return false;
}

/**
 * Get classes taught by teacher
 *
 * Retrieves all classes assigned to a specific teacher through the class_subjects table
 * Includes class code, title, and subject information
 *
 * @param int $teacherId Teacher ID
 * @return array Array of classes
 */
function getTeacherClasses(int $teacherId): array
{
    // TODO: Implementation needed
    // Should join class_subjects with classes and subjects tables to get complete class information
    return [];
}

/**
 * Get students enrolled in a specific class
 *
 * Retrieves all students enrolled in a given class through the enrollments table
 * Includes student personal information and enrollment details
 *
 * @param int $classId Class ID
 * @return array Array of students
 */
function getClassStudents(int $classId): array
{
    // TODO: Implementation needed
    // Should join enrollments with students table to get complete student information
    return [];
}

/**
 * Get periods for a specific class-subject combination
 *
 * Retrieves all periods (class sessions) for a given class-subject ID
 * Ordered by date (newest first) and period label
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of periods
 */
function getClassPeriods(int $classSubjectId): array
{
    // TODO: Implementation needed
    // Should query the periods table for periods associated with the given class_subject_id
    return [];
}

/**
 * Get attendance records for a specific period
 *
 * Retrieves all attendance entries for a given period
 * Returns results indexed by enrollment ID for easier lookup
 *
 * @param int $periodId Period ID
 * @return array Array of attendance records indexed by enrollment ID
 */
function getPeriodAttendance(int $periodId): array
{
    // TODO: Implementation needed
    // Should query the attendance table for records associated with the given period_id
    return [];
}

/**
 * Add a new period to a class
 *
 * Creates a new period (class session) entry for a specific class-subject
 * Returns success/failure of the operation
 *
 * @param int $classSubjectId Class-Subject ID
 * @param string $periodDate Period date (YYYY-MM-DD)
 * @param string $periodLabel Period label
 * @return bool Success status
 */
function addPeriod(int $classSubjectId, string $periodDate, string $periodLabel): bool
{
    // TODO: Implementation needed
    // Should insert a new record into the periods table
    return false;
}

/**
 * Save attendance status for a student
 *
 * Records or updates a student's attendance status for a specific period
 * Handles both creating new records and updating existing ones
 *
 * @param int $enroll_id Enrollment ID
 * @param int $period_id Period ID
 * @param string $status Attendance status (P, A, or L)
 * @return bool Success status
 */
function saveAttendance(int $enroll_id, int $period_id, string $status): bool
{
    // TODO: Implementation needed
    // Should check if a record exists and update it, or insert a new record if it doesn't
    return false;
}

/**
 * Get grade items for a class-subject
 *
 * Retrieves all grade items (assignments, tests, etc.) for a specific class-subject
 * Ordered by item name
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of grade items
 */
function getGradeItems(int $classSubjectId): array
{
    // TODO: Implementation needed
    // Should query the grade_items table for items associated with the given class_subject_id
    return [];
}

/**
 * Get grades for a class-subject
 *
 * Retrieves all grades for all students in a class-subject
 * Returns results indexed by enrollment ID and item ID for easier lookup
 *
 * @param int $classSubjectId Class-Subject ID
 * @return array Array of grades indexed by enrollment ID and item ID
 */
function getClassGrades(int $classSubjectId): array
{
    // TODO: Implementation needed
    // Should join grades with grade_items to get grades for the given class_subject_id
    return [];
}

/**
 * Add a new grade item
 *
 * Creates a new grade item (assignment, test, etc.) for a specific class-subject
 * Returns success/failure of the operation
 *
 * @param int $classSubjectId Class-Subject ID
 * @param string $name Grade item name
 * @param float $maxPoints Maximum points
 * @param float $weight Weight
 * @return bool Success status
 */
function addGradeItem(int $classSubjectId, string $name, float $maxPoints, float $weight): bool
{
    // TODO: Implementation needed
    // Should insert a new record into the grade_items table
    return false;
}

/**
 * Save a grade
 *
 * Records or updates a student's grade for a specific grade item
 * Handles both creating new records and updating existing ones
 *
 * @param int $enrollId Enrollment ID
 * @param int $itemId Grade item ID
 * @param float $points Points
 * @return bool Success status
 */
function saveGrade(int $enrollId, int $itemId, float $points): bool
{
    // TODO: Implementation needed
    // Should check if a grade exists and update it, or insert a new grade if it doesn't
    return false;
}

/**
 * Get pending justifications for a teacher's classes
 *
 * Retrieves all absence justifications that are pending approval
 * for classes taught by a specific teacher
 *
 * @param int $teacherId Teacher ID
 * @return array Array of pending justifications
 */
function getPendingJustifications(int $teacherId): array
{
    // TODO: Implementation needed
    // Should join multiple tables to get complete information about pending justifications
    return [];
}

/**
 * Get justification by ID
 *
 * Retrieves detailed information about a specific absence justification
 * Including student, class, and period information
 *
 * @param int $absenceId Absence ID
 * @return array|null Justification data or null if not found
 */
function getJustificationById(int $absenceId): ?array
{
    // TODO: Implementation needed
    // Should join multiple tables to get complete information about the specific justification
    return null;
}

/**
 * Approve a justification
 *
 * Marks an absence justification as approved
 * Also clears any previous rejection reason
 *
 * @param int $absenceId Absence ID
 * @return bool Success status
 */
function approveJustification(int $absenceId): bool
{
    // TODO: Implementation needed
    // Should update the attendance record to set approved=1 and clear reject_reason
    return false;
}

/**
 * Reject a justification
 *
 * Marks an absence justification as rejected and records the reason
 *
 * @param int $absenceId Absence ID
 * @param string $reason Rejection reason
 * @return bool Success status
 */
function rejectJustification(int $absenceId, string $reason): bool
{
    // TODO: Implementation needed
    // Should update the attendance record to set approved=0 and store the reject_reason
    return false;
}

/**
 * Get justification file information
 *
 * Retrieves the file path for a justification document if one exists
 *
 * @param int $absenceId Absence ID
 * @return string|null File path or null if not found
 */
function getJustificationFileInfo(int $absenceId): ?string
{
    // TODO: Implementation needed
    // Should query the attendance table to get the justification_file field
    return null;
}
