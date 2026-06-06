<?php
declare(strict_types=1);

namespace Moro\Http\Controllers;

use Moro\Services\PhotoService;
use Moro\Repositories\PhotoRepository;

final class PhotoController
{
    private PhotoService $svc;

    public function __construct(PhotoRepository $Repo)
    {
        $this->svc = new PhotoService($Repo);
    }

    public function view(int $homeId, array $query): void
    {
        $photoId = (int)($query['id'] ?? 0);
        if ($photoId <= 0) {
            http_response_code(400);
            exit('Missing photo id.');
        }

        $this->svc->streamPhoto($homeId, $photoId);
        exit;
    }
}