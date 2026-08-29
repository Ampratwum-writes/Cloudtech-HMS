<?php
/* ============================================
   GCTU 9 Hostel — Security bootstrap
   Include this at the very top of every page
   INSTEAD of a bare session_start() call.
   Handles: hardened session cookies + CSRF tokens.
   ============================================ */

// Must run before session_start() — sets how the session cookie behaves.
session_set_cookie_params([
    'httponly' => true,   // JavaScript can never read the session cookie
    'secure'   => false,  // set to true once your site is served over HTTPS
    'samesite' => 'Lax'   // blocks most cross-site cookie sending
]);
session_start();

// ---------- CSRF helpers ----------

// Call this to get the current CSRF token (creates one if none exists yet).
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Echoes a ready-to-use hidden <input> for a <form>.
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// Call at the top of every POST handler. Stops and returns a JSON/redirect
// error if the token is missing or wrong. $isAjax=true replies with JSON
// (for fetch() calls); false redirects back with an error page).
function csrf_verify($isAjax = false) {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $submitted)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(["success" => false, "error" => "Security check failed — please refresh the page and try again."]);
        } else {
            http_response_code(403);
            echo "Security check failed — please go back, refresh the page, and try again.";
        }
        exit;
    }
}
?>
