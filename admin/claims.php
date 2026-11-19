<?php
require_once '../config/config.php';

requireLogin();

if (!isAdmin()) {
    header('Location: ../dashboard.php');
    exit();
}

$conn = getDBConnection();
$message = '';
$error = '';

/** Prepare a query and fail with a useful server-side error if it is invalid. */
function prepareClaimsQuery(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException('Claims query preparation failed: ' . $conn->error);
    }

    return $stmt;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id = (int) ($_POST['claim_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $admin_notes = sanitize($_POST['admin_notes'] ?? '');

    if ($claim_id <= 0) {
        $error = 'Invalid claim.';
    } elseif (!in_array($action, ['approve', 'reject', 'returned'], true)) {
        $error = 'Invalid claim action.';
    } else {
        try {
            $stmt = prepareClaimsQuery(
                $conn,
                "SELECT c.*, i.item_id, i.item_name, i.user_id AS owner_id
                 FROM claims c
                 JOIN items i ON c.item_id = i.item_id
                 WHERE c.claim_id = ?"
            );
            $stmt->bind_param('i', $claim_id);
            $stmt->execute();
            $claim = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$claim) {
                $error = 'Claim not found.';
            } elseif ($action === 'approve' || $action === 'reject') {
                $new_status = $action === 'approve' ? 'approved' : 'rejected';
                $stmt = prepareClaimsQuery(
                    $conn,
                    'UPDATE claims SET claim_status = ?, admin_notes = ? WHERE claim_id = ?'
                );
                $stmt->bind_param('ssi', $new_status, $admin_notes, $claim_id);
                $stmt->execute();
                $stmt->close();

                if ($action === 'approve') {
                    sendNotification(
                        $claim['user_id'],
                        $claim['item_id'],
                        'claim_approved',
                        "Your claim for '{$claim['item_name']}' has been approved. Please pick it up at the Organization Office. Bring a valid ID."
                    );
                    logActivity($_SESSION['user_id'], 'approve_claim', "Approved claim #$claim_id");
                    $message = 'Claim approved. The claimant was told to pick up the item at the Organization Office.';
                } else {
                    sendNotification(
                        $claim['user_id'],
                        $claim['item_id'],
                        'claim_rejected',
                        "Your claim for '{$claim['item_name']}' was not approved. Please contact the Organization Office for details."
                    );
                    logActivity($_SESSION['user_id'], 'reject_claim', "Rejected claim #$claim_id");
                    $message = 'Claim rejected.';
                }
            } elseif ($action === 'returned') {
                if ($claim['claim_status'] !== 'approved') {
                    throw new RuntimeException('Only approved claims can be marked as returned.');
                }

                $conn->begin_transaction();

                try {
                    $stmt = prepareClaimsQuery(
                        $conn,
                        "UPDATE claims SET claim_status = 'completed', admin_notes = ? WHERE claim_id = ?"
                    );
                    $stmt->bind_param('si', $admin_notes, $claim_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = prepareClaimsQuery(
                        $conn,
                        "UPDATE items SET status = 'returned' WHERE item_id = ?"
                    );
                    $stmt->bind_param('i', $claim['item_id']);
                    $stmt->execute();
                    $stmt->close();

                    $auto_reject_note = ' [Auto-rejected: item already returned]';
                    $stmt = prepareClaimsQuery(
                        $conn,
                        "UPDATE claims
                         SET claim_status = 'rejected',
                             admin_notes = CONCAT(IFNULL(admin_notes, ''), ?)
                         WHERE item_id = ?
                           AND claim_id <> ?
                           AND claim_status IN ('pending', 'approved')"
                    );
                    $stmt->bind_param('sii', $auto_reject_note, $claim['item_id'], $claim_id);
                    $stmt->execute();
                    $stmt->close();

                    // The notifications table schema allows admin_message, but not item_returned.
                    sendNotification(
                        $claim['user_id'],
                        $claim['item_id'],
                        'admin_message',
                        "The item '{$claim['item_name']}' has been marked as returned. Thank you for coordinating with the Organization Office."
                    );

                    logActivity(
                        $_SESSION['user_id'],
                        'mark_returned',
                        "Marked item #{$claim['item_id']} returned via claim #$claim_id"
                    );

                    $conn->commit();
                    $message = 'Item marked as returned. Other pending claims for this item were rejected.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }
            }
        } catch (Throwable $e) {
            error_log('ADMIN CLAIMS ERROR: ' . $e->getMessage());
            if ($error === '') {
                $error = 'Could not process the claim. Please check the Railway logs.';
            }
        }
    }
}

$claims = false;
try {
    $claims = $conn->query(
        "SELECT c.*, i.item_name, i.item_type, i.status AS item_status,
                cat.category_name, u.full_name AS claimant_name, u.email
         FROM claims c
         JOIN items i ON c.item_id = i.item_id
         JOIN users u ON c.user_id = u.user_id
         LEFT JOIN categories cat ON i.category_id = cat.category_id
         ORDER BY FIELD(c.claim_status, 'pending', 'approved', 'completed', 'rejected'),
                  c.claim_date DESC"
    );

    if (!$claims) {
        throw new RuntimeException('Could not load claims: ' . $conn->error);
    }
} catch (Throwable $e) {
    error_log('ADMIN CLAIMS LIST ERROR: ' . $e->getMessage());
    $error = $error ?: 'Could not load claims. Please check the database configuration and Railway logs.';
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
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        .claims-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        .claims-table {
            width: 100%;
            min-width: 950px;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .claims-table thead th {
            background: #eaf1fb !important;
            color: #1e293b !important;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 18px 16px;
            border: 0;
            border-bottom: 2px solid #cbd5e1 !important;
            white-space: nowrap;
        }

        .claims-table tbody td {
            vertical-align: middle;
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 0.9rem;
            background: #fff;
        }

        .claims-table tbody tr:hover td {
            background: #f1f6ff;
        }

        .claims-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .claims-table .claim-id {
            color: #64748b;
            font-weight: 700;
        }

        .claims-table .claimant-email,
        .claims-table .item-type,
        .claims-table .claim-date {
            color: #64748b;
            font-size: 0.8rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-pending { background: #fef9c3; color: #713f12; }
        .status-approved { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-rejected { background: #fee2e2; color: #991b1b; }

        .claim-actions {
            min-width: 190px;
        }

        .claim-actions .form-control {
            border-color: #cbd5e1;
            border-radius: 8px;
            font-size: 0.8rem;
        }

        .claim-actions .btn {
            font-weight: 600;
        }

        .claims-empty {
            padding: 48px 20px !important;
            text-align: center;
            color: #64748b !important;
        }

        @media (max-width: 768px) {
            .page-content { padding: 1rem !important; }
            .claims-table thead th { padding: 14px 12px; }
            .claims-table tbody td { padding: 13px 12px; }
        }
    </style>
</head>
<body class="sidebar-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="topbar-title">Manage Claims</span>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?>
                </div>
            </div>
        </div>

        <div class="page-content p-4">
            <div class="alert alert-warning border-warning shadow-sm mb-4" style="background:#fffbeb;color:#713f12;">
                <strong><i class="fas fa-info-circle me-2"></i>Organization Office drop-off / pickup point:</strong>
                All found items must be surrendered to the Organization Office. Claimants must pick up approved items there.
                Mark an item as <strong>Returned</strong> only after the physical handover at the office.
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success shadow-sm"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="claims-card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle claims-table">
                        <thead>
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
                            <?php if ($claims && $claims->num_rows > 0): ?>
                                <?php while ($c = $claims->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 claim-id">#<?php echo (int) $c['claim_id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($c['item_name']); ?></strong><br>
                                            <small class="item-type"><?php echo htmlspecialchars(ucfirst($c['item_type'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($c['claimant_name']); ?></strong><br>
                                            <small class="claimant-email"><?php echo htmlspecialchars($c['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?php echo htmlspecialchars($c['category_name'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo htmlspecialchars(strtolower($c['claim_status'])); ?>">
                                                <?php echo htmlspecialchars(ucfirst($c['claim_status'])); ?>
                                            </span>
                                        </td>
                                        <td style="max-width:200px;">
                                            <small class="text-muted"><?php echo nl2br(htmlspecialchars($c['admin_notes'] ?? '')); ?></small>
                                        </td>
                                        <td>
                                            <small class="claim-date">
                                                <?php echo htmlspecialchars(date('M d, Y', strtotime($c['claim_date']))); ?>
                                            </small>
                                        </td>
                                        <td class="pe-4 claim-actions">
                                            <?php if ($c['claim_status'] === 'pending'): ?>
                                                <form method="POST" class="mb-2">
                                                    <input type="hidden" name="claim_id" value="<?php echo (int) $c['claim_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <textarea name="admin_notes" class="form-control form-control-sm mb-2" placeholder="Notes (optional)" rows="2"></textarea>
                                                    <button type="submit" class="btn btn-sm btn-success w-100 rounded-pill" onclick="return confirm('Approve this claim?')">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST">
                                                    <input type="hidden" name="claim_id" value="<?php echo (int) $c['claim_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100 rounded-pill" onclick="return confirm('Reject this claim?')">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </form>
                                            <?php elseif ($c['claim_status'] === 'approved'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="claim_id" value="<?php echo (int) $c['claim_id']; ?>">
                                                    <input type="hidden" name="action" value="returned">
                                                    <button type="submit" class="btn btn-sm btn-primary w-100 rounded-pill" onclick="return confirm('Mark this item as returned?')">
                                                        <i class="fas fa-hand-holding-heart me-1"></i>Mark Returned
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted small">No action needed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="claims-empty">
                                        <i class="fas fa-clipboard-check fa-2x mb-3 d-block"></i>
                                        No claims have been submitted yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
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
