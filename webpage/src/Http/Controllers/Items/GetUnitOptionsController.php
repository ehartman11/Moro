<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Throwable;
use Moro\Http\Controllers\JsonResponder;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\MaintenanceUnitOptionsRepository;

final class GetUnitOptionsController
{
    public function handle(): never
    {
        $ctx = RequestContext::homeContext();
        $pdo = $ctx['pdo'];

        $scheduleType = trim((string)($_GET['schedule_type'] ?? ''));
        if ($scheduleType === '') {
            JsonResponder::send(['ok' => false, 'units' => []]);
        }

        $valid = ['calendar', 'per_use', 'seasonal', 'condition', 'metered'];
        if (!in_array($scheduleType, $valid, true)) {
            JsonResponder::send(['ok' => false, 'units' => []]);
        }

        $repo = new MaintenanceUnitOptionsRepository($pdo);

        try {
            $units = $repo->listUnitsForType($scheduleType);

            JsonResponder::send([
                'ok' => true,
                'units' => $units,
            ]);

        } catch (Throwable) {
            JsonResponder::send(['ok' => false, 'units' => []]);
        }
    }
}