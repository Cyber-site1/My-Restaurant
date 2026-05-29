<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect guest accounts to login page if signed out
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$currentUserId = $_SESSION['user']['id'];

// Load your products array from the root folder into the $allProducts variable
$productsJsonPath = __DIR__ . '/../products.json';
$allProducts = file_exists($productsJsonPath) ? json_decode(file_get_contents($productsJsonPath), true) : [];

try {
    // Connect to your existing SQLite database file
    $db = new PDO('sqlite:../database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // AUTOMATIC TABLE CREATOR: Safely builds or upgrades the orders table
    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT NOT NULL,
        user_id TEXT NOT NULL,
        timestamp INTEGER NOT NULL,
        status TEXT NOT NULL,
        items TEXT NOT NULL,
        shipping_address TEXT NOT NULL,
        payment_method TEXT NOT NULL,
        payment_phone TEXT NOT NULL,
        checkout_id TEXT,
        mpesa_code TEXT,
        payment_date TEXT
    )");

    // 1. Fetch user row containing synchronized cart string definition
    $userStmt = $db->prepare("SELECT cart, address_country, address_county, address_area, address_street FROM users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $currentUserId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);

    // If the user isn't found in the database, log them out
    if (!$currentUser) {
        header('Location: logout.php');
        exit();
    }

    // 2. Decode the database cart
    $cart = !empty($currentUser['cart']) ? json_decode($currentUser['cart'], true) : [];
    if (!is_array($cart) || empty($cart)) {
        // If the database cart is empty, send them back to the cart page safely
        header('Location: cart.php?error=empty_basket');
        exit();
    }

    // Extract Delivery Metadata
    $address_country   = $currentUser['address_country'] ?? '';
    $address_county    = $currentUser['address_county'] ?? '';
    $address_area      = $currentUser['address_area'] ?? '';
    $address_street    = $currentUser['address_street'] ?? '';

    // Build the default shipping address text block
    $default_address = "";
    if (!empty($address_street))   $default_address .= $address_street . "\n";
    if (!empty($address_area))     $default_address .= $address_area . "\n";
    if (!empty($address_county))   $default_address .= $address_county . "\n";
    if (!empty($address_country))  $default_address .= $address_country;
    $default_address = trim($default_address);

    // --- HANDLE ORDER SUBMISSION ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'shop_submit_final_order') {
        $phoneNum = trim($_POST['mpesa_phone'] ?? '');
        $shipAddress = trim($_POST['shipping_address'] ?? '');
        $payMethod = $_POST['mpesa_method'] ?? 'Paybill';

        if (!empty($phoneNum) && !empty($shipAddress)) {
            
            // Clean up the cart quantities and calculate absolute grand total
            $compiledOrderItems = [];
            $apiGrandTotal = 0;
            foreach ($cart as $id => $qty) {
                $compiledOrderItems[$id] = (int)$qty;
                
                // Cross-reference with products.json for total calculations
                foreach ($allProducts as $p) {
                    if ((int)$p['id'] === (int)$id) {
                        $apiGrandTotal += ((float)$p['price'] * (int)$qty);
                        break;
                    }
                }
            }

            // Ensure amount is a rounded integer for Safaricom transaction formatting
            $checkoutAmount = round($apiGrandTotal);
            $itemsJsonText = json_encode($compiledOrderItems);
            $generatedOrderId = "ME-" . strtoupper(substr(md5(time()), 0, 8));

            // Format phone number safely to standard Kenyan code format (2547XXXXXXXX or 2541XXXXXXXX)
            $formattedPhone = preg_replace('/^\+/', '', $phoneNum); 
            if (str_starts_with($formattedPhone, '0')) {
                $formattedPhone = '254' . substr($formattedPhone, 1);
            }
            // ==================================================================
            // 🚀 SAFARICOM M-PESA DARAJA API STK PUSH CONFIGURATION ENGINE
            // ==================================================================
            // Dynamically routes using 2 distinct shortcodes based on user input
            $mpesaConfig = [
                "env"             => $_ENV['MPESA_ENV'] ?? "sandbox",
                "consumer_key"    => $_ENV['MPESA_CONSUMER_KEY'] ?? "YOUR_CONSUMER_KEY_HERE",
                "consumer_secret" => $_ENV['MPESA_CONSUMER_SECRET'] ?? "YOUR_CONSUMER_SECRET_HERE",
                "paybill_code"    => $_ENV['MPESA_PAYBILL_SHORTCODE'] ?? "174379",
                "till_code"       => $_ENV['MPESA_TILL_SHORTCODE'] ?? "211944",
                "passkey"         => $_ENV['MPESA_PASSKEY'] ?? "bfb272ea231d47617d4f35a6b3b3ba2d",
                "callback_url"    => $_ENV['MPESA_CALLBACK_URL'] ?? "https://yourdomain.com"
            ];

            // Set variables dynamically depending on chosen wallet path
            if ($payMethod === 'Buy Goods Till') {
                $chosenShortCode   = $mpesaConfig['till_code'];
                $transactionType   = 'CustomerBuyGoodsOnline';
                $accountReference  = 'Store Till Payment'; // Tills use store identification label
            } else {
                $chosenShortCode   = $mpesaConfig['paybill_code'];
                $transactionType   = 'CustomerPayBillOnline';
                $accountReference  = $generatedOrderId; // Paybill requires unique invoice references
            }

            // 1. Generate URLs dynamically based on deployment environment
            $authUrl = ($mpesaConfig['env'] === "production") 
                ? "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials" 
                : "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";

            $stkUrl = ($mpesaConfig['env'] === "production") 
                ? "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest" 
                : "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";

            // 2. Fetch API Authentication Token using Authorization Credentials
            $authHeaders = ['Authorization: Basic ' . base64_encode($mpesaConfig['consumer_key'] . ':' . $mpesaConfig['consumer_secret'])];
            $ch = curl_init($authUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $authHeaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Sandbox environment safeguard bypass
            $authResponse = json_decode(curl_exec($ch), true);
            curl_close($ch);

            $accessToken = $authResponse['access_token'] ?? null;

            if ($accessToken) {
                // 3. Setup Token Timestamps and Secure Authentication Password Matrix
                $timestamp = date('YmdHis');
                // Use the dynamically derived chosen shortcode for the password seed
                $stkPassword = base64_encode($chosenShortCode . $mpesaConfig['passkey'] . $timestamp);

                // 4. Map API Payload Objects
                $stkPayload = [
                    'BusinessShortCode' => $chosenShortCode,
                    'Password'          => $stkPassword,
                    'Timestamp'         => $timestamp,
                    'TransactionType'   => $transactionType, 
                    'Amount'            => $checkoutAmount,
                    'PartyA'            => $formattedPhone,
                    'PartyB'            => $chosenShortCode,
                    'PhoneNumber'       => $formattedPhone,
                    'CallBackURL'       => $mpesaConfig['callback_url'],
                    'AccountReference'  => $accountReference,
                    'TransactionDesc'   => 'Food Basket Payment'
                ];

                // 5. Fire STK Push Request to Safaricom Servers
                $stkHeaders = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $accessToken
                ];

                $ch = curl_init($stkUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $stkHeaders);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkPayload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                $stkResult = json_decode(curl_exec($ch), true);
                curl_close($ch);
            }

            // ==================================================================
            // 💾 SAVE TRANSACTION STATUS AND RECORD ENTRY (UPDATED TO CAPTURE ID)
            // ==================================================================
            $checkoutRequestId = $stkResult['CheckoutRequestID'] ?? null;
            $responseCode = $stkResult['ResponseCode'] ?? null;
            $orderStatus = ($responseCode == "0") ? 'PENDING' : 'FAILED_STK_PUSH';

            $orderStmt = $db->prepare("
                INSERT INTO orders (order_id, user_id, timestamp, status, items, shipping_address, payment_method, payment_phone, checkout_id) 
                VALUES (:order_id, :user_id, :timestamp, :status, :items, :shipping_address, :payment_method, :payment_phone, :checkout_id)
            ");
            
            $orderStmt->execute([
                ':order_id'         => $generatedOrderId,
                ':user_id'          => $currentUserId,
                ':timestamp'        => time(),
                ':status'           => $orderStatus,
                ':items'            => $itemsJsonText,
                ':shipping_address' => $shipAddress,
                ':payment_method'   => htmlspecialchars($payMethod),
                ':payment_phone'    => htmlspecialchars($phoneNum),
                ':checkout_id'      => $checkoutRequestId
            ]);

            // Wipe out synchronized basket array inside SQLite user records once successfully initialized
            $clearCartStmt = $db->prepare("UPDATE users SET cart = NULL WHERE id = :id");
            $clearCartStmt->execute([':id' => $currentUserId]);
                        
            // Pass the unique tracking variables directly down to our active JavaScript engine
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Fires up the automated asynchronous status tracker loop instantly
                    startMpesaPaymentStatusTracker('" . htmlspecialchars($generatedOrderId) . "');
                });
            </script>";
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include 'header.php';
?>
<div class="shop-checkout-page-wrapper">
    <div class="shop-checkout-main-container">

        <!-- ================= MODAL DIALOG COMPONENT PANEL ================= -->
        <div id="shopSuccessModal" class="shop-success-modal-backdrop">
            <div class="shop-success-modal-card">
                <div class="modal-emoji-header">🎉</div>
                <h3 class="modal-success-heading">Transaction Received!</h3>
                <p class="modal-success-description">
                    Congrats, your transaction has been received successfully! Please enter your PIN on the M-Pesa prompt sent to your phone to complete payment.
                </p>
                <button type="button" class="shop-modal-redirect-btn btn-modal-order-history-redirect">
                    View Order Status
                </button>
            </div>
        </div>

        <div class="shop-checkout-split-grid">
            
            <!-- LEFT CONTROL CONTAINER PANEL -->
            <div class="shop-checkout-left-form-panel">
                <h2 class="checkout-panel-heading">
                    Delivery & Payment Details
                </h2>

                <form method="POST" action="checkout.php" id="shopMpesaForm" class="shop-checkout-core-form">
                    <input type="hidden" name="action" value="shop_submit_final_order" />

                    <!-- 1. Delivery Area Input with Default Address Injected -->
                    <div class="form-input-row">
                        <label for="delivery_address" class="form-input-label">Delivery Address</label>
                        <textarea id="delivery_address" name="shipping_address" rows="3" required class="form-textarea-field"><?php echo htmlspecialchars($default_address); ?></textarea>
                    </div>

                    <!-- 2. Dual Selection Payment Choice Tabs -->
                    <div class="form-input-row">
                        <label class="form-input-label label-spacing-bottom">Select M-Pesa Payment Method</label>
                        
                        <div class="payment-methods-radio-stack">
                            <label class="payment-method-custom-tab">
                                <input type="radio" name="mpesa_method" value="Paybill" checked class="shop-radio-accent">
                                <div class="tab-text-holder">
                                    <strong class="method-title">M-Pesa Paybill</strong>
                                </div>
                            </label>

                            <label class="payment-method-custom-tab">
                                <input type="radio" name="mpesa_method" value="Buy Goods Till" class="shop-radio-accent">
                                <div class="tab-text-holder">
                                    <strong class="method-title">Buy Goods Till Number</strong>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- 3. Protected Mobile Phone Input Component Field -->
                    <div class="form-input-row phone-container-context">
                        <label for="mpesa_phone" class="form-input-label">Input Your M-Pesa Phone Number Here</label>
                        
                        <div id="phoneValidationError" class="phone-live-validation-error-label" style="display: none; color: red; font-weight: bold; margin-top: 5px;">
                            ⚠️ INVALID PHONE FORMAT!
                        </div>

                        <input type="text" id="mpesa_phone" name="mpesa_phone" placeholder="e.g. 0712345678, 0112345678 or +2547..." required class="form-input-field phone-monospace-field" />
                    </div>

                    <button type="button" id="shopSubmitBtn" class="shop-checkout-submit-action-btn btn-checkout-submit-trigger">
                        Complete Order
                    </button>
                </form>
            </div>

            <!-- RIGHT PANEL: Summary Matrix Box Card Grid -->
            <div class="shop-checkout-right-summary-panel">
                <h3 class="order-summary-panel-title">Order Summary</h3>
                
                <div class="order-summary-items-list-wrapper">
                    <?php
                    $grand_total = 0;
                    foreach ($cart as $id => $quantity):
                        
                        $matchedProduct = null;
                        foreach ($allProducts as $p) {
                            if ((int)$p['id'] === (int)$id) {
                                $matchedProduct = $p;
                                break;
                            }
                        }
                        if (!$matchedProduct) continue;

                        $price = !empty($matchedProduct['price']) ? (float)$matchedProduct['price'] : 0.00;
                        $line = $price * $quantity;
                        $grand_total += $line;
                        ?>
                        <div class="summary-item-flex-row">
                            <div class="summary-item-name-qty">
                                <strong><?php echo htmlspecialchars($matchedProduct['name']); ?></strong> 
                                <span class="summary-qty-muted-tag">x<?php echo (int)$quantity; ?></span>
                            </div>
                            <div class="summary-item-line-total">KSh <?php echo number_format($line, 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-panel-total-due-row">
                    <span>Total Due:</span>
                    <span class="total-due-tomato-amount">KSh <?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>

        </div> <!-- End .shop-checkout-split-grid -->
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