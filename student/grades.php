<?php
/**
 * Student Grades View
 *
 * Allows students to view their own grades and class averages
 *
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'student_functions.php';

// Ensure only students can access this page
requireRole(ROLE_STUDENT);

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) die('Error: Student account not found.');

// Database connection
$pdo = safeGetDBConnection('student/grades.php');

// Get grades data
$grades = getStudentGrades($studentId);
$gradeStats = calculateGradeStatistics($grades);

?>

<!-- Page title and description card -->
<div class="card shadow mb-lg mt-lg">
    <div class="d-flex justify-between items-center">
        <div>
            <h2 class="mt-0 mb-xs">Moje ocene</h2>
            <p class="text-secondary mt-0 mb-0">Ogled trenutnih ocen za vse predmete</p>
        </div>
        <div class="role-badge role-student">Dijak</div>
    </div>
</div>

<!-- Grades summary statistics card -->
<div class="row">
    <div class="col col-md-4">
        <div class="card shadow mb-lg">
            <h3 class="card__title">Povzetek ocen</h3>
            <div class="card__content">
                <div class="d-flex flex-column gap-sm">
                    <?php if (!empty($gradeStats)): ?>
                        <div class="d-flex justify-between">
                            <span>Trenutno povprečje:</span>
                            <span class="grade <?= $gradeStats['averageGrade'] >= 4 ? 'grade-high' : ($gradeStats['averageGrade'] >= 2.5 ? 'grade-medium' : 'grade-low') ?>">
                                    <?= number_format($gradeStats['averageGrade'], 1) ?>
                                </span>
                        </div>
                        <div class="d-flex justify-between">
                            <span>Najvišja ocena:</span>
                            <span class="grade grade-high"><?= number_format($gradeStats['highestGrade'], 1) ?></span>
                        </div>
                        <div class="d-flex justify-between">
                            <span>Lowest Grade:</span>
                            <span class="grade <?= $gradeStats['lowestGrade'] >= 2.5 ? 'grade-medium' : 'grade-low' ?>">
                                    <?= number_format($gradeStats['lowestGrade'], 1) ?>
                                </span>
                        </div>
                        <div class="d-flex justify-between">
                            <span>Total Subjects:</span>
                            <span class="badge badge-primary"><?= $gradeStats['totalSubjects'] ?></span>
                        </div>
                    <?php else: ?>
                        <p class="text-secondary">No grade data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col col-md-8">
        <!-- Grades by subject -->
        <?php if (!empty($grades)): ?>
            <?php foreach ($grades as $subject => $subjectGrades): ?>
                <div class="card shadow mb-lg">
                    <h3 class="card__title"><?= htmlspecialchars($subject) ?></h3>
                    <div class="card__content">
                        <div class="d-flex justify-between mb-md">
                            <span class="text-secondary">Class Average:</span>
                            <span class="grade <?= $subjectGrades['average'] >= 4 ? 'grade-high' : ($subjectGrades['average'] >= 2.5 ? 'grade-medium' : 'grade-low') ?>">
                                    <?= number_format($subjectGrades['average'], 1) ?>
                                </span>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Date</th>
                                    <th>Grade</th>
                                    <th>Weight</th>
                                    <th>Comments</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($subjectGrades['items'] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['name']) ?></td>
                                        <td><?= htmlspecialchars($item['date']) ?></td>
                                        <td>
                                                    <span class="grade <?= $item['grade'] >= 4 ? 'grade-high' : ($item['grade'] >= 2.5 ? 'grade-medium' : 'grade-low') ?>">
                                                        <?= number_format($item['grade'], 1) ?>
                                                    </span>
                                        </td>
                                        <td><?= $item['weight'] ?>%</td>
                                        <td><?= htmlspecialchars($item['comments'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card shadow">
                <div class="card__content">
                    <div class="alert status-info">
                        Ni zabeleženh ocen za to polletje.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include page footer
include '../includes/footer.php';
?>
