<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class TicklerRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Returns schedule rows for a home within a due_date range (inclusive).
     * Output matches existing UI expectations.
     */
    public function getTasksForRange(int $homeId, string $startYmd, string $endYmd): array
    {
        $stmt = $this->pdo->prepare("
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
            ':home_id'    => $homeId,
            ':start_date' => $startYmd,
            ':end_date'   => $endYmd,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns all schedule rows due on a specific date for a home.
     */
    public function getTasksForDate(int $homeId, string $dateYmd): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                ts.id AS schedule_id,
                ts.due_date,
                mt.id AS task_id,
                mt.task_name,
                mt.description,
                mt.priority,
                mt.schedule_type,
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
            ':home_id'  => $homeId,
            ':due_date' => $dateYmd,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
