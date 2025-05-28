<?php
// index.php - Main display page
require_once 'includes/header.php'; // Includes db, functions, session start

// --- Search and Filter Logic ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : ''; // 'lost' or 'found'

// Base SQL query
$sql = "SELECT i.item_id, i.item_type, i.item_name, i.category, i.location, i.item_date, i.image_path, i.status, u.username AS reporter_username
        FROM items i
        JOIN users u ON i.user_id = u.user_id
        WHERE i.status = 'reported' "; // Only show items not claimed/closed

// Build WHERE clause dynamically and safely
$params = [];
$where_clauses = [];

if (!empty($filter_type)) {
    $where_clauses[] = "i.item_type = :type";
    $params[':type'] = $filter_type;
}

if (!empty($filter_category)) {
    $where_clauses[] = "i.category = :category";
    $params[':category'] = $filter_category;
}

// Full-text search if term is provided
if (!empty($search_term)) {
     // Note: Needs FULLTEXT index on item_name, description, location
     $where_clauses[] = "MATCH(i.item_name, i.description, i.location) AGAINST (:search IN NATURAL LANGUAGE MODE)";
     // Simple LIKE search (alternative if FULLTEXT is not set up or preferred)
     // $where_clauses[] = "(i.item_name LIKE :search OR i.description LIKE :search OR i.location LIKE :search)";
     $params[':search'] = $search_term; // For FULLTEXT
     // $params[':search'] = '%' . $search_term . '%'; // For LIKE
}

if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY i.reported_at DESC"; // Show newest first

// Prepare and execute the query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "<div class='flash-error'>Error fetching items: " . escape($e->getMessage()) . "</div>"; // Show error in dev, log in prod
    $items = []; // Prevent errors later
}

// Get categories for the filter dropdown
$categories_sql = "SELECT DISTINCT category FROM items ORDER BY category ASC";
$cat_stmt = $pdo->query($categories_sql);
$all_categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<h2>Lost & Found Items</h2>

<!-- Search and Filter Form -->
<form action="index.php" method="GET" class="filter-form">
    <div class="filter-controls">
        <div>
            <label for="search">Search:</label>
            <input type="text" name="search" id="search" placeholder="Keywords (name, description, location)" value="<?php echo escape($search_term); ?>">
        </div>
        <div>
            <label for="type">Type:</label>
            <select name="type" id="type">
                <option value="">All Types</option>
                <option value="lost" <?php echo ($filter_type === 'lost') ? 'selected' : ''; ?>>Lost</option>
                <option value="found" <?php echo ($filter_type === 'found') ? 'selected' : ''; ?>>Found</option>
            </select>
        </div>
         <div>
            <label for="category">Category:</label>
            <select name="category" id="category">
                <option value="">All Categories</option>
                 <?php foreach ($all_categories as $cat): ?>
                    <option value="<?php echo escape($cat); ?>" <?php echo ($filter_category === $cat) ? 'selected' : ''; ?>>
                        <?php echo escape($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit">Filter / Search</button>
            <a href="index.php" class="button button-secondary">Clear Filters</a>
        </div>
    </div>
</form>
<style>
    .filter-form .filter-controls { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;}
    .filter-form .filter-controls div { flex: 1 1 180px; } /* Flexible width for controls */
    .filter-form label { margin-bottom: 3px; font-size: 0.9em; }
    .filter-form input, .filter-form select { width: 100%; padding: 8px; }
    .filter-form button, .filter-form a.button { padding: 9px 15px; }
</style>

<!-- Item Listing -->
<div class="item-list">
    <?php if (count($items) > 0): ?>
        <?php foreach ($items as $item): ?>
            <div class="item-card item-<?php echo escape($item['item_type']); ?>">
                <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                    <img src="<?php echo escape($item['image_path']); ?>" alt="<?php echo escape($item['item_name']); ?>">
                <?php else: ?>
                     <img src="images/placeholder.png" alt="No image available"> <!-- Optional: Create a placeholder image -->
                <?php endif; ?>

                <h3><?php echo escape($item['item_name']); ?> (<?php echo ucfirst(escape($item['item_type'])); ?>)</h3>
                <p><strong>Category:</strong> <?php echo escape($item['category']); ?></p>
                <?php if (!empty($item['location'])): ?>
                     <p><strong>Location:</strong> <?php echo escape($item['location']); ?></p>
                <?php endif; ?>
                 <?php if (!empty($item['item_date'])): ?>
                     <p><strong>Date:</strong> <?php echo date("M j, Y", strtotime($item['item_date'])); ?></p>
                <?php endif; ?>
                 <p><small>Reported by: <?php echo escape($item['reporter_username']); ?></small></p>

                <div class="item-actions">
                    <a href="item_details.php?id=<?php echo $item['item_id']; ?>" class="button">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No items found matching your criteria.</p>
    <?php endif; ?>
</div>


<?php include 'includes/footer.php'; ?>