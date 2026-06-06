<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use Moro\Repositories\SubmissionMediaRepository;
use Moro\Services\SubmissionMediaService;

final class SubmissionMediaController
{
    private SubmissionMediaService $service;

    public function __construct(?SubmissionMediaService $service = null)
    {
        if ($service !== null) {
            $this->service = $service;
            return;
        }

        $this->service = new SubmissionMediaService(
            new SubmissionMediaRepository(\Moro\Core\Db::pdo())
        );
    }

    public function view(int $homeId, array $query): never
    {
        $mediaId = (int)($query['id'] ?? 0);
        if ($mediaId <= 0) {
            http_response_code(400);
            exit('Missing media id.');
        }

        $this->service->streamInHome($homeId, $mediaId);
        exit;
    }
}
