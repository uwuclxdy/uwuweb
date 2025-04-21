<?php
/**
 * Teacher Justification Approval Page
 *
 * Allows teachers to view and approve/reject student absence justifications
 *
 * Functions:
 * - getTeacherClasses($teacherId) - Gets list of classes taught by a teacher
 * - getPendingJustifications($teacherId) - Gets list of pending justifications for a teacher's classes
 * - getJustificationById($absenceId) - Gets detailed information about a specific justification
 * - getStudentName($studentId) - Gets student's full name by ID
 * - approveJustification($absenceId) - Approves a justification
 * - rejectJustification($absenceId, $reason) - Rejects a justification with a reason
 * - getJustificationFileInfo($absenceId) - Gets information about a saved justification file
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId(getUserId());
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Get pending justifications for a teacher's classes
function getPendingJustifications($teacherId) {
    $pdo = safeGetDBConnection('getPendingJustifications()', false);
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification, 
            a.justification_file,
            a.approved, 
            p.period_date, 
            p.period_label,
            s.name AS subject_name,
            c.title AS class_title,
            st.student_id,
            st.first_name,
            st.last_name,
            st.class_code
         FROM attendance a
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN students st ON e.student_id = st.student_id
         WHERE c.teacher_id = :teacher_id 
         AND a.justification IS NOT NULL 
         AND a.approved IS NULL
         ORDER BY p.period_date DESC"
    );
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get justification by ID
function getJustificationById($absenceId) {
    $pdo = safeGetDBConnection('getJustificationById()', false);
    if (!$pdo) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification, 
            a.justification_file,
            a.approved, 
            p.period_date, 
            p.period_label,
            s.name AS subject_name,
            c.title AS class_title,
            st.student_id,
            st.first_name,
            st.last_name,
            st.class_code
         FROM attendance a
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN students st ON e.student_id = st.student_id
         WHERE a.att_id = :att_id"
    );
    $stmt->execute(['att_id' => $absenceId]);
    return $stmt->fetch();
}

// Approve a justification
function approveJustification($absenceId) {
    $pdo = safeGetDBConnection('approveJustification()', false);
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE attendance 
             SET approved = 1, reject_reason = NULL
             WHERE att_id = :att_id"
        );
        return $stmt->execute(['att_id' => $absenceId]);
    } catch (PDOException $e) {
        error_log("Error approving justification: " . $e->getMessage());
        return false;
    }
}

// Reject a justification
function rejectJustification($absenceId, $reason) {
    $pdo = safeGetDBConnection('rejectJustification()', false);
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE attendance 
             SET approved = 0, reject_reason = :reason
             WHERE att_id = :att_id"
        );
        return $stmt->execute([
            'att_id' => $absenceId,
            'reason' => $reason
        ]);
    } catch (PDOException $e) {
        error_log("Error rejecting justification: " . $e->getMessage());
        return false;
    }
}

// Get justification file information
function getJustificationFileInfo($absenceId) {
    try {
        $pdo = safeGetDBConnection('getJustificationFileInfo()', false);
        if (!$pdo) {
            return null;
        }

        $stmt = $pdo->prepare(
            "SELECT justification_file 
             FROM attendance 
             WHERE att_id = :att_id"
        );

        $stmt->execute(['att_id' => $absenceId]);
        $result = $stmt->fetch();

        if ($result && !empty($result['justification_file'])) {
            return $result['justification_file'];
        }

        return null;
    } catch (PDOException $e) {
        error_log("Database error in getJustificationFileInfo: " . $e->getMessage());
        return null;
    }
}

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        if (isset($_POST['approve_justification'])) {
            $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;

            if ($absenceId <= 0) {
                $message = 'Invalid absence selected.';
                $messageType = 'error';
            } else if (approveJustification($absenceId)) {
                $message = 'Justification approved successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error approving justification. Please try again.';
                $messageType = 'error';
            }
        } else if (isset($_POST['reject_justification'])) {
            $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
            $reason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';

            if ($absenceId <= 0) {
                $message = 'Invalid absence selected.';
                $messageType = 'error';
            } else if (empty($reason)) {
                $message = 'Please provide a reason for rejection.';
                $messageType = 'error';
            } else if (rejectJustification($absenceId, $reason)) {
                $message = 'Justification rejected successfully.';
                $messageType = 'success';
            } else {
                $message = 'Error rejecting justification. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

// Get pending justifications
$justifications = getPendingJustifications($teacherId);

// Selected justification for detailed view
$selectedJustificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$selectedJustification = $selectedJustificationId ? getJustificationById($selectedJustificationId) : null;

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>

    <div class="card card-entrance mt-xl">
        <h1 class="mt-0 mb-md">Absence Justifications</h1>
        <p class="text-secondary mt-0 mb-lg">Review and approve student absence justifications</p>

        <?php if (!empty($message)): ?>
            <div class="alert <?= strpos($message, 'successfully') !== false ? 'status-success' : ($messageType === 'warning' ? 'status-warning' : 'status-error') ?> mb-lg">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
    </div>

<?php if ($selectedJustification): ?>
    <!-- Detailed Justification View -->
    <div class="card card-entrance mt-xl">
        <div class="d-flex justify-between items-center mb-lg">
            <h2 class="mt-0 mb-0">Justification Details</h2>
            <a href="justifications.php" class="btn btn-secondary">Back to List</a>
        </div>

        <div class="bg-tertiary p-lg rounded mb-lg">
            <div class="d-flex flex-column flex-row@md gap-md justify-between">
                <div>
                    <h3 class="mt-0 mb-xs">Student Information</h3>
                    <p class="mb-xs"><strong>Name:</strong> <?= htmlspecialchars($selectedJustification['first_name'] . ' ' . $selectedJustification['last_name']) ?></p>
                    <p class="mb-0"><strong>Class:</strong> <?= htmlspecialchars($selectedJustification['class_code']) ?></p>
                </div>

                <div>
                    <h3 class="mt-0 mb-xs">Absence Details</h3>
                    <p class="mb-xs"><strong>Date:</strong> <?= date('d.m.Y', strtotime($selectedJustification['period_date'])) ?></p>
                    <p class="mb-xs"><strong>Period:</strong> <?= htmlspecialchars($selectedJustification['period_label']) ?></p>
                    <p class="mb-0"><strong>Class:</strong> <?= htmlspecialchars($selectedJustification['subject_name'] . ' - ' . $selectedJustification['class_title']) ?></p>
                </div>
            </div>
        </div>

        <div class="mb-lg">
            <h3 class="mt-0 mb-sm">Justification</h3>
            <div class="bg-tertiary p-md rounded mb-lg">
                <p class="mb-0"><?= nl2br(htmlspecialchars($selectedJustification['justification'])) ?></p>
            </div>

            <?php if (!empty($selectedJustification['justification_file'])): ?>
                <h3 class="mt-0 mb-sm">Supporting Document</h3>
                <div class="bg-tertiary p-md rounded mb-lg">
                    <a href="/uwuweb/uploads/justifications/<?= htmlspecialchars($selectedJustification['justification_file']) ?>" target="_blank" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-xs">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        View Document
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex flex-column flex-row@md gap-md mb-lg">
            <!-- Approve Form -->
            <form method="POST" action="justifications.php" style="flex: 1;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="absence_id" value="<?= $selectedJustification['att_id'] ?>">

                <div class="card" style="background-color: rgba(0, 200, 83, 0.05);">
                    <h3 class="mt-0 mb-md">Approve Justification</h3>
                    <p class="mb-md">Approving this justification will mark the absence as justified.</p>
                    <button type="submit" name="approve_justification" class="btn btn-primary">Approve Justification</button>
                </div>
            </form>

            <!-- Reject Form -->
            <form method="POST" action="justifications.php" style="flex: 1;">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="absence_id" value="<?= $selectedJustification['att_id'] ?>">

                <div class="card" style="background-color: rgba(244, 67, 54, 0.05);">
                    <h3 class="mt-0 mb-sm">Reject Justification</h3>

                    <div class="form-group">
                        <label for="reject_reason" class="form-label">Reason for Rejection</label>
                        <textarea id="reject_reason" name="reject_reason" class="form-input" rows="3" required></textarea>
                    </div>

                    <button type="submit" name="reject_justification" class="btn btn-primary">Reject Justification</button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Justifications List View -->
    <div class="card card-entrance mt-xl">
        <div class="d-flex justify-between items-center mb-lg">
            <h2 class="mt-0 mb-0">Pending Justifications</h2>
        </div>

        <?php if (empty($justifications)): ?>
            <div class="bg-tertiary p-lg text-center rounded">
                <p class="mb-sm">No pending justifications found.</p>
                <p class="text-secondary mb-0">All absence justifications have been processed.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Date</th>
                        <th>Period</th>
                        <th>Has File</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($justifications as $justification): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($justification['last_name'] . ', ' . $justification['first_name']) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($justification['class_code']) ?>
                            </td>
                            <td>
                                <?= date('d.m.Y', strtotime($justification['period_date'])) ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($justification['period_label']) ?>
                            </td>
                            <td>
                                <?= !empty($justification['justification_file']) ?
                                    '<span class="badge status-info">Yes</span>' :
                                    '<span class="badge">No</span>' ?>
                            </td>
                            <td>
                                <a href="justifications.php?id=<?= $justification['att_id'] ?>" class="btn btn-primary btn-sm">Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
