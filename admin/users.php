<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = intval($_POST['user_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();
    $stmt->close();
    
    logActivity($_SESSION['user_id'], 'update_user_status', "Updated user $user_id status to $status");
    
    header("Location: users.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'suspended'], true) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['user', 'admin'], true) ? $_GET['type'] : '';

// Build query
$where = "WHERE 1=1";
if (!empty($status_filter)) {
    $where .= " AND status = '$status_filter'";
}
if (!empty($type_filter)) {
    $where .= " AND user_type = '$type_filter'";
}

$query = "SELECT * FROM users $where ORDER BY created_at DESC";
$users = $conn->query($query);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - iFindPeace Admin</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/msu-logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
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
                        <!-- FIXED: Changed text-white to text-dark -->
                        <small class="text-dark" style="font-size: 0.7rem; letter-spacing: 1px; font-weight: 700;">College of Education</small>
<br>
<small class="text-dark" style="font-size: 0.65rem; letter-spacing: 1px; font-weight: 600;">MSU-GenSan</small>
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
        <div class="row mb-3">
            <div class="col-12">
                <h2 class="fw-bold">Manage Users</h2>
                <p class="text-muted small">View and manage all registered users</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section mb-3">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="user" <?php echo $type_filter === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo $type_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <?php if ($users->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Pledge</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                            <span class="text-white fw-bold"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                                        </div>
                                        <div>
                                            <strong class="small"><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                            <br>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['student_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['user_type'] === 'admin' ? 'danger' : 'primary'; ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['user_type'] !== 'admin'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="update_status" value="1">
                                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['honesty_pledge_signed']): ?>
                                        <i class="fas fa-check-circle text-success"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle text-danger"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info text-white view-user" title="View Details"
                                        data-bs-toggle="modal" data-bs-target="#userModal"
                                        data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                        data-student="<?php echo htmlspecialchars($user['student_id']); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-phone="<?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?>"
                                        data-course="<?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?>"
                                        data-year="<?php echo htmlspecialchars($user['year_level'] ?? 'N/A'); ?>"
                                        data-type="<?php echo htmlspecialchars(ucfirst($user['user_type'])); ?>"
                                        data-status="<?php echo htmlspecialchars(ucfirst($user['status'])); ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users fa-4x"></i>
                <h4>No users found</h4>
                <p>Try adjusting your filters</p>
            </div>
        <?php endif; ?>
        </div><!-- end page-content -->
    </div><!-- end main-content -->

    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-1"><strong>Name:</strong> <span id="userDetailName"></span></p>
                    <p class="mb-1"><strong>Student ID:</strong> <span id="userDetailStudent"></span></p>
                    <p class="mb-1"><strong>Email:</strong> <span id="userDetailEmail"></span></p>
                    <p class="mb-1"><strong>Phone:</strong> <span id="userDetailPhone"></span></p>
                    <p class="mb-1"><strong>Course:</strong> <span id="userDetailCourse"></span></p>
                    <p class="mb-1"><strong>Year:</strong> <span id="userDetailYear"></span></p>
                    <p class="mb-1"><strong>Type:</strong> <span id="userDetailType"></span></p>
                    <p class="mb-0"><strong>Status:</strong> <span id="userDetailStatus"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script>
        document.querySelectorAll('.view-user').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('userDetailName').textContent = button.dataset.name || '';
                document.getElementById('userDetailStudent').textContent = button.dataset.student || '';
                document.getElementById('userDetailEmail').textContent = button.dataset.email || '';
                document.getElementById('userDetailPhone').textContent = button.dataset.phone || '';
                document.getElementById('userDetailCourse').textContent = button.dataset.course || '';
                document.getElementById('userDetailYear').textContent = button.dataset.year || '';
                document.getElementById('userDetailType').textContent = button.dataset.type || '';
                document.getElementById('userDetailStatus').textContent = button.dataset.status || '';
            });
        });
    </script>
</body>
</html>