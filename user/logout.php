<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Wipe out all active session data arrays
$_SESSION = [];

// 2. Destroy the session cookie token on the server completely
session_destroy();

// 3. Redirect the user back to your guest home page smoothly
header('Location: index.php');
exit();
?>
