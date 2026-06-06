<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Repositories\MaintenanceRepository;
use InvalidArgumentException;

final class MaintenanceCardsService
{
    public function __construct(private MaintenanceRepository $repo) {}

    public function getTreeForHome(int $homeId): array
    {
        if ($homeId <= 0) throw new InvalidArgumentException('bad_request');
        return $this->repo->listTaskTreeForHome($homeId);
    }

    public function getCardForHome(int $homeId, int $taskId): array
    {
        if ($homeId <= 0 || $taskId <= 0) throw new InvalidArgumentException('bad_request');

        $row = $this->repo->getTaskCard($homeId, $taskId);
        if (!$row) throw new InvalidArgumentException('not_found');

        return $row;
    }

    public function getDraftForHome(int $homeId, int $taskId): array 
    {
        if ($homeId <= 0 || $taskId <= 0) throw new InvalidArgumentException('bad_request');

        $row = $this->repo->getDraftCard($homeId, $taskId);
        if (!$row) throw new InvalidArgumentException('not_found');

        return $row;
    }
}
