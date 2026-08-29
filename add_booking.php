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
   GCTU 9 Hostel - Add New booking
   Phase 8: Data Entry (inserts a new record)
   ============================================ */
require "config.php";

$message = "";
$messageType = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify(false);
    $studentID   = $_POST['student_id'];
    $roomID      = $_POST['room_id'];
    $semester    = trim($_POST['semester']);
    $checkIn     = $_POST['check_in'];
    $checkOut    = $_POST['check_out'] !== "" ? $_POST['check_out'] : NULL;

    $stmt = $conn->prepare(
        "INSERT INTO booking (StudentID, RoomID, Semester, CheckInDate, CheckOutDate)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("sisss", $studentID, $roomID, $semester, $checkIn, $checkOut);

    if ($stmt->execute()) {
        $message = "booking added successfully.";
        $messageType = "success";
    } else {
        $message = "Error: " . $stmt->error;
        $messageType = "error";
    }
    $stmt->close();
}

// Fetch students for dropdown
$students = $conn->query("SELECT StudentID, FirstName, LastName FROM student ORDER BY FirstName");

// Fetch rooms for dropdown
$rooms = $conn->query("SELECT RoomID, RoomNumber, Block FROM room ORDER BY RoomNumber");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add booking - GCTU 9 Hostel</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Cloud Tech Hostel Management System</h1>
    <p>staff Dashboard</p>
</header>

<nav>
    <a href="index.php">Bookings Dashboard</a>
    <a href="add_booking.php">Add New booking</a>
</nav>

<div class="container">
    <h2>Add New booking</h2>

    <?php if ($message): ?>
        <div class="msg-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="form-box">
        <form method="POST" action="add_booking.php">
            <?php csrf_field(); ?>

            <label for="student_id">student</label>
            <select name="student_id" id="student_id" required>
                <option value="">-- Select student --</option>
                <?php while ($s = $students->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($s['StudentID']) ?>">
                        <?= htmlspecialchars($s['StudentID'] . " - " . $s['FirstName'] . " " . $s['LastName']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="room_id">room</label>
            <select name="room_id" id="room_id" required>
                <option value="">-- Select room --</option>
                <?php while ($r = $rooms->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($r['RoomID']) ?>">
                        <?= htmlspecialchars($r['RoomNumber'] . " (Block " . $r['Block'] . ")") ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="semester">Semester</label>
            <input type="text" name="semester" id="semester" placeholder="e.g. 2025/2026 Semester 1" required>

            <label for="check_in">Check-In Date</label>
            <input type="date" name="check_in" id="check_in" required>

            <label for="check_out">Check-Out Date (optional)</label>
            <input type="date" name="check_out" id="check_out">

            <button type="submit">Add booking</button>
        </form>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
