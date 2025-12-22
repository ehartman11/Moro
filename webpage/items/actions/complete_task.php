<?php
/**
 * Complete maintenance task action (owner-only).
 *
 * Responsibilities:
 * - Requires authenticated user + active home context (_auth.php).
 * - Enforces owner role before allowing completion.
 * - Validates request + completion date.
 * - Writes a task_history row (note/cost/completed_on).
 * - Advances the task's single schedule row by computing the next due date from completion date.
 *
 * Assumptions:
 * - 1:1 schedule model: each maintenance task has exactly one task_schedule row (no “completed” flag).
 * - Task ownership is enforced by scoping through items.home_id = active home.
 */
require_once __DIR__ . "/../_auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method not allowed.");
}

$taskId = (int)($_POST["task_id"] ?? 0);
$itemId = (int)($_POST["item_id"] ?? 0);

$returnTo = $_POST["return_to"] ?? (APP_URL . "/items/index.php?item_id={$itemId}&tab=maintenance");

require_owner($returnTo);

if (!isset($_POST['complete_task'])) {
    header("Location: " . $returnTo . "&err=bad_request");
    exit;
}

$completedOn = trim($_POST["completed_on"] ?? "");
$note        = trim($_POST["note"] ?? "");
$cost        = (($_POST["cost"] ?? "") !== "") ? (float)$_POST["cost"] : 0.0;

if ($taskId <= 0 || $completedOn === "") {
    header("Location: " . $returnTo . "&err=complete_invalid");
    exit;
}

// Validate date input before using it for scheduling math.
$dtCompleted = DateTime::createFromFormat("Y-m-d", $completedOn);
if (!$dtCompleted || $dtCompleted->format("Y-m-d") !== $completedOn) {
    header("Location: " . $returnTo . "&err=complete_bad_date");
    exit;
}

try {
    $pdo->beginTransaction();

    /**
     * Security + fetch task settings + lock schedule row (1:1 schedule).
     * Confirms:
     * - task belongs to active home (scoped through items)
     * - schedule row exists for this task
     */
    $stmt = $pdo->prepare("
        SELECT
            t.id AS task_id,
            t.frequency_value,
            t.frequency_unit,
            s.due_date
        FROM maintenance_tasks t
        JOIN task_schedule s ON s.task_id = t.id
        JOIN items i ON i.id = t.item_id
        WHERE i.home_id = :hid
          AND t.id = :task_id
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([
        ":hid" => $activeHomeId,
        ":task_id" => $taskId
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        header("Location: " . $returnTo . "&err=complete_not_found");
        exit;
    }

    // 1) Write history (schedule_id intentionally omitted in 1:1 model).
    $stmt = $pdo->prepare("
        INSERT INTO task_history (task_id, note, cost, completed_on)
        VALUES (:tid, :note, :cost, :completed_on)
    ");
    $stmt->execute([
        ":tid" => $taskId,
        ":note" => ($note !== "" ? $note : null),
        ":cost" => $cost,
        ":completed_on" => $completedOn
    ]);

    // 2) Compute next due date using the completion date as the anchor.
    $freqVal  = (int)$row["frequency_value"];
    $freqUnit = (string)$row["frequency_unit"];

    $intervalSpec = match ($freqUnit) {
        "days"   => "P{$freqVal}D",
        "weeks"  => "P{$freqVal}W",
        "months" => "P{$freqVal}M",
        "years"  => "P{$freqVal}Y",
        default  => "P{$freqVal}D",
    };

    $dtNext = new DateTime($completedOn);
    $dtNext->add(new DateInterval($intervalSpec));
    $nextDue = $dtNext->format("Y-m-d");

    // 3) Update the single schedule row (no inserts, no completed flag).
    $stmt = $pdo->prepare("
        UPDATE task_schedule
        SET due_date = :due
        WHERE task_id = :tid
        LIMIT 1
    ");
    $stmt->execute([
        ":due" => $nextDue,
        ":tid" => $taskId
    ]);

    $pdo->commit();

    header("Location: " . $returnTo . "&completed=1");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    // TEMP DEBUG: leaking raw exception messages to the URL is risky; remove when stable.
    header("Location: " . $returnTo . "&err=complete_failed&msg=" . urlencode($e->getMessage()));
    exit;
}
