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
            $code = isset($_POST['code']) ? trim($_POST['code']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            
            if (empty($name) || empty($code)) {
                $message = 'Please complete all required fields.';
            } else {
                // Check if subject with same name or code already exists
                $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE name = :name OR code = :code");
                $stmt->execute(['name' => $name, 'code' => $code]);
                if ($stmt->rowCount() > 0) {
                    $message = 'A subject with this name or code already exists.';
                } else {
                    // Create subject
                    $stmt = $pdo->prepare(
                        "INSERT INTO subjects (name, code, description)
                         VALUES (:name, :code, :description)"
                    );
                    $success = $stmt->execute([
                        'name' => $name,
                        'code' => $code,
                        'description' => $description
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
            $code = isset($_POST['code']) ? trim($_POST['code']) : '';
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            
            if ($subjectId <= 0 || empty($name) || empty($code)) {
                $message = 'Please complete all required fields.';
            } else {
                // Check if subject with same name or code already exists (excluding the current subject)
                $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE (name = :name OR code = :code) AND subject_id != :subject_id");
                $stmt->execute(['name' => $name, 'code' => $code, 'subject_id' => $subjectId]);
                if ($stmt->rowCount() > 0) {
                    $message = 'Another subject with this name or code already exists.';
                } else {
                    // Update subject
                    $stmt = $pdo->prepare(
                        "UPDATE subjects 
                         SET name = :name, code = :code, description = :description
                         WHERE subject_id = :subject_id"
                    );
                    $success = $stmt->execute([
                        'subject_id' => $subjectId,
                        'name' => $name,
                        'code' => $code,
                        'description' => $description
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
            "SELECT subject_id, name, code, description
             FROM subjects
             WHERE subject_id = :subject_id"
        );
        $stmt->execute(['subject_id' => $subjectId]);
        $subjectDetails = $stmt->fetch();
        $currentTab = 'subjects';
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
    "SELECT subject_id, name, code, description
     FROM subjects
     ORDER BY name"
);
$stmt->execute();
$subjects = $stmt->fetchAll();

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Include header
include '../includes/header.php';
?>

<?php /* 
    [ADMIN SETTINGS PAGE PLACEHOLDER]
    Components:
    - Page container with admin settings layout
    
    - Page title "System Settings"
    
    - Alert message display when $message is not empty
      - Different styling based on message type
    
    - Tab navigation with:
      - "Academic Terms" tab
      - "Subjects" tab
    
    - Terms Tab (shown when $currentTab === 'terms'):
      - Action button to create a new term
      - Table of terms with:
        - Headers: ID, Name, Start Date, End Date, Actions
        - For each term:
          - Term ID
          - Term name
          - Formatted start date
          - Formatted end date
          - Action buttons (Edit, Delete)
      - Term form modal for creating/editing terms with:
        - Name field
        - Start Date picker
        - End Date picker
        - Submit and Cancel buttons
    
    - Subjects Tab (shown when $currentTab === 'subjects'):
      - Action button to create a new subject
      - Table of subjects with:
        - Headers: ID, Code, Name, Description, Actions
        - For each subject:
          - Subject ID
          - Subject code
          - Subject name
          - Subject description (truncated if long)
          - Action buttons (Edit, Delete)
      - Subject form modal for creating/editing subjects with:
        - Name field
        - Code field
        - Description textarea
        - Submit and Cancel buttons
    
    - Delete confirmation modals for both terms and subjects
      - Warning message about potential impacts
      - Confirm and Cancel buttons
    
    - Interactive features:
      - Tab switching without page reload
      - Form validation
      - Date validation for terms
      - Confirmation for delete actions
*/ ?>

<?php
// Include page footer
include '../includes/footer.php';
?>
