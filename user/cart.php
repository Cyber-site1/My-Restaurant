<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce authentication strictly
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = $_SESSION['user']['id'];
$dbFile = __DIR__ . '/../database.sqlite';

try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connectivity error: " . $e->getMessage());
}

// Fetch user row record containing synchronized cart string definition
$stmt = $db->prepare("SELECT cart FROM users WHERE id = :id");
$stmt->execute([':id' => $currentUserId]);
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);

$cart = !empty($userRow['cart']) ? json_decode($userRow['cart'], true) : [];
if (!is_array($cart)) {
    $cart = [];
}

// --- HANDLE DISH QUANTITY ADJUSTMENT ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shop_update_cart_action'])) {
    $quantities = $_POST['cart_quantities'] ?? [];
    foreach ($quantities as $item_id => $qty) {
        $qty_int = (int)$qty;
        if ($qty_int >= 1 && $qty_int <= 20) {
            $cart[$item_id] = $qty_int;
        }
    }
    
    // Save back to database
    $updateStmt = $db->prepare("UPDATE users SET cart = :cart WHERE id = :id");
    $updateStmt->execute([':cart' => json_encode($cart), ':id' => $currentUserId]);
    
    header('Location: cart.php');
    exit; 
}

// --- HANDLE DISH REMOVAL PARAMETERS ---
if (isset($_GET['remove-cart-item'])) {
    $remove_id = $_GET['remove-cart-item']; 
    if (isset($cart[$remove_id])) {
        unset($cart[$remove_id]);
    }
    
    // Save back to database
    $updateStmt = $db->prepare("UPDATE users SET cart = :cart WHERE id = :id");
    $updateStmt->execute([':cart' => json_encode($cart), ':id' => $currentUserId]);
    
    header('Location: cart.php');
    exit; 
}

// Load live inventory array map from original JSON file repository reference
$productsFile = __DIR__ . '/../products.json';
$allProducts = [];
if (file_exists($productsFile)) {
    $allProducts = json_decode(file_get_contents($productsFile), true) ?? [];
}

// Cross-reference current basket entries with JSON inventory keys securely
$cartItems = [];
foreach ($allProducts as $product) {
    $pId = $product['id'];
    if (isset($cart[$pId])) {
        $cartItems[] = [
            'product' => $product,
            'quantity' => $cart[$pId]
        ];
    }
}

include 'header.php';
?>

<div class="me-responsive-cart-canvas">
    <div class="me-responsive-cart-container">
        
        <div class="me-app-cart-navbar">
            <a href="menu.php" class="me-app-back-arrow">←</a>
            <div class="me-app-navbar-center">
                <h1 class="me-app-title">Review Your Basket</h1>
                <span class="me-app-subtitle"><?php echo count($cartItems); ?> plates selected</span>
            </div>
            <div class="me-app-nav-spacer"></div>
        </div>

        <div class="me-desktop-cart-header">
            <h1 class="me-desktop-title">Your Food Basket</h1>
            <span class="me-desktop-subtitle"><?php echo count($cartItems); ?> dishes selected</span>
        </div>

        <?php if (!empty($cartItems)): ?>
            <form method="POST" action="cart.php" class="me-responsive-cart-form-container">
                <input type="hidden" name="shop_update_cart_action" value="1" />
                
                <div class="me-responsive-items-stream">
                    <?php
                    $grand_total = 0;
                    foreach ($cartItems as $basketNode):
                        $product = $basketNode['product'];
                        $quantity = $basketNode['quantity'];
                        $item_id = $product['id'];

                        $price = !empty($product['price']) ? (float)$product['price'] : 0.00;
                        $line_total = $price * $quantity;
                        $grand_total += $line_total;

                        $thumb_url = '../' . $product['image'];
                        if (empty($product['image']) || !file_exists(__DIR__ . '/../' . $product['image'])) {
                            $thumb_url = 'data:image/svg+xml;utf8,<svg xmlns="http://w3.org" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23eaeaea"/></svg>';
                        }
                        ?>
                        <div class="me-app-item-row">
                            <div class="me-app-thumb" style="background-image: url('<?php echo htmlspecialchars($thumb_url); ?>');"></div>
                            
                            <div class="me-app-info-lane">
                                <div class="me-app-title-price-group">
                                    <h3 class="me-app-item-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <span class="me-app-item-price">KSh <?php echo number_format($price, 2); ?></span>
                                </div>
                                
                                <div class="me-app-actions-subrow">
                                    <div class="me-app-pill-counter">
                                        <button type="button" class="me-pill-btn btn-qty-minus">−</button>
                                        <input type="number" name="cart_quantities[<?php echo $item_id; ?>]" value="<?php echo $quantity; ?>" min="1" max="20" class="me-pill-input input-qty-field" />
                                        <button type="button" class="me-pill-btn btn-qty-plus">+</button>
                                    </div>

                                    <div class="me-app-row-subtotal-text">
                                        Subtotal: <strong class="me-tomato-price">KSh <?php echo number_format($line_total, 2); ?></strong>
                                    </div>

                                    <a href="cart.php?remove-cart-item=<?php echo htmlspecialchars($item_id); ?>" class="me-app-inline-delete">Remove</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="me-responsive-billing-summary-drawer">
                    <div class="me-drawer-price-summary-line">
                        <span class="me-drawer-label">Basket Total Due:</span>
                        <span class="me-drawer-grand-total">KSh <?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    <p class="me-drawer-subtext-notice">Fresh preparation begins the second payment authentication clears.</p>
                    <button type="submit" class="me-app-hidden-submit-trigger">Recalculate Totals</button>
                    <a href="checkout.php" class="me-app-primary-checkout-cta">
                        Proceed to Payment (KSh <?php echo number_format($grand_total, 2); ?>)
                    </a>
                </div>
            </form>
        <?php else: ?>
            <div class="me-app-empty-state">
                <div class="me-app-empty-icon-pulse">🍲</div>
                <h2 class="me-app-empty-title">Your basket is empty</h2>
                <p class="me-app-empty-text">Add your favorite meals from our restaurant menu catalog to start your kitchen delivery request.</p>
                <a href="menu.php" class="me-app-empty-shop-cta">Start Exploring Menu</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
