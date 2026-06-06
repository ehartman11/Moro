<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Homes;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\HomeListingProfileRepository;
use Moro\Services\HomeListingProfileService;

final class SaveListingProfileController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/homes.php');
        $returnTo = $ctx['returnTo'];

        if (!isset($_POST['save_listing_profile'])) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=bad_request'));
        }

        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $service = new HomeListingProfileService(
            new HomeListingProfileRepository($ctx['pdo'])
        );

        try {
            $service->saveProfile((int)$ctx['homeId'], (int)$ctx['userId'], $_POST);
            Response::redirectToUrl($this->withQuery($returnTo, 'listing_saved=1'));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = [
                'beds_invalid',
                'baths_invalid',
                'interior_sqft_invalid',
                'floors_invalid',
                'garage_capacity_invalid',
                'acreage_invalid',
                'year_built_invalid',
                'unauthorized',
            ];
            if (!in_array($code, $allowed, true)) {
                $code = 'listing_save_failed';
            }
            Response::redirectToUrl($this->withQuery($returnTo, 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=listing_save_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
