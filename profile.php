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
    
    // Handle profile picture upload
    $profile_picture = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $upload_result = uploadFile($_FILES['profile_picture'], 'profiles/');
        if ($upload_result['success']) {
            // Delete old profile picture
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $old_pic = $stmt->get_result()->fetch_assoc()['profile_picture'];
            $stmt->close();
            
            if ($old_pic) {
                deleteFile($old_pic);
            }
            
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

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
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
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="items.php">Browse Items</a>
                    </li>
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
                            <li><a class="dropdown-item" href="notifications.php">View All</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
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

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-user-circle me-2"></i>My Profile</h4>
                    </div>
                    <div class="card-body p-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="text-center mb-4">
                            <?php if ($user['profile_picture']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 120px; height: 120px;">
                                    <span class="text-white fw-bold" style="font-size: 3rem;"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                                </div>
                            <?php endif; ?>
                            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($user['student_id']); ?></p>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Student ID</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['student_id']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Course</label>
                                    <input type="text" name="course" class="form-control" value="<?php echo htmlspecialchars($user['course'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Year Level</label>
                                <select name="year_level" class="form-select">
                                    <option value="">Select Year Level</option>
                                    <option value="1st Year" <?php echo ($user['year_level'] ?? '') === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo ($user['year_level'] ?? '') === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo ($user['year_level'] ?? '') === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo ($user['year_level'] ?? '') === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="5th Year" <?php echo ($user['year_level'] ?? '') === '5th Year' ? 'selected' : ''; ?>>5th Year</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Profile Picture</label>
                                <input type="file" name="profile_picture" class="form-control" accept="image/*">
                                <small class="text-muted">Upload a new profile picture (Max 5MB)</small>
                            </div>

                            <div class="card bg-light mb-3">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">
                                        <i class="fas fa-handshake text-primary me-2"></i>Honesty Pledge Status
                                    </h5>
                                    <?php if ($user['honesty_pledge_signed']): ?>
                                        <p class="card-text text-success mb-0">
                                            <i class="fas fa-check-circle me-2"></i>Signed on <?php echo date('F d, Y', strtotime($user['pledge_signed_date'])); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="card-text text-danger mb-0">
                                            <i class="fas fa-times-circle me-2"></i>Not signed
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
