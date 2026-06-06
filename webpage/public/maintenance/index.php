<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Repositories\MaintenanceRepository;
use Moro\Services\MaintenanceCardsService;

$pdo = Db::pdo();
$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();
$role   = Auth::roleOnHome($pdo, $userId, $homeId);

$returnTo = $_POST['return_to'] ?? (Paths::baseUrl() . '/public/index.php');

Auth::requireOwner($role, $returnTo);

$baseUrl = Paths::baseUrl();

$repo = new MaintenanceRepository($pdo);
$svc  = new MaintenanceCardsService($repo);

// flat rows: item_id, item_name, part_name, task_id, task_name, priority, next_due
$rows = $svc->getTreeForHome($homeId);

// Build a nested structure in PHP for easy rendering
$tree = [];
foreach ($rows as $r) {
    $itemId = (int)$r['item_id'];
    $itemName = (string)$r['item_name'];
    $part = (string)$r['part_name'];

    if (!isset($tree[$itemId])) {
        $tree[$itemId] = ['name' => $itemName, 'parts' => []];
    }
    if (!isset($tree[$itemId]['parts'][$part])) {
        $tree[$itemId]['parts'][$part] = [];
    }
    if (!empty($r['task_id'])) {
        $tree[$itemId]['parts'][$part][] = [
            'task_id' => (int)$r['task_id'],
            'task_name' => (string)$r['task_name'],
            'priority' => (string)($r['priority'] ?? ''),
            'next_due' => $r['next_due'] ?? null,
        ];
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// include your app header/nav here
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Maintenance</title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/maintenance_cards.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>
<script>
  window.MORO_BASE_URL = <?= json_encode($baseUrl) ?>;
  window.MORO_CSRF = <?= json_encode(Auth::csrfToken()) ?>;
</script>

<div class="page">
  <div class="maint-layout">

    <aside class="maint-tree">
      <div class="maint-tree-header">
        <h2>Maintenance</h2>
        <input id="maintSearch" class="input" type="text" placeholder="Search tasks…">
      </div>

      <div id="maintTreeRoot" class="tree-root">
        <?php foreach ($tree as $itemId => $item): ?>
          <div class="tree-item" data-item-id="<?= (int)$itemId ?>">
            <button class="tree-toggle" type="button" aria-expanded="false">▸</button>
            <button class="tree-label tree-item-label" type="button">
              <?= h($item['name']) ?>
            </button>

            <div class="tree-children" hidden>
              <?php foreach ($item['parts'] as $partName => $tasks): ?>
                <div class="tree-part">
                  <button class="tree-toggle" type="button" aria-expanded="false">▸</button>
                  <button class="tree-label tree-part-label" type="button">
                    <?= h($partName) ?>
                  </button>

                  <div class="tree-children" hidden>
                    <?php if (!$tasks): ?>
                      <div class="tree-empty muted">No tasks</div>
                    <?php else: ?>
                      <?php foreach ($tasks as $t): ?>
                        <button
                          class="tree-task"
                          type="button"
                          data-task-id="<?= (int)$t['task_id'] ?>"
                          data-task-name="<?= h($t['task_name']) ?>"
                        >
                          <span class="tree-task-name"><?= h($t['task_name']) ?></span>
                          <?php if (!empty($t['next_due'])): ?>
                            <span class="tree-task-due muted"><?= h((string)$t['next_due']) ?></span>
                          <?php endif; ?>
                        </button>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                </div>
              <?php endforeach; ?>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    </aside>

    <main class="maint-card">
      <div id="cardActions" class="card-actions" hidden>
        <!-- JS will populate -->
      </div>
      <div id="cardShell" class="card-shell">
        <h2 class="muted">Select a task</h2>
        <p class="muted">Choose a maintenance task from the left to view its card.</p>
      </div>
    </main>

  </div>
</div>

<script src="<?= $baseUrl ?>/public/assets/js/maintenance_cards.js"></script>
<script>
  window.MoroMaintenanceCards?.init(window.MORO_BASE_URL);
</script>

</body>
</html>
