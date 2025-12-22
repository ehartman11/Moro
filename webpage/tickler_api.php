<?php
/**
 * Tickler API endpoint (JSON).
 *
 * Responsibilities:
 * - Validates session + active home context.
 * - Serves calendar task data for the Tickler UI:
 *   - action=month: returns tasks grouped by due_date for the requested month
 *   - action=day:   returns all tasks due on a specific date
 *
 * Assumptions:
 * - Home scoping is enforced via items.home_id (tasks are tied to items, items belong to a home).
 * - Dates are stored/queried as YYYY-MM-DD (DATE) compatible strings.
 */
session_start();
header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

if (!isset($_SESSION["active_home_id"])) {
    http_response_code(400);
    echo json_encode(["error" => "No active home selected"]);
    exit;
}

require_once "db_conn.php";

$homeId  = (int)$_SESSION["active_home_id"];
$action  = $_GET["action"] ?? "";

function respond(array $arr): void {
    // Centralized JSON response helper to keep control flow clean.
    echo json_encode($arr);
    exit;
}

try {

    if ($action === "month") {
        $year  = (int)($_GET["year"] ?? 0);
        $month = (int)($_GET["month"] ?? 0); // 1-12

        if ($year < 2000 || $month < 1 || $month > 12) {
            http_response_code(400);
            respond(["error" => "Invalid year/month"]);
        }

        // Build an inclusive month range (YYYY-MM-01 .. YYYY-MM-last_day) for the due_date filter.
        $start = sprintf("%04d-%02d-01", $year, $month);
        $end   = date("Y-m-t", strtotime($start));

        $stmt = $pdo->prepare("
            SELECT
                ts.id AS schedule_id,
                ts.due_date,
                mt.id AS task_id,
                mt.task_name,
                mt.description,
                mt.priority,
                i.name AS item_name,
                i.id AS item_id
            FROM task_schedule ts
            JOIN maintenance_tasks mt ON ts.task_id = mt.id
            JOIN items i ON mt.item_id = i.id
            WHERE i.home_id = :home_id
              AND ts.due_date BETWEEN :start_date AND :end_date
            ORDER BY ts.due_date ASC, mt.task_name ASC
        ");
        $stmt->execute([
            ":home_id"    => $homeId,
            ":start_date" => $start,
            ":end_date"   => $end
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group tasks by due_date so the UI can badge dates without extra processing.
        $byDate = [];
        foreach ($rows as $r) {
            $d = $r["due_date"]; // YYYY-MM-DD
            if (!isset($byDate[$d])) $byDate[$d] = [];
            $byDate[$d][] = $r;
        }

        respond(["byDate" => $byDate]);
    }

    if ($action === "day") {
        $date = $_GET["date"] ?? "";
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
            http_response_code(400);
            respond(["error" => "Invalid date format"]);
        }

        $stmt = $pdo->prepare("
            SELECT
                ts.id AS schedule_id,
                ts.due_date,
                mt.id AS task_id,
                mt.task_name,
                mt.description,
                mt.priority,
                i.name AS item_name,
                i.id AS item_id
            FROM task_schedule ts
            JOIN maintenance_tasks mt ON ts.task_id = mt.id
            JOIN items i ON mt.item_id = i.id
            WHERE i.home_id = :home_id
              AND ts.due_date = :due_date
            ORDER BY mt.task_name ASC
        ");
        $stmt->execute([
            ":home_id"  => $homeId,
            ":due_date" => $date
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        respond(["tasks" => $rows]);
    }

    http_response_code(400);
    respond(["error" => "Unknown action"]);

} catch (PDOException $e) {
    http_response_code(500);

    // In production, avoid returning raw DB errors to the client; log server-side instead.
    respond(["error" => $e->getMessage()]);
}
