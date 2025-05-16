<?php
/**
 * Student Absence Justification Page
 * File path: /student/justification.php
 *
 * Allows students to view their absences and submit justifications
 */

use Random\RandomException;

require_once 'student_functions.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require student role
requireRole(3); // ROLE_STUDENT

// Get student ID
$studentId = getStudentId();
if (!$studentId) die('Napaka: Študentski račun ni bil najden.');

// Process POST requests
$message = '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (isset($_POST['submit_justification'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($csrf_token)) $error = 'Neveljavna seja. Poskusite ponovno.'; else {
        $attId = (int)$_POST['att_id'];
        $justification = $_POST['justification'] ?? '';

        // Verify this attendance record belongs to the student
        $absenceDetails = getAbsenceDetails($attId);

        if (!$absenceDetails || $absenceDetails['student_id'] !== $studentId) $error = 'Neveljavna zahteva.'; else if (!empty($_FILES['justification_file']['name'])) if (validateJustificationFile($_FILES['justification_file'])) {
            $filePath = saveJustificationFile($_FILES['justification_file'], $attId);

            if ($filePath) if (uploadJustification($attId, $justification)) {
                // Update file path
                $pdo = safeGetDBConnection('student/justification.php');
                $stmt = $pdo->prepare("UPDATE attendance SET justification_file = :file_path WHERE att_id = :att_id");
                $stmt->execute([
                    'file_path' => $filePath,
                    'att_id' => $attId
                ]);

                $success = true;
                $message = 'Opravičilo je bilo uspešno oddano.';
            } else $error = 'Napaka pri shranjevanju opravičila.'; else $error = 'Napaka pri nalaganju datoteke.';
        } else $error = 'Neveljavna datoteka. Dovoljeni so samo PDF, JPG in PNG formati do 15MB.'; else if (uploadJustification($attId, $justification)) {
            $success = true;
            $message = 'Opravičilo je bilo uspešno oddano.';
        } else $error = 'Napaka pri shranjevanju opravičila.';
    }
}

// Get all student's attendance records
$attendance = getStudentAttendance($studentId);

// Check if redirected from attendance page
$highlightAttId = isset($_GET['att_id']) ? (int)$_GET['att_id'] : 0;
$showJustificationModal = false;

if ($highlightAttId > 0) {
    $absenceDetails = getAbsenceDetails($highlightAttId);
    $showJustificationModal = ($absenceDetails && ($absenceDetails['status'] === 'A' || $absenceDetails['status'] === 'L') &&
        $absenceDetails['approved'] === null && $absenceDetails['student_id'] === $studentId);
} else $absenceDetails = null;

// Generate CSRF token
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse('Napaka pri generiranju seje. Poskusite znova.');
}

// Check justification folder function
function checkJustificationFolder(): void
{
    $folder = __DIR__ . '/justifications/';
}

// Get detailed information about an absence function
function getAbsenceDetails(int $attId): ?array
{
    $pdo = safeGetDBConnection('student/justification.php');

    $sql = "SELECT a.*, p.period_date, p.period_label, cs.class_id, c.title as class_title, 
            s.name as subject_name, e.student_id, t.first_name as teacher_fname, t.last_name as teacher_lname
            FROM attendance a
            JOIN periods p ON a.period_id = p.period_id
            JOIN class_subjects cs ON p.class_subject_id = cs.class_subject_id
            JOIN classes c ON cs.class_id = c.class_id
            JOIN subjects s ON cs.subject_id = s.subject_id
            JOIN teachers t ON cs.teacher_id = t.teacher_id
            JOIN enrollments e ON a.enroll_id = e.enroll_id
            WHERE a.att_id = :att_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['att_id' => $attId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Include header
require_once '../includes/header.php';
?>

<div class="container section">
    <?php renderHeaderCard(
        'Opravičila izostankov',
        'Pregled in oddaja opravičil za izostanke od pouka',
        'student'
    ); ?>

    <?php if ($error): ?>
        <div class="alert status-error card-entrance">
            <div class="alert-content"><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success && $message): ?>
        <div class="alert status-success card-entrance">
            <div class="alert-content"><?= htmlspecialchars($message) ?></div>
        </div>
    <?php endif; ?>

    <div class="card mb-lg">
        <div class="card__title">
            <h3>Moji izostanki</h3>
        </div>
        <div class="card__content">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th class="text-center">Datum</th>
                        <th class="text-center">Ura</th>
                        <th class="text-center">Predmet</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Opravičilo</th>
                        <th class="text-center">Dejanja</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($attendance)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Ni zabeleženih izostankov.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance as $record): ?>
                            <?php
                            // Skip if status is Present
                            if ($record['status'] === 'P') continue;

                            // Get status label
                            $statusLabel = getAttendanceStatusLabel($record['status']);
                            $statusClass = $record['status'] === 'A' ? 'status-absent' : ($record['status'] === 'L' ? 'status-late' : '');

                            // Determine justification status
                            $justificationStatus = 'Ni opravičila';
                            $justificationClass = 'badge-secondary';

                            if ($record['justification']) if ($record['approved'] === null) {
                                $justificationStatus = 'V obravnavi';
                                $justificationClass = 'badge-info';
                            } elseif ($record['approved'] === 1) {
                                $justificationStatus = 'Odobreno';
                                $justificationClass = 'badge-success';
                            } else {
                                $justificationStatus = 'Zavrnjeno';
                                $justificationClass = 'badge-error';
                            }

                            // Can submit/edit justification if it hasn't been approved or rejected yet
                            $canSubmitJustification = empty($record['justification']) ||
                                ($record['approved'] === null && !empty($record['justification']));

                            // Highlight row if it matches the requested attendance record
                            $rowClass = ($record['att_id'] == $highlightAttId) ? 'bg-accent-tertiary' : '';
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="text-center"><?= htmlspecialchars(formatDateDisplay($record['date'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars($record['period_label']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($record['subject_name']) ?></td>
                                <td class="text-center"><span
                                            class="attendance-status <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $justificationClass ?>"><?= htmlspecialchars($justificationStatus) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($canSubmitJustification): ?>
                                        <button data-open-modal="justificationModal"
                                                data-id="<?= $record['att_id'] ?>"
                                                data-date="<?= formatDateDisplay($record['date']) ?>"
                                                data-period="<?= htmlspecialchars($record['period_label']) ?>"
                                                data-subject="<?= htmlspecialchars($record['subject_name']) ?>"
                                                data-status="<?= htmlspecialchars($statusLabel) ?>"
                                                data-justification="<?= htmlspecialchars($record['justification'] ?? '') ?>"
                                                class="btn btn-primary btn-sm">
                                            <?= empty($record['justification']) ? 'Oddaj opravičilo' : 'Uredi opravičilo' ?>
                                        </button>
                                    <?php elseif ($record['approved'] === 0 && !empty($record['reject_reason'])): ?>
                                        <button data-open-modal="rejectionModal"
                                                data-id="<?= $record['att_id'] ?>"
                                                data-reason="<?= htmlspecialchars($record['reject_reason']) ?>"
                                                class="btn btn-secondary btn-sm">
                                            Razlog zavrnitve
                                        </button>
                                    <?php elseif ($record['approved'] === '1'): ?>
                                        <span class="text-disabled">Opravičilo odobreno</span>
                                    <?php else: ?>
                                        <span class="text-disabled">Ni možnih dejanj</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Justification Modal -->
<div class="modal" id="justificationModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="justificationModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="justificationModalTitle">Oddaja/Urejanje opravičila</h3>
        </div>
        <form id="justificationForm" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="att_id" id="justificationModal_id" value="">

                <div class="alert status-info mb-md">
                    <div class="alert-content">
                        <p>Oddajate opravičilo za izostanek:</p>
                        <p><strong>Datum:</strong> <span id="justificationModal_date"></span></p>
                        <p><strong>Ura:</strong> <span id="justificationModal_period"></span></p>
                        <p><strong>Predmet:</strong> <span id="justificationModal_subject"></span></p>
                        <p><strong>Status:</strong> <span id="justificationModal_status"></span></p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="justification">Obrazložitev izostanka:</label>
                    <textarea id="justification" name="justification" class="form-textarea" rows="4"
                              required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="justification_file">Priloži dokazilo (neobvezno):</label>
                    <input type="file" id="justification_file" name="justification_file" class="form-input">
                    <div class="feedback-text mt-xs">Dovoljeni formati: PDF, JPG, PNG (največ 15MB)</div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" name="submit_justification" class="btn btn-primary"
                            id="justification_submit_btn">Oddaj opravičilo
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Rejection Reason Modal -->
<div class="modal" id="rejectionModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="rejectionModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="rejectionModalTitle">Razlog zavrnitve</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-error mb-md">
                <div class="alert-content">
                    <p>Vaše opravičilo je bilo zavrnjeno z naslednjim razlogom:</p>
                    <p id="rejectionModal_reason" class="font-bold mt-sm"></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="d-flex justify-between w-full">
                <div></div> <!-- Empty div for spacing -->
                <button type="button" class="btn btn-secondary" data-close-modal>Zapri</button>
            </div>
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
                const form = modal.querySelector('form');
                if (form) form.reset();

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

                // Process additional data attributes
                if (modalId === 'justificationModal') {
                    document.getElementById('justificationModal_id').value = this.dataset.id;
                    document.getElementById('justificationModal_date').textContent = this.dataset.date;
                    document.getElementById('justificationModal_period').textContent = this.dataset.period;
                    document.getElementById('justificationModal_subject').textContent = this.dataset.subject;
                    document.getElementById('justificationModal_status').textContent = this.dataset.status;

                    // Set existing justification if present
                    if (this.dataset.justification) {
                        document.getElementById('justification').value = this.dataset.justification;
                        document.getElementById('justificationModalTitle').textContent = 'Urejanje opravičila';
                        document.getElementById('justification_submit_btn').textContent = 'Posodobi opravičilo';
                    } else {
                        document.getElementById('justificationModalTitle').textContent = 'Oddaja opravičila';
                        document.getElementById('justification_submit_btn').textContent = 'Oddaj opravičilo';
                    }
                } else if (modalId === 'rejectionModal') {
                    document.getElementById('rejectionModal_reason').textContent = this.dataset.reason;
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

        <?php if ($showJustificationModal && $absenceDetails): ?>
        // Automatically open justification modal if redirected with att_id
        const modalBtn = document.querySelector(`[data-open-modal="justificationModal"][data-id="<?= $highlightAttId ?>"]`);
        if (modalBtn) {
            modalBtn.click();
        }
        <?php endif; ?>
    });
</script>

<style>
    /* Center button in action column */
    .data-table td.text-center .btn,
    .data-table td.text-center .text-disabled {
        margin: 0 auto;
        display: table;
    }

    /* Ensure proper width of attendance status */
    .attendance-status {
        min-width: 120px;
    }
</style>

<?php include '../includes/footer.php'; ?>
