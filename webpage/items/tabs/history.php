<?php
/**
 * History tab (render-only).
 *
 * Responsibilities:
 * - Requires a selected item; otherwise shows an empty state.
 * - Lists completed maintenance history entries for the item (task, completion date, cost, notes).
 * - Fetches and displays any photos associated to each history row.
 * - If owner: renders a form to add a new history entry (handled by actions/add_history.php).
 *
 * Assumptions:
 * - Parent scope provides: $pdo, $selectedItem, $userRoleOnHome.
 * - h() is defined in the parent (items/index.php) and should not be redeclared here.
 */

// Guard: must have selected item
if (!$selectedItem || empty($selectedItem['id'])): ?>
    <div class="empty-state">
        <h2>Select an item</h2>
        <p>Pick an item on the left to view its material history.</p>
    </div>
    <?php return; ?>
<?php endif;

$itemId = (int)$selectedItem['id'];

/* -----------------------------
   Flash messaging (via redirect)
------------------------------ */
$flashSuccess = '';
$flashError   = '';

if (isset($_GET['history_saved'])) {
    $flashSuccess = 'History entry saved.';
}

if (isset($_GET['err'])) {
    // Stable error-code -> message mapping keeps behavior consistent across redirects.
    $flashError = match ((string)$_GET['err']) {
        'history_required' => 'Task and completion date are required.',
        'history_bad_date' => 'Invalid completion date.',
        'history_failed'   => 'Failed to save history entry.',
        'unauthorized'     => 'Unauthorized action.',
        'bad_request'      => 'Bad request.',
        default            => 'An error occurred.',
    };
}

/* -----------------------------
   Fetch: History rows for item
------------------------------ */
$stmtHistory = $pdo->prepare("
    SELECT
        th.id AS history_id,
        th.note,
        th.cost,
        th.completed_on,
        th.created_at,
        mt.task_name
    FROM task_history th
    JOIN maintenance_tasks mt ON mt.id = th.task_id
    WHERE mt.item_id = :item_id
    ORDER BY
        th.completed_on DESC,
        th.id DESC
");
$stmtHistory->execute([':item_id' => $itemId]);
$historyRows = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------
   Fetch photos for history rows
------------------------------ */
$photosByHistory = [];
if (!empty($historyRows)) {
    // Batch fetch photos to avoid N+1 queries when rendering the table.
    $ids = array_map(fn($r) => (int)$r['history_id'], $historyRows);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $stmtPhotos = $pdo->prepare("
        SELECT history_id, file_path
        FROM photos
        WHERE history_id IN ($in)
        ORDER BY uploaded_at DESC
    ");
    $stmtPhotos->execute($ids);

    while ($p = $stmtPhotos->fetch(PDO::FETCH_ASSOC)) {
        $hid = (int)$p['history_id'];
        $photosByHistory[$hid] ??= [];
        $photosByHistory[$hid][] = $p['file_path'];
    }
}

/* -----------------------------
   Owner-only: tasks for add form
------------------------------ */
$tasks = [];
if ($userRoleOnHome === 'owner') {
    $stmtTasks = $pdo->prepare("
        SELECT id, task_name
        FROM maintenance_tasks
        WHERE item_id = :item_id
        ORDER BY task_name
    ");
    $stmtTasks->execute([':item_id' => $itemId]);
    $tasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include __DIR__ . "/../_item_header.php"; ?>

<section class="item-tab">

    <?php if ($flashSuccess): ?>
        <div class="popup show" style="background:#4CAF50;"><?= h($flashSuccess) ?></div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="popup show" style="background:#e74c3c;"><?= h($flashError) ?></div>
    <?php endif; ?>

    <h3>Material History</h3>
    <p class="muted">
        Completed maintenance records for this item (notes, cost, and supporting photos).
    </p>

    <?php if (empty($historyRows)): ?>
        <p class="muted">No history recorded yet.</p>
    <?php else: ?>
        <table class="detail-table">
            <tr>
                <th style="width: 120px;">Completed</th>
                <th>Task</th>
                <th style="width: 110px;">Cost</th>
                <th>Notes</th>
                <th style="width: 220px;">Photos</th>
            </tr>

            <?php foreach ($historyRows as $r): ?>
                <?php
                    $hid = (int)$r['history_id'];

                    // Prefer the explicit completion date; fall back to created_at for legacy rows.
                    if (!empty($r['completed_on'])) {
                        $dateStr = (string)$r['completed_on'];
                    } elseif (!empty($r['created_at'])) {
                        $dateStr = date("Y-m-d", strtotime((string)$r['created_at']));
                    } else {
                        $dateStr = '—';
                    }
                ?>
                <tr>
                    <td><?= h($dateStr) ?></td>
                    <td><?= h($r['task_name']) ?></td>
                    <td>$<?= h(number_format((float)$r['cost'], 2)) ?></td>
                    <td><?= h($r['note'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($photosByHistory[$hid])): ?>
                            <?php foreach ($photosByHistory[$hid] as $path): ?>
                                <div class="muted"><?= h($path) ?></div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($userRoleOnHome === 'owner'): ?>
        <hr style="margin: 28px 0; border: none; border-top: 1px solid #e6e9ef;">

        <h3>Add History Entry</h3>
        <p class="muted">
            Log a completed task. This will also advance the task’s next due date based on the completion date.
        </p>

        <form method="POST" action="actions/add_history.php">
            <input type="hidden" name="add_history" value="1">
            <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
            <input type="hidden" name="return_to"
                   value="<?= APP_URL ?>/items/index.php?item_id=<?= (int)$itemId ?>&tab=history">

            <table class="detail-table">
                <tr>
                    <th style="width: 25%;">Field</th>
                    <th>Value</th>
                </tr>

                <tr>
                    <td>Task *</td>
                    <td>
                        <select name="task_id" required>
                            <option value="">-- Select Task --</option>
                            <?php foreach ($tasks as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= h($t['task_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <td>Completed Date *</td>
                    <td><input type="date" name="done_date" value="<?= h(date('Y-m-d')) ?>" required></td>
                </tr>

                <tr>
                    <td>Cost</td>
                    <td><input type="number" name="cost" step="0.01" min="0"></td>
                </tr>

                <tr>
                    <td>Notes</td>
                    <td><textarea name="note" rows="3"></textarea></td>
                </tr>

                <tr>
                    <td>Photo Path (for now)</td>
                    <td>
                        <input type="text" name="photo_path" placeholder="uploads/receipt_123.jpg">
                    </td>
                </tr>
            </table>

            <div class="detail-actions">
                <input type="submit" value="Save History Entry">
            </div>
        </form>
    <?php endif; ?>

</section>
