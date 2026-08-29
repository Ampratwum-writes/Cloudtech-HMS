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
   GCTU 9 Hostel - Visitors
   ============================================ */
require "config.php";

function render_visitor_row($row, $rowNum = null) {
    $stillIn = empty($row['TimeOut']);
    ob_start();
    ?>
    <tr>
        <td class="row-num"><?= $rowNum !== null ? (int)$rowNum : '' ?></td>
        <td class="id-mono">#<?= htmlspecialchars($row['VisitorID']) ?></td>
        <td><?= htmlspecialchars($row['VisitorName']) ?></td>
        <td><?= htmlspecialchars($row['FirstName'] . " " . $row['LastName']) ?> <span class="id-mono">(<?= htmlspecialchars($row['StudentID']) ?>)</span></td>
        <td><?= htmlspecialchars($row['TimeIn']) ?></td>
        <td><?= htmlspecialchars($row['TimeOut'] ?? '—') ?></td>
        <td>
            <?php if ($stillIn): ?>
                <span class="badge badge-pending">On Premises</span>
            <?php else: ?>
                <span class="badge badge-ended">Signed Out</span>
            <?php endif; ?>
        </td>
        <td>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if ($stillIn): ?>
                    <button class="btn-ghost danger" data-quick-action="checkout" data-id="<?= (int)$row['VisitorID'] ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        Sign Out
                    </button>
                <?php endif; ?>
                <button class="btn-ghost danger" data-quick-action="delete" data-id="<?= (int)$row['VisitorID'] ?>"
                        data-confirm="Delete this visitor log entry for <?= htmlspecialchars(addslashes($row['VisitorName'])) ?>? This cannot be undone.">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                    Delete
                </button>
            </div>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// ---------- AJAX: delete visitor record ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'delete') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("DELETE FROM visitor WHERE VisitorID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) c FROM visitor")->fetch_assoc()['c'];
            echo json_encode(["success" => true, "removed" => true, "message" => "visitor record deleted.", "count" => (int)$count]);
        } else {
            echo json_encode(["success" => false, "error" => "Record not found — may already be deleted."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Could not delete: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: quick action (sign out) ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'checkout') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("UPDATE visitor SET TimeOut = NOW() WHERE VisitorID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $fetch = $conn->prepare(
            "SELECT v.VisitorID, v.VisitorName, v.TimeIn, v.TimeOut, s.StudentID, s.FirstName, s.LastName
             FROM visitor v JOIN student s ON v.StudentID = s.StudentID
             WHERE v.VisitorID = ?"
        );
        $fetch->bind_param("i", $id);
        $fetch->execute();
        $row = $fetch->get_result()->fetch_assoc();
        echo json_encode(["success" => true, "message" => "visitor signed out.", "rowHtml" => render_visitor_row($row)]);
    } else {
        echo json_encode(["success" => false, "error" => "Could not update: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: add visitor sign-in ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_add_visitor'])) {
    header('Content-Type: application/json');
    csrf_verify(true);

    $studentID = $_POST['student_id'];
    $visitorName = trim($_POST['visitor_name']);
    $timeIn = $_POST['time_in'];

    if ($studentID === '' || $visitorName === '' || $timeIn === '') {
        echo json_encode(["success" => false, "error" => "Please fill in all required fields."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO visitor (StudentID, VisitorName, TimeIn, TimeOut) VALUES (?, ?, ?, NULL)");
    $timeInFormatted = str_replace('T', ' ', $timeIn) . ':00';
    $stmt->bind_param("sss", $studentID, $visitorName, $timeInFormatted);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $fetch = $conn->prepare(
            "SELECT v.VisitorID, v.VisitorName, v.TimeIn, v.TimeOut, s.StudentID, s.FirstName, s.LastName
             FROM visitor v JOIN student s ON v.StudentID = s.StudentID
             WHERE v.VisitorID = ?"
        );
        $fetch->bind_param("i", $newId);
        $fetch->execute();
        $newRow = $fetch->get_result()->fetch_assoc();
        $rowHtml = render_visitor_row($newRow);
        $count = $conn->query("SELECT COUNT(*) c FROM visitor")->fetch_assoc()['c'];
        echo json_encode(["success" => true, "message" => "visitor signed in.", "rowHtml" => $rowHtml, "count" => (int)$count]);
    } else {
        echo json_encode(["success" => false, "error" => "Could not save: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

$sql = "SELECT v.VisitorID, v.VisitorName, v.TimeIn, v.TimeOut, s.StudentID, s.FirstName, s.LastName
        FROM visitor v JOIN student s ON v.StudentID = s.StudentID
        ORDER BY (v.TimeOut IS NULL) DESC, v.VisitorID DESC";
$visitors = $conn->query($sql);
$totalVisitors = $visitors->num_rows;

$studentOptions = $conn->query("SELECT StudentID, FirstName, LastName FROM student ORDER BY FirstName");

$pageTitle = "Visitors";
$pageSubtitle = "visitor sign-in and sign-out log";
$activeNav = "visitors";
require "partials/header.php";
?>

<div class="content">

    <div class="card">
        <div class="card-header">
            <div>
                <h2>visitor Log</h2>
                <div class="count-pill" id="rowCount"><?= (int)$totalVisitors ?> shown</div>
            </div>
            <div class="page-actions">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-search-target="visitorsTable" data-count-target="rowCount" placeholder="Search visitors...">
                </div>
                <button class="btn-add" data-modal-open="addVisitorModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Sign In visitor
                </button>
            </div>
        </div>

        <table id="visitorsTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="sortable">ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">visitor Name <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Visiting <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Time In <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Time Out <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Status <span class="sort-arrow">↕</span></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($visitors->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $visitors->fetch_assoc()): ?>
                        <?= render_visitor_row($row, $i++) ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8"><div class="empty-state">No visitors logged yet.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add visitor Modal -->
<div class="modal-overlay" id="addVisitorModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Sign In New visitor</h3>
            <button class="modal-close" data-modal-close>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form class="ajax-form" method="POST" action="visitors.php" data-target="visitorsTable">
            <?php csrf_field(); ?>
            <input type="hidden" name="ajax_add_visitor" value="1">
            <div class="modal-body">
                <div class="form-grid" style="padding:0; grid-template-columns: 1fr 1fr;">
                    <div class="field full">
                        <label for="visitor_name">visitor Name</label>
                        <input type="text" name="visitor_name" id="visitor_name" placeholder="e.g. Adjoa Mensah" required>
                    </div>
                    <div class="field full">
                        <label for="student_id">Visiting student</label>
                        <select name="student_id" id="student_id" required>
                            <option value="">Select a student…</option>
                            <?php while ($s = $studentOptions->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($s['StudentID']) ?>"><?= htmlspecialchars($s['StudentID'] . " — " . $s['FirstName'] . " " . $s['LastName']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field full">
                        <label for="time_in">Time In</label>
                        <input type="datetime-local" name="time_in" id="time_in" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn-add">Sign In</button>
            </div>
        </form>
    </div>
</div>

<?php require "partials/footer.php"; $conn->close(); ?>
