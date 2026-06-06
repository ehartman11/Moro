<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Repositories\VerificationRepository;
use Moro\Services\VerificationReviewService;

$userId = Auth::requireLogin();
$baseUrl = Paths::baseUrl();
Auth::requireAdmin($baseUrl . '/public/homes.php');

$service = new VerificationReviewService(Db::pdo(), new VerificationRepository(Db::pdo()));
$statusFilter = (string)($_GET['status'] ?? 'pending_review');
$subjectFilter = (string)($_GET['subject_type'] ?? 'all');
if (!in_array($statusFilter, ['pending_review', 'verified', 'rejected', 'revoked', 'all'], true)) {
    $statusFilter = 'pending_review';
}
if (!in_array($subjectFilter, ['home_owner_claim', 'contractor_profile', 'all'], true)) {
    $subjectFilter = 'all';
}
$cases = $service->listCases($statusFilter, $subjectFilter);
$eventsByCase = $service->listEventsByCase($cases);

$documentsByCase = [];
foreach ($cases as $case) {
    $caseId = (int)($case['id'] ?? 0);
    $documentsByCase[$caseId] = $service->listCaseDocuments($caseId);
}

$flashSuccess = '';
$flashError = '';

if (isset($_GET['reviewed'])) {
    $flashSuccess = 'Verification case review saved.';
}

if (isset($_GET['err'])) {
    $flashError = match ((string)$_GET['err']) {
        'admin_required' => 'Administrator access is required.',
        'verification_case_required' => 'Verification case is required.',
        'verification_decision_invalid' => 'Review decision is invalid.',
        'verification_notes_required' => 'Notes are required when rejecting a case.',
        'verification_notes_too_long' => 'Notes are too long.',
        'verification_case_not_pending' => 'Case is no longer pending review.',
        'verification_transition_invalid' => 'That decision is not available for the current case status.',
        'verification_subject_invalid' => 'Verification case subject is invalid.',
        'verification_review_failed' => 'Unable to save review decision.',
        default => 'An error occurred.',
    };
}

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$subjectLabel = static function (array $case): string {
    $subjectType = (string)($case['subject_type'] ?? '');
    if ($subjectType === 'home_owner_claim') {
        $nickname = trim((string)($case['home_nickname'] ?? ''));
        $address = trim((string)($case['address_line1'] ?? ''));
        $city = trim((string)($case['city'] ?? ''));
        $state = trim((string)($case['state'] ?? ''));

        $label = $nickname !== '' ? $nickname : 'Home #' . (int)($case['subject_id'] ?? 0);
        $addressLine = trim($address . ($city !== '' ? ', ' . $city : '') . ($state !== '' ? ', ' . $state : ''));
        if ($addressLine !== '') {
            $label .= ' — ' . $addressLine;
        }
        return $label;
    }

    $business = trim((string)($case['business_name'] ?? ''));
    $display = trim((string)($case['display_name'] ?? ''));
    if ($business !== '' && $display !== '') {
        return $business . ' — ' . $display;
    }
    if ($business !== '') {
        return $business;
    }

    return 'Contractor User #' . (int)($case['subject_id'] ?? 0);
};

$submitterLabel = static function (array $case): string {
    $name = trim((string)($case['submitted_by_fname'] ?? '') . ' ' . (string)($case['submitted_by_lname'] ?? ''));
    if ($name === '') {
        return 'User #' . (int)($case['submitted_by_user_id'] ?? 0);
    }
    return $name . ' (User #' . (int)($case['submitted_by_user_id'] ?? 0) . ')';
};

$reviewerLabel = static function (array $case): string {
    $reviewerId = (int)($case['reviewed_by_user_id'] ?? 0);
    if ($reviewerId <= 0) {
        return '—';
    }

    $name = trim((string)($case['reviewed_by_fname'] ?? '') . ' ' . (string)($case['reviewed_by_lname'] ?? ''));
    if ($name === '') {
        return 'User #' . $reviewerId;
    }

    return $name . ' (User #' . $reviewerId . ')';
};

$statusClass = static function (string $status): string {
    return match ($status) {
        'verified' => 'is-verified',
        'pending_review' => 'is-pending',
        'rejected', 'revoked' => 'is-rejected',
        default => 'is-unverified',
    };
};

$eventActorLabel = static function (array $event): string {
    $name = trim((string)($event['actor_fname'] ?? '') . ' ' . (string)($event['actor_lname'] ?? ''));
    if ($name === '') {
        return 'User #' . (int)($event['actor_user_id'] ?? 0);
    }
    return $name;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verification Queue</title>
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/tables.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/homes.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/admin.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<section>
    <h2 class="form-title">Verification Queue (Admin)</h2>

    <?php if ($flashSuccess !== ''): ?>
        <div class="popup show" style="background:#4CAF50;"><?= $esc($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError !== ''): ?>
        <div class="popup show" style="background:#e74c3c;"><?= $esc($flashError) ?></div>
    <?php endif; ?>

    <p class="muted">Review pending ownership and contractor verification requests.</p>

    <form class="admin-filter-form" method="get" action="<?= $baseUrl ?>/public/admin/verification_queue.php">
        <div class="row">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach (['pending_review' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected', 'revoked' => 'Revoked', 'all' => 'All'] as $value => $label): ?>
                    <option value="<?= $esc($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= $esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <label for="subject_type">Type</label>
            <select id="subject_type" name="subject_type">
                <?php foreach (['all' => 'All', 'home_owner_claim' => 'Home Ownership', 'contractor_profile' => 'Contractor'] as $value => $label): ?>
                    <option value="<?= $esc($value) ?>" <?= $subjectFilter === $value ? 'selected' : '' ?>><?= $esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <button type="submit">Filter</button>
        </div>
    </form>

    <table class="admin-verification-table">
        <tr>
            <th>Case</th>
            <th>Subject</th>
            <th>Submitted By</th>
            <th>Submitted At</th>
            <th>Reviewed</th>
            <th>Documents</th>
            <th>Audit</th>
            <th>Action</th>
        </tr>

        <?php if (count($cases) === 0): ?>
            <tr>
                <td colspan="8"><span class="muted">No verification cases match these filters.</span></td>
            </tr>
        <?php else: ?>
            <?php foreach ($cases as $case): ?>
                <?php
                    $caseId = (int)($case['id'] ?? 0);
                    $docs = $documentsByCase[$caseId] ?? [];
                    $subjectType = (string)($case['subject_type'] ?? '');
                    $status = (string)($case['status'] ?? 'unverified');
                    $events = $eventsByCase[$caseId] ?? [];
                ?>
                <tr>
                    <td>
                        #<?= $caseId ?><br>
                        <span class="admin-status-pill <?= $esc($statusClass($status)) ?>"><?= $esc($status) ?></span>
                    </td>
                    <td>
                        <strong><?= $esc($subjectType === 'home_owner_claim' ? 'Home Claim' : 'Contractor Profile') ?></strong><br>
                        <?= $esc($subjectLabel($case)) ?>
                    </td>
                    <td><?= $esc($submitterLabel($case)) ?></td>
                    <td><?= $esc((string)($case['submitted_at'] ?? '—')) ?></td>
                    <td>
                        <?= $esc((string)($case['reviewed_at'] ?? '—')) ?><br>
                        <span class="muted"><?= $esc($reviewerLabel($case)) ?></span>
                        <?php if (!empty($case['review_notes'])): ?>
                            <div class="admin-review-note"><?= nl2br($esc((string)$case['review_notes'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (count($docs) === 0): ?>
                            <span class="muted">No documents attached</span>
                        <?php else: ?>
                            <ul class="admin-doc-list">
                                <?php foreach ($docs as $doc): ?>
                                    <li>
                                        <a href="<?= $baseUrl ?>/public/admin/verification_document.php?id=<?= (int)($doc['id'] ?? 0) ?>" target="_blank" rel="noopener">
                                            <?= $esc((string)($doc['doc_type'] ?? 'document')) ?>
                                        </a>
                                        <span class="muted">(<?= $esc((string)($doc['mime_type'] ?? 'file')) ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (count($events) === 0): ?>
                            <span class="muted">No events recorded</span>
                        <?php else: ?>
                            <ul class="admin-event-list">
                                <?php foreach ($events as $event): ?>
                                    <li>
                                        <strong><?= $esc((string)($event['to_status'] ?? '')) ?></strong>
                                        <span class="muted">
                                            from <?= $esc((string)($event['from_status'] ?? 'new')) ?>
                                            by <?= $esc($eventActorLabel($event)) ?>
                                            at <?= $esc((string)($event['created_at'] ?? '')) ?>
                                        </span>
                                        <?php if (!empty($event['notes'])): ?>
                                            <div><?= nl2br($esc((string)$event['notes'])) ?></div>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($status === 'pending_review' || $status === 'verified'): ?>
                            <form class="admin-review-form" method="post" action="<?= $baseUrl ?>/public/actions.php?action=verification.review_case">
                                <input type="hidden" name="review_verification_case" value="1">
                                <input type="hidden" name="csrf" value="<?= $esc(Auth::csrfToken()) ?>">
                                <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/admin/verification_queue.php?status=<?= urlencode($statusFilter) ?>&subject_type=<?= urlencode($subjectFilter) ?>">
                                <input type="hidden" name="verification_case_id" value="<?= $caseId ?>">

                                <select name="decision" required>
                                    <?php if ($status === 'pending_review'): ?>
                                        <option value="approve">approve</option>
                                        <option value="reject">reject</option>
                                    <?php elseif ($status === 'verified'): ?>
                                        <option value="revoke">revoke</option>
                                    <?php endif; ?>
                                </select>
                                <textarea name="notes" rows="2" maxlength="4000" placeholder="Review notes"></textarea>
                                <button type="submit">Save Review</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">No action available</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.admin-review-form').forEach((form) => {
        const decisionEl = form.querySelector('select[name="decision"]');
        const notesEl = form.querySelector('textarea[name="notes"]');
        if (!decisionEl || !notesEl) return;

        const sync = () => {
            const requiresNotes = decisionEl.value === 'reject' || decisionEl.value === 'revoke';
            notesEl.required = requiresNotes;
        };

        decisionEl.addEventListener('change', sync);
        sync();
    });
});
</script>

</body>
</html>
