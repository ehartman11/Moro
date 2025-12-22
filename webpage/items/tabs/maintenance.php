<?php
/**
 * Maintenance tab (per-item).
 *
 * Responsibilities:
 * - Requires a selected item; otherwise shows an empty state.
 * - Lists maintenance tasks for the item along with their next scheduled due date.
 * - Allows owners to mark a task complete (optionally with note/cost), which advances the schedule.
 * - Provides an “Add Task” form that creates + schedules a new maintenance task for this item.
 * - Includes a lightweight modal for viewing task details without leaving the page.
 */
?>

<?php if (!$selectedItem): ?>
    <div class="empty-state">
        <h2>Select an item</h2>
        <p>Pick an item on the left to view its maintenance.</p>
    </div>
<?php return; endif; ?>

<?php include __DIR__ . "/../_item_header.php"; ?>

<?php
$itemId = (int)$selectedItem['id'];

// Fetch tasks for this item + next due date from task_schedule.
// NOTE: This assumes task_schedule is 1:1 with maintenance_tasks (single active schedule row per task).
$stmt = $pdo->prepare("
    SELECT
        t.*,
        s.due_date AS next_due
    FROM maintenance_tasks t
    LEFT JOIN task_schedule s ON s.task_id = t.id
    WHERE t.item_id = :iid
    ORDER BY
        FIELD(t.priority, 'high','medium','low'),
        t.created_at DESC
");
$stmt->execute([':iid' => $itemId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (isset($_GET['task_added'])): ?>
    <div class="popup show" style="background:#4CAF50;">Task created and scheduled.</div>
<?php endif; ?>

<?php if (isset($_GET['completed'])): ?>
    <div class="popup show" style="background:#4CAF50;">Task marked complete. Next due date updated.</div>
<?php endif; ?>

<?php if (isset($_GET['err'])): ?>
    <div class="popup show" style="background:#e74c3c;">
        <?php
            // Stable error-code -> message mapping keeps the UI consistent and avoids leaking internals.
            $err = $_GET['err'];
            $msg = match ($err) {
                'task_invalid'       => 'Please fill out task name and a valid frequency.',
                'task_add_failed'    => 'Failed to create task.',
                'bad_request'        => 'Bad request.',
                'complete_invalid'   => 'Invalid completion request.',
                'complete_bad_date'  => 'Invalid completion date.',
                'unauthorized'       => 'You are not authorized to complete tasks for this home.',
                'complete_not_found' => 'Task not found or schedule missing.',
                'complete_failed'    => 'Failed to mark complete.',
                default              => 'An error occurred.'
            };
            echo h($msg);
        ?>
    </div>
<?php endif; ?>

<section class="item-tab">

    <h3 style="margin-top:0;">Maintenance Tasks</h3>

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

                    // Compare against today's date (local server time) to flag overdue tasks.
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
                        <?php if ($userRoleOnHome === 'owner'): ?>
                            <?php if (!empty($due)): ?>
                                <details class="complete-details">
                                    <summary class="complete-summary">Mark Complete</summary>

                                    <form method="POST"
                                          action="<?= APP_URL ?>/items/actions/complete_task.php"
                                          style="margin-top:10px;">

                                        <input type="hidden" name="complete_task" value="1">
                                        <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                                        <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
                                        <input type="hidden" name="return_to"
                                               value="<?= APP_URL ?>/items/index.php?item_id=<?= (int)$itemId ?>&tab=maintenance">

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

    <h3>Add Task</h3>

    <form method="POST" action="actions/add_task.php">
        <input type="hidden" name="add_task" value="1">
        <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
        <input type="hidden" name="return_to"
               value="<?= APP_URL ?>/items/index.php?item_id=<?= (int)$itemId ?>&tab=maintenance">

        <div class="row">
            <label>Task Name *</label>
            <input type="text" name="task_name" required>
        </div>

        <div class="row">
            <label>Description</label>
            <input type="text" name="description">
        </div>

        <div class="row">
            <label>Frequency *</label>
            <div style="display:flex; gap:10px;">
                <input type="number" name="frequency_value" min="1" required style="max-width:140px;">
                <select name="frequency_unit" required>
                    <option value="days">days</option>
                    <option value="weeks">weeks</option>
                    <option value="months" selected>months</option>
                    <option value="years">years</option>
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
                Leave blank to auto-schedule using the frequency starting from today.
            </div>
        </div>

        <div class="row">
            <input type="submit" value="Create Task">
        </div>
    </form>

    <!-- Task details modal is populated from row data-* attributes (no extra API call). -->
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
                <a id="taskModalViewBtn" class="btn" href="#">View full task</a>
                <button type="button" class="btn btn-secondary" id="taskModalOk">Close</button>
            </div>
        </div>
    </div>

    <script>
        $(function () {
            function openTaskModal(id, name, desc) {
                $("#taskModalTitle").text(name || "Task");
                $("#taskModalDesc").text(desc || "No description provided.");
                $("#taskModalViewBtn").attr("href", "<?= APP_URL ?>/items/task.php?id=" + id);
                $("#taskModal").addClass("show").attr("aria-hidden", "false");
            }

            function closeTaskModal() {
                $("#taskModal").removeClass("show").attr("aria-hidden", "true");
            }

            $(document).on("click", ".task-link", function (e) {
                e.preventDefault();
                openTaskModal(
                    $(this).data("task-id"),
                    $(this).data("task-name"),
                    $(this).data("task-desc")
                );
            });

            $(document).on("click", "#taskModalClose, #taskModalOk, #taskModal .modal-backdrop", closeTaskModal);

            $(document).on("keydown", function (e) {
                if (e.key === "Escape") closeTaskModal();
            });
        });
    </script>

</section>
