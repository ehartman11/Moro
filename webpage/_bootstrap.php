<?php
declare(strict_types=1);

/**
 * Application path constants.
 *
 * Responsibilities:
 * - Defines the absolute filesystem root for server-side includes.
 * - Defines the public URL base used for building links and redirects.
 *
 * Assumptions:
 * - The application is deployed under a subdirectory (e.g. /webpage).
 * - APP_ROOT is always resolved relative to this file's location.
 */

// Filesystem root for includes (server path)
define('APP_ROOT', __DIR__);

// URL base for links/redirects (browser path)
define('APP_URL', '/webpage');
