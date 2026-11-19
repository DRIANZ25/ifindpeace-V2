<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();

// Get various statistics for reports
$stats = [
    'total_items' => $conn->query("SELECT COUNT(*) as count FROM items")->fetch_assoc()['count'],
    'lost_items' => $conn->query("SELECT COUNT(*) as count FROM items WHERE item_type = 'lost'")->fetch_assoc()['count'],
    'found_items' => $conn->query("SELECT COUNT(*) as count FROM items WHERE item_type = 'found'")->fetch_assoc()['count'],
    'claimed_items' => $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'claimed'")->fetch_assoc()['count'],
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_claims' => $conn->query("SELECT COUNT(*) as count FROM claims")->fetch_assoc()['count'],
    'approved_claims' => $conn->query("SELECT COUNT(*) as count FROM claims WHERE claim_status = 'approved'")->fetch_assoc()['count'],
];

// Items by category
$category_stats = $conn->query("SELECT c.category_name, COUNT(i.item_id) as count 
                                FROM categories c 
                                LEFT JOIN items i ON c.category_id = i.category_id 
                                GROUP BY c.category_id, c.category_name 
                                ORDER BY count DESC");

// Recent activity
$recent_activity = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");

// Top users (most items reported)
$top_users = $conn->query("SELECT u.full_name, u.student_id, COUNT(i.item_id) as item_count 
                          FROM users u 
                          JOIN items i ON u.user_id = i.user_id 
                          GROUP BY u.user_id, u.full_name, u.student_id 
                          ORDER BY item_count DESC 
                          LIMIT 5");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - iFindPeace Admin</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="msu-nav-text ms-3 d-none d-lg-flex align-items-center">
                    <img src="../assets/images/msu-logo.jpg" alt="MSU Logo" class="msu-logo-icon me-2">
                    <div>
                        <small class="text-white" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">MINDANAO STATE UNIVERSITY</small>
                        <br>
                        <small class="text-white" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">GENERAL SANTOS</small>
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="fw-bold">Platform Reports</h2>
                <p class="text-muted">Analytics and statistics overview</p>
            </div>
        </div>

        <!-- Overview Statistics -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo $stats['total_items']; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-danger"><?php echo $stats['lost_items']; ?></div>
                    <div class="stat-label">Lost Items</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success text-center">
                    <div class="stat-number text-success"><?php echo $stats['found_items']; ?></div>
                    <div class="stat-label">Found Items</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-number text-primary"><?php echo $stats['claimed_items']; ?></div>
                    <div class="stat-label">Claimed Items</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-number text-info"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="stat-number text-warning"><?php echo $stats['total_claims']; ?></div>
                    <div class="stat-label">Total Claims</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card success text-center">
                    <div class="stat-number text-success"><?php echo $stats['approved_claims']; ?></div>
                    <div class="stat-label">Approved Claims</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Items by Category -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Items by Category</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($category_stats->num_rows > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total = $stats['total_items'];
                                    while ($cat = $category_stats->fetch_assoc()): 
                                        $percentage = $total > 0 ? round(($cat['count'] / $total) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                            <td><?php echo $cat['count']; ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%;" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $percentage; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted text-center">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Users -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Contributors</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($top_users->num_rows > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>User</th>
                                        <th>Items Reported</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    while ($user = $top_users->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if ($rank === 1): ?>
                                                    <i class="fas fa-medal text-warning"></i>
                                                <?php elseif ($rank === 2): ?>
                                                    <i class="fas fa-medal text-secondary"></i>
                                                <?php elseif ($rank === 3): ?>
                                                    <i class="fas fa-medal text-danger"></i>
                                                <?php else: ?>
                                                    <?php echo $rank; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['student_id']); ?></small>
                                            </td>
                                            <td><?php echo $user['item_count']; ?></td>
                                        </tr>
                                    <?php 
                                    $rank++;
                                    endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-muted text-center">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_activity->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($activity['user_id']): ?>
                                                        <?php 
                                                        $conn = getDBConnection();
                                                        $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
                                                        $stmt->bind_param("i", $activity['user_id']);
                                                        $stmt->execute();
                                                        $user = $stmt->get_result()->fetch_assoc();
                                                        $stmt->close();
                                                        $conn->close();
                                                        echo htmlspecialchars(is_array($user) ? $user['full_name'] : 'Unknown');
                                                        ?>
                                                    <?php else: ?>
                                                        System
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['description'] ?? '-'); ?></td>
                                                <td><small class="text-muted"><?php echo htmlspecialchars($activity['ip_address'] ?? '-'); ?></small></td>
                                                <td><small><?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?></small></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        </div><!-- end page-content -->
    </div><!-- end main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>
