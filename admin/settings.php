<?php
/**
 * Admin Settings Management
 *
 * Provides functionality for administrators to manage system settings,
 * academic terms, and subjects
 *
 * Functions:
 * - displayTermsList() - Displays a table of all terms with management actions
 * - displaySubjectsList() - Displays a table of all subjects with management actions
 * - getTermDetails($termId) - Fetches detailed information about a specific term
 * - getSubjectDetails($subjectId) - Fetches detailed information about a specific subject
 * - createTerm($termData) - Creates a new academic term
 * - updateTerm($termId, $termData) - Updates an existing term's information
 * - deleteTerm($termId) - Deletes a term if no classes are assigned to it
 * - createSubject($subjectData) - Creates a new subject
 * - updateSubject($subjectId, $subjectData) - Updates an existing subject's information
 * - deleteSubject($subjectId) - Deletes a subject if no classes use it
 * - createClass($classData) - Creates a new class
 * - updateClass($classId, $classData) - Updates an existing class's information
 * - deleteClass($classId) - Deletes a class if it has no enrollments
 * - addStudentToClass($classId, $studentId) - Adds a student to a class
 * - assignHomeRoomTeacher($classCode, $teacherId) - Assigns a homeroom teacher to a class
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// CSS styles are included in header.php

// Ensure only administrators can access this page
requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
if (!$pdo) {
    error_log("Database connection failed in admin/settings.php");
    die("Database connection failed. Please check the error log for details.");
}

$message = '';
$termDetails = null;
$subjectDetails = null;
$classDetails = null;
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'terms';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid form submission. Please try again.';
    } else {
        // Create new term
        if (isset($_POST['create_term'])) {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '';

            if (empty($name) || empty($startDate) || empty($endDate)) {
                $message = 'Please complete all required fields.';
            } else if (strtotime($startDate) >= strtotime($endDate)) {
                $message = 'End date must be after start date.';
            } else {
                // Check if term with same name already exists
                $stmt = $pdo->prepare("SELECT term_id FROM terms WHERE name = :name");
                $stmt->execute(['name' => $name]);
                if ($stmt->rowCount() > 0) {
                    $message = 'A term with this name already exists.';
                } else {
                    // Create term
                    $stmt = $pdo->prepare(
                        "INSERT INTO terms (name, start_date, end_date)
                         VALUES (:name, :start_date, :end_date)"
                    );
                    $success = $stmt->execute([
                        'name' => $name,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]);

                    if ($success) {
                        $message = 'Term created successfully.';
                    } else {
                        $message = 'Error creating term. Please try again.';
                    }
                }
            }
            $currentTab = 'terms';
        }

        // Update term
        else if (isset($_POST['update_term'])) {
            $termId = isset($_POST['term_id']) ? (int)$_POST['term_id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';
            $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : '';
            $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : '';

            if ($termId <= 0 || empty($name) || empty($startDate) || empty($endDate)) {
                $message = 'Please complete all required fields.';
            } else if (strtotime($startDate) >= strtotime($endDate)) {
                $message = 'End date must be after start date.';
            } else {
                // Check if term with same name already exists (excluding the current term)
                $stmt = $pdo->prepare("SELECT term_id FROM terms WHERE name = :name AND term_id != :term_id");
                $stmt->execute(['name' => $name, 'term_id' => $termId]);
                if ($stmt->rowCount() > 0) {
                    $message = 'A term with this name already exists.';
                } else {
                    // Update term
                    $stmt = $pdo->prepare(
                        "UPDATE terms
                         SET name = :name, start_date = :start_date, end_date = :end_date
                         WHERE term_id = :term_id"
                    );
                    $success = $stmt->execute([
                        'term_id' => $termId,
                        'name' => $name,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]);

                    if ($success) {
                        $message = 'Term updated successfully.';
                    } else {
                        $message = 'Error updating term. Please try again.';
                    }
                }
            }
            $currentTab = 'terms';
        }

        // Delete term
        else if (isset($_POST['delete_term'])) {
            $termId = isset($_POST['term_id']) ? (int)$_POST['term_id'] : 0;

            if ($termId <= 0) {
                $message = 'Invalid term ID.';
            } else {
                // Check if term is used by any classes
                $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM classes WHERE term_id = :term_id");
                $stmt->execute(['term_id' => $termId]);
                if ($stmt->fetch()['count'] > 0) {
                    $message = 'Cannot delete term: It is used by one or more classes.';
                } else {
                    // Delete term
                    $stmt = $pdo->prepare("DELETE FROM terms WHERE term_id = :term_id");
                    $stmt->execute(['term_id' => $termId]);

                    $message = 'Term deleted successfully.';
                }
            }
            $currentTab = 'terms';
        }

        // Create new subject
        else if (isset($_POST['create_subject'])) {
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';

            if (empty($name)) {
                $message = 'Please complete all required fields.';
            } else {
                // Check if subject with same name already exists
                $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE name = :name");
                $stmt->execute(['name' => $name]);
                if ($stmt->rowCount() > 0) {
                    $message = 'A subject with this name already exists.';
                } else {
                    // Create subject
                    $stmt = $pdo->prepare(
                        "INSERT INTO subjects (name)
                        VALUES (:name)"
                    );
                    $success = $stmt->execute([
                        'name' => $name
                    ]);

                    if ($success) {
                        $message = 'Subject created successfully.';
                    } else {
                        $message = 'Error creating subject. Please try again.';
                    }
                }
            }
            $currentTab = 'subjects';
        }

        // Update subject
        else if (isset($_POST['update_subject'])) {
            $subjectId = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
            $name = isset($_POST['name']) ? trim($_POST['name']) : '';

            if ($subjectId <= 0 || empty($name)) {
                $message = 'Please complete all required fields.';
            } else {
                // Check if subject with same name already exists (excluding the current subject)
                $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE name = :name AND subject_id != :subject_id");
                $stmt->execute(['name' => $name, 'subject_id' => $subjectId]);
                if ($stmt->rowCount() > 0) {
                    $message = 'Another subject with this name already exists.';
                } else {
                    // Update subject
                    $stmt = $pdo->prepare(
                        "UPDATE subjects
                        SET name = :name
                        WHERE subject_id = :subject_id"
                    );
                    $success = $stmt->execute([
                        'subject_id' => $subjectId,
                        'name' => $name
                    ]);

                    if ($success) {
                        $message = 'Subject updated successfully.';
                    } else {
                        $message = 'Error updating subject. Please try again.';
                    }
                }
            }
            $currentTab = 'subjects';
        }

        // Delete subject
        else if (isset($_POST['delete_subject'])) {
            $subjectId = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;

            if ($subjectId <= 0) {
                $message = 'Invalid subject ID.';
            } else {
                // Check if subject is used by any classes
                $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM classes WHERE subject_id = :subject_id");
                $stmt->execute(['subject_id' => $subjectId]);
                if ($stmt->fetch()['count'] > 0) {
                    $message = 'Cannot delete subject: It is used by one or more classes.';
                } else {
                    // Delete subject
                    $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = :subject_id");
                    $stmt->execute(['subject_id' => $subjectId]);

                    $message = 'Subject deleted successfully.';
                }
            }
            $currentTab = 'subjects';
        }

        // Create new class
        else if (isset($_POST['create_class'])) {
            $subjectId = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
            $teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
            $termId = isset($_POST['term_id']) ? (int)$_POST['term_id'] : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';

            if ($subjectId <= 0 || $teacherId <= 0 || $termId <= 0 || empty($title)) {
                $message = 'Please complete all required fields.';
            } else {
                // Create class
                $stmt = $pdo->prepare(
                    "INSERT INTO classes (subject_id, teacher_id, term_id, title)
                     VALUES (:subject_id, :teacher_id, :term_id, :title)"
                );
                $success = $stmt->execute([
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                    'term_id' => $termId,
                    'title' => $title
                ]);

                if ($success) {
                    $message = 'Class created successfully.';
                } else {
                    $message = 'Error creating class. Please try again.';
                }
            }
            $currentTab = 'classes';
        }

        // Update class
        else if (isset($_POST['update_class'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
            $subjectId = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
            $teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
            $termId = isset($_POST['term_id']) ? (int)$_POST['term_id'] : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';

            if ($classId <= 0 || $subjectId <= 0 || $teacherId <= 0 || $termId <= 0 || empty($title)) {
                $message = 'Please complete all required fields.';
            } else {
                // Update class
                $stmt = $pdo->prepare(
                    "UPDATE classes
                     SET subject_id = :subject_id, teacher_id = :teacher_id, 
                         term_id = :term_id, title = :title
                     WHERE class_id = :class_id"
                );
                $success = $stmt->execute([
                    'class_id' => $classId,
                    'subject_id' => $subjectId,
                    'teacher_id' => $teacherId,
                    'term_id' => $termId,
                    'title' => $title
                ]);

                if ($success) {
                    $message = 'Class updated successfully.';
                } else {
                    $message = 'Error updating class. Please try again.';
                }
            }
            $currentTab = 'classes';
        }

        // Delete class
        else if (isset($_POST['delete_class'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;

            if ($classId <= 0) {
                $message = 'Invalid class ID.';
            } else {
                // Check if class has enrollments
                $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM enrollments WHERE class_id = :class_id");
                $stmt->execute(['class_id' => $classId]);
                if ($stmt->fetch()['count'] > 0) {
                    $message = 'Cannot delete class: It has enrolled students. Please remove all enrollments first.';
                } else {
                    // Check if class has grade items
                    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM grade_items WHERE class_id = :class_id");
                    $stmt->execute(['class_id' => $classId]);
                    if ($stmt->fetch()['count'] > 0) {
                        $message = 'Cannot delete class: It has grade items. Please remove all grade items first.';
                    } else {
                        // Check if class has periods
                        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM periods WHERE class_id = :class_id");
                        $stmt->execute(['class_id' => $classId]);
                        if ($stmt->fetch()['count'] > 0) {
                            $message = 'Cannot delete class: It has periods. Please remove all periods first.';
                        } else {
                            // Delete class
                            $stmt = $pdo->prepare("DELETE FROM classes WHERE class_id = :class_id");
                            $stmt->execute(['class_id' => $classId]);
                            $message = 'Class deleted successfully.';
                        }
                    }
                }
            }
            $currentTab = 'classes';
        }

        // Add student to class
        else if (isset($_POST['add_student_to_class'])) {
            $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
            $studentId = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

            if ($classId <= 0 || $studentId <= 0) {
                $message = 'Please complete all required fields.';
            } else {
                // Check if student is already enrolled
                $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM enrollments WHERE student_id = :student_id AND class_id = :class_id");
                $stmt->execute(['student_id' => $studentId, 'class_id' => $classId]);
                if ($stmt->fetch()['count'] > 0) {
                    $message = 'This student is already enrolled in this class.';
                } else {
                    // Add enrollment
                    $stmt = $pdo->prepare(
                        "INSERT INTO enrollments (student_id, class_id)
                         VALUES (:student_id, :class_id)"
                    );
                    $success = $stmt->execute([
                        'student_id' => $studentId,
                        'class_id' => $classId
                    ]);

                    if ($success) {
                        $message = 'Student added to class successfully.';
                    } else {
                        $message = 'Error adding student to class. Please try again.';
                    }
                }
            }
            $currentTab = 'classes';
        }

        // Remove student from class
        else if (isset($_POST['remove_student_from_class'])) {
            $enrollId = isset($_POST['enroll_id']) ? (int)$_POST['enroll_id'] : 0;

            if ($enrollId <= 0) {
                $message = 'Invalid enrollment ID.';
            } else {
                // Check if there are grades for this enrollment
                $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM grades WHERE enroll_id = :enroll_id");
                $stmt->execute(['enroll_id' => $enrollId]);
                if ($stmt->fetch()['count'] > 0) {
                    $message = 'Cannot remove student: There are grades associated with this enrollment.';
                } else {
                    // Check if there are attendance records
                    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM attendance WHERE enroll_id = :enroll_id");
                    $stmt->execute(['enroll_id' => $enrollId]);
                    if ($stmt->fetch()['count'] > 0) {
                        $message = 'Cannot remove student: There are attendance records associated with this enrollment.';
                    } else {
                        // Remove enrollment
                        $stmt = $pdo->prepare("DELETE FROM enrollments WHERE enroll_id = :enroll_id");
                        $stmt->execute(['enroll_id' => $enrollId]);
                        $message = 'Student removed from class successfully.';
                    }
                }
            }
            $currentTab = 'classes';
        }

        // Assign homeroom teacher
        else if (isset($_POST['assign_homeroom'])) {
            $classCode = isset($_POST['class_code']) ? trim($_POST['class_code']) : '';
            $teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;

            if (empty($classCode) || $teacherId <= 0) {
                $message = 'Please complete all required fields.';
            } else {
                // Update students in this class to have this homeroom teacher
                $stmt = $pdo->prepare(
                    "UPDATE students 
                     SET class_code = :class_code 
                     WHERE student_id IN (SELECT student_id FROM enrollments WHERE class_id = :class_id)"
                );
                $success = $stmt->execute([
                    'class_code' => $classCode,
                    'class_id' => isset($_POST['homeroom_class_id']) ? (int)$_POST['homeroom_class_id'] : 0
                ]);

                if ($success) {
                    $message = 'Homeroom teacher assigned successfully.';
                } else {
                    $message = 'Error assigning homeroom teacher. Please try again.';
                }
            }
            $currentTab = 'classes';
        }
    }
}

// Get term details if ID is provided
if (isset($_GET['term_id'])) {
    $termId = (int)$_GET['term_id'];
    if ($termId > 0) {
        $stmt = $pdo->prepare(
            "SELECT term_id, name, start_date, end_date
             FROM terms
             WHERE term_id = :term_id"
        );
        $stmt->execute(['term_id' => $termId]);
        $termDetails = $stmt->fetch();
        $currentTab = 'terms';
    }
}

// Get subject details if ID is provided
if (isset($_GET['subject_id'])) {
    $subjectId = (int)$_GET['subject_id'];
    if ($subjectId > 0) {
        $stmt = $pdo->prepare(
            "SELECT subject_id, name
             FROM subjects
             WHERE subject_id = :subject_id"
        );
        $stmt->execute(['subject_id' => $subjectId]);
        $subjectDetails = $stmt->fetch();
        $currentTab = 'subjects';
    }
}

// Get class details if ID is provided
if (isset($_GET['class_id'])) {
    $classId = (int)$_GET['class_id'];
    if ($classId > 0) {
        $stmt = $pdo->prepare(
            "SELECT c.class_id, c.subject_id, c.teacher_id, c.term_id, c.title,
                    s.name as subject_name, CONCAT(u.username, ' (ID: ', t.teacher_id, ')') as teacher_name,
                    tm.name as term_name
             FROM classes c
             JOIN subjects s ON c.subject_id = s.subject_id
             JOIN teachers t ON c.teacher_id = t.teacher_id
             JOIN users u ON t.user_id = u.user_id
             JOIN terms tm ON c.term_id = tm.term_id
             WHERE c.class_id = :class_id"
        );
        $stmt->execute(['class_id' => $classId]);
        $classDetails = $stmt->fetch();
        $currentTab = 'classes';
    }
}

// Get all terms
$stmt = $pdo->prepare(
    "SELECT term_id, name, start_date, end_date
     FROM terms
     ORDER BY start_date DESC"
);
$stmt->execute();
$terms = $stmt->fetchAll();

// Get all subjects
$stmt = $pdo->prepare(
    "SELECT subject_id, name
     FROM subjects
     ORDER BY name"
);
$stmt->execute();
$subjects = $stmt->fetchAll();

// Get all classes
$stmt = $pdo->prepare(
    "SELECT c.class_id, c.title, s.name as subject_name, 
            CONCAT(u.username, ' (ID: ', t.teacher_id, ')') as teacher_name,
            tm.name as term_name
     FROM classes c
     JOIN subjects s ON c.subject_id = s.subject_id
     JOIN teachers t ON c.teacher_id = t.teacher_id
     JOIN users u ON t.user_id = u.user_id
     JOIN terms tm ON c.term_id = tm.term_id
     ORDER BY tm.start_date DESC, s.name, c.title"
);
$stmt->execute();
$classes = $stmt->fetchAll();

// Get all teachers
$stmt = $pdo->prepare(
    "SELECT t.teacher_id, u.username, u.user_id
     FROM teachers t
     JOIN users u ON t.user_id = u.user_id
     ORDER BY u.username"
);
$stmt->execute();
$teachers = $stmt->fetchAll();

// Get all students
$stmt = $pdo->prepare(
    "SELECT s.student_id, u.username, s.first_name, s.last_name, s.class_code
     FROM students s
     JOIN users u ON s.user_id = u.user_id
     ORDER BY s.last_name, s.first_name"
);
$stmt->execute();
$students = $stmt->fetchAll();

// Generate CSRF token
$csrfToken = generateCSRFToken();

?>
    <div class="card card-entrance mt-xl">
        <h1 class="mt-0 mb-lg">System Settings</h1>

        <?php if (!empty($message)): ?>
            <div class="alert <?= strpos($message, 'successfully') !== false ? 'status-success' : 'status-error' ?> mb-lg">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="d-flex gap-md mb-lg pb-md" style="border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <a href="?tab=terms" class="<?= $currentTab === 'terms' ? 'text-primary' : 'text-secondary' ?>" style="padding-bottom: 8px; border-bottom: 2px solid <?= $currentTab === 'terms' ? 'var(--accent-primary)' : 'transparent' ?>;">
                Academic Terms
            </a>
            <a href="?tab=subjects" class="<?= $currentTab === 'subjects' ? 'text-primary' : 'text-secondary' ?>" style="padding-bottom: 8px; border-bottom: 2px solid <?= $currentTab === 'subjects' ? 'var(--accent-primary)' : 'transparent' ?>;">
                Subjects
            </a>
            <a href="?tab=classes" class="<?= $currentTab === 'classes' ? 'text-primary' : 'text-secondary' ?>" style="padding-bottom: 8px; border-bottom: 2px solid <?= $currentTab === 'classes' ? 'var(--accent-primary)' : 'transparent' ?>;">
                Classes
            </a>
        </div>

        <!-- Terms Tab -->
        <?php if ($currentTab === 'terms'): ?>
            <div class="d-flex justify-between items-center mb-md">
                <h2 class="mt-0 mb-0">Academic Terms</h2>
                <button class="btn btn-primary" id="addTermBtn">Add New Term</button>
            </div>

            <?php if (empty($terms)): ?>
                <div class="bg-tertiary p-lg text-center rounded mb-lg">
                    <p class="mb-sm">No academic terms found.</p>
                    <p class="text-secondary mt-0">Click "Add New Term" to create the first term.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive mb-lg">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($terms as $term): ?>
                            <tr>
                                <td><?= $term['term_id'] ?></td>
                                <td><?= htmlspecialchars($term['name']) ?></td>
                                <td><?= date('d.m.Y', strtotime($term['start_date'])) ?></td>
                                <td><?= date('d.m.Y', strtotime($term['end_date'])) ?></td>
                                <td>
                                    <div class="d-flex gap-sm">
                                        <a href="?tab=terms&term_id=<?= $term['term_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <button class="btn btn-secondary btn-sm delete-term-btn" data-id="<?= $term['term_id'] ?>" data-name="<?= htmlspecialchars($term['name']) ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Term Form Modal -->
            <div class="modal" id="termModal" style="display: <?= $termDetails ? 'flex' : 'none' ?>;">
                <div class="modal-overlay" id="termModalOverlay"></div>
                <div class="modal-container">
                    <div class="card">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0"><?= $termDetails ? 'Edit Term' : 'Add New Term' ?></h3>
                            <button type="button" class="btn-close" id="closeTermModal">&times;</button>
                        </div>

                        <form method="POST" action="settings.php?tab=terms">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <?php if ($termDetails): ?>
                                <input type="hidden" name="term_id" value="<?= $termDetails['term_id'] ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="name" class="form-label">Term Name</label>
                                <input type="text" id="name" name="name" class="form-input" value="<?= $termDetails ? htmlspecialchars($termDetails['name']) : '' ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-input" value="<?= $termDetails ? $termDetails['start_date'] : '' ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="form-input" value="<?= $termDetails ? $termDetails['end_date'] : '' ?>" required>
                            </div>

                            <div class="d-flex gap-md justify-end mt-lg">
                                <button type="button" class="btn" id="cancelTermBtn">Cancel</button>
                                <button type="submit" name="<?= $termDetails ? 'update_term' : 'create_term' ?>" class="btn btn-primary">
                                    <?= $termDetails ? 'Update Term' : 'Create Term' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Term Confirmation Modal -->
            <div class="modal" id="deleteTermModal" style="display: none;">
                <div class="modal-overlay" id="deleteTermModalOverlay"></div>
                <div class="modal-container">
                    <div class="card">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0">Confirm Deletion</h3>
                            <button type="button" class="btn-close" id="closeDeleteTermModal">&times;</button>
                        </div>

                        <div class="mb-lg">
                            <p class="mb-md">Are you sure you want to delete term "<span id="deletingTermName"></span>"?</p>
                            <div class="alert status-warning">
                                <p class="mb-0">Warning: If this term is used by any classes, it cannot be deleted.</p>
                            </div>
                        </div>

                        <form method="POST" action="settings.php?tab=terms">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="term_id" id="deleteTermId" value="">

                            <div class="d-flex gap-md justify-end">
                                <button type="button" class="btn" id="cancelDeleteTermBtn">Cancel</button>
                                <button type="submit" name="delete_term" class="btn btn-primary">Delete Term</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Subjects Tab -->
        <?php if ($currentTab === 'subjects'): ?>
            <div class="d-flex justify-between items-center mb-md">
                <h2 class="mt-0 mb-0">Subjects</h2>
                <button class="btn btn-primary" id="addSubjectBtn">Add New Subject</button>
            </div>

            <?php if (empty($subjects)): ?>
                <div class="bg-tertiary p-lg text-center rounded mb-lg">
                    <p class="mb-sm">No subjects found.</p>
                    <p class="text-secondary mt-0">Click "Add New Subject" to create the first subject.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive mb-lg">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?= $subject['subject_id'] ?></td>
                                <td><?= htmlspecialchars($subject['name']) ?></td>
                                <td>
                                    <div class="d-flex gap-sm">
                                        <a href="?tab=subjects&subject_id=<?= $subject['subject_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <button class="btn btn-secondary btn-sm delete-subject-btn" data-id="<?= $subject['subject_id'] ?>" data-name="<?= htmlspecialchars($subject['name']) ?>">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Subject Form Modal -->
            <div class="modal" id="subjectModal" style="display: <?= $subjectDetails ? 'flex' : 'none' ?>;">
                <div class="modal-overlay" id="subjectModalOverlay"></div>
                <div class="modal-container">
                    <div class="card">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0"><?= $subjectDetails ? 'Edit Subject' : 'Add New Subject' ?></h3>
                            <button type="button" class="btn-close" id="closeSubjectModal">&times;</button>
                        </div>

                        <form method="POST" action="settings.php?tab=subjects">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <?php if ($subjectDetails): ?>
                                <input type="hidden" name="subject_id" value="<?= $subjectDetails['subject_id'] ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="name" class="form-label">Subject Name</label>
                                <input type="text" id="name" name="name" class="form-input" value="<?= $subjectDetails ? htmlspecialchars($subjectDetails['name']) : '' ?>" required>
                            </div>

                            <div class="d-flex gap-md justify-end mt-lg">
                                <button type="button" class="btn" id="cancelSubjectBtn">Cancel</button>
                                <button type="submit" name="<?= $subjectDetails ? 'update_subject' : 'create_subject' ?>" class="btn btn-primary">
                                    <?= $subjectDetails ? 'Update Subject' : 'Create Subject' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Subject Confirmation Modal -->
            <div class="modal" id="deleteSubjectModal" style="display: none;">
                <div class="modal-overlay" id="deleteSubjectModalOverlay"></div>
                <div class="modal-container">
                    <div class="card">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0">Confirm Deletion</h3>
                            <button type="button" class="btn-close" id="closeDeleteSubjectModal">&times;</button>
                        </div>

                        <div class="mb-lg">
                            <p class="mb-md">Are you sure you want to delete subject "<span id="deletingSubjectName"></span>"?</p>
                            <div class="alert status-warning">
                                <p class="mb-0">Warning: If this subject is used by any classes, it cannot be deleted.</p>
                            </div>
                        </div>

                        <form method="POST" action="settings.php?tab=subjects">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="subject_id" id="deleteSubjectId" value="">

                            <div class="d-flex gap-md justify-end">
                                <button type="button" class="btn" id="cancelDeleteSubjectBtn">Cancel</button>
                                <button type="submit" name="delete_subject" class="btn btn-primary">Delete Subject</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Classes Tab -->
        <?php if ($currentTab === 'classes'): ?>
            <div class="d-flex justify-between items-center mb-md">
                <h2 class="mt-0 mb-0">Classes</h2>
                <button class="btn btn-primary" id="addClassBtn">Add New Class</button>
            </div>

            <?php if (empty($classes)): ?>
                <div class="bg-tertiary p-lg text-center rounded mb-lg">
                    <p class="mb-sm">No classes found.</p>
                    <p class="text-secondary mt-0">Click "Add New Class" to create the first class.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive mb-lg">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Term</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?= $class['class_id'] ?></td>
                                <td><?= htmlspecialchars($class['title']) ?></td>
                                <td><?= htmlspecialchars($class['subject_name']) ?></td>
                                <td><?= htmlspecialchars($class['teacher_name']) ?></td>
                                <td><?= htmlspecialchars($class['term_name']) ?></td>
                                <td>
                                    <div class="d-flex gap-sm">
                                        <a href="?tab=classes&class_id=<?= $class['class_id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                        <button class="btn btn-secondary btn-sm delete-class-btn" 
                                                data-id="<?= $class['class_id'] ?>" 
                                                data-name="<?= htmlspecialchars($class['title']) ?>">Delete</button>
                                        <a href="?tab=classes&class_id=<?= $class['class_id'] ?>&action=students" class="btn btn-secondary btn-sm">Students</a>
                                        <a href="?tab=classes&class_id=<?= $class['class_id'] ?>&action=homeroom" class="btn btn-secondary btn-sm">Homeroom</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Class Form Modal -->
            <div class="modal" id="classModal" style="display: <?= ($classDetails && !isset($_GET['action'])) ? 'flex' : 'none' ?>;">
                <div class="modal-overlay" id="classModalOverlay"></div>
                <div class="modal-container">
                    <div class="card">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0"><?= $classDetails ? 'Edit Class' : 'Add New Class' ?></h3>
                            <button type="button" class="btn-close" id="closeClassModal">&times;</button>
                        </div>

                        <form method="POST" action="settings.php?tab=classes">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <?php if ($classDetails): ?>
                                <input type="hidden" name="class_id" value="<?= $classDetails['class_id'] ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label for="title" class="form-label">Class Title</label>
                                <input type="text" id="title" name="title" class="form-input" 
                                       value="<?= $classDetails ? htmlspecialchars($classDetails['title']) : '' ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="subject_id" class="form-label">Subject</label>
                                <select id="subject_id" name="subject_id" class="form-input" required>
                                    <option value="">-- Select Subject --</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['subject_id'] ?>" <?= $classDetails && $classDetails['subject_id'] == $subject['subject_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <select id="teacher_id" name="teacher_id" class="form-input" required>
                                    <option value="">-- Select Teacher --</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?= $teacher['teacher_id'] ?>" <?= $classDetails && $classDetails['teacher_id'] == $teacher['teacher_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($teacher['username']) ?> (ID: <?= $teacher['teacher_id'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="term_id" class="form-label">Term</label>
                                <select id="term_id" name="term_id" class="form-input" required>
                                    <option value="">-- Select Term --</option>
                                    <?php foreach ($terms as $term): ?>
                                        <option value="<?= $term['term_id'] ?>" <?= $classDetails && $classDetails['term_id'] == $term['term_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($term['name']) ?> 
                                            (<?= date('d.m.Y', strtotime($term['start_date'])) ?> - 
                                             <?= date('d.m.Y', strtotime($term['end_date'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="d-flex gap-md justify-end mt-lg">
                                <button type="button" class="btn" id="cancelClassBtn">Cancel</button>
                                <button type="submit" name="<?= $classDetails ? 'update_class' : 'create_class' ?>" class="btn btn-primary">
                                    <?= $classDetails ? 'Update Class' : 'Create Class' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Class Confirmation Modal -->
            <div class="modal" id="deleteClassModal" style="display: none;">
                <div class="modal-overlay" id="deleteClassModalOverlay"></div>
                <div class="modal-container">
                    <div class="card">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0">Confirm Deletion</h3>
                            <button type="button" class="btn-close" id="closeDeleteClassModal">&times;</button>
                        </div>

                        <div class="mb-lg">
                            <p class="mb-md">Are you sure you want to delete class "<span id="deletingClassName"></span>"?</p>
                            <div class="alert status-warning">
                                <p class="mb-0">Warning: If this class has enrolled students, grade items, or periods, it cannot be deleted.</p>
                            </div>
                        </div>

                        <form method="POST" action="settings.php?tab=classes">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="class_id" id="deleteClassId" value="">

                            <div class="d-flex gap-md justify-end">
                                <button type="button" class="btn" id="cancelDeleteClassBtn">Cancel</button>
                                <button type="submit" name="delete_class" class="btn btn-primary">Delete Class</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Students in Class Modal -->
            <?php 
            if (isset($_GET['class_id']) && isset($_GET['action']) && $_GET['action'] === 'students'):
                $classId = (int)$_GET['class_id'];
                // Get class details
                $stmt = $pdo->prepare(
                    "SELECT c.class_id, c.title, s.name as subject_name 
                     FROM classes c 
                     JOIN subjects s ON c.subject_id = s.subject_id 
                     WHERE c.class_id = :class_id"
                );
                $stmt->execute(['class_id' => $classId]);
                $currentClass = $stmt->fetch();
                
                // Get enrolled students
                $stmt = $pdo->prepare(
                    "SELECT e.enroll_id, s.student_id, u.username, s.first_name, s.last_name 
                     FROM enrollments e
                     JOIN students s ON e.student_id = s.student_id
                     JOIN users u ON s.user_id = u.user_id
                     WHERE e.class_id = :class_id
                     ORDER BY s.last_name, s.first_name"
                );
                $stmt->execute(['class_id' => $classId]);
                $enrolledStudents = $stmt->fetchAll();
                
                // Get students not enrolled in this class
                $stmt = $pdo->prepare(
                    "SELECT s.student_id, u.username, s.first_name, s.last_name 
                     FROM students s
                     JOIN users u ON s.user_id = u.user_id
                     WHERE s.student_id NOT IN (
                         SELECT e.student_id FROM enrollments e WHERE e.class_id = :class_id
                     )
                     ORDER BY s.last_name, s.first_name"
                );
                $stmt->execute(['class_id' => $classId]);
                $availableStudents = $stmt->fetchAll();
            ?>
            <div class="modal" style="display: flex;">
                <div class="modal-overlay"></div>
                <div class="modal-container">
                    <div class="card" style="width: 800px; max-width: 95vw;">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0">Manage Students in Class: <?= htmlspecialchars($currentClass['title']) ?></h3>
                            <a href="?tab=classes" class="btn-close">&times;</a>
                        </div>

                        <!-- Add Student Form -->
                        <?php if (!empty($availableStudents)): ?>
                            <div class="bg-tertiary p-lg rounded mb-lg">
                                <h4 class="mt-0 mb-md">Add Student to Class</h4>
                                <form method="POST" action="settings.php?tab=classes" class="d-flex gap-md items-end">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                                    
                                    <div class="form-group mb-0" style="flex: 1;">
                                        <label for="student_id" class="form-label">Student</label>
                                        <select id="student_id" name="student_id" class="form-input" required>
                                            <option value="">-- Select Student --</option>
                                            <?php foreach ($availableStudents as $student): ?>
                                                <option value="<?= $student['student_id'] ?>">
                                                    <?= htmlspecialchars($student['last_name']) ?>, <?= htmlspecialchars($student['first_name']) ?> 
                                                    (<?= htmlspecialchars($student['username']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <button type="submit" name="add_student_to_class" class="btn btn-primary">Add Student</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- Enrolled Students List -->
                        <h4 class="mt-0 mb-md">Enrolled Students</h4>
                        <?php if (empty($enrolledStudents)): ?>
                            <div class="bg-tertiary p-lg text-center rounded mb-lg">
                                <p class="mb-0">No students enrolled in this class yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($enrolledStudents as $student): ?>
                                        <tr>
                                            <td><?= $student['student_id'] ?></td>
                                            <td><?= htmlspecialchars($student['last_name']) ?>, <?= htmlspecialchars($student['first_name']) ?></td>
                                            <td><?= htmlspecialchars($student['username']) ?></td>
                                            <td>
                                                <form method="POST" action="settings.php?tab=classes" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="enroll_id" value="<?= $student['enroll_id'] ?>">
                                                    <button type="submit" name="remove_student_from_class" class="btn btn-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to remove this student from the class?');">
                                                        Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-end mt-lg">
                            <a href="?tab=classes" class="btn">Back to Classes</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Homeroom Assignment Modal -->
            <?php 
            if (isset($_GET['class_id']) && isset($_GET['action']) && $_GET['action'] === 'homeroom'):
                $classId = (int)$_GET['class_id'];
                // Get class details
                $stmt = $pdo->prepare(
                    "SELECT c.class_id, c.title, c.teacher_id,
                            CONCAT(u.username, ' (ID: ', t.teacher_id, ')') as teacher_name
                     FROM classes c 
                     JOIN teachers t ON c.teacher_id = t.teacher_id
                     JOIN users u ON t.user_id = u.user_id
                     WHERE c.class_id = :class_id"
                );
                $stmt->execute(['class_id' => $classId]);
                $currentClass = $stmt->fetch();
                
                // Get a list of homeroom class codes
                $stmt = $pdo->prepare(
                    "SELECT DISTINCT class_code FROM students ORDER BY class_code"
                );
                $stmt->execute();
                $classCodes = $stmt->fetchAll();
            ?>
            <div class="modal" style="display: flex;">
                <div class="modal-overlay"></div>
                <div class="modal-container">
                    <div class="card">
                        <div class="d-flex justify-between items-center mb-lg">
                            <h3 class="mt-0 mb-0">Assign Homeroom: <?= htmlspecialchars($currentClass['title']) ?></h3>
                            <a href="?tab=classes" class="btn-close">&times;</a>
                        </div>

                        <p>Assign this class as a homeroom and its teacher as the homeroom teacher.</p>
                        
                        <form method="POST" action="settings.php?tab=classes">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <input type="hidden" name="homeroom_class_id" value="<?= $classId ?>">
                            <input type="hidden" name="teacher_id" value="<?= $currentClass['teacher_id'] ?>">
                            
                            <div class="form-group">
                                <label for="class_code" class="form-label">Homeroom Class Code</label>
                                <div class="d-flex gap-md">
                                    <input type="text" id="class_code" name="class_code" 
                                           class="form-input" placeholder="e.g., 10A, 11B, 12C" required
                                           list="existing_class_codes" style="flex: 1;">
                                    <datalist id="existing_class_codes">
                                        <?php foreach ($classCodes as $code): ?>
                                            <?php if (!empty($code['class_code'])): ?>
                                                <option value="<?= htmlspecialchars($code['class_code']) ?>">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <p class="text-secondary mt-sm">
                                    This will update all students in this class to have this homeroom class code.
                                </p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Homeroom Teacher</label>
                                <input type="text" class="form-input" value="<?= htmlspecialchars($currentClass['teacher_name']) ?>" disabled>
                                <p class="text-secondary mt-sm">
                                    The teacher of this class will be assigned as the homeroom teacher.
                                </p>
                            </div>
                            
                            <div class="d-flex gap-md justify-end mt-lg">
                                <a href="?tab=classes" class="btn">Cancel</a>
                                <button type="submit" name="assign_homeroom" class="btn btn-primary">Assign Homeroom</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Term management
            const addTermBtn = document.getElementById('addTermBtn');
            const termModal = document.getElementById('termModal');
            const closeTermModal = document.getElementById('closeTermModal');
            const cancelTermBtn = document.getElementById('cancelTermBtn');
            const termModalOverlay = document.getElementById('termModalOverlay');

            if (addTermBtn) {
                addTermBtn.addEventListener('click', function() {
                    termModal.style.display = 'flex';
                });
            }

            if (closeTermModal) {
                closeTermModal.addEventListener('click', function() {
                    termModal.style.display = 'none';
                });
            }

            if (cancelTermBtn) {
                cancelTermBtn.addEventListener('click', function() {
                    termModal.style.display = 'none';
                });
            }

            if (termModalOverlay) {
                termModalOverlay.addEventListener('click', function() {
                    termModal.style.display = 'none';
                });
            }

            // Delete term functionality
            const deleteTermBtns = document.querySelectorAll('.delete-term-btn');
            const deleteTermModal = document.getElementById('deleteTermModal');
            const closeDeleteTermModal = document.getElementById('closeDeleteTermModal');
            const cancelDeleteTermBtn = document.getElementById('cancelDeleteTermBtn');
            const deleteTermId = document.getElementById('deleteTermId');
            const deletingTermName = document.getElementById('deletingTermName');
            const deleteTermModalOverlay = document.getElementById('deleteTermModalOverlay');

            deleteTermBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    deleteTermId.value = id;
                    deletingTermName.textContent = name;
                    deleteTermModal.style.display = 'flex';
                });
            });

            if (closeDeleteTermModal) {
                closeDeleteTermModal.addEventListener('click', function() {
                    deleteTermModal.style.display = 'none';
                });
            }

            if (cancelDeleteTermBtn) {
                cancelDeleteTermBtn.addEventListener('click', function() {
                    deleteTermModal.style.display = 'none';
                });
            }

            if (deleteTermModalOverlay) {
                deleteTermModalOverlay.addEventListener('click', function() {
                    deleteTermModal.style.display = 'none';
                });
            }

            // Subject management
            const addSubjectBtn = document.getElementById('addSubjectBtn');
            const subjectModal = document.getElementById('subjectModal');
            const closeSubjectModal = document.getElementById('closeSubjectModal');
            const cancelSubjectBtn = document.getElementById('cancelSubjectBtn');
            const subjectModalOverlay = document.getElementById('subjectModalOverlay');

            if (addSubjectBtn) {
                addSubjectBtn.addEventListener('click', function() {
                    subjectModal.style.display = 'flex';
                });
            }

            if (closeSubjectModal) {
                closeSubjectModal.addEventListener('click', function() {
                    subjectModal.style.display = 'none';
                });
            }

            if (cancelSubjectBtn) {
                cancelSubjectBtn.addEventListener('click', function() {
                    subjectModal.style.display = 'none';
                });
            }

            if (subjectModalOverlay) {
                subjectModalOverlay.addEventListener('click', function() {
                    subjectModal.style.display = 'none';
                });
            }

            // Delete subject functionality
            const deleteSubjectBtns = document.querySelectorAll('.delete-subject-btn');
            const deleteSubjectModal = document.getElementById('deleteSubjectModal');
            const closeDeleteSubjectModal = document.getElementById('closeDeleteSubjectModal');
            const cancelDeleteSubjectBtn = document.getElementById('cancelDeleteSubjectBtn');
            const deleteSubjectId = document.getElementById('deleteSubjectId');
            const deletingSubjectName = document.getElementById('deletingSubjectName');
            const deleteSubjectModalOverlay = document.getElementById('deleteSubjectModalOverlay');

            deleteSubjectBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    deleteSubjectId.value = id;
                    deletingSubjectName.textContent = name;
                    deleteSubjectModal.style.display = 'flex';
                });
            });

            if (closeDeleteSubjectModal) {
                closeDeleteSubjectModal.addEventListener('click', function() {
                    deleteSubjectModal.style.display = 'none';
                });
            }

            if (cancelDeleteSubjectBtn) {
                cancelDeleteSubjectBtn.addEventListener('click', function() {
                    deleteSubjectModal.style.display = 'none';
                });
            }

            if (deleteSubjectModalOverlay) {
                deleteSubjectModalOverlay.addEventListener('click', function() {
                    deleteSubjectModal.style.display = 'none';
                });
            }

            // Class management
            const addClassBtn = document.getElementById('addClassBtn');
            const classModal = document.getElementById('classModal');
            const closeClassModal = document.getElementById('closeClassModal');
            const cancelClassBtn = document.getElementById('cancelClassBtn');
            const classModalOverlay = document.getElementById('classModalOverlay');

            if (addClassBtn) {
                addClassBtn.addEventListener('click', function() {
                    classModal.style.display = 'flex';
                });
            }

            if (closeClassModal) {
                closeClassModal.addEventListener('click', function() {
                    classModal.style.display = 'none';
                });
            }

            if (cancelClassBtn) {
                cancelClassBtn.addEventListener('click', function() {
                    classModal.style.display = 'none';
                });
            }

            if (classModalOverlay) {
                classModalOverlay.addEventListener('click', function() {
                    classModal.style.display = 'none';
                });
            }

            // Delete class functionality
            const deleteClassBtns = document.querySelectorAll('.delete-class-btn');
            const deleteClassModal = document.getElementById('deleteClassModal');
            const closeDeleteClassModal = document.getElementById('closeDeleteClassModal');
            const cancelDeleteClassBtn = document.getElementById('cancelDeleteClassBtn');
            const deleteClassId = document.getElementById('deleteClassId');
            const deletingClassName = document.getElementById('deletingClassName');
            const deleteClassModalOverlay = document.getElementById('deleteClassModalOverlay');

            deleteClassBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    deleteClassId.value = id;
                    deletingClassName.textContent = name;
                    deleteClassModal.style.display = 'flex';
                });
            });

            if (closeDeleteClassModal) {
                closeDeleteClassModal.addEventListener('click', function() {
                    deleteClassModal.style.display = 'none';
                });
            }

            if (cancelDeleteClassBtn) {
                cancelDeleteClassBtn.addEventListener('click', function() {
                    deleteClassModal.style.display = 'none';
                });
            }

            if (deleteClassModalOverlay) {
                deleteClassModalOverlay.addEventListener('click', function() {
                    deleteClassModal.style.display = 'none';
                });
            }
        });
    </script>

<?php
// Include page footer
include '../includes/footer.php';
?>
