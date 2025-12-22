<?php
/**
 * Add maintenance task action (owner-only).
 *
 * Responsibilities:
 * - Requires authenticated user + active home context (_auth.php).
 * - Enforces owner role before allowing task creation.
 * - Validates required inputs (task name, frequency, unit, priority).
 * - Confirms the item belongs to the active home (prevents cross-home task creation).
 * - Creates:
 *   1) a maintenance_tasks row
 *   2) a single task_schedule row (1:1 schedule model) with an initial due date
 * - Uses a transaction to keep task + schedule inserts consistent.
 */
require_once __DIR__ . "/../_auth.php";

$itemId = (int)($_POST['item_id'] ?? 0);
$returnTo = $_POST['return_to'] ?? (APP_URL . "/items/index.php?item_id={$itemId}&tab=maintenance");

require_owner($returnTo);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}
if (!isset($_POST['add_task'])) {
    header("Location: " . $returnTo . "&err=bad_request");
    exit;
}

$taskName = trim($_POST['task_name'] ?? "");
$desc     = trim($_POST['description'] ?? "");
$freqVal  = (int)($_POST['frequency_value'] ?? 0);
$freqUnit = $_POST['frequency_unit'] ?? "months";
$priority = $_POST['priority'] ?? "medium";
$firstDue = trim($_POST['first_due_date'] ?? ""); // optional YYYY-MM-DD

$validUnits    = ['days','weeks','months','years'];
$validPriority = ['low','medium','high'];

if ($itemId <= 0 || $taskName === "" || $freqVal <= 0 || !in_array($freqUnit, $validUnits, true) || !in_array($priority, $validPriority, true)) {
    header("Location: " . $returnTo . "&err=task_invalid");
    exit;
}

// Security: ensure item is in active home.
$stmtCheck = $pdo->prepare("SELECT id FROM items WHERE id = :id AND home_id = :hid LIMIT 1");
$stmtCheck->execute([':id' => $itemId, ':hid' => $activeHomeId]);
if (!$stmtCheck->fetch()) {
    http_response_code(403);
    exit("Unauthorized item.");
}

try {
    $pdo->beginTransaction();

    // Create task record (schedule is created in a second step).
    $stmt = $pdo->prepare("
        INSERT INTO maintenance_tasks (item_id, task_name, description, frequency_value, frequency_unit, priority)
        VALUES (:item_id, :task_name, :description, :fv, :fu, :priority)
    ");
    $stmt->execute([
        ':item_id'   => $itemId,
        ':task_name' => $taskName,
        ':description' => ($desc !== "" ? $desc : null),
        ':fv'        => $freqVal,
        ':fu'        => $freqUnit,
        ':priority'  => $priority,
    ]);

    $taskId = (int)$pdo->lastInsertId();

    // Initial schedule row (explicit first due date overrides auto-scheduling).
    if ($firstDue !== "") {
        $dueDate = $firstDue;
    } else {
        $dt = new DateTime(); // today
        $intervalSpec = match ($freqUnit) {
            'days'   => "P{$freqVal}D",
            'weeks'  => "P{$freqVal}W",
            'months' => "P{$freqVal}M",
            'years'  => "P{$freqVal}Y",
        };
        $dt->add(new DateInterval($intervalSpec));
        $dueDate = $dt->format("Y-m-d");
    }

    $stmt = $pdo->prepare("
        INSERT INTO task_schedule (task_id, due_date)
        VALUES (:tid, :due)
    ");
    $stmt->execute([':tid' => $taskId, ':due' => $dueDate]);

    $pdo->commit();

    header("Location: " . $returnTo . "&task_added=1");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header("Location: " . $returnTo . "&err=task_add_failed");
    exit;
}
