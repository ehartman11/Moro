<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class SubmissionReviewsRepository
{
    public function __construct(private PDO $pdo) {}

    public function insertReview(int $jobSubmissionId, int $reviewerUserId, string $decision, ?string $comments): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO submission_reviews (
                job_submission_id, reviewer_user_id, decision, comments
            ) VALUES (
                :submission_id, :reviewer_user_id, :decision, :comments
            )
        ");
        $stmt->execute([
            ':submission_id' => $jobSubmissionId,
            ':reviewer_user_id' => $reviewerUserId,
            ':decision' => $decision,
            ':comments' => $comments,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listForSubmissionIds(array $submissionIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $submissionIds), static fn(int $value): bool => $value > 0));
        if ($ids === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->pdo->prepare("\n            SELECT id, job_submission_id, reviewer_user_id, decision, comments, created_at\n            FROM submission_reviews\n            WHERE job_submission_id IN ($in)\n            ORDER BY created_at DESC, id DESC\n        ");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
