<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use InvalidArgumentException;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Services\ServiceJobService;

final class ContractorMyJobsController
{
    public function index(int $homeId, int $contractorUserId, ServiceJobService $service): array
    {
        $rows = [];
        $flashError = '';

        try {
            $rows = $service->listForContractorInHome($homeId, $contractorUserId);
        } catch (InvalidArgumentException $e) {
            $code = $e->getMessage();
            $flashError = match ($code) {
                'unauthorized' => 'You are not authorized for this view.',
                default => 'Unable to load your jobs.',
            };
        } catch (\Throwable) {
            $flashError = 'Unable to load your jobs.';
        }

        return [
            'rows' => $rows,
            'flashError' => $flashError,
        ];
    }

    public function buildDefaultService(): ServiceJobService
    {
        return new ServiceJobService(new ServiceJobsRepository(\Moro\Core\Db::pdo()));
    }
}
