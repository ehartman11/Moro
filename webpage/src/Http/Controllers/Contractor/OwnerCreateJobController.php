<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Http\Controllers\RequestContext;
use Moro\Repositories\ItemsRepository;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Services\ServiceJobService;

final class OwnerCreateJobController
{
    public function handle(): never
    {
        RequestContext::requirePost();

        $defaultReturnTo = Paths::baseUrl() . '/public/contractor/index.php';
        $ctx = RequestContext::ownerContext($_POST, $defaultReturnTo);

        if (!isset($_POST['create_service_job'])) {
            Response::redirectToUrl($this->withQuery($ctx['returnTo'], 'err=bad_request'));
        }

        Auth::requireCsrf($_POST['csrf'] ?? '');

        $service = new ServiceJobService(
            new ServiceJobsRepository($ctx['pdo']),
            new ItemsRepository($ctx['pdo'])
        );

        try {
            $jobId = $service->createAssignmentForHomeowner(
                (int)$ctx['homeId'],
                (int)$ctx['userId'],
                $_POST
            );

            Response::redirectToUrl($this->withQuery(
                $ctx['returnTo'],
                'service_job_created=1&service_job_id=' . $jobId
            ));
        } catch (\InvalidArgumentException $e) {
            $code = $e->getMessage();
            $allowed = [
                'contractor_required',
                'contractor_not_found',
                'job_title_required',
                'job_priority_invalid',
                'item_invalid',
                'task_invalid',
                'task_item_mismatch',
                'unauthorized',
            ];

            if (!in_array($code, $allowed, true)) {
                $code = 'service_job_create_failed';
            }

            Response::redirectToUrl($this->withQuery($ctx['returnTo'], 'err=' . urlencode($code)));
        } catch (\Throwable) {
            Response::redirectToUrl($this->withQuery($ctx['returnTo'], 'err=service_job_create_failed'));
        }
    }

    private function withQuery(string $url, string $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . $query;
    }
}
