<?php
declare(strict_types=1);

/**
 * Shared item header (used across all item tabs).
 *
 * Expects (from parent scope):
 * - $selectedItem (array) Selected item record for the active home.
 * - $tab (string) Current tab: 'details' | 'maintenance' | 'history'
 * - $role (string) Role on home: 'owner' | 'viewer'
 * - h() escape helper defined by parent (items/index.php)
 */

use Moro\Core\Paths;

// Guard: if no item is selected, nothing should render.
if (!isset($selectedItem) || empty($selectedItem['id'])) {
    return;
}

$itemId  = (int)$selectedItem['id'];
$tab     = (string)($tab ?? 'details');
$role    = (string)($role ?? 'viewer');
$baseUrl = Paths::baseUrl();

// Build URLs with baseUrl so this include is location-safe.
$detailsUrl     = $baseUrl . "/public/items/index.php?item_id={$itemId}&tab=details";
$maintenanceUrl = $baseUrl . "/public/items/index.php?item_id={$itemId}&tab=maintenance";
$historyUrl     = $baseUrl . "/public/items/index.php?item_id={$itemId}&tab=history";
?>

<header class="item-header">
    <h2><?= h($selectedItem['name'] ?? '') ?></h2>

    <div class="item-header-meta">
        <span class="badge"><?= h($selectedItem['category'] ?? '') ?></span>

        <?php if (!empty($selectedItem['serial_number'])): ?>
            <span class="muted">SN: <?= h($selectedItem['serial_number']) ?></span>
        <?php endif; ?>
    </div>

    <div class="item-header-tabs">
        <a class="tab-button <?= ($tab === 'details' ? 'active' : '') ?>"
           href="<?= h($detailsUrl) ?>">Details</a>

        <?php if ($role === 'owner'): ?>
            <a class="tab-button <?= ($tab === 'maintenance' ? 'active' : '') ?>"
               href="<?= h($maintenanceUrl) ?>">Maintenance</a>

            <a class="tab-button <?= ($tab === 'history' ? 'active' : '') ?>"
               href="<?= h($historyUrl) ?>">Material History</a>
        <?php endif; ?>
    </div>
</header>
