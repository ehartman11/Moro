<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Repositories\VerificationRepository;

$userId = Auth::requireLogin();
Auth::requireAdmin(Paths::baseUrl() . '/public/homes.php');

$documentId = (int)($_GET['id'] ?? 0);
if ($documentId <= 0) {
    http_response_code(400);
    exit('Document id is required.');
}

$repo = new VerificationRepository(Db::pdo());
$doc = $repo->findDocumentById($documentId);
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

$docKey = (string)($doc['doc_key'] ?? '');
if ($docKey === '' || str_contains($docKey, "\0")) {
    http_response_code(404);
    exit('Document unavailable.');
}

$storageRoot = realpath(rtrim(Paths::root(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'storage');
if ($storageRoot === false) {
    http_response_code(404);
    exit('Storage unavailable.');
}

$abs = realpath($storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $docKey));
if ($abs === false || !str_starts_with($abs, $storageRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Document unavailable.');
}

if (!is_file($abs)) {
    http_response_code(404);
    exit('Document file missing.');
}

$mime = (string)($doc['mime_type'] ?? 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="verification-doc-' . (int)$doc['id'] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

$size = (int)($doc['byte_size'] ?? 0);
if ($size > 0) {
    header('Content-Length: ' . $size);
}

readfile($abs);
exit;
