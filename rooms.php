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
   GCTU 9 Hostel - Rooms
   ============================================ */
require "config.php";

function render_room_row($row, $rowNum = null) {
    $typeChip = $row['RoomType'] === 'Single' ? 'chip-navy' : 'chip-gold';
    ob_start();
    ?>
    <tr>
        <td class="row-num"><?= $rowNum !== null ? (int)$rowNum : '' ?></td>
        <td class="id-mono">#<?= htmlspecialchars($row['RoomID']) ?></td>
        <td><?= htmlspecialchars($row['RoomNumber']) ?></td>
        <td><?= htmlspecialchars($row['Block']) ?></td>
        <td><?= htmlspecialchars($row['Floor']) ?></td>
        <td><?= htmlspecialchars($row['Capacity']) ?></td>
        <td><span class="chip <?= $typeChip ?>"><?= htmlspecialchars($row['RoomType']) ?></span></td>
        <td>
            <button class="btn-ghost danger" data-quick-action="delete" data-id="<?= (int)$row['RoomID'] ?>"
                    data-confirm="Delete room <?= htmlspecialchars(addslashes($row['RoomNumber'])) ?>? This cannot be undone.">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                Delete
            </button>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// ---------- AJAX: delete room ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'delete') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("DELETE FROM room WHERE RoomID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) c FROM room")->fetch_assoc()['c'];
            echo json_encode(["success" => true, "removed" => true, "message" => "room deleted.", "count" => (int)$count]);
        } else {
            echo json_encode(["success" => false, "error" => "room not found — may already be deleted."]);
        }
    } else {
        if ($stmt->errno === 1451) {
            echo json_encode(["success" => false, "error" => "Can't delete this room — it still has bookings linked to it. Delete those bookings first."]);
        } else {
            echo json_encode(["success" => false, "error" => "Could not delete: " . $stmt->error]);
        }
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: add room ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_add_room'])) {
    header('Content-Type: application/json');
    csrf_verify(true);

    $number = trim($_POST['room_number']);
    $block = trim($_POST['block']);
    $floor = trim($_POST['floor']);
    $capacity = trim($_POST['capacity']);
    $type = $_POST['room_type'];

    if ($number === '' || $block === '' || $floor === '' || $capacity === '' || !in_array($type, ['Single', 'Shared'])) {
        echo json_encode(["success" => false, "error" => "Please fill in all fields correctly."]);
        exit;
    }
    if ((int)$capacity <= 0) {
        echo json_encode(["success" => false, "error" => "Capacity must be greater than zero."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO room (RoomNumber, Block, Floor, Capacity, RoomType) VALUES (?, ?, ?, ?, ?)");
    $floorInt = (int)$floor;
    $capInt = (int)$capacity;
    $stmt->bind_param("ssiis", $number, $block, $floorInt, $capInt, $type);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $rowHtml = render_room_row([
            'RoomID' => $newId, 'RoomNumber' => $number, 'Block' => $block,
            'Floor' => $floorInt, 'Capacity' => $capInt, 'RoomType' => $type
        ]);
        $count = $conn->query("SELECT COUNT(*) c FROM room")->fetch_assoc()['c'];
        echo json_encode(["success" => true, "message" => "room added.", "rowHtml" => $rowHtml, "count" => (int)$count]);
    } else {
        $err = $stmt->errno === 1062 ? "That room number already exists." : "Could not save: " . $stmt->error;
        echo json_encode(["success" => false, "error" => $err]);
    }
    $stmt->close();
    exit;
}

$rooms = $conn->query("SELECT * FROM room ORDER BY Block, RoomNumber");
$totalRooms = $rooms->num_rows;

$pageTitle = "Rooms";
$pageSubtitle = "All hostel rooms and their capacity";
$activeNav = "rooms";
require "partials/header.php";
?>

<div class="content">

    <div class="card">
        <div class="card-header">
            <div>
                <h2>room Records</h2>
                <div class="count-pill" id="rowCount"><?= (int)$totalRooms ?> shown</div>
            </div>
            <div class="page-actions">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-search-target="roomsTable" data-count-target="rowCount" placeholder="Search rooms...">
                </div>
                <button class="btn-add" data-modal-open="addRoomModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Add room
                </button>
            </div>
        </div>

        <table id="roomsTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="sortable">room ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">room Number <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Block <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Floor <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Capacity <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Type <span class="sort-arrow">↕</span></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rooms->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $rooms->fetch_assoc()): ?>
                        <?= render_room_row($row, $i++) ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8"><div class="empty-state">No rooms yet. Add one to get started.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add room Modal -->
<div class="modal-overlay" id="addRoomModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New room</h3>
            <button class="modal-close" data-modal-close>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form class="ajax-form" method="POST" action="rooms.php" data-target="roomsTable">
            <?php csrf_field(); ?>
            <input type="hidden" name="ajax_add_room" value="1">
            <div class="modal-body">
                <div class="form-grid" style="padding:0; grid-template-columns: 1fr 1fr;">
                    <div class="field">
                        <label for="room_number">room Number</label>
                        <input type="text" name="room_number" id="room_number" placeholder="e.g. C101" required>
                    </div>
                    <div class="field">
                        <label for="block">Block</label>
                        <input type="text" name="block" id="block" placeholder="e.g. C" required>
                    </div>
                    <div class="field">
                        <label for="floor">Floor</label>
                        <input type="number" name="floor" id="floor" min="0" required>
                    </div>
                    <div class="field">
                        <label for="capacity">Capacity</label>
                        <input type="number" name="capacity" id="capacity" min="1" required>
                    </div>
                    <div class="field full">
                        <label for="room_type">room Type</label>
                        <select name="room_type" id="room_type" required>
                            <option value="Single">Single</option>
                            <option value="Shared">Shared</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn-add">Save room</button>
            </div>
        </form>
    </div>
</div>

<?php require "partials/footer.php"; $conn->close(); ?>
