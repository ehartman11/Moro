<?php
declare(strict_types=1);

namespace Moro\Http\Controllers;

use PDO;
use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Services\FeedbackService;

final class FeedbackController
{
    private FeedbackService $service;

    public function __construct(PDO $pdo)
    {
        $this->service = new FeedbackService($pdo);
    }

    /**
     * Returns a view-model array for the feedback dashboard.
     */
    public function index(int $homeId, array $query): array
    {
        $status = $this->service->normalizeStatus($query['status'] ?? null);

        return [
            'status' => $status,
            'allowedStatuses' => $this->service->allowedStatuses(),
            'rows' => $this->service->listByStatus($homeId, $status, 200),
            'baseUrl' => Paths::baseUrl(),
            'csrf' => Auth::csrfToken(), // implement if not present yet
            'routes' => [
                'status' => Paths::baseUrl() . '/public/actions.php?action=feedback.status',
                'notes'  => Paths::baseUrl() . '/public/actions.php?action=feedback.notes',
            ],
        ];
    }
}