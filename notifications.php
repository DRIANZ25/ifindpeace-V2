<?php
require_once 'config/config.php';
requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

// Mark all as read when viewing
$conn->query("UPDATE notifications SET is_read = TRUE WHERE user_id = $user_id");

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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - iFindPeace</title>
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
                    <li class="nav-item">
                        <a class="nav-link active" href="notifications.php">Notifications</a>
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
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">Notifications</h2>
                <p class="text-muted">Stay updated with your activities</p>
            </div>
        </div>

        <?php if ($notifications->num_rows > 0): ?>
            <div class="card">
                <div class="card-body p-0">
                    <?php while ($notif = $notifications->fetch_assoc()): ?>
                        <div class="notification-item p-4 border-bottom">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <?php
                                    $icon = 'fa-bell';
                                    $color = 'primary';
                                    switch($notif['notification_type']) {
                                        case 'match_found':
                                            $icon = 'fa-search';
                                            $color = 'success';
                                            break;
                                        case 'claim_approved':
                                            $icon = 'fa-check-circle';
                                            $color = 'success';
                                            break;
                                        case 'claim_rejected':
                                            $icon = 'fa-times-circle';
                                            $color = 'danger';
                                            break;
                                        case 'item_expired':
                                            $icon = 'fa-clock';
                                            $color = 'warning';
                                            break;
                                        case 'deadline_reminder':
                                            $icon = 'fa-exclamation-triangle';
                                            $color = 'warning';
                                            break;
                                    }
                                    ?>
                                    <div class="rounded-circle bg-<?php echo $color; ?> d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="fas <?php echo $icon; ?> text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                                    <?php if ($notif['item_name']): ?>
                                        <small class="text-muted">Item: <?php echo htmlspecialchars($notif['item_name']); ?></small>
                                    <?php endif; ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('F d, Y g:i A', strtotime($notif['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash fa-4x"></i>
                <h4>No notifications</h4>
                <p>You're all caught up!</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
