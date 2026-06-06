<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Paths;
use Moro\Http\Controllers\Contractor\ContractorMyJobsController;

$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();
$controller = new ContractorMyJobsController();
$service = $controller->buildDefaultService();
$vm = $controller->index($homeId, $userId, $service);

$baseUrl = Paths::baseUrl();
$csrf = Auth::csrfToken();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Contractor Jobs</title>
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
    <h2 class="form-title">My Contractor Jobs</h2>

    <?php if (($vm['flashError'] ?? '') !== ''): ?>
        <div class="popup show" style="background:#e74c3c;"><?= h((string)$vm['flashError']) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['submitted'])): ?>
        <div class="popup show" style="background:#4CAF50;">Work submitted for review.</div>
    <?php endif; ?>

    <?php if (isset($_GET['err'])): ?>
        <?php
            $err = (string)$_GET['err'];
            $submitErr = match ($err) {
                'contractor_profile_required' => 'Please complete your contractor profile first.',
                'job_required' => 'Job ID is required.',
                'job_not_open' => 'This job is not open for submissions.',
                'amount_invalid' => 'Amount must be zero or greater.',
                'work_summary_required' => 'Work summary is required.',
                'submission_media_missing' => 'Media file was not provided.',
                'submission_media_upload' => 'Media upload failed.',
                'submission_media_too_large' => 'Media file is too large.',
                'submission_media_type_invalid' => 'Media type is not supported.',
                'submission_media_unavailable' => 'Media service is unavailable.',
                'unauthorized' => 'You are not authorized for this action.',
                'submit_failed' => 'Unable to submit work.',
                default => ''
            };
        ?>
        <?php if ($submitErr !== ''): ?>
            <div class="popup show" style="background:#e74c3c;"><?= h($submitErr) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <table class="detail-table" style="margin-top:12px;">
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Due</th>
            <th>Submit Work</th>
        </tr>

        <?php if (empty($vm['rows'])): ?>
            <tr>
                <td colspan="6"><span class="muted">No assigned jobs in this home.</span></td>
            </tr>
        <?php else: ?>
            <?php foreach ($vm['rows'] as $row): ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><?= h((string)$row['title']) ?></td>
                    <td><?= h((string)$row['state']) ?></td>
                    <td><?= h((string)$row['priority']) ?></td>
                    <td><?= h((string)($row['due_at'] ?? '—')) ?></td>
                    <td>
                        <form class="contractor-compact-form" method="post" action="<?= $baseUrl ?>/public/actions.php?action=contractor.submit_work" enctype="multipart/form-data">
                            <input type="hidden" name="submit_work" value="1">
                            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                            <input type="hidden" name="service_job_id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/contractor/my_jobs.php">

                            <input type="number" step="0.01" min="0" name="amount" placeholder="Amount">
                            <input type="text" name="receipt_doc_key" placeholder="Receipt key (optional)">
                            <input type="text" name="work_summary" placeholder="Work summary" required>
                            <select name="media_type">
                                <option value="general" selected>general</option>
                                <option value="before">before</option>
                                <option value="after">after</option>
                            </select>
                            <input type="text" name="media_caption" placeholder="Media caption (optional)">
                            <input type="file" name="media_file" accept="image/*,application/pdf">
                            <button type="submit">Submit</button>
                        </form>
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
