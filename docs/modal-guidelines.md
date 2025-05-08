# uwuweb Modal Implementation Guidelines

## Overview

Modals are used for creating, editing, and confirming actions without leaving the current page. All modals should follow
these guidelines to maintain consistency throughout the application.

## HTML Structure

```html

<div id="[action]-[entity]-modal" class="modal">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">[Action] [Entity]</h3>
            <button type="button" class="btn-close" aria-label="Zapri">&times;</button>
        </div>
        <div class="modal-body">
            <form id="[action]-[entity]-form" method="post">
                <!-- CSRF token -->
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Hidden fields (for edit modals) -->
                <input type="hidden" name="[entity]_id" id="[entity]_id">

                <!-- Form fields -->
                <div class="form-group">
                    <label for="field_name" class="form-label">Field Label</label>
                    <input type="text" id="field_name" name="field_name" class="form-input" required>
                </div>

                <!-- More form groups as needed -->
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close">Prekliči</button>
            <button type="submit" form="[action]-[entity]-form" class="btn btn-primary">Potrdi</button>
        </div>
    </div>
</div>
```

## CSS Implementation

The CSS is already defined in style.css with the following classes:

- `.modal`: Modal container (hidden by default)
- `.modal-overlay`: Semi-transparent background overlay
- `.modal-container`: Content container with fixed width
- `.modal-header`, `.modal-body`, `.modal-footer`: Modal sections
- `.modal-title`: Modal title styling
- `.modal.open`: Visible state
- `.modal.closing`: Closing animation state
- `.btn-close`: Close button styling

## JavaScript Implementation

Add the following JavaScript functions to your page or include them from main.js:

```javascript
// 1. Modal open function
function openModal(modalId, entityData = null) {
    const modal = document.getElementById(modalId);

    // Reset form if it exists
    const form = modal.querySelector('form');
    if (form) form.reset();

    // Fill form with data if editing
    if (entityData) {
        Object.keys(entityData).forEach(key => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) field.value = entityData[key];
        });
    }

    // Show modal with animation
    modal.classList.add('open');

    // Prevent page scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

// 2. Modal close function
function closeModal(modalId) {
    const modal = document.getElementById(modalId);

    // Add closing animation
    modal.classList.add('closing');

    // Remove classes after animation completes
    setTimeout(() => {
        modal.classList.remove('open', 'closing');
        document.body.style.overflow = '';
    }, 300); // Match the CSS transition duration
}

// 3. Setup modal event listeners
function setupModalListeners() {
    // Setup all modals on the page
    document.querySelectorAll('.modal').forEach(modal => {
        const modalId = modal.id;

        // Close on overlay click
        modal.querySelector('.modal-overlay').addEventListener('click', () => {
            closeModal(modalId);
        });

        // Close on close button click
        modal.querySelector('.btn-close').addEventListener('click', () => {
            closeModal(modalId);
        });

        // Close on cancel button click
        const cancelBtn = modal.querySelector('.modal-close');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                closeModal(modalId);
            });
        }

        // Form submission handling (using fetch API)
        const form = modal.querySelector('form');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action || window.location.href, {
                        method: form.method || 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Close modal
                        closeModal(modalId);

                        // Refresh data or show success message
                        if (typeof refreshDataTable === 'function') {
                            refreshDataTable();
                        }

                        // Show success alert
                        showAlert('success', result.message);
                    } else {
                        // Show error message
                        showAlert('error', result.message);
                    }
                } catch (error) {
                    console.error('Error submitting form:', error);
                    showAlert('error', 'Prišlo je do napake pri obdelavi zahteve.');
                }
            });
        }
    });
}

// 4. Helper function to show alerts
function showAlert(type, message) {
    const alertContainer = document.getElementById('alert-container') || createAlertContainer();

    const alert = document.createElement('div');
    alert.className = `alert status-${type}`;
    alert.innerHTML = `
    <div class="alert-icon"></div>
    <div class="alert-content">${message}</div>
  `;

    alertContainer.appendChild(alert);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

// Create alert container if it doesn't exist
function createAlertContainer() {
    const container = document.createElement('div');
    container.id = 'alert-container';
    container.className = 'alert-container';
    document.body.appendChild(container);
    return container;
}

// Initialize all modals when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    setupModalListeners();

    // Setup buttons that open modals
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.dataset.modal;
            const entityId = btn.dataset.id || null;

            if (entityId) {
                // For edit modals, fetch entity data
                fetchEntityData(entityId, modalId);
            } else {
                // For create modals, just open
                openModal(modalId);
            }
        });
    });
});

// 5. Fetch entity data for edit modals
async function fetchEntityData(entityId, modalId) {
    try {
        // Extract entity type from modal ID
        const entityType = modalId.split('-')[1]; // Assumes format: edit-[entity]-modal

        // API endpoint based on entity type
        const endpoint = `/api/${entityType}.php`;

        const response = await fetch(`${endpoint}?action=get&id=${entityId}`);
        const data = await response.json();

        if (data.success) {
            openModal(modalId, data.entity);
        } else {
            showAlert('error', data.message || 'Podatki niso na voljo.');
        }
    } catch (error) {
        console.error('Error fetching entity data:', error);
        showAlert('error', 'Prišlo je do napake pri pridobivanju podatkov.');
    }
}
```

## PHP Implementation

On the server-side, implement these handler functions:

```php
/**
 * Handles AJAX request to get entity by ID
 * 
 * @return void Sends JSON response
 */
function handleGetEntity(): void {
    // Validate request
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        sendJsonErrorResponse('Neveljaven ID', 400, 'handleGetEntity');
    }
    
    $entityId = (int)$_GET['id'];
    
    // Get entity data based on current file/context
    // Example for user:
    $entity = getUserDetails($entityId);
    
    if (!$entity) {
        sendJsonErrorResponse('Entiteta ne obstaja', 404, 'handleGetEntity');
    }
    
    // Send success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'entity' => $entity
    ]);
    exit;
}

/**
 * Handles AJAX request to create or update entity
 * 
 * @return void Sends JSON response
 */
function handleSaveEntity(): void {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        sendJsonErrorResponse('Neveljaven varnostni žeton', 403, 'handleSaveEntity');
    }
    
    // Prepare data array from POST
    $data = []; // Populate from $_POST
    
    // Determine if create or update
    $entityId = isset($_POST['entity_id']) && !empty($_POST['entity_id']) 
        ? (int)$_POST['entity_id'] 
        : null;
    
    if ($entityId) {
        // Update existing entity
        $success = updateEntity($entityId, $data);
        $message = 'Uspešno posodobljeno.';
    } else {
        // Create new entity
        $result = createEntity($data);
        $success = $result !== false;
        $message = 'Uspešno ustvarjeno.';
    }
    
    // Send response
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Napaka pri shranjevanju.']);
    }
    exit;
}
```

## Integration Example

To implement a "Create User" button and modal:

```html
<!-- Button to open modal -->
<button type="button" class="btn btn-primary" data-modal="create-user-modal">
    <i class="icon-plus"></i> Dodaj uporabnika
</button>

<!-- Edit buttons in a data table -->
<button type="button" class="btn btn-sm" data-modal="edit-user-modal" data-id="<?php echo $user['user_id']; ?>">
    <i class="icon-edit"></i> Uredi
</button>
```

## Usage Guidelines

1. **Naming Convention**:
    - Modal IDs should follow the pattern `[action]-[entity]-modal` (e.g., `create-user-modal`, `edit-class-modal`)
    - Form IDs should follow the pattern `[action]-[entity]-form`

2. **Required Attributes**:
    - Add `data-modal="modal-id"` to buttons that open modals
    - Add `data-id="entity-id"` to edit buttons

3. **Form Submission**:
    - All forms should be submitted via AJAX using the provided JavaScript
    - Server responses should be JSON with `success` boolean and `message` string

4. **Animation**:
    - Use the provided CSS classes for animations
    - Match JavaScript timeouts with CSS transition durations

5. **Accessibility**:
    - Include proper ARIA labels on close buttons
    - Ensure focus management within the modal
    - Trap focus in modal while open
