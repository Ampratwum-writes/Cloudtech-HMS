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
   GCTU 9 Hostel - Payments
   ============================================ */
require "config.php";

function render_payment_row($row, $rowNum = null) {
    $methodChip = ['Cash' => 'chip-gold', 'MoMo' => 'chip-navy', 'Bank' => 'chip-muted'][$row['PaymentMethod']] ?? 'chip-muted';
    $balance = (float)$row['OutstandingBalance'];
    ob_start();
    ?>
    <tr>
        <td class="row-num"><?= $rowNum !== null ? (int)$rowNum : '' ?></td>
        <td class="id-mono">#<?= htmlspecialchars($row['PaymentID']) ?></td>
        <td class="id-mono">#<?= htmlspecialchars($row['BookingID']) ?></td>
        <td><?= htmlspecialchars($row['FirstName'] . " " . $row['LastName']) ?></td>
        <td>GHS <?= number_format((float)$row['AmountPaid'], 2) ?></td>
        <td><?= htmlspecialchars($row['PaymentDate']) ?></td>
        <td><span class="chip <?= $methodChip ?>"><?= htmlspecialchars($row['PaymentMethod']) ?></span></td>
        <td><?= $balance > 0 ? '<span class="badge badge-pending">GHS ' . number_format($balance, 2) . '</span>' : '<span class="badge badge-resolved">Cleared</span>' ?></td>
        <td>
            <button class="btn-ghost danger" data-quick-action="delete" data-id="<?= (int)$row['PaymentID'] ?>"
                    data-confirm="Delete this payment of GHS <?= number_format((float)$row['AmountPaid'], 2) ?>? This cannot be undone.">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                Delete
            </button>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// ---------- AJAX: delete payment ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'delete') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = (int)$_POST['id'];

    $stmt = $conn->prepare("DELETE FROM payment WHERE PaymentID = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) c FROM payment")->fetch_assoc()['c'];
            echo json_encode(["success" => true, "removed" => true, "message" => "payment deleted.", "count" => (int)$count]);
        } else {
            echo json_encode(["success" => false, "error" => "payment not found — may already be deleted."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Could not delete: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: add payment ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_add_payment'])) {
    header('Content-Type: application/json');
    csrf_verify(true);

    $bookingID = $_POST['booking_id'];
    $amount = $_POST['amount_paid'];
    $date = $_POST['payment_date'];
    $method = $_POST['payment_method'];
    $balance = $_POST['outstanding_balance'] !== "" ? $_POST['outstanding_balance'] : 0;

    if ($bookingID === '' || $amount === '' || $date === '' || !in_array($method, ['Cash', 'MoMo', 'Bank'])) {
        echo json_encode(["success" => false, "error" => "Please fill in all required fields."]);
        exit;
    }
    if ((float)$amount < 0) {
        echo json_encode(["success" => false, "error" => "Amount paid cannot be negative."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO payment (BookingID, AmountPaid, PaymentDate, PaymentMethod, OutstandingBalance) VALUES (?, ?, ?, ?, ?)");
    $amountF = (float)$amount;
    $balanceF = (float)$balance;
    $stmt->bind_param("idssd", $bookingID, $amountF, $date, $method, $balanceF);

    if ($stmt->execute()) {
        $newId = $stmt->insert_id;
        $fetch = $conn->prepare(
            "SELECT p.PaymentID, p.BookingID, s.FirstName, s.LastName, p.AmountPaid, p.PaymentDate, p.PaymentMethod, p.OutstandingBalance
             FROM payment p JOIN booking b ON p.BookingID = b.BookingID JOIN student s ON b.StudentID = s.StudentID
             WHERE p.PaymentID = ?"
        );
        $fetch->bind_param("i", $newId);
        $fetch->execute();
        $newRow = $fetch->get_result()->fetch_assoc();
        $rowHtml = render_payment_row($newRow);
        $count = $conn->query("SELECT COUNT(*) c FROM payment")->fetch_assoc()['c'];
        echo json_encode(["success" => true, "message" => "payment recorded.", "rowHtml" => $rowHtml, "count" => (int)$count]);
    } else {
        echo json_encode(["success" => false, "error" => "Could not save: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

$sql = "SELECT p.PaymentID, p.BookingID, s.FirstName, s.LastName, p.AmountPaid, p.PaymentDate, p.PaymentMethod, p.OutstandingBalance
        FROM payment p JOIN booking b ON p.BookingID = b.BookingID JOIN student s ON b.StudentID = s.StudentID
        ORDER BY p.PaymentID DESC";
$payments = $conn->query($sql);
$totalPayments = $payments->num_rows;

$bookingOptions = $conn->query(
    "SELECT b.BookingID, s.FirstName, s.LastName, r.RoomNumber
     FROM booking b JOIN student s ON b.StudentID = s.StudentID JOIN room r ON b.RoomID = r.RoomID
     ORDER BY b.BookingID DESC"
);

$pageTitle = "Payments";
$pageSubtitle = "Hostel fee payments and outstanding balances";
$activeNav = "payments";
require "partials/header.php";
?>

<div class="content">

    <div class="card">
        <div class="card-header">
            <div>
                <h2>payment Records</h2>
                <div class="count-pill" id="rowCount"><?= (int)$totalPayments ?> shown</div>
            </div>
            <div class="page-actions">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-search-target="paymentsTable" data-count-target="rowCount" placeholder="Search payments...">
                </div>
                <button class="btn-add" data-modal-open="addPaymentModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Record payment
                </button>
            </div>
        </div>

        <table id="paymentsTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="sortable">payment ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">booking <span class="sort-arrow">↕</span></th>
                    <th class="sortable">student <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Amount Paid <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Date <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Method <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Balance <span class="sort-arrow">↕</span></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payments->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $payments->fetch_assoc()): ?>
                        <?= render_payment_row($row, $i++) ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="9"><div class="empty-state">No payments yet. Record one to get started.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add payment Modal -->
<div class="modal-overlay" id="addPaymentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Record New payment</h3>
            <button class="modal-close" data-modal-close>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form class="ajax-form" method="POST" action="payments.php" data-target="paymentsTable">
            <?php csrf_field(); ?>
            <input type="hidden" name="ajax_add_payment" value="1">
            <div class="modal-body">
                <div class="form-grid" style="padding:0; grid-template-columns: 1fr 1fr;">
                    <div class="field full">
                        <label for="booking_id">booking</label>
                        <select name="booking_id" id="booking_id" required>
                            <option value="">Select a booking…</option>
                            <?php while ($b = $bookingOptions->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($b['BookingID']) ?>">#<?= htmlspecialchars($b['BookingID']) ?> — <?= htmlspecialchars($b['FirstName'] . " " . $b['LastName'] . " (" . $b['RoomNumber'] . ")") ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="amount_paid">Amount Paid (GHS)</label>
                        <input type="number" step="0.01" min="0" name="amount_paid" id="amount_paid" required>
                    </div>
                    <div class="field">
                        <label for="payment_date">payment Date</label>
                        <input type="date" name="payment_date" id="payment_date" required>
                    </div>
                    <div class="field">
                        <label for="payment_method">Method</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="Cash">Cash</option>
                            <option value="MoMo">MoMo</option>
                            <option value="Bank">Bank</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="outstanding_balance">Outstanding Balance (GHS)</label>
                        <input type="number" step="0.01" min="0" name="outstanding_balance" id="outstanding_balance" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn-add">Save payment</button>
            </div>
        </form>
    </div>
</div>

<?php require "partials/footer.php"; $conn->close(); ?>
