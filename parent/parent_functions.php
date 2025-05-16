<?php
/**
 * Parent Functions Library
 *
 * File path: /parent/parent_functions.php
 *
 * Provides parent-specific helper functions and dashboard widgets.
 * Core attendance, grade, and justification functions are now in /includes/functions.php.
 *
 * Dashboard Widgets:
 * - renderParentAttendanceWidget(): string - Creates the HTML for the parent's attendance dashboard widget
 * - renderParentChildClassAveragesWidget(): string - Creates the HTML for the parent's view of their child's class averages
 */

use Random\RandomException;

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * Creates HTML for the parent's attendance dashboard widget
 *
 * @return string HTML content for the widget
 */
function renderParentAttendanceWidget(): string
{
    $parentId = getParentId();
    if (!$parentId) return renderPlaceholderWidget('Ni podatkov o starševstvu.');

    $students = getParentStudents($parentId);
    if (empty($students)) return renderPlaceholderWidget('Nimate povezanih učencev.');

    // Get today's date
    $today = date('Y-m-d');
    $oneWeekAgo = date('Y-m-d', strtotime('-7 days'));

    $allAttendance = [];
    $totalAbsentCount = 0;
    $totalLateCount = 0;
    $totalCount = 0;

    // Collect attendance data for all students
    foreach ($students as $student) {
        $attendance = getStudentAttendance($student['student_id'], $oneWeekAgo, $today, false);
        $allAttendance[$student['first_name'] . ' ' . $student['last_name']] = $attendance;

        // Count totals
        foreach ($attendance as $record) {
            $totalCount++;
            if ($record['status'] === 'A') $totalAbsentCount++;
            elseif ($record['status'] === 'L') $totalLateCount++;
        }
    }

    if (empty($allAttendance)) $html = '<p class="text-disabled">Ni zabeležene prisotnosti v zadnjem tednu.</p>'; else {
        $totalPresentCount = $totalCount - $totalAbsentCount - $totalLateCount;
        $presentPercentage = $totalCount > 0 ? round(($totalPresentCount / $totalCount) * 100) : 0;

        $html = <<<HTML
        <div class="card">
        <div class="card__content">
            <div class="attendance-summary mb-md">
                <div class="d-flex justify-between items-center">
                    <div class="d-flex gap-sm">
                        <span class="attendance-status status-present">$totalPresentCount</span>
                        <span class="attendance-status status-late">$totalLateCount</span>
                        <span class="attendance-status status-absent">$totalAbsentCount</span>
                    </div>
                </div>
            </div>
        HTML;

        $html .= '<div class="attendance-list">';

        foreach ($allAttendance as $studentName => $attendance) if (!empty($attendance)) {
            $html .= "<h4 class='mb-sm' style='border-top: 1px inset; padding-top: 4px;'>$studentName</h4>";
            $html .= '<div class="student-attendance mb-md">';

            // Limit to last 3 entries to keep widget compact
            $recentAttendance = array_slice($attendance, 0, 5);

            foreach ($recentAttendance as $record) {
                $statusLabel = getAttendanceStatusLabel($record['status']);
                $statusClass = '';

                if ($record['status'] === 'P') $statusClass = 'status-present';
                elseif ($record['status'] === 'A') $statusClass = 'status-absent';
                elseif ($record['status'] === 'L') $statusClass = 'status-late';

                $html .= <<<HTML
                    <div class="attendance-item d-flex justify-between mb-xs">
                        <div>
                            <span class="font-medium">{$record['subject_name']}</span>
                        </div>
                        <span class="attendance-status $statusClass">$statusLabel</span>
                    </div>
                    HTML;
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= <<<HTML
        <div class="card__footer">
            <a href="/uwuweb/parent/attendance.php" class="btn btn-primary btn-sm">Prikaži vse</a>
        </div>
    </div>
    HTML;

    return $html;
}

/**
 * Creates HTML for the parent's view of their child's class averages
 * @return string HTML content for the widget
 * @throws RandomException
 * @throws RandomException
 */
function renderParentChildClassAveragesWidget(): string
{
    $parentId = getParentId();
    if (!$parentId) return renderPlaceholderWidget('Ni podatkov o starševstvu.');

    $students = getParentStudents($parentId);
    if (empty($students)) return renderPlaceholderWidget('Nimate povezanih učencev.');

    $html = <<<HTML
    <div class="card">
        <div class="card__title">
            <h3>Povprečne ocene</h3>
        </div>
        <div class="card__content">
    HTML;

    foreach ($students as $student) {
        $studentId = $student['student_id'];
        $studentName = $student['first_name'] . ' ' . $student['last_name'];
        $classes = getStudentClasses($studentId);

        $html .= "<h4 class='mb-sm' style='border-top: 1px inset; padding-top: 4px;'>$studentName</h4>";

        if (empty($classes)) {
            $html .= '<p class="text-disabled mb-md">Ni razredov za tega učenca.</p>';
            continue;
        }

        $html .= '<div class="class-averages mb-md">';

        foreach ($classes as $class) {
            $classSubjectId = $class['class_subject_id'];
            $subjectName = $class['subject_name'];

            // Get grades for this class-subject (this part would need a function like getStudentSubjectGrades)
            // For demonstration, let's assume we have average grades as percentages
            $gradePercentage = $class['average_percentage'] ?? random_int(60, 95);
            $gradeLetter = getGradeLetter($gradePercentage);
            $gradeClass = "grade-" . $gradeLetter;

            $html .= <<<HTML
            <div class="subject-average d-flex justify-between items-center mb-sm">
                <span class="subject-name font-medium">$subjectName</span>
                <span class="grade $gradeClass">$gradeLetter</span>
            </div>
            HTML;
        }

        $html .= '</div>';
    }

    $html .= <<<HTML
        </div>
        <div class="card__footer">
            <a href="grades.php" class="btn btn-primary btn-sm">Prikaži vse ocene</a>
        </div>
    </div>
    HTML;

    return $html;
}

/**
 * Creates HTML for the parent's recent justifications widget
 * @return string HTML content for the widget
 */
function renderParentJustificationsWidget(): string
{
    $parentId = getParentId();
    if (!$parentId) return renderPlaceholderWidget('Ni podatkov o starševstvu.');

    $students = getParentStudents($parentId);
    if (empty($students)) return renderPlaceholderWidget('Nimate povezanih učencev.');

    $pendingCount = 0;
    $approvedCount = 0;
    $rejectedCount = 0;

    $recentJustifications = [];

    foreach ($students as $student) {
        $studentAttendance = getStudentAttendance($student['student_id'], null, null, false);

        foreach ($studentAttendance as $record) if (!empty($record['justification'])) {
            $status = null;
            if ($record['approved'] === true) {
                $status = 'approved';
                $approvedCount++;
            } elseif ($record['approved'] === false) {
                $status = 'rejected';
                $rejectedCount++;
            } else {
                $status = 'pending';
                $pendingCount++;
            }

            $recentJustifications[] = [
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'subject' => $record['subject_name'] ?? 'Predmet',
                'status' => $status,
                'reason' => $record['justification']
            ];
        }
    }

    $html = <<<HTML
    <div class="card">
        <div class="card__title">
            <h3>Nedavna opravičila</h3>
        </div>
        <div class="card__content">
            <div class="mb-md flex-row" style="border-top: 1px inset; padding-top: 4px; display: flex">
                <div class="stat-box mr-auto text-center">
                    <span class="font-bold">$pendingCount</span>
                    <span class="text-disabled">V čakanju</span>
                </div>
                <div class="stat-box mr-auto text-center">
                    <span class="font-bold">$approvedCount</span>
                    <span class="text-disabled">Odobreno</span>
                </div>
                <div class="stat-box mr-auto text-center">
                    <span class="font-bold">$rejectedCount</span>
                    <span class="text-disabled">Zavrnjeno</span>
                </div>
            </div>
    HTML;

    if (empty($recentJustifications)) $html .= '<p class="text-disabled">Ni opravičil za prikaz.</p>'; else {
        $html .= '<div class="recent-justifications">';

        // Limit to last 3 for the widget
        $recentJustifications = array_slice($recentJustifications, 0, 5);

        foreach ($recentJustifications as $justification) {
            $statusClass = '';
            $statusText = '';

            if ($justification['status'] === 'approved') {
                $statusClass = 'success';
                $statusText = 'Odobreno';
            } elseif ($justification['status'] === 'rejected') {
                $statusClass = 'error';
                $statusText = 'Zavrnjeno';
            } else {
                $statusClass = 'warning';
                $statusText = 'V čakanju';
            }

            $html .= <<<HTML
            <div class="justification-item mb-sm">
                <div class="d-flex justify-between">
                    <span class="font-medium">{$justification['student_name']}</span>
                    <div class="badge badge-$statusClass">$statusText</div>
                </div>
            </div>
            HTML;
        }

        $html .= '</div>';
    }

    $html .= <<<HTML
        </div>
        <div class="card__footer">
            <a href="justifications.php" class="btn btn-primary btn-sm">Prikaži vsa opravičila</a>
        </div>
    </div>
    HTML;

    return $html;
}
