<?php
require_once '../config/config.php';
requireLogin();
if (!isAdmin()) {
    header("Location: ../dashboard.php");
    exit();
}
date_default_timezone_set('Asia/Manila');
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
        try {
            $conn->begin_transaction();
            // Find the latest approved claim associated with this item.
            $stmt = $conn->prepare("
                SELECT c.claim_id, c.claimant_id, i.item_name
                FROM claims c
                INNER JOIN items i ON c.item_id = i.item_id
                WHERE c.item_id = ?
                  AND c.claim_status = 'approved'
                ORDER BY c.created_at DESC
                LIMIT 1
            ");
            if (!$stmt) {
                throw new RuntimeException('SELECT approved claim failed: ' . $conn->error);
            }
            $stmt->bind_param('i', $item_id);
            if (!$stmt->execute()) {
                throw new RuntimeException('SELECT approved claim execute failed: ' . $stmt->error);
            }
            $claim = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            // Mark the item as returned.
            $stmt = $conn->prepare("UPDATE items SET status = 'returned' WHERE item_id = ?");
            if (!$stmt) {
                throw new RuntimeException('UPDATE item failed: ' . $conn->error);
            }
            $stmt->bind_param('i', $item_id);
            if (!$stmt->execute()) {
                throw new RuntimeException('UPDATE item execute failed: ' . $stmt->error);
            }
            $stmt->close();
            // Complete the latest approved claim and notify its claimant.
            if ($claim) {
                $stmt = $conn->prepare("
                    UPDATE claims
                    SET claim_status = 'completed',
                        admin_notes = CONCAT(IFNULL(admin_notes, ''), ' [Marked returned by admin]')
                    WHERE claim_id = ?
                ");
                if (!$stmt) {
                    throw new RuntimeException('UPDATE approved claim failed: ' . $conn->error);
                }
                $stmt->bind_param('i', $claim['claim_id']);
                if (!$stmt->execute()) {
                    throw new RuntimeException('UPDATE approved claim execute failed: ' . $stmt->error);
                }
                $stmt->close();
                sendNotification(
                    $claim['claimant_id'],
                    $item_id,
                    'item_returned',
                    "The item '{$claim['item_name']}' has been marked as returned. Thank you for coordinating with the Organization Office."
                );
            }
            // Reject any other pending or approved claims for this item.
            $stmt = $conn->prepare("
                UPDATE claims
                SET claim_status = 'rejected',
                    admin_notes = CONCAT(IFNULL(admin_notes, ''), ' [Auto-rejected: item already returned]')
                WHERE item_id = ?
                  AND claim_status IN ('pending', 'approved')
            ");
            if (!$stmt) {
                throw new RuntimeException('UPDATE remaining claims failed: ' . $conn->error);
            }
            $stmt->bind_param('i', $item_id);
            if (!$stmt->execute()) {
                throw new RuntimeException('UPDATE remaining claims execute failed: ' . $stmt->error);
            }
            $stmt->close();
            logActivity(
                $_SESSION['user_id'],
                'mark_returned',
                "Marked item #{$item_id} returned from items page"
            );
            $conn->commit();
            $message = 'Item marked as returned.';
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('MARK RETURNED ERROR: ' . $e->getMessage());
            $error = 'Could not mark item as returned. Please check the Railway logs.';
        }
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("SELECT item_id, item_name, image_path FROM items WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            $error = 'Item not found.';
        } else {
            $proofs = [];
            $pstmt = $conn->prepare("SELECT id_proof_path FROM claims WHERE item_id = ? AND id_proof_path IS NOT NULL AND id_proof_path <> ''");
            $pstmt->bind_param("i", $item_id);
            $pstmt->execute();
            $pres = $pstmt->get_result();
            while ($proof = $pres->fetch_assoc()) {
                $proofs[] = $proof['id_proof_path'];
            }
            $pstmt->close();
            $conn->begin_transaction();
            try {
                $d1 = $conn->prepare("DELETE FROM claims WHERE item_id = ?");
                $d1->bind_param("i", $item_id);
                if (!$d1->execute()) {
                    throw new Exception('claims');
                }
                $d1->close();
                $d2 = $conn->prepare("DELETE FROM items WHERE item_id = ?");
                $d2->bind_param("i", $item_id);
                if (!$d2->execute()) {
                    throw new Exception('item');
                }
                $d2->close();
                $conn->commit();
                $uploadRoot = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                $paths = array_filter(array_merge([$row['image_path'] ?? ''], $proofs));
                foreach ($paths as $rel) {
                    $rel = ltrim(str_replace(['\\\\', '..'], ['/', ''], $rel), '/');
                    $full = $uploadRoot . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                    if ($rel !== '' && is_file($full)) {
                        unlink($full);
                    }
                }
                logActivity($_SESSION['user_id'], 'delete_item', "Deleted item #$item_id ({$row['item_name']})");
                $message = 'Item "' . $row['item_name'] . '" was deleted.';
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Could not delete this item. Please try again.';
            }
        }
    } elseif (in_array($new_status, ['pending','returned','claimed','donated','expired'])) {
        $stmt = $conn->prepare("UPDATE items SET status = ? WHERE item_id = ?");
        if (!$stmt) {
            error_log('UPDATE item status prepare failed: ' . $conn->error);
            $error = 'Could not update item status. Please check the server logs.';
        } else {
            $stmt->bind_param('si', $new_status, $item_id);
            if (!$stmt->execute()) {
                error_log('UPDATE item status execute failed: ' . $stmt->error);
                $error = 'Could not update item status.';
            } else {
                logActivity($_SESSION['user_id'], 'update_item_status', "Item #$item_id status changed to $new_status");
                $message = 'Item status updated successfully.';
            }
            $stmt->close();
        }
    } else {
        $error = 'Invalid status.';
    }
}
$items = $conn->query("
    SELECT i.*, c.category_name, u.full_name AS reporter
    FROM items i
    LEFT JOIN categories c ON i.category_id = c.category_id
    LEFT JOIN users u ON i.user_id = u.user_id
    ORDER BY i.created_at DESC
");
// Get Stats
$stats_query = "SELECT
    (SELECT COUNT(*) FROM items) as total,
    (SELECT COUNT(*) FROM items WHERE status = 'pending') as pending,
    (SELECT COUNT(*) FROM items WHERE status = 'claimed') as claimed,
    (SELECT COUNT(*) FROM items WHERE status = 'returned') as returned";
$stats = $conn->query($stats_query)->fetch_assoc();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - iFindPeace Admin</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/msu-logo.jpg">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Core CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        /* ============================================================
           MANAGE ITEMS SPECIFIC STYLES
           ============================================================ */
        :root {
            --blue-950: #0a1628;
            --blue-900: #0f2444;
            --blue: #1e40af;
            --blue-mid: #2563eb;
            --yellow: #eab308;
            --yellow-hot: #facc15;
            --sky: #eff6ff;
            --paper: #f8fafc;
            --ink: #0f172a;
            --muted: #475569;
            --ease: cubic-bezier(.2, .8, .2, 1);
            --success: #1f9d6b;
            --danger: #dc2626;
            --purple: #7c3aed;
        }
        body {
            background-color: var(--paper);
            font-family: 'Instrument Sans', system-ui, sans-serif;
        }
        /* ── Topbar Override ── */
        .topbar { background: #ffffff !important; border-bottom: 2px solid var(--sky); }
        .topbar-title { color: var(--blue-900); font-weight: 800; }
        .topbar-user { background: var(--sky); color: var(--blue-900); border: 1px solid #dbeafe; }
        /* ── Stats Row (Glassmorphism Cards) ── */
        .item-stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(10, 22, 40, 0.04);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            transition: transform 0.3s var(--ease), box-shadow 0.3s var(--ease);
            position: relative;
            overflow: hidden;
        }
        .item-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(10, 22, 40, 0.08);
        }
        .item-stat-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--stat-color, var(--blue));
        }
        .item-stat-icon {
            width: 48px; height: 48px;
            border-radius: 14px;
            background: var(--stat-bg, var(--sky));
            color: var(--stat-color, var(--blue));
            display: grid; place-items: center;
            font-size: 1.3rem;
        }
        .item-stat-info h3 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--ink);
            margin: 0;
            line-height: 1;
        }
        .item-stat-info p {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0.2rem 0 0;
        }
        /* ── Main Table Container ── */
        .items-panel {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(10, 22, 40, 0.06);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }
        /* ── Toolbar ── */
        .panel-toolbar {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .search-box {
            position: relative;
            width: 300px;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .search-box input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 50px;
            font-size: 0.9rem;
            background: #f8fafc;
            transition: all 0.3s;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--blue);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1);
        }
        /* ── FIXED TABLE HEADER ── */
        .items-table { width: 100%; margin: 0; border-collapse: collapse; }
        .items-table th {
            background: #f8fafc; /* Light background */
            color: #64748b;      /* Dark readable text */
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 1.2rem 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        .items-table td {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .items-table tbody tr {
            transition: background 0.2s;
            opacity: 0;
            transform: translateY(10px);
            animation: rowFadeUp 0.5s var(--ease) forwards;
        }
        .items-table tbody tr:hover {
            background: var(--sky);
        }
        /* ── Badges ── */
        .type-badge {
            padding: 0.35rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .type-lost { background: var(--blue); color: #fff; box-shadow: 0 4px 10px rgba(30,64,175,0.2); }
        .type-found { background: var(--yellow); color: #713f12; box-shadow: 0 4px 10px rgba(234,179,8,0.2); }
        .status-badge {
            padding: 0.35rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .status-pending { background: #fef9c3; color: #713f12; }
        .status-claimed { background: #dbeafe; color: #1e40af; }
        .status-returned { background: #dcfce7; color: #166534; }
        .status-donated { background: #f3e8ff; color: #6b21a8; }
        .status-expired { background: #fee2e2; color: #991b1b; }
        /* ── Action Forms ── */
        .action-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            min-width: 240px;
        }
        .update-row {
            display: flex;
            gap: 0.5rem;
        }
        .status-select-custom {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.4rem 2rem 0.4rem 0.8rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--ink);
            background-color: #fff;
            cursor: pointer;
            flex: 1;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.7rem center;
            background-size: 1em;
        }
        .status-select-custom:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        .btn-update {
            background: #fff;
            color: var(--blue);
            border: 1px solid var(--blue);
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .btn-update:hover {
            background: var(--blue);
            color: #fff;
        }
        /* FIXED: Removed the pink glow, made it match the theme */
        .btn-mark-returned {
            background: linear-gradient(135deg, var(--yellow), #b45309);
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.3s var(--ease);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 12px rgba(234, 179, 8, 0.3);
        }
        .btn-mark-returned:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(234, 179, 8, 0.5);
        }
        .btn-delete {
            background: #fff;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 700;
            font-size: 0.85rem;
            transition: all 0.2s var(--ease);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        .btn-delete:hover {
            background: #dc2626;
            color: #fff;
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(220, 38, 38, 0.25);
        }
        /* Animations */
        @keyframes rowFadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="sidebar-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <!-- Topbar -->
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
            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <!-- Admin Reminder -->
            <div class="alert alert-warning border-warning shadow-sm d-flex align-items-center gap-3 mb-4" role="alert" style="background: #fffbeb; color: #713f12; border-radius: 16px; padding: 1.2rem;">
                <div style="background: var(--yellow); color: #713f12; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; font-size: 1.2rem;">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <strong class="d-block mb-1" style="font-family: 'Bricolage Grotesque';">Organization Office Drop-off Point</strong>
                    <span class="small">Found items must be surrendered to the Organization Office. Claimants pick them up there after admin approval. Use <strong>Mark Returned</strong> only after the item is physically handed over.</span>
                </div>
            </div>
            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="item-stat-card" style="--stat-color: var(--blue); --stat-bg: var(--sky);">
                        <div class="item-stat-icon"><i class="fas fa-box"></i></div>
                        <div class="item-stat-info">
                            <h3 data-count="<?php echo $stats['total']; ?>">0</h3>
                            <p>Total Items</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="item-stat-card" style="--stat-color: var(--yellow); --stat-bg: #fef9c3;">
                        <div class="item-stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="item-stat-info">
                            <h3 data-count="<?php echo $stats['pending']; ?>">0</h3>
                            <p>Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="item-stat-card" style="--stat-color: var(--purple); --stat-bg: #ede9fe;">
                        <div class="item-stat-icon"><i class="fas fa-hand-paper"></i></div>
                        <div class="item-stat-info">
                            <h3 data-count="<?php echo $stats['claimed']; ?>">0</h3>
                            <p>Claimed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="item-stat-card" style="--stat-color: var(--success); --stat-bg: #dcfce7;">
                        <div class="item-stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="item-stat-info">
                            <h3 data-count="<?php echo $stats['returned']; ?>">0</h3>
                            <p>Returned</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Main Panel -->
            <div class="items-panel">
                <!-- Toolbar -->
                <div class="panel-toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by item name or reporter...">
                    </div>
                    <div class="text-muted fw-bold small">
                        Showing <?php echo $items->num_rows; ?> items
                    </div>
                </div>
                <!-- Table -->
                <div class="table-responsive">
                    <table class="items-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item Details</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Reporter</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($items->num_rows > 0): ?>
                                <?php $delay = 0.05; while ($item = $items->fetch_assoc()): ?>
                                    <tr style="animation-delay: <?php echo $delay; ?>s;">
                                        <td class="fw-bold text-muted">#<?php echo $item['item_id']; ?></td>
                                        <td>
                                            <div class="fw-bold text-dark fs-6 mb-1"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            <div class="text-muted small">
                                                <i class="fas fa-map-marker-alt me-1" style="color: var(--yellow);"></i>
                                                <?php echo htmlspecialchars($item['location_lost'] ?? $item['location_found'] ?? 'Not specified'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="type-badge <?php echo $item['item_type'] == 'lost' ? 'type-lost' : 'type-found'; ?>">
                                                <i class="fas <?php echo $item['item_type'] == 'lost' ? 'fa-search' : 'fa-hand-holding-heart'; ?>"></i>
                                                <?php echo strtoupper($item['item_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border" style="font-size: 0.8rem;">
                                                <?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold text-muted"><?php echo htmlspecialchars($item['reporter'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($item['status']); ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-form-group">
                                                <?php if ($item['status'] !== 'returned'): ?>
                                                    <form method="POST" class="update-row">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                        <select name="status" class="status-select-custom">
                                                            <?php foreach (['pending','returned','claimed','donated','expired'] as $s): ?>
                                                                <option value="<?php echo $s; ?>" <?php echo $item['status'] === $s ? 'selected' : ''; ?>>
                                                                    <?php echo ucfirst($s); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button class="btn-update">Update</button>
                                                    </form>
                                                    <form method="POST">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                        <input type="hidden" name="action" value="mark_returned">
                                                        <button class="btn-mark-returned w-100" onclick="return confirm('Mark this item as RETURNED? Confirm handover at the Organization Office.')">
                                                            <i class="fas fa-hand-holding-heart"></i>Mark Returned
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-success small fw-bold d-flex align-items-center gap-2">
                                                        <i class="fas fa-check-circle fs-5"></i> Handed Over
                                                    </span>
                                                <?php endif; ?>
                                                <form method="POST">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn-delete w-100" onclick="return confirm('Delete this item permanently? Its photo and claims will be removed too.');">
                                                        <i class="fas fa-trash"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php $delay += 0.03; endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-box-open fa-3x mb-3" style="color: #cbd5e1;"></i>
                                            <h5>No items found</h5>
                                            <p>No items have been reported yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- end page-content -->
    </div><!-- end main-content -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        // ============================================================
        // 1. COUNT-UP ANIMATION
        // ============================================================
        document.addEventListener("DOMContentLoaded", () => {
            const counters = document.querySelectorAll('.item-stat-info h3');
            const speed = 200;
            counters.forEach(counter => {
                const updateCount = () => {
                    const target = +counter.getAttribute('data-count');
                    const count = +counter.innerText;
                    const inc = target / speed;
                    if (count < target) {
                        counter.innerText = Math.ceil(count + inc);
                        setTimeout(updateCount, 15);
                    } else {
                        counter.innerText = target;
                    }
                };
                updateCount();
            });
        });
        // ============================================================
        // 2. LIVE SEARCH FILTERING
        // ============================================================
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#itemsTable tbody tr');
            tableRows.forEach(row => {
                // Skip the "No items found" row if it exists
                if (row.cells.length === 1) return;
                const itemName = row.querySelector('.fs-6')?.textContent.toLowerCase() || '';
                const reporter = row.cells[4]?.textContent.toLowerCase() || '';
                if (itemName.includes(searchValue) || reporter.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
