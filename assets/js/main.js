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