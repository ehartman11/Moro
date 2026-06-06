<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Http\Controllers\Contractor\SubmissionReviewController;
use Moro\Repositories\ServiceJobsRepository;
use Moro\Repositories\SubmissionMediaRepository;
use Moro\Repositories\SubmissionReviewsRepository;
use Moro\Services\ServiceJobService;

$userId = Auth::requireLogin();
$homeId = Auth::requireActiveHome();
$pdo = Db::pdo();
$role = Auth::roleOnHome($pdo, $userId, $homeId);
Auth::requireOwner($role, Paths::baseUrl() . '/public/contractor/index.php');

$jobsService = new ServiceJobService(new ServiceJobsRepository($pdo));
$controller = new SubmissionReviewController();
$submissionService = $controller->buildDefaultSubmissionService();
$vm = $controller->index($homeId, $userId, $_GET, $jobsService, $submissionService);

$baseUrl = Paths::baseUrl();
$job = $vm['job'];
$rows = $vm['rows'];
$csrf = Auth::csrfToken();

$mediaBySubmission = [];
$reviewsBySubmission = [];
if (!empty($rows)) {
    $submissionIds = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows);
    $mediaRows = (new SubmissionMediaRepository($pdo))
        ->listForSubmissionIdsByHomeowner($homeId, $userId, $submissionIds);
    $reviewRows = (new SubmissionReviewsRepository($pdo))
        ->listForSubmissionIds($submissionIds);

    foreach ($mediaRows as $media) {
        $sid = (int)($media['job_submission_id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        if (!isset($mediaBySubmission[$sid])) {
            $mediaBySubmission[$sid] = [];
        }
        $mediaBySubmission[$sid][] = $media;
    }

    foreach ($reviewRows as $review) {
        $sid = (int)($review['job_submission_id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        if (!isset($reviewsBySubmission[$sid])) {
            $reviewsBySubmission[$sid] = [];
        }
        $reviewsBySubmission[$sid][] = $review;
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function isImageMedia(string $mediaKey): bool
{
    $ext = strtolower((string)pathinfo($mediaKey, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submission Review</title>
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
    <h2 class="form-title">Submission Review</h2>

    <?php if (($vm['flashError'] ?? '') !== ''): ?>
        <div class="popup show" style="background:#e74c3c;"><?= h((string)$vm['flashError']) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['reviewed'])): ?>
        <div class="popup show" style="background:#4CAF50;">Submission review saved.</div>
    <?php endif; ?>

    <?php if (isset($_GET['err'])): ?>
        <?php
            $err = (string)$_GET['err'];
            $reviewErr = match ($err) {
                'submission_required' => 'Submission ID is required.',
                'review_decision_invalid' => 'Review decision is invalid.',
                'review_comment_required' => 'Comments are required when rejecting or requesting changes.',
                'review_comment_too_long' => 'Comments are too long.',
                'submission_not_submitted' => 'Draft submissions cannot be reviewed.',
                'submission_already_decided' => 'Submission is already finalized.',
                'unauthorized' => 'You are not authorized for this action.',
                'review_failed' => 'Unable to save review decision.',
                default => ''
            };
        ?>
        <?php if ($reviewErr !== ''): ?>
            <div class="popup show" style="background:#e74c3c;"><?= h($reviewErr) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($job): ?>
        <div class="muted contractor-job-meta">
            Job #<?= (int)$job['id'] ?> · <?= h((string)$job['title']) ?> · Contractor: <?= h((string)($job['contractor_name'] ?? '')) ?>
        </div>

        <table class="detail-table">
            <tr>
                <th>Submission</th>
                <th>State</th>
                <th>Amount</th>
                <th>Summary</th>
                <th>Submitted At</th>
                <th>Media</th>
                <th>Decision</th>
            </tr>

            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="7"><span class="muted">No submissions yet.</span></td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php $submissionId = (int)$row['id']; ?>
                        <td>#<?= (int)$row['id'] ?></td>
                        <td><?= h((string)$row['state']) ?></td>
                        <td>
                            <?php if ($row['amount'] !== null): ?>
                                <?= h((string)$row['currency']) ?> <?= h(number_format((float)$row['amount'], 2)) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?= h((string)($row['work_summary'] ?? '')) ?></td>
                        <td><?= h((string)($row['submitted_at'] ?? '—')) ?></td>
                        <td>
                            <?php $mediaRows = $mediaBySubmission[$submissionId] ?? []; ?>
                            <?php if (empty($mediaRows)): ?>
                                <span class="muted">—</span>
                            <?php else: ?>
                                <?php foreach ($mediaRows as $media): ?>
                                    <?php
                                        $mediaUrl = $baseUrl . '/public/contractor/media_view.php?id=' . (int)$media['id'];
                                        $mediaType = (string)($media['media_type'] ?? 'general');
                                        $mediaCaption = (string)($media['caption'] ?? '');
                                        $mediaKey = (string)($media['media_key'] ?? '');
                                    ?>
                                    <div>
                                        <a href="<?= $mediaUrl ?>" target="_blank" rel="noopener">
                                            <?= h($mediaType) ?>
                                        </a>
                                        <?php if ($mediaCaption !== ''): ?>
                                            <span class="muted">- <?= h($mediaCaption) ?></span>
                                        <?php endif; ?>

                                        <?php if (isImageMedia($mediaKey)): ?>
                                            <div style="margin-top:4px;">
                                                <a href="<?= $mediaUrl ?>" target="_blank" rel="noopener">
                                                    <img
                                                        src="<?= $mediaUrl ?>"
                                                        alt="Submission media"
                                                        class="contractor-media-thumb"
                                                    >
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php $reviewRows = $reviewsBySubmission[$submissionId] ?? []; ?>
                            <form class="contractor-compact-form" method="post" action="<?= $baseUrl ?>/public/actions.php?action=contractor.review_submission">
                                <input type="hidden" name="review_submission" value="1">
                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                <input type="hidden" name="submission_id" value="<?= (int)$row['id'] ?>">
                                <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/contractor/submissions.php?job_id=<?= (int)$job['id'] ?>">

                                <select name="decision" required>
                                    <option value="" selected disabled>Select…</option>
                                    <option value="approve">approve</option>
                                    <option value="needs_changes">needs_changes</option>
                                    <option value="reject">reject</option>
                                </select>

                                <input type="text" name="comments" maxlength="2000" placeholder="Comments (required for reject/needs_changes)">

                                <button type="submit">Save</button>
                            </form>

                            <?php if (!empty($reviewRows)): ?>
                                <div class="contractor-review-log">
                                    <?php foreach ($reviewRows as $review): ?>
                                        <div class="muted contractor-review-log-item">
                                            <?= h((string)$review['decision']) ?> · <?= h((string)$review['created_at']) ?>
                                            <?php if (!empty($review['comments'])): ?>
                                                — <?= h((string)$review['comments']) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    <?php endif; ?>

    <div class="contractor-links">
        <a href="<?= $baseUrl ?>/public/contractor/homeowner_jobs.php">Back to Owner Jobs</a>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[action*="action=contractor.review_submission"]').forEach((form) => {
        const decisionEl = form.querySelector('select[name="decision"]');
        const commentsEl = form.querySelector('input[name="comments"]');
        if (!decisionEl || !commentsEl) return;

        const syncRequired = () => {
            const needsComment = decisionEl.value === 'reject' || decisionEl.value === 'needs_changes';
            commentsEl.required = needsComment;
            if (needsComment) {
                commentsEl.setAttribute('aria-required', 'true');
            } else {
                commentsEl.removeAttribute('aria-required');
            }
        };

        decisionEl.addEventListener('change', syncRequired);
        syncRequired();
    });
});
</script>

</body>
</html>
