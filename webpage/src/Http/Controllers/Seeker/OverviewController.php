<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Seeker;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Repositories\HomeListingProfileRepository;
use Moro\Services\SeekerOverviewService;

final class OverviewController
{
    private SeekerOverviewService $service;

    public function __construct(?SeekerOverviewService $service = null)
    {
        if ($service !== null) {
            $this->service = $service;
            return;
        }

        $this->service = new SeekerOverviewService(
            new HomeListingProfileRepository(Db::pdo())
        );
    }

    public function index(array $query): array
    {
        $userId = Auth::requireLogin();

        $vm = $this->service->overviewVm($query, $userId);

        return [
            'filters' => $vm['filters'],
            'rows' => $vm['rows'],
            'baseUrl' => Paths::baseUrl(),
        ];
    }

    public function detail(array $query): array
    {
        Auth::requireLogin();

        $homeId = (int)($query['home_id'] ?? 0);
        $vm = $this->service->detailVm($homeId);

        return [
            'detail' => $vm['detail'],
            'summary' => $vm['summary'] ?? [],
            'baseUrl' => Paths::baseUrl(),
        ];
    }
}
