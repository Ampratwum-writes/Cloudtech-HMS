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
   GCTU 9 Hostel - Students
   ============================================ */
require "config.php";

function render_student_row($row, $rowNum = null) {
    ob_start();
    ?>
    <tr>
        <td class="row-num"><?= $rowNum !== null ? (int)$rowNum : '' ?></td>
        <td class="id-mono"><?= htmlspecialchars($row['StudentID']) ?></td>
        <td><?= htmlspecialchars($row['FirstName'] . " " . $row['LastName']) ?></td>
        <td><?= htmlspecialchars($row['Phone']) ?></td>
        <td><?= htmlspecialchars($row['Email'] ?: '—') ?></td>
        <td><?= htmlspecialchars($row['Program']) ?></td>
        <td>
            <button class="btn-ghost danger" data-quick-action="delete" data-id="<?= htmlspecialchars($row['StudentID']) ?>"
                    data-confirm="Delete <?= htmlspecialchars(addslashes($row['FirstName'] . ' ' . $row['LastName'])) ?>? This will also delete ALL their bookings, payments, and visitor records. This cannot be undone.">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6"/></svg>
                Delete
            </button>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

// ---------- AJAX: delete student ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['quick_action']) && $_POST['quick_action'] === 'delete') {
    header('Content-Type: application/json');
    csrf_verify(true);
    $id = $_POST['id'];

    $stmt = $conn->prepare("DELETE FROM student WHERE StudentID = ?");
    $stmt->bind_param("s", $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) c FROM student")->fetch_assoc()['c'];
            echo json_encode(["success" => true, "removed" => true, "message" => "student deleted.", "count" => (int)$count]);
        } else {
            echo json_encode(["success" => false, "error" => "student not found — may already be deleted."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => "Could not delete: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// ---------- AJAX: add student ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_add_student'])) {
    header('Content-Type: application/json');
    csrf_verify(true);

    $id = trim($_POST['student_id']);
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $program = trim($_POST['program']);

    if ($id === '' || $first === '' || $last === '' || $phone === '' || $program === '') {
        echo json_encode(["success" => false, "error" => "Please fill in all required fields."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO student (StudentID, FirstName, LastName, Phone, Email, Program) VALUES (?, ?, ?, ?, ?, ?)");
    $emailVal = $email !== '' ? $email : null;
    $stmt->bind_param("ssssss", $id, $first, $last, $phone, $emailVal, $program);

    if ($stmt->execute()) {
        $rowHtml = render_student_row([
            'StudentID' => $id, 'FirstName' => $first, 'LastName' => $last,
            'Phone' => $phone, 'Email' => $emailVal, 'Program' => $program
        ]);
        $count = $conn->query("SELECT COUNT(*) c FROM student")->fetch_assoc()['c'];
        echo json_encode(["success" => true, "message" => "student added.", "rowHtml" => $rowHtml, "count" => (int)$count]);
    } else {
        $err = $stmt->errno === 1062 ? "That student ID, phone, or email already exists." : "Could not save: " . $stmt->error;
        echo json_encode(["success" => false, "error" => $err]);
    }
    $stmt->close();
    exit;
}

$students = $conn->query("SELECT * FROM student ORDER BY FirstName");
$totalStudents = $students->num_rows;

$pageTitle = "Students";
$pageSubtitle = "All registered hostel students";
$activeNav = "students";
require "partials/header.php";
?>

<div class="content">

    <div class="card">
        <div class="card-header">
            <div>
                <h2>student Records</h2>
                <div class="count-pill" id="rowCount"><?= (int)$totalStudents ?> shown</div>
            </div>
            <div class="page-actions">
                <div class="search-box">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="text" data-search-target="studentsTable" data-count-target="rowCount" placeholder="Search students...">
                </div>
                <button class="btn-add" data-modal-open="addStudentModal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    Add student
                </button>
            </div>
        </div>

        <table id="studentsTable">
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="sortable">student ID <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Name <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Phone <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Email <span class="sort-arrow">↕</span></th>
                    <th class="sortable">Program <span class="sort-arrow">↕</span></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students->num_rows > 0): ?>
                    <?php $i = 1; while ($row = $students->fetch_assoc()): ?>
                        <?= render_student_row($row, $i++) ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7"><div class="empty-state">No students yet. Add one to get started.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Add student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New student</h3>
            <button class="modal-close" data-modal-close>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <form class="ajax-form" method="POST" action="students.php" data-target="studentsTable">
            <?php csrf_field(); ?>
            <input type="hidden" name="ajax_add_student" value="1">
            <div class="modal-body">
                <div class="form-grid" style="padding:0; grid-template-columns: 1fr 1fr;">
                    <div class="field full">
                        <label for="student_id">student ID</label>
                        <input type="text" name="student_id" id="student_id" placeholder="e.g. STU005" required>
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
                        <label for="phone">Phone</label>
                        <input type="text" name="phone" id="phone" placeholder="0244000000" required>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" placeholder="optional">
                    </div>
                    <div class="field full">
                        <label for="program">Program of Study</label>
                        <input type="text" name="program" id="program" placeholder="e.g. BSc Computer Science" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="btn-add">Save student</button>
            </div>
        </form>
    </div>
</div>

<?php require "partials/footer.php"; $conn->close(); ?>
