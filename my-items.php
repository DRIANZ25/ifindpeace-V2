<?php
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$type = isset($_GET['type']) && in_array($_GET['type'], ['lost', 'found'], true) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'claimed', 'returned', 'donated', 'expired'], true) ? $_GET['status'] : '';

if ($type !== '' && $status_filter !== '') {
    $stmt = $conn->prepare("SELECT i.*, c.category_name
                            FROM items i
                            JOIN categories c ON i.category_id = c.category_id
                            WHERE i.user_id = ? AND i.item_type = ? AND i.status = ?
                            ORDER BY i.created_at DESC");
    $stmt->bind_param("iss", $user_id, $type, $status_filter);
} elseif ($type !== '') {
    $stmt = $conn->prepare("SELECT i.*, c.category_name
                            FROM items i
                            JOIN categories c ON i.category_id = c.category_id
                            WHERE i.user_id = ? AND i.item_type = ?
                            ORDER BY i.created_at DESC");
    $stmt->bind_param("is", $user_id, $type);
} elseif ($status_filter !== '') {
    $stmt = $conn->prepare("SELECT i.*, c.category_name
                            FROM items i
                            JOIN categories c ON i.category_id = c.category_id
                            WHERE i.user_id = ? AND i.status = ?
                            ORDER BY i.created_at DESC");
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt = $conn->prepare("SELECT i.*, c.category_name
                            FROM items i
                            JOIN categories c ON i.category_id = c.category_id
                            WHERE i.user_id = ?
                            ORDER BY i.created_at DESC");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$items = $stmt->get_result();
$item_rows = $items ? $items->fetch_all(MYSQLI_ASSOC) : [];
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Items - iFindPeace</title>
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
                <span class="topbar-title">My Items</span>
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
                <h2 class="fw-bold">My Items</h2>
                <p class="text-muted">Manage your reported lost and found items</p>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="btn-group me-2" role="group">
                    <a href="my-items.php" class="btn <?php echo empty($type) && empty($status_filter) ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    <a href="my-items.php?type=lost" class="btn <?php echo $type === 'lost' && empty($status_filter) ? 'btn-primary' : 'btn-outline-primary'; ?>">Lost</a>
                    <a href="my-items.php?type=found" class="btn <?php echo $type === 'found' && empty($status_filter) ? 'btn-primary' : 'btn-outline-primary'; ?>">Found</a>
                </div>
                <div class="btn-group" role="group">
                    <a href="my-items.php?status=pending" class="btn <?php echo $status_filter === 'pending' ? 'btn-primary' : 'btn-outline-primary'; ?>">Pending</a>
                    <a href="my-items.php?status=returned" class="btn <?php echo $status_filter === 'returned' ? 'btn-primary' : 'btn-outline-primary'; ?>">Returned</a>
                    <a href="my-items.php?status=claimed" class="btn <?php echo $status_filter === 'claimed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Claimed</a>
                </div>
            </div>
        </div>

        <?php if ($item_rows): ?>
            <div class="row g-3">
                <?php foreach ($item_rows as $item): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 item-card">
                            <div class="card-image-wrapper">
                                <?php if ($item['image_path']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-badge">
                                    <span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>">
                                        <?php echo strtoupper($item['item_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                <p class="card-text text-truncate mb-2 small text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-light text-dark small">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?>
                                    </span>
                                    <span class="badge <?php echo $item['status'] == 'pending' ? 'bg-warning' : ($item['status'] == 'claimed' ? 'bg-success' : ($item['status'] == 'returned' ? 'badge-returned' : 'bg-secondary')); ?> small">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                </div>
                                <small class="text-muted small">
                                    <i class="fas fa-clock me-1"></i><?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                </small>
                            </div>
                            <div class="card-footer">
                                <a href="item-details.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary btn-sm w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open fa-3x"></i>
                <h5>No items reported yet</h5>
                <p class="small">Start by reporting a lost or found item</p>
                <a href="report.php" class="btn btn-primary btn-sm">Report an Item</a>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sidebar.js"></script>
</body>
</html>
