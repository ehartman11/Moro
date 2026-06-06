<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Repositories\VerificationRepository;
use Moro\Services\VerificationDocumentService;
use Moro\Services\VerificationService;

final class SubmitVerificationController
{
    public function handle(): never
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $userId = Auth::requireLogin();
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        $returnTo = (string)($_POST['return_to'] ?? (Paths::baseUrl() . '/public/contractor/index.php'));

        if (!isset($_POST['submit_contractor_verification'])) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=bad_request'));
        }

        $service = new VerificationService(
            new VerificationRepository(Db::pdo()),
            new VerificationDocumentService()
        );

        try {
            $service->submitContractorVerification(
                $userId,
                (string)($_POST['doc_type'] ?? ''),
                is_array($_FILES['verification_file'] ?? null) ? $_FILES['verification_file'] : []
            );
            Response::redirectToUrl($this->withQuery($returnTo, 'contractor_verification_submitted=1'));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = [
                'unauthorized',
                'contractor_profile_required',
                'contractor_verification_already_pending',
                'contractor_verification_already_verified',
                'verification_doc_required',
                'verification_doc_upload',
                'verification_doc_too_large',
                'verification_doc_type_invalid',
            ];

            if (!in_array($code, $allowed, true)) {
                $code = 'verification_submit_failed';
            }

            Response::redirectToUrl($this->withQuery($returnTo, 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=verification_submit_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
