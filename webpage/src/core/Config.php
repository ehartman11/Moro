<?php
declare(strict_types=1);

namespace Moro\Core;

/**
 * Application configuration
 *
 * NOTE:
 * - Values can be loaded from env vars or ignored local .env files
 * - Code outside Core should NEVER define its own base URLs or DB creds
 */
final class Config
{
    /** Browser base path */
    public const BASE_URL = '/webpage';

    /** Database configuration */
    public const DB_HOST = '';
    public const DB_NAME = '';
    public const DB_USER = '';
    public const DB_PASS = '';

    private static bool $envLoaded = false;

    private static function env(string $key, ?string $default = null): ?string
    {
        self::loadLocalEnv();

        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }
        return $value;
    }

    private static function loadLocalEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }

        self::$envLoaded = true;
        $paths = [
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env',
            dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env',
        ];

        foreach ($paths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
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
        }
    }

    public static function appEnv(): string
    {
        return strtolower((string)(self::env('MORO_APP_ENV', 'local') ?? 'local'));
    }

    public static function isProduction(): bool
    {
        return self::appEnv() === 'production';
    }

    public static function relaxedPortalRoles(): bool
    {
        if (self::isProduction()) {
            return false;
        }

        $value = self::env('MORO_RELAXED_PORTAL_ROLES', '1');
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function baseUrl(): string
    {
        $fromEnv = self::env('MORO_BASE_URL', null);
        if ($fromEnv !== null) {
            return self::normalizeBaseUrl($fromEnv);
        }

        $fromServer = self::detectBaseUrlFromScriptName();
        if ($fromServer !== null) {
            return $fromServer;
        }

        return self::normalizeBaseUrl(self::BASE_URL);
    }

    private static function detectBaseUrlFromScriptName(): ?string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;
        if (!is_string($scriptName) || $scriptName === '') {
            return null;
        }

        $marker = '/public/';
        $pos = strpos($scriptName, $marker);
        if ($pos === false) {
            return null;
        }

        $base = substr($scriptName, 0, $pos);
        return self::normalizeBaseUrl($base);
    }

    private static function normalizeBaseUrl(string $base): string
    {
        $base = trim($base);
        if ($base === '' || $base === '/') {
            return '';
        }

        if (!str_starts_with($base, '/')) {
            $base = '/' . $base;
        }

        return rtrim($base, '/');
    }

    public static function dbHost(): string
    {
        return self::required('MORO_DB_HOST', self::DB_HOST);
    }

    public static function dbName(): string
    {
        return self::required('MORO_DB_NAME', self::DB_NAME);
    }

    public static function dbUser(): string
    {
        return self::required('MORO_DB_USER', self::DB_USER);
    }

    public static function dbPass(): string
    {
        return self::env('MORO_DB_PASS', self::DB_PASS) ?? self::DB_PASS;
    }

    private static function required(string $key, string $default): string
    {
        $value = self::env($key, $default);
        if ($value === null || $value === '') {
            throw new \RuntimeException($key . ' is not configured.');
        }

        return $value;
    }
}
