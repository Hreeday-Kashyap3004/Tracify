<?php
// logout.php

// Initialize the session - Must be done before accessing session variables
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Unset all of the session variables
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to login page (or home page)
header("location: index.php");
exit;
?>