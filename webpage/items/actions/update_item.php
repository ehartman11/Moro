<?php
/**
 * Item update action (owner-only).
 *
 * Responsibilities:
 * - Requires authenticated user + active home context (_auth.php).
 * - Enforces owner role before allowing any update.
 * - Validates required fields (id, name, category).
 * - Updates the item scoped to the active home to prevent cross-home edits.
 * - Redirects back with stable success/error flags for UI flash messaging.
 */
require_once __DIR__ . "/../_auth.php";

$returnTo = $_POST['return_to'] ?? (APP_URL . "/items/index.php?tab=details");

require_owner($returnTo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}
if (!isset($_POST['update_item'])) {
    header("Location: " . $returnTo . "&err=bad_request");
    exit;
}

$id            = (int)($_POST['id'] ?? 0);
$name          = trim($_POST['name'] ?? "");
$category      = trim($_POST['category'] ?? "");
$brand         = trim($_POST['brand'] ?? "");
$model         = trim($_POST['model'] ?? "");
$serial        = trim($_POST['serial_number'] ?? "");
$purchase_date = $_POST['purchase_date'] ?? null;
$cost          = (isset($_POST["cost"]) && $_POST["cost"] !== "") ? (float)$_POST["cost"] : null;
$notes         = trim($_POST['notes'] ?? "");

if ($id <= 0 || $name === "" || $category === "") {
    header("Location: " . $returnTo . "&err=item_required");
    exit;
}

try {
    $stmtUpdate = $pdo->prepare("
        UPDATE items
        SET name          = :name,
            category      = :category,
            brand         = :brand,
            model         = :model,
            serial_number = :serial,
            purchase_date = :purchase_date,
            cost          = :cost,
            notes         = :notes
        WHERE id = :id AND home_id = :home_id
        LIMIT 1
    ");
    $stmtUpdate->execute([
        ':id'            => $id,
        ':home_id'       => $activeHomeId,
        ':name'          => $name,
        ':category'      => $category,
        ':brand'         => $brand,
        ':model'         => $model,
        ':serial'        => $serial,
        ':purchase_date' => ($purchase_date ?: null),
        ':cost'          => $cost,
        ':notes'         => ($notes !== "" ? $notes : null),
    ]);

    header("Location: " . APP_URL . "/items/index.php?item_id={$id}&tab=details&updated=1");
    exit;

} catch (PDOException $e) {
    header("Location: " . $returnTo . "&err=db_update_item");
    exit;
}
