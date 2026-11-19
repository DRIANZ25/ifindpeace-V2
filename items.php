<?php
require_once 'config/config.php';

$conn = getDBConnection();

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$type = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : '';

// Build query
$where = "WHERE i.status = 'pending'";
$params = [];
$types = '';

if (!empty($search)) {
    $where .= " AND (i.item_name LIKE ? OR i.description LIKE ? OR i.brand LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($type)) {
    $where .= " AND i.item_type = ?";
    $params[] = $type;
    $types .= 's';
}

if (!empty($category)) {
    $where .= " AND i.category_id = ?";
    $params[] = $category;
    $types .= 'i';
}

$query = "SELECT i.*, c.category_name, u.full_name, u.student_id 
          FROM items i 
          JOIN categories c ON i.category_id = c.category_id 
          JOIN users u ON i.user_id = u.user_id 
          $where 
          ORDER BY i.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $item_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $item_rows = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

$categories = $conn->query("SELECT * FROM categories ORDER BY category_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Items - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <div class="d-flex align-items-center">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-search-location me-2"></i>iFind<span>Peace</span>
                </a>
                <div class="msu-nav-text ms-3 d-none d-lg-flex align-items-center">
                    <img src="assets/images/msu-logo.jpg" alt="MSU Logo" class="msu-logo-icon me-2">
                    <div>
                        <small class="text-white" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">MINDANAO STATE UNIVERSITY</small>
                        <br>
                        <small class="text-white" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">GENERAL SANTOS</small>
                    </div>
                </div>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="items.php">Browse Items</a>
                    </li>
                    <?php if (isLoggedIn() && !isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="report.php">Report Item</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <?php
                                $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
                                if ($unread_count > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu notification-dropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php
                                $conn = getDBConnection();
                                $notif_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
                                $stmt = $conn->prepare($notif_query);
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $notifications = $stmt->get_result();

                                if ($notifications->num_rows > 0):
                                    while ($notif = $notifications->fetch_assoc()): ?>
                                        <li class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                            <small class="text-muted"><?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></small>
                                            <p class="mb-0 small"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        </li>
                                    <?php endwhile;
                                else: ?>
                                    <li class="notification-item">
                                        <p class="mb-0 small text-muted">No notifications</p>
                                    </li>
                                <?php endif;
                                $stmt->close();
                                $conn->close();
                                ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="notifications.php">View All</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i>
                                <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="my-items.php"><i class="fas fa-box me-2"></i>My Items</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Log In
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn ms-2" href="register.php" style="background: linear-gradient(135deg, #ff69b4 0%, #ff00ff 100%); color: white; border: none; padding: 0.5rem 1.25rem; font-weight: 700; box-shadow: 0 2px 8px rgba(255,105,180,0.4);">
                                <i class="fas fa-user-plus me-1"></i>Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">Browse Items</h2>
                <p class="text-muted">Search through lost and found items</p>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Items Grid -->
        <?php if ($item_rows): ?>
            <div class="row g-3">
                <?php foreach ($item_rows as $item): ?>
                    <div class="col-md-6 col-lg-3">
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
                                <div class="mb-2">
                                    <span class="badge bg-light text-dark small">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?>
                                    </span>
                                    <?php if ($item['color']): ?>
                                        <span class="badge bg-light text-dark small">
                                            <i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($item['color']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="card-text small text-muted mb-2">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($item['item_type'] == 'lost' ? $item['location_lost'] : $item['location_found']); ?>
                                </p>
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
                <i class="fas fa-search fa-3x"></i>
                <h5>No items found</h5>
                <p class="small">Try adjusting your search or filters</p>
                <a href="items.php" class="btn btn-primary btn-sm">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
