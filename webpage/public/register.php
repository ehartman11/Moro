<?php
declare(strict_types=1);

/**
 * Account registration page.
 *
 * Responsibilities:
 * - Displays a create-account form (optionally prefilled via query params).
 * - On POST, validates required fields, hashes the password, and inserts a new user row.
 * - Shows a success prompt linking to login, or a user-friendly error message.
 */

require_once __DIR__ . '/../src/core/bootstrap.php';

use Moro\Core\Db;
use Moro\Core\Paths;
use Moro\Core\Auth;

$pdo = Db::pdo();
$baseUrl = Paths::baseUrl();
$csrf = Auth::csrfToken();

$prefillFname = (string)($_GET['fname'] ?? '');
$prefillLname = (string)($_GET['lname'] ?? '');
$prefillEmail = (string)($_GET['email'] ?? '');

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireCsrf($_POST['csrf'] ?? '');

    $fname = trim((string)($_POST['fname'] ?? ''));
    $lname = trim((string)($_POST['lname'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($fname === '' || $lname === '' || $email === '' || $password === '') {
        $error = 'All required fields must be filled.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (fname, lname, email, phone, role, password)
                VALUES (:fname, :lname, :email, :phone, :role, :password)
            ");

            $stmt->execute([
                ':fname'    => $fname,
                ':lname'    => $lname,
                ':email'    => $email,
                ':phone'    => ($phone !== '' ? $phone : null),
                ':role'     => 'seeker',
                ':password' => $hash,
            ]);

            $success = true;

        } catch (PDOException $e) {
            // Most common expected failure: unique email constraint.
            // SQLSTATE 23000 = integrity constraint violation (MySQL uses it for duplicate keys).
            if ($e->getCode() === '23000') {
                $error = 'That email is already registered.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
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
    <title>Create Account</title>

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/base.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/forms.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/popup.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/public/styling/nav.css">
</head>
<body>

<?php require_once Paths::root() . '/nav_bar.php'; ?>

<form method="POST" action="<?= $baseUrl ?>/public/register.php">
    <h2 class="form-title">Create Account</h2>
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

    <?php if ($success): ?>
        <div class="popup show" style="background:#4CAF50;">
            Account created successfully.
            <br><br>
            <a href="<?= $baseUrl ?>/public/login.php" style="color:white;text-decoration:underline;">
                Sign In
            </a>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="popup show" style="background:#e74c3c;">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <label>First Name *</label>
        <input type="text" name="fname" required value="<?= h($prefillFname) ?>">
    </div>

    <div class="row">
        <label>Last Name *</label>
        <input type="text" name="lname" required value="<?= h($prefillLname) ?>">
    </div>

    <div class="row">
        <label>Email *</label>
        <input type="email" name="email" required value="<?= h($prefillEmail) ?>">
    </div>

    <div class="row">
        <label>Phone</label>
        <input type="text" name="phone">
    </div>

    <div class="row">
        <label>Password *</label>
        <input type="password" name="password" required>
    </div>

    <div class="row">
        <input type="submit" value="Create Account">
    </div>

    <p style="text-align:center;">
        Already have an account? <a href="<?= $baseUrl ?>/public/login.php">Sign in</a>
    </p>
</form>

</body>
</html>
