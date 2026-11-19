<?php
require_once 'config/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $course = sanitize($_POST['course']);
    $year_level = sanitize($_POST['year_level']);

    $profile_picture = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $upload_result = uploadFile($_FILES['profile_picture'], 'profiles/');
        if ($upload_result['success']) {
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $old_pic = $stmt->get_result()->fetch_assoc()['profile_picture'];
            $stmt->close();
            if ($old_pic) deleteFile($old_pic);
            $profile_picture = $upload_result['filename'];
        } else {
            $error = $upload_result['message'];
        }
    }

    if (empty($error)) {
        if (!empty($profile_picture)) {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, course = ?, year_level = ?, profile_picture = ? WHERE user_id = ?");
            $stmt->bind_param("sssssi", $full_name, $phone, $course, $year_level, $profile_picture, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, course = ?, year_level = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $full_name, $phone, $course, $year_level, $user_id);
        }
        if ($stmt->execute()) {
            $success = 'Profile updated successfully!';
            $_SESSION['full_name'] = $full_name;
            logActivity($user_id, 'update_profile', 'User updated profile');
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

$fields = ['full_name', 'phone', 'course', 'year_level'];
$filled = 0;
foreach ($fields as $f) if (!empty($user[$f] ?? '')) $filled++;
if (!empty($user['profile_picture'])) $filled++;
$total_fields = count($fields) + 1;
$profile_pct = round(($filled / $total_fields) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - iFindPeace</title>
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

        /* FIXED: Increased padding bottom to 8rem so the avatar doesn't cover the subtitle */
        .profile-hero {
            position:relative; overflow:hidden; padding:3rem 0 8rem; color:#fff;
            background:radial-gradient(700px 400px at 80% 10%,rgba(250,204,21,.3),transparent 60%),
                       linear-gradient(160deg,var(--blue-950),var(--blue-900));
        }
        .profile-hero::before { content:''; position:absolute; inset:0; opacity:.07;
            background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);
            background-size:40px 40px; }

        /* FIXED: Changed margin-top from -5rem to -4rem so it sits nicely below the text */
        .avatar-big {
            width:130px; height:130px; border-radius:50%; overflow:hidden;
            border:5px solid rgba(255,255,255,.85); box-shadow:0 18px 40px rgba(0,0,0,.4);
            background:linear-gradient(135deg,var(--blue),var(--yellow));
            display:flex; align-items:center; justify-content:center;
            font-size:3rem; font-weight:800; color:#fff; margin:-4rem auto 1rem; position:relative; z-index:5;
            font-family:'Bricolage Grotesque';
        }
        .avatar-big img { width:100%; height:100%; object-fit:cover; }

        .profile-card { background:#fff; border-radius:22px; box-shadow:0 18px 44px rgba(10,22,40,.12); padding:2rem 2.2rem 2.4rem; }
        .profile-card h3 { font-weight:800; letter-spacing:-.5px; }
        .completion-box { background:var(--sky); border-radius:14px; padding:1rem 1.2rem; margin-bottom:1.5rem; }
        .completion-box .bar { height:8px; border-radius:8px; background:#dbeafe; overflow:hidden; margin-top:.5rem; }
        .completion-box .bar i { display:block; height:100%; border-radius:8px; background:linear-gradient(90deg,var(--blue),var(--yellow-hot)); transition:width 1.2s var(--ease); }
        .completion-box .pct { font-size:1.4rem; font-weight:800; color:var(--blue); font-family:'Bricolage Grotesque'; }

        .form-label { font-weight:700; color:var(--ink); font-size:.85rem; margin-bottom:.4rem; }
        .form-control, .form-select { border:2px solid #dbeafe; border-radius:12px; padding:.7rem 1rem; font-size:.95rem; }
        .form-control:focus, .form-select:focus { border-color:var(--yellow); box-shadow:0 0 0 4px rgba(234,179,8,.2); }
        .form-control[readonly] { background:#f1f5f9; color:var(--muted); cursor:not-allowed; }

        .section-title { font-size:.78rem; font-weight:800; letter-spacing:1.5px; text-transform:uppercase; color:var(--blue); margin:1.5rem 0 .8rem; }

        .pledge-card {
            background:linear-gradient(135deg,#fef9c3,#fff); border:2px dashed var(--yellow);
            border-radius:16px; padding:1.2rem 1.4rem; display:flex; gap:1rem; align-items:flex-start;
        }
        .pledge-card.signed { background:linear-gradient(135deg,#dcfce7,#fff); border-color:#1f9d6b; }
        .pledge-card .icon { width:44px; height:44px; flex:none; border-radius:50%; display:grid; place-items:center; font-size:1.2rem; background:#fde047; color:#713f12; }
        .pledge-card.signed .icon { background:#86efac; color:#166534; }
        .pledge-card strong { display:block; margin-bottom:.2rem; }

        .btn-save {
            width:100%; border:0; border-radius:50px; padding:1rem; font-weight:800; font-size:1rem; color:#fff;
            background:linear-gradient(135deg,var(--blue),var(--yellow));
            transition:transform .2s var(--ease), box-shadow .2s;
        }
        .btn-save:hover { transform:translateY(-3px); box-shadow:0 14px 30px rgba(30,64,175,.4); }
        .btn-save.loading { pointer-events:none; opacity:.85; }
        .btn-save.loading::after { content:''; display:inline-block; width:14px; height:14px; margin-left:.6rem; vertical-align:-2px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }

        .upload-zone {
            display:inline-flex; align-items:center; gap:.6rem; cursor:pointer;
            padding:.65rem 1.2rem; border:2px dashed var(--blue); border-radius:50px; color:var(--blue);
            font-weight:700; font-size:.85rem; transition:background .2s, color .2s;
        }
        .upload-zone:hover { background:var(--blue); color:#fff; }
        .upload-zone input { display:none; }

        .alert { border-radius:14px; border:0; }
        .alert-success { background:#dcfce7; color:#166534; }
        .alert-danger { background:#fee2e2; color:#991b1b; }

        @media (prefers-reduced-motion: reduce) { *,*::before,*::after{animation:none!important;transition:none!important;} }
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
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?></a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="my-items.php"><i class="fas fa-box me-2"></i>My Items</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <section class="profile-hero">
        <div class="container text-center position-relative" style="z-index:2;">
            <h1 class="fw-bold mb-1" style="font-size:clamp(1.6rem,3.5vw,2.4rem);letter-spacing:-1px;">My Profile</h1>
            <p style="color:rgba(255,255,255,.8);">Manage your personal information</p>
        </div>
    </section>

    <div class="container" style="margin-top:-4rem; position:relative; z-index:5;">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="avatar-big">
                    <?php if ($user['profile_picture']): ?>
                        <img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>

                <div class="profile-card">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="text-center mb-4">
                        <h3 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                        <label for="profilePic" class="upload-zone">
                            <input type="file" id="profilePic" accept="image/*" form="profileForm" name="profile_picture">
                            <i class="fas fa-camera"></i>Change Photo
                        </label>
                    </div>

                    <div class="completion-box">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Profile completeness</span>
                            <span class="pct"><?php echo $profile_pct; ?>%</span>
                        </div>
                        <div class="bar"><i style="width:<?php echo $profile_pct; ?>%;"></i></div>
                    </div>

                    <form method="POST" enctype="multipart/form-data" id="profileForm">
                        <div class="section-title">Account Info</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Student ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                        </div>

                        <div class="section-title">Personal</div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="09XXXXXXXXX">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">College / Course</label>
                                <select name="course" class="form-select">
                                    <option value="">Select College</option>
                                    <option value="College of Agriculture" <?php echo ($user['course'] ?? '') === 'College of Agriculture' ? 'selected' : ''; ?>>CA – Agriculture</option>
                                    <option value="College of Business Administration and Accountancy" <?php echo ($user['course'] ?? '') === 'College of Business Administration and Accountancy' ? 'selected' : ''; ?>>CBAA – Business & Accountancy</option>
                                    <option value="College of Education" <?php echo ($user['course'] ?? '') === 'College of Education' ? 'selected' : ''; ?>>CEd – Education</option>
                                    <option value="College of Engineering" <?php echo ($user['course'] ?? '') === 'College of Engineering' ? 'selected' : ''; ?>>COE – Engineering</option>
                                    <option value="College of Fisheries & Aquatic Sciences" <?php echo ($user['course'] ?? '') === 'College of Fisheries & Aquatic Sciences' ? 'selected' : ''; ?>>CFAS – Fisheries & Aquatic Sciences</option>
                                    <option value="College of Health Sciences" <?php echo ($user['course'] ?? '') === 'College of Health Sciences' ? 'selected' : ''; ?>>CHS – Health Sciences</option>
                                    <option value="College of Natural Sciences and Mathematics" <?php echo ($user['course'] ?? '') === 'College of Natural Sciences and Mathematics' ? 'selected' : ''; ?>>CNSM – Natural Sciences & Math</option>
                                    <option value="College of Social Sciences and Humanities" <?php echo ($user['course'] ?? '') === 'College of Social Sciences and Humanities' ? 'selected' : ''; ?>>CSSH – Social Sciences & Humanities</option>
                                    <option value="Institute of Islamic, Arabic, and International Studies" <?php echo ($user['course'] ?? '') === 'Institute of Islamic, Arabic, and International Studies' ? 'selected' : ''; ?>>IIAIS – Islamic & Arabic Studies</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3 mt-3">
                            <label class="form-label">Year Level</label>
                            <select name="year_level" class="form-select">
                                <option value="">Select Year Level</option>
                                <?php foreach (['1st Year','2nd Year','3rd Year','4th Year','5th Year'] as $yl): ?>
                                    <option value="<?php echo $yl; ?>" <?php echo ($user['year_level'] ?? '') === $yl ? 'selected' : ''; ?>><?php echo $yl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="section-title">Honesty Pledge</div>
                        <div class="pledge-card <?php echo $user['honesty_pledge_signed'] ? 'signed' : ''; ?>">
                            <div class="icon"><i class="fas fa-handshake"></i></div>
                            <div>
                                <strong><?php echo $user['honesty_pledge_signed'] ? 'Pledge Signed' : 'Not Signed'; ?></strong>
                                <?php if ($user['honesty_pledge_signed']): ?>
                                    <p class="mb-0 small text-muted">Signed on <?php echo date('F d, Y', strtotime($user['pledge_signed_date'])); ?></p>
                                <?php else: ?>
                                    <p class="mb-0 small text-muted">Please re-sign during registration</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn-save mt-4"><i class="fas fa-save me-2"></i>Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('profileForm').onsubmit = function() {
            this.querySelector('.btn-save').classList.add('loading');
        };
        document.getElementById('profilePic').addEventListener('change', function() {
            document.getElementById('profileForm').submit();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>