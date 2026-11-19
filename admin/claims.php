<?php
require_once '../config/config.php';

requireLogin();

if (!isAdmin()) {
    header("Location: ../dashboard.php");
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id = intval($_POST['claim_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_notes = sanitize($_POST['admin_notes'] ?? '');

    if ($claim_id <= 0) {
        $error = 'Invalid claim.';
    } else {
        $stmt = $conn->prepare("
            SELECT c.*, i.item_id, i.item_name, i.user_id AS owner_id
            FROM claims c
            JOIN items i ON c.item_id = i.item_id
            WHERE c.claim_id = ?
        ");
        $stmt->bind_param("i", $claim_id);
        $stmt->execute();
        $claim = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$claim) {
            $error = 'Claim not found.';
        } elseif ($action === 'approve') {
            $stmt = $conn->prepare("UPDATE claims SET claim_status='approved', admin_notes=? WHERE claim_id=?");
            $stmt->bind_param("si", $admin_notes, $claim_id);
            $stmt->execute();
            $stmt->close();

            sendNotification(
                $claim['claimant_id'],
                $claim['item_id'],
                'claim_approved',
                "Your claim for '{$claim['item_name']}' has been approved. Please pick it up at the Organization Office. Bring a valid ID."
            );
            logActivity($_SESSION['user_id'], 'approve_claim', "Approved claim #$claim_id");
            $message = 'Claim approved. The claimant was told to pick up the item at the Organization Office.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE claims SET claim_status='rejected', admin_notes=? WHERE claim_id=?");
            $stmt->bind_param("si", $admin_notes, $claim_id);
            $stmt->execute();
            $stmt->close();

            sendNotification(
                $claim['claimant_id'],
                $claim['item_id'],
                'claim_rejected',
                "Your claim for '{$claim['item_name']}' was not approved. Please contact the Organization Office for details."
            );
            logActivity($_SESSION['user_id'], 'reject_claim', "Rejected claim #$claim_id");
            $message = 'Claim rejected.';
        } elseif ($action === 'returned') {
            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("UPDATE claims SET claim_status='completed', admin_notes=? WHERE claim_id=?");
                $stmt->bind_param("si", $admin_notes, $claim_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE items SET status='returned' WHERE item_id=?");
                $stmt->bind_param("i", $claim['item_id']);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    UPDATE claims 
                    SET claim_status='rejected', 
                        admin_notes=CONCAT(IFNULL(admin_notes,''), ' [Auto-rejected: item already returned]')
                    WHERE item_id=? AND claim_id<>? AND claim_status IN ('pending','approved')
                ");
                $stmt->bind_param("ii", $claim['item_id'], $claim_id);
                $stmt->execute();
                $stmt->close();

                sendNotification(
                    $claim['claimant_id'],
                    $claim['item_id'],
                    'item_returned',
                    "The item '{$claim['item_name']}' has been marked as returned. Thank you for coordinating with the Organization Office."
                );

                logActivity($_SESSION['user_id'], 'mark_returned', "Marked item #{$claim['item_id']} returned via claim #$claim_id");
                $conn->commit();
                $message = 'Item marked as returned. Other pending claims for this item were rejected.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Could not mark item as returned. Please try again.';
            }
        }
    }
}

$claims = $conn->query("
    SELECT c.*, 
           i.item_name, i.item_type, i.status AS item_status,
           cat.category_name,
           u.full_name AS claimant_name, u.email
    FROM claims c
    JOIN items i ON c.item_id = i.item_id
    JOIN users u ON c.claimant_id = u.user_id
    LEFT JOIN categories cat ON i.category_id = cat.category_id
    ORDER BY FIELD(c.claim_status,'pending','approved','completed','rejected'), c.created_at DESC
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Claims - iFindPeace Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content p-4">
    <h2 class="mb-4"><i class="fas fa-clipboard-check me-2"></i>Manage Claims</h2>

    <div class="alert alert-warning border-warning">
        <strong><i class="fas fa-info-circle me-2"></i>Organization Office drop-off / pickup point:</strong>
        All found items must be surrendered to the Organization Office. Claimants must pick up approved items there.
        Mark an item as <strong>Returned</strong> only after the physical handover at the office.
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Item</th>
                        <th>Claimant</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Admin Notes</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($c = $claims->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo $c['claim_id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($c['item_name']); ?></strong><br>
                            <small class="text-muted"><?php echo ucfirst($c['item_type']); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($c['claimant_name']); ?><br>
                            <small><?php echo htmlspecialchars($c['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($c['category_name'] ?? 'N/A'); ?></td>
                        <td>
                            <?php
                            $badge = 'secondary';
                            if ($c['claim_status'] === 'pending') $badge = 'warning text-dark';
                            if ($c['claim_status'] === 'approved') $badge = 'info text-dark';
                            if ($c['claim_status'] === 'completed') $badge = 'success';
                            if ($c['claim_status'] === 'rejected') $badge = 'danger';
                            ?>
                            <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($c['claim_status']); ?></span>
                        </td>
                        <td style="max-width:220px;"><?php echo nl2br(htmlspecialchars($c['admin_notes'] ?? '')); ?></td>
                        <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                        <td style="min-width:260px;">
                            <?php if ($c['claim_status'] === 'pending'): ?>
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <textarea name="admin_notes" class="form-control form-control-sm mb-1" placeholder="Notes (optional)"></textarea>
                                    <button class="btn btn-sm btn-success w-100 mb-1" onclick="return confirm('Approve this claim? The claimant will be told to pick up at the Organization Office.')">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <textarea name="admin_notes" class="form-control form-control-sm mb-1" placeholder="Reason for rejection"></textarea>
                                    <button class="btn btn-sm btn-outline-danger w-100" onclick="return confirm('Reject this claim?')">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </form>
                            <?php elseif ($c['claim_status'] === 'approved'): ?>
                                <form method="POST">
                                    <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                                    <input type="hidden" name="action" value="returned">
                                    <textarea name="admin_notes" class="form-control form-control-sm mb-1" placeholder="Handover notes"></textarea>
                                    <button class="btn btn-sm btn-primary w-100" onclick="return confirm('Mark this item as RETURNED? This confirms the item was handed over at the Organization Office.')">
                                        <i class="fas fa-hand-holding-heart me-1"></i>Mark Returned
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">No action needed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>