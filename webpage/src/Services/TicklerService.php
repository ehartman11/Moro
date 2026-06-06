<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Repositories\TicklerRepository;
use InvalidArgumentException;

final class TicklerService
{
    public function __construct(private TicklerRepository $repo) {}

    /**
     * Month view: groups tasks by due_date for badge counts.
     * Returns: ['byDate' => ['YYYY-MM-DD' => [task, task...], ...]]
     */
    public function month(int $homeId, int $year, int $month1): array
    {
        if ($homeId <= 0) throw new InvalidArgumentException('unauthorized');
        if ($year < 1970 || $year > 2100) throw new InvalidArgumentException('bad_year');
        if ($month1 < 1 || $month1 > 12) throw new InvalidArgumentException('bad_month');

        $start = sprintf('%04d-%02d-01', $year, $month1);
        $end   = (new \DateTimeImmutable($start))
            ->modify('last day of this month')
            ->format('Y-m-d');

        $rows = $this->repo->getTasksForRange($homeId, $start, $end);

        $byDate = [];
        foreach ($rows as $r) {
            $date = (string)$r['due_date'];

            // Keep payload small but useful for the UI.
            $byDate[$date][] = [
                'schedule_id'  => (int)$r['schedule_id'],
                'due_date'     => $date,
                'task_id'      => (int)$r['task_id'],
                'task_name'    => (string)$r['task_name'],
                'description'  => $r['description'],
                'priority'     => $r['priority'],
                'item_name'    => (string)$r['item_name'],
                'item_id'      => (int)$r['item_id'],
            ];
        }

        return ['byDate' => $byDate];
    }

    /**
     * Day view: returns tasks due on date.
     * Returns: ['tasks' => [task, task...]]
     */
    public function day(int $homeId, string $dateYmd): array
    {
        if ($homeId <= 0) throw new InvalidArgumentException('unauthorized');
        if (!DueDateCalculator::isValidYmd($dateYmd)) throw new InvalidArgumentException('bad_date');

        $rows = $this->repo->getTasksForDate($homeId, $dateYmd);

        $tasks = [];
        foreach ($rows as $r) {
            $tasks[] = [
                'schedule_id'   => (int)$r['schedule_id'],
                'due_date'      => (string)$r['due_date'],
                'task_id'       => (int)$r['task_id'],
                'task_name'     => (string)$r['task_name'],
                'description'   => $r['description'],
                'priority'      => $r['priority'],
                'schedule_type' => $r['schedule_type'] ?? null,
                'item_name'     => (string)$r['item_name'],
                'item_id'       => (int)$r['item_id'],
            ];
        }

        return ['tasks' => $tasks];
    }
}
