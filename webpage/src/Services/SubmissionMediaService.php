<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Core\Paths;
use Moro\Repositories\SubmissionMediaRepository;
use InvalidArgumentException;
use RuntimeException;

final class SubmissionMediaService
{
    public function __construct(private SubmissionMediaRepository $media) {}

    public function storeUploaded(
        int $homeId,
        int $jobSubmissionId,
        array $file,
        string $mediaType,
        ?string $caption
    ): int {
        if ($homeId <= 0 || $jobSubmissionId <= 0) {
            throw new InvalidArgumentException('submission_media_invalid');
        }

        $mediaType = trim($mediaType);
        $allowedTypes = ['before', 'after', 'general'];
        if (!in_array($mediaType, $allowedTypes, true)) {
            throw new InvalidArgumentException('submission_media_type_invalid');
        }

        if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('submission_media_missing');
        }
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('submission_media_upload');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('submission_media_upload');
        }

        $size = (int)($file['size'] ?? 0);
        $maxBytes = 15 * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('submission_media_too_large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
        ];
        if (!isset($allowedMimes[$mime])) {
            throw new InvalidArgumentException('submission_media_type_invalid');
        }

        $ext = $allowedMimes[$mime];

        $dir = rtrim(Paths::root(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'submissions'
            . DIRECTORY_SEPARATOR . $homeId
            . DIRECTORY_SEPARATOR . $jobSubmissionId;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('submission_media_storage');
            }
        }

        $token = bin2hex(random_bytes(8));
        $fileName = $mediaType . '_' . $token . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $fileName;

        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('submission_media_write');
        }

        $mediaKey = 'submissions/' . $homeId . '/' . $jobSubmissionId . '/' . $fileName;
        $caption = $caption !== null ? trim($caption) : null;
        if ($caption === '') {
            $caption = null;
        }

        return $this->media->insert($jobSubmissionId, $mediaType, $mediaKey, $caption);
    }

    public function streamInHome(int $homeId, int $mediaId): never
    {
        $row = $this->media->findByIdInHome($mediaId, $homeId);
        if (!$row) {
            http_response_code(404);
            exit('Not found.');
        }

        $key = (string)$row['media_key'];
        if ($key === '' || str_starts_with($key, '..')) {
            http_response_code(404);
            exit('Not found.');
        }

        $abs = rtrim(Paths::root(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $key);

        if (!is_file($abs)) {
            http_response_code(404);
            exit('File missing.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($abs);
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="media-' . (int)$row['id'] . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');

        readfile($abs);
        exit;
    }
}
