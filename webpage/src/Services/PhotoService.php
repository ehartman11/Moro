<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Core\Paths;
use Moro\Repositories\PhotoRepository;
use InvalidArgumentException;
use RuntimeException;

final class PhotoService
{
    public function __construct(private PhotoRepository $photos) {}

    /**
     * Validates an uploaded image, converts to JPEG, stores it, and writes photos row.
     * Returns inserted photo id.
     */
    public function storeHistoryPhoto(
        int $homeId,
        int $itemId,
        int $taskId,
        int $historyId,
        array $file,
        ?int $uploadedBy
    ): int {
        if ($homeId <= 0 || $itemId <= 0 || $taskId <= 0 || $historyId <= 0) {
            throw new InvalidArgumentException('photo_invalid');
        }

        // --- Upload sanity ---
        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('photo_upload');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('photo_upload');
        }

        $maxBytes = 10 * 1024 * 1024; // 10MB (tune)
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('photo_too_large');
        }

        // --- Identify image from actual bytes (not extension, not client mime) ---
        $imgInfo = @getimagesize($tmp);
        if ($imgInfo === false) {
            throw new InvalidArgumentException('photo_not_image');
        }

        $srcW = (int)$imgInfo[0];
        $srcH = (int)$imgInfo[1];
        $type = (int)$imgInfo[2];

        // Only allow common raster formats as inputs; we convert to JPEG.
        // (Your environment confirms WebP support via GD.)
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($type, $allowed, true)) {
            throw new InvalidArgumentException('photo_type');
        }

        // --- Decode to GD image resource ---
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
            IMAGETYPE_GIF  => @imagecreatefromgif($tmp),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($tmp)
                : throw new InvalidArgumentException('photo_webp_unsupported'),
            default => false,
        };

        if (!$src) {
            throw new InvalidArgumentException('photo_decode_failed');
        }

        // (Optional hardening) Ensure we’re using the true decoded dimensions
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // --- Destination path ---
        $photosDirAbs = rtrim(Paths::root(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'photos'
            . DIRECTORY_SEPARATOR . $homeId
            . DIRECTORY_SEPARATOR . $itemId;

        if (!is_dir($photosDirAbs)) {
            if (!mkdir($photosDirAbs, 0775, true) && !is_dir($photosDirAbs)) {
                imagedestroy($src);
                throw new RuntimeException('photo_storage');
            }
        }

        $token = bin2hex(random_bytes(8));
        $filename = "h{$historyId}_t{$taskId}_{$token}.jpg";
        $destAbs = $photosDirAbs . DIRECTORY_SEPARATOR . $filename;

        // --- Normalize to JPEG ---
        // JPEG has no alpha: flatten onto white to avoid black artifacts.
        $dst = imagecreatetruecolor($srcW, $srcH);

        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $srcW, $srcH, $white);

        imagecopy($dst, $src, 0, 0, 0, 0, $srcW, $srcH);

        $ok = imagejpeg($dst, $destAbs, 85);

        imagedestroy($dst);
        imagedestroy($src);

        if (!$ok || !is_file($destAbs)) {
            throw new RuntimeException('photo_write_failed');
        }

        $bytes = filesize($destAbs);
        if ($bytes === false) $bytes = 0;

        $sha256 = hash_file('sha256', $destAbs) ?: '';
        $storagePath = 'photos/' . $homeId . '/' . $itemId . '/' . $filename;

        return $this->photos->insert([
            'home_id'      => $homeId,
            'item_id'      => $itemId,
            'task_id'      => $taskId,
            'history_id'   => $historyId,
            'storage_path' => $storagePath,
            'bytes'        => (int)$bytes,
            'width'        => $srcW,
            'height'       => $srcH,
            'sha256'       => (string)$sha256,
            'uploaded_by'  => $uploadedBy,
        ]);
    }

    public function streamPhoto(int $homeId, int $photoId): void
    {
        $row = $this->photos->findById($photoId);
        if (!$row || (int)$row['home_id'] !== $homeId) {
            http_response_code(404);
            exit('Not found.');
        }

        $storage = (string)$row['storage_path'];
        if ($storage === '' || str_starts_with($storage, '..')) { // tiny hardening
            http_response_code(404);
            exit('Not found.');
        }

        $abs = rtrim(Paths::root(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $storage);

        if (!is_file($abs)) {
            http_response_code(404);
            exit('File missing.');
        }

        // MIME by extension (fine since you control writes -> always jpg)
        $mime = 'image/jpeg';
        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        if ($ext === 'png') $mime = 'image/png';
        elseif ($ext === 'webp') $mime = 'image/webp';
        elseif ($ext === 'gif') $mime = 'image/gif';

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="photo-' . $photoId . '.' . $ext . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');

        $bytes = (int)($row['bytes'] ?? 0);
        if ($bytes > 0) header('Content-Length: ' . $bytes);

        readfile($abs);
    }

}
