<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Throwable;
use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\HistoryRepository;
use Moro\Repositories\PhotoRepository;
use Moro\Repositories\TaskRepository;
use Moro\Services\HistoryService;

final class HistoryDeleteController
{
    public function handle(): never
    {
        RequestContext::requirePost();
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/items/index.php?tab=details');
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];
        Auth::requireCsrf($_POST['csrf'] ?? '');

        $itemId    = (int)($_POST['item_id'] ?? 0);
        $historyId = (int)($_POST['history_id'] ?? 0);

        $returnTo = (string)($_POST['return_to'] ?? (Paths::baseUrl() . '/public/items/index.php?item_id=' . $itemId . '&tab=history'));

        $svc = new HistoryService(
            new HistoryRepository($pdo),
            new PhotoRepository($pdo),
            new TaskRepository($pdo),
        );

        try {
            $svc->deleteHistoryEntry($homeId, $itemId, $historyId);
        } catch (Throwable) {
        }

        header('Location: ' . $returnTo);
        exit;
    }
}