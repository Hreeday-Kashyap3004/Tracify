<?php
// includes/functions.php

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION["user_id"]);
}

// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Function to sanitize output (prevent XSS)
function escape($html) {
    return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Function to add a notification
function add_notification($pdo, $user_id, $message, $item_id = null) {
    $sql = "INSERT INTO notifications (user_id, item_id, message) VALUES (:user_id, :item_id, :message)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT); // Allows null
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        // Log error in real application
        // error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// Function to get unread notification count
function get_unread_notification_count($pdo, $user_id) {
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = FALSE";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? $result['count'] : 0;
}

// --- Add more utility functions as needed ---
?>