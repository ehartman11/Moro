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

final class CompleteTaskController
{
	public function handle(): never
	{
		RequestContext::requirePost();
		$taskId = (int)($_POST['task_id'] ?? 0);
		$itemId = (int)($_POST['item_id'] ?? 0);

		$ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . "/public/items/index.php?item_id={$itemId}&tab=maintenance");
		$pdo = $ctx['pdo'];
		$homeId = $ctx['homeId'];
		$returnTo = $ctx['returnTo'];

		if (!isset($_POST['complete_task'])) {
			Response::redirectToUrl($returnTo . '&err=bad_request');
		}

		Auth::requireCsrf($_POST['csrf'] ?? '');

		$completedOn = trim((string)($_POST['completed_on'] ?? ''));
		$note        = trim((string)($_POST['note'] ?? ''));
		$cost        = (($_POST['cost'] ?? '') !== '') ? (float)$_POST['cost'] : 0.0;

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
				$completedOn,
				($note !== '' ? $note : null),
				$cost
			);

			Response::redirectToUrl($returnTo . '&completed=1');

		} catch (\InvalidArgumentException $e) {
			$code = $e->getMessage();
			$allowed = ['complete_invalid', 'complete_not_found'];
			if (!in_array($code, $allowed, true)) {
				$code = 'complete_failed';
			}

			Response::redirectToUrl($returnTo . '&err=' . $code);

		} catch (\Throwable) {
			Response::redirectToUrl($returnTo . '&err=complete_failed');
		}
	}
}
