<?php

use Moro\Core\Paths;
use Moro\Core\Auth;
use Moro\Repositories\MaintenanceRepository;
use Moro\Services\MaintenanceViewService;

if (!$selectedItem): ?>
    <div class="empty-state">
        <h2>Select an item</h2>
        <p>Pick an item on the left to view its maintenance.</p>
    </div>
<?php return; endif; ?>

<?php include __DIR__ . "/../_item_header.php"; ?>

<?php
$baseUrl = Paths::baseUrl();
$itemId  = (int)$selectedItem['id'];
$csrf = Auth::csrfToken();

$repo = new MaintenanceRepository($pdo);
$svc  = new MaintenanceViewService($repo);

$data = $svc->getMaintenanceTabData($itemId);

$tasks   = $data['tasks'];
$manuals = $data['manuals'];
?>

<script>
  window.MORO_BASE_URL = <?= json_encode($baseUrl) ?>;
</script>
<script src="<?= $baseUrl ?>/public/assets/js/maintenance.js"></script>

<?php if (isset($_GET['task_added'])): ?>
    <div class="popup show" style="background:#4CAF50;">Task created and scheduled.</div>
<?php endif; ?>

<?php if (isset($_GET['completed'])): ?>
    <div class="popup show" style="background:#4CAF50;">Task marked complete. Next due date updated.</div>
<?php endif; ?>

<?php if (isset($_GET['manual_added'])): ?>
    <div class="popup show" style="background:#4CAF50;">Manual uploaded.</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
    <div class="popup show" style="background:#e74c3c;">
        <?php
            $err = (string)$_GET['err'];
            $msg = match ($err) {
                'task_invalid'            => 'Please fill out task name and a valid frequency.',
                'task_add_requires_due'   => 'Seasonal tasks require a first due date.',
                'task_bad_due'            => 'Invalid due date.',
                'task_add_failed'         => 'Failed to create task.',
                'bad_request'             => 'Bad request.',
                'complete_invalid'        => 'Invalid completion request.',
                'complete_failed'         => 'Failed to mark complete.',
                'unauthorized'            => 'You are not authorized for this action.',
                'manual_invalid'          => 'Manual info missing.',
                'manual_upload'           => 'Manual upload failed.',
                'manual_not_pdf'          => 'Manual must be a PDF.',
                'manual_too_large'        => 'Manual is too large.',
                'manual_failed'           => 'Manual upload failed.',
                default                   => 'An error occurred.'
            };
            echo h($msg);
        ?>
    </div>
<?php endif; ?>

<section class="item-tab">
    <h3 style="margin-top:0;">Manuals</h3>

    <?php if (!$manuals): ?>
        <p class="muted">No manuals attached yet.</p>
    <?php else: ?>
        <ul style="margin:12px 0; padding-left:18px;">
            <?php foreach ($manuals as $m): ?>
                <li style="margin-bottom:8px;">
                    <strong><?= h($m['title']) ?></strong>
                    <span class="muted">(<?= h($m['language']) ?>)</span>
                    <a class="btn btn-secondary"
                              href="<?= $baseUrl ?>/public/actions.php?action=items.download_manual&id=<?= (int)$m['id'] ?>">
                       View / Download
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3 style="margin-top:18px;">Maintenance Tasks</h3>

    <?php if (!$tasks): ?>
        <p class="muted">No maintenance tasks yet for this item.</p>
    <?php else: ?>
        <table class="detail-table" style="margin-top:12px;">
            <tr>
                <th>Task</th>
                <th>Next Due</th>
                <th>Frequency</th>
                <th>Priority</th>
                <th>Action</th>
            </tr>

            <?php foreach ($tasks as $t): ?>
                <?php
                    $due = $t['next_due'] ?? null;
                    $isOverdue = (!empty($due) && strtotime($due) < strtotime(date('Y-m-d')));
                ?>
                <tr>
                    <td>
                        <a href="#"
                           class="task-link"
                           data-task-id="<?= (int)$t['id'] ?>"
                           data-task-name="<?= h($t['task_name']) ?>"
                           data-task-desc="<?= h($t['description'] ?? '') ?>">
                            <?= h($t['task_name']) ?>
                        </a>
                    </td>

                    <td>
                        <?php if (!empty($due)): ?>
                            <span class="<?= $isOverdue ? 'danger-text' : '' ?>">
                                <?= h($due) ?>
                                <?php if ($isOverdue): ?>
                                    <span class="muted">(overdue)</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>

                    <td><?= h($t['frequency_value']) . ' ' . h($t['frequency_unit']) ?></td>
                    <td><?= h($t['priority']) ?></td>

                    <td>
                        <?php if ($role === 'owner'): ?>
                            <?php if (!empty($due)): ?>
                                <details class="complete-details">
                                    <summary class="complete-summary">Mark Complete</summary>

                                    <form method="POST"
                                        action="<?= $baseUrl ?>/public/actions.php?action=items.complete_task"
                                          style="margin-top:10px;">

                                        <input type="hidden" name="complete_task" value="1">
                                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                        <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
                                        <input type="hidden" name="return_to"
                                                 value="<?= $baseUrl ?>/public/items/index.php?item_id=<?= (int)$itemId ?>&tab=maintenance">

                                        <div class="row">
                                            <label>Completion Date</label>
                                            <input type="date" name="completed_on" value="<?= date('Y-m-d') ?>" required>
                                        </div>

                                        <div class="row">
                                            <label>Completion Note (optional)</label>
                                            <input type="text" name="note" maxlength="255">
                                        </div>

                                        <div class="row">
                                            <label>Cost (optional)</label>
                                            <input type="number" step="0.01" min="0" name="cost">
                                        </div>

                                        <div class="row">
                                            <input type="submit" value="Confirm Complete">
                                        </div>
                                    </form>
                                </details>
                            <?php else: ?>
                                <span class="muted">Not scheduled</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted">Owner only</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <hr style="margin:30px 0; border:0; border-top:1px solid #e6e9ef;">

    <?php if ($role !== 'owner'): ?>
        <p class="muted">Only owners can add tasks or upload manuals.</p>
        <?php return; ?>
    <?php endif; ?>

    <h3>Add Task</h3>

    <form method="POST" action="<?= $baseUrl ?>/public/actions.php?action=items.add_task">
        <input type="hidden" name="add_task" value="1">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
        <input type="hidden" name="return_to"
               value="<?= $baseUrl ?>/public/items/index.php?item_id=<?= (int)$itemId ?>&tab=maintenance">

        <div class="row">
            <label>Task Name *</label>
            <input type="text" name="task_name" required>
        </div>

        <div class="row">
            <label>Description</label>
            <input type="text" name="description">
        </div>

        <div class="row">
            <label>Part Name</label>
            <input type="text" name="part_name">
        </div>

        <div class="row">
            <label>Schedule Type *</label>
            <select name="schedule_type" id="schedule_type" required>
                <option value="" selected disabled>Select…</option>
                <option value="calendar">calendar</option>
                <option value="per_use">per_use</option>
                <option value="seasonal">seasonal</option>
                <option value="condition">condition</option>
                <option value="metered">metered</option>
            </select>
        </div>

        <div class="row">
            <label>Frequency *</label>
            <div style="display:flex; gap:10px;">
                <input type="number" name="frequency_value" id="frequency_value" min="1" required style="max-width:140px;" disabled>
                <select name="frequency_unit" id="frequency_unit" required disabled>
                    <option value="" selected>Select schedule type first…</option>
                </select>
            </div>
        </div>

        <div class="row">
            <label>Priority</label>
            <select name="priority">
                <option value="low">low</option>
                <option value="medium" selected>medium</option>
                <option value="high">high</option>
            </select>
        </div>

        <div class="row">
            <label>First Due Date (optional)</label>
            <input type="date" name="first_due_date">
            <div class="muted" style="margin-top:6px;">
                Required for seasonal tasks. 
            </div>
        </div>

        <div class="row">
            <input type="submit" value="Create Task">
        </div>
    </form>

    <hr style="margin:30px 0; border:0; border-top:1px solid #e6e9ef;">

    <h3>Add Manual</h3>

    <form method="POST"
            action="<?= $baseUrl ?>/public/actions.php?action=items.add_manual"
          enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
        <input type="hidden" name="return_to"
             value="<?= $baseUrl ?>/public/items/index.php?item_id=<?= (int)$itemId ?>&tab=maintenance">

        <div class="row">
            <label>Manual Title *</label>
            <input type="text" name="manual_title" required>
        </div>

        <div class="row">
            <label>Language</label>
            <select name="language">
                <option value="english" selected>English</option>
                <option value="spanish">Spanish</option>
                <option value="french">French</option>
            </select>
        </div>

        <div class="row">
            <label>Source URL (optional)</label>
            <input type="text" name="source_url">
        </div>

        <div class="row">
            <label>PDF Manual *</label>
            <input type="file" name="manual_pdf" accept="application/pdf" required>
        </div>

        <div class="row">
            <input type="submit" value="Add Manual">
        </div>
    </form>

    <!-- Task modal (unchanged except APP_URL -> baseUrl) -->
    <div id="taskModal" class="modal" aria-hidden="true">
        <div class="modal-backdrop"></div>

        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="taskModalTitle">
            <div class="modal-header">
                <h3 id="taskModalTitle">Task</h3>
                <button type="button" class="modal-close" id="taskModalClose" aria-label="Close">×</button>
            </div>

            <div class="modal-body">
                <p id="taskModalDesc" class="muted"></p>
            </div>

            <div class="modal-actions">
                <a id="taskModalViewBtn" class="btn" href="#" onclick="">View full task</a>
                <button type="button" class="btn btn-secondary" id="taskModalOk">Close</button>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        window.MoroMaintenanceTab?.init(window.MORO_BASE_URL);
    });
    </script>

</section>
