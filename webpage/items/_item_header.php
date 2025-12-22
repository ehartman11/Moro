<?php
/**
 * Shared item header (used across all item tabs).
 *
 * Responsibilities:
 * - Renders the item title, category badge, and optional serial number.
 * - Provides tab navigation for the selected item.
 * - Enforces role-based visibility for write-heavy tabs (maintenance, history).
 *
 * Expects:
 * - $selectedItem (array)  Selected item record for the active home.
 * - $tab (string)          Current tab: 'details' | 'maintenance' | 'history'.
 * - $userRoleOnHome (string) Role within the home context: 'owner' | 'viewer'.
 */

// Guard: if no item is selected, nothing should render.
if (!isset($selectedItem) || empty($selectedItem['id'])) {
    return;
}

// Local escape helper to avoid coupling to global helpers.
function h_item_header($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$itemId = (int)$selectedItem['id'];
?>

<header class="item-header">
    <h2><?= h_item_header($selectedItem['name'] ?? '') ?></h2>

    <div class="item-header-meta">
        <span class="badge"><?= h_item_header($selectedItem['category'] ?? '') ?></span>
        <?php if (!empty($selectedItem['serial_number'])): ?>
            <span class="muted">SN: <?= h_item_header($selectedItem['serial_number']) ?></span>
        <?php endif; ?>
    </div>

    <div class="item-header-tabs">
        <a class="tab-button <?= (($tab ?? 'details') === 'details' ? 'active' : '') ?>"
           href="index.php?item_id=<?= $itemId ?>&tab=details">Details</a>

        <?php if (($userRoleOnHome ?? 'viewer') === 'owner'): ?>
            <a class="tab-button <?= (($tab ?? '') === 'maintenance' ? 'active' : '') ?>"
               href="index.php?item_id=<?= $itemId ?>&tab=maintenance">Maintenance</a>

            <a class="tab-button <?= (($tab ?? '') === 'history' ? 'active' : '') ?>"
               href="index.php?item_id=<?= $itemId ?>&tab=history">Material History</a>
        <?php endif; ?>
    </div>
</header>
