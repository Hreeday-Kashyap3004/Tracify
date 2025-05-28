<?php
// notifications.php
require_once 'includes/header.php'; // Includes db, functions, session start

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['flash_message'] = "Please login to view notifications.";
    $_SESSION['flash_type'] = "info";
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// --- Mark notifications as read (simple approach: mark all on page load) ---
// In a real app, you might use AJAX or mark only specific ones clicked.
$update_sql = "UPDATE notifications SET is_read = TRUE WHERE user_id = :user_id AND is_read = FALSE";
try {
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $update_stmt->execute();
} catch (PDOException $e) {
    // Log error, but don't necessarily stop page load
    // error_log("Error marking notifications read: " . $e->getMessage());
}


// --- Fetch all notifications for the user ---
$sql = "SELECT notification_id, item_id, message, is_read, created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC"; // Show newest first

try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='flash-error'>Error fetching notifications: " . escape($e->getMessage()) . "</div>";
    $notifications = [];
}

?>

<h2>Your Notifications</h2>

<?php if (count($notifications) > 0): ?>
    <ul class="notification-list">
        <?php foreach ($notifications as $notif): ?>
            <?php
                // Determine link based on whether item_id exists
                $link = '#'; // Default link if no item_id
                if (!empty($notif['item_id'])) {
                    $link = 'item_details.php?id=' . (int)$notif['item_id'];
                }
                // Check if it was already read before this page load
                // (is_read might be true from previous visits or the update query above)
                $was_read_before_load = $notif['is_read'];
            ?>
            <li class="notification-item <?php echo $was_read_before_load ? '' : 'unread'; ?>">
                <p>
                    <?php echo escape($notif['message']); ?>
                    <?php if ($link !== '#'): ?>
                        <a href="<?php echo $link; ?>" style="margin-left: 10px; font-size: 0.9em;">(View Item)</a>
                    <?php endif; ?>
                </p>
                <small>
                     <?php echo date("M j, Y, g:i a", strtotime($notif['created_at'])); ?>
                </small>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>You have no notifications.</p>
<?php endif; ?>


<?php include 'includes/footer.php'; ?>