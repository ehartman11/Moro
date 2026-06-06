<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class TaskRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Locks the task+schedule row for completion and confirms it belongs to active home.
     * If $itemId provided, it also enforces task belongs to that item.
     */

    /**
     * Locks the task+schedule row for completion and confirms it belongs to active home.
     * If $itemId provided, it also enforces task belongs to that item.
     */
    public function lockTaskForCompletion(int $homeId, int $taskId, ?int $itemId): ?array
    {
        $itemClause = $itemId ? "AND t.item_id = :item_id" : "";

        $sql = "
            SELECT
                t.id AS task_id,
                t.item_id AS item_id,
                t.frequency_value,
                t.frequency_unit,
                s.due_date
            FROM maintenance_tasks t
            JOIN task_schedule s ON s.task_id = t.id
            JOIN items i ON i.id = t.item_id
            WHERE i.home_id = :hid
              AND t.id = :task_id
              $itemClause
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->pdo->prepare($sql);

        $params = [':hid' => $homeId, ':task_id' => $taskId];
        if ($itemId) $params[':item_id'] = $itemId;

        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }


    /**
     * Used by add_history.php variant: lock by task+item+home and return freq.
     */
    public function lockTaskForHistory(int $homeId, int $taskId, int $itemId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT mt.id, mt.frequency_value, mt.frequency_unit
            FROM maintenance_tasks mt
            JOIN items i ON i.id = mt.item_id
            WHERE mt.id = :tid
              AND mt.item_id = :item_id
              AND i.home_id = :hid
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([':tid' => $taskId, ':item_id' => $itemId, ':hid' => $homeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    
    public function insertTask(array $row): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO maintenance_tasks
                (item_id, task_name, description, part_name, schedule_type, frequency_value, frequency_unit, priority)
            VALUES
                (:item_id, :task_name, :description, :part_name, :st, :fv, :fu, :priority)
        ");
        $stmt->execute([
            ':item_id'      => (int)$row['item_id'],
            ':task_name'    => (string)$row['task_name'],
            ':description'  => $row['description'] ?? null,
            ':part_name'    => (string)$row['part_name'],
            ':st'           => (string)$row['schedule_type'],
            ':fv'           => (int)$row['frequency_value'],
            ':fu'           => (string)$row['frequency_unit'],
            ':priority'     => (string)$row['priority'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }
    
    public function listForItem(int $itemId): array
        {
            $stmt = $this->pdo->prepare("
                SELECT id, task_name
                FROM maintenance_tasks
                WHERE item_id = :item_id
                ORDER BY task_name
            ");
            $stmt->execute([':item_id' => $itemId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

}
