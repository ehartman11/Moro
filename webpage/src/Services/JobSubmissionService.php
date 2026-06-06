<?php
declare(strict_types=1);

namespace Moro\Services;

use DateTime;
use InvalidArgumentException;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Repositories\SubmissionReviewsRepository;
use Moro\Services\SubmissionMediaService;
use PDO;
use Moro\Repositories\JobSubmissionsRepository;

final class JobSubmissionService
{
    public function __construct(
        private PDO $pdo,
        private JobSubmissionsRepository $submissions,
        private SubmissionReviewsRepository $reviews,
        private ServiceJobsRepository $jobs,
        private ?SubmissionMediaService $media = null
    ) {}

    public function listForJob(int $serviceJobId): array
    {
        return $this->submissions->listForJob($serviceJobId);
    }

    public function createDraft(int $submittedByUserId, array $input): int
    {
        $jobId = (int)($input['service_job_id'] ?? 0);
        if ($jobId <= 0) {
            throw new InvalidArgumentException('job_required');
        }

        $amountRaw = $input['amount'] ?? null;
        $amount = null;
        if ($amountRaw !== null && $amountRaw !== '') {
            $amount = (float)$amountRaw;
        }

        return $this->submissions->createDraft([
            'service_job_id' => $jobId,
            'submitted_by_user_id' => $submittedByUserId,
            'amount' => $amount,
            'currency' => (string)($input['currency'] ?? 'USD'),
            'work_summary' => trim((string)($input['work_summary'] ?? '')) ?: null,
            'receipt_doc_key' => trim((string)($input['receipt_doc_key'] ?? '')) ?: null,
        ]);
    }

    public function createSubmittedForContractor(int $homeId, int $contractorUserId, array $input): int
    {
        if ($homeId <= 0 || $contractorUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        $jobId = (int)($input['service_job_id'] ?? 0);
        if ($jobId <= 0) {
            throw new InvalidArgumentException('job_required');
        }

        $job = $this->jobs->findForContractorInHome($jobId, $homeId, $contractorUserId);
        if (!$job) {
            throw new InvalidArgumentException('unauthorized');
        }

        $jobState = (string)($job['state'] ?? '');
        if ($jobState === 'cancelled' || $jobState === 'completed') {
            throw new InvalidArgumentException('job_not_open');
        }

        $amountRaw = $input['amount'] ?? null;
        $amount = null;
        if ($amountRaw !== null && $amountRaw !== '') {
            $amount = (float)$amountRaw;
            if ($amount < 0) {
                throw new InvalidArgumentException('amount_invalid');
            }
        }

        $workSummary = trim((string)($input['work_summary'] ?? ''));
        if ($workSummary === '') {
            throw new InvalidArgumentException('work_summary_required');
        }

        $submittedAt = (new DateTime('now'))->format('Y-m-d H:i:s');

        $file = $input['media_file'] ?? null;
        $mediaType = trim((string)($input['media_type'] ?? 'general'));
        $caption = isset($input['media_caption']) ? (string)$input['media_caption'] : null;

        $submissionId = 0;

        try {
            $this->pdo->beginTransaction();

            $submissionId = $this->submissions->createSubmitted([
                'service_job_id' => $jobId,
                'submitted_by_user_id' => $contractorUserId,
                'amount' => $amount,
                'currency' => (string)($input['currency'] ?? 'USD'),
                'work_summary' => $workSummary,
                'receipt_doc_key' => trim((string)($input['receipt_doc_key'] ?? '')) ?: null,
            ], $submittedAt);

            if (is_array($file) && (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
                if ($this->media === null) {
                    throw new InvalidArgumentException('submission_media_unavailable');
                }

                $this->media->storeUploaded($homeId, $submissionId, $file, $mediaType, $caption);
            }

            $this->pdo->commit();
            return $submissionId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function reviewSubmissionAsHomeowner(
        int $homeId,
        int $homeownerUserId,
        int $submissionId,
        string $decision,
        ?string $comments
    ): void {
        if ($homeId <= 0 || $homeownerUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        if ($submissionId <= 0) {
            throw new InvalidArgumentException('submission_required');
        }

        $decision = trim($decision);
        $map = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'needs_changes' => 'needs_changes',
        ];

        if (!isset($map[$decision])) {
            throw new InvalidArgumentException('review_decision_invalid');
        }

        $comments = $comments !== null ? trim($comments) : null;
        if ($comments === '') {
            $comments = null;
        }

        if (($decision === 'reject' || $decision === 'needs_changes') && $comments === null) {
            throw new InvalidArgumentException('review_comment_required');
        }

        if ($comments !== null && mb_strlen($comments) > 2000) {
            throw new InvalidArgumentException('review_comment_too_long');
        }

        try {
            $this->pdo->beginTransaction();

            $submission = $this->submissions->lockForHomeownerReview($submissionId, $homeId, $homeownerUserId);
            if (!$submission) {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('unauthorized');
            }

            $currentState = (string)$submission['state'];
            if ($currentState === 'draft') {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('submission_not_submitted');
            }
            if ($currentState === 'approved' || $currentState === 'rejected') {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('submission_already_decided');
            }

            $nextState = $map[$decision];
            $decidedAt = (new DateTime('now'))->format('Y-m-d H:i:s');
            $serviceJobId = (int)$submission['service_job_id'];

            $this->reviews->insertReview($submissionId, $homeownerUserId, $decision, $comments);
            $this->submissions->updateDecisionState($submissionId, $nextState, $decidedAt);

            if ($decision === 'approve') {
                $this->jobs->markCompleted($serviceJobId, $decidedAt);
            } elseif ($decision === 'needs_changes') {
                $this->jobs->updateState($serviceJobId, 'in_progress');
            } elseif ($decision === 'reject') {
                $this->jobs->updateState($serviceJobId, 'cancelled');
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
