<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use InvalidArgumentException;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Services\ServiceJobService;

final class HomeownerJobsController
{
    public function index(int $homeId, int $homeownerUserId, array $query, ServiceJobService $service): array
    {
        $states = ['all', 'open', 'assigned', 'in_progress', 'completed', 'cancelled'];
        $state = isset($query['state']) ? trim((string)$query['state']) : 'all';
        if ($state === '') {
            $state = 'all';
        }

        $rows = [];
        $counts = [
            'all' => 0,
            'open' => 0,
            'assigned' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        $flashError = '';

        try {
            $rows = $service->listForHomeownerInHome($homeId, $homeownerUserId, $state);

            $allRows = $service->listForHomeownerInHome($homeId, $homeownerUserId, 'all');
            $counts['all'] = count($allRows);
            foreach ($allRows as $row) {
                $rowState = (string)($row['state'] ?? '');
                if (isset($counts[$rowState])) {
                    $counts[$rowState]++;
                }
            }
        } catch (InvalidArgumentException $e) {
            $code = $e->getMessage();
            $flashError = match ($code) {
                'job_state_invalid' => 'Invalid status filter.',
                'unauthorized' => 'You are not authorized for this view.',
                default => 'Unable to load service jobs.',
            };
        } catch (\Throwable) {
            $flashError = 'Unable to load service jobs.';
        }

        return [
            'state' => $state,
            'states' => $states,
            'counts' => $counts,
            'rows' => $rows,
            'flashError' => $flashError,
        ];
    }

    public function buildDefaultService(): ServiceJobService
    {
        return new ServiceJobService(new ServiceJobsRepository(\Moro\Core\Db::pdo()));
    }
}
