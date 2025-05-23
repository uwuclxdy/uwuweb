/**
 * uwuweb - Grade Management System
 * Main JavaScript file
 *
 * Provides common functionality across all pages
 */

document.addEventListener('DOMContentLoaded', function () {
    // Mobile navigation toggle
    initMobileNavigation();

    // Initialize modals
    initModals();

    // Initialize alerts auto-hide
    initAlerts();

    // Initialize tab navigation
    initTabs();

    // Form validation
    initFormValidation();

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

    // Tab switching functionality - shared across teacher pages
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            // Remove active class from all tabs
            document.querySelectorAll('.tab-btn').forEach(function (b) {
                b.classList.remove('active');
            });
            document.querySelectorAll('.tab-content').forEach(function (c) {
                c.classList.remove('active');
            });

            // Add active class to clicked tab and corresponding content
            this.classList.add('active');
            document.getElementById(this.dataset.tab).classList.add('active');
        });
    });

    // Special case for delete confirmation modal in attendance.php
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            const itemId = document.getElementById('deletePeriodModal_id').value;

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = document.querySelector('input[name="csrf_token"]').value;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'delete_period';
            actionInput.value = '1';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'period_id';
            idInput.value = itemId;

            form.appendChild(csrfInput);
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        });
    }

    // Alert auto-dismissal for non-error alerts
    const createAlert = (message, type = 'info', container = '.alert-container', autoRemove = true, duration = 5000) => {
        const alertElement = document.createElement('div');
        alertElement.className = `alert status-${type} card-entrance`;

        const iconElement = document.createElement('div');
        iconElement.className = 'alert-icon';

        // Use appropriate icon based on type
        let iconContent;
        switch (type) {
            case 'success':
                iconContent = '✓';
                break;
            case 'warning':
                iconContent = '⚠';
                break;
            case 'error':
                iconContent = '✕';
                break;
            case 'info':
            default:
                iconContent = 'ℹ';
                break;
        }
        iconElement.innerHTML = iconContent;

        const contentElement = document.createElement('div');
        contentElement.className = 'alert-content';
        contentElement.innerHTML = message;

        // Assemble alert
        alertElement.appendChild(iconElement);
        alertElement.appendChild(contentElement);

        // Insert into DOM
        const containerElement = document.querySelector(container);
        if (containerElement) {
            containerElement.appendChild(alertElement);
        } else {
            // If no container exists, create one
            const newContainer = document.createElement('div');
            newContainer.className = 'alert-container';
            document.querySelector('.card').after(newContainer);
            newContainer.appendChild(alertElement);
        }

        // Auto-remove if needed
        if (autoRemove && type !== 'error') {
            setTimeout(() => {
                alertElement.classList.add('closing');
                setTimeout(() => {
                    if (alertElement.parentNode) {
                        alertElement.parentNode.removeChild(alertElement);
                    }
                }, 300);
            }, duration);
        }

        return alertElement;
    };

    // Expose important functions globally
    window.modalUtils = {
        openModal,
        closeModal,
        createAlert
    };
});

/**
 * Initialize mobile navigation toggle functionality
 */
function initMobileNavigation() {
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function () {
            navMenu.classList.toggle('open');
        });

        // Close menu when clicking outside
        document.addEventListener('click', function (event) {
            if (!navToggle.contains(event.target) && !navMenu.contains(event.target)) {
                navMenu.classList.remove('open');
            }
        });
    }
}

/**
 * Initialize modals - this handles generic modal behavior
 * Specific modal actions are handled in their respective page scripts
 */
function initModals() {
    // Open modal triggers
    document.querySelectorAll('[data-open-modal]').forEach(trigger => {
        trigger.addEventListener('click', function () {
            const modalId = this.getAttribute('data-open-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('open');
            }
        });
    });

    // Generic modal functionality
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function () {
            // Find the parent modal
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('open');
            }
        });
    });

    // Close buttons
    document.querySelectorAll('.btn-close, [data-close-modal]').forEach(button => {
        button.addEventListener('click', function () {
            // Find the parent modal
            const modal = this.closest('.modal');
            if (modal) {
                modal.classList.remove('open');
            }
        });
    });

    // Cancel buttons
    document.querySelectorAll('.modal .btn-secondary').forEach(button => {
        if (button.textContent.trim() === 'Cancel') {
            button.addEventListener('click', function () {
                // Find the parent modal
                const modal = this.closest('.modal');
                if (modal) {
                    modal.classList.remove('open');
                }
            });
        }
    });

    // Escape key closes modal
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal.open').forEach(modal => {
                modal.classList.remove('open');
            });
        }
    });
}

/**
 * Initialize alerts to auto-hide after a delay
 */
function initAlerts() {
    const alerts = document.querySelectorAll('.alert');

    alerts.forEach(alert => {
        // Auto-hide success alerts after 5 seconds
        if (alert.classList.contains('status-success')) {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        }
    });
}

/**
 * Initialize tab navigation
 */
function initTabs() {
    const tabLinks = document.querySelectorAll('.tab-link');

    tabLinks.forEach(tabLink => {
        tabLink.addEventListener('click', function (e) {
            e.preventDefault();

            const tabId = this.getAttribute('data-tab');
            if (!tabId) return;

            // Remove active class from all tab links
            tabLinks.forEach(link => {
                link.classList.remove('active');
            });

            // Add active class to clicked tab link
            this.classList.add('active');

            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });

            // Show selected tab content
            const tabContent = document.getElementById(tabId);
            if (tabContent) {
                tabContent.style.display = 'block';
            }
        });
    });
}

/**
 * Initialize form validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        const requiredInputs = form.querySelectorAll('input[required], select[required], textarea[required]');

        form.addEventListener('submit', function (event) {
            let isValid = true;

            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('is-invalid');

                    // Add feedback message if it doesn't exist
                    let feedbackElement = input.nextElementSibling;
                    if (!feedbackElement || !feedbackElement.classList.contains('feedback-text')) {
                        feedbackElement = document.createElement('div');
                        feedbackElement.className = 'feedback-text feedback-invalid';
                        feedbackElement.textContent = 'This field is required';
                        input.parentNode.insertBefore(feedbackElement, input.nextSibling);
                    }
                } else {
                    input.classList.remove('is-invalid');

                    // Remove feedback message if it exists
                    const feedbackElement = input.nextElementSibling;
                    if (feedbackElement && feedbackElement.classList.contains('feedback-invalid')) {
                        feedbackElement.remove();
                    }
                }
            });

            if (!isValid) {
                event.preventDefault();
            }
        });

        // Remove validation styling when user starts typing
        requiredInputs.forEach(input => {
            input.addEventListener('input', function () {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');

                    // Remove feedback message if it exists
                    const feedbackElement = this.nextElementSibling;
                    if (feedbackElement && feedbackElement.classList.contains('feedback-invalid')) {
                        feedbackElement.remove();
                    }
                }
            });
        });
    });

    // Password confirmation validation
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    passwordInputs.forEach(input => {
        // Add validation for password length
        if (!input.id.includes('confirm')) {
            input.addEventListener('input', function () {
                const minLength = 6;

                if (this.value.length > 0 && this.value.length < minLength) {
                    this.classList.add('is-invalid');

                    // Check if we already have a feedback element
                    let feedbackElement = this.nextElementSibling;
                    while (feedbackElement && !feedbackElement.classList.contains('feedback-text')) {
                        feedbackElement = feedbackElement.nextElementSibling;
                    }

                    // Create feedback message if it doesn't exist
                    if (!feedbackElement || !feedbackElement.classList.contains('feedback-text')) {
                        feedbackElement = document.createElement('div');
                        feedbackElement.className = 'feedback-text feedback-invalid mt-xs';
                        feedbackElement.textContent = `Password must be at least ${minLength} characters`;

                        // Find the small hint element, if it exists, and insert after it
                        const smallHint = this.nextElementSibling;
                        if (smallHint && smallHint.tagName === 'SMALL') {
                            smallHint.parentNode.insertBefore(feedbackElement, smallHint.nextSibling);
                        } else {
                            this.parentNode.insertBefore(feedbackElement, this.nextSibling);
                        }
                    }
                } else {
                    this.classList.remove('is-invalid');

                    // Remove feedback message if it exists
                    const feedbackElements = this.parentNode.querySelectorAll('.feedback-invalid');
                    feedbackElements.forEach(element => {
                        if (element.textContent.includes('at least')) {
                            element.remove();
                        }
                    });
                }
            });
        }

        // Existing password match validation
        if (input.id.includes('confirm')) {
            const passwordField = document.getElementById(input.id.replace('confirm_', ''));

            if (passwordField) {
                input.addEventListener('input', function () {
                    if (this.value && this.value !== passwordField.value) {
                        this.classList.add('is-invalid');

                        // Add feedback message if it doesn't exist
                        let feedbackElement = this.nextElementSibling;
                        if (!feedbackElement || !feedbackElement.classList.contains('feedback-text')) {
                            feedbackElement = document.createElement('div');
                            feedbackElement.className = 'feedback-text feedback-invalid';
                            feedbackElement.textContent = 'Passwords do not match';
                            this.parentNode.insertBefore(feedbackElement, this.nextSibling);
                        }
                    } else {
                        this.classList.remove('is-invalid');

                        // Remove feedback message if it exists
                        const feedbackElement = this.nextElementSibling;
                        if (feedbackElement && feedbackElement.classList.contains('feedback-invalid')) {
                            feedbackElement.remove();
                        }
                    }
                });
            }
        }
    });
}

/**
 * Helper function to format dates consistently across the application
 * @param {string|Date} date - The date to format
 * @param {string} format - The format to use (default: 'dd.mm.yyyy')
 * @returns {string} - The formatted date string
 */
function formatDate(date, format = 'dd.mm.yyyy') {
    if (!date) return '';

    const d = new Date(date);
    if (isNaN(d.getTime())) return '';

    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();

    switch (format) {
        case 'dd.mm.yyyy':
            return `${day}.${month}.${year}`;
        case 'yyyy-mm-dd':
            return `${year}-${month}-${day}`;
        case 'mm/dd/yyyy':
            return `${month}/${day}/${year}`;
        default:
            return `${day}.${month}.${year}`;
    }
}

/**
 * Helper function to show a modal
 * @param {string} modalId - The ID of the modal to show
 */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

/**
 * Helper function to hide a modal
 * @param {string} modalId - The ID of the modal to hide
 */
function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Helper function to create a notification
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, warning, info)
 * @param {number} duration - How long to show the notification in milliseconds
 */
function showNotification(message, type = 'info', duration = 3000) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification status-${type}`;
    notification.textContent = message;

    // Style the notification
    notification.style.position = 'fixed';
    notification.style.bottom = '20px';
    notification.style.right = '20px';
    notification.style.padding = '15px 20px';
    notification.style.borderRadius = 'var(--button-radius)';
    notification.style.boxShadow = 'var(--card-shadow)';
    notification.style.zIndex = '9999';
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(20px)';
    notification.style.transition = 'opacity 0.3s, transform 0.3s';

    // Add to DOM
    document.body.appendChild(notification);

    // Trigger animation
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    }, 10);

    // Remove after duration
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(20px)';

        // Remove from DOM after animation
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, duration);
}
