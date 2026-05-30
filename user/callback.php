<?php
header("Content-Type: application/json");

// Capture the incoming raw server notification package payload from Safaricom API
$stkCallbackResponse = file_get_contents('php://input');

// Log raw data for immediate verification / payment tracking audits
$logFile = __DIR__ . "/../MpesaSTKResponse.txt";
$log = fopen($logFile, "a");
fwrite($log, $stkCallbackResponse . PHP_EOL);
fclose($log);

$data = json_decode($stkCallbackResponse, true);

if (!$data) {
    echo json_encode(["ResultCode" => 1, "ResultDesc" => "Invalid JSON data"]);
    exit;
}

$callbackData = $data['Body']['stkCallback'] ?? null;
if (!$callbackData) {
    echo json_encode(["ResultCode" => 1, "ResultDesc" => "Missing callback data shell"]);
    exit;
}

$resultCode        = $callbackData['ResultCode'];
$checkoutRequestID = $callbackData['CheckoutRequestID'] ?? '';

// ResultCode 0 explicitly guarantees customer entered correct pin and payment left phone
if ($resultCode == 0) {
    $callbackMetadata = $callbackData['CallbackMetadata']['Item'] ?? [];
    $amount = 0; $mpesaCode = ""; $phone = ""; $date = "";

    foreach ($callbackMetadata as $item) {
        switch ($item['Name']) {
            case 'Amount': $amount = $item['Value'] ?? 0; break;
            case 'MpesaReceiptNumber': $mpesaCode = $item['Value'] ?? ""; break;
            case 'PhoneNumber': $phone = $item['Value'] ?? ""; break;
            case 'TransactionDate': $date = $item['Value'] ?? ""; break;
        }
    }

    try {
        // FIXED DATABASE PATH: Points to the identical parent asset path used in checkout.php
        // Forces the system to find the file relative to the script's exact folder
        $db = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Update the order status using the unique tracking ID passed down by Safaricom
        $stmt = $db->prepare("UPDATE orders SET status = 'Paid', mpesa_code = :mpesa, payment_date = :pdate WHERE checkout_id = :checkout_id");
        $stmt->execute([
            ':mpesa'        => htmlspecialchars($mpesaCode),
            ':pdate'        => htmlspecialchars($date),
            ':checkout_id'  => htmlspecialchars($checkoutRequestID)
        ]);

    } catch (PDOException $e) {
        error_log("Database callback processing error: " . $e->getMessage());
    }
} else {
    try {
        // If the customer cancelled or entered the wrong pin, flag order details accordingly
        // Forces the system to find the file relative to the script's exact folder
        $db = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("UPDATE orders SET status = 'CANCELLED_BY_USER' WHERE checkout_id = :checkout_id");
        $stmt->execute([':checkout_id' => htmlspecialchars($checkoutRequestID)]);
    } catch (PDOException $e) {
        error_log("Database cancellation tracking error: " . $e->getMessage());
    }
}

// Signal back to Safaricom endpoint execution loop that system logged receipt successfully
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);
