<?php
/**
 * Application path configuration.
 *
 * Responsibilities:
 * - Defines the base URL segment used for building internal links and redirects.
 *
 * Assumptions:
 * - The application is hosted under a subdirectory (e.g. /webpage).
 * - All path generation should reference BASE_URL to avoid hard-coding paths.
 */
define('BASE_URL', '/webpage');
