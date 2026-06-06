<?php
declare(strict_types=1);

namespace Moro\Http\Controllers;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Http\Controllers\JsonResponder;
use Moro\Repositories\TicklerRepository;
use Moro\Services\TicklerService;
use InvalidArgumentException;
use PDOException;

final class TicklerApiController
{
    public function handle(): never
    {
        Auth::requireLogin();
        $homeId = Auth::requireActiveHome();
        

        // (Optional future enhancement) verify user has permission on home
        // For now this matches your current behavior.

        $pdo  = Db::pdo();
        $repo = new TicklerRepository($pdo);
        $svc  = new TicklerService($repo);

        $action = $_GET['action'] ?? '';

        try {
            if ($action === 'month') {
                $year  = (int)($_GET['year'] ?? 0);
                $month = (int)($_GET['month'] ?? 0);
                JsonResponder::send($svc->month($homeId, $year, $month));
            }

            if ($action === 'day') {
                $date = (string)($_GET['date'] ?? '');
                JsonResponder::send($svc->day($homeId, $date));
            }

            JsonResponder::error('Unknown action', 400);

        } catch (InvalidArgumentException $e) {
            JsonResponder::error($e->getMessage(), 400);
        } catch (PDOException $e) {
            // Keep current behavior (but ideally log server-side later)
            JsonResponder::error($e->getMessage(), 500);
        }
    }
}
