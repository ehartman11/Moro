<?php
/**
 * Add history entry action (owner-only).
 *
 * Responsibilities:
 * - Requires authenticated user + active home context (_auth.php).
 * - Enforces owner role before allowing history writes.
 * - Validates required inputs (task_id, done_date) and date format.
 * - Inserts a task_history row (completed_on anchored to the provided completion date).
 * - Optionally inserts a related photo record (temporary path-based approach).
 * - Advances the task's schedule due_date in place based on completion date + frequency.
 *
 * Assumptions:
 * - 1:1 schedule model per task (task_schedule has one row per task).
 * - Scoping is enforced by joining maintenance_tasks -> items.home_id.
 */
require_once __DIR__ . "/../_auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

$itemId = (int)($_POST['item_id'] ?? 0);
$returnTo = $_POST['return_to'] ?? (APP_URL . "/items/index.php?item_id={$itemId}&tab=history");

require_owner($returnTo);

if (!isset($_POST['add_history'])) {
    header("Location: " . $returnTo . "&err=bad_request");
    exit;
}

$taskId    = (int)($_POST['task_id'] ?? 0);
$doneDate  = trim($_POST['done_date'] ?? "");
$cost      = (isset($_POST['cost']) && $_POST['cost'] !== "") ? (float)$_POST['cost'] : 0.00;
$note      = trim($_POST['note'] ?? "");
$photoPath = trim($_POST['photo_path'] ?? "");

if ($taskId <= 0 || $doneDate === "") {
    header("Location: " . $returnTo . "&err=history_required");
    exit;
}

// Validate date input before using it for scheduling math.
$dt = DateTime::createFromFormat("Y-m-d", $doneDate);
if (!$dt || $dt->format("Y-m-d") !== $doneDate) {
    header("Location: " . $returnTo . "&err=history_bad_date");
    exit;
}

try {
    $pdo->beginTransaction();

    // Security: ensure task belongs to item AND item belongs to active home, and pull frequency settings.
    $stmt = $pdo->prepare("
        SELECT mt.id, mt.frequency_value, mt.frequency_unit
        FROM maintenance_tasks mt
        JOIN items i ON i.id = mt.item_id
        WHERE mt.id = :tid
          AND mt.item_id = :item_id
          AND i.home_id = :hid
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        ':tid' => $taskId,
        ':item_id' => $itemId,
        ':hid' => $activeHomeId
    ]);
    $taskRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$taskRow) {
        $pdo->rollBack();
        header("Location: " . $returnTo . "&err=unauthorized");
        exit;
    }

    // 1) Insert history row (completed_on is the user-provided completion date).
    $stmtH = $pdo->prepare("
        INSERT INTO task_history (task_id, note, cost, completed_on)
        VALUES (:task_id, :note, :cost, :completed_on)
    ");
    $stmtH->execute([
        ':task_id'      => $taskId,
        ':note'         => ($note !== "" ? $note : null),
        ':cost'         => $cost,
        ':completed_on' => $doneDate,
    ]);

    $historyId = (int)$pdo->lastInsertId();

    // 2) Optional photo record (temporary path-based upload model).
    if ($photoPath !== "") {
        $stmtP = $pdo->prepare("
            INSERT INTO photos (history_id, file_path)
            VALUES (:hid, :path)
        ");
        $stmtP->execute([':hid' => $historyId, ':path' => $photoPath]);
    }

    // 3) Advance schedule due_date based on completion date + frequency.
    $freqVal  = (int)$taskRow['frequency_value'];
    $freqUnit = (string)$taskRow['frequency_unit'];

    $intervalSpec = match ($freqUnit) {
        "days"   => "P{$freqVal}D",
        "weeks"  => "P{$freqVal}W",
        "months" => "P{$freqVal}M",
        "years"  => "P{$freqVal}Y",
        default  => "P{$freqVal}D",
    };

    $dtNext = new DateTime($doneDate);
    $dtNext->add(new DateInterval($intervalSpec));
    $nextDue = $dtNext->format("Y-m-d");

    // Ensure schedule row exists (expected in 1:1 model, but safe for older data).
    $stmtS = $pdo->prepare("
        SELECT task_id
        FROM task_schedule
        WHERE task_id = :tid
        LIMIT 1
        FOR UPDATE
    ");
    $stmtS->execute([':tid' => $taskId]);
    $hasSchedule = (bool)$stmtS->fetchColumn();

    if ($hasSchedule) {
        $stmtU = $pdo->prepare("
            UPDATE task_schedule
            SET due_date = :due
            WHERE task_id = :tid
            LIMIT 1
        ");
        $stmtU->execute([':due' => $nextDue, ':tid' => $taskId]);
    } else {
        $stmtI = $pdo->prepare("
            INSERT INTO task_schedule (task_id, due_date)
            VALUES (:tid, :due)
        ");
        $stmtI->execute([':tid' => $taskId, ':due' => $nextDue]);
    }

    $pdo->commit();

    header("Location: " . $returnTo . "&history_saved=1");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    // Avoid leaking exception details to the client; keep debug redirects dev-only.
    header("Location: " . $returnTo . "&err=history_failed");
    exit;
}
