<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Homes;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\VerificationRepository;
use Moro\Services\VerificationDocumentService;
use Moro\Services\VerificationService;

final class SubmitOwnershipVerificationController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $defaultReturnTo = Paths::baseUrl() . '/public/homes.php';
        $ctx = RequestContext::ownerContext($_POST, $defaultReturnTo);

        if (!isset($_POST['submit_home_verification'])) {
            Response::redirectToUrl($this->withQuery($ctx['returnTo'], 'err=bad_request'));
        }

        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $service = new VerificationService(
            new VerificationRepository($ctx['pdo']),
            new VerificationDocumentService()
        );

        try {
            $service->submitHomeOwnershipVerification(
                (int)$ctx['homeId'],
                (int)$ctx['userId'],
                (string)($_POST['doc_type'] ?? ''),
                is_array($_FILES['verification_file'] ?? null) ? $_FILES['verification_file'] : []
            );
            Response::redirectToUrl($this->withQuery($ctx['returnTo'], 'home_verification_submitted=1'));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = [
                'unauthorized',
                'home_verification_already_pending',
                'home_verification_already_verified',
                'verification_doc_required',
                'verification_doc_upload',
                'verification_doc_too_large',
                'verification_doc_type_invalid',
            ];

            if (!in_array($code, $allowed, true)) {
                $code = 'verification_submit_failed';
            }

            Response::redirectToUrl($this->withQuery($ctx['returnTo'], 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($ctx['returnTo'], 'err=verification_submit_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
