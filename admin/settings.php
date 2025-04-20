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

// Ensure only administrators can access this page
requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
if (!$pdo) {
    error_log("Database connection failed in admin/settings.php");
    die("Database connection failed. Please check the error log for details.");
}

$message = '';
$error = '';

// Set default active tab
$activeTab = $_GET['tab'] ?? 'terms';

// Variables for form data
$termDetails = null;
$subjectDetails = null;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['action'] ?? '';

    // Verify CSRF token for all POST actions
    if (isset($_POST['csrf_token'])) {
        verifyCSRFToken($_POST['csrf_token']);
    } else {
        $error = 'Security token missing. Please try again.';
    }

    if (empty($error)) {
        try {
            switch ($formAction) {
                // Term management actions
                case 'create_term':
                    // Create new term
                    $name = trim($_POST['name']);
                    $startDate = trim($_POST['start_date']);
                    $endDate = trim($_POST['end_date']);

                    // Basic validation
                    if (empty($name) || empty($startDate) || empty($endDate)) {
                        $error = 'All fields are required.';
                        break;
                    }

                    // Validate dates
                    $startDateTime = new DateTime($startDate);
                    $endDateTime = new DateTime($endDate);

                    if ($endDateTime <= $startDateTime) {
                        $error = 'End date must be after start date.';
                        break;
                    }

                    // Check if term with same name exists
                    $stmt = $pdo->prepare("SELECT term_id FROM terms WHERE name = :name");
                    $stmt->execute(['name' => $name]);
                    if ($stmt->fetch()) {
                        $error = 'Term name already exists. Please choose another.';
                        break;
                    }

                    // Insert term
                    $stmt = $pdo->prepare("INSERT INTO terms (name, start_date, end_date) 
                                        VALUES (:name, :start_date, :end_date)");
                    $stmt->execute([
                        'name' => $name,
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ]);

                    $message = "Term '$name' created successfully.";
                    $activeTab = 'terms';
                    break;

                case 'update_term':
                    // Update existing term
                    $termId = (int)$_POST['term_id'];
                    $name = trim($_POST['name']);
                    $startDate = trim($_POST['start_date']);
                    $endDate = trim($_POST['end_date']);

                    // Basic validation
                    if (empty($name) || empty($startDate) || empty($endDate)) {
                        $error = 'All fields are required.';
                        break;
                    }

                    // Validate dates
                    $startDateTime = new DateTime($startDate);
                    $endDateTime = new DateTime($endDate);

                    if ($endDateTime <= $startDateTime) {
                        $error = 'End date must be after start date.';
                        break;
                    }

                    // Check if term name exists (excluding current term)
                    $stmt = $pdo->prepare("SELECT term_id FROM terms WHERE name = :name AND term_id != :term_id");
                    $stmt->execute([
                        'name' => $name,
                        'term_id' => $termId
                    ]);

                    if ($stmt->fetch()) {
                        $error = 'Term name already exists. Please choose another.';
                        break;
                    }

                    // Update the term
                    $stmt = $pdo->prepare("UPDATE terms SET 
                                        name = :name, 
                                        start_date = :start_date, 
                                        end_date = :end_date 
                                        WHERE term_id = :term_id");
                    $stmt->execute([
                        'name' => $name,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'term_id' => $termId
                    ]);

                    $message = "Term updated successfully.";
                    $activeTab = 'terms';
                    break;

                case 'delete_term':
                    // Delete term if no classes use it
                    $termId = (int)$_POST['term_id'];

                    // Check if term has classes
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classes WHERE term_id = :term_id");
                    $stmt->execute(['term_id' => $termId]);

                    if ($stmt->fetch()['count'] > 0) {
                        $error = 'Cannot delete: Term has classes assigned to it.';
                        break;
                    }

                    // Delete term
                    $stmt = $pdo->prepare("DELETE FROM terms WHERE term_id = :term_id");
                    $stmt->execute(['term_id' => $termId]);

                    $message = "Term deleted successfully.";
                    $activeTab = 'terms';
                    break;

                // Subject management actions
                case 'create_subject':
                    // Create new subject
                    $name = trim($_POST['name']);

                    // Basic validation
                    if (empty($name)) {
                        $error = 'Subject name is required.';
                        break;
                    }

                    // Check if subject with same name exists
                    $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE name = :name");
                    $stmt->execute(['name' => $name]);
                    if ($stmt->fetch()) {
                        $error = 'Subject name already exists. Please choose another.';
                        break;
                    }

                    // Insert subject
                    $stmt = $pdo->prepare("INSERT INTO subjects (name) VALUES (:name)");
                    $stmt->execute(['name' => $name]);

                    $message = "Subject '$name' created successfully.";
                    $activeTab = 'subjects';
                    break;

                case 'update_subject':
                    // Update existing subject
                    $subjectId = (int)$_POST['subject_id'];
                    $name = trim($_POST['name']);

                    // Basic validation
                    if (empty($name)) {
                        $error = 'Subject name is required.';
                        break;
                    }

                    // Check if subject name exists (excluding current subject)
                    $stmt = $pdo->prepare("SELECT subject_id FROM subjects WHERE name = :name AND subject_id != :subject_id");
                    $stmt->execute([
                        'name' => $name,
                        'subject_id' => $subjectId
                    ]);

                    if ($stmt->fetch()) {
                        $error = 'Subject name already exists. Please choose another.';
                        break;
                    }

                    // Update the subject
                    $stmt = $pdo->prepare("UPDATE subjects SET name = :name WHERE subject_id = :subject_id");
                    $stmt->execute([
                        'name' => $name,
                        'subject_id' => $subjectId
                    ]);

                    $message = "Subject updated successfully.";
                    $activeTab = 'subjects';
                    break;

                case 'delete_subject':
                    // Delete subject if no classes use it
                    $subjectId = (int)$_POST['subject_id'];

                    // Check if subject has classes
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classes WHERE subject_id = :subject_id");
                    $stmt->execute(['subject_id' => $subjectId]);

                    if ($stmt->fetch()['count'] > 0) {
                        $error = 'Cannot delete: Subject has classes assigned to it.';
                        break;
                    }

                    // Delete subject
                    $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = :subject_id");
                    $stmt->execute(['subject_id' => $subjectId]);

                    $message = "Subject deleted successfully.";
                    $activeTab = 'subjects';
                    break;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Load term details for editing if term_id is in GET
if (isset($_GET['edit_term']) && is_numeric($_GET['edit_term'])) {
    $termId = (int)$_GET['edit_term'];
    $termDetails = getTermDetails($termId);
    $activeTab = 'terms';
}

// Load subject details for editing if subject_id is in GET
if (isset($_GET['edit_subject']) && is_numeric($_GET['edit_subject'])) {
    $subjectId = (int)$_GET['edit_subject'];
    $subjectDetails = getSubjectDetails($subjectId);
    $activeTab = 'subjects';
}

/**
 * Retrieves detailed information about a specific term
 *
 * @param int $termId The term ID to get details for
 * @return array|null Term details or null if not found
 */
function getTermDetails($termId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT term_id, name, start_date, end_date
        FROM terms
        WHERE term_id = :term_id
    ");
    $stmt->execute(['term_id' => $termId]);

    return $stmt->fetch();
}

/**
 * Retrieves detailed information about a specific subject
 *
 * @param int $subjectId The subject ID to get details for
 * @return array|null Subject details or null if not found
 */
function getSubjectDetails($subjectId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT subject_id, name
        FROM subjects
        WHERE subject_id = :subject_id
    ");
    $stmt->execute(['subject_id' => $subjectId]);

    return $stmt->fetch();
}

/**
 * Displays a table of all terms with management actions
 *
 * @return void
 */
function displayTermsList() {
    global $pdo;

    // Get all terms
    $stmt = $pdo->query("
        SELECT term_id, name, start_date, end_date
        FROM terms
        ORDER BY start_date DESC
    ");

    $terms = $stmt->fetchAll();
?>
    <div class="terms-list">
        <div class="table-wrapper">
            <table class="table">
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
                    <?php if (count($terms) > 0): ?>
                        <?php foreach ($terms as $term): ?>
                        <tr>
                            <td><?= htmlspecialchars($term['term_id']) ?></td>
                            <td><?= htmlspecialchars($term['name']) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d', strtotime($term['start_date']))) ?></td>
                            <td><?= htmlspecialchars(date('Y-m-d', strtotime($term['end_date']))) ?></td>
                            <td class="actions">
                                <a href="?tab=terms&edit_term=<?= $term['term_id'] ?>" class="btn btn-secondary btn-icon">Edit</a>
                                <button class="btn btn-error" onclick="confirmDeleteTerm(<?= $term['term_id'] ?>, '<?= htmlspecialchars($term['name']) ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No terms found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php
}

/**
 * Displays a table of all subjects with management actions
 *
 * @return void
 */
function displaySubjectsList() {
    global $pdo;

    // Get all subjects
    $stmt = $pdo->query("
        SELECT subject_id, name
        FROM subjects
        ORDER BY name"
    );

    $subjects = $stmt->fetchAll();
?>
    <div class="subjects-list">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($subjects) > 0): ?>
                        <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><?= htmlspecialchars($subject['subject_id']) ?></td>
                            <td><?= htmlspecialchars($subject['name']) ?></td>
                            <td class="actions">
                                <a href="?tab=subjects&edit_subject=<?= $subject['subject_id'] ?>" class="btn btn-secondary btn-icon">Edit</a>
                                <button class="btn btn-error" onclick="confirmDeleteSubject(<?= $subject['subject_id'] ?>, '<?= htmlspecialchars($subject['name']) ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No subjects found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}
?>

<main class="container">
    <h1>System Settings</h1>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div class="tab-header">
                <button class="tab-button <?= $activeTab === 'terms' ? 'active' : '' ?>" 
                       onclick="showTab('terms')">Academic Terms</button>
                <button class="tab-button <?= $activeTab === 'subjects' ? 'active' : '' ?>" 
                       onclick="showTab('subjects')">Subjects</button>
            </div>
        </div>

        <div class="card-body">
            <!-- Terms Tab -->
            <div id="terms" class="tab-content <?= $activeTab === 'terms' ? 'active' : '' ?>">
                <div class="section-header">
                    <h2>Academic Terms</h2>
                    <?php if (empty($termDetails)): ?>
                        <button class="btn btn-primary" onclick="showForm('term-form')">Add New Term</button>
                    <?php endif; ?>
                </div>

                <div id="term-form" class="form-section <?= !empty($termDetails) ? 'visible' : '' ?>">
                    <h3><?= empty($termDetails) ? 'Create New Term' : 'Edit Term' ?></h3>
                    <form method="post" action="/uwuweb/admin/settings.php?tab=terms">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="<?= empty($termDetails) ? 'create_term' : 'update_term' ?>">

                        <?php if (!empty($termDetails)): ?>
                            <input type="hidden" name="term_id" value="<?= $termDetails['term_id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="term_name" class="form-label">Term Name</label>
                            <input type="text" class="form-input" id="term_name" name="name"
                                   value="<?= htmlspecialchars($termDetails['name'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-input" id="start_date" name="start_date"
                                   value="<?= !empty($termDetails) ? date('Y-m-d', strtotime($termDetails['start_date'])) : '' ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-input" id="end_date" name="end_date"
                                   value="<?= !empty($termDetails) ? date('Y-m-d', strtotime($termDetails['end_date'])) : '' ?>" required>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <?= empty($termDetails) ? 'Create Term' : 'Update Term' ?>
                            </button>
                            <a href="/uwuweb/admin/settings.php?tab=terms" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="list-section <?= empty($termDetails) ? 'visible' : '' ?>">
                    <?php displayTermsList(); ?>
                </div>
            </div>

            <!-- Subjects Tab -->
            <div id="subjects" class="tab-content <?= $activeTab === 'subjects' ? 'active' : '' ?>">
                <div class="section-header">
                    <h2>Subjects</h2>
                    <?php if (empty($subjectDetails)): ?>
                        <button class="btn btn-primary" onclick="showForm('subject-form')">Add New Subject</button>
                    <?php endif; ?>
                </div>

                <div id="subject-form" class="form-section <?= !empty($subjectDetails) ? 'visible' : '' ?>">
                    <h3><?= empty($subjectDetails) ? 'Create New Subject' : 'Edit Subject' ?></h3>
                    <form method="post" action="/uwuweb/admin/settings.php?tab=subjects">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="<?= empty($subjectDetails) ? 'create_subject' : 'update_subject' ?>">

                        <?php if (!empty($subjectDetails)): ?>
                            <input type="hidden" name="subject_id" value="<?= $subjectDetails['subject_id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="subject_name" class="form-label">Subject Name</label>
                            <input type="text" class="form-input" id="subject_name" name="name"
                                   value="<?= htmlspecialchars($subjectDetails['name'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <?= empty($subjectDetails) ? 'Create Subject' : 'Update Subject' ?>
                            </button>
                            <a href="/uwuweb/admin/settings.php?tab=subjects" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="list-section <?= empty($subjectDetails) ? 'visible' : '' ?>">
                    <?php displaySubjectsList(); ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Delete Term Confirmation Modal -->
<div id="delete-term-modal" class="modal">
    <div class="modal-content card">
        <div class="card-header">
            <h2 class="card-title">Confirm Deletion</h2>
            <span class="close" onclick="closeModal('delete-term-modal')">&times;</span>
        </div>
        <div class="card-body">
            <form id="delete-term-form" method="post" action="/uwuweb/admin/settings.php?tab=terms">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="delete_term">
                <input type="hidden" name="term_id" id="delete-term-id">
    
                <p>Are you sure you want to delete term: <span id="delete-term-name" class="badge badge-primary"></span>?</p>
                <p class="alert alert-error">Warning: This action cannot be undone!</p>
    
                <div class="form-group">
                    <button type="submit" class="btn btn-error">Delete Term</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('delete-term-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Subject Confirmation Modal -->
<div id="delete-subject-modal" class="modal">
    <div class="modal-content card">
        <div class="card-header">
            <h2 class="card-title">Confirm Deletion</h2>
            <span class="close" onclick="closeModal('delete-subject-modal')">&times;</span>
        </div>
        <div class="card-body">
            <form id="delete-subject-form" method="post" action="/uwuweb/admin/settings.php?tab=subjects">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="delete_subject">
                <input type="hidden" name="subject_id" id="delete-subject-id">
    
                <p>Are you sure you want to delete subject: <span id="delete-subject-name" class="badge badge-primary"></span>?</p>
                <p class="alert alert-error">Warning: This action cannot be undone!</p>
    
                <div class="form-group">
                    <button type="submit" class="btn btn-error">Delete Subject</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('delete-subject-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.getElementById(tabId).classList.add('active');

    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });
    event.target.classList.add('active');

    // Update URL with tab parameter
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url);
}

function showForm(formId) {
    document.getElementById(formId).classList.add('visible');
}

function confirmDeleteTerm(termId, termName) {
    document.getElementById('delete-term-id').value = termId;
    document.getElementById('delete-term-name').textContent = termName;
    document.getElementById('delete-term-modal').display = 'block';
}

function confirmDeleteSubject(subjectId, subjectName) {
    document.getElementById('delete-subject-id').value = subjectId;
    document.getElementById('delete-subject-name').textContent = subjectName;
    document.getElementById('delete-subject-modal').display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).display = 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>
