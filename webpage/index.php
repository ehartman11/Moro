<!--
  Landing / marketing page (index).
  Responsibilities:
  - Presents the Moro value proposition + feature highlights.
  - Includes the shared nav bar.
  - Provides a lightweight “lead-in” form that forwards optional prefill fields to register.php via GET.
-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Maintenance Scheduler</title>
    <script src="code.js"> </script>

    <link rel="stylesheet" href="styling/base.css">
    <link rel="stylesheet" href="styling/nav.css">
    <link rel="stylesheet" href="styling/hero.css">
    <link rel="stylesheet" href="styling/home.css">
    <link rel="stylesheet" href="styling/forms.css">
    <link rel="stylesheet" href="styling/tables.css">
    <link rel="stylesheet" href="styling/popup.css">

</head>
<body>
    <header class="header">
        <img src="./images/Moro_logo_light.png" class="header-logo">
        <h1>Know what needs attention - and when</h1>
        <p>
            Moro helps you stay on top of home, vehicle, and equipment upkeep - without stress or clutter. </b>
            Schedule recurring maintenance, track service history, and receive reminders before things need attention. </b>
            No overwhelm. No chaos. Just a calmer, more organized way to care for the things that matter, </b> 
            so you can prevent issues before they become problems.
        </p>
    </header>

    <?php include 'nav_bar.php'; ?>

    <section class="benefit-list">
        <ul>
            <li><span class="check">✓</span> Create and organize maintenance tasks</li>
            <li><span class="check">✓</span> Track history, notes, and photos</li>
            <li><span class="check">✓</span> Automatic schedules for daily, seasonal, and annual needs</li>
            <li><span class="check">✓</span> Applies to homes, vehicles, appliances, and tools</li>
            <li><span class="check">✓</span> Friendly reminders</li>
        </ul>
    </section>

    <section class="feature-grid">
        <div class="feature-card">
            <h3>Prevent Costly Repairs</h3>
            <p>Stay ahead of maintenance before it becomes expensive.</p>
        </div>

        <div class="feature-card">
            <h3>Extend Equipment Life</h3>
            <p>Appliances and vehicles last longer with consistent care.</p>
        </div>

        <div class="feature-card">
            <h3>Reduce Stress</h3>
            <p>No more guessing or remembering. Moro stays on top of it for you.</p>
        </div>
    </section>

    <section class="category-list">
        <h2>One place for everything you care for.</h2>
        <p>Track maintenance schedules, service history, notes, and reminders for:</p>

        <ul>
            <li> Homes</li>
            <li> Vehicles</li>
            <li> Appliances</li>
            <li> Tools</li>
            <li> Outdoor equipment</li>
            <li> And more</li>
        </ul>

        <p>If it requires upkeep, Moro keeps it organized.</p>
    </section>

    <section class="comparison">
        <table>
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Smart Schedules</td>
                    <td>Automatically reminds you on recommended intervals.</td>
                </tr>
                <tr>
                    <td>Maintenance History</td>
                    <td>Store logs, photos, notes, receipts — all in one place.</td>
                </tr>
                <tr>
                    <td>Multi-Category Tracking</td>
                    <td>Organize by home, vehicle, tools, outdoor equipment, and more.</td>
                </tr>
                <tr>
                    <td>Reminders</td>
                    <td>Notifications that support you — never overwhelm you.</td>
                </tr>
            </tbody>
        </table>
    </section>

    <img src="./images/Moro_full_light.png">

    <!-- Lead capture forwards optional values to prefill the registration form (register.php?fname=...&lname=...&email=...). -->
    <form name="appForm" action="register.php" method="get">
        <h2 class="form-title">Interested? Give it a try.</h2>

        <div class="row">
            <label>First Name</label>
            <input type="text" name="fname">
        </div>

        <div class="row">
            <label>Last Name</label>
            <input type="text" name="lname">
        </div>

        <div class="row">
            <label>Email</label>
            <input type="email" name="email">
        </div>

        <div class="row">
            <input type="submit" value="Continue to Registration">
        </div>
    </form>

</body>
</html>
