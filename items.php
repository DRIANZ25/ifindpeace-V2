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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- FIXED: Removed the ../ because this file is in the root folder -->
    <link href="assets/css/mobile.css" rel="stylesheet">
    <style>
        :root { --blue-950:#0a1628; --blue-900:#0f2444; --blue:#1e40af; --yellow:#eab308; --yellow-hot:#facc15; --sky:#eff6ff; --paper:#f8fafc; --ink:#0f172a; --muted:#475569; --ease:cubic-bezier(.2,.8,.2,1); }
        body { font-family:'Instrument Sans',system-ui,sans-serif; color:var(--ink); background:var(--paper); }
        h1,h2,h3,h4,h5,h6,.navbar-brand { font-family:'Bricolage Grotesque',sans-serif; }

        /* ── Navbar ── */
        body .navbar { background:linear-gradient(100deg,var(--blue-950),var(--blue-900),#1e3a8a); border-bottom:2px solid var(--yellow); box-shadow:0 6px 24px rgba(10,22,40,.35); }
        body .navbar .navbar-brand { font-weight:800; font-size:1.5rem; }
        body .navbar .navbar-brand span { color:var(--yellow-hot); }
        body .navbar .nav-link { font-weight:600; opacity:.85; transition:opacity .2s; }
        body .navbar .nav-link:hover, body .navbar .nav-link.active { opacity:1; }
        .btn-signup-nav { background:linear-gradient(135deg,var(--yellow),var(--yellow-hot)); color:#713f12; font-weight:800; border:0; border-radius:50px; padding:.5rem 1.25rem; transition:transform .2s, box-shadow .2s; }
        .btn-signup-nav:hover { color:#713f12; transform:translateY(-2px); box-shadow:0 8px 20px rgba(234,179,8,.45); }

        /* ── Filter Section ── */
        .filter-section { background:#fff; border-radius:18px; padding:1.25rem; box-shadow:0 8px 24px rgba(10,22,40,.06); margin-bottom:2rem; }
        .form-control, .form-select { border:2px solid #dbeafe; border-radius:12px; padding:.65rem 1rem; font-size:.95rem; }
        .form-control:focus, .form-select:focus { border-color:var(--yellow); box-shadow:0 0 0 4px rgba(234,179,8,.2); }
        .btn-filter { background:linear-gradient(135deg,var(--blue),#1e3a8a); color:#fff; font-weight:700; border:0; border-radius:12px; transition:transform .2s; }
        .btn-filter:hover { color:#fff; transform:translateY(-2px); box-shadow:0 8px 20px rgba(30,64,175,.35); }

        /* ── Item Cards ── */
        .item-card { position:relative; background:#fff; border:0; border-radius:16px; overflow:hidden; height:100%; box-shadow:0 6px 20px rgba(10,22,40,.08); transition:transform .3s var(--ease), box-shadow .3s; animation:fadeUp .5s var(--ease) both; }
        .item-card:hover { transform:translateY(-5px); box-shadow:0 16px 36px rgba(30,64,175,.15); }
        @keyframes fadeUp { from{opacity:0;transform:translateY(15px);} to{opacity:1;transform:none;} }

        .card-image-wrapper { position:relative; height:180px; overflow:hidden; background:var(--sky); }
        .card-image-wrapper img { width:100%; height:100%; object-fit:cover; transition:transform .6s var(--ease); }
        .item-card:hover .card-image-wrapper img { transform:scale(1.08); }
        .card-image-wrapper::after { content:''; position:absolute; top:0; left:-100%; width:100%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent); transition:left .7s ease; pointer-events:none; }
        .item-card:hover .card-image-wrapper::after { left:100%; }

        .card-badge { position:absolute; top:12px; right:12px; z-index:2; }
        .badge-lost { background:var(--blue); color:#fff; font-weight:800; font-size:.7rem; letter-spacing:1px; padding:.35rem .85rem; border-radius:50px; box-shadow:0 3px 10px rgba(0,0,0,.2); }
        .badge-found { background:var(--yellow); color:#713f12; font-weight:800; font-size:.7rem; letter-spacing:1px; padding:.35rem .85rem; border-radius:50px; box-shadow:0 3px 10px rgba(0,0,0,.2); }
        
        .item-card .card-title { font-weight:800; color:var(--ink); letter-spacing:-.3px; font-size:.95rem; }
        .item-card .card-text { font-size:.82rem; }
        .meta-chip { background:var(--sky); color:var(--blue); font-weight:600; font-size:.72rem; padding:.25rem .65rem; border-radius:50px; display:inline-block; margin-bottom:.3rem; }
        .item-card .card-footer { background:transparent; border-top:2px dashed #dbeafe; padding:.85rem 1rem; }
        .btn-details { border:2px solid var(--blue); color:var(--blue); font-weight:700; border-radius:50px; background:transparent; transition:background .2s, color .2s; font-size:.8rem; }
        .btn-details:hover { background:var(--blue); color:#fff; }

        .empty-state { background:#fff; border-radius:20px; text-align:center; padding:3rem 1.5rem; box-shadow:0 8px 22px rgba(10,22,40,.08); border:2px dashed #dbeafe; }
        .empty-state i { color:#93c5fd; margin-bottom:1rem; }

        @media (prefers-reduced-motion: reduce) { *,*::before,*::after{animation:none!important;transition:none!important;} }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <div class="d-flex align-items-center">
                <a class="navbar-brand" href="index.php">
                    <i class="fas fa-search-location me-2"></i>iFind<span>Peace</span>
                </a>
                <!-- FIXED: Changed text-dark to text-white -->
                <div class="msu-nav-text ms-3 d-none d-lg-flex align-items-center">
                    <img src="assets/images/msu-logo.jpg" alt="MSU Logo" class="msu-logo-icon me-2">
                    <div>
                        <small class="text-white" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700;">College of Education</small>
                        <br>
                        <small class="text-white" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600;">MSU-GenSan</small>
                    </div>
                </div>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
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
                        <li class="nav-item ms-lg-2">
                            <!-- FIXED: Changed pink gradient to yellow gradient -->
                            <a class="btn btn-signup-nav w-100" href="register.php">
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
                <h2 class="fw-bold" style="color: var(--blue-900); letter-spacing: -.5px;">Browse Items</h2>
                <p class="text-muted">Search through lost and found items on campus</p>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted mb-1">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Item name, brand, or keyword..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted mb-1">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted mb-1">Category</label>
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
                    <button type="submit" class="btn btn-filter w-100 py-2">
                        <i class="fas fa-search me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Items Grid -->
        <?php if ($item_rows): ?>
            <div class="row g-4">
                <?php foreach ($item_rows as $item): ?>
                    <div class="col-md-6 col-lg-3">
                        <div class="card item-card">
                            <div class="card-image-wrapper">
                                <?php if ($item['image_path']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center h-100">
                                        <i class="fas fa-image fa-2x text-muted" style="color: #93c5fd !important;"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-badge">
                                    <span class="<?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>">
                                        <?php echo strtoupper($item['item_type']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-3">
                                <h6 class="card-title mb-1 text-truncate"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                <p class="card-text text-truncate mb-2 text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                <div class="mb-2">
                                    <span class="meta-chip">
                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?>
                                    </span>
                                    <?php if ($item['color']): ?>
                                        <span class="meta-chip">
                                            <i class="fas fa-palette me-1"></i><?php echo htmlspecialchars($item['color']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <p class="card-text small text-muted mb-0 text-truncate">
                                    <i class="fas fa-map-marker-alt me-1" style="color: var(--yellow);"></i>
                                    <?php echo htmlspecialchars($item['item_type'] == 'lost' ? $item['location_lost'] : $item['location_found']); ?>
                                </p>
                            </div>
                            <div class="card-footer">
                                <a href="item-details.php?id=<?php echo $item['item_id']; ?>" class="btn btn-details w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search fa-4x"></i>
                <h5 class="fw-bold mt-2">No items found</h5>
                <p class="small text-muted">Try adjusting your search or filters to find what you're looking for.</p>
                <a href="items.php" class="btn btn-filter px-4 mt-2">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>