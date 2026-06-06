(function () {
  "use strict";

  const BASE_URL = (window.MORO && window.MORO.baseUrl) ? window.MORO.baseUrl : "";
  const API_URL = BASE_URL + "/public/tickler/tickler_api.php";
  const offset = Number((window.MORO && window.MORO.clockOffsetMs) ? window.MORO.clockOffsetMs : 0);


  function pad2(n) { return String(n).padStart(2, "0"); }
  function ymd(y, m0, d) { return `${y}-${pad2(m0 + 1)}-${pad2(d)}`; }

  function initTickler() {
    if (!document.getElementById("cal-body")) return;

    const esc = window.escapeHtml || function (s) { return String(s ?? ""); };

    const now = new Date(Date.now() + offset);
    let viewYear = now.getFullYear();
    let viewMonth = now.getMonth();

    let monthTasksByDate = {};

    function renderCalendar(year, month0) {
      const monthNames = [
        "January","February","March","April","May","June",
        "July","August","September","October","November","December"
      ];
      $("#cal-month-label").text(`${monthNames[month0]} ${year}`);

      const first = new Date(year, month0, 1);
      const startDow = first.getDay();
      const daysInMonth = new Date(year, month0 + 1, 0).getDate();

      let html = "";
      let day = 1 - startDow;

      for (let wk = 0; wk < 6; wk++) {
        html += "<tr>";
        for (let col = 0; col < 7; col++, day++) {
          if (day < 1 || day > daysInMonth) {
            html += `<td class="cal-empty"></td>`;
          } else {
            const dateStr = ymd(year, month0, day);
            const hasTasks = !!monthTasksByDate[dateStr]?.length;
            const badgeCount = hasTasks ? monthTasksByDate[dateStr].length : 0;

            html += `
              <td class="cal-day" data-date="${dateStr}">
                <div class="cal-day-inner">
                  <div class="cal-num">${day}</div>
                  ${hasTasks ? `<div class="cal-badge">${badgeCount}</div>` : ``}
                </div>
              </td>
            `;
          }
        }
        html += "</tr>";
      }

      $("#cal-body").html(html);
    }

    function fetchMonthTasks(year, month0) {
      // Always render the grid shell even if API fails
      monthTasksByDate = {};
      renderCalendar(year, month0);

      const month1 = month0 + 1;

      return $.getJSON(API_URL, { action: "month", year: year, month: month1 })
        .done(function (data) {
          monthTasksByDate = data.byDate || {};
          renderCalendar(year, month0); // re-render with badges
          // default selection logic...
        })
        .fail(function (xhr) {
          console.error("Tickler API failed:", xhr.status, xhr.responseText);
          $("#day-tasks").html(`<p class="muted">Failed to load tasks for this month.</p>`);
        });
    }

    function setCountdownTarget(dateStr) {
      window.__ticklerTargetMs = new Date(dateStr + "T00:00:00").getTime();
    }

    function applyUrgency(diffMs) {
      const $c = $("#countdown-container");
      $c.removeClass("green yellow red pulse");

      if (diffMs < 0) { $c.addClass("pulse"); return; }

      const oneDay = 24 * 60 * 60 * 1000;
      const sevenDays = 7 * oneDay;

      if (diffMs > sevenDays) $c.addClass("green");
      else if (diffMs > oneDay) $c.addClass("yellow");
      else $c.addClass("red");
    }

    function updateCountdownTick() {
      if (!window.__ticklerTargetMs) return;

      const nowMs = Date.now() + offset;
      const signedDiff = window.__ticklerTargetMs - nowMs;

      let diff = Math.abs(signedDiff);

      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const minutes = Math.floor((diff / (1000 * 60)) % 60);
      const seconds = Math.floor((diff / 1000) % 60);

      $("#cd-days").text(days);
      $("#cd-hours").text(String(hours).padStart(2, "0"));
      $("#cd-minutes").text(String(minutes).padStart(2, "0"));
      $("#cd-seconds").text(String(seconds).padStart(2, "0"));

      applyUrgency(signedDiff);
    }

    function renderDayTasks(dateStr, tasks) {
      $("#selected-date-label").text(dateStr);

      if (!tasks || tasks.length === 0) {
        $("#day-tasks").html(`<p class="muted">No tasks scheduled for this day.</p>`);
        $("#task-title").text("Select a task");
        $("#task-desc").text("Choose a day with tasks to see details.");
        $("#day-tasks").removeData("tasks");
        return;
      }

      let html = `<ul class="task-list">`;
      tasks.forEach((t, idx) => {
        html += `
          <li class="task-item" data-idx="${idx}">
            <div class="task-name">${esc(t.task_name)}</div>
            <div class="task-meta muted">${esc(t.item_name || "")}</div>
          </li>
        `;
      });
      html += `</ul>`;

      $("#day-tasks").html(html).data("tasks", tasks);
      $(".task-item").first().trigger("click");
    }

    function selectDay(dateStr) {
      $(".cal-day").removeClass("selected");
      $(`.cal-day[data-date="${dateStr}"]`).addClass("selected");

      setCountdownTarget(dateStr);

      $.getJSON(API_URL, { action: "day", date: dateStr })
        .done(function (data) {
          renderDayTasks(dateStr, data.tasks || []);
        })
        .fail(function (xhr) {
          console.error(xhr.responseText);
          $("#day-tasks").html(`<p class="muted">Failed to load tasks for this day.</p>`);
        });

      updateCountdownTick();
    }

    $(document).on("click", ".cal-day", function () {
      const dateStr = $(this).data("date");
      if (dateStr) selectDay(dateStr);
    });

    $(document).on("click", ".task-item", function () {
      $(".task-item").removeClass("active");
      $(this).addClass("active");

      const tasks = $("#day-tasks").data("tasks") || [];
      const idx = Number($(this).data("idx"));
      const t = tasks[idx];

      if (t) {
        $("#task-title").text(t.task_name || "Task");
        $("#task-desc").text(t.description || "");
      }
    });

    $("#cal-prev").on("click", function () {
      viewMonth--;
      if (viewMonth < 0) { viewMonth = 11; viewYear--; }
      fetchMonthTasks(viewYear, viewMonth);
    });

    $("#cal-next").on("click", function () {
      viewMonth++;
      if (viewMonth > 11) { viewMonth = 0; viewYear++; }
      fetchMonthTasks(viewYear, viewMonth);
    });

    setInterval(updateCountdownTick, 1000);
    fetchMonthTasks(viewYear, viewMonth);
  }

  $(initTickler);
})();
