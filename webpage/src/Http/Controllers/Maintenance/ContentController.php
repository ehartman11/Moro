<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Maintenance;

use PDO;
use Moro\Core\Paths;
use Moro\Http\Controllers\RequestContext;

final class ContentController
{
    public function handle(): never
    {
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/index.php');
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];

        $contentId = (int)($_GET['id'] ?? 0);
        $download  = (int)($_GET['download'] ?? 0);

        if ($contentId <= 0) {
            http_response_code(400);
            exit('Missing or invalid id.');
        }

        $stmt = $pdo->prepare("\n            SELECT\n              id, home_id, item_id, task_id, part_name,\n              state, doc_key, mime_type, byte_size, sha256\n            FROM mrc_content\n            WHERE id = ? AND home_id = ?\n            LIMIT 1\n        ");
        $stmt->execute([$contentId, $homeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            exit('Document not found.');
        }

        $docKey = (string)$row['doc_key'];
        if ($docKey === '' || str_contains($docKey, "\0") || str_contains($docKey, '..') || str_starts_with($docKey, '/')) {
            http_response_code(500);
            exit('Invalid document key.');
        }

        $storageRoot = realpath(__DIR__ . '/../../../../storage/MRCs');
        if ($storageRoot === false) {
            http_response_code(500);
            exit('Storage root missing.');
        }

        $fullPath = $storageRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $docKey);
        $realFullPath = realpath($fullPath);
        if ($realFullPath === false || strncmp($realFullPath, $storageRoot . DIRECTORY_SEPARATOR, strlen($storageRoot) + 1) !== 0) {
            http_response_code(404);
            exit('File not found.');
        }

        if (!is_file($realFullPath) || !is_readable($realFullPath)) {
            http_response_code(404);
            exit('File not accessible.');
        }

        $filename = basename($realFullPath);
        $mimeType = $row['mime_type'] ?: 'application/pdf';
        $fileSize = filesize($realFullPath);

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $disposition = $download === 1 ? 'attachment' : 'inline';
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');

        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($realFullPath);
        exit;
    }
}