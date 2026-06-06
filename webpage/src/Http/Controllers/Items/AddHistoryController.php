<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\HistoryRepository;
use Moro\Repositories\PhotoRepository;
use Moro\Repositories\ScheduleRepository;
use Moro\Repositories\TaskRepository;
use Moro\Services\PhotoService;
use Moro\Services\TaskCompletionService;

final class AddHistoryController
{
	public function handle(): never
	{
		$itemId   = (int)($_POST['item_id'] ?? 0);
		RequestContext::requirePost();
		$ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . "/public/items/index.php?item_id={$itemId}&tab=history");
		$pdo = $ctx['pdo'];
		$userId = $ctx['userId'];
		$homeId = $ctx['homeId'];
		$returnTo = $ctx['returnTo'];

		if (!isset($_POST['add_history'])) {
			Response::redirectToUrl($returnTo . '&err=bad_request');
		}

		Auth::requireCsrf($_POST['csrf'] ?? '');

		$taskId   = (int)($_POST['task_id'] ?? 0);
		$doneDate = trim((string)($_POST['done_date'] ?? ''));

		if ($taskId <= 0 || $doneDate === '') {
			Response::redirectToUrl($returnTo . '&err=history_required');
		}

		$note    = trim((string)($_POST['note'] ?? ''));
		$costRaw = $_POST['cost'] ?? '';
		$cost    = ($costRaw !== '' && $costRaw !== null) ? (float)$costRaw : 0.00;

		$photoFile = $_FILES['photo'] ?? null;

		$svc = new TaskCompletionService(
			$pdo,
			new TaskRepository($pdo),
			new HistoryRepository($pdo),
			new ScheduleRepository($pdo),
			new PhotoService(new PhotoRepository($pdo))
		);

		try {
			$svc->recordCompletionAndAdvance(
				$homeId,
				$taskId,
				$itemId,
				$doneDate,
				($note !== '' ? $note : null),
				$cost,
				$photoFile,
				$userId
			);

			Response::redirectToUrl($returnTo . '&history_saved=1');

		} catch (\InvalidArgumentException $e) {
			$code = $e->getMessage();

			$allowed = [
				'history_required',
				'history_bad_date',
				'complete_invalid',
				'complete_not_found',
				'unauthorized',
				'photo_upload',
				'photo_too_large',
				'photo_not_image',
				'photo_type',
				'photo_decode_failed',
				'photo_invalid',
			];

			if (!in_array($code, $allowed, true)) {
				$code = 'history_failed';
			}

			if ($code === 'complete_invalid') {
				$code = 'history_bad_date';
			}
			if ($code === 'complete_not_found') {
				$code = 'unauthorized';
			}

			Response::redirectToUrl($returnTo . '&err=' . $code);

		} catch (\Throwable) {
			Response::redirectToUrl($returnTo . '&err=history_failed');
		}
	}
}
