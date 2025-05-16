<?php
/**
 * Purpose: Allows parents to view attendance records for their linked students in read-only mode.
 * Path: parent/attendance.php
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once '../parent/parent_functions.php';

requireRole(ROLE_PARENT);

$parentId = getParentId();
if (!$parentId) die('Napaka: Starševski račun ni najden.');

$pdo = safeGetDBConnection('parent/attendance.php');

$students = getParentStudents($parentId);

$selectedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($students[0]['student_id'] ?? 0);

$attendanceRecords = $selectedStudentId ? getStudentAttendance($selectedStudentId) : [];

$selectedStudent = null;
if ($selectedStudentId) foreach ($students as $student) if ($student['student_id'] == $selectedStudentId) {
    $selectedStudent = $student;
    break;
}

$attendanceStats = [];
if ($selectedStudent && !empty($attendanceRecords)) {
    $total = count($attendanceRecords);
    $present = 0;
    $absent = 0;
    $late = 0;

    foreach ($attendanceRecords as $record) switch ($record['status']) {
        case 'P':
            $present++;
            break;
        case 'A':
            $absent++;
            break;
        case 'L':
            $late++;
            break;
    }

    $attendanceStats = [
        'total' => $total,
        'present' => $present,
        'absent' => $absent,
        'late' => $late,
        'presentRate' => $total > 0 ? ($present / $total) * 100 : 0,
        'absentRate' => $total > 0 ? ($absent / $total) * 100 : 0,
        'lateRate' => $total > 0 ? ($late / $total) * 100 : 0
    ];
}
?>

<div class="container mt-lg mb-lg">
    <?php
    renderHeaderCard(
        'Prisotnost učenca',
        'Spremljajte podatke o prisotnosti vašega otroka.',
        'parent'
    );
    ?>

    <?php if (count($students) > 1): ?>
        <div class="card shadow mb-lg">
            <div class="card__content">
                <form method="GET" action="attendance.php" class="d-flex items-end gap-md flex-wrap">
                    <div class="form-group mb-0 flex-grow-1">
                        <label for="student_id" class="form-label">Izberite otroka:</label>
                        <select id="student_id" name="student_id" class="form-input form-select">
                            <option value="">-- Izberite otroka --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['student_id'] ?>" <?= $selectedStudentId == $student['student_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <button type="submit" class="btn btn-primary">Pokaži prisotnost</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selectedStudentId && $selectedStudent): ?>
        <div class="row mb-lg gap-lg">
            <div class="col col-md-4">
                <div class="card shadow h-100">
                    <div class="card__title">Povzetek prisotnosti</div>
                    <div class="card__content">
                        <div class="d-flex flex-column gap-md">
                            <div class="d-flex justify-between items-center">
                                <span class="text-secondary">Prisoten:</span>
                                <div class="d-flex gap-sm items-center">
                                    <span class="badge status-present">
                                        <?= $attendanceStats['present'] ?? 0 ?>
                                    </span>
                                    <span class="text-sm text-secondary">(<?= number_format($attendanceStats['presentRate'] ?? 0, 1) ?>%)</span>
                                </div>
                            </div>
                            <div class="d-flex justify-between items-center">
                                <span class="text-secondary">Odsoten:</span>
                                <div class="d-flex gap-sm items-center">
                                    <span class="badge status-absent">
                                        <?= $attendanceStats['absent'] ?? 0 ?>
                                    </span>
                                    <span class="text-sm text-secondary">(<?= number_format($attendanceStats['absentRate'] ?? 0, 1) ?>%)</span>
                                </div>
                            </div>
                            <div class="d-flex justify-between items-center">
                                <span class="text-secondary">Zamuda:</span>
                                <div class="d-flex gap-sm items-center">
                                    <span class="badge status-late">
                                        <?= $attendanceStats['late'] ?? 0 ?>
                                    </span>
                                    <span class="text-sm text-secondary">(<?= number_format($attendanceStats['lateRate'] ?? 0, 1) ?>%)</span>
                                </div>
                            </div>
                            <hr class="border-color-medium my-md">
                            <div class="d-flex justify-between mb-xs">
                                <span class="text-secondary font-medium">Vsi zapisi:</span>
                                <span class="font-medium"><?= $attendanceStats['total'] ?? 0 ?></span>
                            </div>
                            <div class="d-flex justify-between">
                                <span class="text-secondary font-medium">Stopnja prisotnosti:</span>
                                <?php
                                $presenceRate = $attendanceStats['presentRate'] ?? 0;
                                $rateClass = 'text-error';
                                if ($presenceRate >= 90) $rateClass = 'text-success'; elseif ($presenceRate >= 75) $rateClass = 'text-warning';
                                ?>
                                <span class="font-bold <?= $rateClass ?>">
                                    <?= number_format($presenceRate, 1) ?>%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col col-md-8">
                <div class="card shadow h-100">
                    <div class="card__title">Vizualizacija prisotnosti</div>
                    <div class="card__content">
                        <?php if (!empty($attendanceStats) && $attendanceStats['total'] > 0): ?>
                            <div class="mb-md">
                                <div class="d-flex"
                                     style="height: 24px; background-color: var(--bg-tertiary); border-radius: var(--button-radius); overflow: hidden;">
                                    <div class="status-present"
                                         title="Prisoten: <?= number_format($attendanceStats['presentRate'], 1) ?>%"
                                         style="width: <?= $attendanceStats['presentRate'] ?>%; height: 100%;"></div>
                                    <div class="status-late"
                                         title="Zamuda: <?= number_format($attendanceStats['lateRate'], 1) ?>%"
                                         style="width: <?= $attendanceStats['lateRate'] ?>%; height: 100%;"></div>
                                    <div class="status-absent"
                                         title="Odsoten: <?= number_format($attendanceStats['absentRate'], 1) ?>%"
                                         style="width: <?= $attendanceStats['absentRate'] ?>%; height: 100%;"></div>
                                </div>
                            </div>
                            <div class="d-flex justify-around flex-wrap gap-sm text-sm">
                                <div class="d-flex items-center gap-xs">
                                    <span class="status-indicator status-present rounded-full"
                                          style="width: 12px; height: 12px;"></span>
                                    <span>Prisoten</span>
                                </div>
                                <div class="d-flex items-center gap-xs">
                                    <span class="status-indicator status-late rounded-full"
                                          style="width: 12px; height: 12px;"></span>
                                    <span>Zamuda</span>
                                </div>
                                <div class="d-flex items-center gap-xs">
                                    <span class="status-indicator status-absent rounded-full"
                                          style="width: 12px; height: 12px;"></span>
                                    <span>Odsoten</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert status-info d-flex items-center gap-sm">
                                <span>Za tega učenca ni podatkov o prisotnosti za vizualizacijo.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-lg">
            <div class="card__title d-flex justify-between items-center">
                <span>Podrobni zapisi o prisotnosti</span>
                <span class="text-sm text-secondary">Učenec: <?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></span>
            </div>
            <div class="card__content">
                <?php if (!empty($attendanceRecords)): ?>
                    <div class="table-responsive">
                        <table class="data-table w-full">
                            <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Razred</th>
                                <th>Učna ura</th>
                                <th>Status</th>
                                <th>Opombe</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($attendanceRecords as $record): ?>
                                <tr>
                                    <td class="text-nowrap"><?= htmlspecialchars(formatDateDisplay($record['date'])) ?></td>
                                    <td><?= htmlspecialchars($record['class_name']) ?></td>
                                    <td><?= htmlspecialchars($record['period_label']) ?></td>
                                    <td>
                                        <span class="badge attendance-status status-<?= strtolower($record['status']) ?>">
                                            <?= htmlspecialchars($record['status_label']) ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($record['justification']) ? htmlspecialchars($record['justification']) : '<span class="text-disabled">—</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert status-info d-flex items-center gap-sm">
                        <span>Za tega učenca ni zapisov o prisotnosti.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow mb-lg">
            <div class="card__title">Opravičila za odsotnosti</div>
            <div class="card__content">
                <?php
                // todo: Fetch justifications from the database
                $justifications = [];
                if (!empty($justifications)):
                    ?>
                    <div class="table-responsive">
                        <table class="data-table w-full">
                            <thead>
                            <tr>
                                <th>Datum oddaje</th>
                                <th>Datum odsotnosti</th>
                                <th>Razlog</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($justifications as $justification): ?>
                                <tr>
                                    <td class="text-nowrap"><?= htmlspecialchars(formatDateTimeDisplay($justification['submitted_date'])) ?></td>
                                    <td class="text-nowrap"><?= htmlspecialchars(formatDateDisplay($justification['absence_date'])) ?></td>
                                    <td><?= nl2br(htmlspecialchars($justification['reason'])) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = 'status-info';
                                        $statusText = 'V obravnavi';
                                        if ($justification['status'] === 'approved') {
                                            $statusClass = 'status-success';
                                            $statusText = 'Odobreno';
                                        } else if ($justification['status'] === 'rejected') {
                                            $statusClass = 'status-error';
                                            $statusText = 'Zavrnjeno';
                                        }
                                        ?>
                                        <span class="badge <?= $statusClass ?>">
                                            <?= $statusText ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert status-info d-flex items-center gap-sm">
                        <span>Za tega učenca ni bilo oddanih opravičil za odsotnost.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif (!empty($students) && count($students) === 1): ?>
        <div class="alert status-info d-flex items-center gap-sm mb-lg">
            <span>Prikazujemo prisotnost za vašega edinega otroka: <?= htmlspecialchars($students[0]['first_name'] . ' ' . $students[0]['last_name']) ?>.</span>
        </div>
        <div class="alert status-warning d-flex items-center gap-sm">
            <span>Za <?= htmlspecialchars($students[0]['first_name'] . ' ' . $students[0]['last_name']) ?> ni podatkov o prisotnosti.</span>
        </div>
    <?php elseif (!empty($students) && count($students) > 1): ?>
        <div class="card shadow">
            <div class="card__content text-center p-xl">
                <div class="alert status-info d-flex items-center justify-center gap-sm mb-lg">
                    <span>Prosimo, izberite otroka iz spustnega menija zgoraj za ogled njegove evidence prisotnosti.</span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow">
            <div class="card__content text-center p-xl">
                <div class="alert status-warning d-flex items-center justify-center gap-sm mb-lg">
                    <span>Trenutno nimate otrok, povezanih z vašim računom.</span>
                </div>
                <p class="text-secondary">Prosimo, obrnite se na šolskega administratorja, če menite, da gre za
                    napako.</p>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
include '../includes/footer.php';
?>
