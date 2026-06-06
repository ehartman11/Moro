<?php
declare(strict_types=1);

namespace Moro\Services;

use InvalidArgumentException;
use Moro\Core\Paths;

final class VerificationDocumentService
{
    /** @return array{doc_key:string,mime_type:string,byte_size:int,sha256:string} */
    public function storeUploadedFile(string $subjectType, int $subjectId, int $caseId, array $file): array
    {
        if ($subjectId <= 0 || $caseId <= 0) {
            throw new InvalidArgumentException('verification_doc_invalid');
        }

        if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('verification_doc_required');
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('verification_doc_upload');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('verification_doc_upload');
        }

        $size = (int)($file['size'] ?? 0);
        $maxBytes = 15 * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('verification_doc_too_large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (!isset($allowedMimes[$mime])) {
            throw new InvalidArgumentException('verification_doc_type_invalid');
        }

        $ext = $allowedMimes[$mime];
        $safeSubjectType = $subjectType === 'contractor_profile' ? 'contractor_profile' : 'home_owner_claim';

        $dir = rtrim(Paths::root(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'verifications'
            . DIRECTORY_SEPARATOR . $safeSubjectType
            . DIRECTORY_SEPARATOR . $subjectId
            . DIRECTORY_SEPARATOR . 'case_' . $caseId;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException('verification_doc_storage');
            }
        }

        $token = bin2hex(random_bytes(8));
        $fileName = 'proof_' . $token . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmp, $dest)) {
            throw new \RuntimeException('verification_doc_write');
        }

        $docKey = 'verifications/' . $safeSubjectType . '/' . $subjectId . '/case_' . $caseId . '/' . $fileName;
        $sha256 = hash_file('sha256', $dest) ?: '';

        return [
            'doc_key' => $docKey,
            'mime_type' => $mime,
            'byte_size' => $size,
            'sha256' => $sha256,
        ];
    }
}
