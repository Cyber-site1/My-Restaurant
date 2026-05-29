<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connectivity error.");
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$message = '';
$messageType = '';
$showForm = false;

if (empty($token)) {
    die("Security Error: Access token verification parameters are missing.");
}

// Search database to see if token matches and hasn't expired yet
$stmt = $db->prepare("SELECT * FROM users WHERE reset_token = :token AND reset_expires > datetime('now', 'localtime')");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback checking framework for varying timezone environments
if (!$user) {
    $stmt = $db->prepare("SELECT * FROM users WHERE reset_token = :token AND reset_expires > datetime('now')");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($user) {
    $showForm = true;
} else {
    $message = "This verification link is invalid or has expired. Please submit a new request.";
    $messageType = "error";
}

// Handle Form Submission Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $newPass = trim($_POST['password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');
    
    if ($newPass !== $confirmPass) {
        $message = "Password confirmation inputs do not match.";
        $messageType = "error";
    } elseif (strlen($newPass) < 8) {
        $message = "Password criteria failed. Minimum length is 8 characters.";
        $messageType = "error";
    } else {
        // Securely hash the password using standard production algorithms
        $hashedPassword = password_hash($newPass, PASSWORD_DEFAULT);
        
        // Update user password and clear token columns safely
        $update = $db->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_expires = NULL WHERE id = :id");
        $update->execute([
            ':password' => $hashedPassword,
            ':id' => $user['id']
        ]);
        
        $message = "Success! Your password has been updated. You can now log in.";
        $messageType = "success";
        $showForm = false; // Hide form on successful update
    }
}
?>

<?php include 'header.php'; ?>
<div class="page-content">
    <div class="shop-login-viewport-wrapper">
        <main id="shop-login-form-card">
            <h2 class="shop-portal-title">Set New Password</h2>

            <?php if (!empty($message)): ?>
                <div style="padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; background: <?php echo ($messageType === 'success') ? '#d4edda; color:#155724;' : '#f8d7da; color:#721c24;'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($showForm): ?>
                <div class="shop-wp-form-harness">
                    <form method="POST" action="reset-password.php">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <p>
                            <label>New Secure Password</label>
                            <input type="password" id="new_password" name="password" required oninput="evaluatePasswordStrength(this.value)">
                        </p>
                        
                        <!-- LIVE PASSSWORD STRENGTH TOOL PANEL -->
                        <div id="strength-meter-box" style="margin-top: -10px; margin-bottom: 15px; font-size: 13px; text-align: left;">
                            <span id="meter-label" style="font-weight: bold; color: #666;">Strength: Too Short</span>
                            <p id="meter-recommendation" style="margin: 4px 0 0 0; color: #8c8f94; font-size: 11px; line-height:1.4;">
                                Recommendation: Use 8+ characters combining uppercase, lowercase letters, numbers, and symbols.
                            </p>
                        </div>

                        <p>
                            <label>Confirm Password</label>
                            <input type="password" name="confirm_password" required>
                        </p>

                        <p>
                            <input type="submit" value="Save New Password">
                        </p>
                    </form>
                </div>
            <?php endif; ?>

            <p class="shop-portal-register-footer-text">
                <a href="login.php" class="shop-portal-register-anchor-link">Go to Sign In Page</a>
            </p>
        </main>
    </div>
</div>

<script>
function evaluatePasswordStrength(password) {
    const label = document.getElementById('meter-label');
    const rec = document.getElementById('meter-recommendation');
    
    if (password.length === 0) {
        label.textContent = "Strength: Empty";
        label.style.color = "#666";
        return;
    }
    
    let score = 0;
    
    // Check complexity criteria patterns
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    // Render metrics updates dynamically
    if (password.length < 8) {
        label.textContent = "Strength: Too Short (Minimum 8 chars)";
        label.style.color = "tomato";
    } else if (score <= 2) {
        label.textContent = "Strength: Weak 🔴";
        label.style.color = "tomato";
    } else if (score <= 4) {
        label.textContent = "Strength: Medium 🟡";
        label.style.color = "#e6a100";
    } else {
        label.textContent = "Strength: Strong Code Verified 🟢";
        label.style.color = "#28a745";
    }
}
</script>
<?php include 'footer.php'; ?>
