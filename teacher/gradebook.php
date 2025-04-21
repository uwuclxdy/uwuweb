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

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// CSS styles are included in header.php

// Ensure only teachers can access this page
requireRole(ROLE_TEACHER);

// Get the teacher ID of the logged-in user
$teacherId = getTeacherId();
if (!$teacherId) {
    die('Error: Teacher account not found.');
}

// Database connection - using safe connection to prevent null pointer exceptions
$pdo = safeGetDBConnection('teacher/gradebook.php');

// Get classes taught by teacher
function getTeacherClasses($teacherId) {
    $pdo = safeGetDBConnection('getTeacherClasses()', false);
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT c.class_id, c.title, s.name AS subject_name, t.name AS term_name
         FROM classes c
         JOIN subjects s ON c.subject_id = s.subject_id
         JOIN terms t ON c.term_id = t.term_id
         WHERE c.teacher_id = :teacher_id
         ORDER BY t.start_date DESC, s.name"
    );
    $stmt->execute(['teacher_id' => $teacherId]);
    return $stmt->fetchAll();
}

// Get students enrolled in a class
function getClassStudents($classId) {
    $pdo = safeGetDBConnection('getClassStudents()', false);
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT e.enroll_id, s.student_id, s.first_name, s.last_name, s.class_code
         FROM enrollments e
         JOIN students s ON e.student_id = s.student_id
         WHERE e.class_id = :class_id
         ORDER BY s.last_name, s.first_name"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get grade items for a class
function getGradeItems($classId) {
    $pdo = safeGetDBConnection('getGradeItems()', false);
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT item_id, name, max_points, weight
         FROM grade_items
         WHERE class_id = :class_id
         ORDER BY name"
    );
    $stmt->execute(['class_id' => $classId]);
    return $stmt->fetchAll();
}

// Get grades for a class
function getClassGrades($classId) {
    $pdo = safeGetDBConnection('getClassGrades()', false);
    if (!$pdo) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT g.grade_id, g.enroll_id, g.item_id, g.points
         FROM grades g
         JOIN enrollments e ON g.enroll_id = e.enroll_id
         JOIN grade_items gi ON g.item_id = gi.item_id
         WHERE e.class_id = :class_id"
    );
    $stmt->execute(['class_id' => $classId]);

    // Index by enrollment_id and item_id for easier lookup
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[$row['enroll_id']][$row['item_id']] = $row;
    }
    return $result;
}

// Add a new grade item
function addGradeItem($classId, $name, $description, $maxPoints, $weight, $date) {
    $pdo = safeGetDBConnection('addGradeItem()', false);
    if (!$pdo) {
        return false;
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO grade_items (class_id, name, max_points, weight)
            VALUES (:class_id, :name, :max_points, :weight)"
            );
            return $stmt->execute([
                'class_id' => $classId,
                'name' => $name,
                'max_points' => $maxPoints,
                'weight' => $weight
            ]);
    } catch (PDOException $e) {
        error_log("Error adding grade item: " . $e->getMessage());
        return false;
    }
}

// Save a grade
function saveGrade($enrollId, $itemId, $points, $feedback) {
    $pdo = safeGetDBConnection('saveGrade()', false);
    if (!$pdo) {
        return false;
    }

    try {
        // Check if grade already exists
        $stmt = $pdo->prepare(
            "SELECT grade_id FROM grades
             WHERE enroll_id = :enroll_id AND item_id = :item_id"
        );
        $stmt->execute([
            'enroll_id' => $enrollId,
            'item_id' => $itemId
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing grade
            $stmt = $pdo->prepare(
                "UPDATE grades
                SET points = :points
                WHERE grade_id = :grade_id"
            );
            return $stmt->execute([
                'grade_id' => $existing['grade_id'],
                'points' => $points
            ]);
        }

        // Insert new grade
        $stmt = $pdo->prepare(
            "INSERT INTO grades (enroll_id, item_id, points)
            VALUES (:enroll_id, :item_id, :points)"
        );
        return $stmt->execute([
            'enroll_id' => $enrollId,
            'item_id' => $itemId,
            'points' => $points,
            'feedback' => $feedback
        ]);
    } catch (PDOException $e) {
        error_log("Error saving grade: " . $e->getMessage());
        return false;
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
        if (isset($_POST['add_grade_item'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
            $name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
            $description = isset($_POST['item_description']) ? trim($_POST['item_description']) : '';
            $maxPoints = isset($_POST['max_points']) ? (float)$_POST['max_points'] : 0;
            $weight = isset($_POST['weight']) ? (float)$_POST['weight'] : 0;
            $date = isset($_POST['item_date']) ? $_POST['item_date'] : date('Y-m-d');

            if ($classId <= 0 || empty($name) || $maxPoints <= 0) {
                $message = 'Please fill out all required fields for the grade item.';
                $messageType = 'error';
            } else {
                if (addGradeItem($classId, $name, $description, $maxPoints, $weight, $date)) {
                    $message = 'New grade item added successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error adding grade item. Please try again.';
                    $messageType = 'error';
                }
            }
        } else if (isset($_POST['save_grades'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
            $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            $grades = isset($_POST['grade']) ? $_POST['grade'] : [];
            $feedback = isset($_POST['feedback']) ? $_POST['feedback'] : [];

            if ($classId <= 0 || $itemId <= 0) {
                $message = 'Invalid grade data.';
                $messageType = 'error';
            } else {
                $success = true;
                foreach ($grades as $enrollId => $points) {
                    $studentFeedback = isset($feedback[$enrollId]) ? $feedback[$enrollId] : '';
                    if (!saveGrade($enrollId, $itemId, $points, $studentFeedback)) {
                        $success = false;
                    }
                }

                if ($success) {
                    $message = 'Grades saved successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Some grades failed to save. Please try again.';
                    $messageType = 'warning';
                }
            }
        }
    }
}

// Get teacher's classes
$classes = getTeacherClasses($teacherId);

// Selected class and grade item
$selectedClassId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : (isset($classes[0]['class_id']) ? $classes[0]['class_id'] : 0);
$gradeItems = $selectedClassId ? getGradeItems($selectedClassId) : [];
$selectedItemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

// Get students and grades if a class is selected
$students = $selectedClassId ? getClassStudents($selectedClassId) : [];
$grades = $selectedClassId ? getClassGrades($selectedClassId) : [];

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>

    <div class="card card-entrance mt-xl">
        <h1 class="mt-0 mb-md">Grade Management</h1>
        <p class="text-secondary mt-0 mb-lg">Manage student grades and assessment items for your classes</p>

        <?php if (!empty($message)): ?>
            <div class="alert <?= strpos($message, 'successfully') !== false ? 'status-success' : 'status-error' ?> mb-lg">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Class Selection Form -->
        <form method="GET" action="gradebook.php" class="mb-lg">
            <div class="form-group mb-0">
                <label for="class_id" class="form-label">Select Class</label>
                <div class="d-flex gap-md">
                    <select id="class_id" name="class_id" class="form-input" style="flex: 1;">
                        <option value="">-- Select a class --</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?= $class['class_id'] ?>" <?= $selectedClassId == $class['class_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['subject_name'] . ' - ' . $class['title']) ?> (<?= htmlspecialchars($class['term_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Select</button>
                </div>
            </div>
        </form>
    </div>

<?php if ($selectedClassId): ?>
    <!-- Class selected, show tab navigation -->
    <div class="card">
        <!-- Tab Navigation -->
        <div class="d-flex gap-md mb-lg pb-md" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <a href="#gradeItems" class="tab-link active" data-tab="gradeItems">
                Grade Items
            </a>
            <a href="#enterGrades" class="tab-link" data-tab="enterGrades">
                Enter Grades
            </a>
            <a href="#gradeOverview" class="tab-link" data-tab="gradeOverview">
                Grade Overview
            </a>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="gradeItems">
            <div class="d-flex justify-between items-center mb-md">
                <h2 class="mt-0 mb-0">Assessment Items</h2>
                <button class="btn btn-primary" id="addGradeItemBtn">Add New Item</button>
            </div>

            <?php if (empty($gradeItems)): ?>
                <div class="bg-tertiary p-lg text-center rounded mb-lg">
                    <p class="mb-sm">No grade items found for this class.</p>
                    <p class="text-secondary mt-0">Click "Add New Item" to create the first assessment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive mb-lg">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Max Points</th>
                            <th>Weight</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($gradeItems as $item): ?>
                            <tr>
                                <td><?= $item['item_id'] ?></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td>
                                    <?php
                                    $description = $item['description'] ?? '';
                                    echo htmlspecialchars(strlen($description) > 50 ? substr($description, 0, 47) . '...' : $description);
                                    ?>
                                </td>
                                <td><?= number_format($item['max_points'], 2) ?></td>
                                <td><?= $item['weight'] ? number_format($item['weight'], 2) : 'N/A' ?></td>
                                <td><?= date('d.m.Y', strtotime($item['item_date'])) ?></td>
                                <td>
                                    <div class="d-flex gap-sm">
                                        <button class="btn btn-secondary btn-sm edit-item-btn"
                                                data-id="<?= $item['item_id'] ?>"
                                                data-name="<?= htmlspecialchars($item['name']) ?>"
                                                data-description="<?= htmlspecialchars($item['description'] ?? '') ?>"
                                                data-max-points="<?= $item['max_points'] ?>"
                                                data-weight="<?= $item['weight'] ?>"
                                                data-date="<?= $item['item_date'] ?>">
                                            Edit
                                        </button>
                                        <button class="btn btn-secondary btn-sm delete-item-btn"
                                                data-id="<?= $item['item_id'] ?>"
                                                data-name="<?= htmlspecialchars($item['name']) ?>">
                                            Delete
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

        <div class="tab-content" id="enterGrades" style="display: none;">
            <div class="form-group mb-lg">
                <label for="item_id" class="form-label">Select Assessment Item</label>
                <select id="item_id" name="item_id" class="form-input">
                    <option value="">-- Select an item --</option>
                    <?php foreach ($gradeItems as $item): ?>
                        <option value="<?= $item['item_id'] ?>" <?= $selectedItemId == $item['item_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($item['name']) ?> (<?= number_format($item['max_points'], 2) ?> points)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="gradesForm" style="display: <?= $selectedItemId ? 'block' : 'none' ?>;">
                <?php if (empty($students)): ?>
                    <div class="bg-tertiary p-lg text-center rounded mb-lg">
                        <p class="text-secondary">No students enrolled in this class.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="gradebook.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                        <input type="hidden" name="item_id" value="<?= $selectedItemId ?>">

                        <div class="table-responsive mb-lg">
                            <table class="data-table">
                                <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Points</th>
                                    <th>Feedback</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                    $gradeData = $grades[$student['enroll_id']][$selectedItemId] ?? null;
                                    $points = $gradeData ? $gradeData['points'] : '';
                                    $feedback = $gradeData ? $gradeData['feedback'] : '';
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?></td>
                                        <td><?= htmlspecialchars($student['class_code']) ?></td>
                                        <td>
                                            <input type="number" step="0.01" min="0"
                                                   name="grade[<?= $student['enroll_id'] ?>]"
                                                   class="form-input"
                                                   value="<?= $points ?>"
                                                   style="width: 80px;">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   name="feedback[<?= $student['enroll_id'] ?>]"
                                                   class="form-input"
                                                   value="<?= htmlspecialchars($feedback) ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-end">
                            <button type="submit" name="save_grades" class="btn btn-primary">Save Grades</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content" id="gradeOverview" style="display: none;">
            <h2 class="mt-0 mb-lg">Grade Overview</h2>

            <?php if (empty($gradeItems) || empty($students)): ?>
                <div class="bg-tertiary p-lg text-center rounded mb-lg">
                    <p class="text-secondary">No grade data available. Please add grade items and students to the class.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive mb-lg">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Student</th>
                            <?php foreach ($gradeItems as $item): ?>
                                <th title="<?= htmlspecialchars($item['description'] ?? '') ?>">
                                    <?= htmlspecialchars($item['name']) ?>
                                    <div class="text-secondary" style="font-size: var(--font-size-xs);">
                                        <?= number_format($item['max_points'], 2) ?> pts
                                        <?= $item['weight'] ? '(' . number_format($item['weight'], 2) . 'x)' : '' ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                            <th>Average</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                    <div class="text-secondary" style="font-size: var(--font-size-xs);">
                                        <?= htmlspecialchars($student['class_code']) ?>
                                    </div>
                                </td>

                                <?php
                                $totalPoints = 0;
                                $totalMaxPoints = 0;
                                $weightedGrade = 0;
                                $totalWeight = 0;

                                foreach ($gradeItems as $item):
                                    $gradeData = $grades[$student['enroll_id']][$item['item_id']] ?? null;
                                    $points = $gradeData ? $gradeData['points'] : null;

                                    if ($points !== null) {
                                        $totalPoints += $points;
                                        $totalMaxPoints += $item['max_points'];

                                        if ($item['weight'] > 0) {
                                            $percentage = $item['max_points'] > 0 ? ($points / $item['max_points']) : 0;
                                            $weightedGrade += $percentage * $item['weight'];
                                            $totalWeight += $item['weight'];
                                        }
                                    }

                                    $percentage = $points !== null && $item['max_points'] > 0 ?
                                        ($points / $item['max_points']) * 100 : null;

                                    $gradeClass = '';
                                    if ($percentage !== null) {
                                        if ($percentage >= 90) $gradeClass = 'grade-high';
                                        else if ($percentage >= 70) $gradeClass = 'grade-medium';
                                        else $gradeClass = 'grade-low';
                                    }
                                    ?>
                                    <td>
                                        <?php if ($points !== null): ?>
                                            <div class="grade <?= $gradeClass ?>">
                                                <?= number_format($points, 2) ?>/<?= number_format($item['max_points'], 2) ?>
                                                <div style="font-size: var(--font-size-xs);">
                                                    <?= number_format($percentage, 1) ?>%
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-disabled">Not graded</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>

                                <?php
                                // Calculate overall grade
                                $finalPercentage = null;

                                if ($totalWeight > 0) {
                                    $finalPercentage = ($weightedGrade / $totalWeight) * 100;
                                } else if ($totalMaxPoints > 0) {
                                    $finalPercentage = ($totalPoints / $totalMaxPoints) * 100;
                                }

                                $finalGradeClass = '';
                                if ($finalPercentage !== null) {
                                    if ($finalPercentage >= 90) $finalGradeClass = 'grade-high';
                                    else if ($finalPercentage >= 70) $finalGradeClass = 'grade-medium';
                                    else $finalGradeClass = 'grade-low';
                                }
                                ?>

                                <td>
                                    <?php if ($finalPercentage !== null): ?>
                                        <div class="grade <?= $finalGradeClass ?>" style="font-weight: var(--font-weight-bold);">
                                            <?= number_format($finalPercentage, 1) ?>%
                                            <div style="font-size: var(--font-size-xs);">
                                                <?php
                                                if ($finalPercentage >= 90) echo 'A';
                                                else if ($finalPercentage >= 80) echo 'B';
                                                else if ($finalPercentage >= 70) echo 'C';
                                                else if ($finalPercentage >= 60) echo 'D';
                                                else echo 'F';
                                                ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-disabled">No data</span>
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

    <!-- Grade Item Modal -->
    <div class="modal" id="gradeItemModal" style="display: none;">
        <div class="modal-overlay" id="gradeItemModalOverlay"></div>
        <div class="modal-container">
            <div class="card">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0" id="gradeItemModalTitle">Add New Grade Item</h3>
                    <button type="button" class="btn-close" id="closeGradeItemModal">&times;</button>
                </div>

                <form method="POST" action="gradebook.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                    <input type="hidden" name="item_id" id="editItemId" value="">

                    <div class="form-group">
                        <label for="item_name" class="form-label">Name</label>
                        <input type="text" id="item_name" name="item_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label for="item_description" class="form-label">Description</label>
                        <textarea id="item_description" name="item_description" class="form-input" rows="3"></textarea>
                    </div>

                    <div class="d-flex gap-md">
                        <div class="form-group" style="flex: 1;">
                            <label for="max_points" class="form-label">Maximum Points</label>
                            <input type="number" step="0.01" min="0" id="max_points" name="max_points" class="form-input" required>
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label for="weight" class="form-label">Weight (Optional)</label>
                            <input type="number" step="0.01" min="0" id="weight" name="weight" class="form-input">
                            <div class="feedback-text">Leave empty for equal weight</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="item_date" class="form-label">Date</label>
                        <input type="date" id="item_date" name="item_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="d-flex gap-md justify-end mt-lg">
                        <button type="button" class="btn" id="cancelGradeItemBtn">Cancel</button>
                        <button type="submit" name="add_grade_item" class="btn btn-primary" id="gradeItemSubmitBtn">
                            Add Grade Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Grade Item Modal -->
    <div class="modal" id="deleteGradeItemModal" style="display: none;">
        <div class="modal-overlay" id="deleteGradeItemModalOverlay"></div>
        <div class="modal-container">
            <div class="card">
                <div class="d-flex justify-between items-center mb-lg">
                    <h3 class="mt-0 mb-0">Confirm Deletion</h3>
                    <button type="button" class="btn-close" id="closeDeleteGradeItemModal">&times;</button>
                </div>

                <div class="mb-lg">
                    <p class="mb-md">Are you sure you want to delete the grade item "<span id="deletingGradeItemName"></span>"?</p>
                    <div class="alert status-warning">
                        <p class="mb-0">This will permanently delete all student grades for this item.</p>
                    </div>
                </div>

                <form method="POST" action="gradebook.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="class_id" value="<?= $selectedClassId ?>">
                    <input type="hidden" name="item_id" id="deleteGradeItemId" value="">

                    <div class="d-flex gap-md justify-end">
                        <button type="button" class="btn" id="cancelDeleteGradeItemBtn">Cancel</button>
                        <button type="submit" name="delete_grade_item" class="btn btn-primary">Delete Grade Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');

            tabLinks.forEach(function(tabLink) {
                tabLink.addEventListener('click', function(e) {
                    e.preventDefault();

                    const tabId = this.getAttribute('data-tab');

                    // Update active tab link
                    tabLinks.forEach(function(link) {
                        link.classList.remove('active');
                    });
                    this.classList.add('active');

                    // Show selected tab content
                    tabContents.forEach(function(content) {
                        content.style.display = 'none';
                    });
                    document.getElementById(tabId).style.display = 'block';
                });
            });

            // Item selection for grading
            const itemSelect = document.getElementById('item_id');
            const gradesForm = document.getElementById('gradesForm');

            if (itemSelect) {
                itemSelect.addEventListener('change', function() {
                    if (this.value) {
                        const url = new URL(window.location.href);
                        url.searchParams.set('item_id', this.value);
                        window.location.href = url.toString();
                    } else {
                        gradesForm.style.display = 'none';
                    }
                });
            }

            // Grade Item Modal
            const addGradeItemBtn = document.getElementById('addGradeItemBtn');
            const gradeItemModal = document.getElementById('gradeItemModal');
            const closeGradeItemModal = document.getElementById('closeGradeItemModal');
            const cancelGradeItemBtn = document.getElementById('cancelGradeItemBtn');
            const gradeItemModalOverlay = document.getElementById('gradeItemModalOverlay');
            const gradeItemModalTitle = document.getElementById('gradeItemModalTitle');
            const editItemId = document.getElementById('editItemId');
            const itemName = document.getElementById('item_name');
            const itemDescription = document.getElementById('item_description');
            const maxPoints = document.getElementById('max_points');
            const weight = document.getElementById('weight');
            const itemDate = document.getElementById('item_date');
            const gradeItemSubmitBtn = document.getElementById('gradeItemSubmitBtn');

            if (addGradeItemBtn) {
                addGradeItemBtn.addEventListener('click', function() {
                    // Reset form for new item
                    editItemId.value = '';
                    itemName.value = '';
                    itemDescription.value = '';
                    maxPoints.value = '';
                    weight.value = '';
                    itemDate.value = new Date().toISOString().split('T')[0];

                    gradeItemModalTitle.textContent = 'Add New Grade Item';
                    gradeItemSubmitBtn.textContent = 'Add Grade Item';

                    gradeItemModal.style.display = 'flex';
                });
            }

            // Edit Grade Item
            const editItemBtns = document.querySelectorAll('.edit-item-btn');

            editItemBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const description = this.getAttribute('data-description');
                    const maxPointsVal = this.getAttribute('data-max-points');
                    const weightVal = this.getAttribute('data-weight');
                    const date = this.getAttribute('data-date');

                    editItemId.value = id;
                    itemName.value = name;
                    itemDescription.value = description;
                    maxPoints.value = maxPointsVal;
                    weight.value = weightVal;
                    itemDate.value = date;

                    gradeItemModalTitle.textContent = 'Edit Grade Item';
                    gradeItemSubmitBtn.textContent = 'Update Grade Item';

                    gradeItemModal.style.display = 'flex';
                });
            });

            if (closeGradeItemModal) {
                closeGradeItemModal.addEventListener('click', function() {
                    gradeItemModal.style.display = 'none';
                });
            }

            if (cancelGradeItemBtn) {
                cancelGradeItemBtn.addEventListener('click', function() {
                    gradeItemModal.style.display = 'none';
                });
            }

            if (gradeItemModalOverlay) {
                gradeItemModalOverlay.addEventListener('click', function() {
                    gradeItemModal.style.display = 'none';
                });
            }

            // Delete Grade Item Modal
            const deleteItemBtns = document.querySelectorAll('.delete-item-btn');
            const deleteGradeItemModal = document.getElementById('deleteGradeItemModal');
            const closeDeleteGradeItemModal = document.getElementById('closeDeleteGradeItemModal');
            const cancelDeleteGradeItemBtn = document.getElementById('cancelDeleteGradeItemBtn');
            const deleteGradeItemId = document.getElementById('deleteGradeItemId');
            const deletingGradeItemName = document.getElementById('deletingGradeItemName');
            const deleteGradeItemModalOverlay = document.getElementById('deleteGradeItemModalOverlay');

            deleteItemBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    deleteGradeItemId.value = id;
                    deletingGradeItemName.textContent = name;
                    deleteGradeItemModal.style.display = 'flex';
                });
            });

            if (closeDeleteGradeItemModal) {
                closeDeleteGradeItemModal.addEventListener('click', function() {
                    deleteGradeItemModal.style.display = 'none';
                });
            }

            if (cancelDeleteGradeItemBtn) {
                cancelDeleteGradeItemBtn.addEventListener('click', function() {
                    deleteGradeItemModal.style.display = 'none';
                });
            }

            if (deleteGradeItemModalOverlay) {
                deleteGradeItemModalOverlay.addEventListener('click', function() {
                    deleteGradeItemModal.style.display = 'none';
                });
            }
        });
    </script>

<?php
// Include page footer
include '../includes/footer.php';
?>
