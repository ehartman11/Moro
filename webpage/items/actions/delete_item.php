<?php
/**
 * Item delete action (owner-only).
 *
 * Responsibilities:
 * - Requires authenticated user + active home context (_auth.php).
 * - Enforces owner role before allowing deletion.
 * - Deletes the item scoped to the active home to prevent cross-home deletes.
 * - Redirects back with stable success/error flags for UI flash messaging.
 *
 * Assumptions:
 * - Associated maintenance tasks/history are either cascade-deleted at the DB level,
 *   or handled elsewhere in the application logic.
 */
require_once __DIR__ . "/../_auth.php";

$returnTo = $_POST['return_to'] ?? (APP_URL . "/items/index.php?tab=details");

require_owner($returnTo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}
if (!isset($_POST['delete_item'])) {
    header("Location: " . $returnTo . "&err=bad_request");
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: " . $returnTo . "&err=delete_invalid");
    exit;
}

try {
    $stmtDel = $pdo->prepare("
        DELETE FROM items
        WHERE id = :id AND home_id = :home_id
        LIMIT 1
    ");
    $stmtDel->execute([':id' => $id, ':home_id' => $activeHomeId]);

    header("Location: " . APP_URL . "/items/index.php?deleted=1&tab=details");
    exit;

} catch (PDOException $e) {
    header("Location: " . $returnTo . "&err=db_delete_item");
    exit;
}
