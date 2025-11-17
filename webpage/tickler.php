<?php 
// If the user has submitted a date/time, process it
$hasSubmitted = false;
$dueTime = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST['dueDate'] ?? null;
    $time = $_POST['dueTime'] ?? null;

    if (!$time) {
        $time = "00:00:00";
    }

    if ($date) {
        $combined = "$date $time";
        $dueTime = strtotime($combined);
        $hasSubmitted = true;
    }
}

$currentTime = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Countdown</title>
    <link rel="stylesheet" href="main.css">
    <script src="code.js"></script>
</head>

<body>

<?php include 'nav_bar.php'; ?>

<section>
    <h2 style="text-align:center; margin-bottom:30px;">Select a Due Date</h2>

    <form method="post" action="tickler.php" class="dateForm" style="max-width:400px; margin:auto;">
        <label>Choose Date</label>
        <input type="date" name="dueDate" required>

        <label>Choose Time</label>
        <input type="time" name="dueTime">

        <input type="submit" value="Start Countdown">
    </form>
</section>

<?php if ($hasSubmitted): ?>
<section style="margin-top:40px;">
    <h2 style="text-align:center;">Countdown to Due Date: <?= $date . " " . $time?> </h2>

    <div class="countdown-container">
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

    <script>
        // Convert php to JS
        let dueTime = <?= $dueTime ?> * 1000;
        let serverNow = <?= $currentTime ?> * 1000;
        let offset = serverNow - Date.now();

        updateCountdown(dueTime, offset);
        setInterval(() => updateCountdown(dueTime, offset), 1000);
    </script>
</section>
<?php endif; ?>

</body>
</html>
