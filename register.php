<?php
// ============================================
// GCTU 9 Hostel — student Self-Registration
// Creates a student profile + a users login (Role = student)
// ============================================
require 'security.php';
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'Admin' ? 'index.php' : 'student_dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify(false);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $program   = trim($_POST['program'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if ($firstName === '' || $lastName === '' || $phone === '' || $program === '' ||
        $username === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check for duplicates across both tables
        $check = $conn->prepare("SELECT UserID FROM users WHERE Username = ? OR Email = ? LIMIT 1");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();
        $usernameTaken = $check->num_rows > 0;
        $check->close();

        $check2 = $conn->prepare("SELECT StudentID FROM student WHERE Phone = ? OR Email = ? LIMIT 1");
        $check2->bind_param("ss", $phone, $email);
        $check2->execute();
        $check2->store_result();
        $studentExists = $check2->num_rows > 0;
        $check2->close();

        if ($usernameTaken) {
            $error = 'That username or email is already registered.';
        } elseif ($studentExists) {
            $error = 'A student record with that phone or email already exists.';
        } else {
            // Generate the next StudentID (STU001, STU002, ...)
            $result = $conn->query("SELECT StudentID FROM student ORDER BY CAST(SUBSTRING(StudentID, 4) AS UNSIGNED) DESC LIMIT 1");
            $lastRow = $result ? $result->fetch_assoc() : null;
            $nextNum = $lastRow ? ((int)substr($lastRow['StudentID'], 3)) + 1 : 1;
            $newStudentID = 'STU' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

            $conn->begin_transaction();
            try {
                $insertStudent = $conn->prepare("INSERT INTO student (StudentID, FirstName, LastName, Phone, Email, Program) VALUES (?, ?, ?, ?, ?, ?)");
                $insertStudent->bind_param("ssssss", $newStudentID, $firstName, $lastName, $phone, $email, $program);
                $insertStudent->execute();
                $insertStudent->close();

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insertUser = $conn->prepare("INSERT INTO users (Username, Email, PasswordHash, Role, StudentID) VALUES (?, ?, ?, 'student', ?)");
                $insertUser->bind_param("ssss", $username, $email, $hashed, $newStudentID);
                $insertUser->execute();
                $newUserId = $insertUser->insert_id;
                $insertUser->close();

                $conn->commit();

                session_regenerate_id(true);
                $_SESSION['user_id']    = $newUserId;
                $_SESSION['username']   = $username;
                $_SESSION['role']       = 'student';
                $_SESSION['student_id'] = $newStudentID;

                header("Location: student_dashboard.php");
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Something went wrong while creating your account. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>student Sign Up — CLOUD TECH HOSTEL</title>
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
        max-width: 460px;
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
    h1 { font-size: 22px; font-weight: 700; margin: 0 0 6px; }
    .subtitle { color: var(--muted); font-size: 14px; margin: 0 0 24px; }
    .row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .field { margin-bottom: 16px; }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
    input[type="text"], input[type="password"], input[type="email"], input[type="tel"] {
        width: 100%;
        padding: 10px 13px;
        border-radius: 10px;
        border: 1.5px solid var(--border);
        font-size: 14px;
        font-family: inherit;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12); }
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
        box-shadow: 0 8px 16px -6px rgba(79, 70, 229, 0.5);
        margin-top: 6px;
    }
    .btn-primary:active { transform: scale(0.98); }
    .alert-error {
        display: flex; align-items: center; gap: 8px;
        background: var(--error-bg); color: var(--error-text);
        border: 1px solid var(--error-border); border-radius: 10px;
        padding: 10px 12px; font-size: 13px; margin-bottom: 18px;
    }
    .alert-error svg { flex-shrink: 0; width: 16px; height: 16px; }
    .switch-link { text-align: center; margin-top: 20px; font-size: 13px; color: var(--muted); }
    .switch-link a { color: var(--brand); font-weight: 600; text-decoration: none; }
    .switch-link a:hover { text-decoration: underline; }
</style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5-10-5zM6 12v5c3 3 9 3 12 0v-5"/>
            </svg>
        </div>
        <h1>student Sign Up</h1>
        <p class="subtitle">Create your account to apply for a room</p>

        <?php if ($error): ?>
            <div class="alert-error">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
                </svg>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <?php csrf_field(); ?>
            <div class="row-2">
                <div class="field">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="field">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
            </div>
            <div class="field">
                <label for="program">Program</label>
                <input type="text" id="program" name="program" placeholder="e.g. BSc Computer Science" required>
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" placeholder="0244000000" required>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="you@gctu.edu.gh" required>
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="row-2">
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
            </div>
            <button type="submit" class="btn-primary">Create account</button>
        </form>

        <p class="switch-link">Already have an account? <a href="login.php">Sign in</a></p>
    </div>
</body>
</html>
