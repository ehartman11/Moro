<?php
declare(strict_types=1);

require_once __DIR__ . '/src/core/bootstrap.php';

use Moro\Core\Auth;
use Moro\Core\Db;
use Moro\Core\Paths;

$baseUrl = Paths::baseUrl();
$userId  = Auth::userId();

$userName = $_SESSION['user_name'] ?? 'User';

$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/public/index.php');
if ($requestUri === '' || !str_starts_with($requestUri, '/')) {
    $requestUri = '/public/index.php';
}

$homeId = Auth::activeHomeId();
$homeRole = 'viewer';
$activePortalRole = 'myhome';
$canSwitchPortal = false;
$availablePortals = [
    'myhome' => false,
    'contracting' => false,
    'searching' => false,
];

if ($userId !== null) {
    $pdo = Db::pdo();
  if ($homeId !== null) {
    $homeRole = Auth::roleOnHome($pdo, $userId, $homeId);
  }

    foreach (array_keys($availablePortals) as $portalRole) {
        $availablePortals[$portalRole] = Auth::canUsePortalRole($pdo, $userId, $homeId, $portalRole);
    }

    $savedPortalRole = (string)($_SESSION['active_portal_role'] ?? '');
    if (!Auth::canUsePortalRole($pdo, $userId, $homeId, $savedPortalRole)) {
        $savedPortalRole = Auth::resolveDefaultPortalRole($pdo, $userId, $homeId);
        $_SESSION['active_portal_role'] = $savedPortalRole;
    }

    $activePortalRole = $savedPortalRole;
    $canSwitchPortal = true;
}

$portalError = '';
$errCode = isset($_GET['err']) ? (string)$_GET['err'] : '';
if ($errCode !== '') {
    $portalError = match ($errCode) {
        'portal_role_invalid' => 'Selected portal role is invalid.',
        'portal_role_unavailable' => 'That portal is not available in your current context.',
        'contractor_profile_required' => 'Complete your contractor profile to access Contracting.',
        'seeker_role_required' => 'Your current home context does not include seeker access.',
        'admin_required' => 'Administrator access is required for that page.',
        default => '',
    };
}

$esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<nav class="nav">
  <div class="nav-global">
    <a href="<?= $baseUrl ?>/public/index.php" class="nav-logo">Moro</a>

    <?php if ($userId !== null): ?>
      <div class="nav-global-links">
        <a href="<?= $baseUrl ?>/public/homes.php">Homes</a>

        <?php if (\Moro\Core\Auth::isAdmin()): ?>
          <a href="<?= $baseUrl ?>/public/admin/verification_queue.php">Verification Queue</a>
        <?php endif; ?>

        <?php if ($canSwitchPortal): ?>
          <form class="nav-portal-switch" method="post" action="<?= $baseUrl ?>/public/actions.php?action=nav.switch_portal">
            <input type="hidden" name="csrf" value="<?= $esc(Auth::csrfToken()) ?>">
            <input type="hidden" name="return_to" value="<?= $esc($requestUri) ?>">
            <label for="nav_portal_role" class="nav-portal-label">Role</label>
            <select id="nav_portal_role" name="portal_role" onchange="this.form.submit()">
              <option value="myhome" <?= $activePortalRole === 'myhome' ? 'selected' : '' ?> <?= !$availablePortals['myhome'] ? 'disabled' : '' ?>>MyHome</option>
              <option value="contracting" <?= $activePortalRole === 'contracting' ? 'selected' : '' ?> <?= !$availablePortals['contracting'] ? 'disabled' : '' ?>>Contracting</option>
              <option value="searching" <?= $activePortalRole === 'searching' ? 'selected' : '' ?> <?= !$availablePortals['searching'] ? 'disabled' : '' ?>>Searching</option>
            </select>
          </form>
        <?php endif; ?>

        <div class="nav-user">
          <span class="nav-username">
            <?= $esc((string)$userName) ?>
          </span>

          <div class="nav-dropdown">
            <a href="<?= $baseUrl ?>/public/profile.php">Profile</a>
            <a href="<?= $baseUrl ?>/public/homes.php">Switch Home</a>
            <a href="<?= $baseUrl ?>/public/logout.php">Logout</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="nav-global-links">
        <a href="<?= $baseUrl ?>/public/login.php">Login</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($userId !== null): ?>
    <ul class="nav-portal-links">
      <?php if ($activePortalRole === 'contracting'): ?>
        <li><a href="<?= $baseUrl ?>/public/contractor/index.php">Contractor Portal</a></li>
        <?php if ($homeId !== null): ?>
          <li><a href="<?= $baseUrl ?>/public/contractor/my_jobs.php">My Jobs</a></li>
          <li><a href="<?= $baseUrl ?>/public/contractor/submissions.php">Submissions</a></li>
          <?php if ($homeRole === 'owner'): ?>
            <li><a href="<?= $baseUrl ?>/public/contractor/homeowner_jobs.php">Owner Job Inbox</a></li>
          <?php endif; ?>
        <?php else: ?>
          <li><a href="<?= $baseUrl ?>/public/homes.php">Select Home for Job Queues</a></li>
        <?php endif; ?>
      <?php elseif ($activePortalRole === 'searching'): ?>
        <li><a href="<?= $baseUrl ?>/public/seeker/index.php">Searching Overview</a></li>
      <?php else: ?>
        <?php if ($homeId !== null): ?>
          <li><a href="<?= $baseUrl ?>/public/tickler/index.php">Countdown</a></li>
          <li><a href="<?= $baseUrl ?>/public/items/index.php">Items</a></li>
          <li><a href="<?= $baseUrl ?>/public/maintenance/index.php">MRCs</a></li>
          <li><a href="<?= $baseUrl ?>/public/feedback/index.php">Feedback</a></li>
        <?php else: ?>
          <li><a href="<?= $baseUrl ?>/public/homes.php">Set Up MyHome</a></li>
        <?php endif; ?>
      <?php endif; ?>
    </ul>
  <?php endif; ?>

  <?php if ($portalError !== ''): ?>
    <div class="nav-message"><?= $esc($portalError) ?></div>
  <?php endif; ?>
</nav>
