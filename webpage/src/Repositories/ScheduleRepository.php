<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class ScheduleRepository
{
    public function __construct(private PDO $pdo) {}

    public function updateDueDate(int $taskId, string $nextDueYmd): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE task_schedule
            SET due_date = :due
            WHERE task_id = :tid
            LIMIT 1
        ");
        $stmt->execute([':due' => $nextDueYmd, ':tid' => $taskId]);
    }

    public function ensureScheduleRow(int $taskId, string $dueYmd): void
    {
        $stmt = $this->pdo->prepare("
            SELECT task_id FROM task_schedule WHERE task_id = :tid LIMIT 1 FOR UPDATE
        ");
        $stmt->execute([':tid' => $taskId]);
        $exists = (bool)$stmt->fetchColumn();

        if ($exists) {
            $this->updateDueDate($taskId, $dueYmd);
            return;
        }

        $stmtI = $this->pdo->prepare("
            INSERT INTO task_schedule (task_id, due_date)
            VALUES (:tid, :due)
        ");
        $stmtI->execute([':tid' => $taskId, ':due' => $dueYmd]);
    }

    public function insertScheduleRow(int $taskId, string $dueYmd): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO task_schedule (task_id, due_date)
            VALUES (:tid, :due)
        ");
        $stmt->execute([':tid' => $taskId, ':due' => $dueYmd]);
    }

}
