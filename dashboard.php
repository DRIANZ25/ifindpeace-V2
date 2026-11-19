<?php
require_once 'config/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Statistics
$stats_query = "SELECT
    (SELECT COUNT(*) FROM items WHERE user_id = ? AND item_type = 'lost' AND status = 'pending') as my_lost,
    (SELECT COUNT(*) FROM items WHERE user_id = ? AND item_type = 'found' AND status = 'pending') as my_found,
    (SELECT COUNT(*) FROM claims WHERE user_id = ? AND claim_status = 'pending') as pending_claims,
    (SELECT COUNT(*) FROM claims WHERE user_id = ? AND claim_status = 'approved') as approved_claims,
    (SELECT COUNT(*) FROM items WHERE user_id = ? AND status = 'returned') as returned_items,
    (SELECT COUNT(*) FROM items WHERE user_id = ?) as total_my_items,
    (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0) as unread_notifs";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Recent items on platform
$recent_items_query = "SELECT i.*, c.category_name
                      FROM items i
                      JOIN categories c ON i.category_id = c.category_id
                      WHERE i.status = 'pending'
                      ORDER BY i.created_at DESC
                      LIMIT 6";
$recent_items = $conn->query($recent_items_query);

// My recent items
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

// Category breakdown for chart (all platform items)
$cat_chart = $conn->query("SELECT c.category_name, COUNT(i.item_id) as cnt 
                           FROM categories c 
                           LEFT JOIN items i ON i.category_id = c.category_id 
                           GROUP BY c.category_id, c.category_name 
                           ORDER BY cnt DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Lost vs found totals for chart
$lf_chart = $conn->query("SELECT 
    SUM(CASE WHEN item_type='lost' THEN 1 ELSE 0 END) as lost,
    SUM(CASE WHEN item_type='found' THEN 1 ELSE 0 END) as found
    FROM items")->fetch_assoc();

// 7-day activity
$daily = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($day));
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM items WHERE DATE(created_at) = ?");
    $stmt->bind_param("s", $day);
    $stmt->execute();
    $daily[$label] = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
}

// Recent claims activity (my activity feed)
$activity_stmt = $conn->prepare("SELECT 'claim' as type, claim_status as status, claim_date as date, c.item_id,
                                 i.item_name
                                 FROM claims c JOIN items i ON c.item_id = i.item_id
                                 WHERE c.user_id = ?
                                 ORDER BY c.claim_date DESC LIMIT 5");
$activity_stmt->bind_param("i", $user_id);
$activity_stmt->execute();
$my_claims_activity = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activity_stmt->close();

$conn->close();

// Time-based greeting
$hour = (int)date('H');
if ($hour < 12) $greeting = 'Good morning';
elseif ($hour < 18) $greeting = 'Good afternoon';
else $greeting = 'Good evening';

// Profile completion
$profile_fields = ['full_name', 'email', 'phone', 'course'];
$filled = 0;
foreach ($profile_fields as $f) if (!empty($_SESSION[$f] ?? '')) $filled++;
$profile_pct = round(($filled / count($profile_fields)) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        :root {
            --blue-950: #0a1628; --blue-900: #0f2444; --blue: #1e40af; --blue-mid: #2563eb;
            --yellow: #eab308; --yellow-hot: #facc15; --sky: #eff6ff; --paper: #f8fafc;
            --ink: #0f172a; --muted: #475569; --ease: cubic-bezier(.2, .8, .2, 1);
        }
        body { font-family: 'Instrument Sans', system-ui, sans-serif; color: var(--ink); background: var(--paper); }
        h1, h2, h3, h4, h5, h6 { font-family: 'Bricolage Grotesque', sans-serif; }

        /* Topbar */
        .topbar {
            background: linear-gradient(100deg, var(--blue-950), var(--blue-900));
            border-bottom: 2px solid var(--yellow);
            color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 18px rgba(10,22,40,.3); position: sticky; top: 0; z-index: 100;
        }
        .topbar-title { font-weight: 800; letter-spacing: -.3px; font-size: 1.15rem; color: #fff; }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .sidebar-toggle { background: rgba(255,255,255,.1); border: 0; color: #fff; width: 40px; height: 40px; border-radius: 12px; transition: background .2s; }
        .sidebar-toggle:hover { background: rgba(250,204,21,.35); }
        .topbar-bell { position: relative; color: #fff; font-size: 1.15rem; margin-right: 1.2rem; transition: color .2s; }
        .topbar-bell:hover { color: var(--yellow-hot); }
        .topbar-badge {
            position: absolute; top: -6px; right: -8px; background: var(--yellow-hot); color: #713f12;
            font-size: .65rem; font-weight: 800; padding: 2px 6px; border-radius: 50px;
            animation: bellPulse 2s ease-in-out infinite;
        }
        @keyframes bellPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
        .topbar-user { display: flex; align-items: center; gap: .5rem; font-weight: 600; font-size: .9rem; }

        /* Welcome banner */
        .welcome-banner {
            position: relative; overflow: hidden; padding: 2rem 2.2rem; border-radius: 22px; color: #fff;
            background: radial-gradient(500px 300px at 90% 10%, rgba(250,204,21,.35), transparent 60%),
                        linear-gradient(140deg, var(--blue-950), var(--blue-900));
            box-shadow: 0 18px 44px rgba(10,22,40,.28);
        }
        .welcome-banner::before {
            content: ''; position: absolute; inset: 0; opacity: .07;
            background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px);
            background-size: 36px 36px;
        }
        .welcome-banner h2 { font-size: clamp(1.4rem, 3vw, 2rem); font-weight: 800; letter-spacing: -.5px; position: relative; z-index: 2; }
        .welcome-banner p { color: rgba(255,255,255,.8); margin: 0; position: relative; z-index: 2; }
        .welcome-icon {
            position: absolute; right: -20px; bottom: -20px; font-size: 10rem; color: rgba(250,204,21,.18);
            transform: rotate(-15deg); pointer-events: none;
        }

        /* Stat cards */
        .stat-card {
            position: relative; background: #fff; border-radius: 18px; padding: 1.4rem 1.2rem;
            box-shadow: 0 8px 24px rgba(10,22,40,.08); text-align: center; height: 100%;
            border-left: 5px solid var(--blue);
            transition: transform .3s var(--ease), box-shadow .3s;
            animation: floatCard 5s ease-in-out infinite;
        }
        .stat-card:nth-child(1) { animation-delay: 0s; }
        .stat-card:nth-child(2) { animation-delay: .35s; }
        .stat-card:nth-child(3) { animation-delay: .7s; }
        .stat-card:nth-child(4) { animation-delay: 1.05s; }
        @keyframes floatCard { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        .stat-card:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 18px 40px rgba(10,22,40,.16); }
        .stat-card.lost { border-left-color: var(--blue); }
        .stat-card.found { border-left-color: var(--yellow); }
        .stat-card.claims { border-left-color: #7c3aed; }
        .stat-card.returned { border-left-color: #1f9d6b; }
        .stat-icon {
            width: 48px; height: 48px; display: grid; place-items: center; margin: 0 auto .6rem;
            border-radius: 14px; background: var(--sky); color: var(--blue); font-size: 1.15rem;
        }
        .stat-card.found .stat-icon { background: #fef9c3; color: #713f12; }
        .stat-card.claims .stat-icon { background: #ede9fe; color: #7c3aed; }
        .stat-card.returned .stat-icon { background: #dcfce7; color: #1f9d6b; }
        .stat-number { font-size: 2.3rem; font-weight: 800; color: var(--blue); line-height: 1; font-family: 'Bricolage Grotesque'; }
        .stat-card.found .stat-number { color: #b45309; }
        .stat-card.claims .stat-number { color: #7c3aed; }
        .stat-card.returned .stat-number { color: #1f9d6b; }
        .stat-label { color: var(--muted); font-size: .82rem; font-weight: 600; margin-top: .35rem; }
        .stat-card .btn { border-radius: 50px; font-weight: 600; font-size: .75rem; padding: .3rem .9rem; }

        /* Card base */
        .panel { background: #fff; border-radius: 18px; box-shadow: 0 8px 24px rgba(10,22,40,.08); overflow: hidden; height: 100%; }
        .panel-head { padding: 1.1rem 1.4rem; border-bottom: 2px dashed #dbeafe; display: flex; justify-content: space-between; align-items: center; }
        .panel-head h5 { font-size: 1rem; font-weight: 800; margin: 0; color: var(--blue-900); }
        .panel-body { padding: 1.3rem 1.4rem; }

        /* Quick actions */
        .quick-btn {
            display: flex; align-items: center; justify-content: center; gap: .55rem;
            border: 0; border-radius: 14px; padding: .95rem 1rem; color: #fff; font-weight: 700; font-size: .88rem;
            transition: transform .25s var(--ease), box-shadow .25s;
            text-decoration: none; position: relative; overflow: hidden;
        }
        .quick-btn::after {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);
            transition: left .6s ease;
        }
        .quick-btn:hover::after { left: 100%; }
        .quick-btn:hover { color: #fff; transform: translateY(-3px); }
        .quick-btn.q1 { background: linear-gradient(135deg, var(--blue), #1e3a8a); }
        .quick-btn.q1:hover { box-shadow: 0 12px 28px rgba(30,64,175,.4); }
        .quick-btn.q2 { background: linear-gradient(135deg, var(--yellow), #b45309); color: #fff; }
        .quick-btn.q2:hover { box-shadow: 0 12px 28px rgba(234,179,8,.5); }
        .quick-btn.q3 { background: linear-gradient(135deg, #7c3aed, #4c1d95); }
        .quick-btn.q3:hover { box-shadow: 0 12px 28px rgba(124,58,237,.4); }
        .quick-btn.q4 { background: linear-gradient(135deg, #1f9d6b, #065f46); }
        .quick-btn.q4:hover { box-shadow: 0 12px 28px rgba(31,157,107,.4); }

        /* Table styling */
        .table-hover tbody tr { transition: background .2s, transform .2s; }
        .table-hover tbody tr:hover { background: var(--sky); transform: scale(1.005); }
        .badge-lost { background: var(--blue); }
        .badge-found { background: var(--yellow); color: #713f12; }
        .badge-status {
            background: #dbeafe; color: #1e40af; font-weight: 700; font-size: .7rem;
            padding: .3rem .7rem; border-radius: 50px;
        }
        .badge-status.returned { background: #dcfce7; color: #166534; }
        .badge-status.approved { background: #dbeafe; color: #1e40af; }
        .badge-status.pending { background: #fef9c3; color: #713f12; }
        .badge-status.rejected { background: #fee2e2; color: #991b1b; }
        .badge-status.completed { background: #dcfce7; color: #166534; }

        /* Chart containers */
        .chart-box { position: relative; height: 240px; }
        .chart-small { position: relative; height: 200px; }

        /* Activity timeline */
        .timeline { position: relative; padding-left: 1.5rem; }
        .timeline::before { content: ''; position: absolute; left: .4rem; top: 0; bottom: 0; width: 2px; background: #dbeafe; }
        .timeline-item { position: relative; padding-bottom: 1.2rem; }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-item::before {
            content: ''; position: absolute; left: -1.2rem; top: .3rem; width: 10px; height: 10px;
            border-radius: 50%; background: var(--blue); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--sky);
        }
        .timeline-item.returned::before { background: #1f9d6b; }
        .timeline-item.approved::before { background: var(--yellow); }

        /* Progress bar */
        .progress-line { height: 8px; border-radius: 8px; background: #dbeafe; overflow: hidden; }
        .progress-line i { display: block; height: 100%; border-radius: 8px; background: linear-gradient(90deg, var(--blue), var(--yellow-hot)); transition: width 1.2s var(--ease); }

        /* Entrance animations */
        .fade-rise { opacity: 0; transform: translateY(18px); animation: fadeRise .7s var(--ease) forwards; }
        .d1 { animation-delay: .05s; } .d2 { animation-delay: .15s; } .d3 { animation-delay: .25s; }
        .d4 { animation-delay: .35s; } .d5 { animation-delay: .45s; } .d6 { animation-delay: .55s; }
        @keyframes fadeRise { to { opacity: 1; transform: none; } }

        .empty-state { text-align: center; padding: 2rem 1rem; color: var(--muted); }
        .empty-state i { color: #93c5fd; margin-bottom: .8rem; display: block; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .fade-rise { opacity: 1; transform: none; }
        }
    </style>
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <span class="topbar-title">Dashboard</span>
            </div>
            <div class="topbar-right d-flex align-items-center">
                <a href="notifications.php" class="topbar-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($stats['unread_notifs'] > 0): ?>
                        <span class="topbar-badge"><?php echo $stats['unread_notifs']; ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content">
            <!-- Welcome banner -->
            <div class="welcome-banner mb-4 fade-rise d1">
                <i class="fas fa-search-location welcome-icon"></i>
                <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                <p>Here's what's happening with your account today — <?php echo date('l, F d, Y'); ?></p>
            </div>

            <!-- Stats row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card lost fade-rise d2">
                        <div class="stat-icon"><i class="fas fa-search"></i></div>
                        <div class="stat-number" data-count="<?php echo (int)$stats['my_lost']; ?>">0</div>
                        <div class="stat-label">My Lost Items</div>
                        <a href="my-items.php?type=lost" class="btn btn-sm btn-outline-primary mt-2">View</a>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card found fade-rise d3">
                        <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                        <div class="stat-number" data-count="<?php echo (int)$stats['my_found']; ?>">0</div>
                        <div class="stat-label">My Found Items</div>
                        <a href="my-items.php?type=found" class="btn btn-sm btn-outline-warning mt-2">View</a>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card claims fade-rise d4">
                        <div class="stat-icon"><i class="fas fa-hand-paper"></i></div>
                        <div class="stat-number" data-count="<?php echo (int)$stats['pending_claims']; ?>">0</div>
                        <div class="stat-label">Pending Claims</div>
                        <a href="my-claims.php" class="btn btn-sm btn-outline-secondary mt-2">View</a>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card returned fade-rise d5">
                        <div class="stat-icon"><i class="fas fa-undo"></i></div>
                        <div class="stat-number" data-count="<?php echo (int)$stats['returned_items']; ?>">0</div>
                        <div class="stat-label">Returned Items</div>
                        <a href="my-items.php?status=returned" class="btn btn-sm btn-outline-success mt-2">View</a>
                    </div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="panel mb-4 fade-rise d2">
                <div class="panel-head">
                    <h5><i class="fas fa-bolt me-2" style="color: var(--yellow);"></i>Quick Actions</h5>
                </div>
                <div class="panel-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <a href="report.php" class="quick-btn q1 w-100"><i class="fas fa-plus-circle"></i>Report Lost</a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="report.php?type=found" class="quick-btn q2 w-100"><i class="fas fa-hand-holding-heart"></i>Report Found</a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="items.php" class="quick-btn q3 w-100"><i class="fas fa-search"></i>Browse Items</a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="my-items.php" class="quick-btn q4 w-100"><i class="fas fa-box"></i>My Items</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="panel fade-rise d3">
                        <div class="panel-head">
                            <h5><i class="fas fa-chart-line me-2" style="color: var(--blue);"></i>7-Day Activity</h5>
                            <small class="text-muted">New items per day</small>
                        </div>
                        <div class="panel-body">
                            <div class="chart-box"><canvas id="dailyChart"></canvas></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="panel fade-rise d4">
                        <div class="panel-head">
                            <h5><i class="fas fa-chart-pie me-2" style="color: var(--yellow);"></i>Lost vs Found</h5>
                        </div>
                        <div class="panel-body">
                            <div class="chart-box"><canvas id="lfChart"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category breakdown + Profile completeness -->
            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="panel fade-rise d4">
                        <div class="panel-head">
                            <h5><i class="fas fa-tags me-2" style="color: var(--blue);"></i>Top Categories</h5>
                        </div>
                        <div class="panel-body">
                            <?php if (!empty($cat_chart)): 
                                $maxCat = max(array_column($cat_chart, 'cnt')) ?: 1;
                                foreach ($cat_chart as $c): 
                                    $pct = round(($c['cnt'] / $maxCat) * 100);
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-semibold small"><?php echo htmlspecialchars($c['category_name']); ?></span>
                                        <span class="badge bg-light text-dark"><?php echo (int)$c['cnt']; ?></span>
                                    </div>
                                    <div class="progress-line"><i style="width: <?php echo $pct; ?>%;"></i></div>
                                </div>
                            <?php endforeach; else: ?>
                                <div class="empty-state"><i class="fas fa-tags fa-2x"></i><p>No categories yet</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="panel fade-rise d5">
                        <div class="panel-head">
                            <h5><i class="fas fa-user-circle me-2" style="color: var(--yellow);"></i>Profile Completeness</h5>
                        </div>
                        <div class="panel-body">
                            <div class="text-center mb-3">
                                <div style="font-size: 3rem; font-weight: 800; color: var(--blue); font-family: 'Bricolage Grotesque';"><?php echo $profile_pct; ?>%</div>
                                <small class="text-muted">Your profile is <?php echo $profile_pct >= 75 ? 'looking great' : 'almost there'; ?></small>
                            </div>
                            <div class="progress-line"><i style="width: <?php echo $profile_pct; ?>%;"></i></div>
                            <div class="mt-3 text-center">
                                <a href="profile.php" class="btn btn-sm btn-primary rounded-pill"><i class="fas fa-pen me-1"></i>Update Profile</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent items & activity -->
            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="panel fade-rise d4">
                        <div class="panel-head">
                            <h5><i class="fas fa-box me-2" style="color: var(--blue);"></i>My Recent Items</h5>
                            <a href="my-items.php" class="btn btn-sm btn-light">View All</a>
                        </div>
                        <div class="panel-body p-0">
                            <?php if ($my_items->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead><tr><th>Item</th><th>Type</th><th>Status</th></tr></thead>
                                        <tbody>
                                            <?php while ($item = $my_items->fetch_assoc()): ?>
                                                <tr>
                                                    <td><a href="item-details.php?id=<?php echo $item['item_id']; ?>" class="fw-semibold text-decoration-none"><?php echo htmlspecialchars($item['item_name']); ?></a></td>
                                                    <td><span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>"><?php echo strtoupper($item['item_type']); ?></span></td>
                                                    <td><span class="badge-status <?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-box-open fa-3x"></i><p class="mb-2">No items reported yet</p><a href="report.php" class="btn btn-primary btn-sm">Report an Item</a></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="panel fade-rise d5">
                        <div class="panel-head">
                            <h5><i class="fas fa-stream me-2" style="color: var(--yellow);"></i>My Activity</h5>
                        </div>
                        <div class="panel-body">
                            <?php if (!empty($my_claims_activity)): ?>
                                <div class="timeline">
                                    <?php foreach ($my_claims_activity as $act): ?>
                                        <div class="timeline-item <?php echo $act['status']; ?>">
                                            <div class="fw-semibold small"><?php echo htmlspecialchars($act['item_name']); ?></div>
                                            <div class="text-muted small">Claim <?php echo $act['status']; ?> · <?php echo date('M d, g:i A', strtotime($act['date'])); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state"><i class="fas fa-stream fa-2x"></i><p class="mb-0">No recent activity</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent platform items -->
            <div class="panel fade-rise d6">
                <div class="panel-head">
                    <h5><i class="fas fa-clock me-2" style="color: var(--blue);"></i>Recent Platform Items</h5>
                    <a href="items.php" class="btn btn-sm btn-light">View All</a>
                </div>
                <div class="panel-body p-0">
                    <?php if ($recent_items->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Item</th><th>Type</th><th>Category</th><th>Posted</th></tr></thead>
                                <tbody>
                                    <?php while ($item = $recent_items->fetch_assoc()): ?>
                                        <tr>
                                            <td><a href="item-details.php?id=<?php echo $item['item_id']; ?>" class="fw-semibold text-decoration-none"><?php echo htmlspecialchars($item['item_name']); ?></a></td>
                                            <td><span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>"><?php echo strtoupper($item['item_type']); ?></span></td>
                                            <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                            <td class="text-muted small"><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-box-open fa-3x"></i><p>No items on the platform yet</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- end page-content -->
    </div><!-- end main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        // ---- Count-up animation for stat numbers ----
        (function () {
            var els = document.querySelectorAll('[data-count]');
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            function run(el) {
                var target = parseInt(el.dataset.count, 10) || 0;
                if (reduce || target === 0) { el.textContent = target; return; }
                var start = null, dur = 1200;
                function step(t) {
                    if (start === null) start = t;
                    var p = Math.min((t - start) / dur, 1);
                    el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }
            if (!('IntersectionObserver' in window)) { els.forEach(run); return; }
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) { if (e.isIntersecting) { run(e.target); io.unobserve(e.target); } });
            }, { threshold: 0.5 });
            els.forEach(function (el) { io.observe(el); });
        })();

        // ---- Chart.js analytics ----
        (function () {
            if (typeof Chart === 'undefined') return;

            Chart.defaults.font.family = "'Instrument Sans', sans-serif";
            Chart.defaults.color = '#475569';

            // 7-day activity bar chart
            var dailyLabels = <?php echo json_encode(array_keys($daily)); ?>;
            var dailyData = <?php echo json_encode(array_values($daily)); ?>;
            var dailyCtx = document.getElementById('dailyChart');
            if (dailyCtx) {
                new Chart(dailyCtx, {
                    type: 'bar',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'New items',
                            data: dailyData,
                            backgroundColor: 'rgba(30, 64, 175, 0.85)',
                            borderRadius: 8,
                            borderSkipped: false,
                            hoverBackgroundColor: 'rgba(250, 204, 21, 0.9)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0f2444',
                                titleColor: '#facc15',
                                bodyColor: '#fff',
                                padding: 10,
                                cornerRadius: 8
                            }
                        },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#e2e8f0' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Lost vs Found doughnut
            var lfCtx = document.getElementById('lfChart');
            if (lfCtx) {
                new Chart(lfCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Lost', 'Found'],
                        datasets: [{
                            data: [<?php echo (int)$lf_chart['lost']; ?>, <?php echo (int)$lf_chart['found']; ?>],
                            backgroundColor: ['#1e40af', '#facc15'],
                            borderWidth: 0,
                            hoverOffset: 12
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { weight: '600' } } },
                            tooltip: {
                                backgroundColor: '#0f2444',
                                titleColor: '#facc15',
                                bodyColor: '#fff',
                                padding: 10,
                                cornerRadius: 8
                            }
                        }
                    }
                });
            }
        })();
    </script>
</body>
</html>