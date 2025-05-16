<?php
/**
 * Attendance Page
 * Path: parent/attendance.php
 *
 * Allows parents to view attendance records for their linked students in read-only mode.
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

// Get justifications for the selected student
$justifications = [];
if ($selectedStudentId) try {
    $stmt = $pdo->prepare("
        SELECT a.att_id, a.status, a.justification, a.approved, a.reject_reason, a.justification_file,
               p.period_date as date, p.period_label,
               cs.class_id, c.class_code, c.title as class_title,
               s.name as subject_name
        FROM attendance a
        JOIN periods p ON a.period_id = p.period_id
        JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
        JOIN classes c ON cs.class_id = c.class_id
        JOIN subjects s ON cs.subject_id = s.subject_id
        JOIN enrollments e ON a.enroll_id = e.enroll_id
        WHERE e.student_id = ? AND (a.justification IS NOT NULL OR a.justification_file IS NOT NULL)
        ORDER BY p.period_date DESC
    ");
    $stmt->execute([$selectedStudentId]);
    $justifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $justifications = [];
}

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
                <div class="form-group mb-sm">
                    <button type="submit" class="btn btn-primary">
                        Pokaži prisotnost
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($selectedStudentId && $selectedStudent): ?>
    <div class="row mb-lg gap-lg">
        <div class="col col-md-4">
            <div class="card shadow h-100 card-entrance">
                <div class="card__title">
                    <div class="d-flex justify-between items-center">
                        <span>Povzetek prisotnosti</span>
                        <span class="badge role-badge role-parent"><?= htmlspecialchars($selectedStudent['class_code']) ?></span>
                    </div>
                </div>
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

        <div class="col col-md-7">
            <div class="card shadow h-100 card-entrance">
                <div class="card__title">Vizualizacija prisotnosti</div>
                <div class="card__content">
                    <?php if (!empty($attendanceStats) && $attendanceStats['total'] > 0): ?>
                        <div class="mb-lg">
                            <div class="d-flex"
                                 style="height: 36px; background-color: var(--bg-tertiary); border-radius: var(--button-radius); overflow: hidden;">
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
                        <div class="d-flex justify-around flex-wrap gap-md">
                            <div class="d-flex flex-column items-center">
                                <div class="d-flex items-center gap-xs mb-sm">
                                    <span class="status-indicator status-present rounded-full"
                                          style="width: 12px; height: 12px;"></span>
                                    <span>Prisoten</span>
                                </div>
                                <span class="font-bold text-xl"><?= number_format($attendanceStats['presentRate'], 1) ?>%</span>
                            </div>
                            <div class="d-flex flex-column items-center">
                                <div class="d-flex items-center gap-xs mb-sm">
                                    <span class="status-indicator status-late rounded-full"
                                          style="width: 12px; height: 12px;"></span>
                                    <span>Zamuda</span>
                                </div>
                                <span class="font-bold text-xl"><?= number_format($attendanceStats['lateRate'], 1) ?>%</span>
                            </div>
                            <div class="d-flex flex-column items-center">
                                <div class="d-flex items-center gap-xs mb-sm">
                                    <span class="status-indicator status-absent rounded-full"
                                          style="width: 12px; height: 12px;"></span>
                                    <span>Odsoten</span>
                                </div>
                                <span class="font-bold text-xl"><?= number_format($attendanceStats['absentRate'], 1) ?>%</span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert status-info">
                            <div class="alert-content">
                                <p>Za tega učenca ni podatkov o prisotnosti za vizualizacijo.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-lg card-entrance">
        <div class="card__title">Opravičila za odsotnosti</div>
        <div class="card__content">
            <?php if (!empty($justifications)): ?>
                <div class="table-responsive">
                    <table class="data-table w-full">
                        <thead>
                        <tr>
                            <th>Datum odsotnosti</th>
                            <th>Predmet</th>
                            <th>Učna ura</th>
                            <th>Razlog</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($justifications as $j): ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars(formatDateDisplay($j['date'])) ?></td>
                                <td><?= htmlspecialchars($j['subject_name']) ?></td>
                                <td><?= htmlspecialchars($j['period_label']) ?></td>
                                <td><?= nl2br(htmlspecialchars($j['justification'] ?? '—')) ?></td>
                                <td>
                                    <?php
                                    if ($j['approved'] === null) {
                                        $statusClass = 'status-info';
                                        $statusText = 'V obravnavi';
                                    } elseif ($j['approved'] === 1) {
                                        $statusClass = 'status-success';
                                        $statusText = 'Odobreno';
                                    } else {
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
                <div class="alert status-info">
                    <div class="alert-content">
                        <p>Za tega učenca ni bilo oddanih opravičil za odsotnost.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow mb-lg card-entrance">
        <div class="card__title d-flex justify-between items-center">
            <span>Podrobni zapisi o prisotnosti</span>
            <span class="badge role-badge role-parent"><?= htmlspecialchars($selectedStudent['first_name'] . ' ' . $selectedStudent['last_name']) ?></span>
        </div>
        <div class="card__content">
            <?php if (!empty($attendanceRecords)): ?>
                <div class="table-responsive">
                    <table class="data-table w-full">
                        <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Predmet</th>
                            <th>Učna ura</th>
                            <th>Status</th>
                            <th>Opravičilo</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <?php
                            $statusClass = '';
                            switch ($record['status']) {
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
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars(formatDateDisplay($record['date'])) ?></td>
                                <td><?= htmlspecialchars($record['subject_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($record['period_label']) ?></td>
                                <td>
                                    <span class="badge attendance-status <?= $statusClass ?>">
                                        <?= getAttendanceStatusLabel($record['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($record['justification'])): ?>
                                        <span class="badge status-info">Opravičeno</span>
                                    <?php elseif ($record['status'] === 'A' || $record['status'] === 'L'): ?>
                                        <span class="text-disabled">Ni opravičila</span>
                                    <?php else: ?>
                                        <span class="text-disabled">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert status-info">
                    <div class="alert-content">
                        <p>Za tega učenca ni zapisov o prisotnosti.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php elseif (!empty($students) && count($students) === 1): ?>
    <div class="alert status-info mb-lg">
        <div class="alert-content">
            <p>Prikazujemo prisotnost za vašega edinega
                otroka: <?= htmlspecialchars($students[0]['first_name'] . ' ' . $students[0]['last_name']) ?>.</p>
        </div>
    </div>
    <div class="alert status-warning">
        <div class="alert-content">
            <p>Za <?= htmlspecialchars($students[0]['first_name'] . ' ' . $students[0]['last_name']) ?> ni podatkov o
                prisotnosti.</p>
        </div>
    </div>
<?php elseif (!empty($students) && count($students) > 1): ?>
    <div class="card shadow card-entrance">
        <div class="card__content text-center p-xl">
            <div class="alert status-info">
                <div class="alert-content">
                    <p>Prosimo, izberite otroka iz spustnega menija zgoraj za ogled njegove evidence prisotnosti.</p>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow card-entrance">
        <div class="card__content text-center p-xl">
            <div class="alert status-warning mb-lg">
                <div class="alert-content">
                    <p>Trenutno nimate otrok, povezanih z vašim računom.</p>
                </div>
            </div>
            <p class="text-secondary">Prosimo, obrnite se na šolskega administratorja, če menite, da gre za
                napako.</p>
        </div>
    </div>
<?php endif; ?>

<?php
include '../includes/footer.php';
?>
