<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class FeedbackRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array<int, array<string,mixed>> */
    public function findByHomeAndStatus(int $homeId, string $status, int $limit): array
    {
        // LIMIT cannot be bound in some drivers unless emulation is on; safe-cast it.
        $limit = (int)$limit;

        $sql = "
            SELECT
                f.id,
                f.category,
                f.message,
                f.status,
                f.created_at,
                f.page_no,
                f.step_no,
                f.section_ref,
                f.resolution_notes,

                mc.id AS content_id,
                mc.revision_no,
                mc.doc_key,

                u.fname AS submitted_by,

                f.item_id,
                f.task_id,
                f.part_name
            FROM mrc_feedback f
            LEFT JOIN mrc_content mc ON mc.id = f.mrc_content_id
            LEFT JOIN users u ON u.id = f.submitted_by_user_id
            WHERE f.home_id = :home_id
              AND f.status  = :status
            ORDER BY f.created_at DESC
            LIMIT {$limit}
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':home_id' => $homeId,
            ':status'  => $status,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}