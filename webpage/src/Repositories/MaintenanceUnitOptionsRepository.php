<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class MaintenanceUnitOptionsRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Returns requires_value (0/1) if the unit is allowed for this schedule type, otherwise null.
     */
    public function getRequiresValue(string $scheduleType, string $unit): ?int
    {
        $stmt = $this->pdo->prepare("
            SELECT requires_value
            FROM maintenance_unit_options
            WHERE schedule_type = :st AND unit = :u
            LIMIT 1
        ");
        $stmt->execute([':st' => $scheduleType, ':u' => $unit]);
        $val = $stmt->fetchColumn();
        return ($val === false) ? null : (int)$val;
    }

    public function listUnitsForType(string $scheduleType): array
    {
        $stmt = $this->pdo->prepare("
            SELECT unit, requires_value
            FROM maintenance_unit_options
            WHERE schedule_type = :st
            ORDER BY sort_order ASC, unit ASC
        ");
        $stmt->execute([':st' => $scheduleType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
