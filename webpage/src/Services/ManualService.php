<?php
declare(strict_types=1);

namespace Moro\Services;

use Moro\Core\Paths;
use Moro\Repositories\ItemsRepository;
use Moro\Repositories\ManualRepository;
use InvalidArgumentException;
use RuntimeException;

final class ManualService
{
    public function __construct(
        private ManualRepository $manuals,
        private ItemsRepository $items
    ) {}

    /**
     * Stores a manual PDF on disk and inserts manuals row.
     *
     * @param array $itemRow Expected keys: id, brand, model (from require_item_in_active_home / repo)
     * @param array $file    $_FILES['manual_pdf']
     * @return int inserted manual id
     */
    public function addManual(
        array $itemRow,
        string $title,
        string $language,
        ?string $sourceUrl,
        array $file
    ): int {
        $itemId = (int)($itemRow['id'] ?? 0);
        if ($itemId <= 0) throw new InvalidArgumentException('manual_invalid_item');

        $title = trim($title);
        if ($title === '') throw new InvalidArgumentException('manual_missing_title');

        $language = trim($language);
        if ($language === '') $language = 'english';

        $sourceUrl = $sourceUrl !== null ? trim($sourceUrl) : null;
        if ($sourceUrl === '') $sourceUrl = null;

        // ---- Upload validation ----
        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('manual_upload');
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new InvalidArgumentException('manual_upload');
        }

        // Size guard (tune later)
        $maxBytes = 25 * 1024 * 1024; // 25MB
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new InvalidArgumentException('manual_too_large');
        }

        // MIME check
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if ($mime !== 'application/pdf') {
            throw new InvalidArgumentException('manual_not_pdf');
        }

        // Quick magic header check (defense-in-depth)
        $fh = @fopen($tmpPath, 'rb');
        if (!$fh) throw new InvalidArgumentException('manual_upload');
        $head = fread($fh, 4);
        fclose($fh);
        if ($head !== '%PDF') {
            throw new InvalidArgumentException('manual_not_pdf');
        }

        // ---- Storage path ----
        $manualDirAbs = rtrim(Paths::root(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'manuals';

        if (!is_dir($manualDirAbs)) {
            if (!mkdir($manualDirAbs, 0775, true) && !is_dir($manualDirAbs)) {
                throw new RuntimeException('manual_storage');
            }
        }

        // Safer filename (avoid collisions)
        $safeTitle = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $title);
        $token = bin2hex(random_bytes(8));
        $filename = "{$safeTitle}_item{$itemId}_{$token}.pdf";

        $destAbs = $manualDirAbs . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destAbs)) {
            throw new RuntimeException('manual_move_failed');
        }

        // Hash after move
        $fileHash = hash_file('sha256', $destAbs);
        $storagePath = 'manuals/' . $filename;

        return $this->manuals->insertManual([
            'item_id'      => $itemId,
            'title'        => $title,
            'brand'        => $itemRow['brand'] ?? null,
            'model'        => $itemRow['model'] ?? null,
            'language'     => $language,
            'source_url'   => $sourceUrl,
            'storage_path' => $storagePath,
            'file_hash'    => $fileHash,
            'page_count'   => null,
        ]);
    }

    /**
     * Streams the manual PDF if it belongs to the given home.
     * Exits the request after streaming.
     */
    public function streamPdfForManualInHome(int $homeId, int $manualId): void
    {
        if ($manualId <= 0) throw new InvalidArgumentException('bad_request');

        $manual = $this->manuals->findById($manualId);
        if (!$manual) throw new InvalidArgumentException('manual_not_found');

        $itemId = (int)$manual['item_id'];

        // Ensure manual's item belongs to home
        $item = $this->items->findItemInHome($itemId, $homeId);
        if (!$item) throw new InvalidArgumentException('unauthorized');

        $projectRootAbs = realpath(Paths::root());
        $manualsDirAbs  = realpath(rtrim(Paths::root(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'manuals');

        if ($projectRootAbs === false || $manualsDirAbs === false) {
            throw new RuntimeException('manual_storage');
        }

        $absPath = realpath($projectRootAbs . DIRECTORY_SEPARATOR . (string)$manual['storage_path']);

        // Must resolve inside /manuals
        if ($absPath === false || strpos($absPath, $manualsDirAbs) !== 0) {
            throw new InvalidArgumentException('manual_bad_path');
        }

        if (!is_file($absPath)) {
            throw new InvalidArgumentException('manual_missing');
        }

        $downloadName = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', (string)$manual['title']) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($absPath));
        header('Content-Disposition: inline; filename="' . $downloadName . '"');
        header('X-Content-Type-Options: nosniff');

        // Optional hardening for inline display
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");

        readfile($absPath);
        exit;
    }
}
