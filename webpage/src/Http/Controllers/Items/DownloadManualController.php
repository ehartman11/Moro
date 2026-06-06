<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Items;

use Throwable;
use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Repositories\ItemsRepository;
use Moro\Repositories\ManualRepository;
use Moro\Services\ManualService;

final class DownloadManualController
{
    public function handle(): void
    {
        $pdo = Db::pdo();

        Auth::requireLogin();
        $homeId = Auth::requireActiveHome();

        $manualId = (int)($_GET['id'] ?? 0);
        if ($manualId <= 0) {
            Response::redirectToUrl(Paths::baseUrl() . '/public/items/index.php?err=bad_request');
        }

        $svc = new ManualService(
            new ManualRepository($pdo),
            new ItemsRepository($pdo)
        );

        try {
            $svc->streamPdfForManualInHome($homeId, $manualId);
            exit;

        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            Response::redirectToUrl(Paths::baseUrl() . '/public/items/index.php?err=' . urlencode($code));

        } catch (Throwable) {
            Response::redirectToUrl(Paths::baseUrl() . '/public/items/index.php?err=manual_failed');
        }
    }
}