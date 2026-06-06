<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Repositories\TaskRepository;
use Moro\Repositories\HistoryRepository;
use Moro\Repositories\ScheduleRepository;
use Moro\Repositories\ItemsRepository;
use Moro\Repositories\MaintenanceUnitOptionsRepository;
use Moro\Services\DueDateCalculator;

use PDO;
use Throwable;
use InvalidArgumentException;

final class TaskService
{
    public function __construct(
        private PDO $pdo,
        private ItemsRepository $items,
        private TaskRepository $tasks,
        private HistoryRepository $history,
        private ScheduleRepository $schedule,
        private MaintenanceUnitOptionsRepository $unitOptions,
        private PhotoService $photoService
    ) {}

    /**
     * Writes a history row and advances schedule based on completion date.
     * Returns new history id.
     */
    public function recordCompletionAndAdvance(
        int $activeHomeId,
        int $taskId,
        ?int $itemId,
        string $completedOnYmd,
        ?string $note,
        float $cost,
        ?array $photoFile = null,
        ?int $uploadedBy = null
    ): int {
        if ($taskId <= 0) throw new InvalidArgumentException('Invalid task');
        if (!DueDateCalculator::isValidYmd($completedOnYmd)) throw new InvalidArgumentException('Invalid date');

        $note = $note !== null ? trim($note) : '';
        $uploadedBy = $uploadedBy && $uploadedBy > 0 ? $uploadedBy : null;

        try {
            $this->pdo->beginTransaction();

            // Locks relevant rows; confirms home scope; returns freq settings
            $task = $this->tasks->lockTaskForCompletion($activeHomeId, $taskId, $itemId);

            if (!$task) {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('Not found / unauthorized');
            }

            $historyId = $this->history->insertHistory(
                $taskId,
                ($note !== '' ? $note : null),
                $cost,
                $completedOnYmd
            );

            // NEW: handle uploaded photo (optional)
            if (is_array($photoFile) && ($photoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                // item_id might not have been passed; you can safely derive from locked $task if you include it in the query
                $resolvedItemId = (int)($task['item_id'] ?? ($itemId ?? 0));
                if ($resolvedItemId <= 0) {
                    throw new InvalidArgumentException('photo_missing_item');
                }
                // $task returned from lockTaskForCompletion now includes item_id and task_id
                if ($photoFile !== null && !empty($photoFile)) {
                    $this->photoService->storeHistoryPhoto(
                        $activeHomeId,
                        (int)$task['item_id'],
                        (int)$task['task_id'],
                        $historyId,
                        $photoFile,
                        $uploadedBy
                    );
                }
            }

            $nextDue = DueDateCalculator::nextDue(
                $completedOnYmd,
                (int)$task['frequency_value'],
                (string)$task['frequency_unit']
            );

            $this->schedule->updateDueDate($taskId, $nextDue);

            $this->pdo->commit();
            return $historyId;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

     public function addTaskToItemInHome(
        int $homeId,
        int $itemId,
        string $taskName,
        ?string $description,
        string $partName,
        string $scheduleType,
        int $frequencyValue,
        string $frequencyUnit,
        string $priority,
        ?string $firstDueDate // YYYY-MM-DD optional depending on type
    ): int {
        $taskName = trim($taskName);
        $description = $description !== null ? trim($description) : null;
        if ($description === '') $description = null;

        $partName = trim($partName);
        if ($partName === '') $partName = 'General';

        $scheduleType = trim($scheduleType);
        $frequencyUnit = trim($frequencyUnit);
        $priority = trim($priority);
        $firstDueDate = $firstDueDate !== null ? trim($firstDueDate) : null;
        if ($firstDueDate === '') $firstDueDate = null;

        $validScheduleTypes = ['calendar','per_use','seasonal','condition','metered'];
        $validPriority = ['low','medium','high'];

        if ($itemId <= 0 || $taskName === '') throw new InvalidArgumentException('task_invalid');
        if (!in_array($scheduleType, $validScheduleTypes, true)) throw new InvalidArgumentException('task_invalid');
        if (!in_array($priority, $validPriority, true)) throw new InvalidArgumentException('task_invalid');

        // Ensure item belongs to active home
        $itemRow = $this->items->findItemInHome($itemId, $homeId);
        if (!$itemRow) throw new InvalidArgumentException('unauthorized');

        // Unit validation (DB-driven mapping)
        $requiresValue = null;
        if ($frequencyUnit !== '') {
            $requiresValue = $this->unitOptions->getRequiresValue($scheduleType, $frequencyUnit);
        }

        // Validate + normalize per schedule type (preserves your behavior)
        switch ($scheduleType) {
            case 'calendar':
            case 'condition':
            case 'metered':
                if ($requiresValue === null || (int)$requiresValue !== 1 || $frequencyValue <= 0) {
                    throw new InvalidArgumentException('task_invalid');
                }
                break;

            case 'per_use':
                if ($frequencyUnit !== '' && ($requiresValue === null || (int)$requiresValue !== 0)) {
                    throw new InvalidArgumentException('task_invalid');
                }
                $frequencyValue = 1; // normalize
                break;

            case 'seasonal':
                // For MVP: require explicit due date
                if ($firstDueDate === null || !DueDateCalculator::isValidYmd($firstDueDate)) {
                    throw new InvalidArgumentException('task_add_requires_due');
                }
                $frequencyValue = 1;
                $frequencyUnit = 'months'; // placeholder if DB column is NOT NULL
                break;

            default:
                throw new InvalidArgumentException('task_invalid');
        }

        // Compute due date
        $dueDate = null;

        if ($firstDueDate !== null) {
            if (!DueDateCalculator::isValidYmd($firstDueDate)) {
                throw new InvalidArgumentException('task_bad_due');
            }
            $dueDate = $firstDueDate;
        } else {
            // auto schedule (preserves your current model)
            $today = (new \DateTime('today'))->format('Y-m-d');

            if ($scheduleType === 'calendar' || $scheduleType === 'condition') {
                $dueDate = DueDateCalculator::nextDue($today, $frequencyValue, $frequencyUnit);
            } elseif ($scheduleType === 'per_use' || $scheduleType === 'metered') {
                // sentinel "due today"
                $dueDate = $today;
            } else {
                // seasonal should have firstDueDate already
                $dueDate = $today;
            }
        }

        try {
            $this->pdo->beginTransaction();

            $taskId = $this->tasks->insertTask([
                'item_id'          => $itemId,
                'task_name'        => $taskName,
                'description'      => $description,
                'part_name'        => $partName,
                'schedule_type'    => $scheduleType,
                'frequency_value'  => $frequencyValue,
                'frequency_unit'   => $frequencyUnit,
                'priority'         => $priority,
            ]);

            $this->schedule->insertScheduleRow($taskId, $dueDate);

            $this->pdo->commit();
            return $taskId;

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
