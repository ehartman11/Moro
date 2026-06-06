<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\ItemsRepository;
use Moro\Repositories\ManualRepository;
use Moro\Services\ManualService;

final class AddManualController
{
	public function handle(): never
	{
		RequestContext::requirePost();
		$ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/items/index.php?tab=maintenance');
		$pdo = $ctx['pdo'];
		$homeId = $ctx['homeId'];
		$returnTo = $ctx['returnTo'];

		Auth::requireCsrf($_POST['csrf'] ?? '');

		$itemId    = (int)($_POST['item_id'] ?? 0);
		$title     = trim((string)($_POST['manual_title'] ?? ''));
		$language  = trim((string)($_POST['language'] ?? 'english'));

		$sourceUrl = trim((string)($_POST['source_url'] ?? ''));
		$sourceUrl = ($sourceUrl !== '') ? $sourceUrl : null;

		if ($itemId <= 0 || $title === '') {
			Response::redirectToUrl($returnTo . '&err=manual_invalid');
		}

		$itemRepo = new ItemsRepository($pdo);
		$item = $itemRepo->findItemInHome($itemId, $homeId);
		if (!$item) {
			Response::redirectToUrl($returnTo . '&err=unauthorized');
		}

		$svc = new ManualService(
			new ManualRepository($pdo),
			$itemRepo
		);

		try {
			$svc->addManual($item, $title, $language, $sourceUrl, $_FILES['manual_pdf'] ?? []);
			Response::redirectToUrl($returnTo . '&manual_added=1');

		} catch (\InvalidArgumentException $e) {
			$code = $e->getMessage();
			Response::redirectToUrl($returnTo . '&err=' . urlencode($code));

		} catch (\Throwable) {
			Response::redirectToUrl($returnTo . '&err=manual_failed');
		}
	}
}
