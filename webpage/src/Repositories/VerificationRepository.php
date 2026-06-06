<?php
declare(strict_types=1);

namespace Moro\Repositories;

use PDO;

final class VerificationRepository
{
    public function __construct(private PDO $pdo) {}

    public function homeOwnedByUser(int $homeId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("\n            SELECT 1\n            FROM home_permissions\n            WHERE home_id = :hid\n              AND user_id = :uid\n              AND role = 'owner'\n            LIMIT 1\n        ");
        $stmt->execute([
            ':hid' => $homeId,
            ':uid' => $userId,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    public function contractorProfileExists(int $userId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM contractor_profiles WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function getHomeVerificationStatus(int $homeId): string
    {
        $stmt = $this->pdo->prepare("SELECT owner_verification_status FROM homes WHERE id = :hid LIMIT 1");
        $stmt->execute([':hid' => $homeId]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : 'unverified';
    }

    public function getContractorVerificationStatus(int $userId): string
    {
        $stmt = $this->pdo->prepare("SELECT verification_status FROM contractor_profiles WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $userId]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : 'unverified';
    }

    public function setHomeVerificationStatus(int $homeId, string $status): void
    {
        $stmt = $this->pdo->prepare("\n            UPDATE homes\n            SET owner_verification_status = :status,\n                owner_verification_requested_at = CASE WHEN :status = 'pending_review' THEN CURRENT_TIMESTAMP ELSE owner_verification_requested_at END,\n                owner_verification_reviewed_at = CASE WHEN :status IN ('verified','rejected','revoked') THEN CURRENT_TIMESTAMP ELSE owner_verification_reviewed_at END\n            WHERE id = :hid\n            LIMIT 1\n        ");
        $stmt->execute([
            ':hid' => $homeId,
            ':status' => $status,
        ]);
    }

    public function setContractorVerificationStatus(int $userId, string $status): void
    {
        $stmt = $this->pdo->prepare("\n            UPDATE contractor_profiles\n            SET verification_status = :status,\n                verification_requested_at = CASE WHEN :status = 'pending_review' THEN CURRENT_TIMESTAMP ELSE verification_requested_at END,\n                verification_reviewed_at = CASE WHEN :status IN ('verified','rejected','revoked') THEN CURRENT_TIMESTAMP ELSE verification_reviewed_at END\n            WHERE user_id = :uid\n            LIMIT 1\n        ");
        $stmt->execute([
            ':uid' => $userId,
            ':status' => $status,
        ]);
    }

    public function createCase(string $subjectType, int $subjectId, int $submittedByUserId, string $status): int
    {
        $stmt = $this->pdo->prepare("\n            INSERT INTO verification_cases (subject_type, subject_id, submitted_by_user_id, status)\n            VALUES (:subject_type, :subject_id, :submitted_by, :status)\n        ");
        $stmt->execute([
            ':subject_type' => $subjectType,
            ':subject_id' => $subjectId,
            ':submitted_by' => $submittedByUserId,
            ':status' => $status,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function addEvent(
        int $caseId,
        ?string $fromStatus,
        string $toStatus,
        int $actorUserId,
        string $reasonCode,
        ?string $notes = null
    ): void {
        $stmt = $this->pdo->prepare("\n            INSERT INTO verification_events (\n                verification_case_id, from_status, to_status, actor_user_id, reason_code, notes\n            ) VALUES (\n                :case_id, :from_status, :to_status, :actor_user_id, :reason_code, :notes\n            )\n        ");
        $stmt->execute([
            ':case_id' => $caseId,
            ':from_status' => $fromStatus,
            ':to_status' => $toStatus,
            ':actor_user_id' => $actorUserId,
            ':reason_code' => $reasonCode,
            ':notes' => $notes,
        ]);
    }

    public function addDocument(
        int $caseId,
        string $subjectType,
        int $subjectId,
        string $docType,
        string $docKey,
        string $mimeType,
        int $byteSize,
        string $sha256,
        int $uploadedByUserId
    ): int {
        $stmt = $this->pdo->prepare("\n            INSERT INTO verification_documents (\n                verification_case_id, subject_type, subject_id, doc_type, doc_key, mime_type, byte_size, sha256, uploaded_by_user_id\n            ) VALUES (\n                :case_id, :subject_type, :subject_id, :doc_type, :doc_key, :mime_type, :byte_size, :sha256, :uploaded_by_user_id\n            )\n        ");

        $stmt->execute([
            ':case_id' => $caseId,
            ':subject_type' => $subjectType,
            ':subject_id' => $subjectId,
            ':doc_type' => $docType,
            ':doc_key' => $docKey,
            ':mime_type' => $mimeType,
            ':byte_size' => $byteSize,
            ':sha256' => $sha256,
            ':uploaded_by_user_id' => $uploadedByUserId,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listPendingCases(int $limit = 100): array
    {
        return $this->listCases('pending_review', '', $limit);
    }

    public function listCases(string $status = 'pending_review', string $subjectType = '', int $limit = 100): array
    {
        $where = [];
        $params = [];

        if ($status !== '' && $status !== 'all') {
            $where[] = 'vc.status = :status';
            $params[':status'] = $status;
        }

        if ($subjectType !== '' && $subjectType !== 'all') {
            $where[] = 'vc.subject_type = :subject_type';
            $params[':subject_type'] = $subjectType;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $orderSql = $status === 'pending_review'
            ? 'ORDER BY vc.submitted_at ASC, vc.id ASC'
            : 'ORDER BY vc.updated_at DESC, vc.id DESC';

        $stmt = $this->pdo->prepare("\n            SELECT\n                vc.id,\n                vc.subject_type,\n                vc.subject_id,\n                vc.status,\n                vc.review_notes,\n                vc.submitted_at,\n                vc.reviewed_at,\n                vc.submitted_by_user_id,\n                vc.reviewed_by_user_id,\n                u.fname AS submitted_by_fname,\n                u.lname AS submitted_by_lname,\n                reviewer.fname AS reviewed_by_fname,\n                reviewer.lname AS reviewed_by_lname,\n                h.nickname AS home_nickname,\n                h.address_line1,\n                h.city,\n                h.state,\n                cp.business_name,\n                cp.display_name,\n                doc_counts.doc_count\n            FROM verification_cases vc\n            JOIN users u ON u.id = vc.submitted_by_user_id\n            LEFT JOIN users reviewer ON reviewer.id = vc.reviewed_by_user_id\n            LEFT JOIN homes h ON vc.subject_type = 'home_owner_claim' AND h.id = vc.subject_id\n            LEFT JOIN contractor_profiles cp ON vc.subject_type = 'contractor_profile' AND cp.user_id = vc.subject_id\n            LEFT JOIN (\n                SELECT verification_case_id, COUNT(*) AS doc_count\n                FROM verification_documents\n                GROUP BY verification_case_id\n            ) doc_counts ON doc_counts.verification_case_id = vc.id\n            $whereSql\n            $orderSql\n            LIMIT :lim\n        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listDocumentsForCase(int $caseId): array
    {
        $stmt = $this->pdo->prepare("\n            SELECT id, verification_case_id, doc_type, doc_key, mime_type, byte_size, created_at\n            FROM verification_documents\n            WHERE verification_case_id = :case_id\n            ORDER BY created_at DESC, id DESC\n        ");
        $stmt->execute([':case_id' => $caseId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findDocumentById(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare("\n            SELECT id, verification_case_id, subject_type, subject_id, doc_type, doc_key, mime_type, byte_size, sha256, uploaded_by_user_id, created_at\n            FROM verification_documents\n            WHERE id = :id\n            LIMIT 1\n        ");
        $stmt->execute([':id' => $documentId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listEventsForCaseIds(array $caseIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $caseIds), static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("\n            SELECT\n                ve.id,\n                ve.verification_case_id,\n                ve.from_status,\n                ve.to_status,\n                ve.actor_user_id,\n                ve.reason_code,\n                ve.notes,\n                ve.created_at,\n                u.fname AS actor_fname,\n                u.lname AS actor_lname\n            FROM verification_events ve\n            JOIN users u ON u.id = ve.actor_user_id\n            WHERE ve.verification_case_id IN ($in)\n            ORDER BY ve.created_at DESC, ve.id DESC\n        ");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function lockCaseForReview(int $caseId): ?array
    {
        $stmt = $this->pdo->prepare("\n            SELECT id, subject_type, subject_id, status\n            FROM verification_cases\n            WHERE id = :id\n            LIMIT 1\n            FOR UPDATE\n        ");
        $stmt->execute([':id' => $caseId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function reviewCase(int $caseId, string $status, int $reviewedByUserId, ?string $reviewNotes): void
    {
        $stmt = $this->pdo->prepare("\n            UPDATE verification_cases\n            SET status = :status,\n                reviewed_by_user_id = :reviewed_by,\n                review_notes = :review_notes,\n                reviewed_at = CURRENT_TIMESTAMP,\n                updated_at = CURRENT_TIMESTAMP\n            WHERE id = :id\n            LIMIT 1\n        ");
        $stmt->execute([
            ':id' => $caseId,
            ':status' => $status,
            ':reviewed_by' => $reviewedByUserId,
            ':review_notes' => $reviewNotes,
        ]);
    }
}
