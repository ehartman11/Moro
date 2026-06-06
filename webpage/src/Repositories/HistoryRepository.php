<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class HistoryRepository
{
    public function __construct(private PDO $pdo) {}

    public function insertHistory(int $taskId, ?string $note, float $cost, string $completedOnYmd): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_history (task_id, note, cost, completed_on)
            VALUES (:task_id, :note, :cost, :completed_on)
        ");

        $stmt->execute([
            ':task_id'      => $taskId,
            ':note'         => $note,
            ':cost'         => $cost,
            ':completed_on' => $completedOnYmd,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Safe read for modal: scopes by home+item via maintenance_tasks join.
     * Assumes maintenance_tasks has columns: id, home_id, item_id, task_name
     */
    public function findForHomeItem(int $homeId, int $itemId, int $historyId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                th.id AS history_id,
                th.task_id,
                th.note,
                th.cost,
                th.completed_on,
                th.created_at,
                mt.task_name
            FROM task_history th
            JOIN maintenance_tasks mt ON mt.id = th.task_id
            JOIN items i on i.id = mt.item_id
            WHERE th.id = :hid
              AND i.home_id = :home_id
              AND mt.item_id = :item_id
            LIMIT 1
        ");
        $stmt->execute([
            ':hid' => $historyId,
            ':home_id' => $homeId,
            ':item_id' => $itemId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Safe update for modal: scopes by home+item via joins AND ensures new task_id is also in same home+item.
     */
    public function updateForHomeItem(
        int $homeId,
        int $itemId,
        int $historyId,
        int $newTaskId,
        ?string $note,
        float $cost,
        string $completedOnYmd
    ): bool {
        $stmt = $this->pdo->prepare("
            UPDATE task_history th
            JOIN maintenance_tasks mt_current ON mt_current.id = th.task_id
            JOIN items i_current ON i_current.id = mt_current.item_id

            JOIN maintenance_tasks mt_new ON mt_new.id = :new_task_id
            JOIN items i_new ON i_new.id = mt_new.item_id

            SET
                th.task_id = :new_task_id,
                th.note = :note,
                th.cost = :cost,
                th.completed_on = :completed_on
            WHERE th.id = :hid
            AND mt_current.item_id = :item_id
            AND i_current.home_id = :home_id
            AND mt_new.item_id = :item_id
            AND i_new.home_id = :home_id
        ");

        $stmt->execute([
            ':new_task_id' => $newTaskId,
            ':note' => $note,
            ':cost' => $cost,
            ':completed_on' => $completedOnYmd,
            ':hid' => $historyId,
            ':home_id' => $homeId,
            ':item_id' => $itemId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Safe delete for modal: scopes by home+item via maintenance_tasks join.
     */
    public function deleteForHomeItem(int $homeId, int $itemId, int $historyId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE th
            FROM task_history th
            JOIN maintenance_tasks mt ON mt.id = th.task_id
            JOIN items i ON i.id = mt.item_id
            WHERE th.id = :hid
            AND mt.item_id = :item_id
            AND i.home_id = :home_id
        ");

        $stmt->execute([
            ':hid' => $historyId,
            ':home_id' => $homeId,
            ':item_id' => $itemId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Your existing list for the History tab (selector list).
     * Consider adding home_id scope too (via join), but leaving as-is for now.
     */
    public function listForItemInHome(int $homeId, int $itemId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                th.id AS history_id,
                th.note,
                th.cost,
                th.completed_on,
                th.created_at,
                mt.task_name
            FROM task_history th
            JOIN maintenance_tasks mt ON mt.id = th.task_id
            JOIN items i ON i.id = mt.item_id
            WHERE mt.item_id = :item_id
            AND i.home_id = :home_id
            ORDER BY th.completed_on DESC, th.id DESC
        ");
        $stmt->execute([
            ':item_id' => $itemId,
            ':home_id' => $homeId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}