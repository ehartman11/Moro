<?php
// Ensure session is available for authentication-aware navigation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Provides BASE_URL for consistent link generation
require_once __DIR__ . "/config.php";
?>

<!--
    Global navigation bar.
    Renders different links based on authentication state.
-->
<nav class="nav">
    <a href="<?= BASE_URL ?>/index.php" class="nav-logo">Moro</a>

    <!--
        Authenticated user dropdown.
        Displays username and account-related actions.
    -->
    <?php if (isset($_SESSION["user_id"])): ?>
        <div class="nav-user">
            <span class="nav-username">
                <?= htmlspecialchars($_SESSION["user_name"] ?? "User") ?>
            </span>

            <div class="nav-dropdown">
                <a href="<?= BASE_URL ?>/profile.php">Profile</a>
                <a href="<?= BASE_URL ?>/homes.php">Switch Home</a>
                <a href="<?= BASE_URL ?>/logout.php">Logout</a>
            </div>
        </div>
    <?php endif; ?>

    <!--
        Primary navigation links.
        Authentication state determines which actions are available.
    -->
    <ul>
        <li><a href="<?= BASE_URL ?>/index.php">Home</a></li>
        <li><a href="<?= BASE_URL ?>/tickler.php">Countdown</a></li>

        <?php if (isset($_SESSION["user_id"])): ?>
            <li><a href="<?= BASE_URL ?>/homes.php">Homes</a></li>
            <li><a href="<?= BASE_URL ?>/items/index.php">Items</a></li>
        <?php else: ?>
            <li><a href="<?= BASE_URL ?>/login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>
