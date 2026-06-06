<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class HomeInquiryRepository
{
    public function __construct(private PDO $pdo) {}

    public function insertInquiry(int $homeId, int $seekerUserId, string $message): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO home_inquiries (home_id, seeker_user_id, message, state, opened_at)
            VALUES (:home_id, :seeker_user_id, :message, 'open', NOW())
        ");
        $stmt->execute([
            ':home_id' => $homeId,
            ':seeker_user_id' => $seekerUserId,
            ':message' => $message,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listForOwnerHome(int $homeId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                hi.id,
                hi.home_id,
                hi.seeker_user_id,
                hi.message,
                hi.owner_response,
                hi.state,
                hi.opened_at,
                hi.responded_at,
                hi.created_at,
                hi.updated_at,
                u.fname,
                u.lname
            FROM home_inquiries hi
            JOIN users u ON u.id = hi.seeker_user_id
            WHERE hi.home_id = :home_id
            ORDER BY hi.opened_at DESC, hi.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':home_id', $homeId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findInHome(int $inquiryId, int $homeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, home_id, seeker_user_id, state
            FROM home_inquiries
            WHERE id = :id AND home_id = :home_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $inquiryId,
            ':home_id' => $homeId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function respondInHome(int $inquiryId, int $homeId, string $response, string $state): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE home_inquiries
            SET owner_response = :owner_response,
                state = :state,
                responded_at = NOW(),
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND home_id = :home_id
            LIMIT 1
        ");
        $stmt->execute([
            ':owner_response' => $response,
            ':state' => $state,
            ':id' => $inquiryId,
            ':home_id' => $homeId,
        ]);
    }
}
