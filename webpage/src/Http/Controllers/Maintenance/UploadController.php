<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Maintenance;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\RequestContext;

final class UploadController
{
    public function handle(): never
    {
        RequestContext::requirePost();
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/maintenance/index.php');
        $pdo = $ctx['pdo'];
        $userId = $ctx['userId'];
        $homeId = $ctx['homeId'];
        $returnTo = $ctx['returnTo'];
        Auth::requireCsrf($_POST['csrf'] ?? '');

        $itemId = (int)($_POST['item_id'] ?? 0);
        $taskId = (int)($_POST['task_id'] ?? 0);
        $partName = $_POST['part_name'] ?? null;

        if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            exit('PDF upload failed.');
        }

        $file = $_FILES['pdf'];

        if (($file['type'] ?? '') !== 'application/pdf') {
            http_response_code(415);
            exit('Only PDF files are allowed.');
        }

        $stmt = $pdo->prepare("\n            SELECT COALESCE(MAX(revision_no), 0) + 1\n            FROM mrc_content\n            WHERE home_id = ?\n              AND item_id = ?\n              AND task_id = ?\n              AND part_name <=> ?\n        ");
        $stmt->execute([$homeId, $itemId, $taskId, $partName]);
        $revisionNo = (int)$stmt->fetchColumn() ?: 0;

        $docKey = sprintf(
            'home_%d/item_%d/task_%d/rev_%d.pdf',
            $homeId,
            $itemId,
            $taskId,
            $revisionNo
        );

        $storagePath = __DIR__ . '/../../../../storage/MRCs/' . $docKey;
        $storageDir  = dirname($storagePath);

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        if (!move_uploaded_file((string)$file['tmp_name'], $storagePath)) {
            http_response_code(500);
            exit('Failed to store PDF.');
        }

        $sha256 = hash_file('sha256', $storagePath);
        $byteSize = filesize($storagePath);

        $stmt = $pdo->prepare("\n            INSERT INTO mrc_content (\n                home_id, item_id, task_id, part_name,\n                revision_no, state,\n                doc_key, mime_type, byte_size, sha256,\n                uploaded_by_user_id\n            ) VALUES (\n                ?, ?, ?, ?,\n                ?, 'draft',\n                ?, 'application/pdf', ?, ?,\n                ?\n            )\n        ");

        $stmt->execute([
            $homeId,
            $itemId,
            $taskId,
            $partName,
            $revisionNo + 1,
            $docKey,
            $byteSize,
            $sha256,
            $userId,
        ]);

        header('Location: ' . $returnTo);
        exit;
    }
}