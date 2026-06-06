<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class JobSubmissionsRepository
{
    public function __construct(private PDO $pdo) {}

    public function lockForHomeownerReview(int $submissionId, int $homeId, int $homeownerUserId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT js.id, js.service_job_id, js.state
            FROM job_submissions js
            JOIN service_jobs sj ON sj.id = js.service_job_id
            WHERE js.id = :sid
              AND sj.home_id = :hid
              AND sj.homeowner_user_id = :homeowner_uid
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            ':sid' => $submissionId,
            ':hid' => $homeId,
            ':homeowner_uid' => $homeownerUserId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForJob(int $serviceJobId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, service_job_id, submitted_by_user_id, state, amount, currency,
                   work_summary, receipt_doc_key, submitted_at, decided_at,
                   created_at, updated_at
            FROM job_submissions
            WHERE service_job_id = :jid
            ORDER BY created_at DESC
        ");
        $stmt->execute([':jid' => $serviceJobId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createDraft(array $row): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO job_submissions (
                service_job_id, submitted_by_user_id, state, amount, currency, work_summary, receipt_doc_key
            ) VALUES (
                :service_job_id, :submitted_by_user_id, 'draft', :amount, :currency, :work_summary, :receipt_doc_key
            )
        ");
        $stmt->execute([
            ':service_job_id' => (int)$row['service_job_id'],
            ':submitted_by_user_id' => (int)$row['submitted_by_user_id'],
            ':amount' => $row['amount'] ?? null,
            ':currency' => (string)($row['currency'] ?? 'USD'),
            ':work_summary' => $row['work_summary'] ?? null,
            ':receipt_doc_key' => $row['receipt_doc_key'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function createSubmitted(array $row, string $submittedAt): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO job_submissions (
                service_job_id, submitted_by_user_id, state, amount, currency, work_summary, receipt_doc_key, submitted_at
            ) VALUES (
                :service_job_id, :submitted_by_user_id, 'submitted', :amount, :currency, :work_summary, :receipt_doc_key, :submitted_at
            )
        ");
        $stmt->execute([
            ':service_job_id' => (int)$row['service_job_id'],
            ':submitted_by_user_id' => (int)$row['submitted_by_user_id'],
            ':amount' => $row['amount'] ?? null,
            ':currency' => (string)($row['currency'] ?? 'USD'),
            ':work_summary' => $row['work_summary'] ?? null,
            ':receipt_doc_key' => $row['receipt_doc_key'] ?? null,
            ':submitted_at' => $submittedAt,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateDecisionState(int $submissionId, string $state, string $decidedAt): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE job_submissions
            SET state = :state,
                decided_at = :decided_at,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :sid
            LIMIT 1
        ");
        $stmt->execute([
            ':state' => $state,
            ':decided_at' => $decidedAt,
            ':sid' => $submissionId,
        ]);
    }
}
