<?php
/**
 * Teacher Grade Book
 * 
 * Provides interface for teachers to manage student grades
 * Supports viewing, adding, and editing grades for assigned classes
 * 
 * Functions:
 * - getTeacherId($userId) - Retrieves teacher ID from user ID
 * - getTeacherClasses($teacherId) - Gets classes taught by a teacher
 * - getClassStudents($classId) - Gets students enrolled in a class
 * - getGradeItems($classId) - Gets grade items for a class
 * - getClassGrades($classId) - Gets all grades for a class
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Require teacher role for access
requireRole(ROLE_TEACHER);

// Get teacher ID based on user ID
function getTeacherId($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT teacher_id FROM teachers WHERE user_id = :user_id");
    $stmt->execute(['user_id' => $userId]);
    $result = $stmt->fetch();
    return $result ? $result['teacher_id'] : null;
}

// Get classes taught by teacher
function getTeacherClasses($teacherId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT c.class_id, c.title, s.name AS subject_name, t.name AS term_name
         FROM classes c
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN terms t ON c.term_id = t.term_id
         WHERE c.teacher_id = :teacher_id
         ORDER BY t.start_date DESC, s.name ASC"
    );
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get students enrolled in a specific class
function getClassStudents($classId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT e.enroll_id, s.student_id, s.first_name, s.last_name, s.class_code
         FROM enrollments e
         JOIN students s ON e.student_id = s.student_id
         WHERE e.class_id = :class_id
         ORDER BY s.last_name ASC, s.first_name ASC"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get grade items for a specific class
function getGradeItems($classId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT item_id, name, max_points, weight
         FROM grade_items
         WHERE class_id = :class_id
         ORDER BY name ASC"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get grades for a specific class and its students
function getClassGrades($classId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare(
        "SELECT g.grade_id, g.enroll_id, g.item_id, g.points, g.comment
         FROM grades g
         JOIN enrollments e ON g.enroll_id = e.enroll_id
         WHERE e.class_id = :class_id"
    );
    $stmt->execute(['class_id' => $classId]);
    
    // Index grades by enroll_id and item_id for easier access
    $grades = [];
    foreach ($stmt->fetchAll() as $grade) {
        $grades[$grade['enroll_id']][$grade['item_id']] = [
            'grade_id' => $grade['grade_id'],
            'points' => $grade['points'],
            'comment' => $grade['comment']
        ];
    }
    
    return $grades;
}

// Get selected class and filters from request
$teacherId = getTeacherId(getUserId());
$classes = $teacherClasses = getTeacherClasses($teacherId);
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_id'] ?? null);

// Only proceed if teacher has classes
$hasClasses = !empty($classes);

// If a class is selected, get students, grade items, and grades
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$gradeItems = $selectedClassId ? getGradeItems($selectedClassId) : [];
$grades = $selectedClassId ? getClassGrades($selectedClassId) : [];

// Get selected class details for display
$selectedClass = null;
if ($selectedClassId) {
    foreach ($classes as $class) {
        if ($class['class_id'] == $selectedClassId) {
            $selectedClass = $class;
            break;
        }
    }
}

// Generate CSRF token for AJAX requests
$csrfToken = generateCSRFToken();

// Include page header
include '../includes/header.php';
?>

<div class="gradebook-container">
    <h1>Teacher Grade Book</h1>
    
    <?php if (!$hasClasses): ?>
        <div class="alert alert-error">
            You are not assigned to any classes. Please contact an administrator.
        </div>
    <?php else: ?>
        <!-- Class selector -->
        <div class="class-selector">
            <form method="get" action="">
                <label for="class_id">Select Class:</label>
                <select name="class_id" id="class_id" onchange="this.form.submit()">
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= htmlspecialchars($class['class_id']) ?>" 
                                <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars("{$class['subject_name']} - {$class['title']} ({$class['term_name']})") ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <?php if ($selectedClassId): ?>
            <div class="class-details">
                <h2><?= htmlspecialchars($selectedClass['subject_name']) ?></h2>
                <h3><?= htmlspecialchars($selectedClass['title']) ?> - <?= htmlspecialchars($selectedClass['term_name']) ?></h3>
                
                <div class="action-buttons">
                    <button id="btn-add-grade-item" class="btn">Add Grade Item</button>
                </div>
                
                <!-- Status message container for AJAX feedback -->
                <div id="status-message" class="status-message" style="display: none;"></div>
                
                <?php if (empty($gradeItems)): ?>
                    <div class="alert alert-info">
                        No grade items have been created for this class yet. Use the "Add Grade Item" button to create one.
                    </div>
                <?php elseif (empty($students)): ?>
                    <div class="alert alert-info">
                        No students are enrolled in this class.
                    </div>
                <?php else: ?>
                    <!-- Grade Book Table -->
                    <div class="gradebook-table-container">
                        <table class="gradebook-table">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="student-name">Student</th>
                                    <?php foreach ($gradeItems as $item): ?>
                                        <th class="grade-item">
                                            <?= htmlspecialchars($item['name']) ?>
                                            <span class="max-points">(<?= htmlspecialchars($item['max_points']) ?>)</span>
                                        </th>
                                    <?php endforeach; ?>
                                    <th rowspan="2" class="average">Average</th>
                                </tr>
                                <tr class="weight-row">
                                    <?php foreach ($gradeItems as $item): ?>
                                        <th class="weight">
                                            <?= htmlspecialchars("Weight: {$item['weight']}") ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td class="student-name">
                                            <?= htmlspecialchars("{$student['last_name']}, {$student['first_name']}") ?>
                                            <span class="class-code">[<?= htmlspecialchars($student['class_code']) ?>]</span>
                                        </td>
                                        
                                        <?php 
                                        $totalPoints = 0;
                                        $totalMaxPoints = 0;
                                        $totalWeight = 0;
                                        $weightedPoints = 0;
                                        
                                        foreach ($gradeItems as $item): 
                                            $enrollId = $student['enroll_id'];
                                            $itemId = $item['item_id'];
                                            $gradeValue = isset($grades[$enrollId][$itemId]) ? 
                                                $grades[$enrollId][$itemId]['points'] : '';
                                            $comment = isset($grades[$enrollId][$itemId]) ? 
                                                $grades[$enrollId][$itemId]['comment'] : '';
                                            
                                            // Calculate weighted average if grade exists
                                            if ($gradeValue !== '') {
                                                $points = (float)$gradeValue;
                                                $maxPoints = (float)$item['max_points'];
                                                $weight = (float)$item['weight'];
                                                
                                                if ($maxPoints > 0) {
                                                    $weightedPoints += ($points / $maxPoints) * $weight;
                                                    $totalWeight += $weight;
                                                }
                                            }
                                        ?>
                                            <td class="grade-cell" 
                                                data-enroll-id="<?= htmlspecialchars($enrollId) ?>" 
                                                data-item-id="<?= htmlspecialchars($itemId) ?>"
                                                data-comment="<?= htmlspecialchars($comment) ?>">
                                                <span class="grade-display">
                                                    <?= htmlspecialchars($gradeValue) ?>
                                                </span>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <td class="average" id="average-<?= htmlspecialchars($enrollId) ?>">
                                            <?php
                                            if ($totalWeight > 0) {
                                                $average = ($weightedPoints / $totalWeight) * 100;
                                                echo htmlspecialchars(number_format($average, 1) . '%');
                                            } else {
                                                echo '&ndash;';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Grade Item Form Template (hidden) -->
            <div id="grade-item-form-container" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>Add Grade Item</h3>
                    <form id="grade-item-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="add_grade_item">
                        <input type="hidden" name="class_id" value="<?= htmlspecialchars($selectedClassId) ?>">
                        
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_points">Maximum Points:</label>
                            <input type="number" id="max_points" name="max_points" min="1" value="100" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="weight">Weight:</label>
                            <input type="number" id="weight" name="weight" min="0.1" step="0.1" value="1" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-cancel" onclick="closeGradeItemForm()">Cancel</button>
                            <button type="submit" class="btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Grade Edit Form Template (hidden) -->
            <div id="grade-edit-form-container" class="modal" style="display: none;">
                <div class="modal-content">
                    <h3>Edit Grade</h3>
                    <form id="grade-edit-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_grade">
                        <input type="hidden" name="enroll_id" id="edit_enroll_id">
                        <input type="hidden" name="item_id" id="edit_item_id">
                        
                        <div class="form-group">
                            <label for="points">Points:</label>
                            <input type="number" id="points" name="points" step="0.1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="comment">Comment:</label>
                            <textarea id="comment" name="comment" rows="3"></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-cancel" onclick="closeGradeEditForm()">Cancel</button>
                            <button type="submit" class="btn">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    // Function to show the grade item form
    function showGradeItemForm() {
        document.getElementById('grade-item-form-container').style.display = 'flex';
    }
    
    // Function to close the grade item form
    function closeGradeItemForm() {
        document.getElementById('grade-item-form-container').style.display = 'none';
    }
    
    // Function to show the grade edit form
    function showGradeEditForm(enrollId, itemId, points, comment) {
        const form = document.getElementById('grade-edit-form');
        document.getElementById('edit_enroll_id').value = enrollId;
        document.getElementById('edit_item_id').value = itemId;
        document.getElementById('points').value = points || '';
        document.getElementById('comment').value = comment || '';
        
        document.getElementById('grade-edit-form-container').style.display = 'flex';
    }
    
    // Function to close the grade edit form
    function closeGradeEditForm() {
        document.getElementById('grade-edit-form-container').style.display = 'none';
    }
    
    // Function to show status message
    function showStatusMessage(message, type = 'success') {
        const statusElement = document.getElementById('status-message');
        statusElement.textContent = message;
        statusElement.className = `status-message ${type}`;
        statusElement.style.display = 'block';
        
        // Hide message after 5 seconds
        setTimeout(() => {
            statusElement.style.display = 'none';
        }, 5000);
    }
    
    // Event listener for the Add Grade Item button
    document.getElementById('btn-add-grade-item')?.addEventListener('click', showGradeItemForm);
    
    // Event listeners for grade cells (for inline editing)
    document.querySelectorAll('.grade-cell').forEach(cell => {
        cell.addEventListener('click', function() {
            const enrollId = this.dataset.enrollId;
            const itemId = this.dataset.itemId;
            const points = this.querySelector('.grade-display').textContent.trim();
            const comment = this.dataset.comment || '';
            
            showGradeEditForm(enrollId, itemId, points, comment);
        });
    });
    
    // Grade Item Form submission
    document.getElementById('grade-item-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data and convert to FormData object for AJAX submission
        const formData = new FormData(this);
        
        // Send AJAX request to API
        fetch('/api/grades.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showStatusMessage(data.message, 'success');
                // Reload the page to show the new grade item
                // In a more advanced implementation, we could dynamically add the item to the table
                window.location.reload();
            } else {
                showStatusMessage(data.error, 'error');
            }
        })
        .catch(error => {
            showStatusMessage('An error occurred: ' + error, 'error');
        })
        .finally(() => {
            closeGradeItemForm();
        });
    });
    
    // Grade Edit Form submission
    document.getElementById('grade-edit-form')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(this);
        const enrollId = formData.get('enroll_id');
        const itemId = formData.get('item_id');
        const points = formData.get('points');
        const comment = formData.get('comment');
        
        // Send AJAX request to save the grade
        fetch('/api/grades.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the cell with the new grade value
                const cell = document.querySelector(`.grade-cell[data-enroll-id="${enrollId}"][data-item-id="${itemId}"]`);
                if (cell) {
                    cell.querySelector('.grade-display').textContent = points;
                    cell.dataset.comment = comment;
                    
                    // Add highlighting effect to show the cell was updated
                    cell.classList.add('updated');
                    setTimeout(() => {
                        cell.classList.remove('updated');
                    }, 2000);
                    
                    // Recalculate student average (would typically be done server-side)
                    calculateStudentAverage(enrollId);
                }
                
                showStatusMessage(data.message, 'success');
            } else {
                showStatusMessage(data.error, 'error');
            }
        })
        .catch(error => {
            showStatusMessage('An error occurred: ' + error, 'error');
        })
        .finally(() => {
            closeGradeEditForm();
        });
    });
    
    // Function to calculate student average (simplified version)
    // In a real implementation, this would be calculated server-side
    function calculateStudentAverage(enrollId) {
        // Get all grade cells for this student
        const cells = document.querySelectorAll(`.grade-cell[data-enroll-id="${enrollId}"]`);
        let totalWeightedPoints = 0;
        let totalWeight = 0;
        
        cells.forEach(cell => {
            const itemId = cell.dataset.itemId;
            const gradeValue = cell.querySelector('.grade-display').textContent.trim();
            
            if (gradeValue !== '') {
                // Find the item's weight and max points from the table header
                const headerIndex = [...cell.parentNode.children].indexOf(cell);
                const weightElement = document.querySelector(`.weight-row th:nth-child(${headerIndex})`);
                const maxPointsElement = document.querySelector(`.grade-item:nth-child(${headerIndex}) .max-points`);
                
                if (weightElement && maxPointsElement) {
                    const weightText = weightElement.textContent;
                    const maxPointsText = maxPointsElement.textContent;
                    
                    const weight = parseFloat(weightText.replace('Weight: ', ''));
                    const maxPoints = parseFloat(maxPointsText.replace('(', '').replace(')', ''));
                    
                    if (!isNaN(weight) && !isNaN(maxPoints) && maxPoints > 0) {
                        const points = parseFloat(gradeValue);
                        totalWeightedPoints += (points / maxPoints) * weight;
                        totalWeight += weight;
                    }
                }
            }
        });
        
        // Update the average cell
        const averageCell = document.getElementById(`average-${enrollId}`);
        if (averageCell && totalWeight > 0) {
            const average = (totalWeightedPoints / totalWeight) * 100;
            averageCell.textContent = average.toFixed(1) + '%';
            
            // Highlight the average cell
            averageCell.classList.add('updated');
            setTimeout(() => {
                averageCell.classList.remove('updated');
            }, 2000);
        }
    }
</script>

<style>
    /* Gradebook specific styles */
    .gradebook-container {
        padding: 1rem 0;
    }
    
    .class-selector {
        margin: 1rem 0;
    }
    
    .class-selector select {
        padding: 0.5rem;
        width: 100%;
        max-width: 400px;
    }
    
    .class-details {
        margin-top: 2rem;
    }
    
    .action-buttons {
        margin: 1rem 0;
    }
    
    .gradebook-table-container {
        overflow-x: auto;
        margin-top: 1rem;
    }
    
    .gradebook-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--border-color);
    }
    
    .gradebook-table th, 
    .gradebook-table td {
        padding: 0.75rem;
        border: 1px solid var(--border-color);
        text-align: center;
    }
    
    .gradebook-table th {
        background-color: var(--primary-color);
        color: var(--text-light);
    }
    
    .gradebook-table .weight-row th {
        font-size: 0.8rem;
        background-color: var(--secondary-color);
        font-weight: normal;
    }
    
    .gradebook-table .student-name {
        text-align: left;
        white-space: nowrap;
    }
    
    .gradebook-table .grade-item {
        min-width: 80px;
    }
    
    .gradebook-table .max-points {
        font-size: 0.8rem;
        display: block;
    }
    
    .gradebook-table .class-code {
        font-size: 0.8rem;
        color: #666;
    }
    
    .gradebook-table .grade-cell {
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    .gradebook-table .grade-cell:hover {
        background-color: #f0f0f0;
    }
    
    .gradebook-table .grade-cell.updated,
    .gradebook-table .average.updated {
        animation: highlightBackground 2s;
    }
    
    @keyframes highlightBackground {
        0% { background-color: #ffeb3b; }
        100% { background-color: transparent; }
    }
    
    /* Status message styles */
    .status-message {
        padding: 0.75rem;
        margin: 1rem 0;
        border-radius: 4px;
        display: none;
    }
    
    .status-message.success {
        background-color: #dff0d8;
        color: #3c763d;
        border: 1px solid #d6e9c6;
    }
    
    .status-message.error {
        background-color: #f2dede;
        color: #a94442;
        border: 1px solid #ebccd1;
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
        align-items: center;
        justify-content: center;
        z-index: 10;
    }
    
    .modal-content {
        background-color: white;
        padding: 2rem;
        border-radius: 8px;
        width: 100%;
        max-width: 500px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    }
    
    .form-group {
        margin-bottom: 1rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    
    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid var(--border-color);
        border-radius: 4px;
    }
    
    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
        margin-top: 1.5rem;
    }
    
    .btn-cancel {
        background-color: #ccc;
    }
    
    .btn-cancel:hover {
        background-color: #999;
    }
</style>

<?php
// Include page footer
include '../includes/footer.php';
?>