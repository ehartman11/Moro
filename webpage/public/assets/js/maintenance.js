(function () {
  "use strict";

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function on(root, event, sel, fn) {
    root.addEventListener(event, (e) => {
      const el = e.target.closest(sel);
      if (!el) return;
      fn(e, el);
    });
  }

  function openTaskModal(baseUrl, id, name, desc) {
    qs("#taskModalTitle").textContent = name || "Task";
    qs("#taskModalDesc").textContent = desc || "No description provided.";
    qs("#taskModalViewBtn").setAttribute("href", baseUrl + "/public/items/task.php?id=" + id);
    qs("#taskModal").classList.add("show");
    qs("#taskModal").setAttribute("aria-hidden", "false");
  }

  function closeTaskModal() {
    qs("#taskModal").classList.remove("show");
    qs("#taskModal").setAttribute("aria-hidden", "true");
  }

  function initAddTaskUnits(baseUrl) {
    const scheduleTypeEl = document.getElementById("schedule_type");
    const unitEl = document.getElementById("frequency_unit");
    const valueEl = document.getElementById("frequency_value");

    // Not on a page with the Add Task form
    if (!scheduleTypeEl || !unitEl || !valueEl) return;

    function resetUnits(lock = true, placeholder = "Select schedule type first…") {
      unitEl.innerHTML = "";
      const opt = document.createElement("option");
      opt.value = "";
      opt.textContent = placeholder;
      opt.selected = true;
      unitEl.appendChild(opt);
      unitEl.disabled = lock;
    }

    function setValueEnabled(enabled) {
      valueEl.disabled = !enabled;
      valueEl.required = enabled;
      if (!enabled) valueEl.value = "";
    }

    async function loadUnitsForType(scheduleType) {
      resetUnits(true, "Loading…");

      const url =
        baseUrl +
        "/public/actions.php?action=items.get_unit_options&schedule_type=" +
        encodeURIComponent(scheduleType);

      const res = await fetch(url, { credentials: "same-origin" });
      const data = await res.json();

      if (!data.ok) {
        resetUnits(true, "No units available");
        setValueEnabled(false);
        return;
      }

      unitEl.innerHTML = "";
      const placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.textContent = "Select unit…";
      placeholder.selected = true;
      unitEl.appendChild(placeholder);

      data.units.forEach((u) => {
        const opt = document.createElement("option");
        opt.value = u.unit;
        opt.textContent = u.unit;
        opt.dataset.requiresValue = String(u.requires_value);
        unitEl.appendChild(opt);
      });

      unitEl.disabled = false;
    }

    scheduleTypeEl.addEventListener("change", async () => {
      resetUnits(true, "Select unit…");
      setValueEnabled(false);

      const st = scheduleTypeEl.value;

      if (st === "seasonal") {
        resetUnits(true, "Seasonal (no unit)");
        setValueEnabled(false);
        return;
      }

      if (st === "per_use") {
        await loadUnitsForType(st);
        setValueEnabled(false);
        return;
      }

      await loadUnitsForType(st);
      setValueEnabled(true);
    });

    unitEl.addEventListener("change", () => {
      const selected = unitEl.options[unitEl.selectedIndex];
      if (!selected) return;

      const requiresValue = selected.dataset.requiresValue === "1";
      setValueEnabled(requiresValue);
    });

    resetUnits(true);
    setValueEnabled(false);
  }

  window.MoroMaintenanceTab = {
    init: function (baseUrl) {
      // modal
      on(document, "click", ".task-link", function (e, el) {
        e.preventDefault();
        openTaskModal(baseUrl, el.dataset.taskId, el.dataset.taskName, el.dataset.taskDesc);
      });

      on(document, "click", "#taskModalClose, #taskModalOk, #taskModal .modal-backdrop", function () {
        closeTaskModal();
      });

      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") closeTaskModal();
      });

      // add-task frequency unit behavior
      initAddTaskUnits(baseUrl);
    }
  };
})();
