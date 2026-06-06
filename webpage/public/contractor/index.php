<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Http\Controllers\Contractor\ContractorProfileController;

$userId = Auth::requireLogin();
$pdo = Db::pdo();
$homeId = Auth::activeHomeId();
$role = 'viewer';
if ($homeId !== null) {
    $role = Auth::roleOnHome($pdo, $userId, $homeId);
}
$controller = new ContractorProfileController();
$vm = $controller->index($userId, $_GET);

$profile = $vm['profile'] ?? null;
$baseUrl = $vm['baseUrl'];
$contractorOptions = is_array($vm['contractorOptions'] ?? null) ? $vm['contractorOptions'] : [];
$contractorVerificationStatus = is_array($profile) ? (string)($profile['verification_status'] ?? 'unverified') : 'unverified';

$jobItems = [];
$jobTasksByItem = [];
if ($homeId !== null && $role === 'owner') {
    $stmtItems = $pdo->prepare("\n        SELECT id, name, category, brand, model\n        FROM items\n        WHERE home_id = :hid\n        ORDER BY name ASC, id ASC\n    ");
    $stmtItems->execute([':hid' => $homeId]);
    $itemRows = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($itemRows as $itemRow) {
        $itemId = (int)($itemRow['id'] ?? 0);
        if ($itemId <= 0) {
            continue;
        }

        $jobItems[] = [
            'id' => $itemId,
            'name' => trim((string)($itemRow['name'] ?? '')),
            'category' => trim((string)($itemRow['category'] ?? '')),
            'brand' => trim((string)($itemRow['brand'] ?? '')),
            'model' => trim((string)($itemRow['model'] ?? '')),
        ];
    }

    $stmtTasks = $pdo->prepare("\n        SELECT mt.id, mt.item_id, mt.task_name, mt.part_name, mt.priority\n        FROM maintenance_tasks mt\n        JOIN items i ON i.id = mt.item_id\n        WHERE i.home_id = :hid\n        ORDER BY i.name ASC, mt.task_name ASC, mt.id ASC\n    ");
    $stmtTasks->execute([':hid' => $homeId]);
    $taskRows = $stmtTasks->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($taskRows as $taskRow) {
        $itemId = (int)($taskRow['item_id'] ?? 0);
        $taskId = (int)($taskRow['id'] ?? 0);
        if ($itemId <= 0 || $taskId <= 0) {
            continue;
        }

        if (!isset($jobTasksByItem[$itemId])) {
            $jobTasksByItem[$itemId] = [];
        }

        $jobTasksByItem[$itemId][] = [
            'id' => $taskId,
            'task_name' => trim((string)($taskRow['task_name'] ?? '')),
            'part_name' => trim((string)($taskRow['part_name'] ?? '')),
            'priority' => trim((string)($taskRow['priority'] ?? '')),
        ];
    }
}

$contractorStateOptions = [];
$contractorCategoryOptions = [];
foreach ($contractorOptions as $option) {
    $state = strtoupper(trim((string)($option['license_state'] ?? '')));
    if ($state !== '') {
        $contractorStateOptions[$state] = $state;
    }

    $categories = is_array($option['service_categories'] ?? null) ? $option['service_categories'] : [];
    foreach ($categories as $category) {
        $label = trim((string)$category);
        if ($label !== '') {
            $contractorCategoryOptions[$label] = $label;
        }
    }
}

ksort($contractorStateOptions);
ksort($contractorCategoryOptions, SORT_NATURAL | SORT_FLAG_CASE);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contractor Portal</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/contractor.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/homes.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<section>
    <h2 class="form-title">Contractor Profile</h2>

    <?php if ($profile === null): ?>
        <div class="contractor-note">
            <strong>Activate Contracting</strong><br>
            Complete this profile and click <em>Save Profile</em> to activate your contractor role and unlock the full contracting suite.
        </div>
    <?php endif; ?>

    <?php if (($vm['flashSuccess'] ?? '') !== ''): ?>
        <div class="popup show" style="background:#4CAF50;"><?= h((string)$vm['flashSuccess']) ?></div>
    <?php endif; ?>

    <?php if (($vm['flashError'] ?? '') !== ''): ?>
        <div class="popup show" style="background:#e74c3c;"><?= h((string)$vm['flashError']) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['service_job_created'])): ?>
        <div class="popup show" style="background:#4CAF50;">Service job created.</div>
    <?php endif; ?>

    <?php if (isset($_GET['contractor_verification_submitted'])): ?>
        <div class="popup show" style="background:#4CAF50;">Contractor verification submitted for review.</div>
    <?php endif; ?>

    <?php if ($homeId === null): ?>
        <div class="popup show" style="background:#2f6db2;">Contracting is active. Set an active home to unlock home-scoped job queues and owner assignment tools.</div>
    <?php endif; ?>

    <?php if (isset($_GET['err'])): ?>
        <?php
            $err = (string)$_GET['err'];
            $jobErr = match ($err) {
                'portal_role_invalid' => 'Selected portal role is invalid.',
                'portal_role_unavailable' => 'That portal is not available in your current context.',
                'contractor_profile_required' => 'Complete your contractor profile to access Contracting.',
                'seeker_role_required' => 'Your current home context does not include seeker access.',
                'contractor_required' => 'A contractor selection is required.',
                'contractor_not_found' => 'Contractor user was not found.',
                'job_title_required' => 'Job title is required.',
                'job_priority_invalid' => 'Priority is invalid.',
                'item_invalid' => 'Item ID is invalid.',
                'task_invalid' => 'Task ID is invalid.',
                'task_item_mismatch' => 'Task does not belong to the selected item.',
                'contractor_verification_already_pending' => 'Contractor verification is already pending review.',
                'contractor_verification_already_verified' => 'Contractor profile is already verified.',
                'verification_doc_required' => 'A verification proof file is required.',
                'verification_doc_upload' => 'The verification file upload failed.',
                'verification_doc_too_large' => 'Verification file is too large (max 15MB).',
                'verification_doc_type_invalid' => 'Verification file type is invalid. Use PDF/JPG/PNG/WEBP.',
                'verification_submit_failed' => 'Unable to submit verification right now.',
                'unauthorized' => 'You are not authorized for this action.',
                'service_job_create_failed' => 'Unable to create service job.',
                default => ''
            };
        ?>
        <?php if ($jobErr !== ''): ?>
            <div class="popup show" style="background:#e74c3c;"><?= h($jobErr) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="verification-submit contractor-verification-panel">
        <div class="verification-submit-inner">
            <div>
                <strong>Contractor Verification</strong>
                <p class="muted" style="margin:4px 0 0 0;">Status: <span class="verification-pill is-<?= h($contractorVerificationStatus) ?>"><?= h($contractorVerificationStatus) ?></span></p>
            </div>

            <?php if ($profile !== null && !in_array($contractorVerificationStatus, ['pending_review', 'verified'], true)): ?>
                <form method="post" action="<?= $baseUrl ?>/public/actions.php?action=contractor.submit_verification" enctype="multipart/form-data">
                    <input type="hidden" name="submit_contractor_verification" value="1">
                    <input type="hidden" name="csrf" value="<?= h((string)$vm['csrf']) ?>">
                    <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/contractor/index.php">
                    <div class="verification-inputs">
                        <label for="contractor_doc_type">Proof Type</label>
                        <select id="contractor_doc_type" name="doc_type" required>
                            <option value="license">License</option>
                            <option value="insurance">Insurance</option>
                            <option value="business_registration">Business registration</option>
                            <option value="other">Other</option>
                        </select>

                        <label for="contractor_verification_file">Proof File (PDF or image)</label>
                        <input id="contractor_verification_file" type="file" name="verification_file" accept="application/pdf,image/jpeg,image/png,image/webp" required>
                    </div>
                    <button type="submit">Submit for Verification</button>
                </form>
            <?php elseif ($profile === null): ?>
                <span class="muted">Complete your profile first to submit verification.</span>
            <?php endif; ?>
        </div>
    </div>

    <form class="contractor-form" method="post" action="<?= $baseUrl ?>/public/actions.php?action=contractor.save_profile">
        <input type="hidden" name="csrf" value="<?= h((string)$vm['csrf']) ?>">

        <label for="business_name">Business Name</label>
        <input id="business_name" name="business_name" required value="<?= h((string)($profile['business_name'] ?? '')) ?>">

        <label for="display_name">Display Name</label>
        <input id="display_name" name="display_name" value="<?= h((string)($profile['display_name'] ?? '')) ?>">

        <label for="phone">Phone</label>
        <input id="phone" name="phone" value="<?= h((string)($profile['phone'] ?? '')) ?>">

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?= h((string)($profile['email'] ?? '')) ?>">

        <label for="website">Website</label>
        <input id="website" name="website" value="<?= h((string)($profile['website'] ?? '')) ?>">

        <label for="service_categories">Service Categories (comma separated)</label>
        <input id="service_categories" name="service_categories" value="<?= h((string)($vm['categoriesText'] ?? '')) ?>">

        <label for="license_number">License Number</label>
        <input id="license_number" name="license_number" value="<?= h((string)($profile['license_number'] ?? '')) ?>">

        <label for="license_state">License State</label>
        <input id="license_state" name="license_state" value="<?= h((string)($profile['license_state'] ?? '')) ?>">

        <label class="checkbox-row">
            <input type="checkbox" name="insured" value="1" <?= !empty($profile['insured']) ? 'checked' : '' ?>>
            Insured
        </label>

        <label for="bio">Bio</label>
        <textarea id="bio" name="bio" rows="5"><?= h((string)($profile['bio'] ?? '')) ?></textarea>

        <div class="form-actions">
            <button type="submit">Save Profile</button>
        </div>
    </form>

    <?php if ($homeId !== null && $role === 'owner'): ?>
        <hr class="contractor-divider">

        <h3>Create Service Job (Owner)</h3>
        <form class="contractor-form" method="post" action="<?= $baseUrl ?>/public/actions.php?action=contractor.create_job">
            <input type="hidden" name="create_service_job" value="1">
            <input type="hidden" name="csrf" value="<?= h((string)$vm['csrf']) ?>">
            <input type="hidden" name="return_to" value="<?= $baseUrl ?>/public/contractor/index.php">

            <div class="contractor-filter-grid">
                <div>
                    <label for="contractor_search">Find Contractor</label>
                    <input id="contractor_search" type="text" placeholder="Search by business or person name">
                </div>
                <div>
                    <label for="contractor_state_filter">License State</label>
                    <select id="contractor_state_filter">
                        <option value="">All states</option>
                        <?php foreach ($contractorStateOptions as $state): ?>
                            <option value="<?= h(strtolower($state)) ?>"><?= h($state) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="contractor_category_filter">Service Category</label>
                    <select id="contractor_category_filter">
                        <option value="">All categories</option>
                        <?php foreach ($contractorCategoryOptions as $category): ?>
                            <option value="<?= h(strtolower($category)) ?>"><?= h($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <label for="contractor_user_id">Contractor *</label>
            <select id="contractor_user_id" name="contractor_user_id" required>
                <option value="">Select contractor</option>
                <?php foreach ($contractorOptions as $option): ?>
                    <?php
                        $contractorId = (int)($option['user_id'] ?? 0);
                        $businessName = trim((string)($option['business_name'] ?? ''));
                        $displayName = trim((string)($option['display_name'] ?? ''));
                        $personName = trim((string)($option['person_name'] ?? ''));
                        $licenseState = strtolower(trim((string)($option['license_state'] ?? '')));
                        $serviceCategories = array_values(array_filter(array_map(
                            static fn(mixed $value): string => strtolower(trim((string)$value)),
                            is_array($option['service_categories'] ?? null) ? $option['service_categories'] : []
                        ), static fn(string $value): bool => $value !== ''));
                        $categoriesToken = implode('|', $serviceCategories);
                        $label = $businessName;
                        if ($displayName !== '') {
                            $label .= ' — ' . $displayName;
                        }
                        if ($personName !== '') {
                            $label .= ' (' . $personName . ')';
                        }
                    ?>
                    <?php if ($contractorId > 0): ?>
                        <option
                            value="<?= $contractorId ?>"
                            data-label="<?= h(strtolower($label)) ?>"
                            data-state="<?= h($licenseState) ?>"
                            data-categories="<?= h($categoriesToken) ?>"
                        >
                            <?= h($label) ?> (User #<?= $contractorId ?>)
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <p id="contractor_filter_hint" class="muted contractor-filter-hint">Showing all contractors.</p>
            <?php if (count($contractorOptions) === 0): ?>
                <p style="margin-top:6px; color:#a33;">No contractor profiles are available yet.</p>
            <?php endif; ?>

            <label for="job_title">Title *</label>
            <input id="job_title" name="title" required>

            <label for="job_description">Description</label>
            <textarea id="job_description" name="description" rows="4"></textarea>

            <label for="job_item_id">Item (optional)</label>
            <select id="job_item_id" name="item_id">
                <option value="">No item selected</option>
                <?php foreach ($jobItems as $item): ?>
                    <?php
                        $itemLabel = (string)$item['name'];
                        if ((string)$item['category'] !== '') {
                            $itemLabel .= ' · ' . (string)$item['category'];
                        }
                        if ((string)$item['brand'] !== '') {
                            $itemLabel .= ' · ' . (string)$item['brand'];
                        }
                        if ((string)$item['model'] !== '') {
                            $itemLabel .= ' ' . (string)$item['model'];
                        }
                    ?>
                    <option value="<?= (int)$item['id'] ?>"><?= h($itemLabel) ?> (ID #<?= (int)$item['id'] ?>)</option>
                <?php endforeach; ?>
            </select>

            <label for="job_task_id">Task (optional)</label>
            <select id="job_task_id" name="task_id" disabled>
                <option value="">Select an item first</option>
            </select>
            <p class="muted contractor-field-note">Task options are loaded based on the selected item.</p>

            <label for="job_priority">Priority</label>
            <select id="job_priority" name="priority">
                <option value="low">low</option>
                <option value="medium" selected>medium</option>
                <option value="high">high</option>
            </select>

            <label for="job_scheduled_for">Scheduled For (optional)</label>
            <input id="job_scheduled_for" name="scheduled_for" type="datetime-local">

            <label for="job_due_at">Due At (optional)</label>
            <input id="job_due_at" name="due_at" type="datetime-local">

            <div class="form-actions">
                <button type="submit">Create Service Job</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="contractor-links">
        <?php if ($homeId !== null): ?>
            <a href="<?= $baseUrl ?>/public/contractor/my_jobs.php">My Jobs</a>
            &nbsp;|&nbsp;
            <?php if ($role === 'owner'): ?>
                <a href="<?= $baseUrl ?>/public/contractor/homeowner_jobs.php">Owner Job Inbox</a>
                &nbsp;|&nbsp;
            <?php endif; ?>
            <a href="<?= $baseUrl ?>/public/actions.php?action=contractor.jobs_list">View My Jobs (JSON)</a>
            &nbsp;|&nbsp;
            <a href="<?= $baseUrl ?>/public/items/index.php">Back to Items</a>
        <?php else: ?>
            <a href="<?= $baseUrl ?>/public/homes.php">Set Active Home</a>
            &nbsp;|&nbsp;
            <a href="<?= $baseUrl ?>/public/index.php">Back Home</a>
        <?php endif; ?>
    </div>
</section>

<?php if ($homeId !== null && $role === 'owner'): ?>
<script>
(() => {
    const contractorSearchEl = document.getElementById('contractor_search');
    const contractorStateEl = document.getElementById('contractor_state_filter');
    const contractorCategoryEl = document.getElementById('contractor_category_filter');
    const contractorSelectEl = document.getElementById('contractor_user_id');
    const contractorHintEl = document.getElementById('contractor_filter_hint');

    const originalContractorOptions = Array.from(contractorSelectEl.querySelectorAll('option'));

    const applyContractorFilters = () => {
        const selectedValue = contractorSelectEl.value;
        const query = (contractorSearchEl.value || '').trim().toLowerCase();
        const state = (contractorStateEl.value || '').trim().toLowerCase();
        const category = (contractorCategoryEl.value || '').trim().toLowerCase();

        contractorSelectEl.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select contractor';
        contractorSelectEl.appendChild(placeholder);

        let visibleCount = 0;
        for (const option of originalContractorOptions) {
            if (!option.value) {
                continue;
            }

            const label = (option.dataset.label || '').toLowerCase();
            const optionState = (option.dataset.state || '').toLowerCase();
            const categories = (option.dataset.categories || '').split('|').map((value) => value.trim()).filter(Boolean);

            if (query && !label.includes(query)) {
                continue;
            }
            if (state && optionState !== state) {
                continue;
            }
            if (category && !categories.includes(category)) {
                continue;
            }

            contractorSelectEl.appendChild(option.cloneNode(true));
            visibleCount += 1;
        }

        contractorSelectEl.value = selectedValue;
        if (contractorSelectEl.value !== selectedValue) {
            contractorSelectEl.value = '';
        }

        if (visibleCount === 0) {
            contractorHintEl.textContent = 'No contractors match the current filters.';
            return;
        }

        if (!query && !state && !category) {
            contractorHintEl.textContent = 'Showing all contractors.';
            return;
        }

        contractorHintEl.textContent = `Showing ${visibleCount} matching contractor${visibleCount === 1 ? '' : 's'}.`;
    };

    contractorSearchEl.addEventListener('input', applyContractorFilters);
    contractorStateEl.addEventListener('change', applyContractorFilters);
    contractorCategoryEl.addEventListener('change', applyContractorFilters);
    applyContractorFilters();

    const itemSelectEl = document.getElementById('job_item_id');
    const taskSelectEl = document.getElementById('job_task_id');
    const taskOptionsByItem = <?= json_encode($jobTasksByItem, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    const populateTaskOptions = () => {
        const itemId = itemSelectEl.value;
        taskSelectEl.innerHTML = '';

        if (!itemId) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Select an item first';
            taskSelectEl.appendChild(option);
            taskSelectEl.disabled = true;
            return;
        }

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = 'No task selected';
        taskSelectEl.appendChild(emptyOption);

        const taskRows = Array.isArray(taskOptionsByItem[itemId]) ? taskOptionsByItem[itemId] : [];
        for (const row of taskRows) {
            const option = document.createElement('option');
            option.value = String(row.id || '');
            const part = (row.part_name || '').trim();
            const priority = (row.priority || '').trim();

            let label = (row.task_name || 'Task').trim();
            if (part !== '') {
                label += ` · ${part}`;
            }
            if (priority !== '') {
                label += ` (${priority})`;
            }
            option.textContent = `${label} (ID #${row.id})`;
            taskSelectEl.appendChild(option);
        }

        taskSelectEl.disabled = false;
    };

    itemSelectEl.addEventListener('change', populateTaskOptions);
    populateTaskOptions();
})();
</script>
<?php endif; ?>

</body>
</html>
