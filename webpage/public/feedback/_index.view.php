<?php
declare(strict_types=1);
use Moro\Core\Paths;

$esc = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$rows = $vm['rows'];
$status = $vm['status'];
$allowedStatuses = $vm['allowedStatuses'];
$baseUrl = $vm['baseUrl'];
$csrf = $vm['csrf'];
$routes = $vm['routes'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>MRC Feedback</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 24px; }
    h1 { margin-bottom: 12px; }
    .filters a { margin-right: 10px; text-decoration: none; }
    .filters strong { margin-right: 10px; }
    .card { border: 1px solid #ddd; border-radius: 10px; padding: 14px; margin-bottom: 14px; background: #fff; }
    .meta { color:#555; font-size: 13px; margin-bottom: 8px; }
    .actions { margin-top: 10px; display:flex; gap:8px; flex-wrap: wrap; align-items:center; }
    select, textarea { padding:6px; }
    textarea { width:100%; min-height:70px; }
    .btn { padding:6px 10px; border:0; border-radius:6px; cursor:pointer; }
    .btn.primary { background:#111; color:#fff; }
    .btn.secondary { background:#eee; }
  </style>
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
</head>
<body>
<?php require_once Paths::root() . '/nav_bar.php'; ?>
<h1>MRC Feedback</h1>

<div class="filters">
<?php foreach ($allowedStatuses as $s): ?>
  <?php if ($s === $status): ?>
    <strong><?= $esc($s) ?></strong>
  <?php else: ?>
    <a href="?status=<?= $esc($s) ?>"><?= $esc($s) ?></a>
  <?php endif; ?>
<?php endforeach; ?>
</div>

<?php if (!$rows): ?>
  <p>No feedback found.</p>
<?php endif; ?>

<?php foreach ($rows as $row): ?>
  <div class="card">
    <div class="meta">
      <strong><?= h($row['category']) ?></strong>
      • Submitted <?= $esc($row['created_at']) ?>
      <?php if (!empty($row['submitted_by'])): ?>
        • by <?= $esc($row['submitted_by']) ?>
      <?php endif; ?>
      <?php if (!empty($row['revision_no'])): ?>
        • Rev <?= (int)$row['revision_no'] ?>
      <?php endif; ?>
    </div>

    <p><?= nl2br($esc($row['message'])) ?></p>

    <div class="meta">
      <?php if (!empty($row['section_ref'])): ?>Section: <?= $esc($row['section_ref']) ?> • <?php endif; ?>
      <?php if (!empty($row['page_no'])): ?>Page <?= (int)$row['page_no'] ?> • <?php endif; ?>
      <?php if (!empty($row['step_no'])): ?>Step <?= (int)$row['step_no'] ?><?php endif; ?>
      <?php if (!empty($row['part_name'])): ?> • Part: <?= $esc($row['part_name']) ?><?php endif; ?>
    </div>

    <div class="actions">
      <?php if (!empty($row['content_id'])): ?>
        <a class="btn secondary"
             href="<?= $esc($baseUrl) ?>/public/actions.php?action=maintenance.content&id=<?= (int)$row['content_id'] ?>"
           target="_blank" rel="noopener">
          View PDF
        </a>
      <?php endif; ?>

      <form method="post" action="<?= $esc($routes['status']) ?>">
        <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
        <input type="hidden" name="feedback_id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="return_status" value="<?= $esc($status) ?>">
        <select name="status">
          <?php foreach ($allowedStatuses as $s): ?>
            <option value="<?= $esc($s) ?>" <?= $s === $row['status'] ? 'selected' : '' ?>>
              <?= $esc($s) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn primary" type="submit">Update</button>
      </form>
    </div>

    <form method="post" action="<?= $esc($routes['notes']) ?>">
      <input type="hidden" name="csrf" value="<?= $esc($csrf) ?>">
      <input type="hidden" name="feedback_id" value="<?= (int)$row['id'] ?>">
      <input type="hidden" name="return_status" value="<?= $esc($status) ?>">
      <label>Resolution notes</label>
      <textarea name="resolution_notes"><?= $esc($row['resolution_notes'] ?? '') ?></textarea>
      <button class="btn secondary" type="submit">Save notes</button>
    </form>
  </div>
<?php endforeach; ?>

</body>
</html>