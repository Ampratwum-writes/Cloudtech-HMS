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
   GCTU 9 Hostel - Bookings
   ============================================ */
require "config.php";

function render_booking_row($row, $rowNum = null) {
    $isActive = empty($row['CheckOutDate']) || $row['CheckOutDate'] >= date('Y-m-d');
    ob_start();
    ?>
    <tr>
        <td class="row-num"><?= $rowNum !== null ? (int)$rowNum : '' ?></td>
        <td class="id-mono">#<?= htmlspecialchars($row['BookingID']) ?></td>
        <td class="id-mono"><?= htmlspecialchars($row['StudentID']) ?></td>
        <td><?= htmlspecialchars($row['FirstName'] . " " . $row['LastName']) ?></td>
        <td><?= htmlspecialchars($row['RoomNumber']) ?></td>
        <td><?= htmlspecialchars($row['Block']) ?></td>
        <td><?= htmlspecialchars($row['Semester']) ?></td>
        <td><?= htmlspecialchars($row['CheckInDate']) ?></td>
        <td><?= htmlspecialchars($row['CheckOutDate'] ?? '—') ?></td>
        <td><?php if ($isActive): ?><span class="badge badge-active">Active</span><?php else: ?><span class="badge badge-ended">Ended</span><?php endif; ?></td>
        <td>
            <button class="btn-ghost danger" data-quick-action="delete" data-id="<?= (int)$row['BookingID'] ?>"
                    data-confirm="Delete booking #<?= (int)$row['BookingID'] ?> for <?= htmlspecialchars(addslashes($row['FirstName'] . " " . $row['LastName'])) ?>? This will also delete any payments linked to this booking. This cannot be undone.">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                Delete
            </button>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// ---------- AJAX: delete booking ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'delete') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("DELETE FROM booking WHERE BookingID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) c FROM booking")->fetch_assoc()['c'];
            echo json_encode(["success" => true, "removed" => true, "message" => "booking deleted.", "count" => (int)$count]);
        } else {
            echo json_encode(["success" => false, "error" => "booking not found — it may already be deleted."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Could not delete: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: add booking ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_add_booking'])) {
    header('Content-Type: application/json');
    csrf_verify(true);

    $studentID = $_POST['student_id'];
    $roomID = $_POST['room_id'];
    $semester = trim($_POST['semester']);
    $checkIn = $_POST['check_in'];
    $checkOut = $_POST['check_out'] !== "" ? $_POST['check_out'] : NULL;

    if ($studentID === '' || $roomID === '' || $semester === '' || $checkIn === '') {
        echo json_encode(["success" => false, "error" => "Please fill in all required fields."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO booking (StudentID, RoomID, Semester, CheckInDate, CheckOutDate) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sisss", $studentID, $roomID, $semester, $checkIn, $checkOut);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $fetch = $conn->prepare(
            "SELECT b.BookingID, s.StudentID, s.FirstName, s.LastName, r.RoomNumber, r.Block, b.Semester, b.CheckInDate, b.CheckOutDate
             FROM booking b JOIN student s ON b.StudentID = s.StudentID JOIN room r ON b.RoomID = r.RoomID
             WHERE b.BookingID = ?"
        );
        $fetch->bind_param("i", $newId);
        $fetch->execute();
        $newRow = $fetch->get_result()->fetch_assoc();
        $rowHtml = render_booking_row($newRow);
        $count = $conn->query("SELECT COUNT(*) c FROM booking")->fetch_assoc()['c'];
        echo json_encode(["success" => true, "message" => "booking added.", "rowHtml" => $rowHtml, "count" => (int)$count]);
    } else {
        echo json_encode(["success" => false, "error" => "Could not save: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

$sql = "SELECT b.BookingID, s.StudentID, s.FirstName, s.LastName, r.RoomNumber, r.Block, b.Semester, b.CheckInDate, b.CheckOutDate
        FROM booking b JOIN student s ON b.StudentID = s.StudentID JOIN room r ON b.RoomID = r.RoomID
        ORDER BY b.BookingID DESC";
$bookings = $conn->query($sql);
$totalBookings = $bookings->num_rows;

$students = $conn->query("SELECT StudentID, FirstName, LastName FROM student ORDER BY FirstName");
$rooms = $conn->query("SELECT RoomID, RoomNumber, Block FROM room ORDER BY RoomNumber");

$pageTitle = "Bookings";
$pageSubtitle = "student room bookings by semester";
$activeNav = "bookings";
require "partials/header.php";
?>

<div class="content">

    <div class="card">
        <div class="card-header">
            <div>
                <h2>booking Records</h2>
                <div class="count-pill" id="rowCount"><?= (int)$totalBookings ?> shown</div>
            </div>
            <div class="page-actions">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-search-target="bookingsTable" data-count-target="rowCount" placeholder="Search bookings...">
                </div>
                <button class="btn-add" data-modal-open="addBookingModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Add booking
                </button>
            </div>
        </div>

        <table id="bookingsTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="sortable">booking ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">student ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">student Name <span class="sort-arrow">↕</span></th>
                    <th class="sortable">room <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Block <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Semester <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Check-In <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Check-Out <span class="sort-arrow">↕</span></th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($bookings->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $bookings->fetch_assoc()): ?>
                        <?= render_booking_row($row, $i++) ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="11"><div class="empty-state">No bookings yet. Add one to get started.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add booking Modal -->
<div class="modal-overlay" id="addBookingModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New booking</h3>
            <button class="modal-close" data-modal-close>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form class="ajax-form" method="POST" action="bookings.php" data-target="bookingsTable">
            <?php csrf_field(); ?>
            <input type="hidden" name="ajax_add_booking" value="1">
            <div class="modal-body">
                <div class="form-grid" style="padding:0; grid-template-columns: 1fr 1fr;">
                    <div class="field full">
                        <label for="student_id">student</label>
                        <select name="student_id" id="student_id" required>
                            <option value="">Select a student…</option>
                            <?php while ($s = $students->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($s['StudentID']) ?>"><?= htmlspecialchars($s['StudentID'] . " — " . $s['FirstName'] . " " . $s['LastName']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="room_id">room</label>
                        <select name="room_id" id="room_id" required>
                            <option value="">Select a room…</option>
                            <?php while ($r = $rooms->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($r['RoomID']) ?>"><?= htmlspecialchars($r['RoomNumber'] . " — Block " . $r['Block']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="semester">Semester</label>
                        <input type="text" name="semester" id="semester" placeholder="e.g. 2025/2026 Semester 1" required>
                    </div>
                    <div class="field">
                        <label for="check_in">Check-In Date</label>
                        <input type="date" name="check_in" id="check_in" required>
                    </div>
                    <div class="field">
                        <label for="check_out">Check-Out Date</label>
                        <input type="date" name="check_out" id="check_out">
                        <div class="hint">Optional</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn-add">Save booking</button>
            </div>
        </form>
    </div>
</div>

<?php require "partials/footer.php"; $conn->close(); ?>
