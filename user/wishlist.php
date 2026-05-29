<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect page access routes: redirect guest accounts to login page if signed out
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Connect to your secure SQLite database to handle user records
$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connectivity error.");
}

$currentUserId = $_SESSION['user']['id'];

// Securely fetch your wishlist string data directly from the active user's database record
$stmt = $db->prepare("SELECT wishlist FROM users WHERE id = :id");
$stmt->execute([':id' => $currentUserId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    header('Location: logout.php');
    exit();
}

// Convert the saved database text back into a clean PHP array list
$wishlist_ids = !empty($userRow['wishlist']) ? json_decode($userRow['wishlist'], true) : [];
if (!is_array($wishlist_ids)) {
    $wishlist_ids = [];
}

// --- HANDLE DIRECT WISHLIST REMOVAL PARAMETERS ---
if (isset($_GET['remove-from-wishlist'])) {
    $removeWishId = (int)$_GET['remove-from-wishlist'];
    
    if (in_array($removeWishId, $wishlist_ids)) {
        $wishlist_ids = array_diff($wishlist_ids, [$removeWishId]);
        $wishlist_ids = array_values($wishlist_ids);
        
        // Save the updated list back to the SQLite users table
        $updateWishStmt = $db->prepare("UPDATE users SET wishlist = :wishlist WHERE id = :id");
        $updateWishStmt->execute([
            ':wishlist' => json_encode($wishlist_ids),
            ':id' => $currentUserId
        ]);
        
        // Update the active session record immediately
        if (isset($_SESSION['user']['wishlist'])) {
            $_SESSION['user']['wishlist'] = $wishlist_ids;
        }
    }
    header('Location: wishlist.php?refresh=' . time());
    exit();
}

// --- SYNCHRONIZED ADD TO CART FROM WISHLIST (STAYS ON THIS PAGE) ---
if (isset($_GET['add-to-cart'])) {
    $addId = $_GET['add-to-cart'];
    
    // Fetch current synchronized cart from database
    $stmt = $db->prepare("SELECT cart FROM users WHERE id = :id");
    $stmt->execute([':id' => $currentUserId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $syncCart = !empty($userRow['cart']) ? json_decode($userRow['cart'], true) : [];
    if (!is_array($syncCart)) {
        $syncCart = [];
    }

    if (isset($syncCart[$addId])) {
        $syncCart[$addId]++;
    } else {
        $syncCart[$addId] = 1;
    }
    
    // Save updated cart array back into SQLite user records
    $updateStmt = $db->prepare("UPDATE users SET cart = :cart WHERE id = :id");
    $updateStmt->execute([
        ':cart' => json_encode($syncCart),
        ':id' => $currentUserId
    ]);
    
    // Flag the success message to trigger your custom built-in JavaScript toast bar
    $_SESSION['cart_message'] = "Item added in cart";
    
    header('Location: wishlist.php');
    exit();
}

// Load dishes from the products.json file
$productsFile = __DIR__ . '/../products.json';
$allProducts = [];
if (file_exists($productsFile)) {
    $allProducts = json_decode(file_get_contents($productsFile), true) ?? [];
}

include 'header.php';
?>

<?php if (isset($_SESSION['cart_message'])): ?>
    <script>window.showWishlistNotification = true;</script>
    <?php unset($_SESSION['cart_message']); ?>
<?php endif; ?>

<div class="wishlist-page-layout">
    <div class="wishlist-content-container">
        
        <h2 class="wishlist-section-heading">
            Your Wish List Items
        </h2>

        <?php 
        // Filter and cross-reference saved arrays against live repository definitions
        $matchedItems = [];
        if (!empty($wishlist_ids) && is_array($wishlist_ids)) {
            foreach ($allProducts as $product) {
                if (in_array((int)$product['id'], array_map('intval', $wishlist_ids))) {
                    $matchedItems[] = $product;
                }
            }
        }

        if (!empty($matchedItems)): 
        ?>
            <div class="wishlist-modern-container">

                <?php
                foreach ($matchedItems as $item):
                    $dish_id = $item['id'];
                    $price = !empty($item['price']) ? (float)$item['price'] : 0.00;
                    
                    $image_url = '../' . $item['image'];
                    if (empty($item['image']) || !file_exists(__DIR__ . '/../' . $item['image'])) {
                        $image_url = 'data:image/svg+xml;utf8,<svg xmlns="http://w3.org" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23eaeaea"/></svg>';
                    }
                    ?>
                    <div class="wishlist-modern-card">
                        
                        <div class="wishlist-card-thumbnail" style="background-image: url('<?php echo htmlspecialchars($image_url); ?>');">
                            <span class="menu-card-price-tag">KSh <?php echo number_format($price, 2); ?></span>
                        </div>

                        <div class="wishlist-card-details">
                            <h3 class="wishlist-card-title"><?php echo htmlspecialchars($item['name'] ?? $item['title'] ?? 'Unnamed Dish'); ?></h3>
                            
                            <div class="wishlist-stars-row">
                                <span class="menu-card-stars">★★★★★</span>
                            </div>

                            <div class="wishlist-card-excerpt">
                                <?php echo htmlspecialchars($item['desc'] ?? $item['description'] ?? ''); ?>
                            </div>
                        </div>

                        <div class="wishlist-card-actions">
                            <!-- 🛠️ FIXED: Point the link to wishlist.php instead of menu.php -->
                            <a href="wishlist.php?add-to-cart=<?php echo htmlspecialchars($dish_id); ?>" class="wishlist-btn-cart">
                                Add to Cart
                            </a>
                            <a href="wishlist.php?remove-from-wishlist=<?php echo htmlspecialchars($dish_id); ?>" class="wishlist-btn-remove" title="Remove item">
                                Remove
                            </a>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="wishlist-empty-text">Your wish list is currently empty. Start exploring our restaurant menu catalog to add your favorite dishes!</p>
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
