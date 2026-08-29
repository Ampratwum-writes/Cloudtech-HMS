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
   GCTU 9 Hostel - staff
   ============================================ */
require "config.php";

function render_staff_row($row, $rowNum = null) {
    $roleChip = ['Warden' => 'chip-navy', 'Porter' => 'chip-gold', 'Cleaner' => 'chip-muted'][$row['Role']] ?? 'chip-muted';
    ob_start();
    ?>
    <tr>
        <td class="row-num"><?= $rowNum !== null ? (int)$rowNum : '' ?></td>
        <td class="id-mono"><?= htmlspecialchars($row['StaffID']) ?></td>
        <td><?= htmlspecialchars($row['FirstName'] . " " . $row['LastName']) ?></td>
        <td><span class="chip <?= $roleChip ?>"><?= htmlspecialchars($row['Role']) ?></span></td>
        <td><?= htmlspecialchars($row['Phone']) ?></td>
        <td>
            <button class="btn-ghost danger" data-quick-action="delete" data-id="<?= htmlspecialchars($row['StaffID']) ?>"
                    data-confirm="Delete <?= htmlspecialchars(addslashes($row['FirstName'] . ' ' . $row['LastName'])) ?>? Any maintenance requests assigned to them will become unassigned. This cannot be undone.">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                Delete
            </button>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// ---------- AJAX: delete staff ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'delete') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = $_POST['id'];

    $stmt = $conn->prepare("DELETE FROM staff WHERE StaffID = ?");
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) c FROM staff")->fetch_assoc()['c'];
            echo json_encode(["success" => true, "removed" => true, "message" => "staff member deleted.", "count" => (int)$count]);
        } else {
            echo json_encode(["success" => false, "error" => "staff member not found — may already be deleted."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Could not delete: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: add staff ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_add_staff'])) {
    header('Content-Type: application/json');
    csrf_verify(true);

    $id = trim($_POST['staff_id']);
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);

    if ($id === '' || $first === '' || $last === '' || $phone === '' || !in_array($role, ['Warden', 'Porter', 'Cleaner'])) {
        echo json_encode(["success" => false, "error" => "Please fill in all fields correctly."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO staff (StaffID, FirstName, LastName, Role, Phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $id, $first, $last, $role, $phone);

    if ($stmt->execute()) {
        $rowHtml = render_staff_row(['StaffID' => $id, 'FirstName' => $first, 'LastName' => $last, 'Role' => $role, 'Phone' => $phone]);
        $count = $conn->query("SELECT COUNT(*) c FROM staff")->fetch_assoc()['c'];
        echo json_encode(["success" => true, "message" => "staff member added.", "rowHtml" => $rowHtml, "count" => (int)$count]);
    } else {
        $err = $stmt->errno === 1062 ? "That staff ID or phone already exists." : "Could not save: " . $stmt->error;
        echo json_encode(["success" => false, "error" => $err]);
    }
    $stmt->close();
    exit;
}

$staff = $conn->query("SELECT * FROM staff ORDER BY FirstName");
$totalStaff = $staff->num_rows;

$pageTitle = "staff";
$pageSubtitle = "Wardens, porters, and cleaners on record";
$activeNav = "staff";
require "partials/header.php";
?>

<div class="content">

    <div class="card">
        <div class="card-header">
            <div>
                <h2>staff Records</h2>
                <div class="count-pill" id="rowCount"><?= (int)$totalStaff ?> shown</div>
            </div>
            <div class="page-actions">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-search-target="staffTable" data-count-target="rowCount" placeholder="Search staff...">
                </div>
                <button class="btn-add" data-modal-open="addStaffModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Add staff
                </button>
            </div>
        </div>

        <table id="staffTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="sortable">staff ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Name <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Role <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Phone <span class="sort-arrow">↕</span></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($staff->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $staff->fetch_assoc()): ?>
                        <?= render_staff_row($row, $i++) ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6"><div class="empty-state">No staff yet. Add one to get started.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add staff Modal -->
<div class="modal-overlay" id="addStaffModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New staff Member</h3>
            <button class="modal-close" data-modal-close>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form class="ajax-form" method="POST" action="staff.php" data-target="staffTable">
            <?php csrf_field(); ?>
            <input type="hidden" name="ajax_add_staff" value="1">
            <div class="modal-body">
                <div class="form-grid" style="padding:0; grid-template-columns: 1fr 1fr;">
                    <div class="field full">
                        <label for="staff_id">staff ID</label>
                        <input type="text" name="staff_id" id="staff_id" placeholder="e.g. STF004" required>
                    </div>
                    <div class="field">
                        <label for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" required>
                    </div>
                    <div class="field">
                        <label for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" required>
                    </div>
                    <div class="field">
                        <label for="role">Role</label>
                        <select name="role" id="role" required>
                            <option value="Warden">Warden</option>
                            <option value="Porter">Porter</option>
                            <option value="Cleaner">Cleaner</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" placeholder="0201000000" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn-add">Save staff</button>
            </div>
        </form>
    </div>
</div>

<?php require "partials/footer.php"; $conn->close(); ?>
