<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Contractor;

use InvalidArgumentException;
use Moro\Repositories\JobSubmissionsRepository;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Repositories\SubmissionReviewsRepository;
use Moro\Services\JobSubmissionService;
use Moro\Services\ServiceJobService;

final class SubmissionReviewController
{
    public function index(
        int $homeId,
        int $homeownerUserId,
        array $query,
        ServiceJobService $jobs,
        JobSubmissionService $submissions
    ): array {
        $jobId = (int)($query['job_id'] ?? 0);
        if ($jobId <= 0) {
            return [
                'job' => null,
                'rows' => [],
                'flashError' => 'Missing job id.',
            ];
        }

        try {
            $job = $jobs->getHomeownerJobInHome($jobId, $homeId, $homeownerUserId);
            $rows = $submissions->listForJob($jobId);

            return [
                'job' => $job,
                'rows' => $rows,
                'flashError' => '',
            ];
        } catch (InvalidArgumentException $e) {
            $code = $e->getMessage();
            $message = match ($code) {
                'job_required' => 'Missing job id.',
                'unauthorized' => 'You are not authorized to review this job.',
                default => 'Unable to load submissions.',
            };

            return [
                'job' => null,
                'rows' => [],
                'flashError' => $message,
            ];
        } catch (\Throwable) {
            return [
                'job' => null,
                'rows' => [],
                'flashError' => 'Unable to load submissions.',
            ];
        }
    }

    public function buildDefaultSubmissionService(): JobSubmissionService
    {
        $pdo = \Moro\Core\Db::pdo();

        return new JobSubmissionService(
            $pdo,
            new JobSubmissionsRepository($pdo),
            new SubmissionReviewsRepository($pdo),
            new ServiceJobsRepository($pdo)
        );
    }
}
