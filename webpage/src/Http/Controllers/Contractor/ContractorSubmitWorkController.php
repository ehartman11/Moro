<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\JobSubmissionsRepository;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Repositories\SubmissionMediaRepository;
use Moro\Repositories\SubmissionReviewsRepository;
use Moro\Services\JobSubmissionService;
use Moro\Services\SubmissionMediaService;

final class ContractorSubmitWorkController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $ctx = RequestContext::contractorContext($_POST, Paths::baseUrl() . '/public/contractor/my_jobs.php');
        $returnTo = $ctx['returnTo'];

        if (!isset($_POST['submit_work'])) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=bad_request'));
        }

        Auth::requireCsrf($_POST['csrf'] ?? '');

        $service = new JobSubmissionService(
            $ctx['pdo'],
            new JobSubmissionsRepository($ctx['pdo']),
            new SubmissionReviewsRepository($ctx['pdo']),
            new ServiceJobsRepository($ctx['pdo']),
            new SubmissionMediaService(new SubmissionMediaRepository($ctx['pdo']))
        );

        $input = $_POST;
        $input['media_file'] = $_FILES['media_file'] ?? null;

        try {
            $submissionId = $service->createSubmittedForContractor(
                (int)$ctx['homeId'],
                (int)$ctx['userId'],
                $input
            );

            Response::redirectToUrl($this->withQuery($returnTo, 'submitted=1&submission_id=' . $submissionId));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = [
                'job_required',
                'job_not_open',
                'amount_invalid',
                'work_summary_required',
                'submission_media_missing',
                'submission_media_upload',
                'submission_media_too_large',
                'submission_media_type_invalid',
                'submission_media_unavailable',
                'unauthorized',
            ];
            if (!in_array($code, $allowed, true)) {
                $code = 'submit_failed';
            }

            Response::redirectToUrl($this->withQuery($returnTo, 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($returnTo, 'err=submit_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
