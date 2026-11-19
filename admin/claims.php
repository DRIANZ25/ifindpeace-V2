<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();

// Handle claim status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_claim'])) {
    $claim_id = intval($_POST['claim_id']);
    $status = sanitize($_POST['status']);
    $admin_notes = sanitize($_POST['admin_notes']);
    
    // 1. Update the claim status
    $stmt = $conn->prepare("UPDATE claims SET claim_status = ?, admin_notes = ? WHERE claim_id = ?");
    $stmt->bind_param("ssi", $status, $admin_notes, $claim_id);
    $stmt->execute();
    $stmt->close();
    
    // 2. Get claim details to update the ITEM status as well
    $claim_query = "SELECT cl.*, i.item_id, i.item_name, i.user_id as item_owner_id, cl.user_id as claimant_id 
                    FROM claims cl 
                    JOIN items i ON cl.item_id = i.item_id 
                    WHERE cl.claim_id = ?";
    $stmt = $conn->prepare($claim_query);
    $stmt->bind_param("i", $claim_id);
    $stmt->execute();
    $claim = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // 3. If admin approves or completes the claim, mark the ITEM as claimed
    if ($status === 'approved' || $status === 'completed') {
        $item_id = $claim['item_id'];
        $conn->query("UPDATE items SET status = 'claimed' WHERE item_id = $item_id");
    }
    
    // 4. Notify the claimant
    if ($status === 'approved') {
        sendNotification($claim['claimant_id'], $claim['item_id'], 'claim_approved', "Your claim for '{$claim['item_name']}' has been approved! Please proceed to the Organization Office with your ID to pick it up.");
    } elseif ($status === 'rejected') {
        sendNotification($claim['claimant_id'], $claim['item_id'], 'claim_rejected', "Your claim for '{$claim['item_name']}' has been rejected.");
    } elseif ($status === 'completed') {
        sendNotification($claim['claimant_id'], $claim['item_id'], 'item_returned', "Your claim for '{$claim['item_name']}' is completed. Thank you!");
    }
    
    logActivity($_SESSION['user_id'], 'update_claim_status', "Updated claim $claim_id status to $status");
    
    header("Location: claims.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query
$where = "WHERE 1=1";
if (!empty($status_filter)) {
    $where .= " AND cl.claim_status = '$status_filter'";
}

$query = "SELECT cl.*, i.item_name, i.item_type, u.full_name as claimant_name, u.student_id as claimant_id, u2.full_name as reporter_name
          FROM claims cl
          JOIN items i ON cl.item_id = i.item_id
          JOIN users u ON cl.user_id = u.user_id
          JOIN users u2 ON i.user_id = u2.user_id
          $where
          ORDER BY cl.claim_date DESC";
$claims = $conn->query($query);
$claim_rows = [];
if ($claims) {
    while ($row = $claims->fetch_assoc()) {
        $claim_rows[] = $row;
    }
}
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
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="msu-nav-text ms-3 d-none d-lg-flex align-items-center">
                    <img src="../assets/images/msu-logo.jpg" alt="MSU Logo" class="msu-logo-icon me-2">
                    <div>
                        <small class="text-white" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">MINDANAO STATE UNIVERSITY</small>
                        <br>
                        <small class="text-white" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">GENERAL SANTOS</small>
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">Manage Claims</h2>
                <p class="text-muted">Review and manage item claims</p>
            </div>
        </div>

        <!-- Drop-off Reminder for Admin -->
        <div class="alert alert-info border-info mb-4">
            <h6 class="fw-bold mb-1"><i class="fas fa-building me-2"></i>Admin Drop-off Point Reminder</h6>
            <p class="mb-0 small">Do not approve claims unless the claimant has physically arrived at the <strong>Organization Office</strong> to verify their identity and pick up the item. Once handed over, set the status to <strong>Completed</strong>.</p>
        </div>

        <!-- Filters -->
        <div class="filter-section mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($claim_rows)): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Claimant</th>
                            <th>Reporter</th>
                            <th>Security Question</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claim_rows as $claim): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($claim['item_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo strtoupper($claim['item_type']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($claim['claimant_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($claim['claimant_id']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($claim['reporter_name']); ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($claim['security_question']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $claim['claim_status'] == 'pending' ? 'warning' : ($claim['claim_status'] == 'approved' ? 'success' : ($claim['claim_status'] == 'rejected' ? 'danger' : 'info')); ?>">
                                        <?php echo ucfirst($claim['claim_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($claim['deadline'])); ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#claimModal<?php echo $claim['claim_id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($claim['id_proof_path']): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($claim['id_proof_path']); ?>" target="_blank" class="btn btn-sm btn-info text-white">
                                            <i class="fas fa-file"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-hand-paper fa-4x"></i>
                <h4>No claims found</h4>
                <p>Try adjusting your filters</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Claim Modals -->
    <?php if (!empty($claim_rows)): foreach ($claim_rows as $claim): ?>
        <div class="modal fade" id="claimModal<?php echo $claim['claim_id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Claim Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST">
                            <input type="hidden" name="claim_id" value="<?php echo $claim['claim_id']; ?>">
                            <input type="hidden" name="update_claim" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Item</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($claim['item_name']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Claimant</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($claim['claimant_name']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Security Question</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($claim['security_question']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Security Answer</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($claim['security_answer']); ?>" readonly>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" <?php echo $claim['claim_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $claim['claim_status'] === 'approved' ? 'selected' : ''; ?>>Approved (Waiting for pickup)</option>
                                    <option value="rejected" <?php echo $claim['claim_status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="completed" <?php echo $claim['claim_status'] === 'completed' ? 'selected' : ''; ?>>Completed (Item Handed Over)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Admin Notes</label>
                                <textarea name="admin_notes" class="form-control" rows="3"><?php echo htmlspecialchars($claim['admin_notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Update Claim
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>