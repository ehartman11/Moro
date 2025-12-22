<?php
/**
 * Auth + context guard for authenticated pages.
 *
 * Responsibilities:
 * - Loads bootstrap constants (APP_ROOT / APP_URL) and establishes session state.
 * - Ensures the user is logged in and has an active home selected.
 * - Resolves the user's role for the active home (owner/viewer) for authorization decisions.
 * - Exposes a small authorization helper (require_owner) for write-protected actions.
 *
 * Assumptions:
 * - home_permissions is the source of truth for per-home access.
 * - Pages including this file will use $activeHomeId / $userRoleOnHome for scoping and gating.
 */
require_once __DIR__ . "/../_bootstrap.php";
session_start();

require_once APP_ROOT . "/db_conn.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: " . APP_URL . "/login.php");
    exit;
}

if (!isset($_SESSION["active_home_id"])) {
    header("Location: " . APP_URL . "/homes.php");
    exit;
}

$userId       = (int)$_SESSION["user_id"];
$activeHomeId = (int)$_SESSION["active_home_id"];

// Determine role on active home (used for tab access + write permissions).
$stmtPerm = $pdo->prepare("
    SELECT role
    FROM home_permissions
    WHERE user_id = :uid AND home_id = :hid
    LIMIT 1
");
$stmtPerm->execute([':uid' => $userId, ':hid' => $activeHomeId]);
$userRoleOnHome = $stmtPerm->fetchColumn() ?: 'viewer';

function require_owner(string $returnTo): void {
    // Centralized guard for owner-only actions; redirects with a stable error code.
    global $userRoleOnHome;
    if ($userRoleOnHome !== 'owner') {
        header("Location: " . $returnTo . "&err=unauthorized");
        exit;
    }
}

