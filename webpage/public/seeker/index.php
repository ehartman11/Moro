<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Paths;
use Moro\Http\Controllers\Seeker\OverviewController;

$controller = new OverviewController();
$vm = $controller->index($_GET);

$baseUrl = (string)$vm['baseUrl'];
$filters = is_array($vm['filters'] ?? null) ? $vm['filters'] : [];
$rows = is_array($vm['rows'] ?? null) ? $vm['rows'] : [];

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Searching Portal</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/tables.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/seeker.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<section class="seeker-search">
    <h2 class="form-title">Searching Portal</h2>
    <p class="muted" style="margin-top:-4px;">Search homes by location details only. Owner identity is not displayed.</p>

    <form class="seeker-filters" method="get" action="<?= $baseUrl ?>/public/seeker/index.php" style="margin-top:10px;">
        <div class="row">
            <label for="city">City</label>
            <input id="city" name="city" value="<?= $esc((string)($filters['city'] ?? '')) ?>">
        </div>

        <div class="row">
            <label for="state">State</label>
            <input id="state" name="state" value="<?= $esc((string)($filters['state'] ?? '')) ?>">
        </div>

        <div class="row">
            <label for="zip">ZIP</label>
            <input id="zip" name="zip" value="<?= $esc((string)($filters['zip'] ?? '')) ?>">
        </div>

        <div class="row">
            <label for="min_beds">Minimum Beds</label>
            <input id="min_beds" type="number" step="0.5" min="0" max="20" name="min_beds" value="<?= $esc((string)($filters['min_beds'] ?? '')) ?>">
        </div>

        <div class="row">
            <label for="min_baths">Minimum Baths</label>
            <input id="min_baths" type="number" step="0.5" min="0" max="20" name="min_baths" value="<?= $esc((string)($filters['min_baths'] ?? '')) ?>">
        </div>

        <div class="row">
            <button type="submit">Search Homes</button>
        </div>
    </form>

    <div class="seeker-results-wrap">
    <table class="detail-table seeker-results" style="margin-top:16px;">
        <tr>
            <th>Nickname</th>
            <th>Address</th>
            <th>City</th>
            <th>State</th>
            <th>ZIP</th>
            <th>Beds</th>
            <th>Baths</th>
            <th>Sq Ft</th>
            <th>Garage</th>
            <th>Acreage</th>
            <th>Verification</th>
            <th>Inquiry</th>
            <th>Details</th>
        </tr>

        <?php if (count($rows) === 0): ?>
            <tr>
                <td colspan="13"><span class="muted">No homes match your current filters.</span></td>
            </tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= $esc((string)($row['nickname'] ?? '—')) ?></td>
                    <td><?= $esc((string)($row['address_line1'] ?? '')) ?></td>
                    <td><?= $esc((string)($row['city'] ?? '')) ?></td>
                    <td><?= $esc((string)($row['state'] ?? '')) ?></td>
                    <td><?= $esc((string)($row['zip'] ?? '')) ?></td>
                    <td><?= $esc((string)($row['beds'] ?? '—')) ?></td>
                    <td><?= $esc((string)($row['baths'] ?? '—')) ?></td>
                    <td><?= $esc((string)($row['interior_sqft'] ?? '—')) ?></td>
                    <td>
                        <?php
                            $garageType = (string)($row['garage_type'] ?? '');
                            $garageCapacity = (string)($row['garage_capacity'] ?? '');
                            $garageLabel = trim($garageType . ($garageCapacity !== '' ? ' (' . $garageCapacity . ')' : ''));
                        ?>
                        <?= $esc($garageLabel !== '' ? $garageLabel : '—') ?>
                    </td>
                    <td><?= $esc((string)($row['acreage'] ?? '—')) ?></td>
                    <?php
                        $verificationStatusRaw = strtolower(trim((string)($row['owner_verification_status'] ?? 'unverified')));
                        $verificationStatusClass = match ($verificationStatusRaw) {
                            'verified' => 'is-verified',
                            'pending_review' => 'is-pending',
                            'rejected', 'revoked' => 'is-rejected',
                            default => 'is-unverified',
                        };
                    ?>
                    <td><span class="home-verification-pill <?= $esc($verificationStatusClass) ?>"><?= $esc($verificationStatusRaw) ?></span></td>
                    <?php
                        $inquiryStateRaw = strtolower(trim((string)($row['inquiry_state'] ?? 'none')));
                        $inquiryStateClass = match ($inquiryStateRaw) {
                            'pending' => 'is-pending',
                            'accepted', 'approved' => 'is-accepted',
                            'declined', 'rejected' => 'is-declined',
                            default => 'is-none',
                        };
                    ?>
                    <td><span class="inquiry-pill <?= $esc($inquiryStateClass) ?>"><?= $esc($inquiryStateRaw) ?></span></td>
                    <td>
                        <a class="details-link" href="<?= $baseUrl ?>/public/seeker/view.php?home_id=<?= (int)($row['home_id'] ?? 0) ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
    </div>
</section>

</body>
</html>
