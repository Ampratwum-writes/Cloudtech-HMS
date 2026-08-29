<?php
// ============================================
// GCTU 9 Hostel — Login
// ============================================
require 'security.php';
require 'config.php';

// Already logged in? Skip straight to the app.
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'Admin' ? 'index.php' : 'student_dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(false);
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $stmt = $conn->prepare("SELECT UserID, Username, PasswordHash, Role, StudentID FROM users WHERE Username = ? OR Email = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['PasswordHash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['UserID'];
            $_SESSION['username']   = $user['Username'];
            $_SESSION['role']       = $user['Role'];
            $_SESSION['student_id'] = $user['StudentID'];
            header("Location: " . ($user['Role'] === 'Admin' ? 'index.php' : 'student_dashboard.php'));
            exit;
        } else {
            $error = 'Incorrect username/email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — CLOUD TECH HOSTEL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --brand: #4f46e5;
        --brand-dark: #4338ca;
        --bg-1: #eef2ff;
        --bg-2: #e0e7ff;
        --text: #1e1b2e;
        --muted: #6b7280;
        --border: #e5e7eb;
        --error-bg: #fef2f2;
        --error-text: #b91c1c;
        --error-border: #fecaca;
        --radius: 14px;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Inter', system-ui, sans-serif;
        background: linear-gradient(135deg, var(--bg-1), var(--bg-2));
        color: var(--text);
        padding: 24px;
    }
    .auth-card {
        width: 100%;
        max-width: 400px;
        background: #ffffff;
        border-radius: var(--radius);
        box-shadow: 0 20px 40px -12px rgba(79, 70, 229, 0.18), 0 4px 12px rgba(0,0,0,0.04);
        padding: 40px 36px;
        animation: rise 0.4s ease;
    }
    @keyframes rise {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .auth-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--brand), #818cf8);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        box-shadow: 0 8px 16px -4px rgba(79, 70, 229, 0.4);
    }
    .auth-icon svg { width: 26px; height: 26px; stroke: #fff; }
    h1 {
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 6px;
    }
    .subtitle {
        color: var(--muted);
        font-size: 14px;
        margin: 0 0 28px;
    }
    .field { margin-bottom: 18px; }
    label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text);
    }
    input[type="text"], input[type="password"], input[type="email"] {
        width: 100%;
        padding: 11px 14px;
        border-radius: 10px;
        border: 1.5px solid var(--border);
        font-size: 14px;
        font-family: inherit;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        outline: none;
    }
    input:focus {
        border-color: var(--brand);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
    }
    .btn-primary {
        width: 100%;
        padding: 12px;
        border: none;
        border-radius: 10px;
        background: linear-gradient(135deg, var(--brand), var(--brand-dark));
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: transform 0.1s ease, box-shadow 0.15s ease;
        box-shadow: 0 8px 16px -6px rgba(79, 70, 229, 0.5);
        margin-top: 6px;
    }
    .btn-primary:hover { box-shadow: 0 10px 20px -6px rgba(79, 70, 229, 0.6); }
    .btn-primary:active { transform: scale(0.98); }
    .alert-error {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--error-bg);
        color: var(--error-text);
        border: 1px solid var(--error-border);
        border-radius: 10px;
        padding: 10px 12px;
        font-size: 13px;
        margin-bottom: 18px;
    }
    .alert-error svg { flex-shrink: 0; width: 16px; height: 16px; }
    .switch-link {
        text-align: center;
        margin-top: 22px;
        font-size: 13px;
        color: var(--muted);
    }
    .switch-link a {
        color: var(--brand);
        font-weight: 600;
        text-decoration: none;
    }
    .switch-link a:hover { text-decoration: underline; }
</style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </div>
        <h1>Welcome back</h1>
        <p class="subtitle">SIGN IN TO CLOUD TECH HOSTEL</p>

        <?php if ($error): ?>
            <div class="alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?php csrf_field(); ?>
            <div class="field">
                <label for="identifier">Username or Email</label>
                <input type="text" id="identifier" name="identifier" placeholder="e.g. jdoe or jdoe@gctu.edu.gh" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn-primary">Sign in</button>
        </form>

        <p class="switch-link">New student? <a href="register.php">Create an account</a></p>
    </div>
</body>
</html>
