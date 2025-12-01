<?php
session_start();

/* Unset all session variables */
$_SESSION = [];

/* Destroy the session */
session_destroy();

session_regenerate_id(true);

/* Redirect to login page */
header("Location: login.php");
exit;
