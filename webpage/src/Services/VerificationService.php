<?php
declare(strict_types=1);

namespace Moro\Services;

use InvalidArgumentException;
use Moro\Repositories\VerificationRepository;

final class VerificationService
{
    public function __construct(
        private VerificationRepository $repo,
        private VerificationDocumentService $docs
    ) {}

    public function submitHomeOwnershipVerification(int $homeId, int $userId, string $docType, array $file): void
    {
        if ($homeId <= 0 || $userId <= 0 || !$this->repo->homeOwnedByUser($homeId, $userId)) {
            throw new InvalidArgumentException('unauthorized');
        }

        $currentStatus = $this->repo->getHomeVerificationStatus($homeId);
        if ($currentStatus === 'pending_review') {
            throw new InvalidArgumentException('home_verification_already_pending');
        }
        if ($currentStatus === 'verified') {
            throw new InvalidArgumentException('home_verification_already_verified');
        }

        $this->repo->setHomeVerificationStatus($homeId, 'pending_review');
        $caseId = $this->repo->createCase('home_owner_claim', $homeId, $userId, 'pending_review');
        $docMeta = $this->docs->storeUploadedFile('home_owner_claim', $homeId, $caseId, $file);
        $this->repo->addDocument(
            $caseId,
            'home_owner_claim',
            $homeId,
            $this->normalizeHomeDocType($docType),
            $docMeta['doc_key'],
            $docMeta['mime_type'],
            $docMeta['byte_size'],
            $docMeta['sha256'],
            $userId
        );
        $this->repo->addEvent($caseId, $currentStatus, 'pending_review', $userId, 'owner_submitted');
    }

    public function submitContractorVerification(int $userId, string $docType, array $file): void
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('unauthorized');
        }

        if (!$this->repo->contractorProfileExists($userId)) {
            throw new InvalidArgumentException('contractor_profile_required');
        }

        $currentStatus = $this->repo->getContractorVerificationStatus($userId);
        if ($currentStatus === 'pending_review') {
            throw new InvalidArgumentException('contractor_verification_already_pending');
        }
        if ($currentStatus === 'verified') {
            throw new InvalidArgumentException('contractor_verification_already_verified');
        }

        $this->repo->setContractorVerificationStatus($userId, 'pending_review');
        $caseId = $this->repo->createCase('contractor_profile', $userId, $userId, 'pending_review');
        $docMeta = $this->docs->storeUploadedFile('contractor_profile', $userId, $caseId, $file);
        $this->repo->addDocument(
            $caseId,
            'contractor_profile',
            $userId,
            $this->normalizeContractorDocType($docType),
            $docMeta['doc_key'],
            $docMeta['mime_type'],
            $docMeta['byte_size'],
            $docMeta['sha256'],
            $userId
        );
        $this->repo->addEvent($caseId, $currentStatus, 'pending_review', $userId, 'contractor_submitted');
    }

    private function normalizeHomeDocType(string $value): string
    {
        $value = trim($value);
        $allowed = ['utility_bill', 'property_tax', 'deed', 'closing_statement', 'other'];
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('verification_doc_type_invalid');
        }
        return $value;
    }

    private function normalizeContractorDocType(string $value): string
    {
        $value = trim($value);
        $allowed = ['license', 'insurance', 'business_registration', 'other'];
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('verification_doc_type_invalid');
        }
        return $value;
    }
}
