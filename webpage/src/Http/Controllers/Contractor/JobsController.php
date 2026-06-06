<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Http\Controllers\JsonResponder;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Services\ServiceJobService;

final class JobsController
{
    public function listMine(): never
    {
        $pdo = Db::pdo();
        $userId = Auth::requireLogin();
        if (!Auth::hasContractorProfile($pdo, $userId)) {
            JsonResponder::error('contractor_profile_required', 403);
        }

        $service = new ServiceJobService(
            new ServiceJobsRepository($pdo)
        );

        try {
            $rows = $service->listForContractor($userId);
            JsonResponder::send([
                'ok' => true,
                'jobs' => $rows,
            ]);
        } catch (\Throwable) {
            JsonResponder::error('jobs_unavailable', 500);
        }
    }
}
