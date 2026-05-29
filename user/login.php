<?php
// Force PHP to show errors on screen instead of a blank page
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- LOAD ENVIRONMENT VARIABLES FROM .ENV ---
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }
}

// These are now accessible securely in your PHP backend script
$google_client_id = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
$google_client_secret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// SECURE FILE: Connect to the hidden binary database file instead of users.json
$dbFile = __DIR__ . '/../database.sqlite';
try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 🎯 Cleaned: The columns already exist, so we don't need to try adding them anymore!
    
} catch (PDOException $e) {
    die("Database connectivity error.");
}

$errorMessage = '';

// --- INTERCEPT GOOGLE ID HANDSHAKE TOKENS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['google_id_token'])) {
    $idToken = $_POST['google_id_token'];
    $tokenParts = explode('.', $idToken);
    
    if (count($tokenParts) === 3) {
        $jsonPayload = base64_decode(strtr($tokenParts[1], '-_', '+/'));
        $googleUser = json_decode($jsonPayload, true);
        
        // Security check matching your environment variables
        if (!$googleUser || !isset($googleUser['aud']) || $googleUser['aud'] !== $_ENV['GOOGLE_CLIENT_ID']) {
            $errorMessage = 'Security token mismatch rejection.';
            include 'header.php';
            echo "<div class='shop-portal-alert-error'>{$errorMessage}</div>";
            include 'footer.php';
            exit; 
        }

        if ($googleUser && isset($googleUser['email'])) {
            $email = strtolower(trim($googleUser['email']));
            $name = trim($googleUser['name'] ?? explode('@', $email)[0]);
            
            // Search SQLite table securely for this user email
            $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = :email");
            $stmt->execute([':email' => $email]);
            $matchedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$matchedUser) {
                // Auto-Register profiles cleanly into database on first Google click
                $insert = $db->prepare("INSERT INTO users (name, email, password, google_provider) VALUES (:name, :email, '', 'google')");
                $insert->execute([
                    ':name' => htmlspecialchars($name),
                    ':email' => htmlspecialchars($email)
                ]);
                
                // Fetch the row we just made to get its unique database ID
                $stmt->execute([':email' => $email]);
                $matchedUser = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $_SESSION['user'] = [
                'id' => $matchedUser['id'],
                'name' => $matchedUser['name'],
                'email' => $matchedUser['email']
            ];
            
            header('Location: index.php');
            exit;
        } else {
            $errorMessage = 'Invalid Google identity response data.';
        }
    } else {
        $errorMessage = 'Google identity handshake verification failed.';
    }
}

// --- STANDARD FORM VALIDATION PIPELINE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['google_id_token'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $errorMessage = 'Please fill in all fields.';
    } else {
        // Securely query the database for the matching email string
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(:email)");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Check if user should use Google button instead
            if (empty($user['password']) && !empty($user['google_provider'])) {
                $errorMessage = 'This account is linked to Google. Please use Google Sign-In.';
            } elseif (password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email']
                ];
                header('Location: index.php');
                exit;
            }
        }
        
        if (empty($errorMessage)) {
            $errorMessage = 'Invalid email or password.';
        }
    }
}
?>

<?php include 'header.php'; ?>

<script src="https://accounts.google.com/gsi/client" async defer></script>

<div class="page-content">

    <div class="shop-login-viewport-wrapper">

        <main id="shop-login-form-card">

            <h2 class="shop-portal-title">
                shop Portal
            </h2>

            <?php if (!empty($errorMessage)): ?>
                <div class="shop-portal-alert-error">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <div class="shop-wp-form-harness">
                <form method="POST" action="login.php">
                    <p>
                        <label>Email Address</label>
                        <input type="email" name="email" required>
                    </p>

                    <p>
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </p>

                    <p class="login-remember">
                        <input type="checkbox" id="remember_me">
                        <label for="remember_me">
                            Remember Me
                        </label>
                    </p>

                    <p>
                        <input type="submit" value="Sign In">
                    </p>
                </form>
            </div>

            <!-- DIVIDER -->
            <div class="shop-portal-divider">
                or
            </div>

            <!-- GOOGLE BUTTON -->
            <div class="shop-google-action-container" style="position: relative; display: inline-block;">
                
                <!-- Google App Configuration Data Wrapper -->
                <div id="g_id_onload"
                     data-client_id="419250184037-47d6orij2ivo8rvk27oubj5ktr0am8qh.apps.googleusercontent.com"
                     data-context="signin"
                     data-ux_mode="popup"
                     data-callback="handleGoogleLoginInterceptResponse"
                     data-auto_select="false">
                </div>
                
                <!-- INVISIBLE GOOGLE LAYER: Captures the secure click without changing your look -->
                <div class="g_id_signin"
                     data-type="standard"
                     data-size="large"
                     style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; z-index: 10; cursor: pointer;">
                </div>

                <!-- YOUR CUSTOM BUTTON: Preserves all style.css definitions perfectly -->
                <a href="#" class="shop-google-portal-btn" style="position: relative; z-index: 5; pointer-events: none;">
                    <svg class="shop-google-g-icon" xmlns="http://w3.org" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22c-.13-.67-.21-1.37-.21-2.09c0-.72.08-1.42.21-2.09z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>
                        Sign in with Google
                    </span>
                </a>
            </div>

            <!-- Hidden dispatch wrapper form to safely move google data to backend php validation filters -->
            <form id="googleDirectFallbackForm" method="POST" action="login.php" style="display: none;">
                <input type="hidden" name="google_id_token" id="googleIdTokenCarrierInput" />
            </form>

            <!-- REGISTER & FORGOT PASSWORD Links -->
            <p class="shop-portal-register-footer-text">
                Don’t have an account?
                <a href="register.php" class="shop-portal-register-anchor-link">
                    Create one here
                </a>
                <br><br>
                <!-- Added Password Reset Entry point -->
                <a href="forgot-password.php" class="shop-portal-register-anchor-link" style="color: #666; font-size: 13px;">
                    Forgot Password?
                </a>
            </p>

        </main>

    </div>

</div>

<!-- DATA TRANSLATION AND DISPATCH SCRIPT ROUTINES -->
<script>
function triggerGooglePopupFrame(e) {
    e.preventDefault();
    // Added structural checking fallback routines to catch silent runtime blockages
    if (typeof google !== 'undefined' && google.accounts && google.accounts.id) {
        google.accounts.id.prompt();
    } else {
        alert("The Google Sign-In library didn't load. Make sure your browser ad-blocker is turned off, or try an incognito window.");
    }
}

function handleGoogleLoginInterceptResponse(response) {
    const carrierInput = document.getElementById('googleIdTokenCarrierInput');
    const carrierForm = document.getElementById('googleDirectFallbackForm');
    
    if (response.credential && carrierInput && carrierForm) {
        carrierInput.value = response.credential;
        carrierForm.submit();
    }
}
</script>

<?php include 'footer.php'; ?>
