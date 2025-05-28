<?php
// my_items.php - Shows items reported or claimed by the logged-in user
require_once 'includes/header.php'; // Includes db, functions, session start

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['flash_message'] = "Please login to view your items.";
    $_SESSION['flash_type'] = "info";
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Fetch items reported BY the user
$sql_reported = "SELECT i.*, u.username AS reporter_username
                 FROM items i
                 JOIN users u ON i.user_id = u.user_id
                 WHERE i.user_id = :user_id
                 ORDER BY i.reported_at DESC";

// Fetch items CLAIMED BY the user (where status is 'claimed' or 'pending_claim')
$sql_claimed = "SELECT i.*, finder.username AS reporter_username
                FROM items i
                JOIN users finder ON i.user_id = finder.user_id
                WHERE i.claimer_id = :user_id AND i.status IN ('claimed', 'pending_claim')
                ORDER BY i.reported_at DESC";

try {
    // Get reported items
    $stmt_reported = $pdo->prepare($sql_reported);
    $stmt_reported->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_reported->execute();
    $reported_items = $stmt_reported->fetchAll();

    // Get claimed items
    $stmt_claimed = $pdo->prepare($sql_claimed);
    $stmt_claimed->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_claimed->execute();
    $claimed_items = $stmt_claimed->fetchAll();

} catch (PDOException $e) {
    echo "<div class='flash-error'>Error fetching items: " . escape($e->getMessage()) . "</div>";
    $reported_items = [];
    $claimed_items = [];
}

?>

<h2>My Reported Items</h2>

<?php if (count($reported_items) > 0): ?>
    <div class="item-list">
        <?php foreach ($reported_items as $item):
            // Fetch claimer info if status is pending_claim
            $claimer_username = null;
            if ($item['status'] == 'pending_claim' && !empty($item['claimer_id'])) {
                 $claimer_sql = "SELECT username FROM users WHERE user_id = :claimer_id";
                 $claimer_stmt = $pdo->prepare($claimer_sql);
                 $claimer_stmt->bindParam(':claimer_id', $item['claimer_id'], PDO::PARAM_INT);
                 $claimer_stmt->execute();
                 $claimer_info = $claimer_stmt->fetch();
                 $claimer_username = $claimer_info ? $claimer_info['username'] : 'Unknown User';
            }
        ?>
            <div class="item-card item-<?php echo escape($item['item_type']); ?>">
                 <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                    <img src="<?php echo escape($item['image_path']); ?>" alt="<?php echo escape($item['item_name']); ?>">
                <?php else: ?>
                     <img src="images/placeholder.png" alt="No image available">
                <?php endif; ?>

                <h3><?php echo escape($item['item_name']); ?> (<?php echo ucfirst(escape($item['item_type'])); ?>)</h3>
                 <p><strong>Status:</strong> <span class="status-<?php echo escape($item['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', escape($item['status']))); ?></span>
                    <?php if ($item['status'] == 'pending_claim' && $claimer_username): ?>
                        <br><small>(Claim by: <?php echo escape($claimer_username); ?> - <a href="item_details.php?id=<?php echo $item['item_id']; ?>">Review Claim</a>)</small>
                    <?php endif; ?>
                 </p>
                <p><strong>Category:</strong> <?php echo escape($item['category']); ?></p>
                 <?php if (!empty($item['location'])): ?>
                     <p><strong>Location:</strong> <?php echo escape($item['location']); ?></p>
                <?php endif; ?>
                 <?php if (!empty($item['item_date'])): ?>
                     <p><strong>Date:</strong> <?php echo date("M j, Y", strtotime($item['item_date'])); ?></p>
                <?php endif; ?>
                <p><small>Reported on: <?php echo date("M j, Y", strtotime($item['reported_at'])); ?></small></p>


                <div class="item-actions">
                     <a href="item_details.php?id=<?php echo $item['item_id']; ?>" class="button">View Details</a>
                     <?php if($item['status'] == 'reported' || $item['status'] == 'pending_claim'): ?>
                        <!-- Maybe add an Edit button later -->
                     <?php endif; ?>
                     <?php if($item['status'] == 'reported' && $item['item_type'] == 'lost'): ?>
                         <form action="item_details.php?id=<?php echo $item['item_id']; ?>" method="POST" style="display:inline; margin-left: 5px;">
                            <input type="hidden" name="action" value="close_lost_item">
                            <button type="submit" class="button button-success" onclick="return confirm('Are you sure you want to close this report?');">Mark Found</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>You haven't reported any items yet. <a href="report.php">Report an item now</a>.</p>
<?php endif; ?>


<h2 style="margin-top: 40px;">Items You Have Claimed (or Pending)</h2>

<?php if (count($claimed_items) > 0): ?>
     <div class="item-list">
        <?php foreach ($claimed_items as $item): ?>
             <div class="item-card item-found"> <!-- Claimed items are always 'found' items -->
                 <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                    <img src="<?php echo escape($item['image_path']); ?>" alt="<?php echo escape($item['item_name']); ?>">
                <?php else: ?>
                     <img src="images/placeholder.png" alt="No image available">
                <?php endif; ?>

                <h3><?php echo escape($item['item_name']); ?> (Found Item)</h3>
                <p><strong>Status:</strong> <span class="status-<?php echo escape($item['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', escape($item['status']))); ?></span>
                     <?php if ($item['status'] == 'pending_claim'): ?>
                        <br><small>(Your claim is pending approval by <?php echo escape($item['reporter_username']); ?>)</small>
                     <?php elseif ($item['status'] == 'claimed'): ?>
                         <br><small>(Your claim was approved by <?php echo escape($item['reporter_username']); ?>)</small>
                    <?php endif; ?>
                </p>
                <p><strong>Category:</strong> <?php echo escape($item['category']); ?></p>
                <p><small>Reported by: <?php echo escape($item['reporter_username']); ?></small></p>


                <div class="item-actions">
                     <a href="item_details.php?id=<?php echo $item['item_id']; ?>" class="button">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
     <p>You haven't claimed any items yet.</p>
<?php endif; ?>

<style>
    .status-reported { color: blue; font-weight: bold; }
    .status-pending_claim { color: orange; font-weight: bold; }
    .status-claimed { color: green; font-weight: bold; }
    .status-closed { color: grey; font-weight: bold; }
</style>


<?php include 'includes/footer.php'; ?>