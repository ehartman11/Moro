<?php
/**
 * Logout handler.
 *
 * Responsibilities:
 * - Clears all session data.
 * - Fully invalidates the current session.
 * - Regenerates the session ID to prevent fixation.
 * - Redirects the user back to the login page.
 */
session_start();

// Clear all session variables explicitly.
$_SESSION = [];

// Destroy the existing session data on the server.
session_destroy();

// Regenerate the session ID to ensure the old ID cannot be reused.
session_regenerate_id(true);

// Return the user to the login screen.
header("Location: login.php");
exit;
