<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// --- 1. LOAD ENVIRONMENT CONFIGURATION FROM .ENV ---
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

// --- 2. REQUIRE COMPOSER AUTOLOADER FOR PHPMAILER ---
require __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- 3. CONNECT TO DATABASE ---
$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    die("Database connectivity error.");
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    
    if (empty($email)) {
        $message = "Please enter your email address.";
        $messageType = "error";
    } else {
        // Query if account exists
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // For security, do not disclose that the email doesn't exist
            $message = "If that email is registered, a password reset link has been sent.";
            $messageType = "success";
        } elseif (!empty($user['google_provider'])) {
            $message = "This account uses Google Sign-In. Please recover via Google.";
            $messageType = "error";
        } else {
            // Create strong secure token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token to database
            $update = $db->prepare("UPDATE users SET reset_token = :token, reset_expires = :expiry WHERE id = :id");
            $update->execute([
                ':token' => $token,
                ':expiry' => $expiry,
                ':id' => $user['id']
            ]);
            
            // Construct the real production link dynamically based on the current domain
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $currentDir = dirname($_SERVER['REQUEST_URI']);
            $resetLink = $protocol . $domain . $currentDir . "/reset-password.php?token=" . $token;
            
            // Initialize live mail delivery setup
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = $_ENV['SMTP_USER'] ?? '';
                $mail->Password   = $_ENV['SMTP_PASS'] ?? '';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = $_ENV['SMTP_PORT'] ?? 465; // for secure SSl use 465//
                
                $mail->setFrom($_ENV['SMTP_FROM'] ?? 'noreply@my-restaurant.com', 'My Restaurant Support');
                $mail->addAddress($email, $user['name']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Secure Password Reset Request - My Restaurant';
                
                // Clean structured transactional email layout inside Gmail
                $mail->Body = "
                    <div style='max-width:600px; margin:0 auto; font-family:Arial, sans-serif; border:1px solid #e0e0e0; padding:30px; border-radius:8px;'>
                        <h2 style='color:#222; text-align:center;'>Password Recovery Service</h2>
                        <p>Hello " . htmlspecialchars($user['name']) . ",</p>
                        <p>We received a security request to update your password profile account. If you did not initiate this action, please safely disregard this notice.</p>
                        <p>This validation link is strictly unique and will automatically expire in 1 hour.</p>
                        <div style='text-align:center; margin:30px 0;'>
                            <a href='{$resetLink}' target='_blank' style='background-color:tomato; color:white; padding:12px 30px; text-decoration:none; font-weight:bold; border-radius:5px; display:inline-block; text-transform:uppercase; font-size:14px;'>Change Pass</a>
                        </div>
                        <p style='color:#666; font-size:12px;'>If the button above does not load, copy and paste this verification address into your web browser path bar:</p>
                        <p style='color:#2271b1; font-size:12px; word-break:break-all;'>{$resetLink}</p>
                    </div>
                ";
                
                $mail->send();
                $message = "If that email is registered, a password reset link has been sent.";
                $messageType = "success";
            } catch (Exception $e) {
                $message = "Mail dispatch failure tracking logs: {$mail->ErrorInfo}";
                $messageType = "error";
            }
        }
    }
}
?>

<?php include 'header.php'; ?>
<div class="page-content">
    <div class="shop-login-viewport-wrapper">
        <main id="shop-login-form-card">
            <h2 class="shop-portal-title">Reset Password</h2>
            
            <?php if (!empty($message)): ?>
                <div class="<?php echo ($messageType === 'success') ? 'shop-portal-alert-success' : 'shop-portal-alert-error'; ?>" style="padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; background: <?php echo ($messageType === 'success') ? '#d4edda; color:#155724;' : '#f8d7da; color:#721c24;'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="shop-wp-form-harness">
                <form method="POST" action="forgot-password.php">
                    <p>
                        <label>Registered Email Address</label>
                        <input type="email" name="email" required placeholder="e.g., customer@gmail.com">
                    </p>
                    <p>
                        <input type="submit" value="Send Request">
                    </p>
                </form>
            </div>
            
            <p class="shop-portal-register-footer-text">
                <a href="login.php" class="shop-portal-register-anchor-link">Back to Sign In</a>
            </p>
        </main>
    </div>
</div>
<?php include 'footer.php'; ?>
