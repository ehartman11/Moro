<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Feedback;

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\RequestContext;

final class StatusController
{
    public function handle(): never
    {
        RequestContext::requirePost();
        $ctx = RequestContext::ownerContext($_POST, Paths::baseUrl() . '/public/maintenance/index.php');
        $pdo = $ctx['pdo'];
        $homeId = $ctx['homeId'];
        Auth::requireCsrf($_POST['csrf'] ?? '');

        $feedbackId = (int)($_POST['feedback_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');

        $allowedStatuses = ['open', 'triaged', 'planned', 'resolved', 'dismissed'];
        if ($feedbackId <= 0 || !in_array($status, $allowedStatuses, true)) {
            http_response_code(400);
            exit('Invalid input.');
        }

        $stmt = $pdo->prepare("\n          UPDATE mrc_feedback\n          SET status = ?\n          WHERE id = ?\n            AND home_id = ?\n        ");
        $stmt->execute([$status, $feedbackId, $homeId]);

        header('Location: ' . Paths::baseUrl() . '/public/feedback/index.php?status=' . $status);
        exit;
    }
}