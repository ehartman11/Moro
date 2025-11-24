<!-- Store posted information in variables for later use -->
<!-- If no information has been posted, then set the variable values to null --> 
<?php
$fullName = $_POST['fullName'] ?? null;
$birthdate = $_POST['birthdate'] ?? null;
$email = $_POST['email'] ?? null;
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Form Submissions</title>
    <link rel="stylesheet" href="main.css">
</head>
<body>

    <!-- navigation bar -->
    <?php include 'nav_bar.php'; ?>

    <!-- Simple display for showcasing the information from the submitted form -->
    <section>
        <!-- check if all the variables have values -->
        <?php if ($fullName && $birthdate && $email): ?>
            <!-- display the information -->
            <h2>Submission Received</h2>
            <p><strong>Name:</strong> <?= $fullName ?></p>
            <p><strong>Birthdate:</strong> <?= $birthdate ?></p>
            <p><strong>Email:</strong> <?= $email ?></p>
        <!-- if no information has been submitted, inform the user to fill out the form and submit it -->
        <?php else: ?>

            <h2>No Submission Yet</h2>
            <p>Use the form on the home page to submit your information.</p>

        <?php endif; ?>

    </section>

</body>
</html>
