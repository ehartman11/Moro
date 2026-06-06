<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class MaintenanceRepository
{
    public function __construct(private PDO $pdo) {}

    public function listTasksWithNextDueForItem(int $itemId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.*,
                s.due_date AS next_due
            FROM maintenance_tasks t
            LEFT JOIN task_schedule s ON s.task_id = t.id
            WHERE t.item_id = :iid
            ORDER BY
                FIELD(t.priority, 'high','medium','low'),
                t.created_at DESC
        ");
        $stmt->execute([':iid' => $itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listManualsForItem(int $itemId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, title, language, source_url, created_at
            FROM manuals
            WHERE item_id = :iid
            ORDER BY created_at DESC
        ");
        $stmt->execute([':iid' => $itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listTaskTreeForHome(int $homeId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                i.id   AS item_id,
                i.name AS item_name,
                COALESCE(NULLIF(TRIM(t.part_name), ''), 'General') AS part_name,
                t.id   AS task_id,
                t.task_name,
                t.priority,
                s.due_date AS next_due
            FROM items i
            LEFT JOIN maintenance_tasks t ON t.item_id = i.id
            LEFT JOIN task_schedule s ON s.task_id = t.id
            WHERE i.home_id = :hid
            ORDER BY
                i.name ASC,
                part_name ASC,
                t.task_name ASC
        ");
        $stmt->execute([':hid' => $homeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    
    public function getTaskCard(int $homeId, int $taskId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.id AS task_id,
                t.item_id,
                t.task_name,
                t.description,
                NULLIF(TRIM(t.part_name), '') AS part_name,
                t.priority,
                t.schedule_type,
                t.frequency_value,
                t.frequency_unit,
                s.due_date AS next_due,

                mc.id          AS mrc_content_id,
                mc.revision_no AS mrc_revision_no,
                mc.state       AS mrc_state,
                mc.doc_key     AS mrc_doc_key

            FROM maintenance_tasks t
            JOIN items i ON i.id = t.item_id
            LEFT JOIN task_schedule s ON s.task_id = t.id

            LEFT JOIN mrc_content mc
            ON mc.home_id = i.home_id
            AND mc.item_id = t.item_id
            AND mc.task_id = t.id
            AND mc.part_name <=> NULLIF(TRIM(t.part_name), '')
            AND mc.state = 'draft'
            AND mc.revision_no = (
                SELECT MAX(mc2.revision_no)
                FROM mrc_content mc2
                WHERE mc2.home_id = i.home_id
                    AND mc2.item_id = t.item_id
                    AND mc2.task_id = t.id
                    AND mc2.part_name <=> NULLIF(TRIM(t.part_name), '')
                    AND mc2.state = 'draft'
            )

            WHERE i.home_id = :hid
            AND t.id = :tid
            LIMIT 1
        ");

        $stmt->execute([':hid' => $homeId, ':tid' => $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getDraftCard(int $homeId, int $taskId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.id AS task_id,
                t.item_id,
                t.task_name,
                t.description,
                NULLIF(TRIM(t.part_name), '') AS part_name,
                t.priority,
                t.schedule_type,
                t.frequency_value,
                t.frequency_unit,
                s.due_date AS next_due,

                mc.id          AS mrc_content_id,
                mc.revision_no AS mrc_revision_no,
                mc.state       AS mrc_state,
                mc.doc_key     AS mrc_doc_key

            FROM maintenance_tasks t
            JOIN items i ON i.id = t.item_id
            LEFT JOIN task_schedule s ON s.task_id = t.id

            LEFT JOIN mrc_content mc
            ON mc.home_id = i.home_id
            AND mc.item_id = t.item_id
            AND mc.task_id = t.id
            AND mc.part_name <=> NULLIF(TRIM(t.part_name), '')
            AND mc.state = 'draft'
            AND mc.revision_no = (
                SELECT MAX(mc2.revision_no)
                FROM mrc_content mc2
                WHERE mc2.home_id = i.home_id
                    AND mc2.item_id = t.item_id
                    AND mc2.task_id = t.id
                    AND mc2.part_name <=> NULLIF(TRIM(t.part_name), '')
                    AND mc2.state = 'draft'
            )

            WHERE i.home_id = :hid
            AND t.id = :tid
            LIMIT 1
        ");

        $stmt->execute([':hid' => $homeId, ':tid' => $taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

}
