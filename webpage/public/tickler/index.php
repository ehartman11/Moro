<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Paths;

Auth::requireLogin();
$activeHomeId = Auth::requireActiveHome();

$serverNow = time();

// Keep your existing nav include for now
require_once Paths::root() . '/nav_bar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Calendar</title>

    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/nav.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= Paths::baseUrl() ?>/public/styling/tickler.css">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
    window.MORO = {
        baseUrl: "<?= Paths::baseUrl() ?>",
        clockOffsetMs: <?= (int)$serverNow ?> * 1000 - Date.now(),
        activeHomeId: <?= (int)$activeHomeId ?>
    };
    </script>

    <script src="<?= Paths::baseUrl() ?>/public/assets/js/app.js"></script>
    <script src="<?= Paths::baseUrl() ?>/public/assets/js/tickler.js"></script>

</head>
<body>

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
</body>
</html>
