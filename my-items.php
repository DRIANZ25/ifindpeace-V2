<?php
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$type = isset($_GET['type']) && in_array($_GET['type'], ['lost', 'found'], true) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'claimed', 'returned', 'donated', 'expired'], true) ? $_GET['status'] : '';

// Filter badges counts
$count_stmt = $conn->prepare("SELECT 
    COUNT(*) AS total,
    COALESCE(SUM(item_type='lost'),0) AS lost,
    COALESCE(SUM(item_type='found'),0) AS found,
    COALESCE(SUM(status='pending'),0) AS pending,
    COALESCE(SUM(status='returned'),0) AS returned,
    COALESCE(SUM(status='claimed'),0) AS claimed
    FROM items WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$counts = $count_stmt->get_result()->fetch_assoc();
$count_stmt->close();

if ($type !== '' && $status_filter !== '') {
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i JOIN categories c ON i.category_id = c.category_id WHERE i.user_id = ? AND i.item_type = ? AND i.status = ? ORDER BY i.created_at DESC");
    $stmt->bind_param("iss", $user_id, $type, $status_filter);
} elseif ($type !== '') {
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i JOIN categories c ON i.category_id = c.category_id WHERE i.user_id = ? AND i.item_type = ? ORDER BY i.created_at DESC");
    $stmt->bind_param("is", $user_id, $type);
} elseif ($status_filter !== '') {
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i JOIN categories c ON i.category_id = c.category_id WHERE i.user_id = ? AND i.status = ? ORDER BY i.created_at DESC");
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt = $conn->prepare("SELECT i.*, c.category_name FROM items i JOIN categories c ON i.category_id = c.category_id WHERE i.user_id = ? ORDER BY i.created_at DESC");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$item_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <style>
        :root { --blue-950:#0a1628; --blue-900:#0f2444; --blue:#1e40af; --yellow:#eab308; --yellow-hot:#facc15; --sky:#eff6ff; --paper:#f8fafc; --ink:#0f172a; --muted:#475569; --ease:cubic-bezier(.2,.8,.2,1); }
        body { font-family:'Instrument Sans',system-ui,sans-serif; color:var(--ink); background:var(--paper); }
        h1,h2,h3,h4,h5,h6 { font-family:'Bricolage Grotesque',sans-serif; }
        .topbar { background:linear-gradient(100deg,var(--blue-950),var(--blue-900)); border-bottom:2px solid var(--yellow); color:#fff; padding:1rem 2rem; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; box-shadow:0 4px 18px rgba(10,22,40,.3); }
        .topbar-title { font-weight:800; color:#fff; font-size:1.15rem; }
        .topbar-left { display:flex; align-items:center; gap:1rem; }
        .sidebar-toggle { background:rgba(255,255,255,.1); border:0; color:#fff; width:40px; height:40px; border-radius:12px; }
        .sidebar-toggle:hover { background:rgba(250,204,21,.35); }
        .topbar-bell { position:relative; color:#fff; font-size:1.15rem; margin-right:1.2rem; }
        .topbar-bell:hover { color:var(--yellow-hot); }
        .topbar-badge { position:absolute; top:-6px; right:-8px; background:var(--yellow-hot); color:#713f12; font-size:.65rem; font-weight:800; padding:2px 6px; border-radius:50px; animation:bellPulse 2s infinite; }
        @keyframes bellPulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.15);} }
        .topbar-user { display:flex; align-items:center; gap:.5rem; font-weight:600; font-size:.9rem; }

        .page-head { padding:1.5rem 0 .5rem; }
        .page-head h2 { font-weight:800; letter-spacing:-.5px; color:var(--blue-900); }

        .filter-bar { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.5rem; }
        .filter-pill {
            display:inline-flex; align-items:center; gap:.45rem; padding:.55rem 1.1rem;
            border-radius:50px; background:#fff; border:2px solid #dbeafe; color:var(--blue);
            font-weight:700; font-size:.85rem; text-decoration:none;
            transition:transform .2s var(--ease), background .2s, color .2s, border-color .2s;
        }
        .filter-pill:hover { transform:translateY(-2px); border-color:var(--yellow); color:#713f12; }
        .filter-pill.active { background:linear-gradient(135deg,var(--blue),var(--yellow)); border-color:transparent; color:#fff; }
        .filter-pill .count { background:rgba(255,255,255,.25); padding:1px 8px; border-radius:50px; font-size:.72rem; }
        .filter-pill:not(.active) .count { background:var(--sky); color:var(--blue); }

        .item-card { position:relative; background:#fff; border:0; border-radius:18px; overflow:hidden; height:100%;
            box-shadow:0 8px 22px rgba(10,22,40,.08);
            transition:transform .3s var(--ease), box-shadow .3s;
            animation:fadeUp .6s var(--ease) both; }
        .item-card:nth-child(1){animation-delay:.05s;} .item-card:nth-child(2){animation-delay:.1s;}
        .item-card:nth-child(3){animation-delay:.15s;} .item-card:nth-child(4){animation-delay:.2s;}
        .item-card:nth-child(5){animation-delay:.25s;} .item-card:nth-child(6){animation-delay:.3s;}
        @keyframes fadeUp { from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:none;} }
        .item-card:hover { transform:translateY(-6px); box-shadow:0 18px 40px rgba(30,64,175,.2); }
        .item-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:5px; background:var(--c,var(--blue)); z-index:3; }
        .card-image-wrapper { position:relative; height:180px; overflow:hidden; background:var(--sky); }
        .card-image-wrapper img { width:100%; height:100%; object-fit:cover; transition:transform .6s var(--ease); }
        .item-card:hover .card-image-wrapper img { transform:scale(1.08); }
        .card-image-wrapper::after { content:''; position:absolute; top:0; left:-100%; width:100%; height:100%;
            background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);
            transition:left .8s ease; pointer-events:none; }
        .item-card:hover .card-image-wrapper::after { left:100%; }
        .card-image-wrapper .ph { height:100%; display:flex; align-items:center; justify-content:center; color:#93c5fd; font-size:2.5rem; }
        .card-badge { position:absolute; top:12px; right:12px; z-index:2; }
        .pill-badge { font-weight:800; font-size:.68rem; letter-spacing:1px; padding:.35rem .85rem; border-radius:50px; color:#fff; box-shadow:0 3px 10px rgba(0,0,0,.2); }
        .pill-badge.lost { background:var(--blue); } .pill-badge.found { background:var(--yellow); color:#713f12; }
        .item-card .card-body { padding:1.1rem 1.2rem; }
        .item-card .card-title { font-weight:800; color:var(--ink); letter-spacing:-.3px; font-size:1rem; }
        .cat-chip { background:var(--sky); color:var(--blue); font-weight:600; font-size:.74rem; padding:.3rem .75rem; border-radius:50px; }
        .status-chip { font-weight:700; font-size:.72rem; padding:.3rem .8rem; border-radius:50px; }
        .status-chip.pending { background:#fef9c3; color:#713f12; }
        .status-chip.returned, .status-chip.claimed { background:#dcfce7; color:#166534; }
        .status-chip.donated { background:#dbeafe; color:#1e40af; }
        .status-chip.expired { background:#fee2e2; color:#991b1b; }
        .item-card .card-footer { background:transparent; border-top:2px dashed #dbeafe; padding:.85rem 1.2rem; }
        .btn-details { border:2px solid var(--blue); color:var(--blue); font-weight:700; border-radius:50px; background:transparent; transition:background .2s, color .2s; }
        .btn-details:hover { background:var(--blue); color:#fff; }

        .empty-state { background:#fff; border-radius:20px; text-align:center; padding:3rem 1.5rem; box-shadow:0 8px 22px rgba(10,22,40,.08); border:2px dashed #dbeafe; }
        .empty-state i { color:#93c5fd; margin-bottom:.8rem; }

        @media (prefers-reduced-motion: reduce) { *,*::before,*::after{animation:none!important;transition:none!important;} }
    </style>
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <span class="topbar-title">My Items</span>
            </div>
            <div class="topbar-right d-flex align-items-center">
                <?php $unread_count = getUnreadNotificationCount($_SESSION['user_id']); ?>
                <a href="notifications.php" class="topbar-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?><span class="topbar-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                </a>
                <div class="topbar-user"><i class="fas fa-user-circle"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
            </div>
        </div>

        <div class="page-content p-4">
            <div class="page-head d-flex flex-wrap justify-content-between align-items-end">
                <div>
                    <h2 class="mb-1">My Items</h2>
                    <p class="text-muted mb-0">Manage your reported lost and found items</p>
                </div>
                <a href="report.php" class="btn btn-primary rounded-pill px-4 mt-3 mt-md-0">
                    <i class="fas fa-plus-circle me-2"></i>Report Item
                </a>
            </div>

            <!-- Filter pills -->
            <div class="filter-bar">
                <a href="my-items.php" class="filter-pill <?php echo ($type==='' && $status_filter==='') ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i>All <span class="count"><?php echo (int)$counts['total']; ?></span>
                </a>
                <a href="my-items.php?type=lost" class="filter-pill <?php echo ($type==='lost' && !$status_filter) ? 'active' : ''; ?>">
                    <i class="fas fa-search"></i>Lost <span class="count"><?php echo (int)$counts['lost']; ?></span>
                </a>
                <a href="my-items.php?type=found" class="filter-pill <?php echo ($type==='found' && !$status_filter) ? 'active' : ''; ?>">
                    <i class="fas fa-hand-holding-heart"></i>Found <span class="count"><?php echo (int)$counts['found']; ?></span>
                </a>
                <a href="my-items.php?status=pending" class="filter-pill <?php echo $status_filter==='pending' ? 'active' : ''; ?>">
                    <i class="fas fa-hourglass-half"></i>Pending <span class="count"><?php echo (int)$counts['pending']; ?></span>
                </a>
                <a href="my-items.php?status=returned" class="filter-pill <?php echo $status_filter==='returned' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle"></i>Returned <span class="count"><?php echo (int)$counts['returned']; ?></span>
                </a>
                <a href="my-items.php?status=claimed" class="filter-pill <?php echo $status_filter==='claimed' ? 'active' : ''; ?>">
                    <i class="fas fa-hand-paper"></i>Claimed <span class="count"><?php echo (int)$counts['claimed']; ?></span>
                </a>
            </div>

            <?php if ($item_rows): ?>
                <div class="row g-3">
                    <?php foreach ($item_rows as $item): 
                        $type_color = $item['item_type'] === 'lost' ? 'var(--blue)' : 'var(--yellow)';
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card item-card" style="--c: <?php echo $type_color; ?>;">
                                <div class="card-image-wrapper">
                                    <?php if ($item['image_path']): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                    <?php else: ?>
                                        <div class="ph"><i class="fas fa-image"></i></div>
                                    <?php endif; ?>
                                    <div class="card-badge">
                                        <span class="pill-badge <?php echo $item['item_type']; ?>"><?php echo strtoupper($item['item_type']); ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                    <p class="text-truncate mb-2 small text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="cat-chip"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?></span>
                                        <span class="status-chip <?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span>
                                    </div>
                                    <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo date('M d, Y', strtotime($item['created_at'])); ?></small>
                                </div>
                                <div class="card-footer">
                                    <a href="item-details.php?id=<?php echo $item['item_id']; ?>" class="btn btn-details btn-sm w-100">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fa-4x"></i>
                    <h4 class="fw-bold mt-3">No items in this view</h4>
                    <p class="text-muted">Report a lost or found item to get started.</p>
                    <a href="report.php" class="btn btn-primary rounded-pill px-4 mt-2"><i class="fas fa-plus-circle me-2"></i>Report an Item</a>
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