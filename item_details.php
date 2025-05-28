<?php
// item_details.php
require_once 'includes/header.php'; // Includes db, functions, session start

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;
$potential_matches = []; // For showing potential matches

if ($item_id <= 0) {
    $_SESSION['flash_message'] = "Invalid item ID specified.";
    $_SESSION['flash_type'] = "error";
    redirect("index.php");
}

// Fetch the main item details
$sql = "SELECT i.*, u.username AS reporter_username, u.user_id AS reporter_id
        FROM items i
        JOIN users u ON i.user_id = u.user_id
        WHERE i.item_id = :item_id";
try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $stmt->execute();
    $item = $stmt->fetch();

    if (!$item) {
        $_SESSION['flash_message'] = "Item not found.";
        $_SESSION['flash_type'] = "error";
        redirect("index.php");
    }

    // --- Potential Matching Logic ---
    // If viewing a LOST item, find potential matching FOUND items
    if ($item['item_type'] === 'lost' && $item['status'] === 'reported') {
        $match_sql = "SELECT item_id, item_name, category, location, image_path
                      FROM items
                      WHERE item_type = 'found' AND status = 'reported'
                      AND item_id != :current_item_id /* Don't match itself */
                      AND (category = :category OR item_name LIKE :item_name_like)
                      ORDER BY reported_at DESC
                      LIMIT 5"; // Limit the number of suggestions
        $match_stmt = $pdo->prepare($match_sql);
        $like_name = '%' . $item['item_name'] . '%';
        $match_stmt->bindParam(':current_item_id', $item_id, PDO::PARAM_INT);
        $match_stmt->bindParam(':category', $item['category'], PDO::PARAM_STR);
        $match_stmt->bindParam(':item_name_like', $like_name, PDO::PARAM_STR);
        $match_stmt->execute();
        $potential_matches = $match_stmt->fetchAll();
    }
    // If viewing a FOUND item, find potential matching LOST items
    elseif ($item['item_type'] === 'found' && $item['status'] === 'reported') {
         $match_sql = "SELECT item_id, item_name, category, location, image_path
                      FROM items
                      WHERE item_type = 'lost' AND status = 'reported'
                       AND item_id != :current_item_id /* Don't match itself */
                      AND (category = :category OR item_name LIKE :item_name_like)
                      ORDER BY reported_at DESC
                      LIMIT 5";
        $match_stmt = $pdo->prepare($match_sql);
        $like_name = '%' . $item['item_name'] . '%';
        $match_stmt->bindParam(':current_item_id', $item_id, PDO::PARAM_INT);
        $match_stmt->bindParam(':category', $item['category'], PDO::PARAM_STR);
        $match_stmt->bindParam(':item_name_like', $like_name, PDO::PARAM_STR);
        $match_stmt->execute();
        $potential_matches = $match_stmt->fetchAll();
    }


} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Database error retrieving item details.";
    $_SESSION['flash_type'] = "error";
    // Log error: error_log($e->getMessage());
    redirect("index.php");
}

// --- Claim Handling Logic ---
$claim_error = '';
$claim_success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && is_logged_in()) {

    // --- Claiming a FOUND item ---
    if ($_POST['action'] == 'submit_claim' && $item['item_type'] == 'found' && $item['status'] == 'reported' && $item['reporter_id'] != $_SESSION['user_id']) {
        $claim_details = trim($_POST['claim_details'] ?? '');

        if (empty($claim_details) && !empty($item['verification_question'])) {
            $claim_error = "Please provide details or answer the verification question to support your claim.";
        } else {
            // Record the claim attempt - Update the item record directly (simple approach)
            // In a more complex system, you might use the 'claims' table.
            $update_sql = "UPDATE items SET claimer_id = :claimer_id, claim_details = :claim_details, status = 'pending_claim' /* Change status */
                           WHERE item_id = :item_id AND status = 'reported'"; // Ensure it hasn't been claimed already
            try {
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->bindParam(':claimer_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $update_stmt->bindParam(':claim_details', $claim_details, PDO::PARAM_STR);
                $update_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);

                if ($update_stmt->execute() && $update_stmt->rowCount() > 0) {
                    // Notify the finder
                    $message = "User '" . escape($_SESSION['username']) . "' has submitted a claim for your found item: '" . escape($item['item_name']) . "'. Please review it on 'My Items'.";
                    add_notification($pdo, $item['reporter_id'], $message, $item_id);

                    $_SESSION['flash_message'] = "Your claim has been submitted. The finder has been notified.";
                    $_SESSION['flash_type'] = "success";
                    redirect("item_details.php?id=" . $item_id); // Refresh page
                } else {
                    $claim_error = "Could not submit claim. The item might already be claimed or an error occurred.";
                }
            } catch (PDOException $e) {
                 $claim_error = "Database error during claim submission.";
                 // Log error: error_log("Claim submission error: " . $e->getMessage());
            }
        }
        // We need to refetch the item after potential status change
        $stmt->execute(); // Re-run the original fetch query
        $item = $stmt->fetch();
    }

    // --- Reporter Approving/Rejecting a Claim ---
    elseif (($_POST['action'] == 'approve_claim' || $_POST['action'] == 'reject_claim') && $item['status'] == 'pending_claim' && $item['reporter_id'] == $_SESSION['user_id']) {

        $new_status = ($_POST['action'] == 'approve_claim') ? 'claimed' : 'reported'; // 'reported' makes it available again
        $claimer_id_to_notify = $item['claimer_id']; // Get claimer ID before potentially clearing it

        $update_sql = "UPDATE items SET status = :new_status,
                       claimer_id = CASE WHEN :new_status_case = 'claimed' THEN claimer_id ELSE NULL END,
                       claim_details = CASE WHEN :new_status_case = 'claimed' THEN claim_details ELSE NULL END
                       WHERE item_id = :item_id AND status = 'pending_claim'";
        try {
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':new_status', $new_status, PDO::PARAM_STR);
            $update_stmt->bindParam(':new_status_case', $new_status, PDO::PARAM_STR); // Needed for CASE logic
            $update_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);

            if ($update_stmt->execute()) {
                if ($_POST['action'] == 'approve_claim') {
                    $_SESSION['flash_message'] = "Claim approved. The item is now marked as claimed.";
                    $_SESSION['flash_type'] = "success";
                    // Notify claimer
                    $message = "Your claim for '" . escape($item['item_name']) . "' has been approved by the finder!";
                    add_notification($pdo, $claimer_id_to_notify, $message, $item_id);

                    // Optional: Implement secure contact exchange here if desired (more complex)

                } else { // Rejected
                    $_SESSION['flash_message'] = "Claim rejected. The item is available again.";
                    $_SESSION['flash_type'] = "info";
                     // Notify claimer
                    $message = "Unfortunately, your claim for '" . escape($item['item_name']) . "' was not approved by the finder.";
                    add_notification($pdo, $claimer_id_to_notify, $message, $item_id);
                }
                 redirect("item_details.php?id=" . $item_id); // Refresh page
            } else {
                 $_SESSION['flash_message'] = "Could not update claim status.";
                 $_SESSION['flash_type'] = "error";
            }

        } catch (PDOException $e) {
            $_SESSION['flash_message'] = "Database error updating claim.";
            $_SESSION['flash_type'] = "error";
            // Log error: error_log("Claim update error: " . $e->getMessage());
        }
        // We need to refetch the item after potential status change
        $stmt->execute(); // Re-run the original fetch query
        $item = $stmt->fetch();
    }

     // --- Reporter Closing their own LOST item (marking as found/resolved) ---
    elseif ($_POST['action'] == 'close_lost_item' && $item['item_type'] == 'lost' && $item['status'] == 'reported' && $item['reporter_id'] == $_SESSION['user_id']) {
        $update_sql = "UPDATE items SET status = 'closed' WHERE item_id = :item_id AND user_id = :user_id";
         try {
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $update_stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);

            if ($update_stmt->execute()) {
                 $_SESSION['flash_message'] = "Your lost item report has been closed.";
                 $_SESSION['flash_type'] = "success";
                 redirect("item_details.php?id=" . $item_id); // Refresh page
            } else {
                $_SESSION['flash_message'] = "Could not close the item report.";
                $_SESSION['flash_type'] = "error";
            }
         } catch (PDOException $e) {
             $_SESSION['flash_message'] = "Database error closing item.";
             $_SESSION['flash_type'] = "error";
             // Log error
         }
         // Refetch item data
         $stmt->execute();
         $item = $stmt->fetch();
    }

     // --- Reporter Re-opening a FOUND item after rejecting claim ---
     // (Handled implicitly by setting status back to 'reported' on reject)

}


?>

<div class="item-details">
    <div class="item-details-img">
        <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
            <img src="<?php echo escape($item['image_path']); ?>" alt="<?php echo escape($item['item_name']); ?>">
        <?php else: ?>
             <img src="images/placeholder.png" alt="No image available">
        <?php endif; ?>
    </div>

    <div class="item-details-info">
        <h2><?php echo escape($item['item_name']); ?></h2>
        <p><strong>Status:</strong> <span class="status-<?php echo escape($item['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', escape($item['status']))); ?></span></p>
        <p><strong>Type:</strong> <?php echo ucfirst(escape($item['item_type'])); ?></p>
        <p><strong>Category:</strong> <?php echo escape($item['category']); ?></p>
        <p><strong>Description:</strong> <?php echo nl2br(escape($item['description'])); // Use nl2br to respect newlines ?></p>
        <?php if (!empty($item['location'])): ?>
            <p><strong>Location <?php echo ($item['item_type'] == 'lost') ? 'Lost' : 'Found'; ?>:</strong> <?php echo escape($item['location']); ?></p>
        <?php endif; ?>
        <?php if (!empty($item['item_date'])): ?>
            <p><strong>Date <?php echo ($item['item_type'] == 'lost') ? 'Lost' : 'Found'; ?>:</strong> <?php echo date("F j, Y", strtotime($item['item_date'])); ?></p>
        <?php endif; ?>
        <p><strong>Reported By:</strong> <?php echo escape($item['reporter_username']); ?></p>
        <p><strong>Reported On:</strong> <?php echo date("F j, Y, g:i a", strtotime($item['reported_at'])); ?></p>

         <?php if (!empty($item['verification_question']) && $item['item_type'] == 'found'): ?>
            <p><strong>Verification Question:</strong> <?php echo escape($item['verification_question']); ?></p>
            <small>(Finder set this question - answer it in your claim details if possible)</small>
        <?php endif; ?>


        <!-- Action Buttons -->
        <div class="item-details-actions">
            <?php if (is_logged_in()): ?>
                <?php // --- Actions for the REPORTER --- ?>
                <?php if ($item['reporter_id'] == $_SESSION['user_id']): ?>
                    <?php if ($item['status'] == 'reported' && $item['item_type'] == 'lost'): ?>
                        <form action="item_details.php?id=<?php echo $item_id; ?>" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="close_lost_item">
                            <button type="submit" class="button button-success" onclick="return confirm('Are you sure you want to close this report? (e.g., you found the item)');">Mark as Found/Close</button>
                        </form>
                     <?php elseif ($item['status'] == 'pending_claim' && $item['item_type'] == 'found'): ?>
                        <h4>Review Claim:</h4>
                        <?php
                            // Fetch claimer's username
                            $claimer_sql = "SELECT username FROM users WHERE user_id = :claimer_id";
                            $claimer_stmt = $pdo->prepare($claimer_sql);
                            $claimer_stmt->bindParam(':claimer_id', $item['claimer_id'], PDO::PARAM_INT);
                            $claimer_stmt->execute();
                            $claimer_info = $claimer_stmt->fetch();
                        ?>
                        <p><strong>Claim submitted by:</strong> <?php echo escape($claimer_info['username'] ?? 'Unknown User'); ?></p>
                        <p><strong>Claimer's Details/Answer:</strong></p>
                        <blockquote style="border-left: 3px solid #ccc; padding-left: 10px; margin-left: 0; background-color:#f9f9f9;">
                            <?php echo nl2br(escape($item['claim_details'] ?? 'No details provided.')); ?>
                        </blockquote>
                         <form action="item_details.php?id=<?php echo $item_id; ?>" method="POST" style="display:inline-block; margin-right: 10px;">
                             <input type="hidden" name="action" value="approve_claim">
                             <button type="submit" class="button button-success" onclick="return confirm('Approve this claim? The item will be marked as claimed.');">Approve Claim</button>
                         </form>
                         <form action="item_details.php?id=<?php echo $item_id; ?>" method="POST" style="display:inline-block;">
                             <input type="hidden" name="action" value="reject_claim">
                             <button type="submit" class="button button-danger" onclick="return confirm('Reject this claim? The item will become available again.');">Reject Claim</button>
                         </form>
                    <?php elseif ($item['status'] == 'claimed'): ?>
                         <p><em>You approved the claim for this item.</em></p>
                          <?php
                            // Optionally show who claimed it if you approved
                            $claimer_sql = "SELECT username FROM users WHERE user_id = :claimer_id";
                            $claimer_stmt = $pdo->prepare($claimer_sql);
                            $claimer_stmt->bindParam(':claimer_id', $item['claimer_id'], PDO::PARAM_INT);
                            $claimer_stmt->execute();
                            $claimer_info = $claimer_stmt->fetch();
                            if($claimer_info) {
                                echo "<p><strong>Claimed By:</strong> " . escape($claimer_info['username']) . "</p>";
                            }
                          ?>
                    <?php elseif ($item['status'] == 'closed'): ?>
                         <p><em>You have closed this lost item report.</em></p>
                    <?php endif; ?>

                <?php // --- Actions for OTHER USERS --- ?>
                <?php elseif ($item['item_type'] == 'found' && $item['status'] == 'reported'): ?>
                     <h4>Claim This Item</h4>
                     <?php if (!empty($claim_error)): ?>
                         <p style="color: red;"><?php echo escape($claim_error); ?></p>
                     <?php endif; ?>
                     <form action="item_details.php?id=<?php echo $item_id; ?>" method="POST" class="claim-form">
                         <input type="hidden" name="action" value="submit_claim">
                         <div>
                             <label for="claim_details">Provide details to prove ownership <?php echo (!empty($item['verification_question']) ? 'or answer the question above' : ''); ?>:</label>
                             <textarea name="claim_details" id="claim_details" rows="3" required></textarea>
                         </div>
                         <button type="submit" class="button">Submit Claim</button>
                     </form>
                 <?php elseif ($item['status'] == 'pending_claim' && $item['claimer_id'] == $_SESSION['user_id']): ?>
                    <p><em>Your claim for this item is pending review by the finder.</em></p>
                 <?php elseif ($item['status'] == 'claimed'): ?>
                     <p><em>This item has already been claimed.</em></p>
                 <?php elseif ($item['status'] == 'closed'): ?>
                     <p><em>This lost item report has been closed by the owner.</em></p>
                 <?php endif; ?>

            <?php else: // Not logged in ?>
                 <?php if ($item['item_type'] == 'found' && $item['status'] == 'reported'): ?>
                    <p><a href="login.php?redirect=item_details.php?id=<?php echo $item_id; ?>">Login</a> or <a href="register.php">Register</a> to claim this item.</p>
                 <?php elseif ($item['status'] == 'claimed'): ?>
                     <p><em>This item has already been claimed.</em></p>
                  <?php elseif ($item['status'] == 'closed'): ?>
                     <p><em>This lost item report has been closed by the owner.</em></p>
                 <?php endif; ?>
            <?php endif; ?>
             <a href="index.php" class="button button-secondary" style="margin-top: 10px;">Back to List</a>
        </div>
    </div>
</div>

<!-- Potential Matches Section -->
<?php if (!empty($potential_matches)): ?>
<div class="potential-matches" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
    <h3>Potential Matches Found</h3>
    <div class="item-list">
         <?php foreach ($potential_matches as $match): ?>
            <div class="item-card item-<?php echo ($item['item_type'] == 'lost' ? 'found' : 'lost'); // Show opposite type ?>">
                <?php if (!empty($match['image_path']) && file_exists($match['image_path'])): ?>
                    <img src="<?php echo escape($match['image_path']); ?>" alt="<?php echo escape($match['item_name']); ?>">
                <?php else: ?>
                     <img src="images/placeholder.png" alt="No image available">
                <?php endif; ?>
                <h3><?php echo escape($match['item_name']); ?></h3>
                 <p><strong>Category:</strong> <?php echo escape($match['category']); ?></p>
                <?php if (!empty($match['location'])): ?>
                     <p><strong>Location:</strong> <?php echo escape($match['location']); ?></p>
                <?php endif; ?>
                 <div class="item-actions">
                    <a href="item_details.php?id=<?php echo $match['item_id']; ?>" class="button">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>


<style>
    .status-reported { color: blue; font-weight: bold; }
    .status-pending_claim { color: orange; font-weight: bold; }
    .status-claimed { color: green; font-weight: bold; }
    .status-closed { color: grey; font-weight: bold; }
</style>

<?php include 'includes/footer.php'; ?>