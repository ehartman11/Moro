<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Repositories\MaintenanceRepository;

final class MaintenanceViewService
{
    public function __construct(private MaintenanceRepository $repo) {}

    public function getMaintenanceTabData(int $itemId): array
    {
        $tasks = $this->repo->listTasksWithNextDueForItem($itemId);
        $manuals = $this->repo->listManualsForItem($itemId);

        return [
            'tasks' => $tasks,
            'manuals' => $manuals,
        ];
    }
}
