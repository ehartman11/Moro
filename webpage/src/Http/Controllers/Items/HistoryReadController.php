<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Throwable;
use Moro\Http\Controllers\JsonResponder;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\HistoryRepository;
use Moro\Repositories\PhotoRepository;
use Moro\Repositories\TaskRepository;
use Moro\Services\HistoryService;

final class HistoryReadController
{
    public function handle(): never
    {
        $ctx = RequestContext::homeContext();
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];
        $role = $ctx['role'];

        $itemId = (int)($_GET['item_id'] ?? 0);
        $historyId = (int)($_GET['history_id'] ?? 0);

        $svc = new HistoryService(
            new HistoryRepository($pdo),
            new PhotoRepository($pdo),
            new TaskRepository($pdo),
        );

        try {
            $data = $svc->readHistoryEntry($homeId, $itemId, $historyId, $role === 'owner');
            JsonResponder::send($data);
        } catch (Throwable) {
            JsonResponder::error('not_found', 404);
        }
    }
}