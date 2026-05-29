<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/']);
    session_start();
}

$productsFile = '../products.json';
$errorMessage = "";
$successMessage = "";

// Secure Routing: Force guest accounts to redirect smoothly to login screen if signed out
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Connect to your secure SQLite database
$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // FIXED: Embedded the self-healing layout tables query
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        name TEXT, 
        email TEXT, 
        password TEXT, 
        google_provider TEXT, 
        orders TEXT,
        address_country TEXT,
        address_county TEXT,
        address_area TEXT,
        address_street TEXT,
        address_landmark TEXT
    )");

} catch (PDOException $e) {
    die("Database connectivity error: " . $e->getMessage());
}

// FIXED: Defined the missing variable
$currentUserId = $_SESSION['user']['id'];

// Fetch the most up-to-date user information from the database securely
$stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $currentUserId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: logout.php');
    exit();
}

// Extract Delivery Metadata falling back on pristine options if empty
$address_country   = $currentUser['address_country'] ?? '';
$address_county    = $currentUser['address_county'] ?? '';
$address_area      = $currentUser['address_area'] ?? '';
$address_street    = $currentUser['address_street'] ?? '';
$address_landmark  = $currentUser['address_landmark'] ?? '';
$google_login_provider = $currentUser['google_provider'] ?? ''; 

// Handle Action Updates (Saving account profile modifications or processing complete system deletions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- FIXED: SECURE SQL ACCOUNT DELETION ---
    if ($action === 'shop_delete_profile_core') {
        $deleteStmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $deleteStmt->execute([':id' => $currentUserId]);
        header('Location: logout.php');
        exit();
    }

    // --- FIXED: SECURE SQL PROFILE FORM UPDATE ---
    if ($action === 'shop_save_profile_core') {
        $newEmail = trim($_POST['account_email'] ?? '');
        
        // Verify unique email ownership across other active database entries securely
        $emailStmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) AND id != :id");
        $emailStmt->execute([':email' => $newEmail, ':id' => $currentUserId]);
        $emailExists = $emailStmt->fetch();

        if (empty($newEmail)) {
            $errorMessage = "Email address field cannot be left blank.";
        } elseif ($emailExists) {
            $errorMessage = "This email address is already in use by another account.";
        } else {
            // Read Password Inputs
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $passwordChangedSuccessfully = true;
            $passwordQueryPart = "";
            $queryParams = [
                ':email' => htmlspecialchars($newEmail),
                ':country' => htmlspecialchars(trim($_POST['address_country'] ?? '')),
                ':county' => htmlspecialchars(trim($_POST['address_county'] ?? '')),
                ':area' => htmlspecialchars(trim($_POST['address_area'] ?? '')),
                ':street' => htmlspecialchars(trim($_POST['address_street'] ?? '')),
                ':landmark' => htmlspecialchars(trim($_POST['address_landmark'] ?? '')),
                ':id' => $currentUserId
            ];

            if (!empty($newPassword) || !empty($currentPassword)) {
                // If account is linked to an external login provider, restrict direct modifications
                if (!empty($google_login_provider)) {
                    $errorMessage = "Password changes are managed through Google for this account.";
                    $passwordChangedSuccessfully = false;
                }
                // Verify initial current verification hashes
                elseif (!password_verify($currentPassword, $currentUser['password'])) {
                    $errorMessage = "The current password you provided is incorrect. Password change failed.";
                    $passwordChangedSuccessfully = false;
                } elseif ($newPassword !== $confirmPassword) {
                    $errorMessage = "Your new password fields do not match.";
                    $passwordChangedSuccessfully = false;
                } elseif (strlen($newPassword) < 6) {
                    $errorMessage = "Your new password must be at least 6 characters long.";
                    $passwordChangedSuccessfully = false;
                } elseif (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                    $errorMessage = "Your password must contain a mix of letters and numbers.";
                    $passwordChangedSuccessfully = false;
                } else {
                    // Prepare password segment for the SQL query string safely
                    $passwordQueryPart = ", password = :password";
                    $queryParams[':password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
            }

            if ($passwordChangedSuccessfully && empty($errorMessage)) {
                // Update your session variables instantly
                $_SESSION['user']['email'] = htmlspecialchars($newEmail);

                // FIXED: EXECUTE AN AUTOMATIC SECURE DB RECORD UPDATE INSTEAD OF FILE_PUT_CONTENTS
                $updateSql = "UPDATE users SET 
                                email = :email, 
                                address_country = :country, 
                                address_county = :county, 
                                address_area = :area, 
                                address_street = :street, 
                                address_landmark = :landmark 
                                {$passwordQueryPart} 
                              WHERE id = :id";
                
                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute($queryParams);
                
                // Refresh local rendering elements automatically from database values
                $stmt->execute([':id' => $currentUserId]);
                $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $address_country   = $currentUser['address_country'];
                $address_county    = $currentUser['address_county'];
                $address_area      = $currentUser['address_area'];
                $address_street    = $currentUser['address_street'];
                $address_landmark  = $currentUser['address_landmark'];
                $successMessage = "Your account modifications were saved successfully!";
            }
        }
    }
}
?>
<?php include 'header.php'; ?>

<div class="account-page-layout">
    <div class="account-content-container">
        
        <h2 class="account-section-heading">
            Account Details
        </h2>

        <?php if (!empty($errorMessage)): ?>
            <div id="shopAlertBannerError" class="account-alert-banner error-banner">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div id="shopAlertBannerSuccess" class="account-alert-banner success-banner">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="account.php" class="account-core-form">
            <input type="hidden" name="action" value="shop_save_profile_core" />

            <!-- Username Row -->
            <div class="account-form-row">
                <label class="account-form-label">Username (Cannot be altered)</label>
                <input type="text" value="<?php echo htmlspecialchars($currentUser['name']); ?>" disabled class="account-form-input disabled-input" />
            </div>

            <!-- Email Address Row -->
            <div class="account-form-row">
                <label for="account_email" class="account-form-label">Email Address</label>
                <input type="email" id="account_email" name="account_email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required class="account-form-input dynamic-border" />
            </div>

            <div class="account-form-divider">
                <h4 class="account-divider-title">Delivery Information</h4>
            </div>

            <!-- Delivery Address Section -->
            <div class="account-form-row">
                <label class="account-form-label-bold">
                    Default Delivery Address
                </label>

                <div class="delivery-inputs-stack">
                    <input type="text" name="address_country" placeholder="Country" value="<?php echo htmlspecialchars($address_country); ?>" class="account-form-input" />
                    <input type="text" name="address_county" placeholder="County / Region" value="<?php echo htmlspecialchars($address_county); ?>" class="account-form-input" />
                    <input type="text" name="address_area" placeholder="Area / Estate" value="<?php echo htmlspecialchars($address_area); ?>" class="account-form-input" />
                    <input type="text" name="address_street" placeholder="Street / Apartment / Building" value="<?php echo htmlspecialchars($address_street); ?>" class="account-form-input" />
                    <input type="text" name="address_landmark" placeholder="Nearby Landmark (Optional)" value="<?php echo htmlspecialchars($address_landmark); ?>" class="account-form-input" />
                </div>
            </div>

            <!-- GOOGLE USER MESSAGE BLOCK -->
            <?php if (!empty($google_login_provider)): ?>
                <div class="account-form-divider">
                    <h4 class="account-divider-title">Change Password</h4>
                </div>
                <div class="social-profile-notice">
                    Password changes are managed through Google.
                </div>
            <?php endif; ?>

            <!-- PASSWORD INPUT WRAPPER BLOCK -->
            <div class="password-fields-wrapper <?php echo !empty($google_login_provider) ? 'hidden-for-social-profiles' : ''; ?>">
                <div class="account-form-divider">
                    <h4 class="account-divider-title">Change Password</h4>
                </div>

                <div class="account-form-row">
                    <label for="current_password" class="account-form-label">
                        Current Password
                    </label>

                    <div class="password-toggle-relative">
                        <input 
                            type="password"
                            id="current_password"
                            name="current_password"
                            placeholder="Leave blank if not changing"
                            class="account-form-input field-with-padding dynamic-border"
                        />
                        <span class="password-reveal-eye" data-target="current_password">👁</span>
                    </div>
                </div>

                <div class="password-grid">
                    <div class="account-form-row">
                        <label for="new_password" class="account-form-label">
                            New Password
                        </label>
                        <div class="password-toggle-relative">
                            <input 
                                type="password"
                                id="new_password"
                                name="new_password"
                                placeholder="Minimum 6 characters"
                                class="account-form-input field-with-padding dynamic-border"
                            />
                            <span class="password-reveal-eye" data-target="new_password">👁</span>
                        </div>
                    </div>    
                    <div class="account-form-row">
                        <label for="confirm_password" class="account-form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" class="account-form-input dynamic-border" />
                    </div>
                </div>
                
                <!-- Password Strength Meter Bar Wrapper -->
                <div id="shopStrengthWrapper" class="strength-wrapper-hidden">
                    <div class="strength-meter-track">
                        <div id="shopStrengthBar" class="strength-meter-bar"></div>
                    </div>
                    <span id="shopStrengthText" class="strength-meter-text">Too Short</span>
                </div>
            </div> <!-- End .password-fields-wrapper -->

            <button type="submit" class="account-action-submit-btn">
                Save Account Changes
            </button>
        </form>

        <!-- ACCOUNT DELETION BLOCK (DANGER ZONE) -->
        <div class="danger-zone-wrapper">
            <h4 class="danger-zone-title">Danger Zone</h4>
            <p class="danger-zone-description">
                Once you request account deletion, your profile access shuts down immediately. The database record will be erased permanently.
            </p>
            <form id="deleteAccountForm" method="POST" action="account.php">
                <input type="hidden" name="action" value="shop_delete_profile_core" />
                <button type="submit" class="danger-zone-submit-btn">
                    Request Account Deletion
                </button>
            </form>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>
