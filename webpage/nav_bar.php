<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- Navigation bar -->
<nav class="nav">
    <a href="index.php" class="nav-logo">Moro</a>

    <!-- User dropdown when logged in -->
    <?php if (isset($_SESSION["user_id"])): ?>
        <div class="nav-user">
            <span class="nav-username">
                <?= htmlspecialchars($_SESSION["user_name"] ?? "User") ?>
            </span>

            <div class="nav-dropdown">
                <a href="profile.php">Profile</a>
                <a href="homes.php">Switch Home</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main links -->
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="tickler.php">Countdown</a></li>

        <?php if (isset($_SESSION["user_id"])): ?>
            <li><a href="homes.php">Homes</a></li>
            <li><a href="items.php">Items</a></li>
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
        <?php endif; ?>
    </ul>
</nav>
