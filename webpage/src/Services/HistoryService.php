<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Core\Paths;
use Moro\Repositories\HistoryRepository;
use Moro\Repositories\PhotoRepository;
use Moro\Repositories\TaskRepository;
use InvalidArgumentException;

final class HistoryService
{
    public function __construct(
        private HistoryRepository $historyRepo,
        private PhotoRepository $photoRepo,
        private TaskRepository $taskRepo,
    ) {}

    /**
     * Existing: History tab data (render-only).
     */
    public function getHistoryTabData(int $itemId, int $homeId, string $role): array
    {
        $historyRows = $this->historyRepo->listForItemInHome($homeId,$itemId);

        $photosByHistory = [];
        if (!empty($historyRows)) {
            $historyIds = array_map(fn($r) => (int)$r['history_id'], $historyRows);
            $photoRows = $this->photoRepo->listByHistoryIds($historyIds);

            foreach ($photoRows as $row) {
                $hid = (int)$row['history_id'];
                $photosByHistory[$hid] ??= [];
                $photosByHistory[$hid][] = [
                    'id' => (int)$row['id'],
                    'label' => 'Photo ' . (count($photosByHistory[$hid]) + 1),
                    'created_at' => $row['created_at'] ?? null,
                ];
            }
        }

        $tasks = [];
        if ($role === 'owner') {
            $tasks = $this->taskRepo->listForItem($itemId);
        }

        return [
            'historyRows'     => $historyRows,
            'photosByHistory' => $photosByHistory,
            'tasks'           => $tasks,
        ];
    }

    /**
     * For history_read.php: fetch a single entry safely scoped by home+item and attach photos.
     */
    public function readHistoryEntry(int $homeId, int $itemId, int $historyId, bool $canEdit): array
    {
        if ($homeId <= 0 || $itemId <= 0 || $historyId <= 0) {
            throw new InvalidArgumentException('bad_request');
        }

        $row = $this->historyRepo->findForHomeItem($homeId, $itemId, $historyId);
        if (!$row) {
            throw new InvalidArgumentException('not_found');
        }

        $photoRows = $this->photoRepo->listByHistory($historyId);
        $photos = [];
        $i = 1;
        foreach ($photoRows as $p) {
            $pid = (int)$p['id'];
            $photos[] = [
                'id' => $pid,
                'label' => 'Photo ' . $i++,
                'url' => Paths::baseUrl() . '/public/photos/view.php?id=' . $pid,
            ];
        }

        return [
            'history_id' => (int)$row['history_id'],
            'task_id'    => (int)$row['task_id'],
            'done_date'  => (string)$row['completed_on'],
            'cost'       => (string)$row['cost'],
            'note'       => (string)($row['note'] ?? ''),
            'task_name'  => (string)$row['task_name'],
            'photos'     => $photos,
            'can_edit'   => $canEdit,
        ];
    }

    /**
     * For history_update.php: validate and then call repository update.
     */
    public function updateHistoryEntry(
        int $homeId,
        int $itemId,
        int $historyId,
        int $taskId,
        string $completedOnYmd,
        float $cost,
        ?string $note
    ): void {
        if ($homeId <= 0 || $itemId <= 0 || $historyId <= 0 || $taskId <= 0) {
            throw new InvalidArgumentException('bad_request');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $completedOnYmd)) {
            throw new InvalidArgumentException('date');
        }

        if ($cost < 0) {
            throw new InvalidArgumentException('cost');
        }

        if ($note !== null && function_exists('mb_strlen') && mb_strlen($note) > 5000) {
            throw new InvalidArgumentException('note');
        }

        $ok = $this->historyRepo->updateForHomeItem(
            $homeId,
            $itemId,
            $historyId,
            $taskId,
            $note,
            $cost,
            $completedOnYmd
        );

        if (!$ok) {
            throw new InvalidArgumentException('not_found');
        }
    }

    /**
     * For history_delete.php: call repository delete.
     * Photos are deleted via FK photos.history_id ON DELETE CASCADE (your schema has it).
     */
    public function deleteHistoryEntry(int $homeId, int $itemId, int $historyId): void
    {
        if ($homeId <= 0 || $itemId <= 0 || $historyId <= 0) {
            throw new InvalidArgumentException('bad_request');
        }

        $ok = $this->historyRepo->deleteForHomeItem($homeId, $itemId, $historyId);
        if (!$ok) {
            throw new InvalidArgumentException('not_found');
        }
    }
}