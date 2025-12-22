<?php
/**
 * Account registration page.
 *
 * - Renders a create-account form (optionally prefilled via query params).
 * - On POST, validates required fields, hashes the password, and inserts a new user.
 * - Displays success or a user-friendly error message.
 */
require_once "db_conn.php";

$prefill_fname = $_GET["fname"] ?? "";
$prefill_lname = $_GET["lname"] ?? "";
$prefill_email = $_GET["email"] ?? "";

$error = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fname = trim($_POST["fname"] ?? "");
    $lname = trim($_POST["lname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = "user";

    if ($fname === "" || $lname === "" || $email === "" || $password === "") {
        $error = "All required fields must be filled.";
    } else {
        // Uses PHP's recommended password hashing (algorithm + cost managed by PASSWORD_DEFAULT).
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (fname, lname, email, phone, role, password)
                VALUES (:fname, :lname, :email, :phone, :role, :password)
            ");

            $stmt->execute([
                ":fname" => $fname,
                ":lname" => $lname,
                ":email" => $email,
                // Store empty phone as NULL to avoid meaninglessly distinct values ("", NULL).
                ":phone" => $phone ?: null,
                ":role"  => $role,
                ":password" => $hash
            ]);

            $success = true;

        } catch (PDOException $e) {
            // Assumes the expected failure here is a unique email constraint.
            $error = "That email is already registered.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Create Account</title>
    <link rel="stylesheet" href="styling/base.css">
    <link rel="stylesheet" href="styling/forms.css">
    <link rel="stylesheet" href="styling/popup.css">
    <link rel="stylesheet" href="styling/nav.css">
</head>
<body>

    <nav class="nav">
        <a href="index.php" class="nav-logo">Moro</a>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="login.php">Login</a></li>
        </ul>
    </nav>

<form method="POST">
    <h2 class="form-title">Create Account</h2>

    <?php if ($success): ?>
        <div class="popup show" style="background:#4CAF50;">
            Account created successfully.
            <br><br>
            <a href="login.php" style="color:white;text-decoration:underline;">
                Sign In
            </a>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="popup show" style="background:#e74c3c;">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <label>First Name *</label>
        <input type="text" name="fname" required value="<?= htmlspecialchars($prefill_fname) ?>">
    </div>

    <div class="row">
        <label>Last Name *</label>
        <input type="text" name="lname" required value="<?= htmlspecialchars($prefill_lname) ?>">
    </div>

    <div class="row">
        <label>Email *</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($prefill_email) ?>">
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
        Already have an account? <a href="login.php">Sign in</a>
    </p>
</form>

</body>
</html>
