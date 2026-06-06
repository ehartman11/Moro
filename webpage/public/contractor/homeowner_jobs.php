<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Http\Controllers\Contractor\HomeownerJobsController;

$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();
$pdo = Db::pdo();
$role = Auth::roleOnHome($pdo, $userId, $homeId);
Auth::requireOwner($role, Paths::baseUrl() . '/public/contractor/index.php');

$controller = new HomeownerJobsController();
$service = $controller->buildDefaultService();
$vm = $controller->index($homeId, $userId, $_GET, $service);
$baseUrl = Paths::baseUrl();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Owner Service Jobs</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/contractor.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/tables.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<section>
    <h2 class="form-title">Owner Service Jobs</h2>

    <?php if (($vm['flashError'] ?? '') !== ''): ?>
        <div class="popup show" style="background:#e74c3c;"><?= h((string)$vm['flashError']) ?></div>
    <?php endif; ?>

    <form class="contractor-inline-form" method="get" action="<?= $baseUrl ?>/public/contractor/homeowner_jobs.php">
        <label for="state">Status</label>
        <select id="state" name="state">
            <?php foreach ((array)$vm['states'] as $state): ?>
                <option value="<?= h((string)$state) ?>" <?= ((string)$vm['state'] === (string)$state) ? 'selected' : '' ?>>
                    <?= h((string)$state) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Filter</button>
    </form>

    <div class="contractor-chip-row">
        <?php foreach ((array)$vm['states'] as $state): ?>
            <?php $count = (int)(($vm['counts'][$state] ?? 0)); ?>
            <?php $isActive = ((string)$vm['state'] === (string)$state); ?>
            <a
                href="<?= $baseUrl ?>/public/contractor/homeowner_jobs.php?state=<?= urlencode((string)$state) ?>"
                class="muted contractor-chip <?= $isActive ? 'is-active' : '' ?>"
            >
                <?= h((string)$state) ?>: <?= $count ?>
            </a>
        <?php endforeach; ?>
    </div>

    <table class="detail-table" style="margin-top:12px;">
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Contractor</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Due</th>
            <th>Review</th>
        </tr>

        <?php if (empty($vm['rows'])): ?>
            <tr>
                <td colspan="7"><span class="muted">No service jobs found.</span></td>
            </tr>
        <?php else: ?>
            <?php foreach ($vm['rows'] as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= h((string)$row['title']) ?></td>
                    <td><?= h((string)($row['contractor_name'] ?? ('User #' . (int)$row['contractor_user_id']))) ?></td>
                    <td><?= h((string)$row['state']) ?></td>
                    <td><?= h((string)$row['priority']) ?></td>
                    <td><?= h((string)($row['due_at'] ?? '—')) ?></td>
                    <td>
                        <a href="<?= $baseUrl ?>/public/contractor/submissions.php?job_id=<?= (int)$row['id'] ?>">View Submissions</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>

    <div class="contractor-links">
        <a href="<?= $baseUrl ?>/public/contractor/index.php">Back to Contractor Portal</a>
    </div>
</section>

</body>
</html>
