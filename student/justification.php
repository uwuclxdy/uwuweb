<?php
/**
 * Student Absence Justification Page
 *
 * Allows students to view their absences and submit justifications
 *
 */

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once 'student_functions.php';

requireRole(ROLE_STUDENT);

$studentId = getStudentId();
if (!$studentId) die('Napaka: Študentski račun ni bil najden.');

// Handle file upload submission
$message = '';
$messageType = '';
$uploadedFile = '';
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    $message = 'Napaka.';
    $messageType = 'error';
}

// Get specific absence ID from query string if provided
$specificAbsenceId = isset($_GET['att_id']) ? (int)$_GET['att_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : null);

// Form processing logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Neuspešno preverjanje pristnosti. Poskusite znova.';
    $messageType = 'error';
} else {
    $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
    $justificationText = isset($_POST['justification']) ? trim($_POST['justification']) : '';

    // Basic validation
    if ($absenceId <= 0) {
        $message = 'Neveljavna ID odsotnosti.';
        $messageType = 'error';
    } elseif (empty($justificationText) && empty($_FILES['justification_file']['name'])) {
        $message = 'Vnesite besedilo opravičila ali naložite datoteko.';
        $messageType = 'error';
    } else {
        // Process text justification
        if (!empty($justificationText)) {
            $success = uploadJustification($absenceId, $justificationText);
            if (!$success) {
                $message = 'Napaka pri shranjevanju opravičila. Poskusite znova.';
                $messageType = 'error';
            } else {
                $message = 'Opravičilo je bilo uspešno shranjeno.';
                $messageType = 'success';
            }
        }

        // Process file upload if present
        if (!empty($_FILES['justification_file']['name'])) if (validateJustificationFile($_FILES['justification_file'])) {
            $result = saveJustificationFile($_FILES['justification_file'], $absenceId);
            if ($result !== false) {
                $uploadedFile = $result;
                $message = 'Opravičilo z datoteko je bilo uspešno oddano.';
                $messageType = 'success';
            } else {
                $message = 'Napaka pri nalaganju datoteke. Prosimo, preverite velikost in format datoteke.';
                $messageType = 'error';
            }
        } else {
            $message = 'Neveljavna datoteka. Dovoljene so samo PDF, JPG, PNG in GIF datoteke do 5MB.';
            $messageType = 'error';
        }
    }
}

// Get all absences for this student
$absences = getStudentAttendance($studentId);

// Debug and validate data structure
$validAbsences = [];
foreach ($absences as $absence) if (isset($absence['att_id'], $absence['period_date'], $absence['period_label'], $absence['subject_name'], $absence['class_code'], $absence['status'])) $validAbsences[] = $absence;
$absences = $validAbsences;

// If there's a specific absence ID in the query string, pre-select it for the modal
$focusAbsence = null;
if ($specificAbsenceId) foreach ($absences as $absence) if ((int)$absence['att_id'] === $specificAbsenceId) {
    $focusAbsence = $absence;
    break;
}

// Get both unapproved (including pending) and approved/rejected absences
$pendingAbsences = array_filter($absences, static function ($absence) {
    return $absence['status'] === 'A' && ($absence['approved'] === null);
});

$processedAbsences = array_filter($absences, static function ($absence) {
    return $absence['status'] === 'A' && ($absence['approved'] === 0 || $absence['approved'] === 1);
});

require_once '../includes/header.php';
?>

<div class="container my-xl">
    <?php
    renderHeaderCard(
        'Opravičila za odsotnosti',
        'Upravljanje in oddaja opravičil za odsotnosti',
        'student',
        'Dijak'
    );
    ?>

    <?php if (!empty($message)): ?>
        <div class="alert status-<?= $messageType === 'error' ? 'error' : 'success' ?> mb-lg">
            <p><?= htmlspecialchars($message) ?></p>
        </div>
    <?php endif; ?>

    <?php if (empty($absences)): ?>
        <div class="card p-lg">
            <p class="text-center">Nimate odsotnosti v sistemu.</p>
        </div>
    <?php else: ?>
        <!-- Pending Absences Section -->
        <div class="card mb-xl">
            <div class="card-header">
                <h2 class="card-title">Neopravičene odsotnosti</h2>
                <p class="text-sm text-secondary">Odsotnosti, ki potrebujejo opravičilo.</p>
            </div>
            <?php if (empty($pendingAbsences)): ?>
                <div class="p-lg">
                    <p class="text-center">Nimate neobravnavanih odsotnosti, ki bi potrebovale opravičilo.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table w-100">
                        <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Ura</th>
                            <th>Predmet</th>
                            <th>Razred</th>
                            <th>Status</th>
                            <th>Akcije</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingAbsences as $absence): ?>
                            <tr>
                                <td><?= formatDateDisplay($absence['period_date'] ?? '') ?></td>
                                <td><?= htmlspecialchars($absence['period_label'] ?? '') ?></td>
                                <td><?= htmlspecialchars($absence['subject_name'] ?? '') ?></td>
                                <td><?= htmlspecialchars($absence['class_code'] ?? '') ?></td>
                                <td>
                                    <?php if ($absence['justification'] || $absence['justification_file']): ?>
                                        <span class="badge badge-warning">V obdelavi</span>
                                    <?php else: ?>
                                        <span class="badge badge-error">Potrebno opravičilo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($absence['justification'] || $absence['justification_file']): ?>
                                        <button class="btn btn-sm btn-secondary"
                                                data-open-modal="viewJustificationModal"
                                                data-id="<?= $absence['att_id'] ?>"
                                                data-date="<?= isset($absence['period_date']) ? formatDateDisplay($absence['period_date']) : '' ?>"
                                                data-period="<?= htmlspecialchars($absence['period_label']) ?>"
                                                data-subject="<?= htmlspecialchars($absence['subject_name']) ?>"
                                                data-justification="<?= htmlspecialchars($absence['justification'] ?? '') ?>"
                                                data-has-file="<?= $absence['justification_file'] ? '1' : '0' ?>"
                                                data-file-name="<?= htmlspecialchars($absence['justification_file'] ?? '') ?>">
                                            Pregled
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-primary"
                                                data-open-modal="addJustificationModal"
                                                data-id="<?= $absence['att_id'] ?>"
                                                data-date="<?= isset($absence['period_date']) ? formatDateDisplay($absence['period_date']) : '' ?>"
                                                data-period="<?= htmlspecialchars($absence['period_label']) ?>"
                                                data-subject="<?= htmlspecialchars($absence['subject_name']) ?>">
                                            Oddaj opravičilo
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Processed Absences Section -->
    <?php if (!empty($processedAbsences)): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Obravnavana opravičila</h2>
                <p class="text-sm text-secondary">Opravičila, ki so bila že obravnavana.</p>
            </div>
            <div class="table-responsive">
                <table class="data-table w-100">
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Ura</th>
                        <th>Predmet</th>
                        <th>Razred</th>
                        <th>Status</th>
                        <th>Akcije</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($processedAbsences as $absence): ?>
                        <tr>
                            <td><?= isset($absence['period_date']) ? formatDateDisplay($absence['period_date']) : '' ?></td>
                            <td><?= htmlspecialchars($absence['period_label'] ?? '') ?></td>
                            <td><?= htmlspecialchars($absence['subject_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($absence['class_code'] ?? '') ?></td>
                            <td>
                                <?php if ($absence['approved'] === 1): ?>
                                    <span class="badge badge-success">Odobreno</span>
                                <?php elseif ($absence['approved'] === 0): ?>
                                    <span class="badge badge-error">Zavrnjeno</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary"
                                        data-open-modal="viewJustificationModal"
                                        data-id="<?= $absence['att_id'] ?>"
                                        data-date="<?= formatDateDisplay($absence['period_date']) ?>"
                                        data-period="<?= htmlspecialchars($absence['period_label']) ?>"
                                        data-subject="<?= htmlspecialchars($absence['subject_name']) ?>"
                                        data-justification="<?= htmlspecialchars($absence['justification'] ?? '') ?>"
                                        data-has-file="<?= $absence['justification_file'] ? '1' : '0' ?>"
                                        data-file-name="<?= htmlspecialchars($absence['justification_file'] ?? '') ?>"
                                        data-approved="<?= $absence['approved'] === 1 ? '1' : '0' ?>"
                                        data-reject-reason="<?= htmlspecialchars($absence['reject_reason'] ?? '') ?>">
                                    Pregled
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Justification Modal -->
<div class="modal" id="addJustificationModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="addJustificationModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="addJustificationModalTitle">Oddaja opravičila</h3>
        </div>
        <form id="justificationForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" id="addJustificationModal_id" name="absence_id" value="">

            <div class="modal-body">
                <div class="alert status-warning mb-md">
                    <p>Oddajate opravičilo za odsotnost: <strong id="absenceDetailsText"></strong></p>
                </div>

                <div class="form-group mb-md">
                    <label for="justification" class="form-label">Besedilo opravičila:</label>
                    <textarea id="justification" name="justification" class="form-input" rows="5"
                              placeholder="Vpišite razlog za odsotnost..."></textarea>
                </div>

                <div class="form-group">
                    <label for="justification_file" class="form-label">Priložena datoteka (neobvezno):</label>
                    <input type="file" id="justification_file" name="justification_file" class="form-input">
                    <div class="form-help text-secondary">
                        Dovoljene datoteke: PDF, JPG, PNG, GIF (max. 5MB)
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="submit" class="btn btn-primary">Oddaj opravičilo</button>
            </div>
        </form>
    </div>
</div>

<!-- View Justification Modal -->
<div class="modal" id="viewJustificationModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="viewJustificationModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="viewJustificationModalTitle">Pregled opravičila</h3>
        </div>
        <div class="modal-body">
            <div id="viewAbsenceDetails" class="mb-md p-sm bg-light rounded">
                <p class="m-0">Datum: <span id="viewJustificationModal_date"></span></p>
                <p class="m-0">Ura: <span id="viewJustificationModal_period"></span></p>
                <p class="m-0">Predmet: <span id="viewJustificationModal_subject"></span></p>
            </div>

            <div id="viewJustificationStatusSection" class="mb-md">
                <h4 class="mb-sm">Status opravičila:</h4>
                <div id="viewJustificationStatusPending" class="alert status-warning" style="display:none;">
                    <p>Opravičilo je bilo oddano in čaka na obravnavo.</p>
                </div>
                <div id="viewJustificationStatusApproved" class="alert status-success" style="display:none;">
                    <p>Opravičilo je bilo odobreno.</p>
                </div>
                <div id="viewJustificationStatusRejected" class="alert status-error" style="display:none;">
                    <p>Opravičilo je bilo zavrnjeno.</p>
                    <p id="rejectReasonText" class="mt-sm"></p>
                </div>
            </div>

            <div class="mb-md">
                <h4 class="mb-sm">Besedilo opravičila:</h4>
                <div id="viewJustificationText" class="p-md border rounded">
                    <p class="text-secondary font-italic">Ni besedila opravičila.</p>
                </div>
            </div>

            <div id="attachmentSection">
                <h4 class="mb-sm">Priložena datoteka:</h4>
                <div id="noAttachment" class="text-secondary font-italic">
                    Ni priložene datoteke.
                </div>
                <div id="hasAttachment" style="display:none;">
                    <p>Datoteka: <span id="attachmentFileName"></span></p>
                    <a href="../teacher/download_justification.php?att_id=" id="downloadAttachmentLink"
                       class="btn btn-sm btn-secondary" target="_blank">
                        Prenesi datoteko
                    </a>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Zapri</button>
        </div>
    </div>
</div>

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
            }
        };

        // --- Event Listeners ---

        // Open modal buttons
        document.querySelectorAll('[data-open-modal]').forEach(btn => {
            btn.addEventListener('click', function () {
                const modalId = this.dataset.openModal;

                // Handle specific modal types
                if (modalId === 'addJustificationModal') {
                    const absenceId = this.dataset.id;
                    const date = this.dataset.date;
                    const period = this.dataset.period;
                    const subject = this.dataset.subject;

                    // Update the modal content
                    document.getElementById('addJustificationModal_id').value = absenceId;
                    document.getElementById('absenceDetailsText').textContent =
                        `${date}, ${period} (${subject})`;
                }

                if (modalId === 'viewJustificationModal') {
                    const absenceId = this.dataset.id;
                    const date = this.dataset.date;
                    const period = this.dataset.period;
                    const subject = this.dataset.subject;
                    const justification = this.dataset.justification;
                    const hasFile = this.dataset.hasFile === '1';
                    const fileName = this.dataset.fileName;
                    const approved = this.dataset.approved;
                    const rejectReason = this.dataset.rejectReason;

                    // Update the modal content
                    document.getElementById('viewJustificationModal_date').textContent = date;
                    document.getElementById('viewJustificationModal_period').textContent = period;
                    document.getElementById('viewJustificationModal_subject').textContent = subject;

                    // Show appropriate status
                    document.getElementById('viewJustificationStatusPending').style.display =
                        (approved === undefined) ? 'block' : 'none';
                    document.getElementById('viewJustificationStatusApproved').style.display =
                        (approved === '1') ? 'block' : 'none';
                    document.getElementById('viewJustificationStatusRejected').style.display =
                        (approved === '0') ? 'block' : 'none';

                    if (approved === '0' && rejectReason) {
                        document.getElementById('rejectReasonText').textContent =
                            `Razlog zavrnitve: ${rejectReason}`;
                    }

                    // Update justification text
                    const justTextElem = document.getElementById('viewJustificationText');
                    if (justification) {
                        justTextElem.innerHTML = `<p>${justification.replace(/\n/g, '<br>')}</p>`;
                    } else {
                        justTextElem.innerHTML = `<p class="text-secondary font-italic">Ni besedila opravičila.</p>`;
                    }

                    // Update file attachment section
                    document.getElementById('noAttachment').style.display = hasFile ? 'none' : 'block';
                    document.getElementById('hasAttachment').style.display = hasFile ? 'block' : 'none';

                    if (hasFile) {
                        document.getElementById('attachmentFileName').textContent = fileName;
                        const downloadLink = document.getElementById('downloadAttachmentLink');
                        downloadLink.href = `../teacher/download_justification.php?att_id=${absenceId}`;
                    }
                }

                openModal(modalId);
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

        <?php if ($focusAbsence): ?>
        // Open the modal for the specific absence if URL parameter is provided
        document.addEventListener('DOMContentLoaded', function () {
            const absenceBtn = document.querySelector(`[data-id="<?= $focusAbsence['att_id'] ?>"][data-open-modal]`);
            if (absenceBtn) {
                absenceBtn.click();
            }
        });
        <?php endif; ?>
    });
</script>

<?php include '../includes/footer.php'; ?>
