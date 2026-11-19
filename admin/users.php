<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();

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

$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'suspended'], true) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) && in_array($_GET['type'], ['user', 'admin'], true) ? $_GET['type'] : '';

$where = "WHERE 1=1";
if (!empty($status_filter)) { $where .= " AND status = '$status_filter'"; }
if (!empty($type_filter)) { $where .= " AND user_type = '$type_filter'"; }

$query = "SELECT * FROM users $where ORDER BY created_at DESC";
$users = $conn->query($query);

// Stats
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_admins = $conn->query("SELECT COUNT(*) as c FROM users WHERE user_type='admin'")->fetch_assoc()['c'];
$suspended = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='suspended'")->fetch_assoc()['c'];
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
    <style>
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--blue), var(--yellow)); color: #fff; display: grid; place-items: center; font-weight: 800; }
        .table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #475569; border-bottom: 2px solid #f1f5f9; }
        .table td { vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .table tbody tr:hover { background-color: #eff6ff; }
        .filter-bar { display:flex; gap:0.5rem; margin-bottom:1.5rem; }
        .filter-pill { padding: 0.5rem 1rem; border-radius: 50px; background: #fff; border: 2px solid #dbeafe; color: var(--blue); font-weight: 700; text-decoration: none; font-size: 0.85rem; transition: all 0.2s; }
        .filter-pill:hover, .filter-pill.active { background: var(--blue); color: #fff; border-color: var(--blue); }
    </style>
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <span class="topbar-title">Manage Users</span>
            </div>
            <div class="topbar-right">
                <div class="topbar-user"><i class="fas fa-user-shield"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
            </div>
        </div>

        <div class="page-content p-4">
            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 p-3" style="border-left: 5px solid var(--blue) !important;">
                        <h3 class="fw-bold mb-0"><?php echo $total_users; ?></h3>
                        <small class="text-muted text-uppercase fw-bold" style="letter-spacing:1px;">Total Users</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 p-3" style="border-left: 5px solid var(--yellow) !important;">
                        <h3 class="fw-bold mb-0"><?php echo $total_admins; ?></h3>
                        <small class="text-muted text-uppercase fw-bold" style="letter-spacing:1px;">Administrators</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 p-3" style="border-left: 5px solid #ef4444 !important;">
                        <h3 class="fw-bold mb-0"><?php echo $suspended; ?></h3>
                        <small class="text-muted text-uppercase fw-bold" style="letter-spacing:1px;">Suspended</small>
                    </div>
                </div>
            </div>

            <!-- Filter Pills -->
            <div class="filter-bar">
                <a href="users.php" class="filter-pill <?php echo empty($status_filter) && empty($type_filter) ? 'active' : ''; ?>">All Users</a>
                <a href="users.php?type=user" class="filter-pill <?php echo $type_filter === 'user' ? 'active' : ''; ?>">Regular Users</a>
                <a href="users.php?type=admin" class="filter-pill <?php echo $type_filter === 'admin' ? 'active' : ''; ?>">Admins</a>
                <a href="users.php?status=suspended" class="filter-pill <?php echo $status_filter === 'suspended' ? 'active' : ''; ?>">Suspended</a>
            </div>

            <div class="card shadow-sm border-0" style="border-radius: 16px; overflow: hidden;">
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">User</th>
                                <th>ID / Email</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Pledge</th>
                                <th class="pe-4">Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($user['course'] ?? 'No Course'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </td>
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
                                                <select name="status" class="form-select form-select-sm" style="width: 110px; border-radius: 8px;" onchange="this.form.submit()">
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
                                            <i class="fas fa-check-circle text-success fs-5"></i>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger fs-5"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-muted small"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>