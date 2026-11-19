<?php
require_once 'config/config.php';

// Get recent items for the main grid
$conn = getDBConnection();
$recent_items_query = "SELECT i.*, c.category_name, u.full_name, u.student_id 
                      FROM items i 
                      JOIN categories c ON i.category_id = c.category_id 
                      JOIN users u ON i.user_id = u.user_id 
                      ORDER BY i.created_at DESC 
                      LIMIT 8";
$recent_items = $conn->query($recent_items_query);

// Get items for the History Slideshow (Limit 5)
$history_query = "SELECT i.*, c.category_name 
                  FROM items i 
                  JOIN categories c ON i.category_id = c.category_id 
                  ORDER BY i.created_at DESC 
                  LIMIT 5";
$history_items = $conn->query($history_query);

// Latest item, shown on the hero claim tag
$featured = null;
if ($history_items && $history_items->num_rows > 0) {
    $featured = $history_items->fetch_assoc();
    $history_items->data_seek(0);
}

// Get statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM items WHERE item_type = 'lost' AND status = 'pending') as lost_count,
                (SELECT COUNT(*) FROM items WHERE item_type = 'found' AND status = 'pending') as found_count,
                (SELECT COUNT(*) FROM items WHERE status = 'claimed' OR status = 'returned') as claimed_count,
                (SELECT COUNT(*) FROM users) as user_count";
$stats = $conn->query($stats_query)->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iFindPeace - Peace Comes When Things Are Found</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
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
        }

        body { font-family: 'Instrument Sans', system-ui, sans-serif; color: var(--ink); background: var(--paper); }
        h1, h2, h3, h4, h5, h6, .navbar-brand, .stat-number { font-family: 'Bricolage Grotesque', 'Instrument Sans', sans-serif; }
        :focus-visible { outline: 3px solid var(--yellow-hot); outline-offset: 2px; }

        /* ---------- Navbar ---------- */
        body .navbar {
            background: linear-gradient(100deg, var(--blue-950) 0%, var(--blue-900) 55%, #1e3a8a 100%);
            border-bottom: 2px solid var(--yellow);
            box-shadow: 0 6px 24px rgba(10, 22, 40, .35);
        }
        body .navbar .navbar-brand { font-weight: 800; font-size: 1.5rem; letter-spacing: -.5px; }
        body .navbar .navbar-brand span { color: var(--yellow-hot); }
        body .navbar .nav-link { position: relative; font-weight: 600; opacity: .85; transition: opacity .2s; }
        body .navbar .nav-link::after {
            content: ''; position: absolute; left: 50%; bottom: 2px; width: 0; height: 2px;
            background: var(--yellow-hot); transition: width .3s var(--ease), left .3s var(--ease);
        }
        body .navbar .nav-link:hover, body .navbar .nav-link.active { opacity: 1; }
        body .navbar .nav-link:hover::after, body .navbar .nav-link.active::after { width: 60%; left: 20%; }
        body .navbar .msu-logo-icon { height: 40px; width: 40px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.85); }
        .btn-signup {
            background: linear-gradient(135deg, var(--yellow) 0%, var(--yellow-hot) 100%);
            color: #713f12; border: none; padding: .5rem 1.25rem; font-weight: 700; border-radius: 50px;
            transition: transform .2s var(--ease), box-shadow .2s;
        }
        .btn-signup:hover { color: #713f12; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(234, 179, 8, .45); }

        /* ---------- Hero ---------- */
        .hero-lf {
            position: relative; overflow: hidden; color: #fff;
            padding: 5rem 0 6.5rem;
            background:
                radial-gradient(900px 500px at 85% 10%, rgba(250, 204, 21, .35), transparent 60%),
                radial-gradient(700px 500px at 0% 100%, rgba(30, 64, 175, .7), transparent 60%),
                linear-gradient(160deg, var(--blue-950), var(--blue-900));
        }
        /* faint "route map" grid, like a campus map */
        .hero-lf::before {
            content: ''; position: absolute; inset: 0; opacity: .08; pointer-events: none;
            background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: linear-gradient(to bottom, #000 30%, transparent 95%);
            -webkit-mask-image: linear-gradient(to bottom, #000 30%, transparent 95%);
        }
        .hero-lf::after { /* torn paper edge */
            content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 28px;
            background: var(--paper);
            clip-path: polygon(0 100%, 0 55%, 3% 75%, 6% 45%, 9% 70%, 12% 40%, 15% 68%, 18% 50%, 21% 72%, 24% 42%, 27% 66%, 30% 48%, 33% 74%, 36% 44%, 39% 70%, 42% 50%, 45% 76%, 48% 46%, 51% 68%, 54% 42%, 57% 72%, 60% 48%, 63% 70%, 66% 44%, 69% 74%, 72% 50%, 75% 68%, 78% 42%, 81% 72%, 84% 46%, 87% 70%, 90% 52%, 93% 74%, 96% 48%, 100% 66%, 100% 100%);
        }
        .hero-lf .hero-title {
            font-size: clamp(2.4rem, 5.2vw, 4.2rem); font-weight: 800; line-height: 1.02; letter-spacing: -1.5px;
            text-shadow: 0 4px 24px rgba(0,0,0,.35);
        }
        .hero-lf .hero-sub { font-size: 1.1rem; max-width: 34rem; color: rgba(255,255,255,.82); line-height: 1.6; }

        .dropoff {
            display: flex; gap: .9rem; align-items: flex-start; max-width: 34rem;
            background: rgba(255, 255, 255, .08); backdrop-filter: blur(6px);
            border: 1.5px dashed rgba(250, 204, 21, .55); border-radius: 14px; padding: .9rem 1.1rem;
        }
        .dropoff i { color: var(--yellow-hot); font-size: 1.25rem; margin-top: .15rem; }
        .dropoff p { margin: 0; font-size: .9rem; color: rgba(255,255,255,.9); }

        .search-pill {
            display: flex; max-width: 34rem; background: #fff; border-radius: 60px; padding: .35rem;
            box-shadow: 0 14px 40px rgba(0,0,0,.35); transition: box-shadow .3s;
        }
        .search-pill:focus-within { box-shadow: 0 14px 40px rgba(250, 204, 21, .5); }
        .search-pill input { flex: 1; border: 0; outline: 0; background: transparent; padding: .7rem 1.25rem; font-size: 1rem; min-width: 0; color: var(--ink); }
        .search-pill button {
            border: 0; border-radius: 50px; padding: .7rem 1.6rem; font-weight: 700; color: #fff;
            background: linear-gradient(135deg, var(--blue), var(--yellow));
            transition: transform .2s var(--ease), box-shadow .2s;
        }
        .search-pill button:hover { transform: scale(1.04); box-shadow: 0 6px 18px rgba(30, 64, 175, .4); }

        .btn-report {
            background: #fff; color: var(--blue); font-weight: 700; border: 0; border-radius: 50px;
            padding: .8rem 1.7rem; transition: transform .2s var(--ease), box-shadow .2s;
        }
        .btn-report:hover { color: var(--blue-900); transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,.3); }
        .btn-found {
            color: #fff; font-weight: 700; border: 2px solid var(--yellow-hot); border-radius: 50px;
            padding: .75rem 1.7rem; transition: background .2s, transform .2s var(--ease);
        }
        .btn-found:hover { color: #713f12; background: var(--yellow-hot); transform: translateY(-3px); }

        /* ---------- Claim tag (hero signature element) ---------- */
        .tag-wrap { display: flex; justify-content: center; padding-top: 1rem; }
        .claim-tag {
            position: relative; width: 270px; transform-origin: 50% -90px; will-change: transform;
            cursor: grab; touch-action: none; user-select: none; -webkit-user-select: none;
            filter: drop-shadow(var(--sx, 0px) 26px 24px rgba(0, 0, 0, .45));
        }
        .claim-tag.grabbed { cursor: grabbing; }
        .claim-tag::before { /* string */
            content: ''; position: absolute; left: 50%; top: -90px; width: 3px; height: 100px;
            background: linear-gradient(#fde68a, #fff); transform: translateX(-50%); border-radius: 2px;
        }
        .tag-body {
            position: relative; background: var(--paper); color: var(--ink); border-radius: 18px;
            padding: 3.2rem 1.5rem 1.5rem; box-shadow: 0 30px 60px rgba(0,0,0,.45);
            clip-path: polygon(14% 0, 86% 0, 100% 9%, 100% 100%, 0 100%, 0 9%);
        }
        .tag-body::before { /* punched hole */
            content: ''; position: absolute; top: 14px; left: 50%; width: 22px; height: 22px; transform: translateX(-50%);
            border-radius: 50%; background: var(--blue-900); box-shadow: inset 0 2px 4px rgba(0,0,0,.6);
        }
        .tag-label { font-size: .72rem; font-weight: 700; letter-spacing: 2px; color: var(--muted); }
        .tag-item { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 1.5rem; line-height: 1.1; margin: .3rem 0 1rem; overflow-wrap: anywhere; }
        .tag-perf { border-top: 2px dashed #bfdbfe; margin: 1rem -1.5rem; position: relative; }
        .tag-perf::before, .tag-perf::after { content: ''; position: absolute; top: -9px; width: 16px; height: 16px; border-radius: 50%; background: var(--blue-900); }
        .tag-perf::before { left: -8px; } .tag-perf::after { right: -8px; }
        .tag-row { display: flex; justify-content: space-between; align-items: center; font-size: .82rem; color: var(--muted); }
        .tag-chip { font-weight: 800; font-size: .72rem; letter-spacing: 1.5px; padding: .3rem .8rem; border-radius: 50px; color: #fff; }
        .tag-chip.lost { background: var(--blue); } 
        .tag-chip.found { background: var(--yellow); color: #713f12; }
        .barcode { height: 34px; margin-top: 1rem; opacity: .75;
            background: repeating-linear-gradient(90deg, var(--ink) 0 2px, transparent 2px 5px, var(--ink) 5px 6px, transparent 6px 9px, var(--ink) 9px 12px, transparent 12px 14px); }

        /* interactive tag: pivot nail, hint, shine, live details */
        .tag-wrap { position: relative; padding-top: 110px; }
        .tag-anchor {
            position: absolute; top: 12px; left: 50%; width: 16px; height: 16px; margin-left: -8px; border-radius: 50%;
            background: radial-gradient(circle at 35% 30%, #fff, #fde68a 45%, #b45309); box-shadow: 0 3px 8px rgba(0, 0, 0, .5);
        }
        .tag-hint {
            position: absolute; bottom: -2.6rem; left: 50%; display: flex; gap: .5rem; align-items: center; white-space: nowrap;
            font-size: .85rem; color: rgba(255, 255, 255, .85); pointer-events: none; transition: opacity .5s;
            animation: nudge 2s ease-in-out infinite;
        }
        .tag-hint.gone { opacity: 0; }
        @keyframes nudge { 0%, 100% { transform: translate(-50%, 0); } 50% { transform: translate(-50%, -5px); } }

        .tag-body::after { /* light sweep that follows the swing angle */
            content: ''; position: absolute; inset: 0; pointer-events: none; mix-blend-mode: soft-light;
            background: linear-gradient(105deg, transparent 35%, rgba(255, 255, 255, .85) 50%, transparent 65%);
            background-size: 250% 100%; background-position: var(--shine, 50%) 0;
        }
        .tag-label::before {
            content: ''; display: inline-block; width: 7px; height: 7px; margin-right: .5rem; border-radius: 50%;
            background: var(--yellow); animation: pulse 1.8s infinite;
        }
        .tag-chip { animation: chipGlow 2.4s ease-in-out infinite; }
        @keyframes chipGlow { 50% { box-shadow: 0 0 14px rgba(250, 204, 21, .75); } }
        .barcode { position: relative; overflow: hidden; }
        .barcode::after { /* scanner line */
            content: ''; position: absolute; top: 0; bottom: 0; width: 3px; background: var(--yellow-hot);
            box-shadow: 0 0 10px var(--yellow-hot); animation: scan 2.6s ease-in-out infinite;
        }
        @keyframes scan { 0%, 100% { left: 0; } 50% { left: calc(100% - 3px); } }
        #sparkles { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; }

        /* hero text entrance (one orchestrated moment) */
        .hero-rise { opacity: 0; transform: translateY(18px); animation: rise .8s var(--ease) forwards; }
        .hero-rise.d1 { animation-delay: .05s; } .hero-rise.d2 { animation-delay: .18s; }
        .hero-rise.d3 { animation-delay: .3s; } .hero-rise.d4 { animation-delay: .42s; } .hero-rise.d5 { animation-delay: .54s; }
        @keyframes rise { to { opacity: 1; transform: none; } }

        /* ---------- Stats: ticket stubs ---------- */
        .stats-row { margin-top: -3.2rem; position: relative; z-index: 5; }
        .stub {
            position: relative; background: #fff; border-radius: 16px; padding: 1.3rem 1.2rem 1.1rem; text-align: center; height: 100%;
            box-shadow: 0 12px 32px rgba(10, 22, 40, .12); border-top: 5px solid var(--c);
            transition: transform .3s var(--ease), box-shadow .3s;
            animation: float 4s ease-in-out infinite;
        }
        .stats-row .row > div:nth-child(1) .stub { animation-delay: 0s; }
        .stats-row .row > div:nth-child(2) .stub { animation-delay: .4s; }
        .stats-row .row > div:nth-child(3) .stub { animation-delay: .8s; }
        .stats-row .row > div:nth-child(4) .stub { animation-delay: 1.2s; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-6px); } }
        .stub:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 18px 40px rgba(10, 22, 40, .2); }
        .stub::before, .stub::after { content: ''; position: absolute; top: 58%; width: 16px; height: 16px; border-radius: 50%; background: var(--paper); }
        .stub::before { left: -8px; } .stub::after { right: -8px; }
        .stub .stat-number { font-size: 2.6rem; font-weight: 800; color: var(--c); line-height: 1; }
        .stub .stat-label { margin-top: .5rem; padding-top: .6rem; border-top: 2px dashed #dbeafe; font-size: .85rem; font-weight: 600; color: var(--muted); }

        /* ---------- Section heads ---------- */
        .section-title { font-weight: 800; letter-spacing: -.8px; font-size: 1.9rem; color: var(--blue-900); }
        .btn-maroon {
            background: linear-gradient(135deg, var(--blue), #1d4ed8); color: #fff; border: 0; border-radius: 50px; font-weight: 700;
            transition: transform .2s var(--ease), box-shadow .2s;
        }
        .btn-maroon:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(30, 64, 175, .45); }
        .btn-maroon:hover { animation: glowPulse 1.5s ease-in-out infinite; }
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 8px 20px rgba(30, 64, 175, .35); }
            50% { box-shadow: 0 8px 30px rgba(30, 64, 175, .65); }
        }

        /* ---------- Item cards: luggage-tag style ---------- */
        .item-tag {
            position: relative; background: #fff; border: 0; border-radius: 16px; overflow: hidden; height: 100%;
            box-shadow: 0 6px 20px rgba(10, 22, 40, .09);
            transition: transform .3s var(--ease), box-shadow .3s;
        }
        .item-tag::before { /* type-colored spine */
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; z-index: 3; background: var(--c);
        }
        .item-tag:hover { transform: translateY(-5px); box-shadow: 0 16px 36px rgba(30, 64, 175, .2); }
        .item-tag .thumb { position: relative; height: 170px; overflow: hidden; background: var(--sky); }
        .item-tag .thumb img { width: 100%; height: 100%; object-fit: cover; transition: transform .6s var(--ease); }
        .item-tag:hover .thumb img { transform: scale(1.07); }
        .item-tag .thumb::after { /* shine sweep */
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; z-index: 1;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,.45), transparent);
            transition: left .7s ease; pointer-events: none;
        }
        .item-tag:hover .thumb::after { left: 100%; }
        .item-tag .thumb .ph { height: 100%; display: flex; align-items: center; justify-content: center; color: #93c5fd; font-size: 2.6rem; }
        .pill { font-size: .68rem; font-weight: 800; letter-spacing: 1px; padding: .35rem .8rem; border-radius: 50px; color: #fff; box-shadow: 0 3px 10px rgba(0,0,0,.2); }
        .pill.lost { background: var(--blue); } 
        .pill.found { background: var(--yellow); color: #713f12; }
        .pill.pending { background: #fef08a; color: #713f12; } 
        .pill.returned { background: #1f9d6b; }
        .item-tag .card-title { font-weight: 800; color: var(--ink); letter-spacing: -.3px; }
        .meta-chip { background: var(--sky); color: var(--blue); font-weight: 600; font-size: .75rem; border-radius: 50px; padding: .3rem .75rem; }
        .item-tag .card-footer { background: transparent; border: 0; border-top: 2px dashed #dbeafe; margin: 0 1rem; padding: .85rem 0; }
        .btn-details { border: 2px solid var(--blue); color: var(--blue); font-weight: 700; border-radius: 50px; background: transparent; transition: background .2s, color .2s; }
        .btn-details:hover { background: var(--blue); color: #fff; }

        /* staggered item entrance */
        .recent-grid > div { animation: fadeUp .6s var(--ease) both; }
        .recent-grid > div:nth-child(1) { animation-delay: .05s; }
        .recent-grid > div:nth-child(2) { animation-delay: .12s; }
        .recent-grid > div:nth-child(3) { animation-delay: .19s; }
        .recent-grid > div:nth-child(4) { animation-delay: .26s; }
        .recent-grid > div:nth-child(5) { animation-delay: .33s; }
        .recent-grid > div:nth-child(6) { animation-delay: .4s; }
        .recent-grid > div:nth-child(7) { animation-delay: .47s; }
        .recent-grid > div:nth-child(8) { animation-delay: .54s; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }

        /* ---------- History carousel ---------- */
        .history-panel { position: sticky; top: 90px; }
        .history-panel .carousel { border-radius: 18px; box-shadow: 0 16px 40px rgba(10, 22, 40, .25); border: 4px solid #fff; }
        .history-carousel-img { height: 320px; object-fit: cover; }
        .carousel-caption { background: linear-gradient(transparent, rgba(10, 22, 40, .92)); left: 0; right: 0; bottom: 0; padding: 3rem 1.2rem 2.6rem; }
        .carousel-indicators [data-bs-target] { width: 8px; height: 8px; border-radius: 50%; border: 0; }
        .carousel-indicators .active { background: var(--yellow-hot); }
        .live-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: var(--yellow-hot); margin-right: .45rem; animation: pulse 1.8s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(250, 204, 21, .6); } 70%, 100% { box-shadow: 0 0 0 10px rgba(250, 204, 21, 0); } }

        /* ---------- Footer ---------- */
        .footer-lf { background: linear-gradient(160deg, var(--blue-950), var(--blue-900)); color: rgba(255,255,255,.75); margin-top: 4rem; padding: 2.5rem 0; border-top: 3px solid var(--yellow); }
        .footer-lf img { height: 44px; width: 44px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,.8); }
        .footer-lf .brand { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; color: #fff; font-size: 1.2rem; }
        .footer-lf .brand span { color: var(--yellow-hot); }

        @media (max-width: 991px) {
            .hero-lf { padding: 3.5rem 0 5.5rem; }
            .history-panel { position: static; }
            .tag-wrap { margin-top: 3rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .hero-rise, .claim-tag { opacity: 1; transform: none; }
        }
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
                <div class="msu-nav-text ms-3 d-none d-lg-flex align-items-center">
    <img src="assets/images/msu-logo.jpg" alt="MSU Logo" class="msu-logo-icon me-2">
    <div>
        <small class="text-white" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700;">College of Education</small>
        <br>
        <small class="text-white" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600;">MSU-GenSan</small>
    </div>
</div>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="items.php">Browse Items</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a></li>
                        <?php if (!isAdmin()): ?>
                        <li class="nav-item"><a class="nav-link" href="report.php">Report Item</a></li>
                        <?php endif; ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" data-bs-toggle="dropdown" aria-label="Notifications">
                                <i class="fas fa-bell"></i>
                                <?php 
                                $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
                                if ($unread_count > 0): ?><span class="notification-badge"><?php echo $unread_count; ?></span><?php endif; ?>
                            </a>
                            <ul class="dropdown-menu notification-dropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php
                                $conn = getDBConnection();
                                $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $notifications = $stmt->get_result();
                                if ($notifications->num_rows > 0):
                                    while ($notif = $notifications->fetch_assoc()): ?>
                                        <li class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                            <small class="text-muted"><?php echo date('M d, g:i A', strtotime($notif['created_at'])); ?></small>
                                            <p class="mb-0 small"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        </li>
                                    <?php endwhile; else: ?>
                                    <li class="notification-item"><p class="mb-0 small text-muted">No notifications</p></li>
                                <?php endif; $stmt->close(); $conn->close(); ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="notifications.php">View All</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="my-items.php"><i class="fas fa-box me-2"></i>My Items</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>Log In</a></li>
                        <li class="nav-item"><a class="btn btn-signup ms-lg-2" href="register.php">Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-lf">
        <canvas id="sparkles" aria-hidden="true"></canvas>
        <div class="container position-relative" style="z-index: 2;">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    <h1 class="hero-title hero-rise d1">Lost something? Found something? Let's get it home.</h1>
                    <p class="hero-sub my-4 hero-rise d2">iFindPeace helps MSU students recover lost belongings and report found items. Join our community of honesty and accountability.</p>

                    <!-- Drop-off Point Notice -->
                    <div class="dropoff mb-4 hero-rise d3">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <h6 class="fw-bold mb-1 text-white">Designated drop-off point</h6>
                            <p>If you found an item, drop it off at the <strong>Organization Office</strong>. All claimed items must be picked up there. Do not arrange direct meetups.</p>
                        </div>
                    </div>

                    <form action="items.php" method="GET" class="search-pill hero-rise d4" role="search">
                        <input type="text" name="search" placeholder="Search for lost or found items..." required aria-label="Search items">
                        <button type="submit"><i class="fas fa-search me-2"></i>Search</button>
                    </form>

                    <?php if (!isAdmin()): ?>
                    <div class="mt-4 d-flex flex-wrap gap-2 hero-rise d5">
                        <a href="report.php" class="btn btn-report"><i class="fas fa-plus-circle me-2"></i>Report Lost Item</a>
                        <a href="report.php?type=found" class="btn btn-found"><i class="fas fa-hand-holding-heart me-2"></i>Report Found Item</a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Hanging claim tag showing the latest reported item -->
                <div class="col-lg-5">
                    <div class="tag-wrap">
                        <div class="tag-anchor" id="tagAnchor"></div>
                        <div class="tag-hint" id="tagHint"><i class="fas fa-hand-pointer"></i>Drag the tag to swing it</div>
                        <div class="claim-tag" id="claimTag" tabindex="0" role="button" aria-label="Claim tag. Drag it, or press the left and right arrow keys, to swing it.">
                            <div class="tag-body">
                                <div class="tag-label">LATEST REPORT</div>
                                <div class="tag-item"><?php echo $featured ? htmlspecialchars($featured['item_name']) : 'Nothing reported yet'; ?></div>
                                <div class="tag-row">
                                    <span><i class="fas fa-tag me-1"></i><?php echo $featured ? htmlspecialchars($featured['category_name']) : 'Be the first'; ?></span>
                                    <?php if ($featured): ?>
                                        <span class="tag-chip <?php echo $featured['item_type'] == 'lost' ? 'lost' : 'found'; ?>"><?php echo strtoupper($featured['item_type']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="tag-perf"></div>
                                <div class="tag-row">
                                    <span>Tag no. <?php echo $featured ? str_pad($featured['item_id'], 5, '0', STR_PAD_LEFT) : '00000'; ?></span>
                                    <span><?php echo $featured ? date('M d', strtotime($featured['created_at'])) : ''; ?></span>
                                </div>
                                <div class="barcode"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics -->
    <section class="stats-row">
        <div class="container">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="stub" style="--c: var(--blue);">
                        <div class="stat-number" data-count="<?php echo (int)$stats['lost_count']; ?>">0</div>
                        <div class="stat-label">Lost items</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stub" style="--c: var(--yellow);">
                        <div class="stat-number" data-count="<?php echo (int)$stats['found_count']; ?>">0</div>
                        <div class="stat-label">Found items</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stub" style="--c: #1f9d6b;">
                        <div class="stat-number" data-count="<?php echo (int)$stats['claimed_count']; ?>">0</div>
                        <div class="stat-label">Items returned</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stub" style="--c: var(--blue-900);">
                        <div class="stat-number" data-count="<?php echo (int)$stats['user_count']; ?>">0</div>
                        <div class="stat-label">Registered users</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Items & History Section -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <!-- LEFT SIDE: Recent Items Grid -->
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-end mb-4">
                        <div>
                            <h2 class="section-title mb-1">Recent items</h2>
                            <p class="text-muted small mb-0">Latest lost and found items reported</p>
                        </div>
                        <a href="items.php" class="btn btn-maroon btn-sm px-3 py-2">View all <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>

                    <?php if ($recent_items->num_rows > 0): ?>
                        <div class="row g-4 recent-grid">
                            <?php while ($item = $recent_items->fetch_assoc()): 
                                $is_returned = ($item['status'] == 'claimed' || $item['status'] == 'returned');
                                $type_color = $item['item_type'] == 'lost' ? 'var(--blue)' : 'var(--yellow)';
                            ?>
                                <div class="col-md-6">
                                    <div class="card item-tag" style="--c: <?php echo $type_color; ?>;">
                                        <div class="thumb">
                                            <?php if ($item['image_path']): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            <?php else: ?>
                                                <div class="ph"><i class="fas fa-image"></i></div>
                                            <?php endif; ?>

                                            <!-- Status Indicator -->
                                            <div style="position: absolute; top: 12px; left: 16px; z-index: 2;">
                                                <span class="pill <?php echo $is_returned ? 'returned' : 'pending'; ?>">
                                                    <?php echo $is_returned ? 'Returned' : 'Pending Admin Approval'; ?>
                                                </span>
                                            </div>

                                            <!-- Item Type Badge -->
                                            <div style="position: absolute; top: 12px; right: 12px; z-index: 2;">
                                                <span class="pill <?php echo $item['item_type'] == 'lost' ? 'lost' : 'found'; ?>">
                                                    <?php echo strtoupper($item['item_type']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body" style="padding: 1.1rem 1.2rem .8rem 1.4rem;">
                                            <h6 class="card-title mb-2"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                            <p class="card-text text-truncate mb-3 small text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="meta-chip"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                <small class="text-muted"><i class="far fa-clock me-1" style="color: var(--yellow);"></i><?php echo date('M d', strtotime($item['created_at'])); ?></small>
                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <a href="item-details.php?id=<?php echo $item['item_id']; ?>" class="btn btn-details btn-sm w-100">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-4 p-5 text-center shadow-sm" style="border: 2px dashed #bfdbfe;">
                            <i class="fas fa-box-open fa-3x mb-3" style="color: var(--yellow); opacity: .5;"></i>
                            <h5>No items reported yet</h5>
                            <a href="report.php" class="btn btn-maroon btn-sm px-4">Report an item</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RIGHT SIDE: History Slideshow -->
                <div class="col-lg-4">
                    <div class="history-panel">
                        <div class="mb-4">
                            <h2 class="section-title mb-1">History</h2>
                            <p class="text-muted small mb-0"><span class="live-dot"></span>Recently added items in real time</p>
                        </div>

                        <?php if ($history_items->num_rows > 0): ?>
                            <div id="historyCarousel" class="carousel slide" data-bs-ride="carousel" style="overflow: hidden;">
                                <div class="carousel-indicators">
                                    <?php $history_items->data_seek(0); $i = 0; while($row = $history_items->fetch_assoc()): ?>
                                        <button type="button" data-bs-target="#historyCarousel" data-bs-slide-to="<?php echo $i; ?>" class="<?php echo $i === 0 ? 'active' : ''; ?>" aria-label="Slide <?php echo $i + 1; ?>"></button>
                                    <?php $i++; endwhile; ?>
                                </div>
                                <div class="carousel-inner">
                                    <?php $history_items->data_seek(0); $isFirst = true; while ($h_item = $history_items->fetch_assoc()): 
                                        $h_status = ($h_item['status'] == 'claimed' || $h_item['status'] == 'returned') ? 'Returned' : 'Pending';
                                        $h_badge = ($h_status == 'Returned') ? 'bg-success' : 'bg-warning text-dark';
                                    ?>
                                        <div class="carousel-item <?php echo $isFirst ? 'active' : ''; ?>">
                                            <?php if ($h_item['image_path']): ?>
                                                <img src="uploads/<?php echo htmlspecialchars($h_item['image_path']); ?>" class="d-block w-100 history-carousel-img" alt="<?php echo htmlspecialchars($h_item['item_name']); ?>">
                                            <?php else: ?>
                                                <div class="d-flex align-items-center justify-content-center text-white history-carousel-img" style="background: linear-gradient(135deg, var(--blue), var(--yellow));"><i class="fas fa-image fa-3x" style="opacity: .6;"></i></div>
                                            <?php endif; ?>
                                            <div class="carousel-caption text-start">
                                                <span class="badge <?php echo $h_badge; ?> mb-1"><?php echo $h_status; ?></span>
                                                <span class="badge mb-1" style="background: <?php echo $h_item['item_type'] == 'lost' ? 'var(--blue)' : 'var(--yellow)'; ?>; <?php echo $h_item['item_type'] == 'found' ? 'color: #713f12;' : ''; ?>"><?php echo strtoupper($h_item['item_type']); ?></span>
                                                <h6 class="fw-bold mb-0 text-truncate"><?php echo htmlspecialchars($h_item['item_name']); ?></h6>
                                                <small style="font-size: 0.75rem;"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($h_item['category_name']); ?></small>
                                            </div>
                                        </div>
                                    <?php $isFirst = false; endwhile; ?>
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#historyCarousel" data-bs-slide="prev" aria-label="Previous"><span class="carousel-control-prev-icon"></span></button>
                                <button class="carousel-control-next" type="button" data-bs-target="#historyCarousel" data-bs-slide="next" aria-label="Next"><span class="carousel-control-next-icon"></span></button>
                            </div>
                        <?php else: ?>
                            <div class="bg-white rounded-4 text-center p-4" style="border: 2px dashed #bfdbfe;"><p class="text-muted mb-0">No history yet.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-lf">
        <div class="container">
            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="assets/images/msu-logo.jpg" alt="MSU Logo">
                    <div>
                        <div class="brand">iFind<span>Peace</span></div>
                        <small>College of Education - MSU-GenSan</small>
                    </div>
                </div>
                <small>&copy; <?php echo date('Y'); ?> iFindPeace. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        // Count-up for the stat stubs (runs once when they scroll into view)
        (function () {
            var els = document.querySelectorAll('[data-count]');
            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            function run(el) {
                var target = parseInt(el.dataset.count, 10) || 0;
                if (reduce || target === 0) { el.textContent = target; return; }
                var start = null, dur = 1100;
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
            }, { threshold: 0.6 });
            els.forEach(function (el) { io.observe(el); });
        })();
    </script>
    <script>
        // Swingable claim tag: a real pendulum (gravity + damping) you can grab, throw and nudge
        (function () {
            var tag = document.getElementById('claimTag'), anchor = document.getElementById('tagAnchor');
            var hint = document.getElementById('tagHint'), cvs = document.getElementById('sparkles');
            var hero = document.querySelector('.hero-lf');
            if (!tag || !anchor || !cvs) return;

            var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var ctx = cvs.getContext('2d');
            var GL = 6.1, DAMP = 0.55, MAXA = 2.5;   // gravity/length, damping per second, max angle (rad)
            var theta = reduce ? 0 : 0.95, omega = 0; // angle (right = positive) and angular velocity
            var dragging = false, moved = 0, hist = [], last = performance.now(), visible = true;
            var parts = [], W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2), lastScrollY = window.scrollY;
            var COLORS = ['#facc15', '#fde68a', '#ffffff', '#60a5fa'];

            function pivot() { var r = anchor.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; }
            function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

            // ---- pointer drag ----
            function aim(e) {
                var p = pivot(), a = Math.atan2(e.clientX - p.x, e.clientY - p.y);
                theta = clamp(a, -MAXA, MAXA);
                var now = performance.now();
                hist.push({ t: now, a: theta });
                while (hist.length && now - hist[0].t > 100) hist.shift();
            }
            tag.addEventListener('pointerdown', function (e) {
                dragging = true; moved = 0; hist = []; omega = 0;
                tag.setPointerCapture(e.pointerId); tag.classList.add('grabbed');
                hint && hint.classList.add('gone');
                tag._sx = e.clientX; aim(e);
            });
            tag.addEventListener('pointermove', function (e) {
                if (!dragging) return;
                moved = Math.max(moved, Math.abs(e.clientX - tag._sx));
                aim(e);
            });
            function release(e) {
                if (!dragging) return;
                dragging = false; tag.classList.remove('grabbed');
                var n = hist.length;
                if (moved < 6) { // a tap flicks it away from the side you hit
                    var r = tag.getBoundingClientRect();
                    omega = e.clientX > r.left + r.width / 2 ? -4 : 4;
                } else if (n > 1) {
                    var dt = (hist[n - 1].t - hist[0].t) / 1000;
                    omega = dt > 0 ? clamp((hist[n - 1].a - hist[0].a) / dt, -14, 14) : 0;
                }
                if (Math.abs(omega) > 5) burst(18);
            }
            tag.addEventListener('pointerup', release);
            tag.addEventListener('pointercancel', release);
            tag.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowLeft') omega -= 3.2;
                else if (e.key === 'ArrowRight') omega += 3.2;
                else if (e.key === 'Enter' || e.key === ' ') { omega += (Math.random() < .5 ? -1 : 1) * 4; e.preventDefault(); }
                else return;
                hint && hint.classList.add('gone');
            });

            // scrolling shakes the tag a little
            window.addEventListener('scroll', function () {
                var dy = window.scrollY - lastScrollY; lastScrollY = window.scrollY;
                if (!reduce && !dragging) omega += clamp(dy * 0.012, -1.5, 1.5);
            }, { passive: true });

            // ---- sparkles ----
            function resize() {
                W = hero.clientWidth; H = hero.clientHeight;
                cvs.width = W * dpr; cvs.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            }
            function spawn(x, y, vx, vy, life, size) {
                parts.push({ x: x, y: y, vx: vx, vy: vy, life: life, max: life, s: size, c: COLORS[(Math.random() * COLORS.length) | 0] });
            }
            function tagBottom() {
                var r = tag.getBoundingClientRect(), h = hero.getBoundingClientRect();
                return { x: r.left + r.width / 2 - h.left, y: r.bottom - h.top - 20 };
            }
            function burst(n) {
                var b = tagBottom();
                for (var i = 0; i < n; i++) {
                    var a = Math.random() * Math.PI * 2, s = 40 + Math.random() * 120;
                    spawn(b.x + (Math.random() - .5) * 120, b.y, Math.cos(a) * s, Math.sin(a) * s - 40, 0.9 + Math.random() * 0.7, 1.5 + Math.random() * 2.5);
                }
            }
            function seedDust() {
                for (var i = 0; i < 26; i++) {
                    spawn(Math.random() * W, Math.random() * H, (Math.random() - .5) * 8, -6 - Math.random() * 10, 999, 1 + Math.random() * 1.8);
                    parts[parts.length - 1].dust = true;
                }
            }

            // ---- main loop ----
            function frame(now) {
                requestAnimationFrame(frame);
                var dt = Math.min((now - last) / 1000, 1 / 30); last = now;
                if (!visible || document.hidden) return;

                if (!dragging) {
                    var wind = reduce ? 0 : 0.9 * Math.sin(now / 1100) + 0.45 * Math.sin(now / 470 + 1.3);
                    for (var k = 0; k < 2; k++) { // two sub-steps for stability
                        var h = dt / 2;
                        omega += (-GL * Math.sin(theta) - DAMP * omega + wind) * h;
                        theta += omega * h;
                        if (Math.abs(theta) > MAXA) { theta = Math.sign(theta) * MAXA; omega *= -0.4; }
                    }
                }
                tag.style.transform = 'rotate(' + (-theta) + 'rad)';
                tag.style.setProperty('--sx', (-theta * 45).toFixed(1) + 'px');
                tag.style.setProperty('--shine', clamp(50 + theta * 90, 0, 100).toFixed(1) + '%');

                if (reduce) return;
                // fast swings shed sparkles from the tag's bottom edge
                var speed = Math.abs(omega);
                if (speed > 2.2 && Math.random() < speed * 0.05) {
                    var b = tagBottom();
                    spawn(b.x + (Math.random() - .5) * 90, b.y, -omega * 25 + (Math.random() - .5) * 30, 20 + Math.random() * 30, 0.8 + Math.random() * 0.6, 1.5 + Math.random() * 2);
                }
                ctx.clearRect(0, 0, W, H);
                for (var i = parts.length - 1; i >= 0; i--) {
                    var p = parts[i];
                    p.x += p.vx * dt; p.y += p.vy * dt;
                    if (p.dust) {
                        if (p.y < -10) { p.y = H + 10; p.x = Math.random() * W; }
                        ctx.globalAlpha = 0.25;
                    } else {
                        p.life -= dt; p.vy += 90 * dt;
                        if (p.life <= 0) { parts.splice(i, 1); continue; }
                        ctx.globalAlpha = Math.max(0, p.life / p.max);
                    }
                    ctx.fillStyle = p.c;
                    ctx.beginPath(); ctx.arc(p.x, p.y, p.s, 0, 6.2832); ctx.fill();
                }
                ctx.globalAlpha = 1;
            }

            resize(); if (!reduce) seedDust();
            window.addEventListener('resize', function () { resize(); });
            if ('IntersectionObserver' in window) {
                new IntersectionObserver(function (en) { visible = en[0].isIntersecting; }).observe(hero);
            }
            requestAnimationFrame(frame);
        })();
    </script>
</body>
</html>