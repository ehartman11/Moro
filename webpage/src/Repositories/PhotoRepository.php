<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class PhotoRepository
{
    public function __construct(private PDO $pdo) {}

    public function insert(array $row): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO photos
              (home_id, item_id, task_id, history_id, storage_path, bytes, width, height, sha256, uploaded_by)
            VALUES
              (:home_id, :item_id, :task_id, :history_id, :storage_path, :bytes, :width, :height, :sha256, :uploaded_by)
        ");

        $stmt->execute([
            ':home_id'      => (int)$row['home_id'],
            ':item_id'      => (int)$row['item_id'],
            ':task_id'      => $row['task_id'] !== null ? (int)$row['task_id'] : null,
            ':history_id'   => (int)$row['history_id'],
            ':storage_path' => (string)$row['storage_path'],
            ':bytes'        => (int)$row['bytes'],
            ':width'        => (int)$row['width'],
            ':height'       => (int)$row['height'],
            ':sha256'       => (string)$row['sha256'],
            ':uploaded_by'  => $row['uploaded_by'] !== null ? (int)$row['uploaded_by'] : null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listForHistory(int $historyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, storage_path, created_at
            FROM photos
            WHERE history_id = :hid
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->execute([':hid' => $historyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $photoId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, home_id, item_id, task_id, history_id, storage_path, bytes, width, height, sha256, created_at
            FROM photos
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $photoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    public function listByHistory(int $historyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, storage_path, bytes, width, height, sha256, created_at
            FROM photos
            WHERE history_id = :hid
            ORDER BY id DESC
        ");
        $stmt->execute([':hid' => $historyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listFilePathsByHistoryIds(array $historyIds): array
        {
            $ids = array_values(array_filter(array_map('intval', $historyIds), fn($v) => $v > 0));
            if (empty($ids)) {
                return [];
            }

            $in = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $this->pdo->prepare("
                SELECT id, history_id, storage_path, created_at
                FROM photos
                WHERE history_id IN ($in)
                ORDER BY history_id ASC, created_at ASC, id ASC
            ");
            $stmt->execute($ids);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function listByHistoryIds(array $historyIds): array
        {
            $ids = array_values(array_filter(array_map('intval', $historyIds), fn($v) => $v > 0));
            if (!$ids) return [];

            $in = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $this->pdo->prepare("
                SELECT id, history_id, storage_path
                FROM photos
                WHERE history_id IN ($in)
                ORDER BY created_at DESC
            ");
            $stmt->execute($ids);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }


}
