<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class ItemsRepository
{
    public function __construct(private PDO $pdo) {}

    public function findItemInHome(int $itemId, int $homeId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, home_id, brand, model, name
            FROM items
            WHERE id = :iid AND home_id = :hid
            LIMIT 1
        ");
        $stmt->execute([':iid' => $itemId, ':hid' => $homeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Returns:
     * - 'updated'   => row changed
     * - 'no_change' => row exists but values were the same
     * - 'not_found' => item not in this home (or doesn't exist)
     */
    public function updateItemInHome(int $homeId, int $itemId, array $row): string
    {
        $stmt = $this->pdo->prepare("
            UPDATE items
            SET name          = :name,
                category      = :category,
                brand         = :brand,
                model         = :model,
                serial_number = :serial,
                purchase_date = :purchase_date,
                cost          = :cost,
                notes         = :notes
            WHERE id = :id AND home_id = :home_id
            LIMIT 1
        ");

        $stmt->execute([
            ':id'            => $itemId,
            ':home_id'       => $homeId,
            ':name'          => (string)$row['name'],
            ':category'      => (string)$row['category'],
            ':brand'         => $row['brand'] ?? null,
            ':model'         => $row['model'] ?? null,
            ':serial'        => $row['serial_number'] ?? null,
            ':purchase_date' => $row['purchase_date'] ?? null,
            ':cost'          => $row['cost'] ?? null,
            ':notes'         => $row['notes'] ?? null,
        ]);

        if ($stmt->rowCount() > 0) {
            return 'updated';
        }

        // rowCount() == 0 could mean "no changes" OR "not found in this home".
        // Disambiguate with a fast existence check.
        $check = $this->pdo->prepare("
            SELECT 1
            FROM items
            WHERE id = :id AND home_id = :home_id
            LIMIT 1
        ");
        $check->execute([':id' => $itemId, ':home_id' => $homeId]);

        return $check->fetchColumn() ? 'no_change' : 'not_found';
    }

    public function insertItem(int $homeId, array $row): int
        {
            $stmt = $this->pdo->prepare("
                INSERT INTO items (home_id, name, category, brand, model, serial_number, purchase_date, cost, notes)
                VALUES (:home_id, :name, :category, :brand, :model, :serial, :purchase_date, :cost, :notes)
            ");
            $stmt->execute([
                ':home_id'       => $homeId,
                ':name'          => (string)$row['name'],
                ':category'      => (string)$row['category'],
                ':brand'         => $row['brand'] ?? null,
                ':model'         => $row['model'] ?? null,
                ':serial'        => $row['serial_number'] ?? null,
                ':purchase_date' => $row['purchase_date'] ?? null,
                ':cost'          => $row['cost'] ?? null,
                ':notes'         => $row['notes'] ?? null,
            ]);

            return (int)$this->pdo->lastInsertId();
        }

    public function deleteItemInHome(int $homeId, int $itemId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM items
            WHERE id = :id AND home_id = :home_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $itemId, ':home_id' => $homeId]);
        return $stmt->rowCount() > 0;
    }
}
