<?php
declare(strict_types=1);

namespace Moro\Http\Controllers\Maintenance;

use PDO;
use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\RequestContext;

final class FeedbackSubmitController
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
        if ($contentId <= 0) {
            http_response_code(400);
            exit('Missing mrc_content_id.');
        }

        $stmt = $pdo->prepare("\n          SELECT id, home_id, item_id, task_id, part_name\n          FROM mrc_content\n          WHERE id = ? AND home_id = ?\n          LIMIT 1\n        ");
        $stmt->execute([$contentId, $homeId]);
        $rev = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rev) {
            http_response_code(404);
            exit('MRC revision not found.');
        }

        $allowedCategories = [
            'error', 'missing_info', 'unclear', 'safety',
            'tools_materials', 'formatting', 'other',
        ];

        $category = (string)($_POST['category'] ?? 'other');
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'other';
        }

        $pageNo = trim((string)($_POST['page_no'] ?? ''));
        $pageNoVal = ($pageNo === '') ? null : (int)$pageNo;
        if ($pageNoVal !== null && $pageNoVal < 1) {
            $pageNoVal = null;
        }

        $stepNo = trim((string)($_POST['step_no'] ?? ''));
        $stepNoVal = ($stepNo === '') ? null : (int)$stepNo;
        if ($stepNoVal !== null && $stepNoVal < 1) {
            $stepNoVal = null;
        }

        $sectionRef = trim((string)($_POST['section_ref'] ?? ''));
        if ($sectionRef === '') {
            $sectionRef = null;
        }
        if ($sectionRef !== null && mb_strlen($sectionRef) > 80) {
            $sectionRef = mb_substr($sectionRef, 0, 80);
        }

        $message = trim((string)($_POST['message'] ?? ''));
        if ($message === '') {
            http_response_code(400);
            exit('Feedback message is required.');
        }
        if (mb_strlen($message) > 5000) {
            $message = mb_substr($message, 0, 5000);
        }

        $stmt = $pdo->prepare("\n          INSERT INTO mrc_feedback (\n            home_id, item_id, task_id, part_name,\n            mrc_content_id,\n            submitted_by_user_id,\n            category, page_no, section_ref, step_no,\n            message, status\n          ) VALUES (\n            ?, ?, ?, ?,\n            ?,\n            ?,\n            ?, ?, ?, ?,\n            ?, 'open'\n          )\n        ");

        $stmt->execute([
            (int)$rev['home_id'],
            (int)$rev['item_id'],
            (int)$rev['task_id'],
            $rev['part_name'],
            (int)$rev['id'],
            (int)$userId,
            $category,
            $pageNoVal,
            $sectionRef,
            $stepNoVal,
            $message,
        ]);

        $itemId = (int)$rev['item_id'];
        header('Location:' . Paths::baseUrl() . '/public/items/index.php?item_id=' . $itemId . '&tab=maintenance&feedback=1');
        exit;
    }
}