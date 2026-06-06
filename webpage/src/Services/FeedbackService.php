<?php
declare(strict_types=1);

namespace Moro\Services;

use PDO;
use Moro\Repositories\FeedbackRepository;

final class FeedbackService
{
    private FeedbackRepository $repo;

    /** @var string[] */
    private array $allowed = ['open','triaged','planned','resolved','dismissed'];

    public function __construct(PDO $pdo)
    {
        $this->repo = new FeedbackRepository($pdo);
    }

    /** @return string[] */
    public function allowedStatuses(): array
    {
        return $this->allowed;
    }

    public function normalizeStatus(?string $status): string
    {
        $s = strtolower(trim((string)$status));
        return in_array($s, $this->allowed, true) ? $s : 'open';
    }

    /** @return array<int, array<string,mixed>> */
    public function listByStatus(int $homeId, string $status, int $limit = 200): array
    {
        $status = $this->normalizeStatus($status);

        // Hard safety cap
        $limit = max(1, min($limit, 500));

        return $this->repo->findByHomeAndStatus($homeId, $status, $limit);
    }
}