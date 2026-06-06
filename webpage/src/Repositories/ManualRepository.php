<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class ManualRepository
{
    public function __construct(private PDO $pdo) {}

    public function insertManual(array $row): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO manuals
              (item_id, title, brand, model, language, source_url, storage_path, file_hash, page_count)
            VALUES
              (:item_id, :title, :brand, :model, :language, :source_url, :storage_path, :file_hash, :page_count)
        ");

        $stmt->execute([
            ':item_id'      => (int)$row['item_id'],
            ':title'        => (string)$row['title'],
            ':brand'        => $row['brand'] ?? null,
            ':model'        => $row['model'] ?? null,
            ':language'     => (string)$row['language'],
            ':source_url'   => $row['source_url'] ?? null,
            ':storage_path' => (string)$row['storage_path'],
            ':file_hash'    => (string)$row['file_hash'],
            ':page_count'   => $row['page_count'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $manualId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, item_id, title, storage_path
            FROM manuals
            WHERE id = :mid
            LIMIT 1
        ");
        $stmt->execute([':mid' => $manualId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

}
