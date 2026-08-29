<?php
// ============================================
// GCTU 9 Hostel - room Applications
// Admin reviews pending applications from students.
// Approve -> creates a real booking + marks Approved
// Reject  -> marks Rejected, no booking created
// ============================================
require 'security.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
require 'config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['application_id'])) {
    csrf_verify(false);
    $applicationId = (int)$_POST['application_id'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("SELECT StudentID, RoomID, Semester FROM roomapplication WHERE ApplicationID = ? AND Status = 'Pending'");
    $stmt->bind_param("i", $applicationId);
    $stmt->execute();
    $app = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$app) {
        $error = "That application is no longer pending.";
    } elseif ($action === 'approve') {
        // Guard against approving into a now-full room
        $capCheck = $conn->prepare("
            SELECT r.Capacity,
                   (SELECT COUNT(*) FROM booking bk WHERE bk.RoomID = r.RoomID AND (bk.CheckOutDate IS NULL OR bk.CheckOutDate > CURDATE())) AS OccupiedCount
            FROM room r WHERE r.RoomID = ?
        ");
        $capCheck->bind_param("i", $app['RoomID']);
        $capCheck->execute();
        $cap = $capCheck->get_result()->fetch_assoc();
        $capCheck->close();

        if ($cap && $cap['OccupiedCount'] >= $cap['Capacity']) {
            $error = "That room is now full — reject this application or reassign the student to another room.";
        } else {
            $conn->begin_transaction();
            try {
                $insertBooking = $conn->prepare("INSERT INTO booking (StudentID, RoomID, Semester, CheckInDate, CheckOutDate) VALUES (?, ?, ?, CURDATE(), NULL)");
                $insertBooking->bind_param("sis", $app['StudentID'], $app['RoomID'], $app['Semester']);
                $insertBooking->execute();
                $insertBooking->close();

                $update = $conn->prepare("UPDATE roomapplication SET Status = 'Approved' WHERE ApplicationID = ?");
                $update->bind_param("i", $applicationId);
                $update->execute();
                $update->close();

                $conn->commit();
                $message = "Application approved — a booking has been created.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Could not approve this application. Please try again.";
            }
        }
    } elseif ($action === 'reject') {
        $update = $conn->prepare("UPDATE roomapplication SET Status = 'Rejected' WHERE ApplicationID = ?");
        $update->bind_param("i", $applicationId);
        $update->execute();
        $update->close();
        $message = "Application rejected.";
    }
}

$pendingResult = $conn->query("
    SELECT a.ApplicationID, a.Semester, a.ApplicationDate,
           s.StudentID, s.FirstName, s.LastName,
           r.RoomNumber, r.Block, r.Capacity,
           (SELECT COUNT(*) FROM booking bk WHERE bk.RoomID = r.RoomID AND (bk.CheckOutDate IS NULL OR bk.CheckOutDate > CURDATE())) AS OccupiedCount
    FROM roomapplication a
    JOIN student s ON a.StudentID = s.StudentID
    JOIN room r ON a.RoomID = r.RoomID
    WHERE a.Status = 'Pending'
    ORDER BY a.ApplicationDate ASC
");

$recentResult = $conn->query("
    SELECT a.ApplicationID, a.Semester, a.Status,
           s.FirstName, s.LastName, r.RoomNumber
    FROM roomapplication a
    JOIN student s ON a.StudentID = s.StudentID
    JOIN room r ON a.RoomID = r.RoomID
    WHERE a.Status != 'Pending'
    ORDER BY a.ApplicationID DESC
    LIMIT 10
");

$pageTitle = "room Applications";
$pageSubtitle = "Review and process student room applications";
$activeNav = "applications";
require "partials/header.php";
?>

<div class="content">

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <h2>Pending Applications</h2>
            <span class="count-pill"><?= $pendingResult->num_rows ?> pending</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>student</th>
                    <th>room</th>
                    <th>Semester</th>
                    <th>Applied</th>
                    <th>room Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($pendingResult->num_rows > 0): ?>
                    <?php while ($row = $pendingResult->fetch_assoc()): ?>
                        <?php $full = $row['OccupiedCount'] >= $row['Capacity']; ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?>
                                <span class="id-mono">(<?= htmlspecialchars($row['StudentID']) ?>)</span>
                            </td>
                            <td>Block <?= htmlspecialchars($row['Block']) ?> — <?= htmlspecialchars($row['RoomNumber']) ?></td>
                            <td><?= htmlspecialchars($row['Semester']) ?></td>
                            <td><?= htmlspecialchars($row['ApplicationDate']) ?></td>
                            <td>
                                <?php if ($full): ?>
                                    <span class="chip" style="background:var(--danger-bg); color:var(--danger);">room full</span>
                                <?php else: ?>
                                    <span class="chip chip-navy"><?= (int)$row['OccupiedCount'] ?>/<?= (int)$row['Capacity'] ?> occupied</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:8px;">
                                    <form method="POST" action="applications.php" onsubmit="return confirm('Approve this application and create a booking?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="application_id" value="<?= (int)$row['ApplicationID'] ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-ghost success"<?= $full ? ' disabled title="room is full"' : '' ?>>Approve</button>
                                    </form>
                                    <form method="POST" action="applications.php" onsubmit="return confirm('Reject this application?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="application_id" value="<?= (int)$row['ApplicationID'] ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-ghost danger">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6"><div class="empty-state">No pending applications.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Recently Processed</h2>
        </div>
        <table>
            <thead>
                <tr><th>student</th><th>room</th><th>Semester</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if ($recentResult->num_rows > 0): ?>
                    <?php while ($row = $recentResult->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['FirstName'] . ' ' . $row['LastName']) ?></td>
                            <td><?= htmlspecialchars($row['RoomNumber']) ?></td>
                            <td><?= htmlspecialchars($row['Semester']) ?></td>
                            <td>
                                <?php if ($row['Status'] === 'Approved'): ?>
                                    <span class="badge badge-resolved">Approved</span>
                                <?php else: ?>
                                    <span class="badge" style="background:var(--danger-bg); color:var(--danger);">Rejected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4"><div class="empty-state">No processed applications yet.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<?php require "partials/footer.php"; $conn->close(); ?>
