<?php
declare(strict_types=1);

namespace Moro\Core;

use PDO;
use PDOException;

/**
 * Database factory
 *
 * Responsibilities:
 * - Create and reuse PDO
 * - Enforce consistent error + fetch behavior
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                Config::dbHost(),
                Config::dbName()
            );

            self::$pdo = new PDO($dsn, Config::dbUser(), Config::dbPass(), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException | \RuntimeException $e) {
            // Fail fast — app cannot function without DB
            die('Database connection failed: ' . $e->getMessage());
        }

        return self::$pdo;
    }
}
