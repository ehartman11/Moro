<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Maintenance;

use PDO;
use Throwable;
use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\RequestContext;

final class PublishController
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

        $contentId = (int)($_POST['mrc_content_id'] ?? 0);

        $stmt = $pdo->prepare("\n            SELECT *\n            FROM mrc_content\n            WHERE id = ? AND home_id = ?\n        ");
        $stmt->execute([$contentId, $homeId]);
        $mrc = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mrc) {
            http_response_code(404);
            exit('MRC revision not found.');
        }

        if (($mrc['state'] ?? '') !== 'draft') {
            http_response_code(400);
            exit('Only draft revisions can be published.');
        }

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare("\n                UPDATE mrc_content\n                SET state = 'archived'\n                WHERE home_id = ?\n                  AND item_id = ?\n                  AND task_id = ?\n                  AND part_name <=> ?\n                  AND state = 'published'\n            ");
            $stmt->execute([
                $mrc['home_id'],
                $mrc['item_id'],
                $mrc['task_id'],
                $mrc['part_name'],
            ]);

            $stmt = $pdo->prepare("\n                UPDATE mrc_content\n                SET state = 'published',\n                    published_by_user_id = ?,\n                    published_at = NOW()\n                WHERE id = ? and home_id = ?\n            ");
            $stmt->execute([$userId, $contentId, $homeId]);

            $pdo->commit();

        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        header('Location: ' . $returnTo);
        exit;
    }
}