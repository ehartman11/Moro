<?php
/**
 * Tickler calendar UI page.
 *
 * Responsibilities:
 * - Enforces authenticated session + an "active home" context before rendering.
 * - Renders the calendar + day task list + countdown UI shell.
 * - Bootstraps the client with a server-synced clock offset (used for consistent countdown timing).
 * - Uses tickler_api.php for month/day task retrieval; UI is rendered client-side via jQuery.
 */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION["active_home_id"])) {
    header("Location: homes.php");
    exit;
}

$activeHomeId = (int)$_SESSION["active_home_id"];
$serverNow = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Calendar</title>

    <link rel="stylesheet" href="styling/base.css">
    <link rel="stylesheet" href="styling/nav.css">
    <link rel="stylesheet" href="styling/popup.css">
    <link rel="stylesheet" href="styling/tickler.css">

    <!-- jQuery (CDN) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="code.js"></script>

    <script>
        // Compute a server->client clock offset once, then use it everywhere we need "now".
        // This keeps countdowns stable even if the user's local clock is off.
        const SERVER_NOW_MS = <?= (int)$serverNow ?> * 1000;
        const CLIENT_NOW_MS = Date.now();
        const CLOCK_OFFSET_MS = SERVER_NOW_MS - CLIENT_NOW_MS;

        // Active home (sent to API via session, but useful for debugging)
        const ACTIVE_HOME_ID = <?= (int)$activeHomeId ?>;
    </script>

</head>
<body>

<?php include "nav_bar.php"; ?>

<section class="tickler-layout">
    <!-- Left column: tasks list -->
    <aside class="tickler-left">
        <h2 class="tickler-title">Tasks on <span id="selected-date-label">—</span></h2>
        <div id="day-tasks" class="day-tasks">
            <p class="muted">Select a day on the calendar.</p>
        </div>
    </aside>

    <!-- Right column: countdown + selected task -->
    <main class="tickler-right">
        <div class="countdown-card">
            <h2 class="countdown-title">Countdown</h2>

            <div id="countdown-container" class="countdown-container">
                <div class="countdown-box">
                    <span id="cd-days" class="cd-number">--</span>
                    <span class="cd-label">Days</span>
                </div>
                <div class="countdown-box">
                    <span id="cd-hours" class="cd-number">--</span>
                    <span class="cd-label">Hours</span>
                </div>
                <div class="countdown-box">
                    <span id="cd-minutes" class="cd-number">--</span>
                    <span class="cd-label">Minutes</span>
                </div>
                <div class="countdown-box">
                    <span id="cd-seconds" class="cd-number">--</span>
                    <span class="cd-label">Seconds</span>
                </div>
            </div>

            <div class="selected-task">
                <h3 id="task-title">Select a task</h3>
                <p id="task-desc" class="muted">
                    Choose a day, then pick a task to see details here.
                </p>
            </div>
        </div>

        <!-- Calendar -->
        <div class="calendar-card">
            <div class="calendar-header">
                <button class="cal-nav" id="cal-prev" title="Previous month">‹</button>
                <h2 id="cal-month-label">—</h2>
                <button class="cal-nav" id="cal-next" title="Next month">›</button>
            </div>

            <table class="calendar">
                <thead>
                    <tr>
                        <th>Su</th><th>Mo</th><th>Tu</th><th>We</th><th>Th</th><th>Fr</th><th>Sa</th>
                    </tr>
                </thead>
                <tbody id="cal-body"></tbody>
            </table>
        </div>
    </main>
</section>

<script>
$(function () {
    // Initialize calendar from server-synced "now" (not the user's local clock).
    const now = new Date(Date.now() + CLOCK_OFFSET_MS);
    let viewYear = now.getFullYear();
    let viewMonth = now.getMonth(); // 0-11

    function pad2(n) { return String(n).padStart(2, "0"); }
    function ymd(y, m0, d) { return `${y}-${pad2(m0+1)}-${pad2(d)}`; }

    let monthTasksByDate = {};

    function renderCalendar(year, month0) {
        const monthNames = [
            "January","February","March","April","May","June",
            "July","August","September","October","November","December"
        ];
        $("#cal-month-label").text(`${monthNames[month0]} ${year}`);

        const first = new Date(year, month0, 1);
        const startDow = first.getDay(); // 0=Sun
        const daysInMonth = new Date(year, month0+1, 0).getDate();

        // Build a fixed 6-week grid so the table doesn't jump in height month-to-month.
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
        const month1 = month0 + 1;
        return $.getJSON("tickler_api.php", { action: "month", year: year, month: month1 })
            .done(function (data) {
                monthTasksByDate = data.byDate || {};
                renderCalendar(year, month0);

                // UX: default selection is "today" when viewing the current month, otherwise the 1st.
                const todayStr = ymd(now.getFullYear(), now.getMonth(), now.getDate());
                const defaultDate = (year === now.getFullYear() && month0 === now.getMonth())
                    ? todayStr
                    : ymd(year, month0, 1);

                selectDay(defaultDate);
            })
            .fail(function (xhr) {
                console.error(xhr.responseText);
                $("#day-tasks").html(`<p class="muted">Failed to load tasks for this month.</p>`);
            });
    }

    function setCountdownTarget(dateStr) {
        // Countdown targets midnight at the start of the selected day.
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

        const nowMs = Date.now() + CLOCK_OFFSET_MS;
        let diff = window.__ticklerTargetMs - nowMs;

        const sign = diff < 0 ? -1 : 1;
        diff = Math.abs(diff);

        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
        const minutes = Math.floor((diff / (1000 * 60)) % 60);
        const seconds = Math.floor((diff / 1000) % 60);

        $("#cd-days").text(days);
        $("#cd-hours").text(String(hours).padStart(2, "0"));
        $("#cd-minutes").text(String(minutes).padStart(2, "0"));
        $("#cd-seconds").text(String(seconds).padStart(2, "0"));

        // Use the signed delta (pre-abs) to drive urgency styling.
        const signedDiff = (window.__ticklerTargetMs - (Date.now() + CLOCK_OFFSET_MS));
        applyUrgency(signedDiff);
    }

    function renderDayTasks(dateStr, tasks) {
        $("#selected-date-label").text(dateStr);

        if (!tasks || tasks.length === 0) {
            $("#day-tasks").html(`<p class="muted">No tasks scheduled for this day.</p>`);
            $("#task-title").text("Select a task");
            $("#task-desc").text("Choose a day with tasks to see details.");
            return;
        }

        let html = `<ul class="task-list">`;
        for (const t of tasks) {
            // Store the full task payload on the element so the click handler can render details
            // without another API call.
            html += `
              <li class="task-item" data-task='${JSON.stringify(t).replace(/'/g, "&apos;")}'>
                <div class="task-name">${escapeHtml(t.task_name)}</div>
                <div class="task-meta muted">${escapeHtml(t.item_name || "")}</div>
              </li>
            `;
        }
        html += `</ul>`;
        $("#day-tasks").html(html);

        // auto-select first task
        $(".task-item").first().trigger("click");
    }

    function selectDay(dateStr) {
        $(".cal-day").removeClass("selected");
        $(`.cal-day[data-date="${dateStr}"]`).addClass("selected");

        setCountdownTarget(dateStr);

        $.getJSON("tickler_api.php", { action: "day", date: dateStr })
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
        if (!dateStr) return;
        selectDay(dateStr);
    });

    $(document).on("click", ".task-item", function () {
        $(".task-item").removeClass("active");
        $(this).addClass("active");

        // Stored as JSON string in data-task.
        // NOTE: attribute escaping is handled minimally here; if task payloads ever include quotes/newlines,
        // consider switching to jQuery's .data() with an object (or base64-encoding) to avoid edge cases.
        const raw = $(this).attr("data-task");
        let t = null;
        try { t = JSON.parse(raw.replace(/&apos;/g, "'")); } catch (e) { }

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
});
</script>

</body>
</html>
