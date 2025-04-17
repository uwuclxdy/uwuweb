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

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Require teacher role to access this page
requireRole(ROLE_TEACHER);

// Get teacher ID based on user ID
$teacherId = getTeacherId();

if (!$teacherId) {
    header("Location: ../dashboard.php?error=invalid_teacher");
    exit;
}

// Get classes taught by this teacher
function getTeacherClasses($teacherId) {
    $pdo = getDBConnection();
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
         ORDER BY t.start_date DESC, s.name ASC"
    );
    
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get pending justifications for a teacher's classes
function getPendingJustifications($teacherId, $classFilter = null, $statusFilter = null) {
    $pdo = getDBConnection();
    
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
    $pdo = getDBConnection();
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
    $pdo = getDBConnection();
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
    $pdo = getDBConnection();
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
        $fileInfo = [
            'name' => $filename,
            'path' => $filepath,
            'size' => filesize($filepath),
            'type' => mime_content_type($filepath)
        ];
        return $fileInfo;
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
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
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
                if (!in_array($justification['class_id'], $classIds)) {
                    $message = 'You do not have permission to manage this justification.';
                    $messageType = 'error';
                } else {
                    // Process the action
                    if ($action === 'approve') {
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
                        } else {
                            if (rejectJustification($absenceId, $rejectReason)) {
                                $message = 'Justification rejected successfully.';
                                $messageType = 'success';
                            } else {
                                $message = 'Error rejecting justification. Please try again.';
                                $messageType = 'error';
                            }
                        }
                    } else {
                        $message = 'Invalid action selected.';
                        $messageType = 'error';
                    }
                }
            }
        }
    }
}

// Get filter parameters
$classFilter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : null;

// Get teacher classes and justifications
$classes = getTeacherClasses($teacherId);
$justifications = getPendingJustifications($teacherId, $classFilter, $statusFilter);

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include page header
include '../includes/header.php';
?>

<div class="justifications-container">
    <h1>Student Absence Justifications</h1>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <div class="justifications-intro">
        <p>Review and approve or reject student absence justifications for your classes.</p>
    </div>
    
    <div class="filters">
        <form method="get" action="" class="filter-form">
            <div class="filter-group">
                <label for="class-filter">Filter by Class:</label>
                <select id="class-filter" name="class_id" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= (int)$class['class_id'] ?>" <?= ($classFilter == $class['class_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['subject_name'] . ' - ' . $class['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="status-filter">Filter by Status:</label>
                <select id="status-filter" name="status" onchange="this.form.submit()">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= ($statusFilter == 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= ($statusFilter == 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= ($statusFilter == 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
        </form>
    </div>
    
    <?php if (empty($justifications)): ?>
        <div class="justifications-list empty">
            <p class="empty-message">No justifications found matching your criteria.</p>
        </div>
    <?php else: ?>
        <div class="justifications-list">
            <h2>
                <?php
                $title = 'Justifications';
                if ($statusFilter === 'pending') $title = 'Pending Justifications';
                elseif ($statusFilter === 'approved') $title = 'Approved Justifications';
                elseif ($statusFilter === 'rejected') $title = 'Rejected Justifications';
                
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
                                $statusClass = 'pending-review';
                            } elseif ($justification['approved'] == 1) {
                                $status = 'Approved';
                                $statusClass = 'approved';
                            } else {
                                $status = 'Rejected';
                                $statusClass = 'rejected';
                            }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($studentName) ?></td>
                            <td><?= htmlspecialchars($formattedDate) ?></td>
                            <td><?= htmlspecialchars($className) ?></td>
                            <td><?= htmlspecialchars($justification['period_label']) ?></td>
                            <td class="justification-status <?= $statusClass ?>">
                                <?= htmlspecialchars($status) ?>
                            </td>
                            <td>
                                <button class="btn btn-small view-justification" 
                                        data-absence-id="<?= (int)$justification['att_id'] ?>">
                                    View Details
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Justification Details Modal -->
    <div id="justification-details-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Justification Details</h3>
            
            <div id="justification-details-content" class="justification-details">
                <!-- Content loaded dynamically -->
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn close-modal">Close</button>
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
                fetch('../api/justifications.php?action=get&absence_id=' + absenceId)
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
            let statusText = '';
            let statusClass = '';
            let actionButtons = '';
            
            if (justification.approved === null) {
                statusText = 'Pending';
                statusClass = 'pending-review';
                
                // Add approve/reject buttons for pending justifications
                actionButtons = `
                    <div class="action-buttons">
                        <form method="post" action="" class="approve-form">
                            <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                            <input type="hidden" name="absence_id" value="${justification.att_id}">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-approve">Approve</button>
                        </form>
                        
                        <button type="button" class="btn btn-reject show-reject-form">Reject</button>
                    </div>
                    
                    <div class="reject-form-container" style="display: none;">
                        <form method="post" action="" class="reject-form">
                            <input type="hidden" name="csrf_token" value="${document.querySelector('input[name="csrf_token"]').value}">
                            <input type="hidden" name="absence_id" value="${justification.att_id}">
                            <input type="hidden" name="action" value="reject">
                            
                            <div class="form-group">
                                <label for="reject-reason">Reason for rejection:</label>
                                <textarea name="reject_reason" id="reject-reason" rows="3" required></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="button" class="btn btn-cancel hide-reject-form">Cancel</button>
                                <button type="submit" class="btn btn-confirm-reject">Confirm Rejection</button>
                            </div>
                        </form>
                    </div>
                `;
            } else if (justification.approved == 1) {
                statusText = 'Approved';
                statusClass = 'approved';
            } else {
                statusText = 'Rejected';
                statusClass = 'rejected';
            }
            
            // Generate file link if there's a file
            let fileSection = '';
            if (justification.justification_file) {
                fileSection = `
                    <div class="file-section">
                        <h4>Supporting Document:</h4>
                        <div class="file-info">
                            <a href="../uploads/justifications/${justification.justification_file}" target="_blank">
                                View Document
                            </a>
                        </div>
                    </div>
                `;
            }
            
            // Generate reject reason section if rejected
            let rejectReasonSection = '';
            if (justification.approved == 0 && justification.reject_reason) {
                rejectReasonSection = `
                    <div class="reject-reason-section">
                        <h4>Reason for Rejection:</h4>
                        <div class="reject-reason">
                            ${justification.reject_reason}
                        </div>
                    </div>
                `;
            }
            
            // Set the modal content
            content.innerHTML = `
                <div class="student-info">
                    <h4>Student:</h4>
                    <p>${justification.first_name} ${justification.last_name}</p>
                </div>
                
                <div class="absence-info">
                    <h4>Absence Details:</h4>
                    <p>
                        <strong>Date:</strong> ${formattedDate}<br>
                        <strong>Class:</strong> ${justification.subject_name} - ${justification.class_title}<br>
                        <strong>Period:</strong> ${justification.period_label}
                    </p>
                </div>
                
                <div class="justification-text-section">
                    <h4>Justification:</h4>
                    <div class="justification-text">
                        ${justification.justification}
                    </div>
                </div>
                
                ${fileSection}
                
                <div class="status-section">
                    <h4>Status:</h4>
                    <div class="status ${statusClass}">
                        ${statusText}
                    </div>
                </div>
                
                ${rejectReasonSection}
                
                ${actionButtons}
            `;
            
            // Show the modal
            modal.style.display = 'flex';
            
            // Add event listeners to the newly created elements
            if (justification.approved === null) {
                // Show/hide reject form
                document.querySelector('.show-reject-form').addEventListener('click', function() {
                    document.querySelector('.action-buttons').style.display = 'none';
                    document.querySelector('.reject-form-container').style.display = 'block';
                });
                
                document.querySelector('.hide-reject-form').addEventListener('click', function() {
                    document.querySelector('.action-buttons').style.display = 'flex';
                    document.querySelector('.reject-form-container').style.display = 'none';
                });
            }
        }
    });
</script>

<style>
    /* Justifications page specific styles */
    .justifications-container {
        padding: 1rem 0;
    }
    
    .justifications-intro {
        margin-bottom: 2rem;
    }
    
    .filters {
        margin: 1rem 0 2rem;
        padding: 1rem;
        background-color: #f5f5f5;
        border-radius: 4px;
    }
    
    .filter-form {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        min-width: 200px;
    }
    
    .filter-group label {
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .filter-group select {
        padding: 0.5rem;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    
    .justifications-list {
        margin: 2rem 0;
    }
    
    .justifications-list h2 {
        margin-bottom: 1rem;
    }
    
    .justifications-list.empty {
        padding: 2rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        text-align: center;
    }
    
    .empty-message {
        color: #666;
        font-style: italic;
    }
    
    .justification-status {
        font-weight: 500;
    }
    
    .justification-status.pending-review {
        color: #ffc107;
    }
    
    .justification-status.approved {
        color: #28a745;
    }
    
    .justification-status.rejected {
        color: #dc3545;
    }
    
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background-color: #fff;
        padding: 2rem;
        border-radius: 4px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }
    
    .close-modal {
        position: absolute;
        top: 15px;
        right: 15px;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
    }
    
    .close-modal:hover {
        color: #000;
    }
    
    /* Justification details styles */
    .justification-details h4 {
        margin-top: 1.5rem;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .justification-details .student-info h4,
    .justification-details .absence-info h4 {
        margin-top: 0;
    }
    
    .justification-text {
        padding: 1rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        margin-bottom: 1rem;
        white-space: pre-wrap;
    }
    
    .status {
        font-weight: 500;
    }
    
    .status.pending-review {
        color: #ffc107;
    }
    
    .status.approved {
        color: #28a745;
    }
    
    .status.rejected {
        color: #dc3545;
    }
    
    .file-info {
        padding: 0.5rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    .reject-reason {
        padding: 1rem;
        background-color: #f9f9f9;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    
    /* Action buttons */
    .action-buttons {
        display: flex;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .btn-approve {
        background-color: #28a745;
        color: white;
    }
    
    .btn-reject {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-cancel {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-confirm-reject {
        background-color: #dc3545;
        color: white;
    }
    
    .reject-form-container {
        margin-top: 1.5rem;
    }
    
    /* Form styles */
    .form-group {
        margin-bottom: 1.5rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-group textarea {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-family: inherit;
        font-size: 1rem;
    }
    
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    /* Alert styles */
    .alert {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 4px;
    }
    
    .alert-success {
        background-color: #d4edda;
        color: #155724;
    }
    
    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
    }
</style>

<?php
// Include page footer
include '../includes/footer.php';
?>