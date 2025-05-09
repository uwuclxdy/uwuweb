<?php
/**
 * Teacher Justification Approval Page
 *
 * Allows teachers to view and approve/reject student absence justifications
 *
 * /uwuweb/teacher/justifications.php
 */

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'teacher_functions.php';

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId(getUserId());
if (!$teacherId) die('Napaka: Učiteljev račun ni bil najden.');

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna predložitev obrazca. Prosimo, poskusite znova.';
    $messageType = 'error';
} else if (isset($_POST['approve_justification'])) {
    $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;

    if ($absenceId <= 0) {
        $message = 'Neveljavna odsotnost izbrana.';
        $messageType = 'error';
    } else if (approveJustification($absenceId)) {
        $message = 'Opravičilo uspešno odobreno.';
        $messageType = 'success';
    } else {
        $message = 'Napaka pri odobritvi opravičila.';
        $messageType = 'error';
    }
} else if (isset($_POST['reject_justification'])) {
    $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
    $rejectionReason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : '';

    if ($absenceId <= 0) {
        $message = 'Neveljavna odsotnost izbrana.';
        $messageType = 'error';
    } else if (empty($rejectionReason)) {
        $message = 'Prosimo, navedite razlog za zavrnitev.';
        $messageType = 'error';
    } else if (rejectJustification($absenceId, $rejectionReason)) {
        $message = 'Opravičilo uspešno zavrnjeno.';
        $messageType = 'success';
    } else {
        $message = 'Napaka pri zavrnitvi opravičila.';
        $messageType = 'error';
    }
}

// Get pending justifications
$pendingJustifications = getPendingJustifications($teacherId);
$selectedJustificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedJustification = $selectedJustificationId ? getJustificationById($selectedJustificationId) : null;

// Generate CSRF token
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    die('Napaka pri generiranju varnostnega žetona.');
}
?>

<!-- Page header card with title and role indicator -->
<div class="card shadow mb-lg mt-lg page-transition">
    <div class="d-flex justify-between items-center">
        <div>
            <h2 class="mt-0 mb-xs">Opravičila odsotnosti</h2>
            <p class="text-secondary mt-0 mb-0">Pregled in obdelava opravičil dijakov</p>
        </div>
        <div class="role-badge role-teacher">Učitelj</div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert status-<?= $messageType === 'success' ? 'success' : 'error' ?> mb-lg">
        <div class="alert-icon">
            <?php if ($messageType === 'success'): ?>✓
            <?php else: ?>✕
            <?php endif; ?>
        </div>
        <div class="alert-content">
            <?= htmlspecialchars($message) ?>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Justification list sidebar -->
    <div class="col col-md-4">
        <div class="card shadow mb-lg">
            <div class="card__title">
                <div class="d-flex justify-between items-center">
                    <span>Čakajoča opravičila</span>
                    <span class="badge badge-<?= !empty($pendingJustifications) ? 'warning' : 'success' ?>">
                        <?= count($pendingJustifications) ?>
                    </span>
                </div>
            </div>
            <div class="card__content">
                <?php if (empty($pendingJustifications)): ?>
                    <div class="alert status-success mb-0">
                        <div class="alert-icon">✓</div>
                        <div class="alert-content">
                            Ni čakajočih opravičil za pregled.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-sm">
                        <?php foreach ($pendingJustifications as $justification): ?>
                            <a href="/uwuweb/teacher/justifications.php?id=<?= $justification['absence_id'] ?>"
                               class="card p-sm <?= $selectedJustificationId == $justification['absence_id'] ? 'shadow-sm' : '' ?>"
                               style="text-decoration: none;">
                                <div class="d-flex justify-between">
                                    <span class="text-primary">
                                        <?= htmlspecialchars($justification['student_name']) ?>
                                    </span>
                                    <span class="text-secondary text-sm">
                                        <?= date('d.m.Y', strtotime($justification['absence_date'])) ?>
                                    </span>
                                </div>
                                <div class="text-secondary text-sm mt-xs">
                                    <?= htmlspecialchars(substr($justification['reason'], 0, 50)) ?>
                                    <?= strlen($justification['reason']) > 50 ? '...' : '' ?>
                                </div>
                                <div class="d-flex justify-between mt-xs">
                                    <span class="text-sm"><?= htmlspecialchars($justification['class_name']) ?></span>
                                    <span class="badge badge-secondary text-xs">
                                        <?= date('d.m.Y', strtotime($justification['submitted_at'])) ?>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Justification details and actions -->
    <div class="col col-md-8">
        <?php if ($selectedJustification): ?>
            <div class="card shadow mb-lg">
                <div class="card__title">
                    Podrobnosti opravičila
                </div>
                <div class="card__content">
                    <div class="d-flex justify-between mb-lg">
                        <div>
                            <div class="text-secondary text-sm">Dijak</div>
                            <div class="text-lg">
                                <?= htmlspecialchars($selectedJustification['student_name']) ?>
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary text-sm">Razred</div>
                            <div>
                                <?= htmlspecialchars($selectedJustification['class_name']) ?>
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary text-sm">Datum odsotnosti</div>
                            <div>
                                <?= date('d.m.Y', strtotime($selectedJustification['absence_date'])) ?>
                            </div>
                        </div>
                        <div>
                            <div class="text-secondary text-sm">Predloženo</div>
                            <div>
                                <?= date('d.m.Y', strtotime($selectedJustification['submitted_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Razlog odsotnosti</label>
                        <div class="card p-md bg-tertiary">
                            <?= nl2br(htmlspecialchars($selectedJustification['reason'])) ?>
                        </div>
                    </div>

                    <?php if (!empty($selectedJustification['file_path'])): ?>
                        <div class="form-group">
                            <label class="form-label">Podporna dokumentacija</label>
                            <div class="d-flex gap-md items-center p-md rounded">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                    <polyline points="10 9 9 9 8 9"></polyline>
                                </svg>
                                <div class="d-flex flex-column">
                                    <span class="text-primary"><?= htmlspecialchars(basename($selectedJustification['file_path'])) ?></span>
                                    <span class="text-secondary text-sm"><?= formatFileSize($selectedJustification['file_size'] ?? 0) ?></span>
                                </div>
                                <a href="/uwuweb/teacher/download_justification.php?id=<?= $selectedJustification['absence_id'] ?>"
                                   class="btn btn-secondary btn-sm ml-auto">
                                    Prenesi
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card__footer">
                    <div class="d-flex justify-end gap-md">
                        <button class="btn btn-secondary" data-open-modal="rejectModal">Zavrni</button>
                        <form method="POST" action="justifications.php?id=<?= $selectedJustification['absence_id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="absence_id" value="<?= $selectedJustification['absence_id'] ?>">
                            <input type="hidden" name="approve_justification" value="1">
                            <button type="submit" class="btn btn-primary">Odobri</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Attendance Record -->
            <div class="card shadow mb-lg">
                <div class="card__title">
                    Povezani zapisi prisotnosti
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Ura</th>
                            <th>Status</th>
                            <th>Opombe</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $attendanceRecords = getStudentAttendanceByDate($selectedJustification['student_id'], $selectedJustification['absence_date']);
                        if (empty($attendanceRecords)):
                            ?>
                            <tr>
                                <td colspan="4" class="text-center">
                                    <div class="alert status-info">
                                        <div class="alert-icon">ℹ</div>
                                        <div class="alert-content">
                                            Za ta datum ni najdenih zapisov prisotnosti.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendanceRecords as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['date']) ?></td>
                                    <td><?= htmlspecialchars($record['period_label']) ?></td>
                                    <td>
                                            <span class="attendance-status status-<?= $record['status'] ?>">
                                                <?= $record['status'] === 'present' ? 'Prisoten' : ($record['status'] === 'absent' ? 'Odsoten' : 'Zamudil') ?>
                                            </span>
                                    </td>
                                    <td><?= htmlspecialchars($record['notes'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Rejection Modal -->
            <div class="modal" id="rejectModal">
                <div class="modal-overlay" aria-hidden="true"></div>
                <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
                    <div class="modal-header">
                        <h3 class="modal-title" id="rejectModalTitle">Zavrni opravičilo</h3>
                    </div>
                    <form id="rejectForm" method="POST"
                          action="justifications.php?id=<?= $selectedJustification['absence_id'] ?>">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="absence_id" value="<?= $selectedJustification['absence_id'] ?>">
                            <input type="hidden" name="reject_justification" value="1">

                            <div class="form-group">
                                <label class="form-label" for="rejection_reason">Razlog za zavrnitev:</label>
                                <textarea id="rejection_reason" name="rejection_reason" class="form-input" rows="4"
                                          required
                                          placeholder="Prosimo, navedite razlog, zakaj je to opravičilo zavrnjeno..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                            <button type="submit" class="btn btn-primary">Potrdi zavrnitev</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif (!empty($pendingJustifications)): ?>
            <div class="card shadow">
                <div class="card__content text-center p-xl">
                    <div class="alert status-info mb-lg">
                        <div class="alert-icon">ℹ</div>
                        <div class="alert-content">
                            Izberite opravičilo s seznama za pregled.
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow">
                <div class="card__content text-center p-xl">
                    <div class="alert status-success mb-lg">
                        <div class="alert-icon">✓</div>
                        <div class="alert-content">
                            Ni čakajočih opravičil za pregled.
                        </div>
                    </div>
                    <p class="text-secondary">Vsa opravičila dijakov so bila obdelana. Dobro opravljeno!</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include page footer
include '../includes/footer.php';
?>
