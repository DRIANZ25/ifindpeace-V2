<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();

// Handle item status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $item_id = intval($_POST['item_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE items SET status = ? WHERE item_id = ?");
    $stmt->bind_param("si", $status, $item_id);
    $stmt->execute();
    $stmt->close();
    
    logActivity($_SESSION['user_id'], 'update_item_status', "Updated item $item_id status to $status");
    
    header("Location: items.php");
    exit();
}

// Handle item deletion
if (isset($_GET['delete'])) {
    $item_id = intval($_GET['delete']);
    
    // Get item image path
    $stmt = $conn->prepare("SELECT image_path FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($item && $item['image_path']) {
        deleteFile($item['image_path']);
    }
    
    $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $stmt->close();
    
    logActivity($_SESSION['user_id'], 'delete_item', "Deleted item $item_id");
    
    header("Location: items.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'claimed', 'donated', 'expired'], true) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['lost', 'found'], true) ? $_GET['type'] : '';

// Build query
$where = "WHERE 1=1";
if (!empty($status_filter)) {
    $where .= " AND status = '$status_filter'";
}
if (!empty($type_filter)) {
    $where .= " AND item_type = '$type_filter'";
}

$query = "SELECT i.*, c.category_name, u.full_name, u.student_id 
          FROM items i 
          JOIN categories c ON i.category_id = c.category_id 
          JOIN users u ON i.user_id = u.user_id 
          $where 
          ORDER BY i.created_at DESC";
$items = $conn->query($query);

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
                <h2 class="fw-bold">Manage Items</h2>
                <p class="text-muted">View and manage all reported items</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="claimed" <?php echo $status_filter === 'claimed' ? 'selected' : ''; ?>>Claimed</option>
                        <option value="donated" <?php echo $status_filter === 'donated' ? 'selected' : ''; ?>>Donated</option>
                        <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo $type_filter === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="found" <?php echo $type_filter === 'found' ? 'selected' : ''; ?>>Found</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if ($items->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Posted By</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['image_path']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="rounded me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded bg-light d-flex align-items-center justify-content-center me-2" style="width: 50px; height: 50px;">
                                                <i class="fas fa-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 40)); ?>...</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>">
                                        <?php echo strtoupper($item['item_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td>
                                    <small><?php echo htmlspecialchars($item['full_name']); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($item['student_id']); ?></small>
                                </td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $item['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="claimed" <?php echo $item['status'] === 'claimed' ? 'selected' : ''; ?>>Claimed</option>
                                            <option value="donated" <?php echo $item['status'] === 'donated' ? 'selected' : ''; ?>>Donated</option>
                                            <option value="expired" <?php echo $item['status'] === 'expired' ? 'selected' : ''; ?>>Expired</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                <td>
                                    <a href="../item-details.php?id=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="items.php?delete=<?php echo $item['item_id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this item?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open fa-4x"></i>
                <h4>No items found</h4>
                <p>Try adjusting your filters</p>
            </div>
        <?php endif; ?>
        </div><!-- end page-content -->
    </div><!-- end main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>
