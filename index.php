<?php
require_once 'config/config.php';

// Get recent items (Removed the WHERE clause so returned items still show up)
$conn = getDBConnection();
$recent_items_query = "SELECT i.*, c.category_name, u.full_name, u.student_id 
                      FROM items i 
                      JOIN categories c ON i.category_id = c.category_id 
                      JOIN users u ON i.user_id = u.user_id 
                      ORDER BY i.created_at DESC 
                      LIMIT 8";
$recent_items = $conn->query($recent_items_query);

// Get statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM items WHERE item_type = 'lost' AND status = 'pending') as lost_count,
                (SELECT COUNT(*) FROM items WHERE item_type = 'found' AND status = 'pending') as found_count,
                (SELECT COUNT(*) FROM items WHERE status = 'claimed') as claimed_count,
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
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
                        <small class="text-white" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">MINDANAO STATE UNIVERSITY</small>
                        <br>
                        <small class="text-white" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">GENERAL SANTOS</small>
                    </div>
                </div>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="items.php">Browse Items</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>">Dashboard</a>
                        </li>
                        <?php if (!isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="report.php">Report Item</a>
                        </li>
                        <?php endif; ?>
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
                                <?php
                                $conn = getDBConnection();
                                $notif_query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
                                $stmt = $conn->prepare($notif_query);
                                $stmt->bind_param("i", $_SESSION['user_id']);
                                $stmt->execute();
                                $notifications = $stmt->get_result();
                                
                                if ($notifications->num_rows > 0):
                                    while ($notif = $notifications->fetch_assoc()): ?>
                                        <li class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                            <small class="text-muted"><?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></small>
                                            <p class="mb-0 small"><?php echo htmlspecialchars($notif['message']); ?></p>
                                        </li>
                                    <?php endwhile;
                                else: ?>
                                    <li class="notification-item">
                                        <p class="mb-0 small text-muted">No notifications</p>
                                    </li>
                                <?php endif;
                                $stmt->close();
                                $conn->close();
                                ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="notifications.php">View All</a></li>
                            </ul>
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
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-sign-in-alt me-1"></i>Log In
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn ms-2" href="register.php" style="background: linear-gradient(135deg, #ff69b4 0%, #ff00ff 100%); color: white; border: none; padding: 0.5rem 1.25rem; font-weight: 700; box-shadow: 0 2px 8px rgba(255,105,180,0.4);">
                                <i class="fas fa-user-plus me-1"></i>Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8">
                    <h1 class="hero-title" style="text-shadow: 0 4px 12px rgba(0,0,0,0.3);">Lost Something? Found Something?</h1>
                    <p class="hero-subtitle" style="text-shadow: 0 2px 8px rgba(0,0,0,0.2);">iFindPeace helps MSU students recover lost belongings and report found items. Join our community of honesty and accountability.</p>
                    
                    <!-- Search Box -->
                    <form action="items.php" method="GET" class="search-box">
                        <div class="input-group" style="box-shadow: 0 8px 30px rgba(0,0,0,0.2); border-radius: 50px; overflow: hidden;">
                            <input type="text" name="search" class="form-control" placeholder="Search for lost or found items..." required style="border: none; padding: 0.875rem 1.5rem; font-size: 1rem; font-weight: 500;">
                            <button type="submit" class="btn" style="background: linear-gradient(135deg, #FFA500 0%, #ff6600 100%); border: none; padding: 0.875rem 2rem; font-weight: 700; box-shadow: none;">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </form>
                    
                    <?php if (!isAdmin()): ?>
                    <div class="mt-4">
                        <a href="report.php" class="btn btn-lg me-2" style="background: rgba(255,255,255,0.95); color: #800000; border: none; padding: 0.75rem 1.75rem; font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s ease;">
                            <i class="fas fa-plus-circle me-2"></i>Report Lost Item
                        </a>
                        <a href="report.php?type=found" class="btn btn-outline-light btn-lg" style="border: 2px solid rgba(255,255,255,0.8); color: white; padding: 0.75rem 1.75rem; font-weight: 700; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s ease;">
                            <i class="fas fa-hand-holding-heart me-2"></i>Report Found Item
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics -->
    <section class="py-4">
        <div class="container">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="stat-card text-center" style="position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.05; color: #800000;">
                            <i class="fas fa-search"></i>
                        </div>
                        <div class="mb-1">
                            <i class="fas fa-search fa-lg" style="color: #800000; opacity: 0.3;"></i>
                        </div>
                        <div class="stat-number" style="color: #800000;"><?php echo $stats['lost_count']; ?></div>
                        <div class="stat-label">Lost Items</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card success text-center" style="position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.05; color: #ff00ff;">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <div class="mb-1">
                            <i class="fas fa-hand-holding-heart fa-lg" style="color: #ff00ff; opacity: 0.3;"></i>
                        </div>
                        <div class="stat-number" style="color: #ff00ff;"><?php echo $stats['found_count']; ?></div>
                        <div class="stat-label">Found Items</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card text-center" style="position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.05; color: #c71585;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="mb-1">
                            <i class="fas fa-check-circle fa-lg" style="color: #c71585; opacity: 0.3;"></i>
                        </div>
                        <div class="stat-number" style="color: #c71585;"><?php echo $stats['claimed_count']; ?></div>
                        <div class="stat-label">Items Claimed</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card warning text-center" style="position: relative; overflow: hidden;">
                        <div style="position: absolute; top: -20px; right: -20px; font-size: 5rem; opacity: 0.05; color: #ff69b4;">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="mb-1">
                            <i class="fas fa-users fa-lg" style="color: #ff69b4; opacity: 0.3;"></i>
                        </div>
                        <div class="stat-number" style="color: #ff69b4;"><?php echo $stats['user_count']; ?></div>
                        <div class="stat-label">Registered Users</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Items -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1" style="color: #1f2937;">Recent Items</h2>
                    <p class="text-muted small mb-0">Latest lost and found items reported</p>
                </div>
                <a href="items.php" class="btn btn-primary">
                    View All Items <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>

            <?php if ($recent_items->num_rows > 0): ?>
                <div class="row g-3">
                    <?php while ($item = $recent_items->fetch_assoc()): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="card h-100 item-card" style="border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                                <div class="card-image-wrapper" style="position: relative; overflow: hidden; border-radius: 0.75rem 0.75rem 0 0;">
                                    <?php if ($item['image_path']): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($item['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?>" style="height: 160px; object-fit: cover; transition: transform 0.4s ease;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 160px;">
                                            <i class="fas fa-image fa-3x text-muted" style="opacity: 0.3;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status Indicator (Pending / Returned) -->
                                    <?php
                                    $status_class = 'bg-warning text-dark';
                                    $status_text = 'Pending';
                                    if ($item['status'] == 'claimed' || $item['status'] == 'returned') {
                                        $status_class = 'bg-success';
                                        $status_text = 'Returned';
                                    }
                                    ?>
                                    <div style="position: absolute; top: 10px; left: 10px; z-index: 2;">
                                        <span class="badge <?php echo $status_class; ?>" style="font-size: 0.7rem; padding: 0.35rem 0.85rem; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>

                                    <!-- Item Type (Lost / Found) -->
                                    <div class="card-badge" style="position: absolute; top: 10px; right: 10px; z-index: 2;">
                                        <span class="badge <?php echo $item['item_type'] == 'lost' ? 'badge-lost' : 'badge-found'; ?>" style="font-size: 0.7rem; padding: 0.35rem 0.85rem; font-weight: 700; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                                            <?php echo strtoupper($item['item_type']); ?>
                                        </span>
                                    </div>
                                    <div style="position: absolute; bottom: 0; left: 0; right: 0; height: 60px; background: linear-gradient(to top, rgba(0,0,0,0.7), transparent); pointer-events: none;"></div>
                                </div>
                                <div class="card-body" style="padding: 1rem;">
                                    <h6 class="card-title mb-2" style="font-weight: 700; color: #1f2937; line-height: 1.3;"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                    <p class="card-text text-truncate mb-3 small" style="color: #6b7280; font-weight: 500;"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-light text-dark small" style="padding: 0.3rem 0.75rem; font-weight: 600;">
                                            <i class="fas fa-tag me-1" style="color: #800000;"></i><?php echo htmlspecialchars($item['category_name']); ?>
                                        </span>
                                        <small class="text-muted small" style="font-weight: 500;">
                                            <i class="fas fa-clock me-1" style="color: #ff69b4;"></i><?php echo date('M d', strtotime($item['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="card-footer" style="background: #f8f9fa; border-top: 1px solid #e5e7eb; padding: 0.75rem 1rem;">
                                    <a href="item-details.php?id=<?php echo $item['item_id']; ?>" class="btn btn-primary btn-sm w-100" style="font-weight: 700; padding: 0.5rem;">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open fa-3x"></i>
                    <h5>No items yet</h5>
                    <p class="small">Be the first to report a lost or found item!</p>
                    <a href="report.php" class="btn btn-primary btn-sm">Report an Item</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-4">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="fw-bold mb-2" style="color: #1f2937;">How It Works</h2>
                <p class="text-muted small">Simple steps to find what you've lost</p>
            </div>
            <div class="row g-3">
                <div class="col-md-4 text-center">
                    <div class="card h-100 border-0" style="background: linear-gradient(135deg, rgba(128,0,0,0.05) 0%, rgba(255,0,255,0.05) 100%); border-radius: 1rem; padding: 1.5rem; transition: all 0.3s ease;">
                        <div class="mb-3" style="position: relative; display: inline-block;">
                            <div style="position: absolute; width: 100%; height: 100%; background: radial-gradient(circle, rgba(128,0,0,0.2) 0%, transparent 70%); border-radius: 50%;"></div>
                            <i class="fas fa-clipboard-list fa-3x" style="color: #800000; position: relative; z-index: 1;"></i>
                        </div>
                        <h5 style="font-weight: 700; color: #1f2937;">1. Report Item</h5>
                        <p class="text-muted small" style="font-weight: 500;">Report your lost item or a found item with details and photos.</p>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="card h-100 border-0" style="background: linear-gradient(135deg, rgba(255,0,255,0.05) 0%, rgba(199,21,133,0.05) 100%); border-radius: 1rem; padding: 1.5rem; transition: all 0.3s ease;">
                        <div class="mb-3" style="position: relative; display: inline-block;">
                            <div style="position: absolute; width: 100%; height: 100%; background: radial-gradient(circle, rgba(255,0,255,0.2) 0%, transparent 70%); border-radius: 50%;"></div>
                            <i class="fas fa-search fa-3x" style="color: #ff00ff; position: relative; z-index: 1;"></i>
                        </div>
                        <h5 style="font-weight: 700; color: #1f2937;">2. Search & Match</h5>
                        <p class="text-muted small" style="font-weight: 500;">Our system automatically matches lost and found items.</p>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="card h-100 border-0" style="background: linear-gradient(135deg, rgba(199,21,133,0.05) 0%, rgba(255,105,180,0.05) 100%); border-radius: 1rem; padding: 1.5rem; transition: all 0.3s ease;">
                        <div class="mb-3" style="position: relative; display: inline-block;">
                            <div style="position: absolute; width: 100%; height: 100%; background: radial-gradient(circle, rgba(199,21,133,0.2) 0%, transparent 70%); border-radius: 50%;"></div>
                            <i class="fas fa-handshake fa-3x" style="color: #c71585; position: relative; z-index: 1;"></i>
                        </div>
                        <h5 style="font-weight: 700; color: #1f2937;">3. Claim & Recover</h5>
                        <p class="text-muted small" style="font-weight: 500;">Verify ownership and claim your item securely.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-4" style="background: var(--gradient-primary); color: white;">
        <div class="container text-center">
            <h2 class="fw-bold mb-2">Join Our Community</h2>
            <p class="mb-3">Help create a culture of honesty and accountability at MSU-Gensan.</p>
            <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn btn-light btn-lg">Register Now <i class="fas fa-arrow-right ms-2"></i></a>
            <?php else: ?>
                <a href="report.php" class="btn btn-light btn-lg">Report an Item <i class="fas fa-arrow-right ms-2"></i></a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">iFindPeace</h5>
                    <p class="text-white-50">A centralized lost and found platform for Mindanao State University - General Santos students.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">Quick Links</h5>
                    <div class="footer-links d-flex flex-column">
                        <a href="index.php">Home</a>
                        <a href="items.php">Browse Items</a>
                        <a href="about.php">About Us</a>
                        <a href="contact.php">Contact</a>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">Contact Info</h5>
                    <p class="text-white-50 mb-2">
                        <i class="fas fa-map-marker-alt me-2"></i>MSU-Gensan, General Santos City
                    </p>
                    <p class="text-white-50 mb-2">
                        <i class="fas fa-envelope me-2"></i>support@ifindpeace.com
                    </p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center text-white-50">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> iFindPeace. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>