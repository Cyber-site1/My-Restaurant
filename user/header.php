<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = isset($_SESSION['user']);

$userName = '';
$userInitials = '';

if ($isLoggedIn) {

    $userName = $_SESSION['user']['name'];

    $nameParts = explode(' ', trim($userName));

    if (count($nameParts) >= 2) {

        $userInitials =
            strtoupper(substr($nameParts[0], 0, 1)) .
            strtoupper(substr($nameParts[1], 0, 1));

    } else {

        $userInitials = strtoupper(substr($userName, 0, 2));

    }
}

// Ensure we always have a working database connection inside the header
if (!isset($db)) {
    $dbFile = __DIR__ . '/../database.sqlite';
    if (file_exists($dbFile)) {
        try {
            $db = new PDO("sqlite:" . $dbFile);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Fails silently so it doesn't crash your page design if the file is locked
        }
    }
}

// Securely grab the freshest wishlist and cart counts directly from SQLite
if (isset($db) && isset($_SESSION['user']['id'])) {
    $headerStmt = $db->prepare("SELECT wishlist, cart FROM users WHERE id = :id");
    $headerStmt->execute([':id' => $_SESSION['user']['id']]);
    $freshUser = $headerStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($freshUser) {
        $userWishlist = json_decode($freshUser['wishlist'], true) ?? [];
        $syncCart = json_decode($freshUser['cart'], true) ?? [];
    }
} else {
    // Fallback defaults if the user is a guest or database drops offline
    $userWishlist = [];
    $syncCart = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <!-- Forces mobile device webview configurations to render at native phone dimensions -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="icon" type="image/x-icon" href="../favicon.ico?v=1.0">

    <title>My Restaurant</title>

    <!-- Dynamic timestamp breaks the mobile browser cache block instantly -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

</head>

<body>

<header class="site-header">

    <!-- LEFT BLOCK -->

    <div class="header-left-block">

        <h1 class="site-logo">

            <a href="index.php">
                My Restaurant
            </a>

        </h1>

    </div>

    <!-- CENTER BLOCK -->

    <div class="header-center-block">

        <nav class="main-navigation">

            <ul class="nav-links-list">

                <li><a href="index.php">Home</a></li>

                <li><a href="menu.php">Menu</a></li>

            </ul>

        </nav>

    </div>

    <!-- RIGHT BLOCK -->

    <div class="header-right-block">

        <div class="user-menu-wrapper">

            <?php if ($isLoggedIn): ?>

                <!-- USER BUTTON -->

                <div id="userMenuTriggerButton"
                     class="user-menu-trigger-container">

                    <div class="user-profile-badge">

                        <?php echo htmlspecialchars($userInitials); ?>

                    </div>

                    <span class="user-profile-name">

                        <?php echo htmlspecialchars($userName); ?>

                    </span>

                </div>

                <!-- DROPDOWN -->

                <div id="userHeaderVerticalDropdown"
                     class="user-dropdown-flyout-panel">

                    <a href="account.php"
                       class="dropdown-link-item">
                        Account Details
                    </a>

                    <a href="wishlist.php"
                       class="dropdown-link-item">
                        Wish List
                    </a>

                    <a href="cart.php"
                       class="dropdown-link-item">
                        Cart
                    </a>

                    <a href="orders.php"
                       class="dropdown-link-item">
                        Orders
                    </a>

                    <a href="logout.php"
                       class="dropdown-link-item sign-out-action-trigger">
                        Sign Out
                    </a>

                </div>

            <?php else: ?>

                <a href="login.php"
                   class="header-guest-login-btn">
                    Login
                </a>

            <?php endif; ?>

        </div>

    </div>

</header>