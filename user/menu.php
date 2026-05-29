<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("Database connectivity error: " . $e->getMessage());
}

$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

// Fetch user wishlist from SQLite to show hearts correctly
$stmt = $db->prepare("SELECT wishlist FROM users WHERE id = :id");
$stmt->execute([':id' => $currentUserId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);
$userWishlist = !empty($userRow['wishlist']) ? json_decode($userRow['wishlist'], true) : [];

// Load dishes from your original JSON file
$productsFile = __DIR__ . '/../products.json';
$menuItems = [];
if (file_exists($productsFile)) {
    $menuItems = json_decode(file_get_contents($productsFile), true) ?? [];
}

// --- WISHLIST SUBMISSION TOGGLE LISTENER HOOK ---
if (isset($_GET['toggle-wishlist'])) {
    $wishId = (int)$_GET['toggle-wishlist'];
    
    // Check if the current ID is already in the user's wishlist array
    if (in_array($wishId, $userWishlist)) {
        // If it exists, remove it
        $userWishlist = array_diff($userWishlist, [$wishId]);
    } else {
        // If it doesn't exist, add it
        $userWishlist[] = $wishId;
    }
    
    // Clean and reset array keys
    $userWishlist = array_values($userWishlist);
    
    // Update the encrypted/encoded text inside the SQLite database users table
    $updateStmt = $db->prepare("UPDATE users SET wishlist = :wishlist WHERE id = :id");
    $updateStmt->execute([
        ':wishlist' => json_encode($userWishlist),
        ':id' => $currentUserId
    ]);
    
    // Update the active session immediately so headers match across device boundaries
    if (isset($_SESSION['user'])) {
        $_SESSION['user']['wishlist'] = $userWishlist;
    }
    
    // Force a clean reload to wipe out stale device session cache records
    header('Location: menu.php?refresh=' . time());
    exit();
}

// --- SYNCHRONIZED BASKET SUBMISSION QUERY LISTENER HOOK ---
if (isset($_GET['add-to-cart'])) {
    $addId = $_GET['add-to-cart'];
    
    // Fetch current user's synchronized cart from database
    $stmt = $db->prepare("SELECT cart FROM users WHERE id = :id");
    $stmt->execute([':id' => $currentUserId]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $syncCart = !empty($userRow['cart']) ? json_decode($userRow['cart'], true) : [];
    if (!is_array($syncCart)) {
        $syncCart = [];
    }

    // Update quantity counter parameters
    if (isset($syncCart[$addId])) {
        $syncCart[$addId]++;
    } else {
        $syncCart[$addId] = 1;
    }
    
    // Save synchronized basket array back into SQLite user profile column
    $updateStmt = $db->prepare("UPDATE users SET cart = :cart WHERE id = :id");
    $updateStmt->execute([
        ':cart' => json_encode($syncCart),
        ':id' => $currentUserId
    ]);
    
    // Route workflow directly to the checkout summary page layout view
    header('Location: cart.php');
    exit();
}

include 'header.php'; 
?>

<!-- HTML Toast Banner Notification Template Container -->
<div id="shopToastContainer" class="toast-notification-container" style="display: none !important;">
    <div class="toast-notification-card">
        <span class="toast-emoji">🛒</span>
        <div class="toast-text-content">
            <strong class="toast-title">Item Added!</strong>
            <p class="toast-message">The plate was saved to your basket.</p>
        </div>
    </div>
</div>

<!-- CATEGORY BAR PANEL -->
<div id="shopCategoryPanel" class="category-panel-container" style="position: fixed !important; left: 0px !important; right: 0px !important; width: 100vw !important; background: #f5f5f5 !important; z-index: 1000 !important; padding: 12px 20px !important; border-radius: 0px !important; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); box-sizing: border-box !important; display: flex; gap: 12px; overflow-x: auto; scrollbar-width: none;">
    <button class="category-tab-btn active-tab" onclick="switchCategory('All')">All Dishes</button>
    <button class="category-tab-btn" onclick="switchCategory('Breakfast')">Breakfast</button>
    <button class="category-tab-btn" onclick="switchCategory('Main Course')">Main Course</button>
    <button class="category-tab-btn" onclick="switchCategory('Drinks')">Drinks</button>
</div>

<div class="shop-master-canvas" style="background-color: #f5f5f5; min-height: auto; padding: 135px 20px 40px 20px; font-family: Arial, sans-serif; box-sizing: border-box;">
    <div class="menu-page-wrapper" style="max-width: 1300px; margin: 0 auto; display: flex; gap: 40px; align-items: flex-start;">

        <!-- LEFT SIDEBAR PANEL -->
        <aside class="desktop-price-aside" style="flex: 1; min-width: 300px; background: #ffffff !important; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: sticky; top: 120px; min-height: auto; box-sizing: border-box; display: flex; flex-direction: row; justify-content: space-between; gap: 20px;">
            <div style="flex-grow: 1;">
                <div style="border-bottom: 2px solid #333333; padding-bottom: 6px; margin-bottom: 25px;">
                    <h2 style="color: #000000; font-weight: bold; margin: 0; font-size: 22px; text-transform: uppercase; letter-spacing: 0.5px;">Price Filter</h2>
                </div>
                <div style="margin-bottom: 35px;">
                    <div style="display: flex; justify-content: space-between; font-weight: bold; color: #333; margin-bottom: 12px; font-size: 14px;">
                        <span>Min: KSh 50</span>
                        <span style="color: tomato; font-weight: bold;">Max: KSh <span id="sliderValue">3000</span></span>
                    </div>
                    <input type="range" id="priceRangeSlider" min="50" max="3000" value="3000" step="50" oninput="filterMenuByPrice(this.value)" style="width: 100%; accent-color: #222222; cursor: pointer; background: tomato; height: 6px; border-radius: 3px; appearance: none; outline: none;">
                </div>
                <div id="verticalFeaturedContainer" style="display: flex; flex-direction: column; gap: 20px;"></div>
            </div>
            <div style="width: 2px; background-color: #555555; border-radius: 10px; align-self: stretch; margin-left: 20px;"></div>
        </aside>
		
        <!-- RIGHT MAIN PANEL -->
        <main class="storefront-main-grid" style="flex: 3; min-width: 600px; display: flex; flex-direction: column; gap: 30px;">
            <div style="display: flex; width: 100%; max-width: 600px; border: 2px solid tomato; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); background: #ffffff;">
                <input type="text" id="menuSearchInput" onkeyup="searchMenuItems()" placeholder="Search dishes..." style="flex-grow: 1; padding: 14px 20px; background-color: #eaeaea; border: none; font-size: 15px; color: #333; outline: none; font-family: Arial;">
                <button type="button" style="background-color: tomato; color: #ffffff; border: none; padding: 0 30px; font-weight: bold; font-size: 15px; cursor: pointer; font-family: Arial; text-transform: uppercase;">Search</button>
            </div>
            
            <div id="storefrontMenuGrid" class="storefront-product-matrix" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; grid-auto-rows: max-content;">
                <?php
                if (!empty($menuItems)):
                    foreach ($menuItems as $item):
                        include 'menu_item.php';
                    endforeach;
                else:
                    echo '<p style="grid-column: span 3; text-align: center; color: #999; padding: 40px; font-family: Arial;">No dishes found.</p>';
                endif;
                ?>
            </div>

            <div class="pagination-footer-wrapper" style="display: flex; justify-content: flex-end; align-items: center; margin-top: 10px; gap: 15px;">
                <button type="button" id="prevPageBtn" onclick="changeActivePage(-1)" class="pag-nav-arrow" disabled style="background: #ffffff; border: 1px solid #ddd; border-radius: 6px; padding: 10px 16px; font-weight: bold; cursor: pointer; color: #333; opacity: 0.5; transition: 0.2s;">←</button>
                <span id="paginationPageLabel" style="font-size: 14px; font-weight: bold; color: #555;">Page 1 of 1</span>
                <button type="button" id="nextPageBtn" onclick="changeActivePage(1)" class="pag-nav-arrow" disabled style="background: #ffffff; border: 1px solid #ddd; border-radius: 6px; padding: 10px 16px; font-weight: bold; cursor: pointer; color: #333; opacity: 0.5; transition: 0.2s;">→</button>
            </div>
        </main>
    </div>
</div>

<script src="functions.js"></script>

<!-- ================= 📱 USER DISH REVIEW POPUP MODAL CANVAS ================= -->
<div id="dishReviewPopupModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; justify-content:center; align-items:center; padding:15px; box-sizing:border-box; font-family:Arial, sans-serif;">
    <div style="background:#fff; width:100%; max-width:520px; border-radius:12px; padding:24px; box-sizing:border-box; display:flex; flex-direction:column; max-height:85vh; position:relative; box-shadow:0 10px 25px rgba(0,0,0,0.2);">
        
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #edf2f7; padding-bottom:12px; margin-bottom:16px;">
            <h3 id="modalDishTitleName" style="margin:0; font-size:18px; color:#1a202c; font-weight:bold;">Dish Reviews</h3>
            <button onclick="closeDishReviewPopup()" style="background:none; border:none; font-size:24px; color:#a0aec0; cursor:pointer; line-height:1; padding:0; outline:none;">&times;</button>
        </div>

        <div id="modalReviewsScrollFeed" style="flex-grow:1; overflow-y:auto; margin-bottom:20px; padding-right:5px; display:flex; flex-direction:column; gap:14px;"></div>

        <div style="border-top:1px solid #edf2f7; padding-top:16px;">
            <h4 style="margin:0 0 10px 0; font-size:14px; color:#4a5568; font-weight:bold;">Add Your Rating</h4>
            
            <div style="display:flex; gap:6px; flex-direction:row-reverse; justify-content:flex-end; margin-bottom:12px;">
                <input type="radio" id="mstar5" name="modal_rating" value="5" style="display:none;"><label for="mstar5" class="modal-star-node" style="font-size:26px; color:#ccc; cursor:pointer; user-select:none;">★</label>
                <input type="radio" id="mstar4" name="modal_rating" value="4" style="display:none;"><label for="mstar4" class="modal-star-node" style="font-size:26px; color:#ccc; cursor:pointer; user-select:none;">★</label>
                <input type="radio" id="mstar3" name="modal_rating" value="3" style="display:none;"><label for="mstar3" class="modal-star-node" style="font-size:26px; color:#ccc; cursor:pointer; user-select:none;">★</label>
                <input type="radio" id="mstar2" name="modal_rating" value="2" style="display:none;"><label for="mstar2" class="modal-star-node" style="font-size:26px; color:#ccc; cursor:pointer; user-select:none;">★</label>
                <input type="radio" id="mstar1" name="modal_rating" value="1" style="display:none;"><label for="mstar1" class="modal-star-node" style="font-size:26px; color:#ccc; cursor:pointer; user-select:none;">★</label>
            </div>

            <div style="display:flex; gap:10px; align-items:stretch;">
                <textarea id="modalReviewTextInput" rows="2" placeholder="Write your dining thoughts..." style="flex-grow:1; padding:10px; border:1px solid #cbd5e0; border-radius:6px; font-size:13px; resize:none; font-family:inherit; outline:none; box-sizing:border-box;"></textarea>
                <button onclick="submitModalReviewForm()" style="background:tomato; color:#fff; border:none; padding:0 20px; font-weight:bold; border-radius:6px; cursor:pointer; font-size:13px; text-transform:uppercase;">Send</button>
            </div>
        </div>

    </div>
</div>
<?php include 'footer.php'; ?>
