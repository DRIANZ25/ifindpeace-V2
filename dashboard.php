<?php
require_once 'config/config.php';
requireLogin();

$conn = getDBConnection();

// Get user statistics
$user_id = $_SESSION['user_id'];
$stats_query = "SELECT
                (SELECT COUNT(*) FROM items WHERE user_id = ? AND item_type = 'lost' AND status = 'pending') as my_lost,
                (SELECT COUNT(*) FROM items WHERE user_id = ? AND item_type = 'found' AND status = 'pending') as my_found,
                (SELECT COUNT(*) FROM claims WHERE user_id = ? AND claim_status = 'pending') as pending_claims,
                (SELECT COUNT(*) FROM claims WHERE user_id = ? AND claim_status = 'approved') as approved_claims,
                (SELECT COUNT(*) FROM items WHERE user_id = ? AND status = 'returned') as returned_items";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent items
$recent_items_query = "SELECT i.*, c.category_name
                      FROM items i
                      JOIN categories c ON i.category_id = c.category_id
                      WHERE i.status = 'pending'
                      ORDER BY i.created_at DESC
                      LIMIT 6";
$recent_items = $conn->query($recent_items_query);

// Get user's recent items
$my_items_query = "SELECT i.*, c.category_name 
                  FROM items i 
                  JOIN categories c ON i.category_id = c.category_id 
                  WHERE i.user_id = ? 
                  ORDER BY i.created_at DESC 
                  LIMIT 4";
$stmt = $conn->prepare($my_items_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_items = $stmt->get_result();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="topbar-title">Dashboard</span>
            </div>
            <div class="topbar-right">
                <?php 
                $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
                ?>
                <a href="notifications.php" class="topbar-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="topbar-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p class="text-muted">Here's what's happening with your account</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-search fa-lg" style="color: #800000;"></i>
                    </div>
                    <div class="stat-number" style="color: #800000;"><?php echo $stats['my_lost']; ?></div>
                    <div class="stat-label">My Lost Items</div>
                    <a href="my-items.php?type=lost" class="btn btn-sm btn-outline-primary mt-1">View</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success text-center">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-hand-holding-heart fa-lg" style="color: #ff00ff;"></i>
                    </div>
                    <div class="stat-number" style="color: #ff00ff;"><?php echo $stats['my_found']; ?></div>
                    <div class="stat-label">My Found Items</div>
                    <a href="my-items.php?type=found" class="btn btn-sm btn-outline-primary mt-1">View</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-hand-paper fa-lg" style="color: #c71585;"></i>
                    </div>
                    <div class="stat-number" style="color: #c71585;"><?php echo $stats['pending_claims']; ?></div>
                    <div class="stat-label">Pending Claims</div>
                    <a href="my-claims.php" class="btn btn-sm btn-outline-primary mt-1">View</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center" style="border-left-color: #22c55e;">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-undo fa-lg" style="color: #22c55e;"></i>
                    </div>
                    <div class="stat-number" style="color: #22c55e;"><?php echo $stats['returned_items']; ?></div>
                    <div class="stat-label">Returned Items</div>
                    <a href="my-items.php?status=returned" class="btn btn-sm btn-outline-primary mt-1">View</a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <a href="report.php" class="btn w-100 py-2" style="background: linear-gradient(135deg, #800000 0%, #ff00ff 100%); color: white; font-weight: 700; padding: 0.625rem 1.25rem; box-shadow: 0 2px 8px rgba(128,0,0,0.3);">
                                    <i class="fas fa-plus-circle fa-lg me-1"></i>
                                    Report Lost Item
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="report.php?type=found" class="btn w-100 py-2" style="background: linear-gradient(135deg, #ff00ff 0%, #c71585 100%); color: white; font-weight: 700; padding: 0.625rem 1.25rem; box-shadow: 0 2px 8px rgba(255,0,255,0.3);">
                                    <i class="fas fa-hand-holding-heart fa-lg me-1"></i>
                                    Report Found Item
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="items.php" class="btn w-100 py-2" style="background: linear-gradient(135deg, #c71585 0%, #ff69b4 100%); color: white; font-weight: 700; padding: 0.625rem 1.25rem; box-shadow: 0 2px 8px rgba(199,21,133,0.3);">
                                    <i class="fas fa-search fa-lg me-1"></i>
                                    Browse Items
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="my-items.php" class="btn w-100 py-2" style="background: linear-gradient(135deg, #ff69b4 0%, #800000 100%); color: white; font-weight: 700; padding: 0.625rem 1.25rem; box-shadow: 0 2px 8px rgba(255,105,180,0.3);">
                                    <i class="fas fa-box fa-lg me-1"></i>
                                    My Items
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- My Recent Items -->
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-box me-2"></i>My Recent Items</h6>
                        <a href="my-items.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($my_items->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($item = $my_items->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <a href="item-details.php?id=<?php echo $item['item_id']; ?>">
                                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>">
                                                        <?php echo strtoupper($item['item_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($item['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open fa-3x"></i>
                                <p class="mb-0">No items reported yet</p>
                                <a href="report.php" class="btn btn-primary btn-sm mt-2">Report an Item</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Platform Items -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Platform Items</h5>
                        <a href="items.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_items->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Type</th>
                                            <th>Category</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($item = $recent_items->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <a href="item-details.php?id=<?php echo $item['item_id']; ?>">
                                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>">
                                                        <?php echo strtoupper($item['item_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-box-open fa-3x"></i>
                                <p class="mb-0">No items on the platform yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- end page-content -->
    </div><!-- end main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
</body>
</html>
