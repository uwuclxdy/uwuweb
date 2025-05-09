<?php
/**
 * Teacher Grade Book
 * /uwuweb/teacher/gradebook.php
 *
 * Provides interface for teachers to manage student grades
 * Supports viewing, adding, and editing grades for assigned classes
 *
 */

use Random\RandomException;

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php'; // header.php includes the CSS link, no need to change href there
require_once 'teacher_functions.php';

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId();
if (!$teacherId) die('Error: Teacher account not found.');

// Database connection - using safe connection to prevent null pointer exceptions
$pdo = safeGetDBConnection('teacher/gradebook.php');

// Process form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $message = 'Invalid form submission. Please try again.';
    $messageType = 'error';
} else if (isset($_POST['add_grade_item'])) {
    $classSubjectId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0; // Assuming class_id refers to class_subject_id here based on function usage
    $name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
    $description = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
    $maxPoints = isset($_POST['max_points']) ? (float)$_POST['max_points'] : 0;
    $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 1.00; // Default weight if not provided
    $date = $_POST['item_date'] ?? date('Y-m-d');

    if ($classSubjectId <= 0 || empty($name) || $maxPoints <= 0) {
        $message = 'Please fill out all required fields for the grade item (Class, Name, Max Points).';
        $messageType = 'error';
    } else if (!teacherHasAccessToClassSubject($classSubjectId, $teacherId)) {
        $message = 'You do not have permission to add grade items to this class.';
        $messageType = 'error';
    } else try {
        if (addGradeItemFunction($classSubjectId, $name, $maxPoints, $weight)) {
            $message = 'New grade item added successfully.';
            $messageType = 'success';
        } else {
            $message = 'Error adding grade item. Please check permissions or try again.';
            $messageType = 'error';
        }
    } catch (JsonException|Exception $e) {
    }
} else if (isset($_POST['save_grades'])) {
    $classSubjectId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $grades = $_POST['grade'] ?? [];
    $feedback = $_POST['feedback'] ?? [];

    if ($classSubjectId <= 0 || $itemId <= 0) {
        $message = 'Invalid grade data provided.';
        $messageType = 'error';
    } else if (!teacherHasAccessToClassSubject($classSubjectId)) {
        $message = 'You do not have permission to save grades for this class.';
        $messageType = 'error';
    } else {
        $successCount = 0;
        $failCount = 0;
        foreach ($grades as $enrollId => $pointsStr) {
            $points = ($pointsStr !== '' && is_numeric($pointsStr)) ? (float)$pointsStr : null; // Allow null for empty grades
            $studentFeedback = isset($feedback[$enrollId]) ? trim($feedback[$enrollId]) : null;

            try {
                if (saveGrade((int)$enrollId, $itemId, $points, $studentFeedback)) $successCount++; else $failCount++;
            } catch (JsonException|Exception $e) {

            }
        }

        if ($failCount === 0 && $successCount > 0) {
            $message = 'Grades saved successfully.';
            $messageType = 'success';
        } elseif ($successCount > 0 && $failCount > 0) {
            $message = 'Some grades saved successfully, but ' . $failCount . ' failed (possibly due to invalid input or permissions).';
            $messageType = 'warning';
        } elseif ($successCount === 0 && $failCount > 0) {
            $message = 'Failed to save grades. Please check input or permissions.';
            $messageType = 'error';
        } else {
            $message = 'No grades were submitted or changed.';
            $messageType = 'info';
        }
    }
} else if (isset($_POST['delete_grade_item'])) $itemIdToDelete = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

// Get teacher's classes (class-subject combinations)
$classes = getTeacherClasses($teacherId);

// Selected class-subject ID
$selectedClassSubjectId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : ($classes[0]['class_subject_id'] ?? 0);

// Verify teacher has access to the selected class-subject
if ($selectedClassSubjectId > 0 && !teacherHasAccessToClassSubject($selectedClassSubjectId, $teacherId)) {
    // If access denied, reset selection and show error
    $selectedClassSubjectId = 0;
    $message = 'You do not have access to the selected class.';
    $messageType = 'error';
}

$gradeItems = $selectedClassSubjectId ? getGradeItemsFunction($selectedClassSubjectId) : [];
$selectedItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Get students and grades if a class-subject is selected
$students = [];
$grades = [];
if ($selectedClassSubjectId > 0) {
    // Assuming getClassStudents needs class_id, not class_subject_id. Need to fetch class_id first.
    // This requires adjusting getTeacherClasses or adding a function to get class_id from class_subject_id.
    // For now, let's assume getClassStudents is adapted or we fetch class_id elsewhere.
    // Find the class_id associated with the selectedClassSubjectId from the $classes array
    $currentClassId = null;
    foreach ($classes as $classInfo) if ($classInfo['class_subject_id'] == $selectedClassSubjectId) {
        $currentClassId = $classInfo['class_id'];
        break;
    }
    if ($currentClassId) $students = getClassStudents($currentClassId); else {
        // Handle case where class_id couldn't be found for the selected class_subject_id
        $message = 'Error retrieving student list for the selected class-subject.';
        $messageType = 'error';
    }

    $gradesData = getClassGradesTeacher($selectedClassSubjectId); // Needs class_subject_id
    $grades = $gradesData['grades'] ?? [];
}


// Generate CSRF token
try {
    $csrfToken = generateCSRFToken();
} catch (RandomException $e) {
    error_log("CSRF Token Generation Failed: " . $e->getMessage());
    die('Error generating security token. Please try refreshing the page.');
}

?>

<!-- Main card with page title and description -->
<div class="card shadow mb-lg mt-lg">
    <div class="d-flex justify-between items-center">
        <div>
            <h2 class="mt-0 mb-xs">Gradebook</h2>
            <p class="text-secondary mt-0 mb-0">Manage grades for your classes</p>
        </div>
        <div class="role-badge role-teacher">Teacher</div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert status-<?= htmlspecialchars($messageType) ?> mb-lg">
        <div class="alert-icon">
            <?php if ($messageType === 'success'): ?>✓
            <?php elseif ($messageType === 'warning'): ?>⚠
            <?php else: ?>✕
            <?php endif; ?>
        </div>
        <div class="alert-content">
            <?= htmlspecialchars($message) ?>
        </div>
    </div>
<?php endif; ?>

<!-- Class selection form with dropdown and select button -->
<div class="card shadow mb-lg">
    <div class="card__content">
        <form method="GET" action="/uwuweb/teacher/gradebook.php" class="d-flex items-center gap-md flex-wrap">
            <div class="form-group mb-0" style="flex: 1;">
                <label for="class_id" class="form-label">Select Class & Subject:</label>
                <select id="class_id" name="class_id" class="form-input form-select">
                    <option value="">-- Select a class --</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= $class['class_subject_id'] ?>" <?= $selectedClassSubjectId == $class['class_subject_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['class_code']) ?>
                            - <?= htmlspecialchars($class['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Select Class</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedClassSubjectId): ?>
    <!-- Tab navigation with three tabs -->
    <div class="card shadow mb-lg">
        <div class="d-flex mb-lg p-sm" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <button class="btn tab-btn active" data-tab="grade-items">Grade Items</button>
            <button class="btn tab-btn" data-tab="enter-grades">Enter Grades</button>
            <button class="btn tab-btn" data-tab="grade-overview">Grade Overview</button>
        </div>

        <!-- Grade Items tab content -->
        <div class="tab-content active" id="grade-items">
            <div class="d-flex justify-between items-center mb-md p-md">
                <h3 class="mt-0 mb-0">Assessment Items</h3>
                <button class="btn btn-primary btn-sm" id="addGradeItemBtn">
                    <span class="btn-icon">+</span> Add Grade Item
                </button>
            </div>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Max Points</th>
                        <th>Weight</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($gradeItems)): ?>
                        <tr>
                            <td colspan="4" class="text-center p-lg">
                                <div class="alert status-info mb-0">
                                    No grade items exist for this class-subject combination. Click "Add Grade Item" to
                                    create one.
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gradeItems as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= htmlspecialchars((string)$item['max_points']) ?></td>
                                <td><?= htmlspecialchars((string)$item['weight']) ?>%</td>
                                <td>
                                    <div class="d-flex gap-xs">
                                        <a href="/uwuweb/teacher/gradebook.php?class_id=<?= $selectedClassSubjectId ?>&item_id=<?= $item['item_id'] ?>"
                                           class="btn btn-secondary btn-sm">Enter Grades</a>
                                        <button class="btn btn-sm edit-item-btn"
                                                data-id="<?= $item['item_id'] ?>"
                                                data-name="<?= htmlspecialchars($item['name']) ?>"
                                                data-max-points="<?= htmlspecialchars((string)$item['max_points']) ?>"
                                                data-weight="<?= htmlspecialchars((string)$item['weight']) ?>">Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm delete-item-btn"
                                                data-id="<?= $item['item_id'] ?>"
                                                data-name="<?= htmlspecialchars($item['name']) ?>">Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enter Grades tab content -->
        <div class="tab-content" id="enter-grades">
            <div class="card__content">
                <?php if (empty($gradeItems)): ?>
                    <div class="alert status-warning">
                        No grade items exist for this class. Please create a grade item first in the 'Grade Items' tab.
                    </div>
                <?php else: ?>
                    <div class="form-group mb-lg">
                        <label class="form-label" for="grade_item_select">Select Grade Item:</label>
                        <select id="grade_item_select" class="form-input form-select">
                            <option value="">-- Select an item --</option>
                            <?php foreach ($gradeItems as $item): ?>
                                <option value="<?= $item['item_id'] ?>" <?= $selectedItemId == $item['item_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($item['name']) ?>
                                    (Max: <?= htmlspecialchars((string)$item['max_points']) ?> pts,
                                    Weight: <?= htmlspecialchars((string)$item['weight']) ?>%)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (!$selectedItemId): ?>
                        <div class="alert status-info">
                            Please select a grade item from the dropdown above to enter grades.
                        </div>
                    <?php elseif (empty($students)): ?>
                        <div class="alert status-info">
                            No students are enrolled in this class, or student data could not be retrieved.
                        </div>
                    <?php else: ?>
                        <?php
                        // Find the details of the selected grade item
                        $currentItem = null;
                        foreach ($gradeItems as $item) if ($item['id'] == $selectedItemId) {
                            $currentItem = $item;
                            break;
                        }
                        ?>
                        <form method="POST"
                              action="/uwuweb/teacher/gradebook.php?class_id=<?= $selectedClassSubjectId ?>&item_id=<?= $selectedItemId ?>"
                              id="gradeForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="class_id" value="<?= $selectedClassSubjectId ?>">
                            <input type="hidden" name="item_id" id="form_item_id" value="<?= $selectedItemId ?>">
                            <input type="hidden" name="save_grades" value="1">

                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Points
                                            (Max: <?= $currentItem ? htmlspecialchars((string)$currentItem['max_points']) : 'N/A' ?>
                                            )
                                        </th>
                                        <th>Grade (%)</th>
                                        <th>Comment</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <?php
                                        $enrollId = $student['enroll_id'];
                                        $existingGrade = isset($grades[$enrollId][$selectedItemId]) ? $grades[$enrollId][$selectedItemId] : null;

                                        $points = $existingGrade ? $existingGrade['points'] : '';
                                        $comment = $existingGrade ? $existingGrade['comment'] : '';
                                        $maxPointsForItem = $currentItem ? (float)$currentItem['max_points'] : 0;
                                        $percentage = ($points !== '' && $maxPointsForItem > 0) ?
                                            (((float)$points / $maxPointsForItem) * 100) : null;

                                        $gradeClass = '';
                                        if ($percentage !== null) if ($percentage >= 80) $gradeClass = 'grade-high';
                                        elseif ($percentage >= 50) $gradeClass = 'grade-medium';
                                        else $gradeClass = 'grade-low';
                                        ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                            </td>
                                            <td style="width: 150px">
                                                <label>
                                                    <input type="number" class="form-input point-input"
                                                           name="grade[<?= $enrollId ?>]"
                                                           value="<?= htmlspecialchars((string)$points) ?>"
                                                           step="0.01" min="0"
                                                           max="<?= $maxPointsForItem ?>"
                                                           data-max-points="<?= $maxPointsForItem ?>"
                                                           data-enroll-id="<?= $enrollId ?>">
                                                </label>
                                            </td>
                                            <td style="width: 80px">
                                                    <span class="grade <?= $gradeClass ?>"
                                                          id="grade-display-<?= $enrollId ?>">
                                                        <?= $percentage !== null ? number_format($percentage) . '%' : '—' ?>
                                                    </span>
                                            </td>
                                            <td>
                                                <label>
                                                    <input type="text" class="form-input"
                                                           name="feedback[<?= $enrollId ?>]"
                                                           value="<?= htmlspecialchars($comment) ?>"
                                                           placeholder="Optional comment">
                                                </label>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-end mt-lg">
                                <button type="submit" class="btn btn-primary">Save Grades</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grade Overview tab -->
        <div class="tab-content" id="grade-overview">
            <div class="card__content">
                <?php if (empty($gradeItems) || empty($students)): ?>
                    <div class="alert status-info">
                        <?= empty($students) ? 'No students are enrolled in this class.' : 'No grade items exist to calculate an overview.' ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>Student</th>
                                <?php foreach ($gradeItems as $item): ?>
                                    <th title="<?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars((string)$item['weight']) ?>%)">
                                        <?= htmlspecialchars(substr($item['name'], 0, 15)) . (strlen($item['name']) > 15 ? '...' : '') ?>
                                        <br><small>(<?= htmlspecialchars((string)$item['weight']) ?>%)</small>
                                    </th>
                                <?php endforeach; ?>
                                <th>Weighted Avg (%)</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>

                                    <?php
                                    $weightedSum = 0;
                                    $totalWeight = 0;

                                    foreach ($gradeItems as $item):
                                        $itemId = $item['item_id'];
                                        $enrollId = $student['enroll_id'];
                                        $studentGrade = isset($grades[$enrollId][$itemId]) ? $grades[$enrollId][$itemId] : null;

                                        $points = ($studentGrade && isset($studentGrade['points'])) ? (float)$studentGrade['points'] : null;
                                        $maxPointsForItem = (float)$item['max_points'];
                                        $weightForItem = (float)$item['weight'];
                                        $percentage = ($points !== null && $maxPointsForItem > 0) ?
                                            (($points / $maxPointsForItem) * 100) : null;

                                        if ($percentage !== null && $weightForItem > 0) {
                                            $weightedSum += $percentage * ($weightForItem / 100);
                                            $totalWeight += $weightForItem / 100;
                                        }

                                        $gradeClass = '';
                                        if ($percentage !== null) if ($percentage >= 80) $gradeClass = 'grade-high';
                                        elseif ($percentage >= 50) $gradeClass = 'grade-medium';
                                        else $gradeClass = 'grade-low';
                                        ?>
                                        <td class="text-center">
                                            <?php if ($percentage !== null): ?>
                                                <span class="grade <?= $gradeClass ?>">
                                                        <?= number_format($percentage) ?>%
                                                    </span>
                                                <br><small>(<?= htmlspecialchars((string)$points) ?>
                                                    /<?= htmlspecialchars((string)$maxPointsForItem) ?>)</small>
                                            <?php else: ?>
                                                <span class="text-disabled">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>

                                    <?php
                                    $finalAverage = ($totalWeight > 0) ? ($weightedSum / $totalWeight) : null;
                                    $finalGradeClass = '';

                                    if ($finalAverage !== null) if ($finalAverage >= 80) $finalGradeClass = 'grade-high';
                                    elseif ($finalAverage >= 50) $finalGradeClass = 'grade-medium';
                                    else $finalGradeClass = 'grade-low';
                                    ?>

                                    <td class="text-center">
                                        <?php if ($finalAverage !== null): ?>
                                            <span class="grade <?= $finalGradeClass ?> font-bold">
                                                    <?= number_format($finalAverage, 1) ?>%
                                                </span>
                                        <?php else: ?>
                                            <span class="text-disabled">—</span>
                                        <?php endif; ?>
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

    <!-- Modal for adding/editing grade items -->
    <div class="modal" id="gradeItemModal">
        <div class="modal-overlay"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Grade Item</h3>
                <button class="btn-close" id="closeModal">×</button>
            </div>
            <div class="modal-body">
                <!-- Form action points to the API endpoint -->
                <form id="gradeItemForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="class_subject_id" value="<?= $selectedClassSubjectId ?>">
                    <input type="hidden" name="item_id" id="edit_item_id" value="">
                    <input type="hidden" name="action" id="form_action" value="add"> <!-- 'add' or 'update' -->

                    <div class="form-group">
                        <label class="form-label" for="item_name">Name:</label>
                        <input type="text" id="item_name" name="name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="max_points">Maximum Points:</label>
                        <input type="number" id="max_points" name="max_points" class="form-input" min="0" step="0.01"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="weight">Weight (%):</label>
                        <input type="number" id="weight" name="weight" class="form-input" min="0" max="100" step="0.1"
                               value="1.0" required>
                    </div>
                    <div id="modal-error" class="alert status-error" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button class="btn btn-primary" id="saveItemBtn">Save</button>
            </div>
        </div>
    </div>

    <!-- Modal for confirming deletion -->
    <div class="modal" id="deleteModal">
        <div class="modal-overlay"></div>
        <div class="modal-container">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="btn-close" id="closeDeleteModal">×</button>
            </div>
            <div class="modal-body">
                <div class="alert status-warning">
                    <p>Are you sure you want to delete the grade item "<strong id="deleteItemName"></strong>"?</p>
                    <p>This will permanently delete all grades associated with this item and cannot be undone.</p>
                </div>
                <div id="delete-modal-error" class="alert status-error" style="display: none;"></div>
                <form id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="item_id" id="delete_item_id" value="">
                    <input type="hidden" name="action" value="delete">
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDeleteBtn">Cancel</button>
                <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- JavaScript for tab switching, modal handling, and AJAX form submissions -->
<style>
    .tab-btn {
        background: none;
        border: none;
        padding: var(--space-sm) var(--space-md);
        margin-right: var(--space-sm);
        border-bottom: 2px solid transparent;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all var(--transition-normal);
    }

    .tab-btn:hover {
        color: var(--text-primary);
        background-color: rgba(255, 255, 255, 0.05);
    }

    .tab-btn.active {
        color: var(--accent-primary);
        border-bottom-color: var(--accent-primary);
    }

    .tab-content {
        display: none;
        padding: var(--space-md);
    }

    .tab-content.active {
        display: block;
    }

    .modal { /* Basic modal styles assumed from css */
    }

    .modal.open {
        display: flex;
    }

    .font-bold {
        font-weight: bold;
    }

    .text-center {
        text-align: center;
    }

    .text-disabled {
        color: var(--text-secondary);
        font-style: italic;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Tab Navigation
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        const currentUrl = new URL(window.location.href);
        const currentItemId = currentUrl.searchParams.get('item_id');

        tabButtons.forEach(button => {
            button.addEventListener('click', function () {
                const tabId = this.getAttribute('data-tab');
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Activate "Enter Grades" tab if item_id is in URL
        if (currentItemId) {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            const enterGradesBtn = document.querySelector('.tab-btn[data-tab="enter-grades"]');
            const enterGradesContent = document.getElementById('enter-grades');
            if (enterGradesBtn && enterGradesContent) {
                enterGradesBtn.classList.add('active');
                enterGradesContent.classList.add('active');
            }
        }


        // Modal Handling
        const gradeItemModal = document.getElementById('gradeItemModal');
        const deleteModal = document.getElementById('deleteModal');
        const addGradeItemBtn = document.getElementById('addGradeItemBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const saveItemBtn = document.getElementById('saveItemBtn');
        const editItemBtns = document.querySelectorAll('.edit-item-btn');
        const deleteItemBtns = document.querySelectorAll('.delete-item-btn');
        const closeDeleteModal = document.getElementById('closeDeleteModal');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        const gradeItemForm = document.getElementById('gradeItemForm');
        const deleteForm = document.getElementById('deleteForm');
        const modalErrorDiv = document.getElementById('modal-error');
        const deleteModalErrorDiv = document.getElementById('delete-modal-error');

        function openModal(modal) {
            if (modal) modal.classList.add('open');
        }

        function closeModalFunc(modal) {
            if (modal) {
                modal.classList.remove('open');
                // Reset errors
                if (modalErrorDiv) modalErrorDiv.display = 'none';
                if (deleteModalErrorDiv) deleteModalErrorDiv.display = 'none';
            }
        }

        if (addGradeItemBtn) {
            addGradeItemBtn.addEventListener('click', function () {
                document.getElementById('modalTitle').textContent = 'Add Grade Item';
                gradeItemForm.reset(); // Reset form fields
                document.getElementById('edit_item_id').value = '';
                document.getElementById('form_action').value = 'add';
                document.getElementById('weight').value = '1.0'; // Set default weight
                openModal(gradeItemModal);
            });
        }

        editItemBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('modalTitle').textContent = 'Edit Grade Item';
                gradeItemForm.reset();
                document.getElementById('edit_item_id').value = this.dataset.id;
                document.getElementById('item_name').value = this.dataset.name;
                document.getElementById('max_points').value = this.dataset.maxPoints;
                document.getElementById('weight').value = this.dataset.weight;
                document.getElementById('form_action').value = 'update';
                openModal(gradeItemModal);
            });
        });

        deleteItemBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('delete_item_id').value = this.dataset.id;
                document.getElementById('deleteItemName').textContent = this.dataset.name;
                openModal(deleteModal);
            });
        });

        if (closeModal) closeModal.addEventListener('click', () => closeModalFunc(gradeItemModal));
        if (cancelBtn) cancelBtn.addEventListener('click', () => closeModalFunc(gradeItemModal));
        if (closeDeleteModal) closeDeleteModal.addEventListener('click', () => closeModalFunc(deleteModal));
        if (cancelDeleteBtn) cancelDeleteBtn.addEventListener('click', () => closeModalFunc(deleteModal));

        // AJAX for Save Grade Item
        if (saveItemBtn) {
            saveItemBtn.addEventListener('click', async function () {
                modalErrorDiv.display = 'none'; // Hide previous errors
                const formData = new FormData(gradeItemForm);
                const action = formData.get('action') === 'update' ? 'updateGradeItem' : 'addGradeItem';
                formData.append('action', action); // Ensure correct action for API

                try {
                    const response = await fetch('/uwuweb/api/grades.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        closeModalFunc(gradeItemModal);
                        window.location.reload(); // Reload to see changes
                    } else {
                        modalErrorDiv.textContent = result.message || 'An error occurred.';
                        modalErrorDiv.display = 'block';
                    }
                } catch (error) {
                    console.error('Error saving grade item:', error);
                    modalErrorDiv.textContent = 'A network error occurred. Please try again.';
                    modalErrorDiv.display = 'block';
                }
            });
        }

        // AJAX for Delete Grade Item
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', async function () {
                deleteModalErrorDiv.display = 'none';
                const formData = new FormData(deleteForm);
                formData.append('action', 'deleteGradeItem'); // Explicitly set action

                try {
                    const response = await fetch('/uwuweb/api/grades.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        closeModalFunc(deleteModal);
                        window.location.reload(); // Reload to reflect deletion
                    } else {
                        deleteModalErrorDiv.textContent = result.message || 'An error occurred during deletion.';
                        deleteModalErrorDiv.display = 'block';
                    }
                } catch (error) {
                    console.error('Error deleting grade item:', error);
                    deleteModalErrorDiv.textContent = 'A network error occurred. Please try again.';
                    deleteModalErrorDiv.display = 'block';
                }
            });
        }


        // Grade item selection in "Enter Grades" tab
        const gradeItemSelect = document.getElementById('grade_item_select');
        if (gradeItemSelect) {
            gradeItemSelect.addEventListener('change', function () {
                const itemId = this.value;
                const classSubjectId = <?= $selectedClassSubjectId ?>;
                if (itemId) {
                    window.location.href = `/uwuweb/teacher/gradebook.php?class_id=${classSubjectId}&item_id=${itemId}`;
                } else {
                    // Optionally handle the case where "-- Select an item --" is chosen
                    // Maybe clear the table or show a message
                    window.location.href = `/uwuweb/teacher/gradebook.php?class_id=${classSubjectId}`;
                }
            });
        }

        // Click modal overlay to close
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function () {
                closeModalFunc(gradeItemModal);
                closeModalFunc(deleteModal);
            });
        });

        // Calculate grades percentages on input
        document.querySelectorAll('.point-input').forEach(input => {
            input.addEventListener('input', function () {
                const enrollId = this.dataset.enrollId;
                const maxPoints = parseFloat(this.dataset.maxPoints);
                const points = parseFloat(this.value);
                const display = document.getElementById(`grade-display-${enrollId}`);

                if (display && !isNaN(points) && !isNaN(maxPoints) && maxPoints > 0) {
                    const percentage = Math.min(100, Math.max(0, (points / maxPoints) * 100)); // Clamp between 0 and 100
                    display.textContent = `${percentage.toFixed(0)}%`;

                    display.className = 'grade'; // Reset classes
                    if (percentage >= 80) display.classList.add('grade-high');
                    else if (percentage >= 50) display.classList.add('grade-medium');
                    else display.classList.add('grade-low');

                } else if (display) {
                    display.textContent = '—';
                    display.className = 'grade'; // Reset classes
                }

                // Optional: Add validation styling for points > maxPoints
                if (!isNaN(points) && !isNaN(maxPoints) && points > maxPoints) {
                    this.borderColor = 'var(--accent-danger)';
                } else {
                    this.borderColor = ''; // Reset border color
                }
            });
            // Trigger calculation on page load for existing values
            input.dispatchEvent(new Event('input'));
        });
    });
</script>

<?php
// Include page footer
include '../includes/footer.php';
?>
