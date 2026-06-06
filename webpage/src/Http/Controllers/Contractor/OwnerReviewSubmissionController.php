<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\JobSubmissionsRepository;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Repositories\SubmissionReviewsRepository;
use Moro\Services\JobSubmissionService;

final class OwnerReviewSubmissionController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/contractor/homeowner_jobs.php');
        $returnTo = $ctx['returnTo'];

        if (!isset($_POST['review_submission'])) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=bad_request'));
        }

        Auth::requireCsrf($_POST['csrf'] ?? '');

        $submissionId = (int)($_POST['submission_id'] ?? 0);
        $decision = (string)($_POST['decision'] ?? '');
        $comments = isset($_POST['comments']) ? (string)$_POST['comments'] : null;

        $service = new JobSubmissionService(
            $ctx['pdo'],
            new JobSubmissionsRepository($ctx['pdo']),
            new SubmissionReviewsRepository($ctx['pdo']),
            new ServiceJobsRepository($ctx['pdo'])
        );

        try {
            $service->reviewSubmissionAsHomeowner(
                (int)$ctx['homeId'],
                (int)$ctx['userId'],
                $submissionId,
                $decision,
                $comments
            );

            Response::redirectToUrl($this->withQuery($returnTo, 'reviewed=1'));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = [
                'submission_required',
                'review_decision_invalid',
                'review_comment_required',
                'review_comment_too_long',
                'submission_not_submitted',
                'submission_already_decided',
                'unauthorized',
            ];
            if (!in_array($code, $allowed, true)) {
                $code = 'review_failed';
            }

            Response::redirectToUrl($this->withQuery($returnTo, 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=review_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
