<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect page access routes safely
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Connect to your secure SQLite database
$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connectivity error: " . $e->getMessage());
}

$currentUserId = $_SESSION['user']['id'];

// --- UPGRADED REAL-TIME RELATIONAL DATABASE LOADER ---
try {
    // Fetch all cancelled items linked to the customer directly from the orders table row registry
    $stmt = $db->prepare("SELECT * FROM orders WHERE user_id = :uid AND (status = 'CANCELLED_BY_USER' OR status = 'CANCELLED' OR status = 'FAILED_STK_PUSH') ORDER BY timestamp DESC");
    $stmt->execute([':uid' => $currentUserId]);
    $rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database transaction lookup error: " . $e->getMessage());
}

$order_history = [];
foreach ($rawOrders as $row) {
    // Standardize your key descriptors map arrays to match your front-end template execution bindings
    $order_history[] = [
        'order_id'         => $row['order_id'],
        'timestamp'        => (int)$row['timestamp'],
        'status'           => 'CANCELLED', // Force evaluation flag to CANCELLED for template matching
        'items'            => json_decode($row['items'], true) ?? [], // Re-inflate string entries back into clean arrays
        'shipping_address' => $row['shipping_address'],
        'payment_method'   => $row['payment_method'],
        'payment_phone'    => $row['payment_phone']
    ];
}

// --- SECURE SQL DELETE CANCELLED ORDERS ACTION ---
if (isset($_GET['action']) && $_GET['action'] === 'delete_cancelled' && isset($_GET['orders'])) {
    $ordersToDelete = explode(',', $_GET['orders']);
    $deletedCount = 0;

    foreach ($ordersToDelete as $oId) {
        // Permanently remove target row matching unique tracking indicators directly from the database table
        $delStmt = $db->prepare("DELETE FROM orders WHERE order_id = :oid AND user_id = :uid");
        $delStmt->execute([
            ':oid' => trim($oId),
            ':uid' => $currentUserId
        ]);
        $deletedCount += $delStmt->rowCount();
    }
    
    header('Location: cancelled.php?deleted=' . $deletedCount);
    exit();
} 

$productsFile = __DIR__ . '/../products.json';
$allProducts = [];
if (file_exists($productsFile)) {
    $allProducts = json_decode(file_get_contents($productsFile), true) ?? [];
}

include 'header.php';
?>

<div class="shop-cancelled-page-wrapper">
    <div class="shop-cancelled-main-container">

        <!-- Header -->
        <div class="shop-cancelled-header">
            <h2 class="cancelled-title">Cancelled Orders</h2>
            <a href="orders.php" class="shop-back-orders-btn">
                Back To Orders
            </a>
        </div>
		
        <?php if (isset($_GET["deleted"])): ?>
            <?php $deleted_count = (int)$_GET["deleted"]; ?>
            <div class="shop-cancelled-alert-success">
                <?php if ($deleted_count === 1): ?>
                    Cancelled order deleted successfully.
                <?php else: ?>
                    Cancelled orders deleted successfully.
                <?php endif; ?>
            </div>
        <?php endif; ?>
		
        <!-- Delete Bar -->
        <div id="shopDeleteBar" class="shop-delete-action-bar">
            <button type="button" class="shop-bulk-delete-btn btn-bulk-delete-trigger">
                Delete Selected Orders
            </button>
        </div>

        <div class="shop-cancelled-list-wrapper">

            <?php
            $cancelled_orders_found = false;
            if (!empty($order_history) && is_array($order_history)) {
                foreach ($order_history as $order) {
                    if (strtoupper($order["status"]) !== "CANCELLED") {
                        continue;
                    }
                    $cancelled_orders_found = true;
                    ?>
                    <div class="shop-cancelled-card" data-order-id="<?php echo htmlspecialchars($order["order_id"]); ?>">

                        <!-- Header Strip -->
                        <div class="shop-cancelled-card-meta-strip">
                            <div>
                                <span class="meta-label">Order Code:</span>
                                <strong class="meta-value order-id-code"><?php echo htmlspecialchars($order["order_id"]); ?></strong>
                            </div>
                            <div>
                                <span class="meta-label">Date Placed:</span>
                                <strong class="meta-value"><?php echo date('M d, Y \a\t g:i a', $order["timestamp"]); ?></strong>
                            </div>
                            <div class="meta-action-group">
                                <input type="checkbox" class="shopOrderCheckbox check-order-row-item" value="<?php echo htmlspecialchars($order["order_id"]); ?>">
                                <span class="shop-status-badge status-cancelled">CANCELLED</span>
                            </div>
                        </div>

                        <!-- Items -->
                        <div class="shop-cancelled-card-body">
                            <div class="shop-cancelled-items-column">
                                <?php
                                $order_total = 0;
                                foreach ($order["items"] as $item_id => $qty):
                                    
                                    // Cross reference food descriptors map array
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
                                    <div class="shop-cancelled-item-row">
                                        <img src="<?php echo htmlspecialchars($thumb_url); ?>" class="shop-cancelled-item-thumb" alt="Dish">
                                        <div class="shop-cancelled-item-details">
                                            <strong class="item-title-text"><?php echo htmlspecialchars($matchedDish['name']); ?></strong>
                                            <div class="item-qty-text">x<?php echo (int)$qty; ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="shop-cancelled-total-column">
                                Cancelled Order Value:
                                <span class="shop-cancelled-total-amount">
                                    KSh <?php echo number_format($order_total, 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div> <!-- End .shop-cancelled-list-wrapper -->

        <?php if (!$cancelled_orders_found): ?>
            <div class="shop-empty-cancelled-view">
                <p class="empty-cancelled-notice">No cancelled orders found.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Include your separate external scripts warehouse bundle file -->
<script src="functions.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Run the validation attachment loop loaded from functions.js
    initializeCheckoutPhoneValidator();
});
</script>

<?php include 'footer.php'; ?>
