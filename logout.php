<?php
// ============================================
// GCTU 9 Hostel — Logout
// ============================================
require 'security.php';

// Clear session data
$_SESSION = [];

// Remove the session cookie itself from the browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header("Location: login.php");
exit;
