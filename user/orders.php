<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect page access routes
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Safety fallback: Ensure the orders table exists so the page never crashes
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT NOT NULL,
        user_id TEXT NOT NULL,
        timestamp INTEGER NOT NULL,
        status TEXT NOT NULL,
        items TEXT NOT NULL,
        shipping_address TEXT NOT NULL,
        payment_method TEXT NOT NULL,
        payment_phone TEXT NOT NULL
    )");
} catch (PDOException $e) {
    die("Database connectivity error: " . $e->getMessage());
}

$currentUserId = $_SESSION['user']['id'];

// --- HANDLE THE CANCEL ORDER REQUEST ACTIONS ---
if (isset($_GET['cancel_order_id'])) {
    $cancelId = $_GET['cancel_order_id'];
    
    // Update the status column to CANCELLED for this specific order and user directly in the database
    $updateStmt = $db->prepare("UPDATE orders SET status = 'CANCELLED' WHERE order_id = :order_id AND user_id = :user_id");
    $updateStmt->execute([
        ':order_id' => $cancelId,
        ':user_id'  => $currentUserId
    ]);
    
    // Force a cache-busting redirect so both mobile and desktop refresh completely
    header('Location: orders.php?refresh=' . time());
    exit();
}

// --- FETCH SYNCED ORDER DATA ---
// Grab all active orders for this user directly from the server's orders table
$stmt = $db->prepare("SELECT order_id, timestamp, status, items, shipping_address, payment_method, payment_phone FROM orders WHERE user_id = :user_id AND UPPER(status) != 'CANCELLED' ORDER BY timestamp DESC");
$stmt->execute([':user_id' => $currentUserId]);
$live_orders_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format the database rows so they perfectly match your original loop layout variables
$live_orders = [];
foreach ($live_orders_raw as $row) {
    $live_orders[] = [
        "order_id"         => $row['order_id'],
        "timestamp"        => (int)$row['timestamp'],
        "status"           => $row['status'],
        "items"            => json_decode($row['items'], true) ?? [], // Decodes the synchronized cart items
        "shipping_address" => $row['shipping_address'],
        "payment_method"   => $row['payment_method'],
        "payment_phone"    => $row['payment_phone']
    ];
}

// Load dishes from products.json for loop cross-referencing inside the HTML rendering lists
$productsFile = __DIR__ . '/../products.json'; 
$allProducts = [];
if (file_exists($productsFile)) {
    $allProducts = json_decode(file_get_contents($productsFile), true) ?? [];
}

include 'header.php';
?>

<div class="shop-orders-page-wrapper">
    <div class="shop-orders-main-container">

        <!-- Header Controls Panel Section -->
        <div class="shop-orders-header">
            <h2 class="orders-title">Your Order History</h2>
            <a href="cancelled.php" class="shop-view-cancelled-btn">
                View Cancelled Orders
            </a>
        </div>

        <!-- Render order banner alert confirmations when redirect hooks fire -->
        <?php if (isset($_GET["checkout_success"])): ?>
            <div class="shop-order-alert-success">
                Success! Your order (<?php echo htmlspecialchars($_GET["id"]); ?>) has been sent directly to the shop kitchen!
            </div>
        <?php endif; ?>

        <!-- Check our new safely filtered live orders array -->
        <?php if (!empty($live_orders)): ?>
            <div class="shop-orders-list-wrapper">
                <?php foreach ($live_orders as $order): ?>
                    <div class="shop-order-card">

                        <!-- Top Order Identification Info Summary Strip Banner block -->
                        <div class="shop-order-card-meta-strip">
                            <div>
                                <span class="meta-label">Order Code:</span>
                                <strong class="meta-value order-id-code"><?php echo htmlspecialchars($order["order_id"]); ?></strong>
                            </div>
                            <div>
                                <span class="meta-label">Date Placed:</span>
                                <strong class="meta-value"><?php echo date('M d, Y \a\t g:i a', $order["timestamp"]); ?></strong>
                            </div>
                            <div class="meta-action-group">
                                <span class="shop-status-badge status-<?php echo strtolower($order["status"]); ?>">
                                    <?php echo htmlspecialchars($order["status"]); ?>
                                </span>
                                <a href="orders.php?cancel_order_id=<?php echo urlencode($order["order_id"]); ?>" class="shop-cancel-order-trigger btn-order-cancel-intercept">
                                    Cancel Order
                                </a>
                            </div>
                        </div>

                        <!-- Inner Individual Plate Items Listing Layout rows block mapping array fields -->
                        <div class="shop-order-card-body">
                            <div class="shop-order-items-column">
                                <?php
                                $order_total = 0;
                                foreach ($order["items"] as $item_id => $qty):
                                    
                                    // Locate matching food object details from main json catalog definitions
                                    $matchedDish = null;
                                    foreach ($allProducts as $p) {
                                        if ((int)$p['id'] === (int)$item_id) {
                                            $matchedDish = $p;
                                            break;
                                        }
                                    }
                                    if (!$matchedDish) continue;

                                    $price = !empty($matchedDish['price']) ? (float)$matchedDish['price'] : 0.00;
                                    $subtotal = $price * $qty;
                                    $order_total += $subtotal;

                                    $thumb_url = '../' . $matchedDish['image'];
                                    if (empty($matchedDish['image']) || !file_exists(__DIR__ . '/../' . $matchedDish['image'])) {
                                        $thumb_url = 'data:image/svg+xml;utf8,<svg xmlns="http://w3.org" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="%23eaeaea"/></svg>';
                                    }
                                    ?>
                                    <div class="shop-order-item-row">
                                        <img src="<?php echo htmlspecialchars($thumb_url); ?>" class="shop-order-item-thumb" alt="Dish">
                                        <div class="shop-order-item-details">
                                            <strong class="item-title-text"><?php echo htmlspecialchars($matchedDish['name']); ?></strong>
                                            <div class="item-qty-text">x<?php echo (int)$qty; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="shop-order-total-column">
                                Grand Total Paid:
                                <span class="shop-grand-total-amount">
                                    KSh <?php echo number_format($order_total, 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="shop-empty-orders-view">
                <p class="empty-orders-notice">You haven't placed any kitchen food requests yet.</p>
                <a href="menu.php" class="shop-order-food-btn">
                    Order Delicious Food
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Ensure your central functions asset file is included -->
<script src="functions.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Safely pull the order tracking parameters directly out of the active URL string
    const urlParams = new URLSearchParams(window.location.search);
    const activeOrderId = urlParams.get('id') || '';
    const isJustCheckedOut = urlParams.get('checkout_success') || '';

    // Only fire the background loop if they just finished checking out and have a valid order ID
    if (isJustCheckedOut === "1" && activeOrderId !== "") {
        beginLiveOrderStatusPolling(activeOrderId);
    }
});
</script>

<?php include 'footer.php'; ?>
