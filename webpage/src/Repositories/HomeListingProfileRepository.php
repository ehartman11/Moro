<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class HomeListingProfileRepository
{
    public function __construct(private PDO $pdo) {}

    public function findByHomeId(int $homeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                id,
                home_id,
                beds,
                baths,
                interior_sqft,
                style,
                floors,
                basement_type,
                garage_type,
                garage_capacity,
                acreage,
                year_built_override,
                headline,
                summary,
                visibility_fields,
                is_published,
                published_at,
                updated_by_user_id,
                created_at,
                updated_at
            FROM home_listing_profiles
            WHERE home_id = :home_id
            LIMIT 1
        ");
        $stmt->execute([':home_id' => $homeId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['is_published'] = (int)$row['is_published'];
        $visibility = [];
        if (!empty($row['visibility_fields']) && is_string($row['visibility_fields'])) {
            $decoded = json_decode($row['visibility_fields'], true);
            if (is_array($decoded)) {
                $visibility = $decoded;
            }
        }
        $row['visibility_fields'] = $visibility;
        return $row;
    }

    public function upsertByHomeId(int $homeId, int $updatedByUserId, array $profile): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO home_listing_profiles (
                home_id,
                beds,
                baths,
                interior_sqft,
                style,
                floors,
                basement_type,
                garage_type,
                garage_capacity,
                acreage,
                year_built_override,
                headline,
                summary,
                visibility_fields,
                is_published,
                published_at,
                updated_by_user_id
            ) VALUES (
                :home_id,
                :beds,
                :baths,
                :interior_sqft,
                :style,
                :floors,
                :basement_type,
                :garage_type,
                :garage_capacity,
                :acreage,
                :year_built_override,
                :headline,
                :summary,
                :visibility_fields,
                :is_published,
                :published_at,
                :updated_by_user_id
            )
            ON DUPLICATE KEY UPDATE
                beds = VALUES(beds),
                baths = VALUES(baths),
                interior_sqft = VALUES(interior_sqft),
                style = VALUES(style),
                floors = VALUES(floors),
                basement_type = VALUES(basement_type),
                garage_type = VALUES(garage_type),
                garage_capacity = VALUES(garage_capacity),
                acreage = VALUES(acreage),
                year_built_override = VALUES(year_built_override),
                headline = VALUES(headline),
                summary = VALUES(summary),
                visibility_fields = VALUES(visibility_fields),
                is_published = VALUES(is_published),
                published_at = VALUES(published_at),
                updated_by_user_id = VALUES(updated_by_user_id)
        ");

        $visibilityJson = null;
        if (!empty($profile['visibility_fields']) && is_array($profile['visibility_fields'])) {
            $visibilityJson = json_encode($profile['visibility_fields'], JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            ':home_id' => $homeId,
            ':beds' => $profile['beds'] ?? null,
            ':baths' => $profile['baths'] ?? null,
            ':interior_sqft' => $profile['interior_sqft'] ?? null,
            ':style' => $profile['style'] ?? null,
            ':floors' => $profile['floors'] ?? null,
            ':basement_type' => $profile['basement_type'] ?? null,
            ':garage_type' => $profile['garage_type'] ?? null,
            ':garage_capacity' => $profile['garage_capacity'] ?? null,
            ':acreage' => $profile['acreage'] ?? null,
            ':year_built_override' => $profile['year_built_override'] ?? null,
            ':headline' => $profile['headline'] ?? null,
            ':summary' => $profile['summary'] ?? null,
            ':visibility_fields' => $visibilityJson,
            ':is_published' => !empty($profile['is_published']) ? 1 : 0,
            ':published_at' => $profile['published_at'] ?? null,
            ':updated_by_user_id' => $updatedByUserId,
        ]);
    }

    public function listPublishedByLocation(
        ?string $city,
        ?string $state,
        ?string $zip,
        ?string $minBeds,
        ?string $minBaths,
        ?int $seekerUserId,
        int $limit = 200
    ): array
    {
        $sql = "
            SELECT
                h.id AS home_id,
                h.nickname,
                h.address_line1,
                h.city,
                h.state,
                h.zip,
                h.year_built,
                h.owner_verification_status,
                p.beds,
                p.baths,
                p.interior_sqft,
                p.style,
                p.floors,
                p.basement_type,
                p.garage_type,
                p.garage_capacity,
                p.acreage,
                p.headline,
                p.visibility_fields,
                iq.state AS inquiry_state
            FROM homes h
            JOIN home_listing_profiles p ON p.home_id = h.id
            LEFT JOIN (
                SELECT i1.home_id, i1.state
                FROM home_inquiries i1
                INNER JOIN (
                    SELECT home_id, MAX(id) AS max_id
                    FROM home_inquiries
                    WHERE seeker_user_id = :seeker_uid
                    GROUP BY home_id
                ) latest ON latest.max_id = i1.id
            ) iq ON iq.home_id = h.id
            WHERE p.is_published = 1
        ";

        $params = [];
        if ($city !== null && $city !== '') {
            $sql .= " AND h.city LIKE :city ";
            $params[':city'] = $city . '%';
        }
        if ($state !== null && $state !== '') {
            $sql .= " AND h.state LIKE :state ";
            $params[':state'] = $state . '%';
        }
        if ($zip !== null && $zip !== '') {
            $sql .= " AND h.zip LIKE :zip ";
            $params[':zip'] = $zip . '%';
        }
        if ($minBeds !== null && $minBeds !== '') {
            $sql .= " AND p.beds >= :min_beds ";
            $params[':min_beds'] = $minBeds;
        }
        if ($minBaths !== null && $minBaths !== '') {
            $sql .= " AND p.baths >= :min_baths ";
            $params[':min_baths'] = $minBaths;
        }

        $sql .= "
            ORDER BY h.state ASC, h.city ASC, h.address_line1 ASC
            LIMIT :lim
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':seeker_uid', (int)($seekerUserId ?? 0), PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            if ($k === ':min_beds' || $k === ':min_baths') {
                $stmt->bindValue($k, (float)$v, PDO::PARAM_STR);
            } else {
                $stmt->bindValue($k, $v, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $visibility = [];
            if (!empty($row['visibility_fields']) && is_string($row['visibility_fields'])) {
                $decoded = json_decode($row['visibility_fields'], true);
                if (is_array($decoded)) {
                    $visibility = $decoded;
                }
            }
            $row['visibility_fields'] = $visibility;
        }
        unset($row);

        return $rows;
    }

    public function findPublishedDetailByHomeId(int $homeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                h.id AS home_id,
                h.nickname,
                h.address_line1,
                h.address_line2,
                h.city,
                h.state,
                h.zip,
                h.year_built,
                p.beds,
                p.baths,
                p.interior_sqft,
                p.style,
                p.floors,
                p.basement_type,
                p.garage_type,
                p.garage_capacity,
                p.acreage,
                p.year_built_override,
                p.headline,
                p.summary,
                p.visibility_fields,
                p.published_at,
                p.updated_at
            FROM homes h
            JOIN home_listing_profiles p ON p.home_id = h.id
            WHERE h.id = :home_id
              AND p.is_published = 1
            LIMIT 1
        ");
        $stmt->execute([':home_id' => $homeId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $visibility = [];
        if (!empty($row['visibility_fields']) && is_string($row['visibility_fields'])) {
            $decoded = json_decode($row['visibility_fields'], true);
            if (is_array($decoded)) {
                $visibility = $decoded;
            }
        }
        $row['visibility_fields'] = $visibility;

        return $row;
    }

    public function trustSummaryForHome(int $homeId): array
    {
        $summary = [
            'items_count' => 0,
            'tasks_count' => 0,
            'history_count' => 0,
            'approved_work_count' => 0,
        ];

        $stmtItems = $this->pdo->prepare("SELECT COUNT(*) FROM items WHERE home_id = :home_id");
        $stmtItems->execute([':home_id' => $homeId]);
        $summary['items_count'] = (int)$stmtItems->fetchColumn();

        $stmtTasks = $this->pdo->prepare("\n            SELECT COUNT(*)\n            FROM maintenance_tasks mt\n            JOIN items i ON i.id = mt.item_id\n            WHERE i.home_id = :home_id\n        ");
        $stmtTasks->execute([':home_id' => $homeId]);
        $summary['tasks_count'] = (int)$stmtTasks->fetchColumn();

        $stmtHistory = $this->pdo->prepare("\n            SELECT COUNT(*)\n            FROM task_history th\n            JOIN maintenance_tasks mt ON mt.id = th.task_id\n            JOIN items i ON i.id = mt.item_id\n            WHERE i.home_id = :home_id\n        ");
        $stmtHistory->execute([':home_id' => $homeId]);
        $summary['history_count'] = (int)$stmtHistory->fetchColumn();

        $stmtApproved = $this->pdo->prepare("\n            SELECT COUNT(*)\n            FROM job_submissions js\n            JOIN service_jobs sj ON sj.id = js.service_job_id\n            WHERE sj.home_id = :home_id\n              AND js.state = 'approved'\n        ");
        $stmtApproved->execute([':home_id' => $homeId]);
        $summary['approved_work_count'] = (int)$stmtApproved->fetchColumn();

        return $summary;
    }
}
