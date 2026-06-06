<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class SeekerRepository
{
    public function __construct(private PDO $pdo) {}

    public function searchHomes(?string $city, ?string $state, ?string $zip, int $limit = 200): array
    {
        $sql = "
            SELECT id, nickname, address_line1, city, state, zip, year_built
            FROM homes
            WHERE 1=1
        ";

        $params = [];

        if ($city !== null && $city !== '') {
            $sql .= " AND city LIKE :city ";
            $params[':city'] = $city . '%';
        }

        if ($state !== null && $state !== '') {
            $sql .= " AND state LIKE :state ";
            $params[':state'] = $state . '%';
        }

        if ($zip !== null && $zip !== '') {
            $sql .= " AND zip LIKE :zip ";
            $params[':zip'] = $zip . '%';
        }

        $sql .= "
            ORDER BY state ASC, city ASC, address_line1 ASC
            LIMIT :lim
        ";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
