<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Repositories\HistoryRepository;
use Moro\Repositories\ScheduleRepository;
use Moro\Repositories\TaskRepository;
use PDO;
use Throwable;
use InvalidArgumentException;

final class TaskCompletionService
{
    public function __construct(
        private PDO $pdo,
        private TaskRepository $tasks,
        private HistoryRepository $history,
        private ScheduleRepository $schedule,
        private PhotoService $photos
    ) {}

    /**
     * Writes a history row and advances schedule based on completion date.
     * Optionally stores an uploaded photo (converted to JPEG).
     * Returns inserted history id.
     */
    public function recordCompletionAndAdvance(
        int $homeId,
        int $taskId,
        ?int $itemId,
        string $completedOnYmd,
        ?string $note,
        float $cost,
        ?array $photoFile = null,
        ?int $uploadedBy = null
    ): int {
        if ($taskId <= 0) throw new InvalidArgumentException('complete_invalid');
        if (!DueDateCalculator::isValidYmd($completedOnYmd)) throw new InvalidArgumentException('complete_invalid');

        $note = ($note !== null) ? trim($note) : null;
        if ($note === '') $note = null;

        try {
            $this->pdo->beginTransaction();

            // Locks + confirms home scope (+ optional item scope)
            $task = $this->tasks->lockTaskForCompletion($homeId, $taskId, $itemId);
            if (!$task) {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('complete_not_found');
            }

            // IMPORTANT: ensure lockTaskForCompletion SELECTs item_id
            $realItemId = (int)($task['item_id'] ?? 0);
            if ($realItemId <= 0) {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('complete_not_found');
            }

            $historyId = $this->history->insertHistory(
                $taskId,
                $note,
                $cost,
                $completedOnYmd
            );

            if ($photoFile !== null && isset($photoFile['error'])) {

                if ((int)$photoFile['error'] === UPLOAD_ERR_NO_FILE) {
                    // No file selected — this is fine, do nothing
                }
                elseif ((int)$photoFile['error'] === UPLOAD_ERR_OK) {
                    // Valid upload — store it
                    $this->photos->storeHistoryPhoto(
                        homeId: $homeId,
                        itemId: $realItemId,
                        taskId: $taskId,
                        historyId: $historyId,
                        file: $photoFile,
                        uploadedBy: $uploadedBy
                    );
                }
                else {
                    // File was selected but upload failed
                    throw new \RuntimeException('Photo upload failed.');
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
}
