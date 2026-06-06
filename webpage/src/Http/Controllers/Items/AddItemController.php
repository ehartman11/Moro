<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\ItemsRepository;
use Moro\Services\ItemService;

final class AddItemController
{
	public function handle(): never
	{
		RequestContext::requirePost();
		$ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/items/index.php?tab=details');
		$pdo = $ctx['pdo'];
		$homeId = $ctx['homeId'];
		$returnTo = $ctx['returnTo'];

		if (!isset($_POST['add_item'])) {
			Response::redirectToUrl($returnTo . '&err=bad_request');
		}

		Auth::requireCsrf($_POST['csrf'] ?? '');

		$svc = new ItemService(new ItemsRepository($pdo));

		try {
			$newId = $svc->addItemToHome($homeId, $_POST);

			Response::redirectToUrl(
				Paths::baseUrl() . "/public/items/index.php?item_id={$newId}&tab=details&added=1"
			);

		} catch (\InvalidArgumentException $e) {
			$code = $e->getMessage();
			$allowed = ['item_required', 'item_bad_date'];
			if (!in_array($code, $allowed, true)) {
				$code = 'db_add_item';
			}

			Response::redirectToUrl($returnTo . '&err=' . $code);

		} catch (\Throwable) {
			Response::redirectToUrl($returnTo . '&err=db_add_item');
		}
	}
}
