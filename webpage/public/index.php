<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/core/bootstrap.php';

use Moro\Core\Paths;

$baseUrl = Paths::baseUrl();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$flashError = '';
if (isset($_GET['err'])) {
    $flashError = match ((string)$_GET['err']) {
        'portal_role_invalid' => 'Selected portal role is invalid.',
        'portal_role_unavailable' => 'That portal is not available in your current context.',
        'contractor_profile_required' => 'Complete your contractor profile to access Contracting.',
        'seeker_role_required' => 'Your current home context does not include seeker access.',
        default => '',
    };
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Maintenance Scheduler</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/hero.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/home.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/tables.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<?php if ($flashError !== ''): ?>
    <div class="popup show" style="background:#e74c3c; margin:12px 20px;"><?= h($flashError) ?></div>
<?php endif; ?>

<header class="header">
    <img src="<?= $baseUrl ?>/public/images/Moro_logo_light.png" class="header-logo" alt="Moro">
    <h1>Know what needs attention - and when</h1>
    <p>
        Moro helps you stay on top of home, vehicle, and equipment upkeep - without stress or clutter.<br>
        Schedule recurring maintenance, track service history, and receive reminders before things need attention.<br>
        No overwhelm. No chaos. Just a calmer, more organized way to care for the things that matter,<br>
        so you can prevent issues before they become problems.
    </p>
</header>

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
        <li>Homes</li>
        <li>Vehicles</li>
        <li>Appliances</li>
        <li>Tools</li>
        <li>Outdoor equipment</li>
        <li>And more</li>
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

<img src="<?= $baseUrl ?>/public/images/Moro_full_light.png" alt="Moro">

<form name="appForm" action="<?= $baseUrl ?>/public/register.php" method="get">
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
