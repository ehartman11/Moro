<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Admin;

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Repositories\VerificationRepository;
use Moro\Services\VerificationReviewService;

final class ReviewVerificationCaseController
{
    public function handle(): never
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method not allowed.');
        }

        $userId = Auth::requireLogin();
        $returnTo = (string)($_POST['return_to'] ?? (Paths::baseUrl() . '/public/admin/verification_queue.php'));

        Auth::requireAdmin($returnTo);
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        if (!isset($_POST['review_verification_case'])) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=bad_request'));
        }

        $service = new VerificationReviewService(Db::pdo(), new VerificationRepository(Db::pdo()));

        try {
            $service->reviewCase(
                $userId,
                (int)($_POST['verification_case_id'] ?? 0),
                (string)($_POST['decision'] ?? ''),
                isset($_POST['notes']) ? (string)$_POST['notes'] : null
            );

            Response::redirectToUrl($this->withQuery($returnTo, 'reviewed=1'));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = [
                'unauthorized',
                'verification_case_required',
                'verification_decision_invalid',
                'verification_notes_required',
                'verification_notes_too_long',
                'verification_case_not_pending',
                'verification_transition_invalid',
                'verification_subject_invalid',
            ];
            if (!in_array($code, $allowed, true)) {
                $code = 'verification_review_failed';
            }
            Response::redirectToUrl($this->withQuery($returnTo, 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=verification_review_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
