<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\ItemsRepository;
use Moro\Services\ItemService;

final class DeleteItemController
{
    public function handle(): never
    {
        RequestContext::requirePost();
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/items/index.php?tab=details');
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];
        $returnTo = $ctx['returnTo'];

        if (!isset($_POST['delete_item'])) {
            Response::redirectToUrl($returnTo . '&err=bad_request');
        }

        Auth::requireCsrf($_POST['csrf'] ?? '');

        $itemId = (int)($_POST['id'] ?? 0);

        $svc = new ItemService(new ItemsRepository($pdo));

        try {
            $svc->deleteItemInHome($homeId, $itemId);

            Response::redirectToUrl(Paths::baseUrl() . '/public/items/index.php?deleted=1&tab=details');

        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = ['delete_invalid', 'unauthorized'];
            if (!in_array($code, $allowed, true)) {
                $code = 'db_delete_item';
            }

            Response::redirectToUrl($returnTo . '&err=' . $code);

        } catch (\Throwable) {
            Response::redirectToUrl($returnTo . '&err=db_delete_item');
        }
    }
}