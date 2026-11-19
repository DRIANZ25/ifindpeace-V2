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
    $item_id = intval($_POST['item_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($item_id <= 0) {
        $error = 'Invalid item.';
    } elseif ($action === 'mark_returned') {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT c.claim_id, c.claimant_id, i.item_name FROM claims c JOIN items i ON c.item_id = i.item_id WHERE c.item_id = ? AND c.claim_status = 'approved' ORDER BY c.created_at DESC LIMIT 1");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $claim = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE items SET status='returned' WHERE item_id=?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->close();

            if ($claim) {
                $stmt = $conn->prepare("UPDATE claims SET claim_status='completed', admin_notes=CONCAT(IFNULL(admin_notes,''), ' [Marked returned by admin]') WHERE claim_id=?");
                $stmt->bind_param("i", $claim['claim_id']);
                $stmt->execute();
                $stmt->close();
                sendNotification($claim['claimant_id'], $item_id, 'item_returned', "The item '{$claim['item_name']}' has been marked as returned.");
            }

            $stmt = $conn->prepare("UPDATE claims SET claim_status='rejected', admin_notes=CONCAT(IFNULL(admin_notes,''), ' [Auto-rejected: item already returned]') WHERE item_id=? AND claim_status IN ('pending','approved')");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $stmt->close();

            logActivity($_SESSION['user_id'], 'mark_returned', "Marked item #$item_id returned from items page");
            $conn->commit();
            $message = 'Item marked as returned.';
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Could not mark item as returned.';
        }
    } elseif (in_array($new_status, ['pending','returned','claimed','donated','expired'])) {
        $stmt = $conn->prepare("UPDATE items SET status=? WHERE item_id=?");
        $stmt->bind_param("si", $new_status, $item_id);
        $stmt->execute();
        $stmt->close();
        logActivity($_SESSION['user_id'], 'update_item_status', "Item #$item_id status changed to $new_status");
        $message = 'Item status updated.';
    } else {
        $error = 'Invalid status.';
    }
}

$items = $conn->query("SELECT i.*, c.category_name, u.full_name AS reporter FROM items i LEFT JOIN categories c ON i.category_id = c.category_id LEFT JOIN users u ON i.user_id = u.user_id ORDER BY i.created_at DESC");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - iFindPeace Admin</title>
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
                <span class="topbar-title">Manage Items</span>
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
                <strong><i class="fas fa-info-circle me-2"></i>Drop-off point:</strong>
                Found items must be surrendered to the <strong>Organization Office</strong>. Claimants pick them up there after admin approval.
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
                                <th>Type</th>
                                <th>Category</th>
                                <th>Reporter</th>
                                <th>Status</th>
                                <th class="pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?php echo $item['item_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['location_lost'] ?? $item['location_found'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $item['item_type'] == 'lost' ? 'primary' : 'warning text-dark'; ?>">
                                        <?php echo ucfirst($item['item_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($item['reporter'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($item['status'] === 'pending') $badge = 'warning text-dark';
                                    if ($item['status'] === 'returned') $badge = 'success';
                                    if ($item['status'] === 'claimed') $badge = 'info text-dark';
                                    ?>
                                    <span class="badge bg-<?php echo $badge; ?>"><?php echo ucfirst($item['status']); ?></span>
                                </td>
                                <td class="pe-4" style="min-width:280px;">
                                    <?php if ($item['status'] !== 'returned'): ?>
                                        <form method="POST" class="d-flex gap-2 mb-2">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <select name="status" class="form-select form-select-sm" style="border-radius: 8px;">
                                                <?php foreach (['pending','returned','claimed','donated','expired'] as $s): ?>
                                                    <option value="<?php echo $s; ?>" <?php echo $item['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary" style="border-radius: 8px;">Update</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <input type="hidden" name="action" value="mark_returned">
                                            <button class="btn btn-sm btn-success w-100 rounded-pill" onclick="return confirm('Mark this item as RETURNED?')">
                                                <i class="fas fa-hand-holding-heart me-1"></i>Mark Returned
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-success small fw-bold"><i class="fas fa-check-circle me-1"></i>Returned</span>
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