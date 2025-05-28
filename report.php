<?php
// report.php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in, otherwise redirect to login page
if (!is_logged_in()) {
    $_SESSION['flash_message'] = "Please login to report an item.";
    $_SESSION['flash_type'] = "info";
    redirect('login.php');
}

$item_type = $item_name = $category = $description = $location = $item_date = $verification_question = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Form Data Retrieval and Basic Validation ---
    $item_type = trim($_POST['item_type'] ?? '');
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $item_date = trim($_POST['item_date'] ?? '');
    $verification_question = ($item_type === 'found') ? trim($_POST['verification_question'] ?? '') : null; // Only for found items

    if (empty($item_type) || ($item_type !== 'lost' && $item_type !== 'found')) {
        $errors[] = "Please select whether the item was lost or found.";
    }
    if (empty($item_name)) {
        $errors[] = "Please enter the item name.";
    }
    if (empty($category)) {
        $errors[] = "Please select a category.";
    }
     if (empty($description)) {
        $errors[] = "Please provide a description.";
    }
    // Location and date are optional, so no empty check needed unless required by you

    // --- Image Upload Handling ---
    $image_path = null; // Default to null
    $upload_dir = "uploads/"; // Make sure this directory exists and is writable!

    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['item_image'];
        $file_name = $file['name'];
        $file_tmp_name = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        $file_type = $file['type'];

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_ext, $allowed_extensions)) {
            if ($file_error === 0) {
                if ($file_size < 5000000) { // 5MB limit (adjust as needed)
                    // Create unique filename to prevent overwriting
                    $new_file_name = uniqid('item_', true) . '.' . $file_ext;
                    $file_destination = $upload_dir . $new_file_name;

                    if (move_uploaded_file($file_tmp_name, $file_destination)) {
                        $image_path = $file_destination; // Store relative path
                    } else {
                        $errors[] = "Failed to move uploaded file. Check permissions on 'uploads' folder.";
                    }
                } else {
                    $errors[] = "Your file is too large (Max 5MB).";
                }
            } else {
                $errors[] = "There was an error uploading your file (Error code: $file_error).";
            }
        } else {
            $errors[] = "You cannot upload files of this type (Allowed: jpg, jpeg, png, gif).";
        }
    } elseif (isset($_FILES['item_image']) && $_FILES['item_image']['error'] != UPLOAD_ERR_NO_FILE) {
        // Handle other upload errors if needed
         $errors[] = "An error occurred during file upload (Code: " . $_FILES['item_image']['error'] . ").";
    }

    // --- Database Insertion ---
    if (empty($errors)) {
        $sql = "INSERT INTO items (user_id, item_type, item_name, category, description, location, item_date, image_path, verification_question)
                VALUES (:user_id, :item_type, :item_name, :category, :description, :location, :item_date, :image_path, :verification_question)";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':item_type', $item_type, PDO::PARAM_STR);
            $stmt->bindParam(':item_name', $item_name, PDO::PARAM_STR);
            $stmt->bindParam(':category', $category, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':location', $location, PDO::PARAM_STR); // Use PARAM_STR even if NULL
            $stmt->bindParam(':item_date', $item_date, PDO::PARAM_STR); // Use PARAM_STR even if NULL
            $stmt->bindParam(':image_path', $image_path, PDO::PARAM_STR); // Use PARAM_STR even if NULL
            $stmt->bindParam(':verification_question', $verification_question, PDO::PARAM_STR); // Use PARAM_STR even if NULL


            if ($stmt->execute()) {
                 // --- Potential Matching Notification (Basic) ---
                 if ($item_type === 'found') {
                    // Notify users who lost similar items
                    // This is a basic match - improve as needed
                    $match_sql = "SELECT user_id FROM items
                                  WHERE item_type = 'lost' AND status = 'reported'
                                  AND (category = :category OR item_name LIKE :item_name_like)";
                    $match_stmt = $pdo->prepare($match_sql);
                    $like_name = '%' . $item_name . '%';
                    $match_stmt->bindParam(':category', $category, PDO::PARAM_STR);
                    $match_stmt->bindParam(':item_name_like', $like_name, PDO::PARAM_STR);
                    $match_stmt->execute();
                    $potential_owners = $match_stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($potential_owners as $owner_id) {
                         if ($owner_id != $_SESSION['user_id']) { // Don't notify self
                            $message = "An item similar to one you reported lost ('" . escape($item_name) . "') has been found. Check the listings!";
                            add_notification($pdo, $owner_id, $message, $pdo->lastInsertId());
                        }
                    }
                 } elseif ($item_type === 'lost') {
                    // Notify users who found similar items
                    $match_sql = "SELECT item_id, user_id FROM items
                                  WHERE item_type = 'found' AND status = 'reported'
                                  AND (category = :category OR item_name LIKE :item_name_like)";
                     $match_stmt = $pdo->prepare($match_sql);
                     $like_name = '%' . $item_name . '%';
                     $match_stmt->bindParam(':category', $category, PDO::PARAM_STR);
                     $match_stmt->bindParam(':item_name_like', $like_name, PDO::PARAM_STR);
                     $match_stmt->execute();
                     $potential_finds = $match_stmt->fetchAll();

                     foreach ($potential_finds as $find) {
                         if ($find['user_id'] != $_SESSION['user_id']) { // Don't notify self
                             // Notify the finder
                             $finder_message = "Someone reported losing an item ('" . escape($item_name) . "') similar to one you found. Check item ID: " . $find['item_id'];
                             add_notification($pdo, $find['user_id'], $finder_message, $find['item_id']);
                             // Notify the loser about this specific found item
                             $loser_message = "A potentially matching found item ('" . escape($item_name) . "') was already in the system. Check item ID: " . $find['item_id'];
                             add_notification($pdo, $_SESSION['user_id'], $loser_message, $find['item_id']);
                         }
                     }
                 }

                 $_SESSION['flash_message'] = "Item reported successfully!";
                 $_SESSION['flash_type'] = "success";
                 redirect("index.php"); // Redirect after successful submission
            } else {
                $errors[] = "Database error: Could not save the item.";
            }
        } catch (PDOException $e) {
             // Log error properly in a real app: error_log($e->getMessage());
             $errors[] = "Database error: " . $e->getMessage(); // Show generic error in production
        }
    }
}

// Item categories
$categories = ['Electronics', 'Clothing', 'Keys', 'Books', 'Bags', 'Wallets', 'ID Cards', 'Other'];

?>

<?php include 'includes/header.php'; ?>

<h2>Report an Item</h2>

<?php if (!empty($errors)): ?>
    <div class="flash-error">
        <strong>Error!</strong> Please fix the following issues:
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo escape($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">

    <div>
        <label for="item_type">Did you lose or find this item? *</label>
        <select name="item_type" id="item_type" required onchange="toggleVerificationQuestion(this.value)">
            <option value="">-- Select --</option>
            <option value="lost" <?php echo ($item_type === 'lost') ? 'selected' : ''; ?>>I Lost an Item</option>
            <option value="found" <?php echo ($item_type === 'found') ? 'selected' : ''; ?>>I Found an Item</option>
        </select>
    </div>

    <div>
        <label for="item_name">Item Name *</label>
        <input type="text" name="item_name" id="item_name" value="<?php echo escape($item_name); ?>" required>
        <small>E.g., "Black iPhone 12", "Blue Jansport Backpack", "Silver Keyring"</small>
    </div>

    <div>
        <label for="category">Category *</label>
        <select name="category" id="category" required>
            <option value="">-- Select Category --</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo escape($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                    <?php echo escape($cat); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div>
        <label for="description">Description *</label>
        <textarea name="description" id="description" required><?php echo escape($description); ?></textarea>
        <small>Provide details like color, brand, specific markings, contents (if a bag), etc.</small>
    </div>

    <div>
        <label for="location">Location Lost/Found</label>
        <input type="text" name="location" id="location" value="<?php echo escape($location); ?>">
        <small>E.g., "Library 2nd Floor", "Near Cafeteria", "Room C101"</small>
    </div>

     <div>
        <label for="item_date">Date Lost/Found</label>
        <input type="date" name="item_date" id="item_date" value="<?php echo escape($item_date); ?>" max="<?php echo date('Y-m-d'); // Prevent future dates ?>">
    </div>

     <div>
        <label for="item_image">Upload Image (Optional)</label>
        <input type="file" name="item_image" id="item_image" accept="image/png, image/jpeg, image/gif">
        <small>Max file size: 5MB. Allowed types: JPG, PNG, GIF.</small>
    </div>

    <!-- Verification Question Field - Shown only for 'found' items -->
    <div id="verification_question_div" style="<?php echo ($item_type === 'found') ? 'display: block;' : 'display: none;'; ?>">
        <label for="verification_question">Verification Question (Optional)</label>
        <input type="text" name="verification_question" id="verification_question" value="<?php echo escape($verification_question); ?>">
        <small>Ask a question only the owner would know (e.g., "What brand is it?", "What's the specific scratch mark?"). Leave blank if unsure.</small>
    </div>


    <div>
        <button type="submit">Report Item</button>
    </div>

</form>

<script>
    // JavaScript to show/hide the verification question based on item type
    function toggleVerificationQuestion(type) {
        const verificationDiv = document.getElementById('verification_question_div');
        if (type === 'found') {
            verificationDiv.style.display = 'block';
        } else {
            verificationDiv.style.display = 'none';
            document.getElementById('verification_question').value = ''; // Clear value if hidden
        }
    }
    // Initialize on page load in case of form errors
    document.addEventListener('DOMContentLoaded', function() {
        const initialType = document.getElementById('item_type').value;
        toggleVerificationQuestion(initialType);
    });
</script>


<?php include 'includes/footer.php'; ?>