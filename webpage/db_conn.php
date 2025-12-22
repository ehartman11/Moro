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
 * - Credentials should be externalized (env/config) outside of source control in production.
 */
$host = "localhost";
$dbname = "moro_db";
$username = "root";
$password = "";

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);

    // Throw exceptions on DB errors so failures are explicit and catchable.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Default to associative arrays for predictable, readable fetch results.
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Fail fast: without a DB connection, the app cannot function.
    die("Database connection failed: " . $e->getMessage());
}

