<?php
/**
 * Common Header File
 *
 * Contains common HTML header structure included in all pages
 * Includes necessary CSS and initializes session
 *
 * Functions:
 * - None (template file)
 */

// Ensure auth.php is included for session management
require_once 'auth.php';

// Get current user's role if logged in
$currentRole = getUserRole();
$roleName = $currentRole ? getRoleName($currentRole) : 'Guest';
?>
<?php /* 
    [HEADER HTML PLACEHOLDER]
    Components:
    - HTML doctype and opening tags
    - Head section with meta tags
    - Title "uwuweb - Grade Management System"
    - CSS stylesheet reference
    
    - Main navigation header with:
      - Site logo/title "uwuweb"
      - Mobile navigation toggle (only when logged in)
      - User info section showing:
        - Username
        - Role badge
        - Logout button
    
    - Container for main content
    - Error alert display when URL has error parameter
*/ ?>
<?php if (isset($_GET['error'])): 
    $errorMsg = match($_GET['error']) {
        'unauthorized' => 'You are not authorized to access that resource.',
        'invalid_csrf' => 'Security token mismatch. Please try again.',
        default => 'An error occurred.',
    };
?>
    <?php /* [ERROR ALERT PLACEHOLDER] - Displays error message from $errorMsg */ ?>
<?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_GET['success']) ?>
            </div>
        <?php endif; ?>
