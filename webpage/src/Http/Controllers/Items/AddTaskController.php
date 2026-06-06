<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\HistoryRepository;
use Moro\Repositories\ItemsRepository;
use Moro\Repositories\MaintenanceUnitOptionsRepository;
use Moro\Repositories\PhotoRepository;
use Moro\Repositories\ScheduleRepository;
use Moro\Repositories\TaskRepository;
use Moro\Services\PhotoService;
use Moro\Services\TaskService;

final class AddTaskController
{
	public function handle(): never
	{
		RequestContext::requirePost();
		$itemId   = (int)($_POST['item_id'] ?? 0);
		$ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . "/public/items/index.php?item_id={$itemId}&tab=maintenance");
		$pdo = $ctx['pdo'];
		$homeId = $ctx['homeId'];
		$returnTo = $ctx['returnTo'];

		if (!isset($_POST['add_task'])) {
			Response::redirectToUrl($returnTo . '&err=bad_request');
		}

		Auth::requireCsrf($_POST['csrf'] ?? '');

		$svc = new TaskService(
			$pdo,
			new ItemsRepository($pdo),
			new TaskRepository($pdo),
			new HistoryRepository($pdo),
			new ScheduleRepository($pdo),
			new MaintenanceUnitOptionsRepository($pdo),
			new PhotoService(new PhotoRepository($pdo))
		);

		try {
			$svc->addTaskToItemInHome(
				$homeId,
				(int)($_POST['item_id'] ?? 0),
				(string)($_POST['task_name'] ?? ''),
				($_POST['description'] ?? null),
				(string)($_POST['part_name'] ?? ''),
				(string)($_POST['schedule_type'] ?? ''),
				(int)($_POST['frequency_value'] ?? 0),
				(string)($_POST['frequency_unit'] ?? ''),
				(string)($_POST['priority'] ?? 'medium'),
				($_POST['first_due_date'] ?? null)
			);

			Response::redirectToUrl($returnTo . '&task_added=1');

		} catch (\InvalidArgumentException $e) {
			$code = $e->getMessage();
			$allowed = ['task_invalid', 'task_add_requires_due', 'task_bad_due', 'unauthorized'];
			if (!in_array($code, $allowed, true)) {
				$code = 'task_add_failed';
			}

			Response::redirectToUrl($returnTo . '&err=' . $code);

		} catch (\Throwable) {
			Response::redirectToUrl($returnTo . '&err=task_add_failed');
		}
	}
}
