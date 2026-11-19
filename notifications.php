<?php
require_once 'config/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Mark a single notification as read, then redirect to item
if (isset($_GET['read_id'])) {
    $rid = intval($_GET['read_id']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $rid, $user_id);
    $stmt->execute();
    $stmt->close();

    if (isset($_GET['item_id'])) {
        $iid = intval($_GET['item_id']);
        $conn->close();
        header("Location: item-details.php?id=" . $iid);
        exit();
    }
    $conn->close();
    header("Location: notifications.php");
    exit();
}

// Mark all as read via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    header("Location: notifications.php");
    exit();
}

// Get notifications
$query = "SELECT n.*, i.item_name 
          FROM notifications n 
          LEFT JOIN items i ON n.item_id = i.item_id 
          WHERE n.user_id = ? 
          ORDER BY n.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

// Unread count
$unread = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['c'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        :root { --blue-950:#0a1628; --blue-900:#0f2444; --blue:#1e40af; --yellow:#eab308; --yellow-hot:#facc15; --sky:#eff6ff; --paper:#f8fafc; --ink:#0f172a; --muted:#475569; --ease:cubic-bezier(.2,.8,.2,1); }
        body { font-family:'Instrument Sans',system-ui,sans-serif; color:var(--ink); background:var(--paper); }
        h1,h2,h3,h4,h5,h6 { font-family:'Bricolage Grotesque',sans-serif; }
        body .navbar { background:linear-gradient(100deg,var(--blue-950),var(--blue-900),#1e3a8a); border-bottom:2px solid var(--yellow); box-shadow:0 6px 24px rgba(10,22,40,.35); }
        body .navbar .navbar-brand { font-weight:800; font-size:1.5rem; }
        body .navbar .navbar-brand span { color:var(--yellow-hot); }
        body .navbar .nav-link { font-weight:600; }

        .notif-hero {
            position:relative; overflow:hidden; padding:2.5rem 0 4rem; color:#fff;
            background:radial-gradient(600px 350px at 80% 10%,rgba(250,204,21,.3),transparent 60%),
                       linear-gradient(160deg,var(--blue-950),var(--blue-900));
        }
        .notif-hero::before { content:''; position:absolute; inset:0; opacity:.07;
            background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);
            background-size:40px 40px; }

        .notif-shell {
            margin-top:-3rem; position:relative; z-index:5;
            background:#fff; border-radius:22px; overflow:hidden;
            box-shadow:0 18px 44px rgba(10,22,40,.15);
        }
        .notif-toolbar {
            padding:1.1rem 1.5rem; display:flex; justify-content:space-between; align-items:center;
            border-bottom:2px dashed #dbeafe;
        }
        .notif-count { font-weight:700; color:var(--blue-900); }
        .notif-count .badge-new {
            display:inline-block; background:var(--yellow-hot); color:#713f12;
            font-size:.7rem; font-weight:800; padding:2px 8px; border-radius:50px; margin-left:.4rem;
        }
        .btn-mark-read {
            border:2px solid var(--blue); color:var(--blue); background:transparent; border-radius:50px;
            font-weight:700; font-size:.82rem; padding:.4rem 1rem; transition:all .2s;
        }
        .btn-mark-read:hover { background:var(--blue); color:#fff; }

        .notif-row {
            display:flex; gap:1rem; padding:1.2rem 1.5rem;
            border-bottom:1px solid #f1f5f9; text-decoration:none; color:inherit;
            transition:background .2s; position:relative;
        }
        .notif-row:hover { background:var(--sky); color:inherit; }
        .notif-row.unread { background:#fefce8; }
        .notif-row.unread::before {
            content:''; position:absolute; left:.5rem; top:50%; transform:translateY(-50%);
            width:8px; height:8px; border-radius:50%; background:var(--yellow-hot);
            box-shadow:0 0 0 3px rgba(250,204,21,.25); animation:dotPulse 2s infinite;
        }
        @keyframes dotPulse { 50% { box-shadow:0 0 0 8px rgba(250,204,21,0); } }

        .notif-icon {
            width:52px; height:52px; flex:none; border-radius:50%;
            display:grid; place-items:center; font-size:1.1rem; color:#fff;
        }
        .notif-icon.match { background:linear-gradient(135deg,var(--blue),#1e3a8a); }
        .notif-icon.approved { background:linear-gradient(135deg,#1f9d6b,#065f46); }
        .notif-icon.rejected { background:linear-gradient(135deg,#dc2626,#991b1b); }
        .notif-icon.expired { background:linear-gradient(135deg,#f59e0b,#b45309); }
        .notif-icon.default { background:linear-gradient(135deg,var(--blue),var(--yellow)); }

        .notif-body { flex:1; min-width:0; }
        .notif-msg { font-weight:600; color:var(--ink); margin-bottom:.3rem; }
        .notif-meta { display:flex; gap:.8rem; flex-wrap:wrap; font-size:.78rem; color:var(--muted); }
        .notif-meta .item-tag { background:var(--sky); color:var(--blue); padding:2px 8px; border-radius:50px; font-weight:600; }

        .notif-arrow {
            align-self:center; color:#94a3b8; font-size:.9rem;
            transition:transform .2s, color .2s;
        }
        .notif-row:hover .notif-arrow { color:var(--blue); transform:translateX(4px); }

        .notif-empty {
            text-align:center; padding:3rem 1.5rem; color:var(--muted);
        }
        .notif-empty i { color:#93c5fd; margin-bottom:1rem; }

        .fade-rise { opacity:0; transform:translateY(15px); animation:fadeRise .6s var(--ease) forwards; }
        .d1 { animation-delay:.05s; } .d2 { animation-delay:.15s; } .d3 { animation-delay:.25s; }
        @keyframes fadeRise { to { opacity:1; transform:none; } }

        @media (prefers-reduced-motion: reduce) { *,*::before,*::after{animation:none!important;transition:none!important;} .fade-rise{opacity:1;transform:none;} }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-search-location me-2"></i>iFind<span>Peace</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo isAdmin()?'admin/dashboard.php':'dashboard.php'; ?>">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="items.php">Browse Items</a></li>
                    <li class="nav-item"><a class="nav-link" href="report.php">Report Item</a></li>
                    <li class="nav-item"><a class="nav-link active" href="notifications.php">Notifications</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?></a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="my-items.php"><i class="fas fa-box me-2"></i>My Items</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="notif-hero">
        <div class="container position-relative" style="z-index:2;">
            <h1 class="fw-bold mb-1 fade-rise d1" style="font-size:clamp(1.6rem,3.5vw,2.4rem);letter-spacing:-1px;">Notifications</h1>
            <p class="mb-0 fade-rise d2" style="color:rgba(255,255,255,.8);">Tap a notification to open the matched item</p>
        </div>
    </section>

    <div class="container">
        <div class="notif-shell fade-rise d3">
            <div class="notif-toolbar">
                <div class="notif-count">
                    <?php echo $notifications->num_rows; ?> notification<?php echo $notifications->num_rows === 1 ? '' : 's'; ?>
                    <?php if ($unread > 0): ?>
                        <span class="badge-new"><?php echo $unread; ?> new</span>
                    <?php endif; ?>
                </div>
                <?php if ($unread > 0): ?>
                    <form method="POST" class="mb-0">
                        <button type="submit" name="mark_all_read" class="btn-mark-read">
                            <i class="fas fa-check-double me-1"></i>Mark all read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($notifications->num_rows > 0): ?>
                <?php while ($notif = $notifications->fetch_assoc()): 
                    $type = $notif['notification_type'] ?? 'default';
                    $icon_map = [
                        'match_found' => ['fa-search', 'match'],
                        'claim_approved' => ['fa-check-circle', 'approved'],
                        'claim_rejected' => ['fa-times-circle', 'rejected'],
                        'item_returned' => ['fa-undo', 'approved'],
                        'item_expired' => ['fa-clock', 'expired'],
                        'deadline_reminder' => ['fa-exclamation-triangle', 'expired'],
                        'admin_message' => ['fa-envelope', 'default'],
                    ];
                    list($icon, $cls) = $icon_map[$type] ?? ['fa-bell', 'default'];
                    $is_unread = !$notif['is_read'];
                    $has_item = !empty($notif['item_id']);

                    // Build link
                    $link = 'notifications.php?read_id=' . (int)$notif['notification_id'];
                    if ($has_item) $link .= '&item_id=' . (int)$notif['item_id'];
                ?>
                    <?php if ($has_item): ?>
                        <a href="<?php echo $link; ?>" class="notif-row <?php echo $is_unread ? 'unread' : ''; ?>">
                    <?php else: ?>
                        <div class="notif-row <?php echo $is_unread ? 'unread' : ''; ?>" style="cursor:default;">
                    <?php endif; ?>

                        <div class="notif-icon <?php echo $cls; ?>"><i class="fas <?php echo $icon; ?>"></i></div>
                        <div class="notif-body">
                            <div class="notif-msg"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notif-meta">
                                <?php if ($notif['item_name']): ?>
                                    <span class="item-tag"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($notif['item_name']); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></span>
                            </div>
                        </div>
                        <?php if ($has_item): ?>
                            <div class="notif-arrow"><i class="fas fa-chevron-right"></i></div>
                        <?php endif; ?>

                    <?php if ($has_item): ?>
                        </a>
                    <?php else: ?>
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="notif-empty">
                    <i class="fas fa-bell-slash fa-4x"></i>
                    <h4 class="fw-bold mt-3">No notifications yet</h4>
                    <p>When someone claims your item or a match is found, you'll see it here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>