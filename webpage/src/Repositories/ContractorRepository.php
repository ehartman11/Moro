<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class ContractorRepository
{
    public function __construct(private PDO $pdo) {}

    public function findProfileByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, user_id, business_name, display_name, phone, email, website,
                     service_categories, license_number, license_state, insured, verification_status, bio,
                   created_at, updated_at
            FROM contractor_profiles
            WHERE user_id = :uid
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $categories = [];
        if (!empty($row['service_categories']) && is_string($row['service_categories'])) {
            $decoded = json_decode($row['service_categories'], true);
            if (is_array($decoded)) {
                $categories = $decoded;
            }
        }

        $row['service_categories'] = $categories;
        $row['insured'] = (int)$row['insured'];

        return $row;
    }

    public function upsertProfile(int $userId, array $profile): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO contractor_profiles (
                user_id, business_name, display_name, phone, email, website,
                service_categories, license_number, license_state, insured, bio
            ) VALUES (
                :uid, :business_name, :display_name, :phone, :email, :website,
                :service_categories, :license_number, :license_state, :insured, :bio
            )
            ON DUPLICATE KEY UPDATE
                business_name = VALUES(business_name),
                display_name = VALUES(display_name),
                phone = VALUES(phone),
                email = VALUES(email),
                website = VALUES(website),
                service_categories = VALUES(service_categories),
                license_number = VALUES(license_number),
                license_state = VALUES(license_state),
                insured = VALUES(insured),
                bio = VALUES(bio)
        ");

        $categoriesJson = null;
        if (!empty($profile['service_categories']) && is_array($profile['service_categories'])) {
            $categoriesJson = json_encode(array_values($profile['service_categories']), JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            ':uid' => $userId,
            ':business_name' => (string)$profile['business_name'],
            ':display_name' => $profile['display_name'] ?? null,
            ':phone' => $profile['phone'] ?? null,
            ':email' => $profile['email'] ?? null,
            ':website' => $profile['website'] ?? null,
            ':service_categories' => $categoriesJson,
            ':license_number' => $profile['license_number'] ?? null,
            ':license_state' => $profile['license_state'] ?? null,
            ':insured' => !empty($profile['insured']) ? 1 : 0,
            ':bio' => $profile['bio'] ?? null,
        ]);
    }

    public function listContractorOptions(int $limit = 200): array
    {
        $stmt = $this->pdo->prepare("\n            SELECT\n                cp.user_id,\n                cp.business_name,\n                cp.display_name,\n                cp.license_state,\n                cp.service_categories,\n                u.fname,\n                u.lname\n            FROM contractor_profiles cp\n            JOIN users u ON u.id = cp.user_id\n            ORDER BY cp.business_name ASC, cp.display_name ASC\n            LIMIT :lim\n        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return [];
        }

        return array_map(static function (array $row): array {
            $personName = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
            $categories = [];
            $rawCategories = $row['service_categories'] ?? null;
            if (is_string($rawCategories) && trim($rawCategories) !== '') {
                $decoded = json_decode($rawCategories, true);
                if (is_array($decoded)) {
                    $categories = array_values(array_filter(array_map(
                        static fn(mixed $value): string => trim((string)$value),
                        $decoded
                    ), static fn(string $value): bool => $value !== ''));
                }
            }

            return [
                'user_id' => (int)$row['user_id'],
                'business_name' => (string)($row['business_name'] ?? ''),
                'display_name' => (string)($row['display_name'] ?? ''),
                'person_name' => $personName,
                'license_state' => trim((string)($row['license_state'] ?? '')),
                'service_categories' => $categories,
            ];
        }, $rows);
    }
}
