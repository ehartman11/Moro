<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\ItemsRepository;
use Moro\Services\ItemService;

final class UpdateItemController
{
    public function handle(): never
    {
        RequestContext::requirePost();
        $itemId   = (int)($_POST['id'] ?? 0);
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . "/public/items/index.php?item_id={$itemId}&tab=details");
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];
        $returnTo = $ctx['returnTo'];

        if (!isset($_POST['update_item'])) {
            Response::redirectToUrl($returnTo . '&err=bad_request');
        }

        Auth::requireCsrf($_POST['csrf'] ?? '');

        $svc = new ItemService(new ItemsRepository($pdo));

        try {
            $status = $svc->updateItemInHome($homeId, $itemId, $_POST);

            $flag = ($status === 'no_change') ? 'noop=1' : 'updated=1';

            Response::redirectToUrl(
                Paths::baseUrl() . "/public/items/index.php?item_id={$itemId}&tab=details&{$flag}"
            );

        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = ['item_required', 'item_bad_date', 'unauthorized'];
            if (!in_array($code, $allowed, true)) {
                $code = 'db_update_item';
            }

            Response::redirectToUrl($returnTo . '&err=' . $code);

        } catch (\Throwable) {
            Response::redirectToUrl($returnTo . '&err=db_update_item');
        }
    }
}