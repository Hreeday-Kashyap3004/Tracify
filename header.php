<?php
// includes/header.php
require_once 'db.php';      // Ensure DB connection and session start
require_once 'functions.php'; // Include functions

$unread_count = 0;
if (is_logged_in()) {
    $unread_count = get_unread_notification_count($pdo, $_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRACIFY - Lost & Found</title>
    <link rel="stylesheet" href="css/style.css">
    <!-- Add any other CSS/JS libraries if needed -->
</head>
<body>
    <header>
        <h1><a href="index.php">TRACIFY</a></h1>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="report.php">Report Item</a></li>
                    <li><a href="my_items.php">My Items</a></li>
                    <li><a href="notifications.php">Notifications <?php if($unread_count > 0) echo "<span class='notif-count'>($unread_count)</span>"; ?></a></li>
                    <li><span>Welcome, <?php echo escape($_SESSION['username'] ?? 'User'); ?>!</span></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    <main class="container">
        <?php
        // Display session flash messages (optional but good)
        if (isset($_SESSION['flash_message'])) {
            echo '<div class="flash-' . escape($_SESSION['flash_type'] ?? 'info') . '">' . escape($_SESSION['flash_message']) . '</div>';
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        }
        ?>