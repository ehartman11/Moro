<?php
use Moro\Core\Paths;
use Moro\Core\Auth;

/**
 * History tab (render-only).
 *
 * Parent provides:
 * - $selectedItem, $role
 * - $historyRows, $photosByHistory, $tasks
 * - $historyFlashSuccess, $historyFlashError
 * - Paths::baseUrl(), h()
 */

// Guard: must have selected item
if (!$selectedItem || empty($selectedItem['id'])): ?>
    <div class="empty-state">
        <h2>Select an item</h2>
        <p>Pick an item on the left to view its material history.</p>
    </div>
    <?php return; ?>
<?php endif;

$baseUrl = Paths::baseUrl();
$itemId  = (int)$selectedItem['id'];
$csrf = Auth::csrfToken();

// Provided by index.php
$flashSuccess = $historyFlashSuccess ?? '';
$flashError   = $historyFlashError ?? '';

// Safety defaults if index.php didn’t populate for some reason
$historyRows     = $historyRows ?? [];
$photosByHistory = $photosByHistory ?? [];
$tasks           = $tasks ?? [];
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
                <th style="width: 140px;">Completed</th>
                <th>Task</th>
            </tr>

            <?php foreach ($historyRows as $r): ?>
                <?php
                $hid = (int)$r['history_id'];
                $dateStr = !empty($r['completed_on'])
                    ? (string)$r['completed_on']
                    : (!empty($r['created_at']) ? date('Y-m-d', strtotime((string)$r['created_at'])) : '—');
                ?>
                <tr class="history-row" data-history-id="<?= $hid ?>">
                <td><?= h($dateStr) ?></td>
                <td><?= h($r['task_name']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($role === 'owner'): ?>
        <hr style="margin: 28px 0; border: none; border-top: 1px solid #e6e9ef;">

        <h3>Add History Entry</h3>
        <p class="muted">
            Log a completed task. This will also advance the task’s next due date based on the completion date.
        </p>

        <form method="POST" action="<?= $baseUrl ?>/public/actions.php?action=items.add_history" enctype="multipart/form-data">
            <input type="hidden" name="add_history" value="1">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
            <input type="hidden" name="return_to"
                   value="<?= $baseUrl ?>/public/items/index.php?item_id=<?= (int)$itemId ?>&tab=history">

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
                    <td>Photo (optional)</td>
                    <td><input type="file" name="photo" accept="image/*"></td>
                </tr>
            </table>

            <div class="detail-actions">
                <input type="submit" value="Save History Entry">
            </div>
        </form>
    <?php endif; ?>

    <div id="historyModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); padding:40px; z-index:999;">
        <div style="background:#fff; border-radius:12px; max-width:960px; margin:0 auto; padding:14px; position:relative;">
            <button id="historyClose" style="position:absolute; right:10px; top:10px;">✕</button>

            <h3 style="margin:0 0 10px 0;">History Entry</h3>

            <div id="historyLoadMsg" class="muted" style="display:none; margin-bottom:10px;">Loading…</div>
            <div id="historyErr" style="display:none; background:#fee; border:1px solid #f99; padding:10px; border-radius:8px; margin-bottom:10px;"></div>

            <form id="historyForm" method="post" action="<?= h($baseUrl) ?>/public/actions.php?action=items.history_update">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
                <input type="hidden" name="history_id" id="hm_history_id" value="">

                <table class="detail-table">
                    <tr><th style="width: 180px;">Field</th><th>Value</th></tr>

                    <tr>
                    <td>Task</td>
                    <td>
                        <select name="task_id" id="hm_task_id">
                        <?php foreach ($tasks as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= h($t['task_name']) ?></option>
                        <?php endforeach; ?>
                        </select>
                    </td>
                    </tr>

                    <tr>
                    <td>Completed Date</td>
                    <td><input type="date" name="done_date" id="hm_done_date"></td>
                    </tr>

                    <tr>
                    <td>Cost</td>
                    <td><input type="number" step="0.01" min="0" name="cost" id="hm_cost"></td>
                    </tr>

                    <tr>
                    <td>Notes</td>
                    <td><textarea name="note" rows="4" id="hm_note"></textarea></td>
                    </tr>

                    <tr>
                    <td>Photos</td>
                    <td id="hm_photos"><span class="muted">—</span></td>
                    </tr>
                </table>

                <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:12px;">
                    <button type="submit" class="btn primary" id="hm_save">Save</button>
                </div>
            </form>

            <form id="historyDeleteForm" method="post" action="<?= h($baseUrl) ?>/public/actions.php?action=items.history_delete" style="margin-top:8px;">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="item_id" value="<?= (int)$itemId ?>">
                <input type="hidden" name="history_id" id="hm_delete_history_id" value="">
                <button class="btn danger" type="submit"
                        id="hm_delete"
                        onclick="return confirm('Delete this history entry? This will also delete associated photos.');">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <script>
        (() => {
        const baseUrl = <?= json_encode($baseUrl) ?>;
        const itemId  = <?= json_encode((int)$itemId) ?>;
        const isOwner = <?= json_encode($role === 'owner') ?>;

        const modal = document.getElementById('historyModal');
        const closeBtn = document.getElementById('historyClose');
        const msg = document.getElementById('historyLoadMsg');
        const err = document.getElementById('historyErr');

        const hidInput = document.getElementById('hm_history_id');
        const hidDelInput = document.getElementById('hm_delete_history_id');

        const taskSel = document.getElementById('hm_task_id');
        const doneDate = document.getElementById('hm_done_date');
        const cost = document.getElementById('hm_cost');
        const note = document.getElementById('hm_note');

        const saveBtn = document.getElementById('hm_save');
        const delBtn  = document.getElementById('hm_delete');

        const photosCell = document.getElementById('hm_photos');

        function openModal() {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
        function setLoading(on) {
            msg.style.display = on ? 'block' : 'none';
        }
        function setError(text) {
            if (!text) { err.style.display = 'none'; err.textContent = ''; return; }
            err.style.display = 'block';
            err.textContent = text;
        }
        function setEditable(canEdit) {
            taskSel.disabled = !canEdit;
            doneDate.disabled = !canEdit;
            cost.disabled = !canEdit;
            note.disabled = !canEdit;
            saveBtn.style.display = canEdit ? 'inline-block' : 'none';
            delBtn.style.display  = canEdit ? 'inline-block' : 'none';
        }

        function renderPhotos(photos) {
            if (!photos || photos.length === 0) {
            photosCell.innerHTML = '<span class="muted">—</span>';
            return;
            }
            photosCell.innerHTML = photos.map((p, i) => {
            const label = p.label || `Photo ${i+1}`;
            const url = p.url;
            return `<div><a class="muted" href="#" data-photo-url="${url.replaceAll('"','&quot;')}">${label}</a></div>`;
            }).join('');
        }

        async function fetchHistory(historyId) {
            const url = `${baseUrl}/public/actions.php?action=items.history_read&item_id=${encodeURIComponent(itemId)}&history_id=${encodeURIComponent(historyId)}`;
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) throw new Error('Failed to load history entry.');
            return await res.json();
        }

        // Click row to open modal
        document.addEventListener('click', async (e) => {
            const row = e.target.closest('.history-row');
            if (!row) return;

            const historyId = row.getAttribute('data-history-id');
            if (!historyId) return;

            setError('');
            setLoading(true);
            renderPhotos([]);
            openModal();

            hidInput.value = historyId;
            hidDelInput.value = historyId;

            setEditable(false);

            try {
            const data = await fetchHistory(historyId);

            taskSel.value = String(data.task_id || '');
            doneDate.value = data.done_date || '';
            cost.value = (data.cost != null) ? String(data.cost) : '';
            note.value = data.note || '';

            renderPhotos(data.photos || []);
            setEditable(isOwner && data.can_edit !== false);
            } catch (errObj) {
            setError(errObj.message || 'Error loading entry.');
            } finally {
            setLoading(false);
            }
        });

        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.style.display === 'block') closeModal(); });
        })();
    </script>

    <div id="photoModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); padding:40px; z-index:2000;">
        <div style="background:#fff; border-radius:10px; max-width:960px; margin:0 auto; padding:12px; position:relative;">
            <button id="photoClose" style="position:absolute; right:10px; top:10px;">✕</button>
            <img id="photoImg" alt="Photo" style="max-width:100%; height:auto; display:block; margin:0 auto;">
        </div>
    </div>

    <script>
    const modal = document.getElementById('photoModal');
    const img = document.getElementById('photoImg');
    document.addEventListener('click', (e) => {
        const a = e.target.closest('[data-photo-url]');
        if (!a) return;
        e.preventDefault();
        img.src = a.getAttribute('data-photo-url');
        modal.style.display = 'block';
    });
    document.getElementById('photoClose').addEventListener('click', () => {
        img.src = '';
        modal.style.display = 'none';
    });
    </script>
</section>
