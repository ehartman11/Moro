<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class SubmissionMediaRepository
{
    public function __construct(private PDO $pdo) {}

    public function insert(int $jobSubmissionId, string $mediaType, string $mediaKey, ?string $caption): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO submission_media (job_submission_id, media_type, media_key, caption)
            VALUES (:sid, :media_type, :media_key, :caption)
        ");
        $stmt->execute([
            ':sid' => $jobSubmissionId,
            ':media_type' => $mediaType,
            ':media_key' => $mediaKey,
            ':caption' => $caption,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findByIdInHome(int $mediaId, int $homeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT sm.id, sm.job_submission_id, sm.media_type, sm.media_key, sm.caption, sm.created_at
            FROM submission_media sm
            JOIN job_submissions js ON js.id = sm.job_submission_id
            JOIN service_jobs sj ON sj.id = js.service_job_id
            WHERE sm.id = :mid
              AND sj.home_id = :hid
            LIMIT 1
        ");
        $stmt->execute([
            ':mid' => $mediaId,
            ':hid' => $homeId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForSubmissionIdsByHomeowner(int $homeId, int $homeownerUserId, array $submissionIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $submissionIds), static fn(int $value): bool => $value > 0));
        if ($ids === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        $params = [$homeId, $homeownerUserId, ...$ids];

        $stmt = $this->pdo->prepare("\n            SELECT\n                sm.id,\n                sm.job_submission_id,\n                sm.media_type,\n                sm.media_key,\n                sm.caption,\n                sm.created_at\n            FROM submission_media sm\n            JOIN job_submissions js ON js.id = sm.job_submission_id\n            JOIN service_jobs sj ON sj.id = js.service_job_id\n            WHERE sj.home_id = ?\n              AND sj.homeowner_user_id = ?\n              AND sm.job_submission_id IN ($in)\n            ORDER BY sm.created_at DESC, sm.id DESC\n        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
