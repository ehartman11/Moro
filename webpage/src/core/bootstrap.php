<?php
declare(strict_types=1);

$loadEnv = static function (string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
};

$loadEnv(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env');
$loadEnv(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');

$debugValue = getenv('MORO_APP_DEBUG');
if ($debugValue === false || $debugValue === '') {
    $debugValue = '0';
}

define('APP_DEBUG', filter_var($debugValue, FILTER_VALIDATE_BOOL));

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

/**
 * Moro application bootstrap
 *
 * Responsibilities:
 * - Start session
 * - Register autoloader for src/*
 * - Centralize request initialization
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Minimal PSR-4–style autoloader for Moro\
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Moro\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath  = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    $baseDir = __DIR__ . '/../';
    $file = $baseDir . $relativePath;

    if (!file_exists($file) && str_starts_with($relativePath, 'Core' . DIRECTORY_SEPARATOR)) {
        $file = $baseDir . 'core' . DIRECTORY_SEPARATOR . substr($relativePath, strlen('Core' . DIRECTORY_SEPARATOR));
    }

    if (file_exists($file)) {
        require_once $file;
    }
});
