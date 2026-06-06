<?php
/**
 * Database connection bootstrap.
 *
 * Responsibilities:
 * - Centralizes PDO creation and configuration.
 * - Ensures UTF-8 (utf8mb4) support for full Unicode compatibility.
 * - Sets consistent error and fetch behavior across the application.
 *
 * NOTE:
 * - This file is expected to be included once per request via require_once.
 * - Credentials are loaded from environment variables or ignored local .env files.
 */

require_once __DIR__ . '/src/core/Config.php';
require_once __DIR__ . '/src/core/Db.php';

$pdo = \Moro\Core\Db::pdo();
