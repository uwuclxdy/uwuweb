<?php
/**
 * Student Absence Justification Page
 *
 * Allows students to view their absences and submit justifications
 *
 * Functions:
 * - getStudentAbsences($studentId) - Gets list of absences for a student
 * - getSubjectNameById($subjectId) - Gets subject name by ID
 * - uploadJustification($absenceId, $justification) - Uploads a justification for an absence
 * - validateJustificationFile($file) - Validates an uploaded justification file
 * - saveJustificationFile($file, $absenceId) - Saves an uploaded justification file
 * - getJustificationFileInfo($absenceId) - Gets information about a saved justification file
 */

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'student_functions.php';

// Ensure only students can access this page
requireRole(ROLE_STUDENT);

// Get the student ID of the logged-in user
$studentId = getStudentId();
if (!$studentId) die('Napaka: Študentski račun ni bil najden.');

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
    $messageType = 'error';
} else {
    // Process justification submission
    $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
    $justification = isset($_POST['justification']) ? trim($_POST['justification']) : '';

    if ($absenceId <= 0) {
        $message = 'Izbrana neveljavna odsotnost.';
        $messageType = 'error';
    } elseif (empty($justification)) {
        $message = 'Navedite sporočilo za opravičilo.';
        $messageType = 'error';
    } else if (uploadJustification($absenceId, $justification)) {
        // Handle file upload if provided
        if (isset($_FILES['justification_file']) && $_FILES['justification_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $fileResult = validateJustificationFile($_FILES['justification_file']);
            if ($fileResult === true) if (saveJustificationFile($_FILES['justification_file'], $absenceId)) $message = 'Opravičilo uspešno poslano s priloženo datoteko.'; else $message = 'Opravičilo je bilo poslano, vendar je prišlo do napake pri shranjevanju vaše datoteke.'; else $message = 'Opravičilo je bilo poslano, vendar datoteka ni bila shranjena: ' . $fileResult;
        } else $message = 'Opravičilo uspešno poslano.';
        $messageType = 'success';
    } else {
        $message = 'Napaka pri oddaji opravičila. Poskusite znova.';
        $messageType = 'error';
    }
}

// Get student's absences and justifications
$absences = getStudentAbsences($studentId);
$justifications = getStudentJustifications($studentId);

// Generate CSRF token
$csrfToken = '';
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    // Log error and set a default message
    error_log("Error generating CSRF token: " . $e->getMessage());
    $message = 'Error generating security token. Please try again.';
    $messageType = 'error';
}

// Get selected absence
$selectedAbsenceId = isset($_GET['absence_id']) ? (int)$_GET['absence_id'] : 0;
$selectedAbsence = null;

if ($selectedAbsenceId > 0) foreach ($absences as $absence) if ($absence['absence_id'] == $selectedAbsenceId) {
    $selectedAbsence = $absence;
    break;
}
?>

<!-- Main title card with page heading -->
<div class="card shadow mb-lg mt-lg page-transition">
    <div class="d-flex justify-between items-center">
        <div>
            <h2 class="mt-0 mb-xs">Opravičila odsotnosti</h2>
            <p class="text-secondary mt-0 mb-0">Oddajanje in spremljanje opravičil za odsotnosti</p>
        </div>
        <div class="role-badge role-student">Dijak</div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert status-<?= $messageType === 'success' ? 'success' : 'error' ?> mb-lg">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Absences list column -->
    <div class="col col-md-4">
        <!-- Absences that need justification -->
        <div class="card shadow mb-lg">
            <div class="card__title">
                <div class="d-flex justify-between items-center">
                    <span>Neopravičene odsotnosti</span>
                    <span class="badge badge-<?= !empty($absences) ? 'warning' : 'success' ?>">
                        <?= count(array_filter($absences, static function ($a) {
                            return !$a['justified'];
                        })) ?>
                    </span>
                </div>
            </div>
            <div class="card__content">
                <?php
                $unjustifiedAbsences = array_filter($absences, static function ($a) {
                    return !$a['justified'];
                });
                if (empty($unjustifiedAbsences)):
                    ?>
                    <div class="alert status-success mb-0">
                        Nimate neopravičenih odsotnosti.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-sm">
                        <?php foreach ($unjustifiedAbsences as $absence): ?>
                            <a href="/uwuweb/student/justification.php?absence_id=<?= $absence['absence_id'] ?>"
                               class="card p-sm <?= $selectedAbsenceId == $absence['absence_id'] ? 'shadow-sm' : '' ?>"
                               style="text-decoration: none;">
                                <div class="d-flex justify-between">
                                    <span class="text-primary">
                                        <?= htmlspecialchars($absence['subject_name']) ?>
                                    </span>
                                    <span class="text-secondary text-sm">
                                        <?= date('d.m.Y', strtotime($absence['date'])) ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-between mt-xs">
                                    <span class="text-secondary text-sm">
                                        <?= htmlspecialchars($absence['period_label']) ?>
                                    </span>
                                    <span class="attendance-status status-absent">
                                        Odsoten
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Previous justifications -->
        <div class="card shadow mb-lg">
            <div class="card__title">
                <div class="d-flex justify-between items-center">
                    <span>Oddana opravičila</span>
                    <span class="badge badge-secondary">
                        <?= count($justifications) ?>
                    </span>
                </div>
            </div>
            <div class="card__content">
                <?php if (empty($justifications)): ?>
                    <div class="alert status-info mb-0">
                        Še niste oddali nobenega opravičila.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Predmet</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($justifications as $justification): ?>
                                <?php
                                $statusClass = 'status-info';
                                if ($justification['status'] === 'approved') $statusClass = 'status-success'; else if ($justification['status'] === 'rejected') $statusClass = 'status-error';
                                ?>
                                <tr>
                                    <td><?= date('d.m.Y', strtotime($justification['absence_date'])) ?></td>
                                    <td><?= htmlspecialchars($justification['subject_name']) ?></td>
                                    <td>
                                            <span class="badge <?= $statusClass ?>">
                                                <?= ucfirst($justification['status']) ?>
                                            </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Justification form column -->
    <div class="col col-md-8">
        <?php if ($selectedAbsence): ?>
            <div class="card shadow">
                <div class="card__title">
                    Oddaj opravičilo
                </div>
                <div class="card__content">
                    <div class="mb-lg">
                        <div class="d-flex flex-wrap gap-lg">
                            <div>
                                <div class="text-secondary text-sm">Datum</div>
                                <div><?= date('d.m.Y', strtotime($selectedAbsence['date'])) ?></div>
                            </div>
                            <div>
                                <div class="text-secondary text-sm">Predmet</div>
                                <div><?= htmlspecialchars($selectedAbsence['subject_name']) ?></div>
                            </div>
                            <div>
                                <div class="text-secondary text-sm">Ura</div>
                                <div><?= htmlspecialchars($selectedAbsence['period_label']) ?></div>
                            </div>
                            <div>
                                <div class="text-secondary text-sm">Učitelj</div>
                                <div><?= htmlspecialchars($selectedAbsence['teacher_name'] ?? 'Neznano') ?></div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="justification.php?absence_id=<?= $selectedAbsence['absence_id'] ?>"
                          enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="absence_id" value="<?= $selectedAbsence['absence_id'] ?>">

                        <div class="form-group">
                            <label for="justification" class="form-label">Opravičilo za odsotnost:</label>
                            <textarea id="justification" name="justification" class="form-input" rows="6" required
                                      placeholder="Prosimo, pojasnite razlog za vašo odsotnost..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="justification_file" class="form-label">Dokazna dokumentacija
                                (neobvezno):</label>
                            <div class="d-flex gap-md items-center">
                                <input type="file" id="justification_file" name="justification_file" class="form-input">
                                <div class="text-secondary text-sm">
                                    Največja velikost: 5MB. Sprejeti formati: PDF, JPG, PNG.
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-lg">
                            <div class="d-flex justify-end gap-md">
                                <a href="/uwuweb/student/justification.php" class="btn btn-secondary">Prekliči</a>
                                <button type="submit" class="btn btn-primary">Oddaj opravičilo</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif (!empty($unjustifiedAbsences)): ?>
            <div class="card shadow">
                <div class="card__content text-center p-xl">
                    <div class="alert status-info mb-lg">
                        Izberite odsotnost s seznama, da oddate opravičilo.
                    </div>
                    <p class="text-secondary">Imate odsotnosti, ki zahtevajo opravičilo. Kliknite na odsotnost na levi
                        strani, da oddate svoje pojasnilo.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow">
                <div class="card__content text-center p-xl">
                    <div class="alert status-success mb-lg">
                        Nimate odsotnosti, ki bi zahtevale opravičilo.
                    </div>
                    <p class="text-secondary">Vse vaše odsotnosti so bile opravičene. Odlično obiskovanje pouka!</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Rejected justifications that need attention -->
        <?php
        $rejectedJustifications = array_filter($justifications, static function ($j) {
            return $j['status'] === 'rejected';
        });

        if (!empty($rejectedJustifications)):
            ?>
            <div class="card shadow mt-lg">
                <div class="card__title">
                    <div class="d-flex justify-between items-center">
                        <span>Zavrnjena opravičila</span>
                        <span class="badge badge-error"><?= count($rejectedJustifications) ?></span>
                    </div>
                </div>
                <div class="card__content">
                    <div class="alert status-error mb-md">
                        Naslednja opravičila so bila zavrnjena in zahtevajo vašo pozornost.
                    </div>

                    <div class="d-flex flex-column gap-md">
                        <?php foreach ($rejectedJustifications as $justification): ?>
                            <div class="card p-md shadow-sm">
                                <div class="d-flex justify-between mb-sm">
                                    <div>
                                        <div class="text-secondary text-sm">Datum</div>
                                        <div><?= date('d.m.Y', strtotime($justification['absence_date'])) ?></div>
                                    </div>
                                    <div>
                                        <div class="text-secondary text-sm">Predmet</div>
                                        <div><?= htmlspecialchars($justification['subject_name']) ?></div>
                                    </div>
                                    <div>
                                        <div class="text-secondary text-sm">Oddano</div>
                                        <div><?= date('d.m.Y', strtotime($justification['submitted_at'])) ?></div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Razlog za zavrnitev:</label>
                                    <div class="card p-sm bg-tertiary">
                                        <?= nl2br(htmlspecialchars($justification['rejection_reason'])) ?>
                                    </div>
                                </div>

                                <div class="d-flex justify-end">
                                    <a href="/uwuweb/student/justification.php?absence_id=<?= $justification['absence_id'] ?>"
                                       class="btn btn-primary btn-sm">
                                        Oddaj novo opravičilo
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
