<?php
require_once 'config/config.php';

if (!isset($_GET['id'])) {
    header("Location: items.php");
    exit();
}

$item_id = intval($_GET['id']);
$conn = getDBConnection();

$conn->query("UPDATE items SET view_count = view_count + 1 WHERE item_id = $item_id");

$query = "SELECT i.*, c.category_name, u.full_name, u.student_id, u.email 
          FROM items i 
          JOIN categories c ON i.category_id = c.category_id 
          JOIN users u ON i.user_id = u.user_id 
          WHERE i.item_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $item_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

if (!$item) {
    header("Location: items.php");
    exit();
}

$already_claimed = false;
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $claim_check = $conn->prepare("SELECT * FROM claims WHERE item_id = ? AND user_id = ?");
    $claim_check->bind_param("ii", $item_id, $user_id);
    $claim_check->execute();
    if ($claim_check->get_result()->num_rows > 0) {
        $already_claimed = true;
    }
    $claim_check->close();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_item'])) {
    requireLogin();
    
    if ($item['item_type'] !== 'found') {
        $error = 'Only found items can be claimed';
    } elseif ($item['status'] !== 'pending') {
        $error = 'This item is no longer available to claim';
    } elseif ($already_claimed) {
        $error = 'You have already claimed this item';
    } elseif ($item['user_id'] == $_SESSION['user_id']) {
        $error = 'You cannot claim your own item';
    } else {
        $security_question = sanitize($_POST['security_question']);
        $security_answer = sanitize($_POST['security_answer']);
        
        $id_proof_path = '';
        if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === 0) {
            $upload_result = uploadFile($_FILES['id_proof'], 'id_proofs/');
            if ($upload_result['success']) {
                $id_proof_path = $upload_result['filename'];
            } else {
                $error = $upload_result['message'];
            }
        } else {
            $error = 'Please upload your ID proof';
        }
        
        if (empty($error)) {
            $user_id = $_SESSION['user_id'];
            $deadline = date('Y-m-d', strtotime('+7 days'));
            
            $claim_stmt = $conn->prepare("INSERT INTO claims (item_id, user_id, id_proof_path, security_question, security_answer, deadline) VALUES (?, ?, ?, ?, ?, ?)");
            $claim_stmt->bind_param("iissss", $item_id, $user_id, $id_proof_path, $security_question, $security_answer, $deadline);
            
            if ($claim_stmt->execute()) {
                $claim_stmt->close();
                logActivity($user_id, 'claim_item', "Claimed item: {$item['item_name']}");
                sendNotification($item['user_id'], $item_id, 'admin_message', "Someone claimed your found item: {$item['item_name']}. Open the item page to review the claim.");
                header("Location: item-details.php?id=" . $item_id . "&claimed=1");
                exit();
            } else {
                $error = 'Failed to submit claim. Please try again.';
                $claim_stmt->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_claim'])) {
    requireLogin();
    $review = reviewClaim(
        intval($_POST['claim_id']),
        sanitize($_POST['status']),
        sanitize($_POST['admin_notes'] ?? ''),
        $_SESSION['user_id'],
        isAdmin()
    );
    if ($review['success']) {
        header("Location: item-details.php?id=" . $item_id . "&updated=1");
        exit();
    }
    $error = $review['message'];
}

if (isset($_GET['claimed'])) {
    $success = 'Claim submitted successfully. The item reporter will review it.';
}
if (isset($_GET['updated'])) {
    $success = 'Claim updated successfully.';
}

$stmt->close();

$incoming_claims = [];
$is_owner = isLoggedIn() && (int) $item['user_id'] === (int) $_SESSION['user_id'];
if ($is_owner) {
    $incoming_stmt = $conn->prepare("SELECT cl.*, u.full_name, u.student_id AS claimant_student_id
                                    FROM claims cl
                                    JOIN users u ON cl.user_id = u.user_id
                                    WHERE cl.item_id = ?
                                    ORDER BY cl.claim_date DESC");
    $incoming_stmt->bind_param("i", $item_id);
    $incoming_stmt->execute();
    $incoming_claims = $incoming_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $incoming_stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($item['item_name']); ?> - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <style>
        :root {
            --blue-950: #0a1628; --blue-900: #0f2444; --blue: #1e40af; --blue-mid: #2563eb;
            --yellow: #eab308; --yellow-hot: #facc15; --sky: #eff6ff; --paper: #f8fafc;
            --ink: #0f172a; --muted: #475569; --ease: cubic-bezier(.2, .8, .2, 1);
        }
        body { font-family: 'Instrument Sans', system-ui, sans-serif; color: var(--ink); background: var(--paper); }
        h1, h2, h3, h4, h5, .navbar-brand { font-family: 'Bricolage Grotesque', sans-serif; }

        /* Navbar */
        body .navbar {
            background: linear-gradient(100deg, var(--blue-950) 0%, var(--blue-900) 55%, #1e3a8a 100%);
            border-bottom: 2px solid var(--yellow);
            box-shadow: 0 6px 24px rgba(10, 22, 40, .35);
        }
        body .navbar .navbar-brand { font-weight: 800; font-size: 1.5rem; letter-spacing: -.5px; }
        body .navbar .navbar-brand span { color: var(--yellow-hot); }
        body .navbar .nav-link { font-weight: 600; opacity: .85; transition: opacity .2s; }
        body .navbar .nav-link:hover { opacity: 1; }
        .btn-light { background: var(--yellow-hot); border: 0; color: #713f12; font-weight: 700; }
        .btn-light:hover { background: var(--yellow); color: #713f12; }

        /* Item hero */
        .item-hero {
            position: relative; overflow: hidden; padding: 4rem 0 6rem; color: #fff;
            background:
                radial-gradient(800px 450px at 85% 10%, rgba(250,204,21,.3), transparent 60%),
                radial-gradient(600px 450px at 0% 100%, rgba(30,64,175,.7), transparent 60%),
                linear-gradient(160deg, var(--blue-950), var(--blue-900));
        }
        .item-hero::before {
            content: ''; position: absolute; inset: 0; opacity: .07; pointer-events: none;
            background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: linear-gradient(to bottom, #000 30%, transparent 95%);
            -webkit-mask-image: linear-gradient(to bottom, #000 30%, transparent 95%);
        }
        .item-hero::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 28px; background: var(--paper);
            clip-path: polygon(0 100%, 0 55%, 3% 75%, 6% 45%, 9% 70%, 12% 40%, 15% 68%, 18% 50%, 21% 72%, 24% 42%, 27% 66%, 30% 48%, 33% 74%, 36% 44%, 39% 70%, 42% 50%, 45% 76%, 48% 46%, 51% 68%, 54% 42%, 57% 72%, 60% 48%, 63% 70%, 66% 44%, 69% 74%, 72% 50%, 75% 68%, 78% 42%, 81% 72%, 84% 46%, 87% 70%, 90% 52%, 93% 74%, 96% 48%, 100% 66%, 100% 100%);
        }
        .item-title { font-size: clamp(1.9rem, 4vw, 3.2rem); font-weight: 800; letter-spacing: -1px; text-shadow: 0 4px 24px rgba(0,0,0,.35); }
        .item-cat { color: rgba(255,255,255,.8); font-size: 1rem; }
        .badge-type {
            font-size: .85rem; letter-spacing: 2px; font-weight: 800; padding: .55rem 1.1rem; border-radius: 50px;
            display: inline-block;
        }
        .badge-type.lost { background: #fff; color: var(--blue); }
        .badge-type.found { background: var(--yellow-hot); color: #713f12; }

        /* Cards & containers */
        .glass-card {
            background: #fff; border-radius: 20px; box-shadow: 0 12px 36px rgba(10, 22, 40, .1);
            overflow: hidden; transition: transform .3s var(--ease), box-shadow .3s;
        }
        .glass-card:hover { transform: translateY(-3px); box-shadow: 0 18px 44px rgba(10, 22, 40, .18); }
        .image-wrap {
            position: relative; overflow: hidden; border-radius: 20px;
            background: linear-gradient(135deg, var(--sky), #fff);
            display: flex; align-items: center; justify-content: center; min-height: 400px;
        }
        .image-wrap img { max-height: 500px; object-fit: contain; transition: transform .6s var(--ease); }
        .image-wrap:hover img { transform: scale(1.04); }
        .image-wrap .ph { padding: 4rem; color: #93c5fd; font-size: 5rem; }

        /* Info list */
        .detail-row { display: flex; align-items: flex-start; padding: .9rem 0; border-bottom: 1px dashed #dbeafe; }
        .detail-row:last-child { border-bottom: 0; }
        .detail-row .ico {
            width: 38px; height: 38px; flex: none; display: grid; place-items: center;
            border-radius: 12px; background: var(--sky); color: var(--blue); margin-right: .9rem;
        }
        .detail-row .label { font-size: .78rem; font-weight: 700; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; }
        .detail-row .value { font-weight: 600; color: var(--ink); }

        /* Claim button */
        .btn-claim {
            background: linear-gradient(135deg, var(--blue), var(--yellow)); color: #fff; border: 0;
            border-radius: 50px; font-weight: 700; padding: .95rem 1.6rem;
            transition: transform .2s var(--ease), box-shadow .2s;
            animation: glowPulse 2.4s ease-in-out infinite;
        }
        .btn-claim:hover { color: #fff; transform: translateY(-3px); box-shadow: 0 14px 30px rgba(30,64,175,.4); }
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 8px 20px rgba(30,64,175,.25); }
            50% { box-shadow: 0 10px 32px rgba(250,204,21,.55); }
        }

        .btn-back {
            border: 2px solid var(--blue); color: var(--blue); font-weight: 700;
            border-radius: 50px; padding: .7rem 1.4rem; background: transparent;
            transition: background .2s, color .2s;
        }
        .btn-back:hover { background: var(--blue); color: #fff; }

        /* Claim cards */
        .claim-card {
            background: var(--sky); border: 2px dashed #bfdbfe; border-radius: 16px; padding: 1.2rem;
            margin-bottom: 1rem; transition: border-color .2s, background .2s;
        }
        .claim-card:hover { border-color: var(--yellow); background: #fffbeb; }
        .status-badge { font-weight: 700; font-size: .72rem; letter-spacing: 1px; padding: .35rem .8rem; border-radius: 50px; }
        .status-badge.pending { background: #fef9c3; color: #713f12; }
        .status-badge.approved { background: #dbeafe; color: #1e40af; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .status-badge.completed { background: #dcfce7; color: #166534; }

        /* Alerts */
        .alert { border-radius: 14px; border: 0; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-info { background: #dbeafe; color: #1e40af; }

        /* Modal */
        .modal-content { border-radius: 20px; border: 0; overflow: hidden; }
        .modal-header { background: linear-gradient(100deg, var(--blue-950), var(--blue-900)); color: #fff; border: 0; padding: 1.3rem 1.5rem; }
        .modal-header .btn-close { filter: invert(1); }
        .form-control { border: 2px solid #dbeafe; border-radius: 12px; padding: .7rem 1rem; }
        .form-control:focus { border-color: var(--yellow); box-shadow: 0 0 0 4px rgba(234,179,8,.2); }

        /* Entrance animations */
        .fade-rise { opacity: 0; transform: translateY(20px); animation: fadeRise .8s var(--ease) forwards; }
        .d1 { animation-delay: .05s; } .d2 { animation-delay: .15s; } .d3 { animation-delay: .25s; }
        .d4 { animation-delay: .35s; } .d5 { animation-delay: .45s; }
        @keyframes fadeRise { to { opacity: 1; transform: none; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
            .fade-rise { opacity: 1; transform: none; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-search-location me-2"></i>iFind<span>Peace</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="items.php">Browse Items</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="report.php">Report Item</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle position-relative" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-bell"></i>
                                <?php $unread_count = getUnreadNotificationCount($_SESSION['user_id']); if ($unread_count > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu notification-dropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
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
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-light ms-2" href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="item-hero">
        <div class="container position-relative" style="z-index:2;">
            <div class="d-flex align-items-center gap-3 mb-3 fade-rise d1">
                <span class="badge-type <?php echo $item['item_type'] == 'lost' ? 'lost' : 'found'; ?>">
                    <?php echo strtoupper($item['item_type']); ?>
                </span>
                <span class="item-cat"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?></span>
                <span class="item-cat"><i class="fas fa-eye me-1"></i><?php echo number_format($item['view_count']); ?> views</span>
            </div>
            <h1 class="item-title fade-rise d2"><?php echo htmlspecialchars($item['item_name']); ?></h1>
            <p class="item-cat fade-rise d3 mb-0"><i class="fas fa-clock me-1"></i>Posted <?php echo date('F d, Y', strtotime($item['created_at'])); ?></p>
        </div>
    </section>

    <div class="container" style="margin-top: -3rem; position: relative; z-index: 5;">
        <?php if ($error): ?>
            <div class="alert alert-danger fade-rise d1"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success fade-rise d1"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Image -->
            <div class="col-lg-6">
                <div class="image-wrap glass-card fade-rise d1">
                    <?php if ($item['image_path']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                    <?php else: ?>
                        <div class="ph"><i class="fas fa-image"></i></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Details -->
            <div class="col-lg-6">
                <div class="glass-card p-4 p-md-5 fade-rise d2">
                    <h4 class="fw-bold mb-3">Description</h4>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>

                    <h5 class="fw-bold mt-4 mb-3">Details</h5>
                    <div class="detail-row">
                        <div class="ico"><i class="fas fa-<?php echo $item['item_type']=='lost'?'search':'hand-holding-heart'; ?>"></i></div>
                        <div><div class="label">Type</div><div class="value"><?php echo ucfirst($item['item_type']); ?></div></div>
                    </div>
                    <div class="detail-row">
                        <div class="ico"><i class="fas fa-map-marker-alt"></i></div>
                        <div><div class="label">Location</div><div class="value"><?php echo htmlspecialchars($item['item_type'] == 'lost' ? ($item['location_lost'] ?: 'Not specified') : ($item['location_found'] ?: 'Not specified')); ?></div></div>
                    </div>
                    <div class="detail-row">
                        <div class="ico"><i class="fas fa-calendar"></i></div>
                        <div><div class="label">Date</div><div class="value"><?php echo formatDisplayDate($item['item_type'] == 'lost' ? $item['date_lost'] : $item['date_found']); ?></div></div>
                    </div>
                    <?php if ($item['color']): ?>
                    <div class="detail-row">
                        <div class="ico"><i class="fas fa-palette"></i></div>
                        <div><div class="label">Color</div><div class="value"><?php echo htmlspecialchars($item['color']); ?></div></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($item['brand']): ?>
                    <div class="detail-row">
                        <div class="ico"><i class="fas fa-trademark"></i></div>
                        <div><div class="label">Brand</div><div class="value"><?php echo htmlspecialchars($item['brand']); ?></div></div>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <div class="ico"><i class="fas fa-user"></i></div>
                        <div><div class="label">Posted by</div><div class="value"><?php echo htmlspecialchars($item['full_name']); ?></div></div>
                    </div>

                    <div class="mt-4 d-flex flex-column gap-2">
                        <?php if ($item['item_type'] === 'found' && $item['status'] === 'pending' && isLoggedIn() && !isAdmin() && !$already_claimed && $item['user_id'] != $_SESSION['user_id']): ?>
                            <button class="btn btn-claim w-100" data-bs-toggle="modal" data-bs-target="#claimModal">
                                <i class="fas fa-hand-paper me-2"></i>Claim This Item
                            </button>
                        <?php elseif ($already_claimed): ?>
                            <div class="alert alert-info mb-0"><i class="fas fa-info-circle me-2"></i>You have already claimed this item</div>
                        <?php elseif (!isLoggedIn()): ?>
                            <a href="login.php" class="btn btn-claim w-100"><i class="fas fa-sign-in-alt me-2"></i>Login to Claim</a>
                        <?php endif; ?>
                        <a href="items.php" class="btn btn-back w-100"><i class="fas fa-arrow-left me-2"></i>Back to Browse</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Claims on this item -->
        <?php if ($is_owner && $incoming_claims): ?>
            <div class="mt-5 fade-rise d3">
                <h4 class="fw-bold mb-3"><i class="fas fa-clipboard-list me-2"></i>Claims on this item</h4>
                <div class="row g-3">
                    <?php foreach ($incoming_claims as $incoming): ?>
                        <div class="col-lg-6">
                            <div class="claim-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><?php echo htmlspecialchars($incoming['full_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($incoming['claimant_student_id'] ?? 'N/A'); ?> · Deadline <?php echo formatDisplayDate($incoming['deadline'], 'M d, Y'); ?></small>
                                    </div>
                                    <span class="status-badge <?php echo $incoming['claim_status']; ?>"><?php echo ucfirst($incoming['claim_status']); ?></span>
                                </div>
                                <p class="small mb-1"><strong>Q:</strong> <?php echo htmlspecialchars($incoming['security_question']); ?></p>
                                <p class="small mb-2"><strong>A:</strong> <?php echo htmlspecialchars($incoming['security_answer']); ?></p>
                                <?php if ($incoming['id_proof_path']): ?>
                                    <a href="uploads/<?php echo htmlspecialchars($incoming['id_proof_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-2"><i class="fas fa-id-card me-1"></i>View ID proof</a>
                                <?php endif; ?>
                                <form method="POST" class="row g-2">
                                    <input type="hidden" name="review_claim" value="1">
                                    <input type="hidden" name="claim_id" value="<?php echo (int) $incoming['claim_id']; ?>">
                                    <div class="col-5">
                                        <select name="status" class="form-select form-select-sm">
                                            <?php foreach (['pending','approved','rejected','completed'] as $status_option): ?>
                                                <option value="<?php echo $status_option; ?>" <?php echo $incoming['claim_status'] === $status_option ? 'selected' : ''; ?>><?php echo ucfirst($status_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <input type="text" name="admin_notes" class="form-control form-control-sm" placeholder="Notes" value="<?php echo htmlspecialchars($incoming['admin_notes'] ?? ''); ?>">
                                    </div>
                                    <div class="col-3">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Claim Modal -->
    <?php if ($item['item_type'] === 'found' && $item['status'] === 'pending' && isLoggedIn() && !isAdmin() && !$already_claimed && $item['user_id'] != $_SESSION['user_id']): ?>
        <div class="modal fade" id="claimModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-hand-paper me-2"></i>Claim This Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold">ID Proof *</label>
                                <input type="file" name="id_proof" class="form-control" accept="image/*,.pdf" required>
                                <small class="text-muted">Upload your student ID or any valid ID (Max 5MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Security Question *</label>
                                <input type="text" name="security_question" class="form-control" required placeholder="e.g., What color is the item?">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Your Answer *</label>
                                <input type="text" name="security_answer" class="form-control" required placeholder="Your answer">
                            </div>
                            <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Your claim will be reviewed by the reporter. Pickup is at the Organization Office.</div>
                            <button type="submit" name="claim_item" class="btn btn-claim w-100"><i class="fas fa-paper-plane me-2"></i>Submit Claim</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>