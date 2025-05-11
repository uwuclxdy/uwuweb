<?php
/**
 * Teacher Justification Approval Page
 *
 * Allows teachers to view and approve/reject student absence justifications
 *
 * /uwuweb/teacher/justifications.php
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';
require_once 'teacher_functions.php';

requireRole(ROLE_TEACHER);

$teacherId = getTeacherId(getUserId());
if (!$teacherId) die('Napaka: Učiteljev račun ni bil najden.');

// Get all pending justifications for this teacher
$pendingJustifications = getPendingJustifications();

// Handle direct approval/rejection via URL
$action = $_GET['action'] ?? '';
$justificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$viewJustification = null;

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Neveljavna oddaja obrazca. Poskusite znova.';
        $messageType = 'error';
    } else {
        // Process rejection submission
        if (isset($_POST['reject_justification'], $_POST['justification_id'], $_POST['reject_reason'])) {
            $absenceId = (int)$_POST['justification_id'];
            $reason = trim($_POST['reject_reason']);

            if (empty($reason)) {
                $message = 'Za zavrnitev opravičila morate navesti razlog.';
                $messageType = 'error';
            } else if (rejectJustification($absenceId, $reason)) {
                $message = 'Opravičilo uspešno zavrnjeno.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri zavrnitvi opravičila.';
                $messageType = 'error';
            }
        }

        // Process approval submission
        if (isset($_POST['approve_justification'], $_POST['justification_id'])) {
            $absenceId = (int)$_POST['justification_id'];

            if (approveJustification($absenceId)) {
                $message = 'Opravičilo uspešno odobreno.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri odobritvi opravičila.';
                $messageType = 'error';
            }
        }
    }

    // Refresh justifications list after processing
    $pendingJustifications = getPendingJustifications();
}

// Handle view/approve/reject actions
if ($justificationId > 0) {
    $viewJustification = getJustificationById($justificationId);

    if (!$viewJustification) {
        $message = 'Opravičilo ni bilo najdeno ali nimate dostopa do njega.';
        $messageType = 'error';
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>

<!-- Main title card with page heading -->
<?php
renderHeaderCard(
    'Opravičila odsotnosti',
    'Pregled in odobritev opravičil za odsotnosti',
    'teacher',
    'Učitelj'
);
?>

<?php if (!empty($message)): ?>
    <div class="alert status-<?= $messageType === 'success' ? 'success' : 'error' ?> mb-lg">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="row">
    <!-- Pending justifications list column -->
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
                        Trenutno nimate čakajočih opravičil za pregled.
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-sm">
                        <?php foreach ($pendingJustifications as $justification): ?>
                            <a href="justifications.php?action=view&id=<?= $justification['att_id'] ?>"
                               class="card p-sm <?= isset($viewJustification) && $viewJustification['att_id'] == $justification['att_id'] ? 'shadow-sm' : '' ?>"
                               style="text-decoration: none;">
                                <div class="d-flex justify-between">
                                    <span class="text-primary">
                                        <?= htmlspecialchars($justification['first_name'] . ' ' . $justification['last_name']) ?>
                                    </span>
                                    <span class="text-secondary text-sm">
                                        <?= date('d.m.Y', strtotime($justification['period_date'])) ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-between mt-xs">
                                    <span class="text-secondary text-sm">
                                        <?= htmlspecialchars($justification['class_code']) ?> | <?= htmlspecialchars($justification['subject_name']) ?>
                                    </span>
                                    <span class="badge badge-warning">
                                        V obdelavi
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Justification details column -->
    <div class="col col-md-8">
        <?php if ($viewJustification): ?>
            <div class="card shadow">
                <div class="card__title">
                    Podrobnosti opravičila
                </div>
                <div class="card__content">
                    <div class="mb-lg">
                        <div class="d-flex flex-wrap gap-lg">
                            <div>
                                <div class="text-secondary text-sm">Dijak</div>
                                <div class="font-bold"><?= htmlspecialchars($viewJustification['first_name'] . ' ' . $viewJustification['last_name']) ?></div>
                            </div>
                            <div>
                                <div class="text-secondary text-sm">Razred</div>
                                <div><?= htmlspecialchars($viewJustification['class_code']) ?></div>
                            </div>
                            <div>
                                <div class="text-secondary text-sm">Datum</div>
                                <div><?= date('d.m.Y', strtotime($viewJustification['period_date'])) ?></div>
                            </div>
                            <div>
                                <div class="text-secondary text-sm">Predmet</div>
                                <div><?= htmlspecialchars($viewJustification['subject_name']) ?></div>
                            </div>
                            <div>
                                <div class="text-secondary text-sm">Ura</div>
                                <div><?= htmlspecialchars($viewJustification['period_label']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sporočilo dijaka:</label>
                        <div class="card p-md bg-tertiary">
                            <?= nl2br(htmlspecialchars($viewJustification['justification'])) ?>
                        </div>
                    </div>

                    <?php if (!empty($viewJustification['justification_file'])): ?>
                        <div class="form-group">
                            <label class="form-label">Priložena datoteka:</label>
                            <div>
                                <a href="download_justification.php?id=<?= $viewJustification['att_id'] ?>"
                                   class="btn btn-secondary d-inline-flex items-center gap-xs">
                                    <span class="material-icons-outlined">attach_file</span>
                                    Prenesi datoteko
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-end gap-md mt-lg">
                        <button data-open-modal="rejectJustificationModal" class="btn btn-error">Zavrni</button>
                        <button data-open-modal="approveJustificationModal" class="btn btn-success">Odobri</button>
                    </div>
                </div>
            </div>
        <?php elseif (!empty($pendingJustifications)): ?>
            <div class="card shadow">
                <div class="card__content text-center p-xl">
                    <div class="alert status-info mb-lg">
                        Izberite opravičilo s seznama za pregled.
                    </div>
                    <p class="text-secondary">Imate čakajoča opravičila, ki zahtevajo odobritev. Kliknite na opravičilo
                        na levi strani, da si ogledate podrobnosti in ga odobrite ali zavrnete.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow">
                <div class="card__content text-center p-xl">
                    <div class="alert status-success mb-lg">
                        Trenutno ni nobenih opravičil, ki bi zahtevala vašo pozornost.
                    </div>
                    <p class="text-secondary">Vsa opravičila so bila pregledana. Ko bodo dijaki oddali nova opravičila,
                        se bodo prikazala na tej strani.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Justification Modal -->
<?php if ($viewJustification): ?>
    <div class="modal" id="approveJustificationModal">
        <div class="modal-overlay" aria-hidden="true"></div>
        <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="approveJustificationModalTitle">
            <div class="modal-header">
                <h3 class="modal-title" id="approveJustificationModalTitle">Potrditev odobritve</h3>
            </div>
            <form method="POST" action="justifications.php?action=view&id=<?= $viewJustification['att_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="justification_id" value="<?= $viewJustification['att_id'] ?>">
                <input type="hidden" name="approve_justification" value="1">

                <div class="modal-body">
                    <div class="alert status-warning mb-md">
                        <p>Ali ste prepričani, da želite odobriti to opravičilo?</p>
                    </div>
                    <p>Opravičilo dijaka
                        <strong><?= htmlspecialchars($viewJustification['first_name'] . ' ' . $viewJustification['last_name']) ?></strong>
                        bo označeno kot odobreno in odsotnost bo opravičena.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-success">Odobri opravičilo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Justification Modal -->
    <div class="modal" id="rejectJustificationModal">
        <div class="modal-overlay" aria-hidden="true"></div>
        <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="rejectJustificationModalTitle">
            <div class="modal-header">
                <h3 class="modal-title" id="rejectJustificationModalTitle">Zavrnitev opravičila</h3>
            </div>
            <form method="POST" action="justifications.php?action=view&id=<?= $viewJustification['att_id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="justification_id" value="<?= $viewJustification['att_id'] ?>">
                <input type="hidden" name="reject_justification" value="1">

                <div class="modal-body">
                    <div class="alert status-error mb-md">
                        <p>Opravičilo bo zavrnjeno in dijak bo obveščen o razlogu zavrnitve.</p>
                    </div>

                    <div class="form-group">
                        <label for="reject_reason" class="form-label">Razlog zavrnitve:</label>
                        <textarea id="reject_reason" name="reject_reason" class="form-input" rows="3"
                                  required></textarea>
                        <div class="form-help">Navedite jasen razlog za zavrnitev opravičila.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-error">Zavrni opravičilo</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Modal handling JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // --- Modal Management Functions ---
        const openModal = (modalId) => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
                // Focus the first focusable element
                const firstFocusable = modal.querySelector('button, [href], input, select, textarea');
                if (firstFocusable) firstFocusable.focus();
            }
        };

        const closeModal = (modal) => {
            if (typeof modal === 'string') {
                modal = document.getElementById(modal);
            }

            if (modal) {
                modal.classList.remove('open');
                // Reset forms if present
                const form = modal.querySelector('form');
                if (form) form.reset();

                // Clear any error messages
                const errorMsgs = modal.querySelectorAll('.feedback-error');
                errorMsgs.forEach(msg => {
                    if (msg && msg.style) {
                        msg.style.display = 'none';
                    }
                });
            }
        };

        // --- Event Listeners ---

        // Open modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;
                openModal(modalId);

                // If the button has additional data attributes, process them
                const dataId = this.dataset.id;
                const dataName = this.dataset.name;

                if (dataId) {
                    // Handle ID data (e.g., fill hidden form field)
                    const idField = document.getElementById(`${modalId}_id`);
                    if (idField) idField.value = dataId;
                }

                if (dataName) {
                    // Handle name data (e.g., show in confirmation text)
                    const nameDisplay = document.getElementById(`${modalId}_name`);
                    if (nameDisplay) nameDisplay.textContent = dataName;
                }
            });
        });

        // Close modal buttons
        document.querySelectorAll('[data-close-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        // Close modals when clicking the overlay
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function () {
                closeModal(this.closest('.modal'));
            });
        });

        // Close modals with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.open').forEach(modal => {
                    closeModal(modal);
                });
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
