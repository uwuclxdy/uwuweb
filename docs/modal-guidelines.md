# Modal Implementation Guidelines for uwuweb

This document provides standardized guidelines for implementing modals (popup dialogs) across the uwuweb application.

## 1. HTML Structure

Use the following HTML structure for all modals:

```html

<div class="modal" id="uniqueModalId">
   <div class="modal-overlay" aria-hidden="true"></div>
   <div class="modal-container" role="dialog" aria-modal="true" aria-labelledby="uniqueModalTitleId">
        <div class="modal-header">
           <h3 class="modal-title" id="uniqueModalTitleId">Modal Title</h3>
           <button class="btn-close" aria-label="Close modal" data-close-modal>×</button>
        </div>
      <!-- For forms: -->
      <form id="uniqueFormId" method="POST" action="targetPage.php">
         <div class="modal-body">
            <!-- Form content goes here -->
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="action_type" value="specific_action">
            <!-- Additional form fields -->
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Submit</button>
         </div>
      </form>
      <!-- For non-forms: -->
      <!--
      <div class="modal-body">
          Content goes here
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
          <button type="button" class="btn btn-primary" id="confirmButton">Confirm</button>
      </div>
      -->
    </div>
</div>
```

## 2. CSS Classes

Use these standardized CSS classes from the uwuweb stylesheet:

- `.modal`: The main modal container
- `.modal-overlay`: Semi-transparent background overlay
- `.modal-container`: Container for modal content
- `.modal-header`: Contains the title and close button
- `.modal-title`: Modal title text
- `.modal-body`: Main content area
- `.modal-footer`: Action buttons area
- `.modal.open`: Applied to show the modal (added via JavaScript)

## 3. JavaScript Implementation

Include this standard JavaScript in your page to handle modals:

```javascript
document.addEventListener('DOMContentLoaded', function () {
   // --- Modal Management Functions ---
   const openModal = (modalId) => {
      const modal = document.getElementById(modalId);
      if (modal) {
         modal.classList.add('open');
         // Focus the first focusable element
         const firstFocusable = modal.querySelector('button, [href], input, select, textarea');
         if (firstFocusable) firstFocusable.focus();
      }
   };

   const closeModal = (modal) => {
      if (typeof modal === 'string') {
         modal = document.getElementById(modal);
      }

      if (modal) {
         modal.classList.remove('open');
         // Reset forms if present
         const form = modal.querySelector('form');
         if (form) form.reset();

         // Clear any error messages
         const errorMsgs = modal.querySelectorAll('.feedback-error');
         errorMsgs.forEach(msg => {
            if (msg && msg.style) {
               msg.style.display = 'none';
            }
         });
      }
   };

   // --- Event Listeners ---

   // Open modal buttons
   document.querySelectorAll('[data-open-modal]').forEach(btn => {
      btn.addEventListener('click', function () {
         const modalId = this.dataset.openModal;
         openModal(modalId);

         // If the button has additional data attributes, process them
         // Example: data-id, data-name, etc.
         const dataId = this.dataset.id;
         const dataName = this.dataset.name;

         if (dataId) {
            // Handle ID data (e.g., fill hidden form field)
            const idField = document.getElementById(`${modalId}_id`);
            if (idField) idField.value = dataId;
         }

         if (dataName) {
            // Handle name data (e.g., show in confirmation text)
            const nameDisplay = document.getElementById(`${modalId}_name`);
            if (nameDisplay) nameDisplay.textContent = dataName;
         }
      });
   });

   // Close modal buttons
   document.querySelectorAll('[data-close-modal]').forEach(btn => {
      btn.addEventListener('click', function () {
         closeModal(this.closest('.modal'));
      });
   });

   // Close modals when clicking the overlay
   document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', function () {
         closeModal(this.closest('.modal'));
      });
   });

   // Close modals with Escape key
   document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
         document.querySelectorAll('.modal.open').forEach(modal => {
            closeModal(modal);
         });
      }
   });
});
```

## 4. Modal Types and Usage Patterns

### 4.1 Create/Add Modal

For adding new items:

```html

<button data-open-modal="createItemModal" class="btn btn-primary">
   <span class="btn-icon"><!-- SVG icon --></span>
   Create New Item
</button>

<div class="modal" id="createItemModal">
   <!-- Standard modal structure -->
   <form method="POST" action="current-page.php">
      <!-- Form fields -->
      <input type="hidden" name="create_item" value="1">
   </form>
</div>
```

### 4.2 Edit Modal

For editing existing items:

```html

<button data-open-modal="editItemModal" data-id="123" class="btn btn-secondary">Edit</button>

<div class="modal" id="editItemModal">
   <!-- Standard modal structure -->
   <form method="POST" action="current-page.php">
      <input type="hidden" id="editItemModal_id" name="item_id" value="">
      <input type="hidden" name="update_item" value="1">
      <!-- Form fields -->
   </form>
</div>
```

### 4.3 Confirmation Modal

For confirming actions like deletion:

```html

<button data-open-modal="deleteItemModal" data-id="123" data-name="Item Name" class="btn btn-error">Delete</button>

<div class="modal" id="deleteItemModal">
   <!-- Standard modal structure -->
   <div class="modal-body">
      <div class="alert status-warning mb-md">
         <p>Are you sure you want to delete <strong id="deleteConfirmModal_name">this item</strong>?</p>
      </div>
      <div class="alert status-error font-bold">
         <p>This action cannot be undone.</p>
      </div>
   </div>
   <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
      <button type="button" class="btn btn-error" id="confirmDeleteBtn">Delete</button>
   </div>
</div>

<script>
   // Add this after the standard modal JS
   document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
      const itemId = document.getElementById('deleteItemModal_id').textContent;

      // Either submit a form:
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'current-page.php';

      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = 'csrf_token';
      csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';

      const actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'delete_item';
      actionInput.value = '1';

      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'item_id';
      idInput.value = itemId;

      form.appendChild(csrfInput);
      form.appendChild(actionInput);
      form.appendChild(idInput);
      document.body.appendChild(form);
      form.submit();

      // Or use fetch for AJAX:
      /*
      fetch('api/items.php', {
          method: 'POST',
          headers: {
              'Content-Type': 'application/json'
          },
          body: JSON.stringify({
              action: 'delete',
              csrf_token: '<?= htmlspecialchars($csrfToken) ?>',
              item_id: itemId
          })
      })
      .then(response => response.json())
      .then(data => {
          if (data.success) {
              // Handle success (e.g., remove item from DOM, show message)
              closeModal('deleteItemModal');
          } else {
              // Handle error
              alert(data.message || 'Error deleting item');
          }
      });
      */
   });
</script>
```

## 5. Best Practices

1. **Use Semantic IDs**: Name modals and their components descriptively (e.g., `createUserModal`, `editGradeModal`).

2. **Data Attributes**: Use `data-*` attributes to pass information to modals:
   - `data-open-modal="modalId"` for triggering buttons
   - `data-id`, `data-name`, etc. for entity-specific information

3. **Accessibility**:
   - Include proper ARIA attributes (`aria-modal`, `aria-labelledby`)
   - Ensure keyboard navigation works (focus management)
   - Provide visible focus states

4. **Form Validation**:
   - Use HTML5 validation attributes (`required`, `pattern`, etc.)
   - Add custom validation with visual feedback (`.is-valid`, `.is-invalid` classes)

5. **Error Handling**:
   - Display validation errors close to the relevant fields
   - Show a general error message at the top for server-side errors

6. **Reuse Existing Functions**:
   - Always check `functions.php` and role-specific function files before implementing new functionality
   - Use established helpers like `sendJsonErrorResponse()`, `validateDate()`, etc.

7. **CSS Transitions**:
   - The stylesheet includes modal animations - no additional CSS needed

## 6. Delete Confirmation Pattern

For delete confirmations, follow this simpler pattern instead of requiring users to type "DELETE":

```html

<button data-open-modal="deleteConfirmModal" data-id="123" data-name="Item Name" class="btn btn-error btn-sm">Delete
</button>

<div class="modal" id="deleteConfirmModal">
   <div class="modal-overlay" aria-hidden="true"></div>
   <div class="modal-container" role="dialog" aria-modal="true">
      <div class="modal-header">
         <h3 class="modal-title">Potrditev izbrisa</h3>
         <button class="btn-close" aria-label="Close modal" data-close-modal>×</button>
      </div>
      <div class="modal-body">
         <div class="alert status-warning mb-md">
            <p>Ali ste prepričani, da želite izbrisati <strong id="deleteConfirmModal_name">ta element</strong>?</p>
         </div>
         <div class="alert status-error font-bold">
            <p>Tega dejanja ni mogoče razveljaviti.</p>
         </div>
         <input type="hidden" id="deleteConfirmModal_id" value="">
      </div>
      <div class="modal-footer">
         <button type="button" class="btn btn-secondary" data-close-modal>Prekliči</button>
         <button type="button" class="btn btn-error" id="confirmDeleteBtn">Izbriši</button>
      </div>
   </div>
</div>

<script>
   document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
      const itemId = document.getElementById('deleteConfirmModal_id').value;

      // Create and submit form
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = window.location.href;

      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = 'csrf_token';
      csrfInput.value = '<?= htmlspecialchars($csrfToken) ?>';

      const actionInput = document.createElement('input');
      actionInput.type = 'hidden';
      actionInput.name = 'delete_item';
      actionInput.value = '1';

      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'item_id';
      idInput.value = itemId;

      form.appendChild(csrfInput);
      form.appendChild(actionInput);
      form.appendChild(idInput);
      document.body.appendChild(form);
      form.submit();
   });
</script>
```

## 7. Example: Adding Grade Item Modal

```html

<button data-open-modal="addGradeItemModal" class="btn btn-primary">Add Grade Item</button>

<div class="modal" id="addGradeItemModal">
   <div class="modal-overlay" aria-hidden="true"></div>
   <div class="modal-container" role="dialog" aria-modal="true">
      <div class="modal-header">
         <h3 class="modal-title">Add New Grade Item</h3>
         <button class="btn-close" aria-label="Close modal" data-close-modal>×</button>
      </div>
      <form id="addGradeItemForm" method="POST">
         <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="class_subject_id" value="<?= $classSubjectId ?>">

            <div class="form-group">
               <label class="form-label" for="grade_item_name">Name:</label>
               <input type="text" id="grade_item_name" name="name" class="form-input" required>
            </div>

            <div class="row">
               <div class="col col-md-6">
                  <div class="form-group">
                     <label class="form-label" for="grade_item_max_points">Maximum Points:</label>
                     <input type="number" id="grade_item_max_points" name="max_points" class="form-input" required
                            min="1" step="0.01">
                  </div>
               </div>
               <div class="col col-md-6">
                  <div class="form-group">
                     <label class="form-label" for="grade_item_weight">Weight:</label>
                     <input type="number" id="grade_item_weight" name="weight" class="form-input" value="1.00"
                            min="0.01" max="3.00" step="0.01">
                  </div>
               </div>
            </div>
         </div>
         <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Cancel</button>
            <button type="submit" class="btn btn-primary">Add Item</button>
         </div>
      </form>
   </div>
</div>

<script>
   document.getElementById('addGradeItemForm').addEventListener('submit', function (e) {
      e.preventDefault();

      // Use the existing addGradeItem function from the API
      const formData = new FormData(this);

      fetch('api/grades.php', {
         method: 'POST',
         body: formData
      })
              .then(response => response.json())
              .then(data => {
                 if (data.success) {
                    // Show success message and reload or update UI
                    location.reload();
                 } else {
                    // Show error message
                    alert(data.message || 'Error adding grade item');
                 }
              })
              .catch(error => {
                 console.error('Error:', error);
              });
   });
</script>
```
