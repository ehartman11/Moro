/**
 * Items feature JS (task creation/edit helpers).
 * Safe to include on Items pages only.
 */

(function () {
  "use strict";

  // requires window.MORO_BASE_URL set by the server
  function baseUrl() {
    return window.MORO_BASE_URL || "";
  }

  window.add_task_units = function () {
    const scheduleTypeEl = document.getElementById("schedule_type");
    const unitEl = document.getElementById("frequency_unit");
    const valueEl = document.getElementById("frequency_value");

    // If we are not on the expected page, do nothing.
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
        baseUrl() +
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
  };
})();
