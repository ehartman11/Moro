<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Maintenance;

use Moro\Core\Paths;
use Moro\Http\Controllers\JsonResponder;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\MaintenanceRepository;
use Moro\Services\MaintenanceCardsService;

final class GetTaskCardController
{
    public function handle(): never
    {
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/maintenance/index.php');
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];

        $taskId = (int)($_GET['task_id'] ?? 0);
        if ($taskId <= 0) {
            JsonResponder::send(['ok' => false, 'error' => 'bad_request']);
        }

        $svc = new MaintenanceCardsService(new MaintenanceRepository($pdo));

        try {
            $card = $svc->getCardForHome($homeId, $taskId);
            JsonResponder::send(['ok' => true, 'card' => $card]);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $allowed = ['bad_request', 'not_found'];
            if (!in_array($msg, $allowed, true)) {
                $msg = 'error';
            }
            JsonResponder::send(['ok' => false, 'error' => $msg]);
        } catch (\Throwable) {
            JsonResponder::send(['ok' => false, 'error' => 'error']);
        }
    }
}