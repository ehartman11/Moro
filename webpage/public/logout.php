<?php
declare(strict_types=1);

/**
 * Logout handler.
 *
 * Responsibilities:
 * - Clears all session data.
 * - Fully invalidates the current session.
 * - Regenerates the session ID to prevent fixation.
 * - Redirects the user back to the login page.
 */

require_once __DIR__ . '/../src/core/bootstrap.php';

use Moro\Core\Response;
use Moro\Core\Paths;

// Ensure session exists (bootstrap should already do this, but safe-guarded)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Destroy session data
session_destroy();

// Regenerate session ID so the old one cannot be reused
session_regenerate_id(true);

// Redirect to login
Response::redirectToUrl(Paths::baseUrl() . '/public/login.php');
