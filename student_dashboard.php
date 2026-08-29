<?php
// ============================================
// GCTU 9 Hostel — student Dashboard
// Students apply for rooms and track their status
// ============================================
require 'security.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit;
}
require 'config.php';

$studentId = $_SESSION['student_id'];
$message = '';
$error = '';

// Handle new application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_room_id'])) {
    csrf_verify(false);
    $roomId = (int)$_POST['apply_room_id'];
    $semester = trim($_POST['semester'] ?? '');

    if ($semester === '') {
        $error = 'Please specify the semester you are applying for.';
    } else {
        // Prevent duplicate pending applications for the same room
        $check = $conn->prepare("SELECT ApplicationID FROM roomapplication WHERE StudentID = ? AND RoomID = ? AND Status = 'Pending' LIMIT 1");
        $check->bind_param("si", $studentId, $roomId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'You already have a pending application for this room.';
            $check->close();
        } else {
            $check->close();
            $stmt = $conn->prepare("INSERT INTO roomapplication (StudentID, RoomID, Semester, ApplicationDate, Status) VALUES (?, ?, ?, CURDATE(), 'Pending')");
            $stmt->bind_param("sis", $studentId, $roomId, $semester);
            if ($stmt->execute()) {
                $message = 'Application submitted successfully. Await admin approval.';
            } else {
                $error = 'Could not submit your application. Please try again.';
            }
            $stmt->close();
        }
    }
}

// Available rooms: capacity not yet filled by active bookings
$roomsResult = $conn->query("
    SELECT r.RoomID, r.RoomNumber, r.Block, r.Floor, r.Capacity, r.RoomType,
           COALESCE(b.OccupiedCount, 0) AS OccupiedCount
    FROM room r
    LEFT JOIN (
        SELECT RoomID, COUNT(*) AS OccupiedCount
        FROM booking
        WHERE CheckOutDate IS NULL OR CheckOutDate > CURDATE()
        GROUP BY RoomID
    ) b ON r.RoomID = b.RoomID
    HAVING OccupiedCount < r.Capacity
    ORDER BY r.Block, r.RoomNumber
");

// This student's applications
$appsStmt = $conn->prepare("
    SELECT a.ApplicationID, a.Semester, a.ApplicationDate, a.Status, r.RoomNumber, r.Block
    FROM roomapplication a
    JOIN room r ON a.RoomID = r.RoomID
    WHERE a.StudentID = ?
    ORDER BY a.ApplicationDate DESC
");
$appsStmt->bind_param("s", $studentId);
$appsStmt->execute();
$applications = $appsStmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard — CLOUD TECH HOSTEL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --brand: #4f46e5;
        --brand-dark: #4338ca;
        --bg: #f5f6fb;
        --text: #1e1b2e;
        --muted: #6b7280;
        --border: #e5e7eb;
        --success-bg: #ecfdf5; --success-text: #047857; --success-border: #a7f3d0;
        --error-bg: #fef2f2; --error-text: #b91c1c; --error-border: #fecaca;
        --pending-bg: #fffbeb; --pending-text: #b45309;
        --approved-bg: #ecfdf5; --approved-text: #047857;
        --rejected-bg: #fef2f2; --rejected-text: #b91c1c;
        --radius: 14px;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Inter', system-ui, sans-serif;
        background: var(--bg);
        color: var(--text);
    }
    .topbar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 18px 32px;
        background: #fff;
        border-bottom: 1px solid var(--border);
    }
    .topbar .brand { font-weight: 700; font-size: 16px; display: flex; align-items: center; gap: 10px; }
    .topbar .brand-icon {
        width: 32px; height: 32px; border-radius: 9px;
        background: linear-gradient(135deg, var(--brand), #818cf8);
        display: flex; align-items: center; justify-content: center;
    }
    .topbar .brand-icon svg { width: 17px; height: 17px; stroke: #fff; }
    .topbar .user-info { display: flex; align-items: center; gap: 16px; font-size: 13px; color: var(--muted); }
    .topbar .user-info a { color: var(--brand); font-weight: 600; text-decoration: none; }
    .container { max-width: 1000px; margin: 0 auto; padding: 32px 24px; }
    h2 { font-size: 18px; margin: 0 0 4px; }
    .section-sub { color: var(--muted); font-size: 13px; margin: 0 0 18px; }
    .banner {
        display: flex; align-items: center; gap: 8px;
        border-radius: 10px; padding: 12px 14px; font-size: 13px; margin-bottom: 20px;
    }
    .banner-success { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
    .banner-error { background: var(--error-bg); color: var(--error-text); border: 1px solid var(--error-border); }
    .room-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 16px; margin-bottom: 40px;
    }
    .room-card {
        background: #fff; border: 1px solid var(--border); border-radius: var(--radius);
        padding: 18px; box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }
    .room-card .room-number { font-size: 16px; font-weight: 700; margin-bottom: 2px; }
    .room-card .room-meta { font-size: 12px; color: var(--muted); margin-bottom: 12px; }
    .room-card .room-type {
        display: inline-block; font-size: 11px; font-weight: 600;
        background: #eef2ff; color: var(--brand); padding: 2px 8px; border-radius: 999px;
        margin-bottom: 12px;
    }
    .room-card select, .room-card input[type="text"] {
        width: 100%; padding: 8px 10px; border-radius: 8px; border: 1.5px solid var(--border);
        font-size: 13px; font-family: inherit; margin-bottom: 8px;
    }
    .btn-apply {
        width: 100%; padding: 9px; border: none; border-radius: 8px;
        background: linear-gradient(135deg, var(--brand), var(--brand-dark));
        color: #fff; font-size: 13px; font-weight: 600; font-family: inherit; cursor: pointer;
    }
    .btn-apply:active { transform: scale(0.98); }
    table { width: 100%; border-collapse: collapse; background: #fff; border-radius: var(--radius); overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.03); }
    th, td { text-align: left; padding: 12px 16px; font-size: 13px; border-bottom: 1px solid var(--border); }
    th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.02em; }
    tr:last-child td { border-bottom: none; }
    .status-pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
    .status-Pending { background: var(--pending-bg); color: var(--pending-text); }
    .status-Approved { background: var(--approved-bg); color: var(--approved-text); }
    .status-Rejected { background: var(--rejected-bg); color: var(--rejected-text); }
    .empty-state { text-align: center; color: var(--muted); padding: 24px; font-size: 13px; }
</style>
</head>
<body>
    <div class="topbar">
        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                </svg>
            </div>
            CLOUD TECH HOSTEL
        </div>
        <div class="user-info">
            Signed in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
            <a href="logout.php">Log out</a>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="banner banner-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="banner banner-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h2>Available Rooms</h2>
        <p class="section-sub">Apply for a room below — an admin will review and approve your application.</p>

        <div class="room-grid">
            <?php if ($roomsResult && $roomsResult->num_rows > 0): ?>
                <?php while ($room = $roomsResult->fetch_assoc()): ?>
                    <div class="room-card">
                        <div class="room-number">room <?php echo htmlspecialchars($room['RoomNumber']); ?></div>
                        <div class="room-meta">Block <?php echo htmlspecialchars($room['Block']); ?> · Floor <?php echo htmlspecialchars($room['Floor']); ?> · <?php echo (int)$room['OccupiedCount']; ?>/<?php echo (int)$room['Capacity']; ?> occupied</div>
                        <span class="room-type"><?php echo htmlspecialchars($room['RoomType']); ?></span>
                        <form method="POST" action="student_dashboard.php">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="apply_room_id" value="<?php echo (int)$room['RoomID']; ?>">
                            <input type="text" name="semester" placeholder="e.g. 2025/2026 Semester 2" required>
                            <button type="submit" class="btn-apply">Apply</button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">No available rooms right now — check back later.</div>
            <?php endif; ?>
        </div>

        <h2>My Applications</h2>
        <p class="section-sub">Track the status of the rooms you've applied for.</p>

        <table>
            <thead>
                <tr>
                    <th>room</th>
                    <th>Semester</th>
                    <th>Date Applied</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($applications->num_rows > 0): ?>
                    <?php while ($app = $applications->fetch_assoc()): ?>
                        <tr>
                            <td>Block <?php echo htmlspecialchars($app['Block']); ?> — <?php echo htmlspecialchars($app['RoomNumber']); ?></td>
                            <td><?php echo htmlspecialchars($app['Semester']); ?></td>
                            <td><?php echo htmlspecialchars($app['ApplicationDate']); ?></td>
                            <td><span class="status-pill status-<?php echo htmlspecialchars($app['Status']); ?>"><?php echo htmlspecialchars($app['Status']); ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state">You haven't applied for any rooms yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
