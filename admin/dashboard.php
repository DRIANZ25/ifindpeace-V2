<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();

// Get platform statistics
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM items) as total_items,
                (SELECT COUNT(*) FROM users WHERE user_type = 'user') as user_count,
                (SELECT COUNT(*) FROM items WHERE status = 'claimed') as claimed_count,
                (SELECT COUNT(*) FROM claims) as total_claims";
$stats = $conn->query($stats_query)->fetch_assoc();

// Get recent users
$recent_users_query = "SELECT * FROM users ORDER BY created_at DESC LIMIT 5";
$recent_users = $conn->query($recent_users_query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - iFindPeace</title>
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
                <h2 class="fw-bold">Admin Dashboard</h2>
                <p class="text-muted">Overview of platform activity</p>
            </div>
        </div>

        <!-- Admin Workflow Alert -->
        <div class="alert alert-primary border-primary mb-4">
            <h6 class="fw-bold mb-1"><i class="fas fa-bullhorn me-2"></i>Reminder for Admin</h6>
            <p class="mb-0 small">Please ensure that all found items are surrendered to the <strong>Organization Office</strong>. Direct meetups between users are prohibited. Only mark items as "Claimed" after physically handing them over to the verified owner.</p>
        </div>

        <!-- Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-box fa-lg" style="color: #800000;"></i>
                    </div>
                    <div class="stat-number" style="color: #800000;"><?php echo $stats['total_items']; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success text-center">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-users fa-lg" style="color: #ff00ff;"></i>
                    </div>
                    <div class="stat-number" style="color: #ff00ff;"><?php echo $stats['user_count']; ?></div>
                    <div class="stat-label">Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-check-circle fa-lg" style="color: #c71585;"></i>
                    </div>
                    <div class="stat-number" style="color: #c71585;"><?php echo $stats['claimed_count']; ?></div>
                    <div class="stat-label">Claimed</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning text-center">
                    <div class="stat-icon mb-1">
                        <i class="fas fa-hand-paper fa-lg" style="color: #ff69b4;"></i>
                    </div>
                    <div class="stat-number" style="color: #ff69b4;"><?php echo $stats['total_claims']; ?></div>
                    <div class="stat-label">Total Claims</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Users -->
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>Recent Users</h6>
                        <a href="users.php" class="btn btn-sm btn-light">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_users->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Student ID</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['student_id']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No users yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <a href="items.php" class="btn w-100 py-2" style="background: linear-gradient(135deg, #800000 0%, #c71585 100%); color: white; font-weight: 700; padding: 0.625rem 1.25rem; box-shadow: 0 2px 8px rgba(128,0,0,0.3);">
                                    <i class="fas fa-box fa-lg me-1"></i>
                                    Manage Items
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="claims.php" class="btn w-100 py-2" style="background: linear-gradient(135deg, #c71585 0%, #ff69b4 100%); color: white; font-weight: 700; padding: 0.625rem 1.25rem; box-shadow: 0 2px 8px rgba(199,21,133,0.3);">
                                    <i class="fas fa-hand-paper fa-lg me-1"></i>
                                    Manage Claims
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="users.php" class="btn w-100 py-2" style="background: linear-gradient(135deg, #ff00ff 0%, #c71585 100%); color: white; font-weight: 700; padding: 0.625rem 1.25rem; box-shadow: 0 2px 8px rgba(255,0,255,0.3);">
                                    <i class="fas fa-users fa-lg me-1"></i>
                                    Manage Users
                                </a>
                            </div>
                        </div>
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