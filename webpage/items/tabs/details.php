<?php
/**
 * Details tab (add + view/edit item).
 *
 * Responsibilities:
 * - Owner: can add new items and edit/delete existing items.
 * - Viewer: can view item details read-only (details tab only).
 *
 * Assumptions:
 * - Parent scope provides: $userRoleOnHome, $selectedItem, $tab, APP_URL.
 * - h() is defined once in items/index.php and should not be redeclared here.
 */
$canEdit = ($userRoleOnHome === 'owner');
?>

<?php if (isset($_GET['add']) && $canEdit): ?>
    <section class="item-tab">
        <div style="max-width: 860px; margin: 0 auto;">
            <h2 style="text-align:center; margin-top:0;">Add New Item</h2>

            <form method="POST" action="actions/add_item.php">
                <input type="hidden" name="add_item" value="1">
                <input type="hidden" name="return_to" value="<?= APP_URL ?>/items/index.php?tab=details&add=1">

                <table class="detail-table">
                    <tr><th style="width: 30%;">Field</th><th>Value</th></tr>

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

                    <tr><td>Brand</td><td><input type="text" name="brand"></td></tr>
                    <tr><td>Model</td><td><input type="text" name="model"></td></tr>
                    <tr><td>Serial Number</td><td><input type="text" name="serial_number"></td></tr>

                    <tr>
                        <td>Purchase Date</td>
                        <td><input type="date" name="purchase_date"></td>
                    </tr>

                    <tr>
                        <td>Cost</td>
                        <td><input type="number" name="cost" step="0.01" min="0"></td>
                    </tr>

                    <tr>
                        <td>Notes</td>
                        <td><textarea name="notes" rows="4"></textarea></td>
                    </tr>
                </table>

                <div class="detail-actions">
                    <input type="submit" value="Add Item">
                </div>
            </form>
        </div>
    </section>

<?php elseif ($selectedItem): ?>
    <?php include __DIR__ . "/../_item_header.php"; ?>

    <section class="item-tab">
        <div style="max-width: 980px; margin: 0 auto;">

            <form method="POST"
                  action="actions/update_item.php"
                  onsubmit="<?= $canEdit ? 'return confirmSave();' : 'return false;' ?>">

                <input type="hidden" name="id" value="<?= (int)$selectedItem['id'] ?>">
                <input type="hidden" name="update_item" value="1">
                <input type="hidden" name="return_to" value="<?= APP_URL ?>/items/index.php?item_id=<?= (int)$selectedItem['id'] ?>&tab=details">

                <!-- IDENTITY -->
                <h3 style="margin: 0 0 10px;">Identity</h3>
                <table class="detail-table">
                    <tr><th style="width: 30%;">Field</th><th>Value</th></tr>

                    <tr>
                        <td>Name *</td>
                        <td>
                            <input type="text" name="name"
                                   value="<?= h($selectedItem['name']) ?>"
                                   <?= !$canEdit ? 'readonly' : '' ?>
                                   required>
                        </td>
                    </tr>

                    <tr>
                        <td>Category *</td>
                        <td>
                            <select name="category" required <?= !$canEdit ? 'disabled' : '' ?>>
                                <?php
                                    // Allows legacy/custom categories to still display + remain selectable.
                                    $categories = ['Home','Vehicle','Appliance','Tools','Outdoor','Other'];
                                    $currentCat = (string)($selectedItem['category'] ?? '');
                                    if ($currentCat && !in_array($currentCat, $categories, true)) $categories[] = $currentCat;
                                    foreach ($categories as $cat):
                                ?>
                                    <option value="<?= h($cat) ?>" <?= ($cat === $currentCat ? 'selected' : '') ?>>
                                        <?= h($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- PRODUCT INFO -->
                <h3 style="margin: 22px 0 10px;">Product Info</h3>
                <table class="detail-table">
                    <tr><th style="width: 30%;">Field</th><th>Value</th></tr>

                    <tr>
                        <td>Brand</td>
                        <td><input type="text" name="brand" value="<?= h($selectedItem['brand'] ?? '') ?>" <?= !$canEdit ? 'readonly' : '' ?>></td>
                    </tr>

                    <tr>
                        <td>Model</td>
                        <td><input type="text" name="model" value="<?= h($selectedItem['model'] ?? '') ?>" <?= !$canEdit ? 'readonly' : '' ?>></td>
                    </tr>

                    <tr>
                        <td>Serial Number</td>
                        <td><input type="text" name="serial_number" value="<?= h($selectedItem['serial_number'] ?? '') ?>" <?= !$canEdit ? 'readonly' : '' ?>></td>
                    </tr>
                </table>

                <!-- PURCHASE -->
                <h3 style="margin: 22px 0 10px;">Purchase</h3>
                <table class="detail-table">
                    <tr><th style="width: 30%;">Field</th><th>Value</th></tr>

                    <tr>
                        <td>Purchase Date</td>
                        <td>
                            <input type="date" name="purchase_date"
                                   value="<?= h($selectedItem['purchase_date'] ?? '') ?>"
                                   <?= !$canEdit ? 'readonly' : '' ?>>
                        </td>
                    </tr>

                    <tr>
                        <td>Cost</td>
                        <td>
                            <input type="number" name="cost" step="0.01" min="0"
                                   value="<?= h($selectedItem['cost'] ?? '') ?>"
                                   <?= !$canEdit ? 'readonly' : '' ?>>
                        </td>
                    </tr>
                </table>

                <!-- NOTES -->
                <h3 style="margin: 22px 0 10px;">Notes</h3>
                <table class="detail-table">
                    <tr><th style="width: 30%;">Field</th><th>Value</th></tr>
                    <tr>
                        <td>Notes</td>
                        <td>
                            <textarea name="notes" rows="5" <?= !$canEdit ? 'readonly' : '' ?>><?= h($selectedItem['notes'] ?? '') ?></textarea>
                        </td>
                    </tr>
                </table>

                <?php if ($canEdit): ?>
                    <div class="detail-actions" style="margin-top: 16px;">
                        <input type="submit" value="Save Changes">
                    </div>
                <?php endif; ?>
            </form>

            <?php if ($canEdit): ?>
                <!-- DANGER ZONE -->
                <div style="
                    margin-top: 28px;
                    padding: 18px 20px;
                    border: 1px solid #f1c1c1;
                    border-radius: 12px;
                    background: #fff6f6;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 20px;
                ">
                    <div>
                        <div style="font-weight: 700; margin-bottom: 6px;">Danger Zone</div>
                        <div class="muted">
                            Deleting an item permanently removes it and associated maintenance tasks and history.
                            This cannot be undone.
                        </div>
                    </div>

                    <form method="POST" action="actions/delete_item.php" onsubmit="return confirmDelete();">
                        <input type="hidden" name="delete_item" value="1">
                        <input type="hidden" name="id" value="<?= (int)$selectedItem['id'] ?>">
                        <input type="hidden" name="return_to"
                               value="<?= APP_URL ?>/items/index.php?item_id=<?= (int)$selectedItem['id'] ?>&tab=details">
                        <button type="submit" class="btn-danger-outline">Delete Item</button>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </section>

<?php else: ?>
    <div class="empty-state">
        <h2>Select an item<?= ($userRoleOnHome === 'owner') ? ' or add a new one' : '' ?></h2>
        <p>Use the menu on the left to choose an item.</p>
    </div>
<?php endif; ?>
