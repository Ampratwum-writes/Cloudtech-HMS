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
   GCTU 9 Hostel - maintenance
   ============================================ */
require "config.php";

function render_maintenance_row($row, $rowNum = null) {
    $isPending = $row['Status'] === 'Pending';
    ob_start();
    ?>
    <tr>
        <td class="row-num"><?= $rowNum !== null ? (int)$rowNum : '' ?></td>
        <td class="id-mono">#<?= htmlspecialchars($row['MaintenanceID']) ?></td>
        <td><?= htmlspecialchars($row['RoomNumber']) ?></td>
        <td><?= htmlspecialchars($row['IssueDescription']) ?></td>
        <td><?= htmlspecialchars($row['StaffName'] ?: 'Unassigned') ?></td>
        <td><?= htmlspecialchars($row['DateReported']) ?></td>
        <td>
            <?php if ($isPending): ?>
                <span class="badge badge-pending">Pending</span>
            <?php else: ?>
                <span class="badge badge-resolved">Resolved</span>
            <?php endif; ?>
        </td>
        <td>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if ($isPending): ?>
                    <button class="btn-ghost success" data-quick-action="resolve" data-id="<?= (int)$row['MaintenanceID'] ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                        Mark Resolved
                    </button>
                <?php endif; ?>
                <button class="btn-ghost danger" data-quick-action="delete" data-id="<?= (int)$row['MaintenanceID'] ?>"
                        data-confirm="Delete this maintenance record for <?= htmlspecialchars(addslashes($row['RoomNumber'])) ?>? This cannot be undone.">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                    Delete
                </button>
            </div>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// ---------- AJAX: delete maintenance record ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'delete') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("DELETE FROM maintenance WHERE MaintenanceID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) c FROM maintenance")->fetch_assoc()['c'];
            echo json_encode(["success" => true, "removed" => true, "message" => "maintenance record deleted.", "count" => (int)$count]);
        } else {
            echo json_encode(["success" => false, "error" => "Record not found — may already be deleted."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Could not delete: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: quick action (mark resolved) ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'resolve') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("UPDATE maintenance SET Status = 'Resolved' WHERE MaintenanceID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $fetch = $conn->prepare(
            "SELECT m.MaintenanceID, r.RoomNumber, m.IssueDescription, m.DateReported, m.Status,
                    CONCAT(st.FirstName, ' ', st.LastName) AS StaffName
             FROM maintenance m JOIN room r ON m.RoomID = r.RoomID
             LEFT JOIN staff st ON m.StaffID = st.StaffID
             WHERE m.MaintenanceID = ?"
        );
        $fetch->bind_param("i", $id);
        $fetch->execute();
        $row = $fetch->get_result()->fetch_assoc();
        echo json_encode(["success" => true, "message" => "Marked as resolved.", "rowHtml" => render_maintenance_row($row)]);
    } else {
        echo json_encode(["success" => false, "error" => "Could not update: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: add maintenance request ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_add_maintenance'])) {
    header('Content-Type: application/json');
    csrf_verify(true);

    $roomID = $_POST['room_id'];
    $staffID = $_POST['staff_id'] !== "" ? $_POST['staff_id'] : NULL;
    $issue = trim($_POST['issue_description']);
    $date = $_POST['date_reported'];

    if ($roomID === '' || $issue === '' || $date === '') {
        echo json_encode(["success" => false, "error" => "Please fill in all required fields."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO maintenance (RoomID, StaffID, IssueDescription, DateReported, Status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isss", $roomID, $staffID, $issue, $date);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $fetch = $conn->prepare(
            "SELECT m.MaintenanceID, r.RoomNumber, m.IssueDescription, m.DateReported, m.Status,
                    CONCAT(st.FirstName, ' ', st.LastName) AS StaffName
             FROM maintenance m JOIN room r ON m.RoomID = r.RoomID
             LEFT JOIN staff st ON m.StaffID = st.StaffID
             WHERE m.MaintenanceID = ?"
        );
        $fetch->bind_param("i", $newId);
        $fetch->execute();
        $newRow = $fetch->get_result()->fetch_assoc();
        $rowHtml = render_maintenance_row($newRow);
        $count = $conn->query("SELECT COUNT(*) c FROM maintenance")->fetch_assoc()['c'];
        echo json_encode(["success" => true, "message" => "maintenance request logged.", "rowHtml" => $rowHtml, "count" => (int)$count]);
    } else {
        echo json_encode(["success" => false, "error" => "Could not save: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

$sql = "SELECT m.MaintenanceID, r.RoomNumber, m.IssueDescription, m.DateReported, m.Status,
               CONCAT(st.FirstName, ' ', st.LastName) AS StaffName
        FROM maintenance m JOIN room r ON m.RoomID = r.RoomID
        LEFT JOIN staff st ON m.StaffID = st.StaffID
        ORDER BY (m.Status = 'Pending') DESC, m.MaintenanceID DESC";
$maintenance = $conn->query($sql);
$totalMaintenance = $maintenance->num_rows;

$roomOptions = $conn->query("SELECT RoomID, RoomNumber, Block FROM room ORDER BY RoomNumber");
$staffOptions = $conn->query("SELECT StaffID, FirstName, LastName FROM staff ORDER BY FirstName");

$pageTitle = "maintenance";
$pageSubtitle = "Reported issues and repair status";
$activeNav = "maintenance";
require "partials/header.php";
?>

<div class="content">

    <div class="card">
        <div class="card-header">
            <div>
                <h2>maintenance Requests</h2>
                <div class="count-pill" id="rowCount"><?= (int)$totalMaintenance ?> shown</div>
            </div>
            <div class="page-actions">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-search-target="maintenanceTable" data-count-target="rowCount" placeholder="Search issues...">
                </div>
                <button class="btn-add" data-modal-open="addMaintenanceModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Report Issue
                </button>
            </div>
        </div>

        <table id="maintenanceTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="sortable">ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">room <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Issue <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Assigned staff <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Date Reported <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Status <span class="sort-arrow">↕</span></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($maintenance->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $maintenance->fetch_assoc()): ?>
                        <?= render_maintenance_row($row, $i++) ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8"><div class="empty-state">No maintenance requests logged yet.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add maintenance Modal -->
<div class="modal-overlay" id="addMaintenanceModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Report maintenance Issue</h3>
            <button class="modal-close" data-modal-close>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form class="ajax-form" method="POST" action="maintenance.php" data-target="maintenanceTable">
            <?php csrf_field(); ?>
            <input type="hidden" name="ajax_add_maintenance" value="1">
            <div class="modal-body">
                <div class="form-grid" style="padding:0; grid-template-columns: 1fr 1fr;">
                    <div class="field full">
                        <label for="room_id">room</label>
                        <select name="room_id" id="room_id" required>
                            <option value="">Select a room…</option>
                            <?php while ($r = $roomOptions->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($r['RoomID']) ?>"><?= htmlspecialchars($r['RoomNumber'] . " — Block " . $r['Block']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="issue_description">Issue Description</label>
                        <input type="text" name="issue_description" id="issue_description" placeholder="e.g. Broken window latch" required>
                    </div>
                    <div class="field">
                        <label for="staff_id">Assign staff (optional)</label>
                        <select name="staff_id" id="staff_id">
                            <option value="">Unassigned</option>
                            <?php while ($s = $staffOptions->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($s['StaffID']) ?>"><?= htmlspecialchars($s['FirstName'] . " " . $s['LastName']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="date_reported">Date Reported</label>
                        <input type="date" name="date_reported" id="date_reported" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn-add">Log Issue</button>
            </div>
        </form>
    </div>
</div>

<?php require "partials/footer.php"; $conn->close(); ?>
