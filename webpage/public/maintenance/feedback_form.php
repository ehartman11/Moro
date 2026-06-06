<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;

$pdo = Db::pdo();

$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();
$role   = Auth::roleOnHome($pdo, $userId, $homeId);

// Prefer GET for return_to on a GET page, but allow POST too.
$returnTo = $_GET['return_to']
  ?? $_POST['return_to']
  ?? (Paths::baseUrl() . '/public/maintenance/index.php');

// Decide who can submit feedback.
// If feedback submission should be open to any home member, use a requireMember/requireRole-style guard.
// If you only want owners to submit feedback, keep requireOwner.
Auth::requireOwner($role, $returnTo);
// Auth::requireMember($role, $returnTo); // <-- if you implement this

$contentId = (int)($_GET['mrc_content_id'] ?? 0);
if ($contentId <= 0) {
  http_response_code(400);
  exit('Missing mrc_content_id.');
}

// Load the revision AND enforce it belongs to the active home.
$stmt = $pdo->prepare("
  SELECT id, home_id, item_id, task_id, part_name, revision_no, state
  FROM mrc_content
  WHERE id = ? AND home_id = ?
  LIMIT 1
");
$stmt->execute([$contentId, $homeId]);
$rev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rev) {
  http_response_code(404);
  exit('MRC revision not found for the active home.');
}

// Optional: restrict feedback to published only.
// if ($rev['state'] !== 'published') {
//   http_response_code(403);
//   exit('This revision is not published.');
// }

function h(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// URL to your submit handler.
$action = Paths::baseUrl() . '/public/actions.php?action=maintenance.feedback_submit';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Submit MRC Feedback</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; max-width: 720px; margin: 24px auto; padding: 0 16px; }
    label { display:block; margin: 12px 0 6px; font-weight: 600; }
    input, select, textarea { width:100%; padding:10px; box-sizing:border-box; }
    textarea { min-height: 140px; }
    .row { display:flex; gap:12px; }
    .row > div { flex:1; }
    .meta { color:#555; font-size: 14px; margin: 8px 0 16px; }
    .btn { display:inline-block; padding:10px 14px; border:0; background:#111; color:#fff; cursor:pointer; border-radius: 8px; text-decoration:none; }
    .btn.secondary { background:#666; }
  </style>
</head>
<body>

  <h1>Submit Feedback</h1>

  <p class="meta">
    Revision: <strong><?= (int)$rev['revision_no'] ?></strong>
    <?php if (!empty($rev['part_name'])): ?>
      • Part: <strong><?= h($rev['part_name']) ?></strong>
    <?php endif; ?>
  </p>

  <form method="post" action="<?= h($action) ?>">
    <input type="hidden" name="csrf" value="<?= h(Auth::csrfToken()) ?>">
    <!-- Carry return_to through submission -->
    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">

    <!-- Minimum identity: let the submit handler look up everything else from mrc_content -->
    <input type="hidden" name="mrc_content_id" value="<?= (int)$rev['id'] ?>">

    <label for="category">Category</label>
    <select id="category" name="category" required>
      <option value="error">Error</option>
      <option value="missing_info">Missing info</option>
      <option value="unclear">Unclear</option>
      <option value="safety">Safety concern</option>
      <option value="tools_materials">Tools / materials</option>
      <option value="formatting">Formatting</option>
      <option value="other" selected>Other</option>
    </select>

    <div class="row">
      <div>
        <label for="page_no">Page # (optional)</label>
        <input id="page_no" name="page_no" type="number" min="1" step="1" placeholder="e.g. 2">
      </div>
      <div>
        <label for="step_no">Step # (optional)</label>
        <input id="step_no" name="step_no" type="number" min="1" step="1" placeholder="e.g. 5">
      </div>
    </div>

    <label for="section_ref">Section (optional)</label>
    <input id="section_ref" name="section_ref" type="text" maxlength="80" placeholder="e.g. Precautions, Procedure, Verification">

    <label for="message">Feedback</label>
    <textarea id="message" name="message" required maxlength="5000"
      placeholder="What should be corrected, clarified, or added?"></textarea>

    <div style="margin-top:16px; display:flex; gap:10px;">
      <button class="btn" type="submit">Submit</button>
      <a class="btn secondary" href="<?= h($returnTo) ?>">Cancel</a>
    </div>
  </form>

</body>
</html>