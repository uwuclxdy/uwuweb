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

// Add CSS link for this specific page
echo '<link rel="stylesheet" href="/uwuweb/assets/css/teacher-justifications.css">';

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId(getUserId());
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Database connection
$pdo = safeGetDBConnection('teacher/justifications.php');

// Get classes taught by this teacher
function getTeacherClasses($teacherId) {
    $pdo = safeGetDBConnection('getTeacherClasses()', false);
    if (!$pdo) {
        error_log("Database connection failed in getTeacherClasses()");
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT 
            c.class_id, 
            c.title,
            s.subject_id,
            s.name as subject_name,
            t.term_id,
            t.name as term_name
         FROM classes c
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN terms t ON c.term_id = t.term_id
         WHERE c.teacher_id = :teacher_id
         ORDER BY t.start_date DESC, s.name"
    );

    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get pending justifications for a teacher's classes
function getPendingJustifications($teacherId, $classFilter = null, $statusFilter = null) {
    $pdo = safeGetDBConnection('getPendingJustifications()', false);
    if (!$pdo) {
        error_log("Database connection failed in getPendingJustifications()");
        return [];
    }

    $query = "SELECT 
                a.att_id, 
                a.status, 
                a.justification, 
                a.justification_file,
                a.approved, 
                p.period_id,
                p.period_date, 
                p.period_label, 
                c.class_id,
                c.title as class_title, 
                s.subject_id,
                s.name as subject_name,
                e.enroll_id,
                st.student_id,
                st.first_name,
                st.last_name
             FROM attendance a
             JOIN enrollments e ON a.enroll_id = e.enroll_id
             JOIN periods p ON a.period_id = p.period_id
             JOIN classes c ON p.class_id = c.class_id
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN students st ON e.student_id = st.student_id
             WHERE c.teacher_id = :teacher_id 
             AND a.status = 'A' 
             AND a.justification IS NOT NULL";

    $params = ['teacher_id' => $teacherId];

    // Add class filter if provided
    if ($classFilter) {
        $query .= " AND c.class_id = :class_id";
        $params['class_id'] = $classFilter;
    }

    // Add status filter if provided
    if ($statusFilter === 'pending') {
        $query .= " AND a.approved IS NULL";
    } elseif ($statusFilter === 'approved') {
        $query .= " AND a.approved = 1";
    } elseif ($statusFilter === 'rejected') {
        $query .= " AND a.approved = 0";
    }

    $query .= " ORDER BY p.period_date DESC, p.period_label ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get detailed information about a specific justification
function getJustificationById($absenceId) {
    $pdo = safeGetDBConnection('getJustificationById()', false);
    if (!$pdo) {
        error_log("Database connection failed in getJustificationById()");
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT 
            a.att_id, 
            a.status, 
            a.justification,
            a.justification_file,
            a.approved,
            a.reject_reason, 
            p.period_id,
            p.period_date, 
            p.period_label, 
            c.class_id,
            c.title as class_title, 
            s.subject_id,
            s.name as subject_name,
            e.enroll_id,
            st.student_id,
            st.first_name,
            st.last_name
         FROM attendance a
         JOIN enrollments e ON a.enroll_id = e.enroll_id
         JOIN periods p ON a.period_id = p.period_id
         JOIN classes c ON p.class_id = c.class_id
         JOIN subjects s ON c.subject_id = s.subject_id
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
        error_log("Database connection failed in approveJustification()");
        return false;
    }

    $stmt = $pdo->prepare(
        "UPDATE attendance 
         SET approved = 1,
             reject_reason = NULL 
         WHERE att_id = :att_id"
    );

    return $stmt->execute(['att_id' => $absenceId]);
}

// Reject a justification with a reason
function rejectJustification($absenceId, $reason) {
    $pdo = safeGetDBConnection('rejectJustification()', false);
    if (!$pdo) {
        error_log("Database connection failed in rejectJustification()");
        return false;
    }

    $stmt = $pdo->prepare(
        "UPDATE attendance 
         SET approved = 0,
             reject_reason = :reason
         WHERE att_id = :att_id"
    );

    return $stmt->execute([
        'att_id' => $absenceId,
        'reason' => $reason
    ]);
}

// Get justification file information
function getJustificationFileInfo($filename) {
    if (empty($filename)) {
        return null;
    }

    $filepath = '../uploads/justifications/' . $filename;

    if (file_exists($filepath)) {
        return [
            'name' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath),
            'type' => mime_content_type($filepath)
        ];
    }

    return null;
}

// Process form submissions
$message = '';
$messageType = '';

// Handle justification actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        $absenceId = isset($_POST['absence_id']) ? (int)$_POST['absence_id'] : 0;
        $action = $_POST['action'] ?? '';

        if ($absenceId <= 0) {
            $message = 'Invalid absence selected.';
            $messageType = 'error';
        } else {
            // Check if this justification belongs to a class taught by this teacher
            $justification = getJustificationById($absenceId);

            if (!$justification) {
                $message = 'Justification not found.';
                $messageType = 'error';
            } else {
                // Get all classes taught by this teacher
                $teacherClasses = getTeacherClasses($teacherId);
                $classIds = array_column($teacherClasses, 'class_id');

                // Check if this justification's class is taught by the current teacher
                if (!in_array($justification['class_id'], $classIds, true)) {
                    $message = 'You do not have permission to manage this justification.';
                    $messageType = 'error';
                } else if ($action === 'approve') {
                    if (approveJustification($absenceId)) {
                        $message = 'Justification approved successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Error approving justification. Please try again.';
                        $messageType = 'error';
                    }
                } elseif ($action === 'reject') {
                    $rejectReason = isset($_POST['reject_reason']) ? trim($_POST['reject_reason']) : '';

                    if (empty($rejectReason)) {
                        $message = 'Please provide a reason for rejection.';
                        $messageType = 'error';
                    } else if (rejectJustification($absenceId, $rejectReason)) {
                            $message = 'Justification rejected successfully.';
                            $messageType = 'success';
                        } else {
                            $message = 'Error rejecting justification. Please try again.';
                            $messageType = 'error';
                        }
                } else {
                    $message = 'Invalid action selected.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get filter parameters
$classFilter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$statusFilter = $_GET['status'] ?? null;

// Get teacher classes and justifications
$classes = getTeacherClasses($teacherId);
$justifications = getPendingJustifications($teacherId, $classFilter, $statusFilter);

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include page header
include '../includes/header.php';
?>

<div class="page-container">
    <h1 class="page-title">Student Absence Justifications</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Information</h3>
        </div>
        <div class="card-body">
            <p>Review and approve or reject student absence justifications for your classes.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filters</h3>
        </div>
        <div class="card-body">
            <form method="get" action="/uwuweb/teacher/justifications.php" class="filter-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="form-group">
                    <label for="class-filter" class="form-label">Filter by Class:</label>
                    <select id="class-filter" name="class_id" class="form-input" onchange="this.form.submit()">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= (int)$class['class_id'] ?>" <?= ($classFilter == $class['class_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['subject_name'] . ' - ' . $class['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status-filter" class="form-label">Filter by Status:</label>
                    <select id="status-filter" name="status" class="form-input" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?= ($statusFilter === 'pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($justifications)): ?>
        <div class="card">
            <div class="card-body">
                <p class="text-secondary">No justifications found matching your criteria.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?php
                    $title = 'Justifications';
                    if ($statusFilter === 'pending') {
                        $title = 'Pending Justifications';
                    }
                    elseif ($statusFilter === 'approved') {
                        $title = 'Approved Justifications';
                    }
                    elseif ($statusFilter === 'rejected') {
                        $title = 'Rejected Justifications';
                    }

                    if ($classFilter) {
                        foreach ($classes as $class) {
                            if ($class['class_id'] == $classFilter) {
                                $title .= ' - ' . $class['subject_name'] . ' - ' . $class['title'];
                                break;
                            }
                        }
                    }
                    echo htmlspecialchars($title);
                    ?>
                </h2>
            </div>

            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Date</th>
                                <th>Class</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($justifications as $justification): ?>
                                <?php
                                    $formattedDate = date('d.m.Y', strtotime($justification['period_date']));
                                    $studentName = $justification['first_name'] . ' ' . $justification['last_name'];
                                    $className = $justification['subject_name'] . ' - ' . $justification['class_title'];

                                    $status = '';
                                    $statusClass = '';

                                    if ($justification['approved'] === null) {
                                        $status = 'Pending';
                                        $statusClass = 'badge badge-warning';
                                    } elseif ($justification['approved'] == 1) {
                                        $status = 'Approved';
                                        $statusClass = 'badge badge-success';
                                    } else {
                                        $status = 'Rejected';
                                        $statusClass = 'badge badge-error';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($studentName) ?></td>
                                    <td><?= htmlspecialchars($formattedDate) ?></td>
                                    <td><?= htmlspecialchars($className) ?></td>
                                    <td><?= htmlspecialchars($justification['period_label']) ?></td>
                                    <td><span class="<?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                                    <td>
                                        <button class="btn btn-secondary view-justification"
                                                data-absence-id="<?= (int)$justification['att_id'] ?>">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Justification Details Modal -->
    <div id="justification-details-modal" class="modal" style="display: none;">
        <div class="modal-content card">
            <div class="card-header">
                <h3 class="card-title">Justification Details</h3>
                <span class="close-modal">&times;</span>
            </div>
            
            <div class="card-body">
                <div id="justification-details-content" class="justification-details">
                    <!-- Content loaded dynamically -->
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary close-modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // View Justification Details
        document.querySelectorAll('.view-justification').forEach(button => {
            button.addEventListener('click', function() {
                const absenceId = this.getAttribute('data-absence-id');

                // Fetch justification details via AJAX
                fetch('/uwuweb/api/justifications.php?action=get&absence_id=' + absenceId)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            displayJustificationDetails(data.justification);
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching justification details:', error);
                        alert('Error loading justification details. Please try again.');
                    });
            });
        });

        // Close modals
        document.querySelectorAll('.close-modal').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.modal').forEach(modal => {
                    modal.style.display = 'none';
                });
            });
        });

        // Close modal when clicking outside it
        window.addEventListener('click', function(event) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Function to display justification details in the modal
        function displayJustificationDetails(justification) {
            const modal = document.getElementById('justification-details-modal');
            const content = document.getElementById('justification-details-content');

            // Format date
            const date = new Date(justification.period_date);
            const formattedDate = date.getDate().toString().padStart(2, '0') + '.' +
                                  (date.getMonth() + 1).toString().padStart(2, '0') + '.' +
                                  date.getFullYear();

            // Create status text and class
            let statusText;
            let statusClass;
            let actionButtons = '';

            if (justification.approved === null) {
                statusText = 'Pending';
                statusClass = 'badge badge-warning';

                // Add approve/reject buttons for pending justifications
                actionButtons = `
                    <div class="form-actions">
                        <form method="post" action="/uwuweb/teacher/justifications.php" class="approve-form">
                            <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                            <input type="hidden" name="absence_id" value="${justification.att_id}">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-primary">Approve</button>
                        </form>

                        <button type="button" class="btn btn-error show-reject-form">Reject</button>
                    </div>

                    <div class="reject-form-container" style="display: none;">
                        <form method="post" action="/uwuweb/teacher/justifications.php" class="reject-form">
                            <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                            <input type="hidden" name="absence_id" value="${justification.att_id}">
                            <input type="hidden" name="action" value="reject">

                            <div class="form-group">
                                <label for="reject-reason" class="form-label">Reason for rejection:</label>
                                <textarea name="reject_reason" id="reject-reason" class="form-input" rows="3" required></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary hide-reject-form">Cancel</button>
                                <button type="submit" class="btn btn-error">Confirm Rejection</button>
                            </div>
                        </form>
                    </div>
                `;
            } else if (justification.approved === 1) {
                statusText = 'Approved';
                statusClass = 'badge badge-success';
            } else {
                statusText = 'Rejected';
                statusClass = 'badge badge-error';
            }

            // Generate file link if there's a file
            let fileSection = '';
            if (justification.justification_file) {
                fileSection = `
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Supporting Document</h4>
                        </div>
                        <div class="card-body">
                            <a href="/uwuweb/uploads/justifications/${justification.justification_file}" class="btn btn-secondary" target="_blank">
                                View Document
                            </a>
                        </div>
                    </div>
                `;
            }

            // Generate reject reason section if rejected
            let rejectReasonSection = '';
            if (justification.approved === 0 && justification.reject_reason) {
                rejectReasonSection = `
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Reason for Rejection</h4>
                        </div>
                        <div class="card-body">
                            <p>${justification.reject_reason}</p>
                        </div>
                    </div>
                `;
            }

            // Set the modal content
            content.innerHTML = `
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Student Information</h4>
                    </div>
                    <div class="card-body">
                        <p>${justification.first_name} ${justification.last_name}</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Absence Details</h4>
                    </div>
                    <div class="card-body">
                        <p>
                            <strong>Date:</strong> ${formattedDate}<br>
                            <strong>Class:</strong> ${justification.subject_name} - ${justification.class_title}<br>
                            <strong>Period:</strong> ${justification.period_label}<br>
                            <strong>Status:</strong> <span class="${statusClass}">${statusText}</span>
                        </p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Justification</h4>
                    </div>
                    <div class="card-body">
                        <p>${justification.justification}</p>
                    </div>
                </div>

                ${fileSection}
                ${rejectReasonSection}
                ${actionButtons}
            `;

            // Add event listeners for reject form
            modal.style.display = 'flex';
            
            // Event listener for showing reject form
            const showRejectBtn = modal.querySelector('.show-reject-form');
            if (showRejectBtn) {
                showRejectBtn.addEventListener('click', function() {
                    modal.querySelector('.reject-form-container').style.display = 'block';
                    this.parentElement.style.display = 'none';
                });
            }

            // Event listener for hiding reject form
            const hideRejectBtn = modal.querySelector('.hide-reject-form');
            if (hideRejectBtn) {
                hideRejectBtn.addEventListener('click', function() {
                    modal.querySelector('.reject-form-container').style.display = 'none';
                    modal.querySelector('.form-actions').style.display = 'flex';
                });
            }
        }
    });
</script>

<?php
// Include page footer
include '../includes/footer.php';
?>
