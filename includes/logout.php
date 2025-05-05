<?php
/**
 * Logout Handler
 *
 * Terminates user session and redirects to login page
 *
 */

include_once 'auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) session_start();

// Destroy session
destroySession();
