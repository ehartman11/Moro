<?php
require_once "db_conn.php";
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Email and password are required.";
    } else {
        $stmt = $pdo->prepare("
            SELECT id, fname, lname, password, role
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([":email" => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["fname"];
            $_SESSION["user_role"] = $user["role"];

            header("Location: items.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sign In</title>
    <link rel="stylesheet" href="styling/base.css">
    <link rel="stylesheet" href="styling/forms.css">
    <link rel="stylesheet" href="styling/popup.css">
    <link rel="stylesheet" href="styling/nav.css">
    <script src="code.js"></script>
</head>
<body>
    <?php include 'nav_bar.php'; ?>

<form name="loginForm" action="login.php" method="post" onsubmit="return validateLogin();">

    <h2 class="form-title">Sign In</h2>

    <?php if ($error): ?>
        <div class="popup show" style="background:#e74c3c;">
            <?= htmlspecialchars($error) ?>
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
        No account? <a href="register.php">Create one</a>
    </p>
</form>

</body>
</html>
