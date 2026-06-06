<?php
declare(strict_types=1);

namespace Moro\Services;

use InvalidArgumentException;
use Moro\Repositories\VerificationRepository;
use PDO;

final class VerificationReviewService
{
    public function __construct(
        private PDO $pdo,
        private VerificationRepository $repo
    ) {}

    public function listPendingCases(): array
    {
        return $this->repo->listPendingCases(200);
    }

    public function listCases(string $status = 'pending_review', string $subjectType = ''): array
    {
        $allowedStatuses = ['pending_review', 'verified', 'rejected', 'revoked', 'all'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending_review';
        }

        $allowedSubjectTypes = ['home_owner_claim', 'contractor_profile', 'all', ''];
        if (!in_array($subjectType, $allowedSubjectTypes, true)) {
            $subjectType = '';
        }

        return $this->repo->listCases($status, $subjectType, 300);
    }

    public function listCaseDocuments(int $caseId): array
    {
        if ($caseId <= 0) {
            return [];
        }

        return $this->repo->listDocumentsForCase($caseId);
    }

    public function listEventsByCase(array $cases): array
    {
        $caseIds = array_map(static fn(array $case): int => (int)($case['id'] ?? 0), $cases);
        $events = $this->repo->listEventsForCaseIds($caseIds);

        $byCase = [];
        foreach ($events as $event) {
            $caseId = (int)($event['verification_case_id'] ?? 0);
            if ($caseId <= 0) {
                continue;
            }
            $byCase[$caseId][] = $event;
        }

        return $byCase;
    }

    public function reviewCase(int $reviewerUserId, int $caseId, string $decision, ?string $notes): void
    {
        if ($reviewerUserId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        if ($caseId <= 0) {
            throw new InvalidArgumentException('verification_case_required');
        }

        $decision = trim(strtolower($decision));
        $toStatus = match ($decision) {
            'approve' => 'verified',
            'reject' => 'rejected',
            'revoke' => 'revoked',
            default => null,
        };

        if ($toStatus === null) {
            throw new InvalidArgumentException('verification_decision_invalid');
        }

        $notes = $notes !== null ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        if (($decision === 'reject' || $decision === 'revoke') && $notes === null) {
            throw new InvalidArgumentException('verification_notes_required');
        }

        if ($notes !== null && mb_strlen($notes) > 4000) {
            throw new InvalidArgumentException('verification_notes_too_long');
        }

        try {
            $this->pdo->beginTransaction();

            $case = $this->repo->lockCaseForReview($caseId);
            if (!$case) {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('verification_case_required');
            }

            $currentStatus = (string)($case['status'] ?? '');
            $transitionAllowed = (
                ($currentStatus === 'pending_review' && ($decision === 'approve' || $decision === 'reject'))
                || ($currentStatus === 'verified' && $decision === 'revoke')
            );
            if (!$transitionAllowed) {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('verification_transition_invalid');
            }

            $subjectType = (string)($case['subject_type'] ?? '');
            $subjectId = (int)($case['subject_id'] ?? 0);

            $this->repo->reviewCase($caseId, $toStatus, $reviewerUserId, $notes);

            if ($subjectType === 'home_owner_claim') {
                $this->repo->setHomeVerificationStatus($subjectId, $toStatus);
            } elseif ($subjectType === 'contractor_profile') {
                $this->repo->setContractorVerificationStatus($subjectId, $toStatus);
            } else {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('verification_subject_invalid');
            }

            $this->repo->addEvent(
                $caseId,
                $currentStatus,
                $toStatus,
                $reviewerUserId,
                match ($decision) {
                    'approve' => 'admin_approved',
                    'reject' => 'admin_rejected',
                    'revoke' => 'admin_revoked',
                    default => 'admin_reviewed',
                },
                $notes
            );

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
