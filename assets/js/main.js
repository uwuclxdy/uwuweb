/**
 * uwuweb - Grade Management System
 * Main JavaScript file
 * 
 * Contains client-side functionality for enhanced user experience
 * Uses vanilla JavaScript without external dependencies
 */

document.addEventListener('DOMContentLoaded', function() {
    // Add active class to current navigation item
    highlightCurrentNavItem();
    
    // Initialize mobile menu toggle
    initMobileMenuToggle();
    
    // Initialize responsive tables
    initResponsiveTables();
});

/**
 * Highlights the current navigation item based on URL path
 */
function highlightCurrentNavItem() {
    const currentPath = window.location.pathname;
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const href = item.getAttribute('href');
        if (href === currentPath) {
            item.classList.add('active');
        }
    });
}

/**
 * Shows a notification message
 * @param {string} message - The message to display
 * @param {string} type - The message type (success, error)
 */
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove notification after 5 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 500);
    }, 5000);
}

/**
 * Initializes the mobile menu toggle functionality
 */
function initMobileMenuToggle() {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mainNav = document.querySelector('.main-nav');
    
    if (mobileMenuToggle && mainNav) {
        mobileMenuToggle.addEventListener('click', function() {
            mainNav.classList.toggle('active');
            
            // Toggle aria-expanded attribute for accessibility
            const expanded = mainNav.classList.contains('active');
            mobileMenuToggle.setAttribute('aria-expanded', expanded);
        });
    }
}

/**
 * Initializes responsive tables with data-label attributes for mobile view
 */
function initResponsiveTables() {
    const tables = document.querySelectorAll('.responsive-table');
    
    tables.forEach(table => {
        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
        const cells = table.querySelectorAll('tbody td');
        
        cells.forEach((cell, index) => {
            // Add data-label attribute with corresponding header text
            const headerIndex = index % headers.length;
            cell.setAttribute('data-label', headers[headerIndex]);
        });
        
        // Add card-view class to the parent element for mobile layout
        if (table.parentElement) {
            table.parentElement.classList.add('card-view');
        }
    });
}

/**
 * Ensures forms are responsive and accessible
 * Adds required attributes and validation feedback
 * @param {HTMLFormElement} form - The form to enhance
 */
function enhanceForm(form) {
    const inputs = form.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        const formGroup = input.closest('.form-group');
        if (formGroup) {
            const label = formGroup.querySelector('label');
            
            // Connect label and input with matching id/for attributes
            if (label && !label.getAttribute('for') && input.id) {
                label.setAttribute('for', input.id);
            }
            
            // Add validation feedback
            input.addEventListener('invalid', function() {
                formGroup.classList.add('has-error');
            });
            
            input.addEventListener('input', function() {
                formGroup.classList.remove('has-error');
            });
        }
    });
}