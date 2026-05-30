<?php
// Include your database connection setup here
$db = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$orderId = $_GET['order_id'] ?? '';
$mpesaReceipt = 'NOT AVAILABLE';

if (!empty($orderId)) {
    // 🚀 MATCHED COLUMNS: Changed from mpesa_receipt to mpesa_code
    $stmt = $db->prepare("SELECT mpesa_code FROM orders WHERE order_id = :order_id LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order && !empty($order['mpesa_code'])) {
        $mpesaReceipt = $order['mpesa_code'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body { text-align: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 50px 20px; background: #f4f6f8; color: #333; }
        .card { background: white; padding: 40px; border-radius: 12px; display: inline-block; max-width: 450px; width: 100%; box-shadow: 0 4px 15px rgba(0,0,0,0.06); box-sizing: border-box; }
        .icon { font-size: 60px; color: #2ecc71; margin-bottom: 15px; }
        h1 { color: #2c3e50; margin: 0 0 10px 0; font-size: 24px; }
        p { color: #7f8c8d; font-size: 15px; margin: 5px 0 20px 0; }
        .details-box { background: #f8f9fa; border: 1px solid #eec; padding: 15px; border-radius: 8px; text-align: left; margin-bottom: 25px; }
        .details-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px; }
        .details-row:last-child { margin-bottom: 0; }
        .label { color: #95a5a6; font-weight: 50px; }
        .value { color: #2c3e50; font-weight: bold; font-family: monospace; font-size: 15px; }
        .btn { display: block; background: #2ecc71; color: white; padding: 12px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px; transition: background 0.2s; }
        .btn:hover { background: #27ae60; }
    </style>
</head>
<body>

    <div class="card">
        <div class="icon">✓</div>
        <h1>Payment Successful!</h1>
        <p>Your food basket order has been confirmed.</p>
        
        <div class="details-box">
            <div class="details-row">
                <span class="label">Order ID:</span>
                <span class="value"><?php echo htmlspecialchars($orderId); ?></span>
            </div>
            <div class="details-row">
                <span class="label">M-Pesa Receipt:</span>
                <span class="value" style="color: #2ecc71;"><?php echo htmlspecialchars($mpesaReceipt); ?></span>
            </div>
        </div>

        <!-- 🚀 Updated destination route -->
        <a href="user/menu.php" class="btn">Continue Shopping</a>
    </div>

</body>
</html>