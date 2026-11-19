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
            sendNotification($claim['claimant_id'], $claim['item_id'], 'claim_approved', "Your claim for '{$claim['item_name']}' has been approved. Please pick it up at the Organization Office. Bring a valid ID.");
            logActivity($_SESSION['user_id'], 'approve_claim', "Approved claim #$claim_id");
            $message = 'Claim approved. The claimant was told to pick up the item at the Organization Office.';
        } elseif ($action === 'reject') {
            $stmt = $conn->prepare("UPDATE claims SET claim_status='rejected', admin_notes=? WHERE claim_id=?");
            $stmt->bind_param("si", $admin_notes, $claim_id);
            $stmt->execute();
            $stmt->close();
            sendNotification($claim['claimant_id'], $claim['item_id'], 'claim_rejected', "Your claim for '{$claim['item_name']}' was not approved. Please contact the Organization Office for details.");
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
                $stmt = $conn->prepare("UPDATE claims SET claim_status='rejected', admin_notes=CONCAT(IFNULL(admin_notes,''), ' [Auto-rejected: item already returned]') WHERE item_id=? AND claim_id<>? AND claim_status IN ('pending','approved')");
                $stmt->bind_param("ii", $claim['item_id'], $claim_id);
                $stmt->execute();
                $stmt->close();
                sendNotification($claim['claimant_id'], $claim['item_id'], 'item_returned', "The item '{$claim['item_name']}' has been marked as returned. Thank you for coordinating with the Organization Office.");
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
    SELECT c.*, i.item_name, i.item_type, i.status AS item_status, cat.category_name, u.full_name AS claimant_name, u.email
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
    <link rel="icon" type="image/jpeg" href="../assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #475569; border-bottom: 2px solid #f1f5f9; }
        .table td { vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .table tbody tr:hover { background-color: #eff6ff; }
        .status-badge { padding: 0.35rem 0.8rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; }
        .status-pending { background: #fef9c3; color: #713f12; }
        .status-approved { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="topbar-title">Manage Claims</span>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content p-4">
            <div class="alert alert-warning border-warning shadow-sm mb-4" style="background: #fffbeb; color: #713f12;">
                <strong><i class="fas fa-info-circle me-2"></i>Organization Office drop-off / pickup point:</strong>
                All found items must be surrendered to the Organization Office. Claimants must pick up approved items there.
                Mark an item as <strong>Returned</strong> only after the physical handover at the office.
            </div>

            <?php if ($message): ?><div class="alert alert-success shadow-sm"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <div class="card shadow-sm border-0" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>Item</th>
                                <th>Claimant</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Admin Notes</th>
                                <th>Date</th>
                                <th class="pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($c = $claims->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?php echo $c['claim_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($c['item_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo ucfirst($c['item_type']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($c['claimant_name']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($c['email']); ?></small>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($c['category_name'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($c['claim_status']); ?>">
                                        <?php echo ucfirst($c['claim_status']); ?>
                                    </span>
                                </td>
                                <td style="max-width:200px;"><small class="text-muted"><?php echo nl2br(htmlspecialchars($c['admin_notes'] ?? '')); ?></small></td>
                                <td><small class="text-muted"><?php echo date('M d, Y', strtotime($c['created_at'])); ?></small></td>
                                <td class="pe-4" style="min-width:240px;">
                                    <?php if ($c['claim_status'] === 'pending'): ?>
                                        <form method="POST" class="mb-2">
                                            <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <textarea name="admin_notes" class="form-control form-control-sm mb-1" placeholder="Notes (optional)" rows="1"></textarea>
                                            <button class="btn btn-sm btn-success w-100 rounded-pill" onclick="return confirm('Approve this claim?')">
                                                <i class="fas fa-check me-1"></i>Approve
                                            </button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button class="btn btn-sm btn-outline-danger w-100 rounded-pill" onclick="return confirm('Reject this claim?')">
                                                <i class="fas fa-times me-1"></i>Reject
                                            </button>
                                        </form>
                                    <?php elseif ($c['claim_status'] === 'approved'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="claim_id" value="<?php echo $c['claim_id']; ?>">
                                            <input type="hidden" name="action" value="returned">
                                            <button class="btn btn-sm btn-primary w-100 rounded-pill" onclick="return confirm('Mark as RETURNED?')">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>