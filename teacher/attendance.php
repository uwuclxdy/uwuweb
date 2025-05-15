<?php
/**
 * Teacher Attendance Page
 *
 * Provides interface for teachers to manage student attendance
 * File path: /teacher/attendance.php
 */

use Random\RandomException;

require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'teacher_functions.php';

// Ensure user has teacher role
requireRole(2);

// Get teacher ID
$teacherId = getTeacherId();
if (!$teacherId && !isset($_GET['fetch_json'])) { // Allow fetch_json for initial load error handling if needed by JS
    header('Location: ../dashboard.php?error=teacher_profile_not_found');
    exit;
}

// Get teacher's classes
$teacherClasses = $teacherId ? getTeacherClasses($teacherId) : [];
if (empty($teacherClasses) && !isset($_GET['fetch_json']) && $teacherId) {
    header('Location: ../dashboard.php?error=no_classes_assigned');
    exit;
}

// Default to today's date
$currentDate = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $currentDate;
$filterByDate = isset($_GET['filter_by_date']) && filter_var($_GET['filter_by_date'], FILTER_VALIDATE_BOOLEAN);


// Validate date format
$dateError = null;
if (!validateDate($selectedDate)) {
    $selectedDate = $currentDate;
    if (!isset($_GET['fetch_json'])) $dateError = "Neveljaven format datuma. Uporabljen je današnji datum.";
}

// Get selected class-subject
$selectedClassSubjectId = isset($_GET['class_subject_id']) ? (int)$_GET['class_subject_id'] : 0;

// If not specified, use the first class-subject
if (!$selectedClassSubjectId && !empty($teacherClasses)) $selectedClassSubjectId = $teacherClasses[0]['class_subject_id'];

// Check if teacher has access to this class-subject
if (!isset($_GET['fetch_json'])) if ($teacherId && $selectedClassSubjectId && !teacherHasAccessToClassSubject($selectedClassSubjectId, $teacherId)) {
    header('Location: ../dashboard.php?error=unauthorized_class_access');
    exit;
}

// Get the selected class-subject details
$selectedClassSubject = null;
if ($selectedClassSubjectId) foreach ($teacherClasses as $classSubject) if ($classSubject['class_subject_id'] == $selectedClassSubjectId) {
    $selectedClassSubject = $classSubject;
    break;
}

// Get periods for selected class-subject
$periods = $selectedClassSubjectId ? getClassPeriods($selectedClassSubjectId) : [];

// Filter periods by date if the filter is enabled
$filteredPeriods = $periods;
if ($filterByDate && !empty($periods)) $filteredPeriods = array_filter($periods, static function ($period) use ($selectedDate) {
    return $period['period_date'] === $selectedDate;
});

// Get selected period
$selectedPeriodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : 0;

// If not specified or invalid, use the latest period from filtered list
if ($selectedPeriodId === 0 || !array_key_exists($selectedPeriodId, array_column($filteredPeriods, null, 'period_id'))) {
    $selectedPeriodId = 0; // Reset if not in filtered list or explicitly 0
    $periodsToUse = !empty($filteredPeriods) ? $filteredPeriods : []; // Only use filtered periods for auto-selection by date

    if (!empty($periodsToUse)) {
        $latestPeriod = null;
        foreach ($periodsToUse as $period) if (!$latestPeriod || strtotime($period['period_date'] . ' ' . $period['period_label']) > strtotime($latestPeriod['period_date'] . ' ' . $latestPeriod['period_label'])) $latestPeriod = $period;
        if ($latestPeriod) $selectedPeriodId = $latestPeriod['period_id'];
    }
}


// Get the selected period details
$selectedPeriod = null;
if ($selectedPeriodId) foreach ($periods as $period) if ($period['period_id'] == $selectedPeriodId) {
    $selectedPeriod = $period;
    break;
}

// Get students for the selected class
$students = [];
$classIsEmpty = true; // Assume empty
if ($selectedClassSubject) {
    $students = getClassStudents($selectedClassSubject['class_id']);
    $classIsEmpty = empty($students);
}

// Get attendance records for the selected period
$attendanceRecords = [];
$attendanceError = false;
$attendanceErrorMessage = '';
if ($selectedPeriodId && !$classIsEmpty) try {
    $attendanceRecords = getPeriodAttendance($selectedPeriodId);
} catch (Exception $e) {
    $attendanceError = true;
    $attendanceErrorMessage = "Napaka pri pridobivanju podatkov o prisotnosti: " . $e->getMessage();
}

// Determine if teacher is homeroom teacher for this class
$isHomeroom = $teacherId && $selectedClassSubject && isHomeroomTeacher($teacherId, $selectedClassSubject['class_id']);

// Generate CSRF token for forms
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    // For JSON requests, we might send an error, for HTML, it's more critical.
    if (isset($_GET['fetch_json'])) sendJsonErrorResponse('Napaka pri generiranju CSRF žetona: ' . $e->getMessage(), 500, 'csrf_token_fetch');
    // For HTML page, this would be a fatal error, consider how to handle.
    // For now, let it pass and the form won't work.
    $csrfToken = ''; // Fallback, though not ideal.
    // error_log('CSRF token generation failed: ' . $e->getMessage());
}


if (isset($_GET['fetch_json'])) {
    header('Content-Type: application/json');

    $homeroomStudentStats = [];
    if ($isHomeroom && $selectedClassSubject && !$classIsEmpty) {
        $allClassAttendance = getAllAttendanceForClass($selectedClassSubject['class_id']);
        foreach ($students as $student) {
            $studentAllAttendanceRecords = [];
            foreach ($allClassAttendance as $attRecord) if ($attRecord['student_id'] == $student['student_id']) $studentAllAttendanceRecords[] = $attRecord;
            $stats = calculateAttendanceStats($studentAllAttendanceRecords);
            $homeroomStudentStats[$student['student_id']] = $stats;
        }
    }

    try {
        echo json_encode([
            'success' => true,
            'selectedClassSubjectId' => $selectedClassSubjectId,
            'selectedPeriodId' => $selectedPeriodId,
            'selectedDate' => $selectedDate,
            'filterByDate' => $filterByDate,
            'periods' => array_values($filteredPeriods), // Ensure simple array
            'students' => $students,
            'attendanceRecords' => ($selectedPeriodId && !$classIsEmpty) ? $attendanceRecords : [],
            'selectedPeriod' => $selectedPeriod,
            'classSubjectDetails' => $selectedClassSubject,
            'isHomeroomTeacher' => $isHomeroom,
            'homeroomStudentStats' => $homeroomStudentStats,
            'classIsEmpty' => $classIsEmpty,
            'csrfToken' => $csrfToken, // Send new CSRF token if needed for dynamic forms
            'teacherClasses' => $teacherClasses, // For repopulating class_subject_id if necessary
            'dateError' => $dateError,
            'attendanceError' => $attendanceError,
            'attendanceErrorMessage' => $attendanceErrorMessage,
        ], JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        sendJsonErrorResponse('Napaka pri kodiranju podatkov: ' . $e->getMessage(), 500, 'json_encode_error');
    }
    exit;
}

// Include header
include_once '../includes/header.php';
renderHeaderCard(
    "Upravljanje prisotnosti",
    "Za izbrano obdobje označite prisotnost učencev.",
    "teacher"
);
?>

<?php if (isset($dateError)): ?>
    <div class="alert status-warning" id="dateErrorAlert">
        <div class="alert-content">
            <p><?= htmlspecialchars($dateError) ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="section">
    <div class="card">
        <div class="card__title">Filtri</div>
        <div class="card__content">
            <form id="filterForm">
                <div class="row">
                    <div class="col col-md-4">
                        <div class="form-group">
                            <label for="class_subject_id" class="form-label">Predmet</label>
                            <select id="class_subject_id" name="class_subject_id" class="form-select">
                                <?php if (empty($teacherClasses)): ?>
                                    <option value="0">Ni dodeljenih predmetov</option>
                                <?php else: ?>
                                    <?php foreach ($teacherClasses as $classSubject): ?>
                                        <option value="<?= $classSubject['class_subject_id'] ?>"
                                            <?= ($classSubject['class_subject_id'] == $selectedClassSubjectId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($classSubject['subject_name'] . ' - ' . $classSubject['class_title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col col-md-4">
                        <div class="form-group">
                            <label for="period_id" class="form-label">Ura</label>
                            <select id="period_id" name="period_id" class="form-select">
                                <?php if (empty($filteredPeriods)): ?>
                                    <option value="0">Ni ur<?= $filterByDate ? ' za izbrani datum' : '' ?></option>
                                <?php else: ?>
                                    <?php foreach ($filteredPeriods as $period): ?>
                                        <option value="<?= $period['period_id'] ?>"
                                            <?= ($period['period_id'] == $selectedPeriodId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($period['period_label'] . ' (' . formatDateDisplay($period['period_date']) . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col col-md-4">
                        <div class="form-group">
                            <label for="date" class="form-label">
                                <input type="checkbox"
                                       name="filter_by_date"
                                       value="1" <?= $filterByDate ? 'checked' : '' ?>
                                       id="filterByDateCheckbox"> Datum
                            </label>
                            <input type="date" id="date" name="date" class="form-input"
                                   value="<?= htmlspecialchars($selectedDate) ?>"
                                <?= !$filterByDate ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="section">
    <div class="d-flex justify-between mb-md">
        <h2 class="text-lg" id="attendanceListTitle">Prisotnost za izbrano uro</h2>
        <button data-open-modal="addPeriodModal" id="addPeriodButton"
                class="btn btn-primary" <?= $classIsEmpty ? 'disabled' : '' ?>>
            Dodaj uro
        </button>
    </div>

    <div id="attendanceErrorAlertContainer">
        <?php if ($attendanceError): ?>
            <div class="alert status-error">
                <div class="alert-content">
                    <p><?= htmlspecialchars($attendanceErrorMessage) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="noPeriodSelectedAlertContainer">
        <?php if (empty($selectedPeriod) && !$classIsEmpty && !$attendanceError): ?>
            <div class="alert status-info">
                <div class="alert-content">
                    <p>Za izbrano kombinacijo ni učnih ur. Dodajte novo uro s klikom na gumb "Dodaj uro".</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="classEmptyAlertContainer">
        <?php if ($classIsEmpty): ?>
            <div class="alert status-warning">
                <div class="alert-content">
                    <p>V tem razredu ni vpisanih učencev. Učence mora najprej vpisati administrator.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="attendanceTableContainer" <?= ($classIsEmpty || empty($selectedPeriod) || $attendanceError) ? 'style="display: none;"' : '' ?>>
        <div class="card">
            <div class="card__title" id="attendanceCardTitle">
                <?php if ($selectedPeriod): ?>
                    <?= htmlspecialchars($selectedPeriod['period_label'] . ' - ' . formatDateDisplay($selectedPeriod['period_date'])) ?>
                <?php endif; ?>
            </div>
            <div class="card__content">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Učenec</th>
                            <th>Status</th>
                            <th>Ukrepi</th>
                        </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                        <?php if (!empty($selectedPeriod) && !empty($students)): ?>
                            <?php foreach ($students as $student): ?>
                                <?php
                                $attendanceRecord = null;
                                foreach ($attendanceRecords as $record) if ($record['student_id'] == $student['student_id']) {
                                    $attendanceRecord = $record;
                                    break;
                                }
                                $status = $attendanceRecord ? $attendanceRecord['status'] : 'A';
                                $enrollId = $attendanceRecord ? $attendanceRecord['enroll_id'] : ($student['enroll_id'] ?? 0); // Use enroll_id from student if available
                                $statusClass = '';
                                switch ($status) {
                                    case 'P':
                                        $statusClass = 'status-present';
                                        break;
                                    case 'A':
                                        $statusClass = 'status-absent';
                                        break;
                                    case 'L':
                                        $statusClass = 'status-late';
                                        break;
                                }
                                ?>
                                <tr data-student-id="<?= $student['student_id'] ?>"
                                    data-enroll-id="<?= $enrollId ?>"
                                    data-class-id="<?= $selectedClassSubject ? $selectedClassSubject['class_id'] : 0 ?>">
                                    <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                    <td>
                                        <span class="attendance-status <?= $statusClass ?>">
                                            <?= getAttendanceStatusLabel($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-xs">
                                            <button type="button" class="btn btn-success btn-sm attendance-btn"
                                                    data-status="P" data-period-id="<?= $selectedPeriodId ?>">
                                                Prisoten
                                            </button>
                                            <button type="button" class="btn btn-error btn-sm attendance-btn"
                                                    data-status="A" data-period-id="<?= $selectedPeriodId ?>">
                                                Odsoten
                                            </button>
                                            <button type="button" class="btn btn-warning btn-sm attendance-btn"
                                                    data-status="L" data-period-id="<?= $selectedPeriodId ?>">
                                                Zamuda
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif (empty($students) && !empty($selectedPeriod)): ?>
                            <tr>
                                <td colspan="3" class="text-center">V tem razredu ni vpisanih učencev.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="section" id="homeroomStatsSection" <?= !$isHomeroom || $classIsEmpty ? 'style="display: none;"' : '' ?>>
    <h2 class="text-lg mb-md">Statistika prisotnosti za razred</h2>
    <div class="dashboard-grid" id="homeroomStatsGrid">
        <?php if ($isHomeroom && !$classIsEmpty): ?>
            <?php
            $homeroomStudentStats = [];
            if ($selectedClassSubject) {
                $allClassAttendance = getAllAttendanceForClass($selectedClassSubject['class_id']);
                foreach ($students as $student) {
                    $studentAllAttendanceRecords = [];
                    foreach ($allClassAttendance as $attRecord) if ($attRecord['student_id'] == $student['student_id']) $studentAllAttendanceRecords[] = $attRecord;
                    $stats = calculateAttendanceStats($studentAllAttendanceRecords);
                    $homeroomStudentStats[$student['student_id']] = $stats;
                }
            }
            ?>
            <?php foreach ($students as $student): ?>
                <?php
                $stats = $homeroomStudentStats[$student['student_id']] ?? ['present_percent' => 0, 'present_count' => 0, 'absent_count' => 0, 'late_count' => 0, 'total' => 0];
                $rateClass = ($stats['present_percent'] ?? 0) >= 90 ? 'text-success' : (($stats['present_percent'] ?? 0) >= 75 ? 'text-warning' : 'text-error');
                ?>
                <div class="card shadow-sm mb-sm">
                    <div class="card__title p-md d-flex justify-between items-center">
                        <span><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span>
                        <span class="badge <?= $rateClass ?>">
                            <?= number_format($stats['present_percent'] ?? 0, 1) ?>%
                        </span>
                    </div>
                    <div class="card__content p-md">
                        <div class="d-flex flex-column gap-sm">
                            <div class="d-flex justify-between"><span>Prisoten:</span><span
                                        class="badge badge-success"><?= $stats['present_count'] ?? 0 ?></span></div>
                            <div class="d-flex justify-between"><span>Odsoten:</span><span
                                        class="badge badge-error"><?= $stats['absent_count'] ?? 0 ?></span></div>
                            <div class="d-flex justify-between"><span>Zamuda:</span><span
                                        class="badge badge-warning"><?= $stats['late_count'] ?? 0 ?></span></div>
                            <div class="d-flex justify-between mt-sm"><span>Skupaj učnih ur:</span><span
                                        class="font-bold"><?= $stats['total'] ?? 0 ?></span></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Period Modal -->
<div class="modal" id="addPeriodModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="addPeriodModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="addPeriodModalTitle">Dodaj novo učno uro</h3>
        </div>
        <form id="addPeriodForm">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" id="addPeriodCsrfToken"
                       value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="class_subject_id" id="addPeriodClassSubjectId"
                       value="<?= $selectedClassSubjectId ?>">

                <div class="form-group">
                    <label for="periodDate" class="form-label">Datum</label>
                    <input type="date" id="periodDate" name="period_date" class="form-input"
                           value="<?= date('Y-m-d') ?>" required>
                    <div id="periodDateError" class="feedback-error" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label for="periodLabel" class="form-label">Naziv ure</label>
                    <input type="text" id="periodLabel" name="period_label" class="form-input" required
                           maxlength="50" minlength="2">
                    <div id="periodLabelError" class="feedback-error" style="display: none;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-primary">Dodaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Alert container for notifications -->
<div class="alert-container"></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- Global state variables ---
        let G_CSRF_TOKEN = '<?= htmlspecialchars($csrfToken) ?>';
        let G_SELECTED_CLASS_SUBJECT_ID = <?= (int)$selectedClassSubjectId ?>;
        let G_SELECTED_PERIOD_ID = <?= (int)$selectedPeriodId ?>;
        let G_SELECTED_DATE = '<?= htmlspecialchars($selectedDate) ?>';
        let G_FILTER_BY_DATE = <?= $filterByDate ? 'true' : 'false' ?>;

        // --- DOM Elements ---
        const classSubjectSelect = document.getElementById('class_subject_id');
        const periodSelect = document.getElementById('period_id');
        const filterByDateCheckbox = document.getElementById('filterByDateCheckbox');
        const dateInput = document.getElementById('date');
        const addPeriodButton = document.getElementById('addPeriodButton');
        const attendanceTableBody = document.getElementById('attendanceTableBody');
        const attendanceCardTitle = document.getElementById('attendanceCardTitle');
        const attendanceTableContainer = document.getElementById('attendanceTableContainer');
        const noPeriodAlertContainer = document.getElementById('noPeriodSelectedAlertContainer');
        const classEmptyAlertContainer = document.getElementById('classEmptyAlertContainer');
        const attendanceErrorAlertContainer = document.getElementById('attendanceErrorAlertContainer');
        const homeroomStatsSection = document.getElementById('homeroomStatsSection');
        const homeroomStatsGrid = document.getElementById('homeroomStatsGrid');
        const addPeriodModalClassSubjectId = document.getElementById('addPeriodClassSubjectId');
        const addPeriodModalCsrfToken = document.getElementById('addPeriodCsrfToken');

        // --- Utility Functions ---
        function formatDateDisplayJS(dateString) {
            if (!dateString) return '';
            const [year, month, day] = dateString.split('-');
            return `${day}.${month}.${year}`;
        }

        function getAttendanceStatusLabelJS(status) {
            switch (status) {
                case 'P':
                    return 'Prisoten';
                case 'A':
                    return 'Odsoten';
                case 'L':
                    return 'Zamuda';
                default:
                    return 'Neznan';
            }
        }

        function showLoadingAlert() {
            // Simple loading, replace or enhance if a global alert system is used
            const existingAlert = document.querySelector('.alert-container .status-info');
            if (existingAlert && existingAlert.textContent.includes('Nalaganje')) return existingAlert;
            return createAlert('Nalaganje podatkov...', 'info', false);
        }

        function hideLoadingAlert(alertInstance) {
            if (alertInstance && alertInstance.parentElement) {
                alertInstance.classList.add('closing');
                setTimeout(() => {
                    if (alertInstance.parentElement) {
                        alertInstance.parentElement.removeChild(alertInstance);
                    }
                }, 300);
            } else { // Fallback: remove any loading alert
                const loadingAlert = document.querySelector('.alert-container .status-info');
                if (loadingAlert && loadingAlert.textContent.includes('Nalaganje')) {
                    loadingAlert.classList.add('closing');
                    setTimeout(() => {
                        if (loadingAlert.parentElement) {
                            loadingAlert.parentElement.removeChild(loadingAlert);
                        }
                    }, 300);
                }
            }
        }


        // --- Data Fetching and Page Update Functions ---
        function loadDynamicData(params = {}) {
            const queryParams = new URLSearchParams({
                fetch_json: 'true',
                class_subject_id: params.class_subject_id ?? G_SELECTED_CLASS_SUBJECT_ID,
                period_id: params.period_id ?? G_SELECTED_PERIOD_ID,
                date: params.date ?? G_SELECTED_DATE,
                filter_by_date: params.filter_by_date ?? G_FILTER_BY_DATE,
            });

            const loadingAlertInstance = showLoadingAlert();

            fetch(`attendance.php?${queryParams.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updatePageWithData(data);
                        // Update URL without fetch_json for bookmarking/refresh
                        const displayParams = new URLSearchParams({
                            class_subject_id: data.selectedClassSubjectId,
                            period_id: data.selectedPeriodId,
                            date: data.selectedDate,
                            filter_by_date: data.filterByDate ? '1' : '0',
                        });
                        // Remove params with 0 or false values for cleaner URL, except period_id if it's explicitly 0
                        if (!data.filterByDate) displayParams.delete('filter_by_date');
                        if (data.selectedPeriodId === 0 && queryParams.get('period_id') !== '0') { // if server defaulted to 0 and not explicitly requested
                            // keep period_id if it was explicitly set to 0 in request, otherwise remove if server defaulted
                        } else if (data.selectedPeriodId === 0) {
                            // displayParams.set('period_id', '0'); // keep it if it's 0
                        }


                        history.pushState(null, '', `attendance.php?${displayParams.toString()}`);
                    } else {
                        createAlert(data.message || 'Napaka pri nalaganju podatkov.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching dynamic data:', error);
                    createAlert('Napaka v komunikaciji s strežnikom.', 'error');
                })
                .finally(() => {
                    hideLoadingAlert(loadingAlertInstance);
                });
        }

        function updatePageWithData(data) {
            G_CSRF_TOKEN = data.csrfToken || G_CSRF_TOKEN; // Update CSRF token
            G_SELECTED_CLASS_SUBJECT_ID = data.selectedClassSubjectId;
            G_SELECTED_PERIOD_ID = data.selectedPeriodId;
            G_SELECTED_DATE = data.selectedDate;
            G_FILTER_BY_DATE = data.filterByDate;

            // Update filter controls state
            classSubjectSelect.value = data.selectedClassSubjectId;
            dateInput.value = data.selectedDate;
            filterByDateCheckbox.checked = data.filterByDate;
            dateInput.disabled = !data.filterByDate;
            addPeriodModalClassSubjectId.value = data.selectedClassSubjectId; // For "Add Period" modal
            addPeriodModalCsrfToken.value = G_CSRF_TOKEN;


            // Populate periods dropdown
            periodSelect.innerHTML = ''; // Clear existing options
            if (data.periods && data.periods.length > 0) {
                data.periods.forEach(period => {
                    const option = document.createElement('option');
                    option.value = period.period_id;
                    option.textContent = `${period.period_label} (${formatDateDisplayJS(period.period_date)})`;
                    if (period.period_id === data.selectedPeriodId) {
                        option.selected = true;
                    }
                    periodSelect.appendChild(option);
                });
            } else {
                const option = document.createElement('option');
                option.value = "0";
                option.textContent = `Ni ur${data.filterByDate ? ' za izbrani datum' : ''}`;
                periodSelect.appendChild(option);
            }
            periodSelect.value = data.selectedPeriodId; // Ensure correct selection

            // Update "Add Period" button state
            addPeriodButton.disabled = data.classIsEmpty;

            // Handle alerts
            classEmptyAlertContainer.style.display = data.classIsEmpty ? 'block' : 'none';
            noPeriodAlertContainer.style.display = (!data.classIsEmpty && !data.selectedPeriod && !data.attendanceError) ? 'block' : 'none';
            attendanceErrorAlertContainer.style.display = data.attendanceError ? 'block' : 'none';
            if (data.attendanceError) {
                attendanceErrorAlertContainer.innerHTML = `<div class="alert status-error"><div class="alert-content"><p>${data.attendanceErrorMessage}</p></div></div>`;
            }

            const dateErrorAlert = document.getElementById('dateErrorAlert');
            if (dateErrorAlert) dateErrorAlert.style.display = data.dateError ? 'block' : 'none';
            if (data.dateError && dateErrorAlert) dateErrorAlert.querySelector('p').textContent = data.dateError;


            // Populate attendance table
            attendanceTableBody.innerHTML = ''; // Clear existing rows
            if (data.selectedPeriod && !data.classIsEmpty && !data.attendanceError) {
                attendanceTableContainer.style.display = 'block';
                attendanceCardTitle.textContent = `${data.selectedPeriod.period_label} - ${formatDateDisplayJS(data.selectedPeriod.period_date)}`;

                if (data.students && data.students.length > 0) {
                    data.students.forEach(student => {
                        const record = data.attendanceRecords.find(ar => ar.student_id === student.student_id);
                        const status = record ? record.status : 'A';
                        const enrollId = record ? record.enroll_id : (student.enroll_id || 0);
                        const classId = data.classSubjectDetails ? data.classSubjectDetails.class_id : 0;

                        let statusClass = '';
                        switch (status) {
                            case 'P':
                                statusClass = 'status-present';
                                break;
                            case 'A':
                                statusClass = 'status-absent';
                                break;
                            case 'L':
                                statusClass = 'status-late';
                                break;
                        }

                        const row = attendanceTableBody.insertRow();
                        row.dataset.studentId = student.student_id;
                        row.dataset.enrollId = enrollId;
                        row.dataset.classId = classId;

                        row.insertCell().textContent = `${student.first_name} ${student.last_name}`;
                        row.insertCell().innerHTML = `<span class="attendance-status ${statusClass}">${getAttendanceStatusLabelJS(status)}</span>`;

                        const actionsCell = row.insertCell();
                        actionsCell.innerHTML = `
                            <div class="d-flex gap-xs">
                                <button type="button" class="btn btn-success btn-sm attendance-btn" data-status="P" data-period-id="${data.selectedPeriodId}">Prisoten</button>
                                <button type="button" class="btn btn-error btn-sm attendance-btn" data-status="A" data-period-id="${data.selectedPeriodId}">Odsoten</button>
                                <button type="button" class="btn btn-warning btn-sm attendance-btn" data-status="L" data-period-id="${data.selectedPeriodId}">Zamuda</button>
                            </div>`;
                    });
                    attachAttendanceButtonListeners();
                } else {
                    attendanceTableBody.innerHTML = `<tr><td colspan="3" class="text-center">V tem razredu ni vpisanih učencev.</td></tr>`;
                }
            } else {
                attendanceTableContainer.style.display = 'none';
            }

            // Update Homeroom Stats
            homeroomStatsSection.style.display = (data.isHomeroomTeacher && !data.classIsEmpty) ? 'block' : 'none';
            homeroomStatsGrid.innerHTML = '';
            if (data.isHomeroomTeacher && !data.classIsEmpty && data.students) {
                data.students.forEach(student => {
                    const stats = data.homeroomStudentStats[student.student_id] || {
                        present_percent: 0,
                        present_count: 0,
                        absent_count: 0,
                        late_count: 0,
                        total: 0
                    };
                    const rateClass = (stats.present_percent || 0) >= 90 ? 'text-success' : ((stats.present_percent || 0) >= 75 ? 'text-warning' : 'text-error');
                    const statCardHtml = `
                        <div class="card shadow-sm mb-sm">
                            <div class="card__title p-md d-flex justify-between items-center">
                                <span>${student.first_name} ${student.last_name}</span>
                                <span class="badge ${rateClass}">${Number(stats.present_percent || 0).toFixed(1)}%</span>
                            </div>
                            <div class="card__content p-md">
                                <div class="d-flex flex-column gap-sm">
                                    <div class="d-flex justify-between"><span>Prisoten:</span><span class="badge badge-success">${stats.present_count || 0}</span></div>
                                    <div class="d-flex justify-between"><span>Odsoten:</span><span class="badge badge-error">${stats.absent_count || 0}</span></div>
                                    <div class="d-flex justify-between"><span>Zamuda:</span><span class="badge badge-warning">${stats.late_count || 0}</span></div>
                                    <div class="d-flex justify-between mt-sm"><span>Skupaj učnih ur:</span><span class="font-bold">${stats.total || 0}</span></div>
                                </div>
                            </div>
                        </div>`;
                    homeroomStatsGrid.insertAdjacentHTML('beforeend', statCardHtml);
                });
            }
        }

        function handleFilterInteraction() {
            // Read current values from form elements to ensure they are up-to-date
            const params = {
                class_subject_id: classSubjectSelect.value,
                period_id: periodSelect.value, // This might be "0" if no periods or if "Ni ur" is selected
                date: dateInput.value,
                filter_by_date: filterByDateCheckbox.checked
            };
            // If period_id from select is "0" (meaning "Ni ur"), we want the server to auto-select latest.
            // So, if period_id is "0", we can send 0 or let server logic handle it.
            // The server logic already handles period_id=0 by selecting the latest.
            if (params.period_id === "0" && periodSelect.options.length === 1 && periodSelect.options[0].value === "0") {
                // This means "Ni ur" is the only option. Server will correctly handle period_id=0.
            }

            loadDynamicData(params);
        }


        // --- Event Listeners ---
        classSubjectSelect.addEventListener('change', () => {
            G_SELECTED_CLASS_SUBJECT_ID = parseInt(classSubjectSelect.value);
            G_SELECTED_PERIOD_ID = 0; // Reset period ID to allow server to pick latest for new class/subject
            handleFilterInteraction();
        });

        periodSelect.addEventListener('change', () => {
            G_SELECTED_PERIOD_ID = parseInt(periodSelect.value);
            handleFilterInteraction();
        });

        filterByDateCheckbox.addEventListener('change', () => {
            G_FILTER_BY_DATE = filterByDateCheckbox.checked;
            dateInput.disabled = !G_FILTER_BY_DATE;
            G_SELECTED_PERIOD_ID = 0; // Reset period selection when date filter changes
            handleFilterInteraction();
        });

        dateInput.addEventListener('change', () => {
            G_SELECTED_DATE = dateInput.value;
            G_SELECTED_PERIOD_ID = 0; // Reset period selection when date changes
            handleFilterInteraction();
        });


        // --- Modal Management (local, as per existing file structure) ---
        const openModal = (modalId) => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                const firstFocusable = modal.querySelector('button, [href], input, select, textarea');
                if (firstFocusable) firstFocusable.focus();
                if (modalId === 'addPeriodModal') {
                    const classSubjectOpt = classSubjectSelect.options[classSubjectSelect.selectedIndex];
                    const subjectName = classSubjectOpt ? classSubjectOpt.text.split(' - ')[0] : '';
                    const today = new Date();
                    const formattedDate = today.getDate() + '.' + (today.getMonth() + 1) + '.';
                    document.getElementById('periodLabel').value = subjectName + ' ' + formattedDate;
                    document.getElementById('periodDateError').style.display = 'none';
                    document.getElementById('periodLabelError').style.display = 'none';
                    // Ensure current class_subject_id and CSRF are set for the modal form
                    addPeriodModalClassSubjectId.value = G_SELECTED_CLASS_SUBJECT_ID;
                    addPeriodModalCsrfToken.value = G_CSRF_TOKEN;
                }
            }
        };

        const closeModal = (modal) => {
            if (typeof modal === 'string') modal = document.getElementById(modal);
            if (modal) {
                modal.classList.remove('open');
                const form = modal.querySelector('form');
                if (form) form.reset();
                const errorMsgs = modal.querySelectorAll('.feedback-error');
                errorMsgs.forEach(msg => {
                    if (msg && msg.style) msg.style.display = 'none';
                });
            }
        };

        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                if (this.disabled) return;
                openModal(this.dataset.openModal);
            });
        });
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') document.querySelectorAll('.modal.open').forEach(closeModal);
        });

        // --- Alert Creation (local) ---
        function createAlert(message, type = 'info', autoRemove = true, duration = 5000) {
            const alertContainer = document.querySelector('.alert-container');
            if (!alertContainer) {
                console.warn('Alert container not found. Cannot display alert:', message);
                return null;
            }
            const alertElement = document.createElement('div');
            alertElement.className = `alert status-${type} card-entrance`;
            const contentElement = document.createElement('div');
            contentElement.className = 'alert-content';
            contentElement.innerHTML = message; // Note: For security, consider textContent if message is not HTML
            alertElement.appendChild(contentElement);
            alertContainer.appendChild(alertElement);
            if (autoRemove) {
                setTimeout(() => {
                    alertElement.classList.add('closing');
                    setTimeout(() => {
                        if (alertElement.parentElement) alertElement.parentElement.removeChild(alertElement);
                    }, 300);
                }, duration);
            }
            return alertElement;
        }

        // Validate period date (not too far in future or past)
        function validatePeriodDate(date) {
            const inputDate = new Date(date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const maxFutureDate = new Date(today);
            maxFutureDate.setDate(today.getDate() + 30);
            const maxPastDate = new Date(today);
            maxPastDate.setDate(today.getDate() - 180);
            if (inputDate > maxFutureDate) return {
                valid: false,
                message: 'Datum ne more biti več kot 30 dni v prihodnosti.'
            };
            if (inputDate < maxPastDate) return {
                valid: false,
                message: 'Datum ne more biti več kot 180 dni v preteklosti.'
            };
            return {valid: true};
        }

        // Handle "Add Period" form
        document.getElementById('addPeriodForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const periodDate = document.getElementById('periodDate').value;
            const periodLabel = document.getElementById('periodLabel').value;
            let hasErrors = false;

            const dateValidation = validatePeriodDate(periodDate);
            if (!dateValidation.valid) {
                document.getElementById('periodDateError').textContent = dateValidation.message;
                document.getElementById('periodDateError').style.display = 'block';
                hasErrors = true;
            } else {
                document.getElementById('periodDateError').style.display = 'none';
            }

            if (!periodLabel || periodLabel.trim().length < 2) {
                document.getElementById('periodLabelError').textContent = 'Naziv ure mora vsebovati vsaj 2 znaka.';
                document.getElementById('periodLabelError').style.display = 'block';
                hasErrors = true;
            } else if (periodLabel.trim().length > 50) {
                document.getElementById('periodLabelError').textContent = 'Naziv ure ne sme presegati 50 znakov.';
                document.getElementById('periodLabelError').style.display = 'block';
                hasErrors = true;
            } else {
                document.getElementById('periodLabelError').style.display = 'none';
            }

            if (!hasErrors) {
                const formData = new FormData(this);
                fetch('../api/attendance.php', {method: 'POST', body: formData})
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            createAlert('Učna ura je bila uspešno dodana.', 'success');
                            closeModal('addPeriodModal');
                            // Refresh page data, try to select the new period if API returns its ID
                            // Server logic for period_id=0 should pick the latest, which would be the new one.
                            G_SELECTED_PERIOD_ID = data.period_id || 0; // Hint for selection
                            loadDynamicData({period_id: G_SELECTED_PERIOD_ID});
                        } else {
                            createAlert(data.message || 'Napaka pri dodajanju učne ure.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        createAlert('Prišlo je do napake.', 'error');
                    });
            }
        });

        // --- Attendance Button Handling ---
        function attachAttendanceButtonListeners() {
            document.querySelectorAll('.attendance-btn').forEach(btn => {
                // Remove existing listener before adding a new one to prevent duplicates
                // This is a simple way; a more robust way would be to use a single delegated listener on table body
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);

                newBtn.addEventListener('click', function () {
                    const status = this.dataset.status;
                    const periodId = this.dataset.periodId;
                    const row = this.closest('tr');
                    const enrollId = row.dataset.enrollId;
                    const studentId = row.dataset.studentId;
                    const classId = row.dataset.classId;

                    const buttonsInRow = row.querySelectorAll('.attendance-btn');
                    buttonsInRow.forEach(b => b.disabled = true);

                    const formData = new FormData();
                    formData.append('csrf_token', G_CSRF_TOKEN);
                    formData.append('enroll_id', enrollId);
                    formData.append('period_id', periodId);
                    formData.append('status', status);
                    formData.append('student_id', studentId);
                    formData.append('class_id', classId);

                    fetch('../api/attendance.php', {method: 'POST', body: formData})
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const statusCell = row.querySelector('.attendance-status');
                                statusCell.className = 'attendance-status'; // Reset classes
                                let newStatusClass = '';
                                switch (status) {
                                    case 'P':
                                        newStatusClass = 'status-present';
                                        break;
                                    case 'A':
                                        newStatusClass = 'status-absent';
                                        break;
                                    case 'L':
                                        newStatusClass = 'status-late';
                                        break;
                                }
                                statusCell.classList.add(newStatusClass);
                                statusCell.textContent = getAttendanceStatusLabelJS(status);
                                if (data.enroll_id) row.dataset.enrollId = data.enroll_id; // Update enroll_id if it was created
                                createAlert('Status je bil uspešno posodobljen.', 'success', true, 2000);
                            } else {
                                createAlert(data.message || 'Napaka pri posodabljanju statusa.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            createAlert('Prišlo je do napake.', 'error');
                        })
                        .finally(() => {
                            buttonsInRow.forEach(b => b.disabled = false);
                        });
                });
            });
        }

        // Initial attachment of listeners for buttons loaded by PHP
        attachAttendanceButtonListeners();
    });
</script>

<?php include_once '../includes/footer.php'; ?>
