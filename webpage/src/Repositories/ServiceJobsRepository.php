<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class ServiceJobsRepository
{
    public function __construct(private PDO $pdo) {}

    public function contractorProfileExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM contractor_profiles WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function findTaskInHome(int $taskId, int $homeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT mt.id, mt.item_id
            FROM maintenance_tasks mt
            JOIN items i ON i.id = mt.item_id
            WHERE mt.id = :tid AND i.home_id = :hid
            LIMIT 1
        ");
        $stmt->execute([
            ':tid' => $taskId,
            ':hid' => $homeId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForContractor(int $contractorUserId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, home_id, item_id, task_id, title, description, state, priority,
                   scheduled_for, due_at, completed_at, created_at, updated_at
            FROM service_jobs
            WHERE contractor_user_id = :uid
            ORDER BY created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $contractorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForContractorInHome(int $homeId, int $contractorUserId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, home_id, item_id, task_id, title, description, state, priority,
                   scheduled_for, due_at, completed_at, created_at, updated_at
            FROM service_jobs
            WHERE home_id = :hid
              AND contractor_user_id = :uid
            ORDER BY created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':hid', $homeId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $contractorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForContractorInHome(int $jobId, int $homeId, int $contractorUserId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, home_id, contractor_user_id, state
            FROM service_jobs
            WHERE id = :jid
              AND home_id = :hid
              AND contractor_user_id = :uid
            LIMIT 1
        ");
        $stmt->execute([
            ':jid' => $jobId,
            ':hid' => $homeId,
            ':uid' => $contractorUserId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForHomeownerInHome(int $homeId, int $homeownerUserId, ?string $state, int $limit = 100): array
    {
        $stateClause = '';
        if ($state !== null && $state !== '') {
            $stateClause = 'AND sj.state = :state';
        }

        $sql = "
            SELECT
                sj.id,
                sj.home_id,
                sj.item_id,
                sj.task_id,
                sj.homeowner_user_id,
                sj.contractor_user_id,
                sj.title,
                sj.description,
                sj.state,
                sj.priority,
                sj.scheduled_for,
                sj.due_at,
                sj.completed_at,
                sj.created_at,
                sj.updated_at,
                COALESCE(cp.business_name, u.name, CONCAT('User #', sj.contractor_user_id)) AS contractor_name
            FROM service_jobs sj
            JOIN users u ON u.id = sj.contractor_user_id
            LEFT JOIN contractor_profiles cp ON cp.user_id = sj.contractor_user_id
            WHERE sj.home_id = :hid
              AND sj.homeowner_user_id = :homeowner_uid
              $stateClause
            ORDER BY sj.created_at DESC
            LIMIT :lim
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':hid', $homeId, PDO::PARAM_INT);
        $stmt->bindValue(':homeowner_uid', $homeownerUserId, PDO::PARAM_INT);
        if ($stateClause !== '') {
            $stmt->bindValue(':state', $state, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForHomeownerInHome(int $jobId, int $homeId, int $homeownerUserId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                sj.id,
                sj.home_id,
                sj.item_id,
                sj.task_id,
                sj.homeowner_user_id,
                sj.contractor_user_id,
                sj.title,
                sj.description,
                sj.state,
                sj.priority,
                sj.scheduled_for,
                sj.due_at,
                sj.completed_at,
                sj.created_at,
                sj.updated_at,
                COALESCE(cp.business_name, u.name, CONCAT('User #', sj.contractor_user_id)) AS contractor_name
            FROM service_jobs sj
            JOIN users u ON u.id = sj.contractor_user_id
            LEFT JOIN contractor_profiles cp ON cp.user_id = sj.contractor_user_id
            WHERE sj.id = :jid
              AND sj.home_id = :hid
              AND sj.homeowner_user_id = :homeowner_uid
            LIMIT 1
        ");
        $stmt->execute([
            ':jid' => $jobId,
            ':hid' => $homeId,
            ':homeowner_uid' => $homeownerUserId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $row): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO service_jobs (
                home_id, item_id, task_id, homeowner_user_id, contractor_user_id,
                title, description, state, priority, scheduled_for, due_at
            ) VALUES (
                :home_id, :item_id, :task_id, :homeowner_user_id, :contractor_user_id,
                :title, :description, :state, :priority, :scheduled_for, :due_at
            )
        ");

        $stmt->execute([
            ':home_id' => (int)$row['home_id'],
            ':item_id' => $row['item_id'] ?? null,
            ':task_id' => $row['task_id'] ?? null,
            ':homeowner_user_id' => (int)$row['homeowner_user_id'],
            ':contractor_user_id' => (int)$row['contractor_user_id'],
            ':title' => (string)$row['title'],
            ':description' => $row['description'] ?? null,
            ':state' => (string)($row['state'] ?? 'assigned'),
            ':priority' => (string)($row['priority'] ?? 'medium'),
            ':scheduled_for' => $row['scheduled_for'] ?? null,
            ':due_at' => $row['due_at'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function markCompleted(int $jobId, string $completedAt): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE service_jobs
            SET state = 'completed',
                completed_at = :completed_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :jid
            LIMIT 1
        ");
        $stmt->execute([
            ':completed_at' => $completedAt,
            ':jid' => $jobId,
        ]);
    }

    public function updateState(int $jobId, string $state): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE service_jobs
            SET state = :state,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :jid
            LIMIT 1
        ");
        $stmt->execute([
            ':state' => $state,
            ':jid' => $jobId,
        ]);
    }
}
