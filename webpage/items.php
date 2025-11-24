<?php
require_once "db_conn.php";

// States to track user actions and errors
$updateSuccess = false;
$updateError   = "";
$addSuccess    = false;
$addError      = "";

// Handle add item submission 
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item'])) {
    $name          = trim($_POST["name"] ?? "");
    $category      = trim($_POST["category"] ?? "");
    $brand         = trim($_POST["brand"] ?? "");
    $model         = trim($_POST["model"] ?? "");
    $serial        = trim($_POST["serial_number"] ?? "");
    $purchase_date = $_POST["purchase_date"] ?? null;
    $cost          = isset($_POST["cost"]) && $_POST["cost"] !== "" ? floatval($_POST["cost"]) : null;
    $notes         = trim($_POST["notes"] ?? "");

    if ($name === "" || $category === "") {
        $addError = "Name and category are required.";
    } else {
        try {
            $stmtAdd = $pdo->prepare("
                INSERT INTO items (name, category, brand, model, serial_number, purchase_date, cost, notes)
                VALUES (:name, :category, :brand, :model, :serial, :purchase_date, :cost, :notes)
            ");

            $stmtAdd->execute([
                ':name'          => $name,
                ':category'      => $category,
                ':brand'         => $brand,
                ':model'         => $model,
                ':serial'        => $serial,
                ':purchase_date' => $purchase_date ?: null,
                ':cost'          => $cost,
                ':notes'         => $notes
            ]);

            $addSuccess = true;

        } catch (PDOException $e) {
            $addError = "Database error: " . $e->getMessage();
        }
    }
}

// Handle update item submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_item'])) {
    $id            = (int)($_POST['id'] ?? 0);
    $name          = trim($_POST['name'] ?? "");
    $category      = trim($_POST['category'] ?? "");
    $brand         = trim($_POST['brand'] ?? "");
    $model         = trim($_POST['model'] ?? "");
    $serial        = trim($_POST['serial_number'] ?? "");
    $purchase_date = $_POST['purchase_date'] ?? null;
    $cost          = isset($_POST["cost"]) && $_POST["cost"] !== "" ? floatval($_POST["cost"]) : null;
    $notes         = trim($_POST['notes'] ?? "");

    if ($id <= 0 || $name === "" || $category === "") {
        $updateError = "Item ID, name, and category are required.";
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE items
                SET name          = :name,
                    category      = :category,
                    brand         = :brand,
                    model         = :model,
                    serial_number = :serial,
                    purchase_date = :purchase_date,
                    cost          = :cost,
                    notes         = :notes
                WHERE id = :id
            ");

            $stmtUpdate->execute([
                ':id'            => $id,
                ':name'          => $name,
                ':category'      => $category,
                ':brand'         => $brand,
                ':model'         => $model,
                ':serial'        => $serial,
                ':purchase_date' => $purchase_date ?: null,
                ':cost'          => $cost,
                ':notes'         => $notes
            ]);

            $updateSuccess = true;

        } catch (PDOException $e) {
            $updateError = "Database error: " . $e->getMessage();
        }
    }
}

// Handle delete item request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_item'])) {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM items WHERE id = :id");
            $stmtDel->execute([':id' => $id]);

            // Redirect back with a success message so refresh doesn't re-post
            header("Location: items.php?deleted=1");
            exit;

        } catch (PDOException $e) {
            $updateError = "Delete Error: " . $e->getMessage();
        }
    }
}

// Fetch all items for the left tree 
$stmt = $pdo->query("
    SELECT id, name, category, brand, model, serial_number
    FROM items
    ORDER BY category, name, serial_number
");
$items = $stmt->fetchAll();

// Group by category
$tree = [];
foreach ($items as $item) {
    $cat = $item['category'] ?: 'Uncategorized';
    if (!isset($tree[$cat])) {
        $tree[$cat] = [];
    }
    $tree[$cat][] = $item;
}

// Handle selected item: displays item data to the table/form 
$selectedItem = null;
if (isset($_GET['item_id'])) {
    $itemId = (int) $_GET['item_id'];
    $stmtItem = $pdo->prepare("SELECT * FROM items WHERE id = :id");
    $stmtItem->execute([':id' => $itemId]);
    $selectedItem = $stmtItem->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Items</title>

    <link rel="stylesheet" href="styling/base.css">
    <link rel="stylesheet" href="styling/nav.css">
    <link rel="stylesheet" href="styling/forms.css">
    <link rel="stylesheet" href="styling/tables.css">
    <link rel="stylesheet" href="styling/items.css">
    <link rel="stylesheet" href="styling/popup.css">

    <script src="code.js"></script>
    <script>
        // Confirm before deleting item
        function confirmDelete() {
            return confirm("Are you sure you want to DELETE this item? This cannot be undone.");
        }

        // Confirm before saving changes
        function confirmSave() {
            return confirm("Are you sure you want to save these changes?");
        }

        // Tab switching
        function showTab(tabId) {
            const tabs = document.querySelectorAll('.item-tab');
            tabs.forEach(t => t.style.display = 'none');

            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(b => b.classList.remove('active'));

            document.getElementById(tabId).style.display = 'block';
            const btn = document.querySelector('[data-tab="' + tabId + '"]');
            if (btn) btn.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Default to the details view 
            const defaultTab = document.getElementById('tab-details');
            if (defaultTab) {
                showTab('tab-details');
            }

            // Auto-hide popup
            const popup = document.querySelector(".popup.show");
            if (popup) {
                setTimeout(() => popup.classList.add("hide"), 2000);
                setTimeout(() => popup.remove(), 2600);
            }
        });
    </script>
</head>
<body>

<?php include "nav_bar.php"; ?>

<section class="items-layout">
    <!-- LEFT: Add button + Tree Navigation -->
    <aside class="items-tree">
        <button class="add-item-btn" onclick="window.location='items.php?add=1'">+ Add Item</button>

        <h3>Items</h3>
        <?php if (empty($tree)): ?>
            <p class="muted">No items yet. Click "Add Item" to create your first asset.</p>
        <?php else: ?>
            <ul class="tree-root">
                <?php foreach ($tree as $category => $catItems): ?>
                    <li class="tree-category">
                        <span class="tree-cat-label"><?= htmlspecialchars($category) ?></span>
                        <ul class="tree-items">
                            <?php foreach ($catItems as $item): ?>
                                <?php
                                    $labelParts = [];
                                    if (!empty($item['name'])) {
                                        $labelParts[] = $item['name'];
                                    }
                                    if (!empty($item['serial_number'])) {
                                        $labelParts[] = "(SN: " . $item['serial_number'] . ")";
                                    }
                                    $label = implode(' ', $labelParts) ?: ("Item #" . $item['id']);
                                    $isActive = isset($selectedItem['id']) && $selectedItem['id'] == $item['id'];
                                ?>
                                <li class="tree-leaf <?= $isActive ? 'active' : '' ?>">
                                    <a href="items.php?item_id=<?= (int)$item['id'] ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

    <!-- RIGHT: Item Details / Add Item / Tabs -->
    <main class="items-detail">
        <?php if ($updateSuccess): ?>
            <div class="popup show" style="background:#4CAF50;">
                Item updated successfully.
            </div>
        <?php endif; ?>

        <?php if ($addSuccess): ?>
            <div class="popup show" style="background:#4CAF50;">
                Item added successfully.
            </div>
        <?php endif; ?>

        <?php if ($updateError !== ""): ?>
            <div class="popup show" style="background:#e74c3c;">
                <?= htmlspecialchars($updateError) ?>
            </div>
        <?php endif; ?>

        <?php if ($addError !== ""): ?>
            <div class="popup show" style="background:#e74c3c;">
                <?= htmlspecialchars($addError) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="popup show" style="background:#e74c3c;">
                Item deleted successfully.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['add'])): ?>
            <!-- Add Item Form -->
            <section>
                <h2 style="text-align:center;">Add New Item</h2>

                <form method="POST" action="items.php?add=1">
                    <input type="hidden" name="add_item" value="1">

                    <table class="detail-table">
                        <tr>
                            <th>Field</th>
                            <th>Value</th>
                        </tr>
                        <tr>
                            <td>Item Name *</td>
                            <td><input type="text" name="name" required></td>
                        </tr>
                        <tr>
                            <td>Category *</td>
                            <td>
                                <select name="category" required>
                                    <option value="">-- Select Category --</option>
                                    <option>Home</option>
                                    <option>Vehicle</option>
                                    <option>Appliance</option>
                                    <option>Tools</option>
                                    <option>Outdoor</option>
                                    <option>Other</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>Brand</td>
                            <td><input type="text" name="brand"></td>
                        </tr>
                        <tr>
                            <td>Model</td>
                            <td><input type="text" name="model"></td>
                        </tr>
                        <tr>
                            <td>Serial Number</td>
                            <td><input type="text" name="serial_number"></td>
                        </tr>
                        <tr>
                            <td>Purchase Date (optional)</td>
                            <td><input type="date" name="purchase_date"></td>
                        </tr>
                        <tr>
                            <td>Cost</td>
                            <td><input type="number" name="cost" step="0.01" min="0"></td>
                        </tr>
                        <tr>
                            <td>Notes</td>
                            <td>
                                <textarea name="notes" rows="4"></textarea>
                            </td>
                        </tr>
                    </table>

                    <div class="detail-actions">
                        <input type="submit" value="Add Item">
                    </div>
                </form>
            </section>

        <?php elseif ($selectedItem): ?>
            <!-- Existing Item Details / Tabs -->
            <header class="item-header">
                <h2><?= htmlspecialchars($selectedItem['name']) ?></h2>
                <div class="item-header-meta">
                    <span class="badge"><?= htmlspecialchars($selectedItem['category']) ?></span>
                    <?php if (!empty($selectedItem['serial_number'])): ?>
                        <span class="muted">SN: <?= htmlspecialchars($selectedItem['serial_number']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="item-header-tabs">
                    <button class="tab-button active" data-tab="tab-details" onclick="showTab('tab-details')">Details</button>
                    <button class="tab-button" data-tab="tab-maintenance" onclick="showTab('tab-maintenance')">Maintenance</button>
                    <button class="tab-button" data-tab="tab-history" onclick="showTab('tab-history')">Material History</button>
                </div>
            </header>

            <!-- Tab: Details -->
            <section id="tab-details" class="item-tab">
                <form method="POST" action="items.php?item_id=<?= (int)$selectedItem['id'] ?>" onsubmit="return confirmSave();">
                    <input type="hidden" name="id" value="<?= (int)$selectedItem['id'] ?>">
                    <input type="hidden" name="update_item" value="1">

                    <table class="detail-table">
                        <tr>
                            <th>Field</th>
                            <th>Value</th>
                        </tr>
                        <tr>
                            <td>Name *</td>
                            <td><input type="text" name="name" value="<?= htmlspecialchars($selectedItem['name']) ?>" required></td>
                        </tr>
                        <tr>
                            <td>Category *</td>
                            <td>
                                <select name="category" required>
                                    <?php
                                        $categories = ['Home','Vehicle','Appliance','Tools','Outdoor','Other'];
                                        $currentCat = $selectedItem['category'];
                                        if ($currentCat && !in_array($currentCat, $categories)) {
                                            $categories[] = $currentCat;
                                        }
                                        foreach ($categories as $cat):
                                    ?>
                                        <option value="<?= htmlspecialchars($cat) ?>"
                                            <?= $cat === $currentCat ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>Brand</td>
                            <td><input type="text" name="brand" value="<?= htmlspecialchars($selectedItem['brand'] ?? '') ?>"></td>
                        </tr>
                        <tr>
                            <td>Model</td>
                            <td><input type="text" name="model" value="<?= htmlspecialchars($selectedItem['model'] ?? '') ?>"></td>
                        </tr>
                        <tr>
                            <td>Serial Number</td>
                            <td><input type="text" name="serial_number" value="<?= htmlspecialchars($selectedItem['serial_number'] ?? '') ?>"></td>
                        </tr>
                        <tr>
                            <td>Purchase Date</td>
                            <td>
                                <input type="date" name="purchase_date"
                                       value="<?= htmlspecialchars($selectedItem['purchase_date'] ?? '') ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>Cost</td>
                            <td>
                                <input type="number" name="cost" step="0.01" min="0"
                                       value="<?= htmlspecialchars($selectedItem['cost'] ?? '') ?>">
                            </td>
                        </tr>
                        <tr>
                            <td>Notes</td>
                            <td>
                                <textarea name="notes" rows="4"><?= htmlspecialchars($selectedItem['notes'] ?? '') ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <div class="detail-actions">
                        <input type="submit" value="Save Changes">
                        <!-- Delete Button -->
                        <button type="submit"
                                name="delete_item"
                                value="1"
                                class="delete-button"
                                onclick="return confirmDelete();">
                            Delete Item
                        </button>
                    </div>
                </form>
            </section>

            <!-- Tab: Maintenance -->
            <!-- TODO: create a new display for Maintenance needed for the item selected -->
            <section id="tab-maintenance" class="item-tab" style="display:none;">
                <p class="muted">
                    This will show all scheduled and completed maintenance tasks for this item.
                </p>
            </section>

            <!-- Tab: Material History -->
            <!-- TODO: create a new display for Maintenance needed for the item selected -->
            <section id="tab-history" class="item-tab" style="display:none;">
                <p class="muted">
                    This will show material/part usage and cost history linked to this item.
                </p>
            </section>

        <?php else: ?>
            <!-- Show empty state until an item is selected -->
            <div class="empty-state">
                <h2>Select an item or add a new one</h2>
                <p>Use the menu on the left to choose an item, or click <strong>Add Item</strong> to create one.</p>
            </div>
        <?php endif; ?>
    </main>
</section>

</body>
</html>
