<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Homes;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\HomeInquiryRepository;
use Moro\Repositories\HomeListingProfileRepository;
use Moro\Services\HomeInquiryService;

final class RespondInquiryController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/homes.php');
        $returnTo = $ctx['returnTo'];

        if (!isset($_POST['respond_inquiry'])) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=bad_request'));
        }

        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $service = new HomeInquiryService(
            new HomeInquiryRepository($ctx['pdo']),
            new HomeListingProfileRepository($ctx['pdo'])
        );

        try {
            $service->respondAsOwner((int)$ctx['homeId'], (int)($_POST['inquiry_id'] ?? 0), $_POST);
            Response::redirectToUrl($this->withQuery($returnTo, 'inquiry_responded=1'));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = ['inquiry_required', 'inquiry_response_required', 'inquiry_response_too_long', 'unauthorized'];
            if (!in_array($code, $allowed, true)) {
                $code = 'inquiry_response_failed';
            }
            Response::redirectToUrl($this->withQuery($returnTo, 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=inquiry_response_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
