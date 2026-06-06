<?php
declare(strict_types=1);

/**
 * Items hub page (tree + tabbed details).
 *
 * New stack:
 * - src/core/bootstrap.php
 * - Moro\Core\Auth, Db, Paths
 *
 * Keeps:
 * - Same query-param flash behavior
 * - Same tabs/includes
 * - Same HTML layout
 */

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Repositories\HistoryRepository;
use Moro\Repositories\PhotoRepository;
use Moro\Repositories\TaskRepository;
use Moro\Services\HistoryService;


$pdo = Db::pdo();

$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();
$role   = Auth::roleOnHome($pdo, $userId, $homeId);

// Nav stays as-is (but use Paths::root() instead of APP_ROOT)
require_once Paths::root() . '/nav_bar.php';

/* -----------------------------
   Flash messages for ITEM actions
------------------------------ */
$flashSuccess = '';
$flashError   = '';

if (isset($_GET['added']))   $flashSuccess = "Item added successfully.";
if (isset($_GET['updated'])) $flashSuccess = "Item updated successfully.";
if (isset($_GET['deleted'])) $flashSuccess = "Item deleted successfully.";

if (isset($_GET['err'])) {
    $flashError = match ((string)$_GET['err']) {
        'item_required'   => 'Name and category are required.',
        'item_bad_date'   => 'Purchase date must be YYYY-MM-DD.',
        'db_add_item'     => 'Database error while adding item.',
        'db_update_item'  => 'Database error while updating item.',
        'db_delete_item'  => 'Database error while deleting item.',
        'portal_role_invalid' => 'Selected portal role is invalid.',
        'portal_role_unavailable' => 'That portal is not available in your current context.',
        'contractor_profile_required' => 'Complete your contractor profile to access Contracting.',
        'seeker_role_required' => 'Your current home context does not include seeker access.',
        'unauthorized'    => 'You are not authorized to do that.',
        default           => 'An error occurred.'
    };
}

/* -----------------------------
   Tab selection (force viewer to details only)
------------------------------ */
$tab = $_GET['tab'] ?? 'details';
$allowedTabs = ['details', 'maintenance', 'history'];

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'details';
}
if ($role !== 'owner' && $tab !== 'details') {
    $tab = 'details';
    $flashError = "You do not have permission to access that tab.";
}

/* -----------------------------
   Fetch items (left tree)
------------------------------ */
$stmt = $pdo->prepare("
    SELECT id, name, category, brand, model, serial_number
    FROM items
    WHERE home_id = :home_id
    ORDER BY category, name, serial_number
");
$stmt->execute([":home_id" => $homeId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by category for the sidebar tree.
$tree = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'Uncategorized';
    $tree[$cat][] = $item;
}

/* -----------------------------
   Selected Item
------------------------------ */
$selectedItem = null;
$itemId = 0;

if (isset($_GET['item_id'])) {
    $itemId = (int)$_GET['item_id'];

    // Home scoping here prevents cross-home item access by guessing IDs.
    $stmtItem = $pdo->prepare("SELECT * FROM items WHERE id = :id AND home_id = :home_id");
    $stmtItem->execute([':id' => $itemId, ':home_id' => $homeId]);
    $selectedItem = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$selectedItem) {
        // Optional: if someone passes a bad item_id, force details and show a friendly message
        $flashError = $flashError ?: "Item not found in this home.";
        $tab = 'details';
    }
}

// History tab VM defaults
$historyRows = [];
$photosByHistory = [];
$tasks = [];

// History flash (namespace these so ?err= doesn't collide with item errors)
$historyFlashSuccess = '';
$historyFlashError = '';

if (isset($_GET['history_saved'])) {
    $historyFlashSuccess = 'History entry saved.';
}

if (isset($_GET['history_err'])) {
    $historyFlashError = match ((string)$_GET['history_err']) {
        'history_required' => 'Task and completion date are required.',
        'history_bad_date' => 'Invalid completion date.',
        'history_failed'   => 'Failed to save history entry.',
        'unauthorized'     => 'Unauthorized action.',
        'bad_request'      => 'Bad request.',
        default            => 'An error occurred.',
    };
}

if ($tab === 'history' && $selectedItem && !empty($selectedItem['id'])) {
    $svc = new HistoryService(
        new HistoryRepository($pdo),
        new PhotoRepository($pdo),
        new TaskRepository($pdo),
    );

    $vm = $svc->getHistoryTabData((int)$selectedItem['id'], (int)$homeId, (string)$role);

    $historyRows = $vm['historyRows'];
    $photosByHistory = $vm['photosByHistory'];
    $tasks = $vm['tasks'];
}

/* -----------------------------
   Tab includes
------------------------------ */
$tabMap = [
    'details'     => __DIR__ . "/tabs/details.php",
    'maintenance' => __DIR__ . "/tabs/maintenance.php",
    'history'     => __DIR__ . "/tabs/history.php",
];

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Items</title>

    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/nav.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/tables.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/items.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/modal.css">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        function confirmDelete() {
            return confirm("Are you sure you want to DELETE this item? This cannot be undone.");
        }
        function confirmSave() {
            return confirm("Are you sure you want to save these changes?");
        }
        document.addEventListener('DOMContentLoaded', () => {
            const popup = document.querySelector(".popup.show");
            if (popup) {
                setTimeout(() => popup.classList.add("hide"), 2000);
                setTimeout(() => popup.remove(), 2600);
            }
        });
    </script>
</head>
<body>

<section class="items-layout">
    <!-- LEFT: Tree -->
    <aside class="items-tree">
        <?php if ($role === 'owner'): ?>
            <button class="add-item-btn" onclick="window.location='<?= $baseUrl ?>/public/items/index.php?tab=details&add=1'">+ Add Item</button>
        <?php endif; ?>

        <h3>Items</h3>

        <?php if (empty($tree)): ?>
            <p class="muted">No items yet.</p>
        <?php else: ?>
            <ul class="tree-root">
                <?php foreach ($tree as $category => $catItems): ?>
                    <li class="tree-category">
                        <span class="tree-cat-label"><?= h($category) ?></span>
                        <ul class="tree-items">
                            <?php foreach ($catItems as $item): ?>
                                <?php
                                    $labelParts = [];
                                    if (!empty($item['name'])) $labelParts[] = $item['name'];
                                    if (!empty($item['serial_number'])) $labelParts[] = "(SN: " . $item['serial_number'] . ")";
                                    $label = implode(' ', $labelParts) ?: ("Item #" . $item['id']);

                                    $isActive = isset($selectedItem['id']) && (int)$selectedItem['id'] === (int)$item['id'];
                                ?>
                                <li class="tree-leaf <?= $isActive ? 'active' : '' ?>">
                                    <a href="<?= $baseUrl ?>/public/items/index.php?item_id=<?= (int)$item['id'] ?>&tab=<?= urlencode($tab) ?>">
                                        <?= h($label) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

    <!-- RIGHT: Content -->
    <main class="items-detail">

        <?php if ($flashSuccess): ?>
            <div class="popup show" style="background:#4CAF50;"><?= h($flashSuccess) ?></div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="popup show" style="background:#e74c3c;"><?= h($flashError) ?></div>
        <?php endif; ?>

        <?php
            // Make role + home/item context available to tabs without relying on legacy globals.
            // Tabs can use: $pdo, $homeId, $role, $selectedItem, $itemId, $userId
            include $tabMap[$tab];
        ?>
    </main>
</section>

</body>
</html>
