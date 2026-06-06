<?php
declare(strict_types=1);

/**
 * Login page.
 *
 * Responsibilities:
 * - Renders the sign-in form.
 * - Authenticates a user via email + password.
 * - On success, initializes session identity and redirects into the app.
 * - On failure, returns a generic authentication error.
 */

require_once __DIR__ . '/../src/core/bootstrap.php';

use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Response;
use Moro\Core\Auth;

$pdo = Db::pdo();
$baseUrl = Paths::baseUrl();
$csrf = Auth::csrfToken();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf($_POST['csrf'] ?? '');

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT id, fname, lname, password, role
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, (string)$user['password'])) {
            // Establish authenticated session identity.
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_name'] = (string)($user['fname'] ?? 'User');
            $_SESSION['user_role'] = (string)($user['role'] ?? 'user');

            // IMPORTANT: choose a real post-login landing page.
            // Recommended flow: go to homes.php to select/confirm active home.
            Response::redirect('/public/homes.php');
        }

        // Deliberately generic to avoid leaking which field was incorrect.
        $error = 'Invalid email or password.';
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<form action="<?= $baseUrl ?>/public/login.php" method="post">
    <h2 class="form-title">Sign In</h2>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

    <?php if ($error !== ''): ?>
        <div class="popup show" style="background:#e74c3c;">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <label>Email</label>
        <input type="email" name="email" required>
    </div>

    <div class="row">
        <label>Password</label>
        <input type="password" name="password" required>
    </div>

    <div class="row">
        <input type="submit" value="Sign In">
    </div>

    <p style="text-align:center;">
        No account? <a href="<?= $baseUrl ?>/public/register.php">Create one</a>
    </p>
</form>

</body>
</html>
