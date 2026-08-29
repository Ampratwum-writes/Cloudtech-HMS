<?php
// ============================================
// Paste this at the VERY TOP of every ADMIN page
// (students.php, rooms.php, bookings.php, payments.php,
//  staff.php, maintenance.php, visitors.php, index.php...)
// It must come before any HTML output.
// Blocks anyone not logged in AND anyone not an Admin
// (so students can't reach these pages even if logged in).
// ============================================
require 'security.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
require 'config.php';
?>

<?php
/* ============================================
   GCTU 9 Hostel - Dashboard
   Overview across all 7 entities
   ============================================ */
require "config.php";

$totalStudents = $conn->query("SELECT COUNT(*) c FROM student")->fetch_assoc()['c'];
$totalRooms = $conn->query("SELECT COUNT(*) c FROM room")->fetch_assoc()['c'];
$activeBookings = $conn->query("SELECT COUNT(*) c FROM booking WHERE CheckOutDate IS NULL OR CheckOutDate >= CURDATE()")->fetch_assoc()['c'];
$pendingMaintenance = $conn->query("SELECT COUNT(*) c FROM maintenance WHERE Status = 'Pending'")->fetch_assoc()['c'];
$totalStaff = $conn->query("SELECT COUNT(*) c FROM staff")->fetch_assoc()['c'];
$onPremises = $conn->query("SELECT COUNT(*) c FROM visitor WHERE TimeOut IS NULL")->fetch_assoc()['c'];
$totalCollected = $conn->query("SELECT COALESCE(SUM(AmountPaid),0) s FROM payment")->fetch_assoc()['s'];
$totalOutstanding = $conn->query("SELECT COALESCE(SUM(OutstandingBalance),0) s FROM payment")->fetch_assoc()['s'];

$recentBookings = $conn->query(
    "SELECT b.BookingID, s.FirstName, s.LastName, r.RoomNumber, b.CheckInDate
     FROM booking b JOIN student s ON b.StudentID = s.StudentID JOIN room r ON b.RoomID = r.RoomID
     ORDER BY b.BookingID DESC LIMIT 5"
);
$recentPayments = $conn->query(
    "SELECT p.PaymentID, s.FirstName, s.LastName, p.AmountPaid, p.PaymentMethod, p.PaymentDate
     FROM payment p JOIN booking b ON p.BookingID = b.BookingID JOIN student s ON b.StudentID = s.StudentID
     ORDER BY p.PaymentID DESC LIMIT 5"
);

$pageTitle = "Dashboard";
$pageSubtitle = "CLOUD TECH — overview across all records";
$activeNav = "dashboard";
require "partials/header.php";
?>

<div class="content">

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg></div></div>
            <div class="stat-number"><?= (int)$totalStudents ?></div>
            <div class="stat-label">Registered Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 4l9 6.5"/><path d="M5 9.5V20h14V9.5"/></svg></div></div>
            <div class="stat-number"><?= (int)$totalRooms ?></div>
            <div class="stat-label">Total Rooms</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 9h18"/></svg></div></div>
            <div class="stat-number"><?= (int)$activeBookings ?></div>
            <div class="stat-label">Active Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg></div></div>
            <div class="stat-number"><?= (int)$pendingMaintenance ?></div>
            <div class="stat-label">Pending maintenance</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg></div></div>
            <div class="stat-number"><?= (int)$totalStaff ?></div>
            <div class="stat-label">Hostel staff</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div></div>
            <div class="stat-number"><?= (int)$onPremises ?></div>
            <div class="stat-label">Visitors On Premises</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div></div>
            <div class="stat-number">GHS <?= number_format($totalCollected, 0) ?></div>
            <div class="stat-label">Total Collected</div>
        </div>
        <div class="stat-card">
            <div class="stat-top"><div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg></div></div>
            <div class="stat-number">GHS <?= number_format($totalOutstanding, 0) ?></div>
            <div class="stat-label">Outstanding Balance</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">

        <div class="card">
            <div class="card-header">
                <h2>Recent Bookings</h2>
                <a href="bookings.php" class="btn-ghost">View all</a>
            </div>
            <table>
                <thead><tr><th>student</th><th>room</th><th>Check-In</th></tr></thead>
                <tbody>
                    <?php if ($recentBookings->num_rows > 0): ?>
                        <?php while ($b = $recentBookings->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($b['FirstName'] . " " . $b['LastName']) ?></td>
                                <td><?= htmlspecialchars($b['RoomNumber']) ?></td>
                                <td><?= htmlspecialchars($b['CheckInDate']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><div class="empty-state">No bookings yet.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Recent Payments</h2>
                <a href="payments.php" class="btn-ghost">View all</a>
            </div>
            <table>
                <thead><tr><th>student</th><th>Amount</th><th>Method</th></tr></thead>
                <tbody>
                    <?php if ($recentPayments->num_rows > 0): ?>
                        <?php while ($p = $recentPayments->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['FirstName'] . " " . $p['LastName']) ?></td>
                                <td>GHS <?= number_format((float)$p['AmountPaid'], 2) ?></td>
                                <td><?= htmlspecialchars($p['PaymentMethod']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3"><div class="empty-state">No payments yet.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<?php require "partials/footer.php"; $conn->close(); ?>
