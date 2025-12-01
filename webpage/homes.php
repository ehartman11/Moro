<?php
require_once "db_conn.php";
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION["user_id"];
$success = false;
$error = "";

/* Handle New Home Creation */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_home"])) {

    $nickname = trim($_POST["nickname"] ?? "");
    $addr1 = trim($_POST["address_line1"] ?? "");
    $addr2 = trim($_POST["address_line2"] ?? "");
    $city = trim($_POST["city"] ?? "");
    $state = trim($_POST["state"] ?? "");
    $zip = trim($_POST["zip"] ?? "");
    $year = trim($_POST["year_built"] ?? "");

    if ($addr1 === "" || $city === "" || $state === "" || $zip === "") {
        $error = "Address Line 1, City, State, and ZIP are required.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO homes (owner_id, nickname, address_line1, address_line2, city, state, zip, year_built)
            VALUES (:owner_id, :nickname, :addr1, :addr2, :city, :state, :zip, :year_built)
        ");

        $stmt->execute([
            ":owner_id" => $userId,
            ":nickname" => $nickname ?: null,
            ":addr1" => $addr1,
            ":addr2" => $addr2 ?: null,
            ":city" => $city,
            ":state" => $state,
            ":zip" => $zip,
            ":year_built" => $year ?: null
        ]);

        $homeId = $pdo->lastInsertId();
        $stmtPerm = $pdo->prepare("
            INSERT INTO home_permissions (home_id, user_id, role)
            VALUES (:hid, :uid, 'owner')
        ");
        $stmtPerm->execute([
            ':hid' => $homeId,
            ':uid' => $userId
        ]);
        $_SESSION["active_home_id"] = $homeId;
        $success = true;
    }
}

/* Fetch All Homes Owned by User */
$stmt = $pdo->prepare("
    SELECT h.*, p.role
    FROM homes h
    JOIN home_permissions p ON p.home_id = h.id
    WHERE p.user_id = :uid
");
$stmt->execute([":uid" => $userId]);
$homes = $stmt->fetchAll();


/* Handle Home Selection */
if (isset($_GET["select_home"])) {

    $homeId = (int) $_GET["select_home"];

    $stmt = $pdo->prepare("
        SELECT 1
        FROM home_permissions
        WHERE user_id = :uid AND home_id = :hid
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':hid' => $homeId
    ]);

    if ($stmt->fetch()) {
        $_SESSION["active_home_id"] = $homeId;
        header("Location: items.php");
        exit;
    } else {
        $error = "You do not have permission to access this home.";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>My Homes</title>
    <link rel="stylesheet" href="styling/base.css">
    <link rel="stylesheet" href="styling/forms.css">
    <link rel="stylesheet" href="styling/popup.css">
    <link rel="stylesheet" href="styling/nav.css">
</head>
<body>

<?php include "nav_bar.php"; ?>

<section>

<h2 class="form-title">My Homes</h2>

<?php if ($success): ?>
    <div class="popup show" style="background:#4CAF50;">Home added successfully.</div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="popup show" style="background:#e74c3c;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Existing Homes -->
<?php if ($homes): ?>
    <table>
        <tr>
            <th>Nickname</th>
            <th>Address</th>
            <th>Action</th>
        </tr>
        <?php foreach ($homes as $home): ?>
        <tr>
            <td><?= htmlspecialchars($home["nickname"] ?? "—") ?></td>
            <td>
                <?= htmlspecialchars($home["address_line1"]) ?>,
                <?= htmlspecialchars($home["city"]) ?>,
                <?= htmlspecialchars($home["state"]) ?>
            </td>
            <td>
                <a href="homes.php?select_home=<?= $home["id"] ?>">Set Active</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p class="muted">No homes added yet.</p>
<?php endif; ?>

<!-- Add Home -->
<form method="POST">
    <h3>Add New Home</h3>

    <div class="row">
        <label>Nickname</label>
        <input type="text" name="nickname">
    </div>

    <div class="row">
        <label>Address Line 1 *</label>
        <input type="text" name="address_line1" required>
    </div>

    <div class="row">
        <label>Address Line 2</label>
        <input type="text" name="address_line2">
    </div>

    <div class="row">
        <label>City *</label>
        <input type="text" name="city" required>
    </div>

    <div class="row">
        <label>State *</label>
        <input type="text" name="state" required>
    </div>

    <div class="row">
        <label>ZIP *</label>
        <input type="text" name="zip" required>
    </div>

    <div class="row">
        <label>Year Built</label>
        <input type="number" name="year_built">
    </div>

    <div class="row">
        <input type="hidden" name="create_home" value="1">
        <input type="submit" value="Add Home">
    </div>
</form>

</section>
</body>
</html>
