<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

// SECURE FILE: Stored outside public folder, completely locked away
$dbFile = __DIR__ . '/../database.sqlite';
$errorMessage = '';
$successMessage = '';

try {
    // Connect to the SQLite database
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // AUTOMATIC TABLE SETUP: Creates your structural layout instantly if missing
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        google_provider TEXT DEFAULT NULL
    )");
} catch (PDOException $e) {
    die("Database engine initialization crash: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $errorMessage = 'Please fill in all fields.';
    } else {
        try {
            // Check if email already exists using standard safe queries
            $checkStmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email)");
            $checkStmt->execute([':email' => $email]);
            
            if ($checkStmt->fetch()) {
                $errorMessage = 'Email already exists.';
            } else {
                // Securely encrypt the user password hash
                $secureHash = password_hash($password, PASSWORD_DEFAULT);

                // SQL INJECTION PROTECTION: Prepares inputs cleanly using bound parameters
                $insertStmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
                $insertStmt->execute([
                    ':name'     => htmlspecialchars($name),
                    ':email'    => htmlspecialchars($email),
                    ':password' => $secureHash
                ]);

                $successMessage = 'Account created successfully.';
                header('Refresh:2; url=login.php');
            }
        } catch (PDOException $e) {
            $errorMessage = 'Database transaction failure.';
        }
    }
}
?>

<?php include 'header.php'; ?>

<div class="page-content">

    <div class="shop-register-viewport-wrapper">

        <main class="shop-register-form-card">

            <h2 class="shop-register-title">
                Create Your Account
            </h2>

            <?php if (!empty($errorMessage)): ?>
                <div class="shop-register-alert-error">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMessage)): ?>
                <div class="shop-register-alert-success">
                    <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" class="shop-register-form">
                <div class="register-form-group">
                    <label>Username</label>
                    <input type="text" name="name" required>
                </div>

                <div class="register-form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>

                <div class="register-form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="shop-register-btn">
                    Register
                </button>
            </form>

            <p class="shop-register-login-link">
                Already have an account?
                <a href="login.php">
                    Login here
                </a>
            </p>

        </main>

    </div>

</div>

<?php include 'footer.php'; ?>
