<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Feedback;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\RequestContext;

final class NotesController
{
    public function handle(): never
    {
        RequestContext::requirePost();
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/maintenance/index.php');
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];
        Auth::requireCsrf($_POST['csrf'] ?? '');

        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        $notes = trim((string)($_POST['resolution_notes'] ?? ''));

        if ($feedbackId <= 0) {
            http_response_code(400);
            exit('Invalid feedback id.');
        }

        $stmt = $pdo->prepare("\n          UPDATE mrc_feedback\n          SET resolution_notes = ?\n          WHERE id = ?\n            AND home_id = ?\n        ");
        $stmt->execute([$notes, $feedbackId, $homeId]);

        header('Location: ' . Paths::baseUrl() . '/public/feedback/index.php');
        exit;
    }
}