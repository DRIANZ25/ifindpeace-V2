<?php
require_once 'config/config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$query = "SELECT cl.*, i.item_name, i.item_type, i.image_path, u.full_name as reporter_name
          FROM claims cl
          JOIN items i ON cl.item_id = i.item_id
          JOIN users u ON i.user_id = u.user_id
          WHERE cl.user_id = ?
          ORDER BY cl.claim_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$claims = $stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Claims - iFindPeace</title>
    <link rel="icon" type="image/jpeg" href="assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link href="assets/css/sidebar.css" rel="stylesheet">
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="topbar-title">My Claims</span>
            </div>
            <div class="topbar-right">
                <?php 
                $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
                ?>
                <a href="notifications.php" class="topbar-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="topbar-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="topbar-user">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">My Claims</h2>
                <p class="text-muted">Track your item claims</p>
            </div>
        </div>

        <?php if ($claims->num_rows > 0): ?>
            <div class="row g-3">
                <?php while ($claim = $claims->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 item-card">
                            <div class="card-image-wrapper">
                                <?php if ($claim['image_path']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($claim['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($claim['item_name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-badge">
                                    <?php
                                    $badge_class = 'bg-info';
                                    $display_status = ucfirst($claim['claim_status']);
                                    if ($claim['claim_status'] == 'pending') {
                                        $badge_class = 'bg-warning';
                                    } elseif ($claim['claim_status'] == 'approved') {
                                        $badge_class = 'bg-success';
                                    } elseif ($claim['claim_status'] == 'rejected') {
                                        $badge_class = 'bg-danger';
                                    } elseif ($claim['claim_status'] == 'completed') {
                                        $badge_class = 'badge-returned';
                                        $display_status = 'Returned';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> small">
                                        <?php echo $display_status; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($claim['item_name']); ?></h6>
                                <p class="small text-muted mb-2">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($claim['reporter_name']); ?>
                                </p>
                                <p class="card-text text-truncate mb-2 small text-muted">
                                    <i class="fas fa-question-circle me-1"></i><?php echo htmlspecialchars($claim['security_question']); ?>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted small">
                                        <i class="fas fa-calendar me-1"></i><?php echo formatDisplayDate($claim['deadline'], 'M d, Y'); ?>
                                    </small>
                                    <small class="text-muted small">
                                        <i class="fas fa-clock me-1"></i><?php echo formatDisplayDate($claim['claim_date'], 'M d, Y'); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="item-details.php?id=<?php echo $claim['item_id']; ?>" class="btn btn-primary btn-sm w-100">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-hand-paper fa-3x"></i>
                <h5>No claims yet</h5>
                <p class="small">Browse items and claim found items that belong to you</p>
                <a href="items.php" class="btn btn-primary btn-sm">Browse Items</a>
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
