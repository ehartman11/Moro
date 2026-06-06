<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Seeker;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\HomeInquiryRepository;
use Moro\Repositories\HomeListingProfileRepository;
use Moro\Services\HomeInquiryService;

final class SubmitInquiryController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $userId = Auth::requireLogin();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $homeId = (int)($_POST['home_id'] ?? 0);
        $returnTo = (string)($_POST['return_to'] ?? (Paths::baseUrl() . '/public/seeker/view.php?home_id=' . $homeId));

        $service = new HomeInquiryService(
            new HomeInquiryRepository(Db::pdo()),
            new HomeListingProfileRepository(Db::pdo())
        );

        try {
            $service->submitInquiry($homeId, $userId, $_POST);
            Response::redirectToUrl($this->withQuery($returnTo, 'inquiry_sent=1'));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = ['home_not_found', 'inquiry_message_required', 'inquiry_message_too_long', 'unauthorized'];
            if (!in_array($code, $allowed, true)) {
                $code = 'inquiry_submit_failed';
            }
            Response::redirectToUrl($this->withQuery($returnTo, 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=inquiry_submit_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
