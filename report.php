<?php
require_once 'config/config.php';
requireLogin();

if (isAdmin()) {
    header("Location: admin/dashboard.php");
    exit();
}

$error = '';
$success = '';
$item_type = isset($_GET['type']) && $_GET['type'] === 'found' ? 'found' : 'lost';

$conn = getDBConnection();
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_type = sanitize($_POST['item_type']);
    $category_id = intval($_POST['category_id']);
    $item_name = sanitize($_POST['item_name']);
    $description = sanitize($_POST['description']);
    $location = sanitize($_POST['location']);
    $date = sanitize($_POST['date']);
    $color = sanitize($_POST['color']);
    $brand = sanitize($_POST['brand']);
    
    if (empty($item_name) || empty($description) || empty($category_id)) {
        $error = 'Please fill in all required fields';
    } else {
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_result = uploadFile($_FILES['image'], 'items/');
            if ($upload_result['success']) { $image_path = $upload_result['filename']; }
            else { $error = $upload_result['message']; }
        }
        
        if (empty($error)) {
            $user_id = $_SESSION['user_id'];
            $item_type = $item_type === 'found' ? 'found' : 'lost';
            if ($item_type === 'lost') {
                $stmt = $conn->prepare("INSERT INTO items (user_id, category_id, item_name, item_type, description, location_lost, date_lost, image_path, color, brand) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            } else {
                $stmt = $conn->prepare("INSERT INTO items (user_id, category_id, item_name, item_type, description, location_found, date_found, image_path, color, brand) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            }
            $stmt->bind_param("iissssssss", $user_id, $category_id, $item_name, $item_type, $description, $location, $date, $image_path, $color, $brand);
            
            if ($stmt->execute()) {
                $item_id = $conn->insert_id;
                $success = ucfirst($item_type) . ' item reported successfully! The admin will review this post.';
                logActivity($user_id, 'report_' . $item_type, "Reported $item_type item: $item_name");
                checkForMatches($item_id, $item_name, $item_type, $category_id, $user_id, $conn);
            } else {
                $error = 'Failed to report item. Please try again.';
            }
            $stmt->close();
        }
    }
}
$conn->close();

function checkForMatches($item_id, $item_name, $item_type, $category_id, $user_id, $conn) {
    $opposite_type = ($item_type === 'lost') ? 'found' : 'lost';
    $cat_stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $cat_stmt->bind_param("i", $category_id);
    $cat_stmt->execute();
    $cat_result = $cat_stmt->get_result()->fetch_assoc();
    $category_name = $cat_result ? $cat_result['category_name'] : 'General';
    $cat_stmt->close();

    $match_query = "SELECT item_id, user_id, item_name FROM items WHERE item_type = ? AND category_id = ? AND status = 'pending' AND user_id != ? LIMIT 5";
    $stmt = $conn->prepare($match_query);
    $stmt->bind_param("sii", $opposite_type, $category_id, $user_id);
    $stmt->execute();
    $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($matches) {
        $msg_new = "Potential match found! Someone recently reported a $opposite_type item in the '$category_name' category. Check your dashboard.";
        sendNotification($user_id, $item_id, 'match_found', $msg_new);
        foreach ($matches as $match) {
            $msg_old = "A new $item_type item ('$item_name') was just reported in the '$category_name' category. This might be your item! Check your dashboard.";
            sendNotification($match['user_id'], $match['item_id'], 'match_found', $msg_old);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Item - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/mobile.css" rel="stylesheet">
    <style>
        :root { --blue-950:#0a1628; --blue-900:#0f2444; --blue:#1e40af; --yellow:#eab308; --yellow-hot:#facc15; --sky:#eff6ff; --paper:#f8fafc; --ink:#0f172a; --muted:#475569; --ease:cubic-bezier(.2,.8,.2,1); }
        body { font-family:'Instrument Sans',system-ui,sans-serif; color:var(--ink); background:var(--paper); }
        h1,h2,h3,h4,h5,h6 { font-family:'Bricolage Grotesque',sans-serif; }
        
        body .navbar { background:linear-gradient(100deg,var(--blue-950),var(--blue-900),#1e3a8a); border-bottom:2px solid var(--yellow); box-shadow:0 6px 24px rgba(10,22,40,.35); }
        body .navbar .navbar-brand { font-weight:800; font-size:1.5rem; }
        body .navbar .navbar-brand span { color:var(--yellow-hot); }
        body .navbar .nav-link { font-weight:600; }

        /* FIXED: Increased bottom padding so the card doesn't overlap the text */
        .report-hero {
            position:relative; overflow:hidden; padding:3.5rem 0 8rem; color:#fff;
            background:radial-gradient(700px 400px at 85% 10%,rgba(250,204,21,.3),transparent 60%),
                       radial-gradient(500px 400px at 0% 100%,rgba(30,64,175,.7),transparent 60%),
                       linear-gradient(160deg,var(--blue-950),var(--blue-900));
        }
        .report-hero::before { content:''; position:absolute; inset:0; opacity:.07;
            background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);
            background-size:40px 40px; mask-image:linear-gradient(to bottom,#000 30%,transparent 95%); -webkit-mask-image:linear-gradient(to bottom,#000 30%,transparent 95%); }
        .report-hero::after { content:''; position:absolute; left:0; right:0; bottom:-1px; height:26px; background:var(--paper);
            clip-path:polygon(0 100%,0 55%,5% 75%,10% 45%,15% 70%,20% 40%,25% 68%,30% 50%,35% 72%,40% 42%,45% 66%,50% 48%,55% 74%,60% 44%,65% 70%,70% 50%,75% 68%,80% 42%,85% 72%,90% 46%,95% 70%,100% 55%,100% 100%); }
        .report-hero h1 { font-weight:800; letter-spacing:-1px; font-size:clamp(1.9rem,4vw,2.8rem); position:relative; z-index:2; }
        
        /* FIXED: Forced the subtitle text to be light so it's visible */
        .report-hero p { color:rgba(255,255,255,.85) !important; position:relative; z-index:2; font-size: 1.1rem; }

        .form-card { background:#fff; border-radius:22px; box-shadow:0 18px 44px rgba(10,22,40,.15); padding:2rem 2rem 2.5rem; margin-top:-4rem; position:relative; z-index:5; }
        .progress-form { margin-bottom:1.5rem; }
        .progress-form .bar { height:8px; border-radius:8px; background:#dbeafe; overflow:hidden; }
        .progress-form .bar i { display:block; height:100%; border-radius:8px; background:linear-gradient(90deg,var(--blue),var(--yellow-hot)); transition:width .5s var(--ease); width:0; }
        .progress-form small { display:block; margin-top:.4rem; color:var(--muted); font-size:.78rem; }

        .dropoff { display:flex; gap:.9rem; align-items:flex-start; background:#fffbeb; border:2px dashed var(--yellow); border-radius:14px; padding:1rem 1.2rem; margin-bottom:1.5rem; }
        .dropoff i { color:#b45309; font-size:1.3rem; margin-top:.1rem; }
        .dropoff p { margin:0; font-size:.88rem; color:#713f12; }

        .type-pick { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem; }
        .type-pick label { position:relative; display:flex; flex-direction:column; align-items:center; padding:1.3rem 1rem; border:2px dashed #dbeafe; border-radius:16px; cursor:pointer; transition:all .3s var(--ease); background:#fff; }
        .type-pick label i { font-size:1.7rem; margin-bottom:.5rem; color:var(--blue); }
        .type-pick label strong { font-family:'Bricolage Grotesque'; font-size:1rem; color:var(--ink); }
        .type-pick label small { color:var(--muted); font-size:.75rem; }
        .type-pick input:checked + label { border-color:var(--yellow); background:#fffbeb; box-shadow:0 8px 24px rgba(234,179,8,.25); transform:translateY(-2px); }
        .type-pick input[value="found"]:checked + label i { color:#b45309; }
        .type-pick input { position:absolute; opacity:0; }

        .form-label { font-weight:700; color:var(--ink); font-size:.85rem; margin-bottom:.4rem; }
        .form-control, .form-select { border:2px solid #dbeafe; border-radius:12px; padding:.7rem 1rem; font-size:.95rem; }
        .form-control:focus, .form-select:focus { border-color:var(--yellow); box-shadow:0 0 0 4px rgba(234,179,8,.2); }
        textarea.form-control { min-height:120px; resize:vertical; }

        .char-counter { text-align:right; font-size:.75rem; color:var(--muted); margin-top:.2rem; }

        .upload-zone { position:relative; display:block; border:2px dashed #dbeafe; border-radius:16px; padding:1.5rem; text-align:center; cursor:pointer; transition:all .3s; background:#fafcff; }
        .upload-zone:hover { border-color:var(--yellow); background:#fffbeb; }
        .upload-zone input { position:absolute; inset:0; opacity:0; cursor:pointer; }
        .upload-zone i { color:#93c5fd; font-size:2rem; margin-bottom:.5rem; display:block; }
        .upload-zone strong { display:block; color:var(--ink); }
        .upload-zone small { color:var(--muted); }
        .upload-zone img.preview { max-height:180px; border-radius:12px; margin-bottom:.5rem; display:none; }

        .btn-submit { width:100%; border:0; border-radius:50px; padding:1rem 1.5rem; font-weight:800; font-size:1rem; color:#fff; background:linear-gradient(135deg,var(--blue),var(--yellow)); transition:transform .2s var(--ease), box-shadow .2s; }
        .btn-submit:hover { transform:translateY(-3px); box-shadow:0 14px 30px rgba(30,64,175,.4); }
        .btn-submit.loading { pointer-events:none; opacity:.85; }
        .btn-submit.loading::after { content:''; display:inline-block; width:14px; height:14px; margin-left:.6rem; vertical-align:-2px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }

        .btn-cancel { border:2px solid var(--blue); color:var(--blue); font-weight:700; border-radius:50px; padding:1rem 1.5rem; background:transparent; transition:background .2s, color .2s; }
        .btn-cancel:hover { background:var(--blue); color:#fff; }

        .alert { border-radius:14px; border:0; }
        .alert-success { background:#dcfce7; color:#166534; }
        .alert-danger { background:#fee2e2; color:#991b1b; }
        .alert-info { background:#dbeafe; color:#1e40af; }

        .fade-rise { opacity:0; transform:translateY(20px); animation:fadeRise .7s var(--ease) forwards; }
        .d1 { animation-delay:.05s; } .d2 { animation-delay:.15s; } .d3 { animation-delay:.25s; } .d4 { animation-delay:.35s; }
        @keyframes fadeRise { to { opacity:1; transform:none; } }

        @media (prefers-reduced-motion: reduce) { *,*::before,*::after{animation:none!important;transition:none!important;} .fade-rise{opacity:1;transform:none;} }
        @media (max-width:767px) { .type-pick { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-search-location me-2"></i>iFind<span>Peace</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="items.php">Browse Items</a></li>
                    <li class="nav-item"><a class="nav-link active" href="report.php">Report Item</a></li>
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
                </ul>
            </div>
        </div>
    </nav>

    <section class="report-hero">
        <div class="container position-relative" style="z-index:2;">
            <h1 class="fade-rise d1">Report <?php echo ucfirst($item_type); ?> Item</h1>
            <p class="fade-rise d2 mb-0">Help keep the community connected — takes less than a minute.</p>
        </div>
    </section>

    <div class="container" style="margin-top:-4rem; position:relative; z-index:5;">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="form-card fade-rise d3">

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="text-center py-5">
                            <div style="width:90px;height:90px;margin:0 auto 1.5rem;border-radius:50%;display:grid;place-items:center;font-size:2.4rem;color:#fff;background:linear-gradient(135deg,#1f9d6b,#34c38f);animation:pop .6s var(--ease) both;">
                                <i class="fas fa-check"></i>
                            </div>
                            <h3 class="fw-bold mb-2">Report submitted!</h3>
                            <p class="text-muted mb-4"><?php echo htmlspecialchars($success); ?></p>
                            <div class="d-flex justify-content-center gap-2 flex-wrap">
                                <a href="my-items.php" class="btn btn-submit px-4" style="max-width:220px;">View My Items</a>
                                <a href="dashboard.php" class="btn btn-cancel px-4" style="max-width:220px;">Dashboard</a>
                            </div>
                        </div>
                        <style>@keyframes pop{from{transform:scale(0) rotate(-40deg);}to{transform:none;}}</style>
                    <?php else: ?>
                        <div class="dropoff">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <strong class="d-block mb-1">Drop-off at the Organization Office</strong>
                                <p class="mb-0">If you found an item, please surrender it to the <strong>Organization Office</strong>. The admin handles verification and returning. Never give items directly to claimants.</p>
                            </div>
                        </div>

                        <div class="progress-form">
                            <div class="bar"><i id="pbar"></i></div>
                            <small id="ptxt">Fill in the required fields to continue</small>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="reportForm">
                            <div class="type-pick">
                                <input type="radio" class="btn-check" name="item_type" id="typeLost" value="lost" <?php echo $item_type==='lost'?'checked':''; ?> required>
                                <label for="typeLost">
                                    <i class="fas fa-search"></i>
                                    <strong>I Lost an Item</strong>
                                    <small>Report something you're searching for</small>
                                </label>
                                <input type="radio" class="btn-check" name="item_type" id="typeFound" value="found" <?php echo $item_type==='found'?'checked':''; ?>>
                                <label for="typeFound">
                                    <i class="fas fa-hand-holding-heart"></i>
                                    <strong>I Found an Item</strong>
                                    <small>Turn in what someone else lost</small>
                                </label>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Category *</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php $categories->data_seek(0); while ($cat = $categories->fetch_assoc()): ?>
                                            <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Item Name *</label>
                                    <input type="text" name="item_name" class="form-control" required placeholder="e.g., iPhone 13, Black Wallet">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Description *</label>
                                <textarea name="description" id="desc" class="form-control" maxlength="500" required placeholder="Provide detailed description of the item..."></textarea>
                                <div class="char-counter"><span id="charCount">0</span> / 500</div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label" id="locationLabel"><?php echo $item_type==='lost'?'Where was it lost?':'Where was it found?'; ?> *</label>
                                    <input type="text" name="location" class="form-control" required placeholder="e.g., Library, 2nd Floor">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" id="dateLabel"><?php echo $item_type==='lost'?'Date Lost':'Date Found'; ?> *</label>
                                    <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">Color <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="text" name="color" class="form-control" placeholder="e.g., Black, Blue">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Brand <span class="text-muted fw-normal">(optional)</span></label>
                                    <input type="text" name="brand" class="form-control" placeholder="e.g., Apple, Nike">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label">Item Image <span class="text-muted fw-normal">(optional)</span></label>
                                <label class="upload-zone" for="itemImage">
                                    <input type="file" name="image" accept="image/*" id="itemImage">
                                    <img id="imgPreview" class="preview" alt="Preview">
                                    <i class="fas fa-cloud-upload-alt" id="uploadIcon"></i>
                                    <strong id="uploadText">Click to upload a photo</strong>
                                    <small>JPG, PNG · Max 5MB</small>
                                </label>
                            </div>

                            <div class="d-flex gap-2 mt-4 flex-wrap">
                                <button type="submit" class="btn-submit flex-grow-1"><i class="fas fa-paper-plane me-2"></i>Submit Report</button>
                                <a href="dashboard.php" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        var typeLost = document.getElementById('typeLost'), typeFound = document.getElementById('typeFound');
        if (typeLost) {
            typeLost.addEventListener('change', function() {
                document.getElementById('locationLabel').textContent = 'Where was it lost? *';
                document.getElementById('dateLabel').textContent = 'Date Lost';
            });
            typeFound.addEventListener('change', function() {
                document.getElementById('locationLabel').textContent = 'Where was it found? *';
                document.getElementById('dateLabel').textContent = 'Date Found';
            });
        }

        var img = document.getElementById('itemImage');
        if (img) {
            img.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                var preview = document.getElementById('imgPreview');
                var icon = document.getElementById('uploadIcon');
                var text = document.getElementById('uploadText');
                var reader = new FileReader();
                reader.onload = function(ev) {
                    preview.src = ev.target.result;
                    preview.style.display = 'inline-block';
                    icon.style.display = 'none';
                    text.textContent = file.name;
                };
                reader.readAsDataURL(file);
            });
        }

        var desc = document.getElementById('desc');
        if (desc) {
            var cc = document.getElementById('charCount');
            desc.addEventListener('input', function() { cc.textContent = desc.value.length; });
        }

        var form = document.getElementById('reportForm');
        if (form) {
            var pbar = document.getElementById('pbar'), ptxt = document.getElementById('ptxt');
            function updateProgress() {
                var fields = [
                    form.category_id.value,
                    form.item_name.value.trim(),
                    form.description.value.trim(),
                    form.location.value.trim(),
                    form.date.value
                ];
                var done = fields.filter(function(v) { return v && v.length > 0; }).length;
                var pct = (done / fields.length) * 100;
                pbar.style.width = pct + '%';
                ptxt.textContent = done === fields.length ? 'Ready to submit!' : done + ' of ' + fields.length + ' required fields';
            }
            form.addEventListener('input', updateProgress);
            form.querySelectorAll('select').forEach(function(s) { s.addEventListener('change', updateProgress); });
            form.onsubmit = function() { form.querySelector('.btn-submit').classList.add('loading'); };
            updateProgress();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>