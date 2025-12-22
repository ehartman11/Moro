<?php
/**
 * Items hub page (tree + tabbed details).
 *
 * Responsibilities:
 * - Enforces authenticated + active-home context (via _auth.php).
 * - Builds the left “items by category” tree for the active home.
 * - Resolves the selected item (if item_id is provided).
 * - Enforces tab access rules (non-owners are forced to details-only).
 * - Routes the right-side content to a tab include (details/maintenance/history).
 * - Displays item-level flash messages via query params.
 */
require_once __DIR__ . "/_auth.php";
include APP_ROOT . "/nav_bar.php";

/* -----------------------------
   Flash messages for ITEM actions
   (tabs handle their own flashes)
------------------------------ */
$flashSuccess = '';
$flashError   = '';

if (isset($_GET['added']))   $flashSuccess = "Item added successfully.";
if (isset($_GET['updated'])) $flashSuccess = "Item updated successfully.";
if (isset($_GET['deleted'])) $flashSuccess = "Item deleted successfully.";

if (isset($_GET['err'])) {
    // Maps stable error codes to user-facing messages (avoids leaking internal details).
    $flashError = match ((string)$_GET['err']) {
        'item_required'   => 'Name and category are required.',
        'db_add_item'     => 'Database error while adding item.',
        'db_update_item'  => 'Database error while updating item.',
        'db_delete_item'  => 'Database error while deleting item.',
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
if ($userRoleOnHome !== 'owner' && $tab !== 'details') {
    // Viewers can still browse the item tree, but cannot access write-heavy tabs.
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
$stmt->execute([":home_id" => $activeHomeId]);
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
if (isset($_GET['item_id'])) {
    $itemId = (int)$_GET['item_id'];

    // Home scoping here prevents cross-home item access by guessing IDs.
    $stmtItem = $pdo->prepare("SELECT * FROM items WHERE id = :id AND home_id = :home_id");
    $stmtItem->execute([':id' => $itemId, ':home_id' => $activeHomeId]);
    $selectedItem = $stmtItem->fetch(PDO::FETCH_ASSOC);
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

    <link rel="stylesheet" href="<?= APP_URL ?>/styling/base.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/styling/nav.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/styling/forms.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/styling/tables.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/styling/items.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/styling/popup.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/styling/modal.css">

    <script src="<?= APP_URL ?>/code.js"></script>
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
        <?php if ($userRoleOnHome === 'owner'): ?>
            <button class="add-item-btn" onclick="window.location='index.php?tab=details&add=1'">+ Add Item</button>
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
                                    <a href="index.php?item_id=<?= (int)$item['id'] ?>&tab=<?= urlencode($tab) ?>">
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

        <?php include $tabMap[$tab]; ?>
    </main>
</section>

</body>
</html>
