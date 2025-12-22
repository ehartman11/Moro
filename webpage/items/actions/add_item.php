<?php
/**
 * Add item action (owner-only).
 *
 * Responsibilities:
 * - Requires authenticated user + active home context (_auth.php).
 * - Enforces owner role before allowing item creation.
 * - Validates required fields (name, category).
 * - Inserts a new item scoped to the active home.
 * - Redirects back to the newly created item with a stable success flag.
 */
require_once __DIR__ . "/../_auth.php";

$returnTo = $_POST['return_to'] ?? (APP_URL . "/items/index.php?tab=details");

require_owner($returnTo);

// Only accept POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

// Must include marker
if (!isset($_POST['add_item'])) {
    header("Location: " . $returnTo . "&err=bad_request");
    exit;
}

$name          = trim($_POST["name"] ?? "");
$category      = trim($_POST["category"] ?? "");
$brand         = trim($_POST["brand"] ?? "");
$model         = trim($_POST["model"] ?? "");
$serial        = trim($_POST["serial_number"] ?? "");
$purchase_date = $_POST["purchase_date"] ?? null;
$cost          = (isset($_POST["cost"]) && $_POST["cost"] !== "") ? (float)$_POST["cost"] : null;
$notes         = trim($_POST["notes"] ?? "");

if ($name === "" || $category === "") {
    header("Location: " . $returnTo . "&err=item_required");
    exit;
}

try {
    $stmtAdd = $pdo->prepare("
        INSERT INTO items (home_id, name, category, brand, model, serial_number, purchase_date, cost, notes)
        VALUES (:home_id, :name, :category, :brand, :model, :serial, :purchase_date, :cost, :notes)
    ");
    $stmtAdd->execute([
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

    // Redirect to the newly created item so the user lands in the normal details view.
    $newId = (int)$pdo->lastInsertId();

    header("Location: " . APP_URL . "/items/index.php?item_id={$newId}&tab=details&added=1");
    exit;

} catch (PDOException $e) {
    header("Location: " . $returnTo . "&err=db_add_item");
    exit;
}
