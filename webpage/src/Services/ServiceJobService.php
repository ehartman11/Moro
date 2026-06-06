<?php
declare(strict_types=1);

namespace Moro\Services;

use DateTime;
use InvalidArgumentException;
use Moro\Repositories\ItemsRepository;
use Moro\Repositories\ServiceJobsRepository;

final class ServiceJobService
{
    public function __construct(
        private ServiceJobsRepository $jobs,
        private ?ItemsRepository $items = null
    ) {}

    public function listForContractor(int $contractorUserId): array
    {
        return $this->jobs->listForContractor($contractorUserId, 100);
    }

    public function listForContractorInHome(int $homeId, int $contractorUserId): array
    {
        if ($homeId <= 0 || $contractorUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        return $this->jobs->listForContractorInHome($homeId, $contractorUserId, 200);
    }

    public function listForHomeownerInHome(int $homeId, int $homeownerUserId, ?string $state = null): array
    {
        if ($homeId <= 0 || $homeownerUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        $normalizedState = null;
        if (is_string($state)) {
            $state = trim($state);
            if ($state !== '' && $state !== 'all') {
                $validStates = ['open', 'assigned', 'in_progress', 'completed', 'cancelled'];
                if (!in_array($state, $validStates, true)) {
                    throw new InvalidArgumentException('job_state_invalid');
                }
                $normalizedState = $state;
            }
        }

        return $this->jobs->listForHomeownerInHome($homeId, $homeownerUserId, $normalizedState, 200);
    }

    public function getHomeownerJobInHome(int $jobId, int $homeId, int $homeownerUserId): array
    {
        if ($jobId <= 0 || $homeId <= 0 || $homeownerUserId <= 0) {
            throw new InvalidArgumentException('job_required');
        }

        $job = $this->jobs->findForHomeownerInHome($jobId, $homeId, $homeownerUserId);
        if (!$job) {
            throw new InvalidArgumentException('unauthorized');
        }

        return $job;
    }

    public function createAssignment(array $input): int
    {
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('job_title_required');
        }

        return $this->jobs->create([
            'home_id' => (int)($input['home_id'] ?? 0),
            'item_id' => isset($input['item_id']) && $input['item_id'] !== '' ? (int)$input['item_id'] : null,
            'task_id' => isset($input['task_id']) && $input['task_id'] !== '' ? (int)$input['task_id'] : null,
            'homeowner_user_id' => (int)($input['homeowner_user_id'] ?? 0),
            'contractor_user_id' => (int)($input['contractor_user_id'] ?? 0),
            'title' => $title,
            'description' => trim((string)($input['description'] ?? '')) ?: null,
            'priority' => (string)($input['priority'] ?? 'medium'),
            'scheduled_for' => trim((string)($input['scheduled_for'] ?? '')) ?: null,
            'due_at' => trim((string)($input['due_at'] ?? '')) ?: null,
            'state' => 'assigned',
        ]);
    }

    public function createAssignmentForHomeowner(int $homeId, int $homeownerUserId, array $input): int
    {
        if ($homeId <= 0 || $homeownerUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        $contractorUserId = (int)($input['contractor_user_id'] ?? 0);
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $priority = trim((string)($input['priority'] ?? 'medium'));

        $itemId = isset($input['item_id']) && $input['item_id'] !== '' ? (int)$input['item_id'] : null;
        $taskId = isset($input['task_id']) && $input['task_id'] !== '' ? (int)$input['task_id'] : null;

        if ($contractorUserId <= 0) {
            throw new InvalidArgumentException('contractor_required');
        }

        if ($title === '') {
            throw new InvalidArgumentException('job_title_required');
        }

        $validPriorities = ['low', 'medium', 'high'];
        if (!in_array($priority, $validPriorities, true)) {
            throw new InvalidArgumentException('job_priority_invalid');
        }

        if (!$this->jobs->contractorProfileExists($contractorUserId)) {
            throw new InvalidArgumentException('contractor_not_found');
        }

        if ($itemId !== null) {
            if ($itemId <= 0) {
                throw new InvalidArgumentException('item_invalid');
            }
            if ($this->items === null || !$this->items->findItemInHome($itemId, $homeId)) {
                throw new InvalidArgumentException('unauthorized');
            }
        }

        if ($taskId !== null) {
            if ($taskId <= 0) {
                throw new InvalidArgumentException('task_invalid');
            }
            $task = $this->jobs->findTaskInHome($taskId, $homeId);
            if (!$task) {
                throw new InvalidArgumentException('unauthorized');
            }
            if ($itemId !== null && (int)$task['item_id'] !== $itemId) {
                throw new InvalidArgumentException('task_item_mismatch');
            }
        }

        return $this->jobs->create([
            'home_id' => $homeId,
            'item_id' => $itemId,
            'task_id' => $taskId,
            'homeowner_user_id' => $homeownerUserId,
            'contractor_user_id' => $contractorUserId,
            'title' => $title,
            'description' => ($description !== '' ? $description : null),
            'priority' => $priority,
            'scheduled_for' => $this->normalizeDateTime($input['scheduled_for'] ?? null),
            'due_at' => $this->normalizeDateTime($input['due_at'] ?? null),
            'state' => 'assigned',
        ]);
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $dt = new DateTime($value);
        return $dt->format('Y-m-d H:i:s');
    }
}
