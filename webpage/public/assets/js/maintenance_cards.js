(function () {
  "use strict";

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }
  function qsa(sel, root) {
    return Array.from((root || document).querySelectorAll(sel));
  }

  function escapeHtml(s) {
    return String(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function getDirectChildrenContainer(nodeEl) {
    // Most reliable: the first .tree-children that is a direct child
    for (const child of Array.from(nodeEl.children)) {
      if (child.classList && child.classList.contains("tree-children")) return child;
    }
    return null;
  }

  function setToggleState(toggleBtn, expanded) {
    toggleBtn.setAttribute("aria-expanded", expanded ? "true" : "false");
    toggleBtn.textContent = expanded ? "▾" : "▸";
  }

  function toggleNode(toggleBtn, forceExpanded /* boolean | undefined */) {
    const parent = toggleBtn.closest(".tree-item, .tree-part");
    if (!parent) return;

    const children = getDirectChildrenContainer(parent);
    if (!children) return;

    const currentlyExpanded = toggleBtn.getAttribute("aria-expanded") === "true";
    const nextExpanded =
      typeof forceExpanded === "boolean" ? forceExpanded : !currentlyExpanded;

    setToggleState(toggleBtn, nextExpanded);
    children.hidden = !nextExpanded;
  }

  function collapseAll(root) {
    qsa(".tree-item, .tree-part", root).forEach((node) => {
      const toggle = qs(".tree-toggle", node);
      const children = getDirectChildrenContainer(node);
      if (!toggle || !children) return;
      setToggleState(toggle, false);
      children.hidden = true;
    });
  }

  function expandAncestors(el) {
    // If a task is clicked or revealed by search, expand its part + item
    const part = el.closest(".tree-part");
    if (part) {
      const toggle = qs(".tree-toggle", part);
      if (toggle) toggleNode(toggle, true);
    }

    const item = el.closest(".tree-item");
    if (item) {
      const toggle = qs(".tree-toggle", item);
      if (toggle) toggleNode(toggle, true);
    }
  }

  function setActiveTask(btn) {
    qsa(".tree-task.is-active").forEach((x) => x.classList.remove("is-active"));
    btn.classList.add("is-active");
  }

  function renderUploadControl(card) {
    // Requires card.item_id, card.task_id, card.part_name (can be null/empty)
    const uploadAction = `${window.MORO_BASE_URL}/public/actions.php?action=maintenance.upload`;
    const csrf = window.MORO_CSRF ? String(window.MORO_CSRF) : "";

    const partVal = card.part_name ? String(card.part_name) : "";

    return `
      <form class="mrc-upload" method="post" action="${escapeHtml(uploadAction)}" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="${escapeHtml(csrf)}">
        <input type="hidden" name="home_id" value="${escapeHtml(String(card.home_id || ""))}">
        <input type="hidden" name="item_id" value="${escapeHtml(String(card.item_id || ""))}">
        <input type="hidden" name="task_id" value="${escapeHtml(String(card.task_id || ""))}">
        <input type="hidden" name="part_name" value="${escapeHtml(partVal)}">

        <input class="mrc-upload-file" type="file" name="pdf" accept="application/pdf">
        <button type="submit" class="btn secondary mrc-upload-btn">Upload PDF</button>
      </form>
    `;
  }

  function moroUrl(path) {
    return window.location.origin + window.MORO_BASE_URL + path;
  }

  function renderCard(card) {
    const due = card.next_due ? String(card.next_due) : "—";
    const desc = card.description ? String(card.description) : "No description.";
    const part = card.part_name ? String(card.part_name) : "General";

    const freqVal =
      card.frequency_value === null || card.frequency_value === undefined
        ? ""
        : String(card.frequency_value);
    
    const pdfHtml = card.mrc_doc_key
    ? (() => {
        const pdfUrl = `${window.MORO_BASE_URL}/storage/MRCs/${encodeURIComponent(card.mrc_doc_key)}`;
        const viewUrl =
          moroUrl(`/public/actions.php?action=maintenance.content&id=${encodeURIComponent(card.mrc_content_id)}`);
        const downloadUrl = viewUrl + "&download=1";
        console.log(card.mrc_content_id);
        const feedbackUrl = `${window.MORO_BASE_URL}/public/maintenance/feedback_form.php?mrc_content_id=${encodeURIComponent(card.mrc_content_id)}`;

        return `
          <div class="mrc-block">
            <div class="mrc-header">
              <div class="label">MRC</div>
              <div class="mrc-buttons">
                <a class="btn secondary" target="_blank" rel="noopener" href="${pdfUrl}">Open PDF</a>
                <a class="btn" href="${feedbackUrl}">Submit feedback</a>
              </div>
            </div>
            
            <iframe
              class="mrc-frame"
              src="${viewUrl}"
              title="MRC PDF"
              loading="eager"
              referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
          </div>
        `;
      })()
    : `
      <div class="mrc-block">
        <div class="label">MRC</div>
        <p class="muted">No published MRC is attached to this task yet.</p>
      </div>
    `;


    return `
      <div class="maint-card-inner">
        <div class="maint-card-header">
          <h2>${escapeHtml(card.task_name || "Task")}</h2>
          <div class="muted">Part: ${escapeHtml(part)}</div>  
          <div>
            ${renderUploadControl(card)}
          </div>
        </div>

        <div class="maint-card-grid">
          <div><div class="label">Priority</div><div>${escapeHtml(card.priority || "—")}</div></div>
          <div><div class="label">Schedule Type</div><div>${escapeHtml(card.schedule_type || "—")}</div></div>
          <div><div class="label">Frequency</div><div>${escapeHtml(freqVal)} ${escapeHtml(card.frequency_unit || "")}</div></div>
          <div><div class="label">Next Due</div><div>${escapeHtml(due)}</div></div>
        </div>

        <div class="maint-card-desc">
          <div class="label">Description</div>
          <div>${escapeHtml(desc)}</div>
        </div>

        <div class="maint-card-actions">
          <a class="btn" href="${window.MORO_BASE_URL}/public/items/task.php?id=${encodeURIComponent(
            card.task_id
          )}">Open full task</a>
        </div>
        ${pdfHtml}
      </div>
    `;
  }

  async function fetchCard(baseUrl, taskId) {
    const url =
      baseUrl +
      "/public/actions.php?action=maintenance.get_task_card&task_id=" +
      encodeURIComponent(taskId);

    const res = await fetch(url, { credentials: "same-origin" });
    return await res.json();
  }

  function initSearch(baseUrl) {
    const input = qs("#maintSearch");
    const root = qs("#maintTreeRoot");
    if (!input || !root) return;

    input.addEventListener("input", () => {
      const q = input.value.trim().toLowerCase();

      if (q === "") {
        // restore visibility
        qsa(".tree-task", root).forEach((btn) => (btn.style.display = ""));
        return;
      }

      qsa(".tree-task", root).forEach((btn) => {
        const name = (btn.dataset.taskName || btn.textContent || "").toLowerCase();
        const match = name.includes(q);
        btn.style.display = match ? "" : "none";
        if (match) expandAncestors(btn);
      });
    });
  }

  function buildUrl(base, path, params) {
    const u = new URL(path, base);
    Object.entries(params || {}).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== "") u.searchParams.set(k, String(v));
    });
    return u.toString();
  }

  function setActions(baseUrl, data) {
    const actionsEl = document.getElementById("cardActions");
    if (!actionsEl) return;

/*
    const taskId = data?.task?.id;
    const publishedId = data?.mrc?.published?.content_id || null;
    const draftId = data?.mrc?.draft?.content_id || null;

    // Always show dashboard (if you want it owner-only, enforce in PHP too)
    const dashA = document.createElement("a");
    dashA.className = "btn";
    dashA.href = buildUrl(baseUrl, "/public/maintenance/feedback_dashboard.php", {
      task_id: taskId,
      return_to: window.location.href
    });
    dashA.textContent = "Feedback Dashboard";
    actionsEl.appendChild(dashA);

    // Feedback form only makes sense for published MRC
    if (publishedId) {
      const fbA = document.createElement("a");
      fbA.className = "btn btn-primary";
      fbA.href = buildUrl(baseUrl, "/public/maintenance/feedback_form.php", {
        task_id: taskId,
        mrc_content_id: publishedId,
        return_to: window.location.href
      });
      fbA.textContent = "Leave Feedback";
      actionsEl.appendChild(fbA);
    }

    // Publish only if a draft exists (enforce owner in publish.php)
    if (draftId) {
      const pubA = document.createElement("a");
      pubA.className = "btn btn-danger";
      pubA.href = buildUrl(baseUrl, "/public/maintenance/publish.php", {
        task_id: taskId,
        mrc_content_id: draftId,
        return_to: window.location.href
      });
      pubA.textContent = "Publish Draft";
      actionsEl.appendChild(pubA);
    }

    actionsEl.hidden = actionsEl.children.length === 0;
    */
  }


  function ensureCollapsedDefaults() {
    const root = qs("#maintTreeRoot");
    if (!root) return;
    // If markup already has hidden attributes, this is harmless.
    collapseAll(root);
  }

  window.MoroMaintenanceCards = {
    init: function (baseUrl) {
      ensureCollapsedDefaults();

      // Single delegated click handler (avoids double-handling)
      document.addEventListener("click", async (e) => {
        // Toggle caret
        const toggle = e.target.closest(".tree-toggle");
        if (toggle) {
          toggleNode(toggle);
          return;
        }

        // Clicking labels toggles too
        const itemLabel = e.target.closest(".tree-item-label");
        if (itemLabel) {
          const t = qs(".tree-toggle", itemLabel.closest(".tree-item"));
          if (t) toggleNode(t);
          return;
        }

        const partLabel = e.target.closest(".tree-part-label");
        if (partLabel) {
          const t = qs(".tree-toggle", partLabel.closest(".tree-part"));
          if (t) toggleNode(t);
          return;
        }

        // Clicking a task loads the card
        const taskBtn = e.target.closest(".tree-task");
        if (!taskBtn) return;

        const taskId = taskBtn.dataset.taskId;
        if (!taskId) return;

        setActiveTask(taskBtn);
        expandAncestors(taskBtn);

        const shell = qs("#cardShell");
        if (shell) shell.innerHTML = `<p class="muted">Loading…</p>`;

        try {
          const data = await fetchCard(baseUrl, taskId);
          if (!data.ok) {
            if (shell) shell.innerHTML = `<p class="muted">Could not fetch task data.</p>`;
            return;
          }
          if (shell) shell.innerHTML = renderCard(data.card);
          setActions(window.MORO_BASE_URL, data);
        } catch {
          if (shell) shell.innerHTML = `<p class="muted">Could not load task.</p>`;
        }
      });

      initSearch(baseUrl);
    },
  };
})();
