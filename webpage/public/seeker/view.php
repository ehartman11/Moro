<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\Seeker\OverviewController;

$controller = new OverviewController();
$detail = null;
$summary = [];
$baseUrl = Paths::baseUrl();
$error = '';
$flashSuccess = '';

try {
    $vm = $controller->detail($_GET);
    $detail = is_array($vm['detail'] ?? null) ? $vm['detail'] : null;
    $summary = is_array($vm['summary'] ?? null) ? $vm['summary'] : [];
    $baseUrl = (string)($vm['baseUrl'] ?? $baseUrl);
} catch (\InvalidArgumentException $e) {
    $error = match ($e->getMessage()) {
        'home_required' => 'A home selection is required.',
        'home_not_found' => 'That home listing is not available.',
        default => 'Unable to load home details.',
    };
} catch (\Throwable) {
    $error = 'Unable to load home details.';
}

if (isset($_GET['inquiry_sent'])) {
    $flashSuccess = 'Inquiry sent to homeowner.';
}
if (isset($_GET['err']) && $error === '') {
    $error = match ((string)$_GET['err']) {
        'inquiry_message_required' => 'Inquiry message is required.',
        'inquiry_message_too_long' => 'Inquiry message is too long.',
        'inquiry_submit_failed' => 'Unable to submit inquiry.',
        default => $error,
    };
}

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$isVisible = static function (array $detail, string $key): bool {
    $visibility = $detail['visibility_fields'] ?? null;
    if (!is_array($visibility) || !array_key_exists($key, $visibility)) {
        return true;
    }

    return (bool)$visibility[$key];
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Listing Details</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/tables.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<section>
    <h2 class="form-title">Home Listing Details</h2>

    <?php if ($flashSuccess !== ''): ?>
        <div class="popup show" style="background:#4CAF50;"><?= $esc($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="popup show" style="background:#e74c3c;"><?= $esc($error) ?></div>
    <?php elseif ($detail !== null): ?>
        <?php if (!empty($detail['headline'])): ?>
            <h3 style="margin-top:4px;"><?= $esc((string)$detail['headline']) ?></h3>
        <?php endif; ?>

        <table class="detail-table" style="margin-top:12px;">
            <tr><th>Address</th><td><?= $esc((string)($detail['address_line1'] ?? '')) ?></td></tr>
            <tr><th>City</th><td><?= $esc((string)($detail['city'] ?? '')) ?></td></tr>
            <tr><th>State</th><td><?= $esc((string)($detail['state'] ?? '')) ?></td></tr>
            <tr><th>ZIP</th><td><?= $esc((string)($detail['zip'] ?? '')) ?></td></tr>
            <?php if ($isVisible($detail, 'beds')): ?><tr><th>Beds</th><td><?= $esc((string)($detail['beds'] ?? '—')) ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'baths')): ?><tr><th>Baths</th><td><?= $esc((string)($detail['baths'] ?? '—')) ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'interior_sqft')): ?><tr><th>Interior Sq Ft</th><td><?= $esc((string)($detail['interior_sqft'] ?? '—')) ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'style')): ?><tr><th>Style</th><td><?= $esc((string)($detail['style'] ?? '—')) ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'floors')): ?><tr><th>Floors</th><td><?= $esc((string)($detail['floors'] ?? '—')) ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'basement_type')): ?><tr><th>Basement</th><td><?= $esc((string)($detail['basement_type'] ?? '—')) ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'garage_type') || $isVisible($detail, 'garage_capacity')): ?><tr><th>Garage</th><td><?= $esc((string)($detail['garage_type'] ?? '—')) ?> <?= !empty($detail['garage_capacity']) ? '(' . $esc((string)$detail['garage_capacity']) . ')' : '' ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'acreage')): ?><tr><th>Acreage</th><td><?= $esc((string)($detail['acreage'] ?? '—')) ?></td></tr><?php endif; ?>
            <?php if ($isVisible($detail, 'year_built_override')): ?><tr><th>Year Built</th><td><?= $esc((string)($detail['year_built_override'] ?? $detail['year_built'] ?? '—')) ?></td></tr><?php endif; ?>
            <tr><th>Published</th><td><?= $esc((string)($detail['published_at'] ?? '—')) ?></td></tr>
        </table>

        <h3 style="margin-top:16px;">Trust Summary</h3>
        <table class="detail-table" style="margin-top:8px;">
            <tr><th>Items Documented</th><td><?= (int)($summary['items_count'] ?? 0) ?></td></tr>
            <tr><th>Maintenance Tasks</th><td><?= (int)($summary['tasks_count'] ?? 0) ?></td></tr>
            <tr><th>Completed History Entries</th><td><?= (int)($summary['history_count'] ?? 0) ?></td></tr>
            <tr><th>Approved Contractor Work</th><td><?= (int)($summary['approved_work_count'] ?? 0) ?></td></tr>
        </table>

        <?php if ($isVisible($detail, 'summary') && !empty($detail['summary'])): ?>
            <h3 style="margin-top:16px;">Summary</h3>
            <p><?= nl2br($esc((string)$detail['summary'])) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= $baseUrl ?>/public/actions.php?action=seeker.submit_inquiry" style="margin-top:20px;">
            <h3>Contact Homeowner</h3>
            <input type="hidden" name="csrf" value="<?= $esc(Auth::csrfToken()) ?>">
            <input type="hidden" name="home_id" value="<?= (int)($detail['home_id'] ?? 0) ?>">
            <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/seeker/view.php?home_id=<?= (int)($detail['home_id'] ?? 0) ?>">

            <div class="row">
                <label for="inquiry_message">Inquiry Message</label>
                <textarea id="inquiry_message" name="message" rows="4" required></textarea>
            </div>

            <div class="row">
                <button type="submit">Send Inquiry</button>
            </div>
        </form>
    <?php endif; ?>

    <div style="margin-top:1rem;">
        <a href="<?= $baseUrl ?>/public/seeker/index.php">Back to Search</a>
    </div>
</section>

</body>
</html>
