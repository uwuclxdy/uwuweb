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
require_once 'teacher_functions.php';

requireRole(ROLE_TEACHER);

$teacherId = getTeacherId(getUserId());
if (!$teacherId) die('Napaka: Učiteljev račun ni bil najden.');

// CSR+F token for forms
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    sendJsonErrorResponse('Napaka pri generiranju CSRF žetona.', 500, 'justifications.php');
}

// Get pending justifications for homeroom teacher
$pendingJustifications = getHomeroomTeacherJustifications($teacherId);

// Toggle for showing processed justifications
$showProcessed = isset($_GET['show_processed']) && $_GET['show_processed'] === '1';
$processedJustifications = [];
if ($showProcessed) $processedJustifications = getHomeroomTeacherJustifications($teacherId, true);

// Set page title and include header
$pageTitle = 'Opravičila za odsotnost';
require_once '../includes/header.php';
?>

<div class="container">
    <?php renderHeaderCard(
        'Opravičila za odsotnost',
        'Pregled in potrjevanje opravičil odsotnosti učencev',
        'teacher'
    ); ?>

    <div class="card mb-lg">
        <div class="card__content">
            <div class="d-flex justify-between items-center mb-md">
                <h3>Čakajoča opravičila</h3>
                <div class="form-group d-flex items-center">
                    <input type="checkbox" id="showProcessed" name="showProcessed" class="mr-sm"
                        <?php echo $showProcessed ? 'checked' : ''; ?>>
                    <label for="showProcessed" class="form-label mb-0">Prikaži tudi obdelana opravičila</label>
                </div>
            </div>

            <?php if (empty($pendingJustifications)): ?>
                <div class="alert status-info">
                    <div class="alert-content">
                        <p>Trenutno ni čakajočih opravičil.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Učenec</th>
                            <th>Razred</th>
                            <th>Predmet</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th>Opravičilo</th>
                            <th>Priloga</th>
                            <th>Dejanja</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pendingJustifications as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></td>
                                <td><?= htmlspecialchars($item['class_code']) ?></td>
                                <td><?= htmlspecialchars($item['subject_name']) ?></td>
                                <td><?= formatDateDisplay($item['period_date']) ?>
                                    (<?= htmlspecialchars($item['period_label']) ?>)
                                </td>
                                <td>
                                        <span class="badge badge-<?= $item['status'] === 'A' ? 'error' : 'warning' ?>">
                                            <?= getAttendanceStatusLabel($item['status']) ?>
                                        </span>
                                </td>
                                <td><?= htmlspecialchars($item['justification']) ?></td>
                                <td>
                                    <?php if (!empty($item['justification_file'])): ?>
                                        <a href="../includes/download_justification.php?att_id=<?= $item['att_id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                                           class="btn btn-secondary btn-sm">
                                            Prenesi
                                        </a>
                                    <?php else: ?>
                                        <span class="text-disabled">Ni priloge</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-sm">
                                        <button class="btn btn-success btn-sm"
                                                data-open-modal="approveModal"
                                                data-id="<?= $item['att_id'] ?>"
                                                data-name="<?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?>">
                                            Odobri
                                        </button>
                                        <button class="btn btn-error btn-sm"
                                                data-open-modal="rejectModal"
                                                data-id="<?= $item['att_id'] ?>"
                                                data-name="<?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?>">
                                            Zavrni
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($showProcessed && !empty($processedJustifications)): ?>
        <div class="card mb-lg">
            <div class="card__content">
                <h3 class="mb-md">Obdelana opravičila</h3>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Učenec</th>
                            <th>Razred</th>
                            <th>Predmet</th>
                            <th>Datum</th>
                            <th>Status</th>
                            <th>Opravičilo</th>
                            <th>Priloga</th>
                            <th>Rezultat</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($processedJustifications as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></td>
                                <td><?= htmlspecialchars($item['class_code']) ?></td>
                                <td><?= htmlspecialchars($item['subject_name']) ?></td>
                                <td><?= formatDateDisplay($item['period_date']) ?>
                                    (<?= htmlspecialchars($item['period_label']) ?>)
                                </td>
                                <td>
                                        <span class="badge badge-<?= $item['status'] === 'A' ? 'error' : 'warning' ?>">
                                            <?= getAttendanceStatusLabel($item['status']) ?>
                                        </span>
                                </td>
                                <td><?= htmlspecialchars($item['justification']) ?></td>
                                <td>
                                    <?php if (!empty($item['justification_file'])): ?>
                                        <a href="../includes/download_justification.php?att_id=<?= $item['att_id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                                           class="btn btn-secondary btn-sm">
                                            Prenesi
                                        </a>
                                    <?php else: ?>
                                        <span class="text-disabled">Ni priloge</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['approved'] === 1): ?>
                                        <span class="badge badge-success">Odobreno</span>
                                    <?php elseif ($item['approved'] === 0): ?>
                                        <span class="badge badge-error"
                                              title="<?= htmlspecialchars($item['reject_reason']) ?>">
                                                Zavrnjeno
                                            </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">V obdelavi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Modal -->
<div class="modal" id="approveModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="approveModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="approveModalTitle">Potrditev odobritve</h3>
        </div>
        <div class="modal-body">
            <div class="alert status-success mb-md">
                <p>Ali res želite odobriti opravičilo učenca <strong id="approveModal_name"></strong>?</p>
            </div>
            <input type="hidden" id="approveModal_id" value="">
        </div>
        <div class="modal-footer">
            <div class="d-flex justify-between w-full">
                <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                <button type="button" class="btn btn-success" id="confirmApproveBtn">Odobri</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal" id="rejectModal">
    <div class="modal-overlay" aria-hidden="true"></div>
    <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="rejectModalTitle">Zavrnitev opravičila</h3>
        </div>
        <form id="rejectForm">
            <div class="modal-body">
                <div class="alert status-error mb-md">
                    <p>Ali res želite zavrniti opravičilo učenca <strong id="rejectModal_name"></strong>?</p>
                </div>
                <input type="hidden" id="rejectModal_id" name="att_id" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="form-group">
                    <label for="rejectReason" class="form-label">Razlog zavrnitve:</label>
                    <textarea id="rejectReason" name="reason" class="form-textarea" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-between w-full">
                    <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
                    <button type="submit" class="btn btn-error">Zavrni</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Toggle show processed justifications
        document.getElementById('showProcessed').addEventListener('change', function () {
            window.location.href = 'justifications.php' + (this.checked ? '?show_processed=1' : '');
        });

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

        // Handle approve justification
        document.getElementById('confirmApproveBtn').addEventListener('click', function () {
            const absenceId = document.getElementById('approveModal_id').value;

            fetch('../api/justifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'approveJustification',
                    'att_id': absenceId,
                    'csrf_token': '<?= $csrfToken ?>'
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh page on success
                        location.reload();
                    } else {
                        alert('Napaka: ' + (data.message || 'Neznana napaka pri odobritvi opravičila.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Napaka pri pošiljanju zahteve.');
                });
        });

        // Handle reject form submission
        document.getElementById('rejectForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'rejectJustification');

            fetch('../api/justifications.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh page on success
                        location.reload();
                    } else {
                        alert('Napaka: ' + (data.message || 'Neznana napaka pri zavrnitvi opravičila.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Napaka pri pošiljanju zahteve.');
                });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
