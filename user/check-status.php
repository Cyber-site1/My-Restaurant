<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

// 1. Block unauthorized access if the customer is logged out
if (!isset($_SESSION['user'])) {
    echo json_encode(["status" => "ERROR", "message" => "Unauthorized access"]);
    exit();
}

$orderId = $_GET['order_id'] ?? '';

// 2. Reject the request if no order ID was passed into the background check
if (empty($orderId)) {
    echo json_encode(["status" => "ERROR", "message" => "Missing order ID parameter"]);
    exit();
}

try {
    // 3. Connect to the database file sitting up one level in the admin-dashboard folder
    $db = new PDO('sqlite:../database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 4. Query the live status of the order matching the ID
    $stmt = $db->prepare("SELECT status FROM orders WHERE order_id = :order_id LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. Send the exact database status string back to the JavaScript loop
    if ($order) {
        echo json_encode(["status" => $order['status']]);
    } else {
        echo json_encode(["status" => "NOT_FOUND"]);
    }

} catch (PDOException $e) {
    // Gracefully handle database locking or connectivity glitches
    echo json_encode(["status" => "ERROR", "message" => "Database breakdown"]);
}
