<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();
$message = '';
$error = '';

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = intval($_POST['user_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $status, $user_id);
    
    if ($stmt->execute()) {
        $message = "User status updated successfully.";
        logActivity($_SESSION['user_id'], 'update_user_status', "Updated user $user_id status to $status");
    } else {
        $error = "Failed to update user status.";
    }
    $stmt->close();
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

// Get Statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users) as total,
    (SELECT COUNT(*) FROM users WHERE user_type = 'admin') as admins,
    (SELECT COUNT(*) FROM users WHERE status = 'active') as active,
    (SELECT COUNT(*) FROM users WHERE status = 'suspended') as suspended";
$stats = $conn->query($stats_query)->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - iFindPeace Admin</title>
    <link rel="icon" type="image/jpeg" href="../assets/images/msu-logo.jpg">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Core CSS -->
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">

    <style>
        /* ============================================================
           MANAGE USERS SPECIFIC STYLES
           ============================================================ */
        :root {
            --blue-950: #0a1628;
            --blue-900: #0f2444;
            --blue: #1e40af;
            --blue-mid: #2563eb;
            --yellow: #eab308;
            --yellow-hot: #facc15;
            --sky: #eff6ff;
            --paper: #f8fafc;
            --ink: #0f172a;
            --muted: #475569;
            --ease: cubic-bezier(.2, .8, .2, 1);
            --success: #1f9d6b;
            --danger: #dc2626;
        }

        body {
            background-color: var(--paper);
            font-family: 'Instrument Sans', system-ui, sans-serif;
        }

        /* ── Topbar Override ── */
        .topbar { background: #ffffff !important; border-bottom: 2px solid var(--sky); }
        .topbar-title { color: var(--blue-900); font-weight: 800; }
        .topbar-user { background: var(--sky); color: var(--blue-900); border: 1px solid #dbeafe; }

        /* ── Stats Row (Glassmorphism Cards) ── */
        .user-stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 8px 24px rgba(10, 22, 40, 0.04);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1.2rem;
            transition: transform 0.3s var(--ease), box-shadow 0.3s var(--ease);
            position: relative;
            overflow: hidden;
        }
        .user-stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(10, 22, 40, 0.08);
        }
        .user-stat-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--stat-color, var(--blue));
        }
        .user-stat-icon {
            width: 48px; height: 48px;
            border-radius: 14px;
            background: var(--stat-bg, var(--sky));
            color: var(--stat-color, var(--blue));
            display: grid; place-items: center;
            font-size: 1.3rem;
        }
        .user-stat-info h3 {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--ink);
            margin: 0;
            line-height: 1;
        }
        .user-stat-info p {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0.2rem 0 0;
        }

        /* ── Main Table Container ── */
        .users-panel {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(10, 22, 40, 0.06);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }

        /* ── Toolbar (Search & Filters) ── */
        .panel-toolbar {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .search-box {
            position: relative;
            width: 300px;
        }
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .search-box input {
            width: 100%;
            padding: 0.6rem 1rem 0.6rem 2.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 50px;
            font-size: 0.9rem;
            background: #f8fafc;
            transition: all 0.3s;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--blue);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1);
        }
        
        .filter-pills { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .filter-pill {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--muted);
            background: #f1f5f9;
            transition: all 0.2s;
        }
        .filter-pill:hover { background: #e2e8f0; color: var(--ink); }
        .filter-pill.active { background: var(--blue); color: #fff; }

        /* ── Custom Table ── */
        .users-table { width: 100%; margin: 0; border-collapse: collapse; }
        .users-table th {
            background: #f8fafc;
            color: var(--muted);
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 1rem 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        .users-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .users-table tbody tr {
            transition: background 0.2s;
            opacity: 0;
            transform: translateY(10px);
            animation: rowFadeUp 0.5s var(--ease) forwards;
        }
        .users-table tbody tr:hover {
            background: var(--sky);
        }

        /* ── Table Elements ── */
        .table-user-cell { display: flex; align-items: center; gap: 1rem; }
        .user-avatar {
            width: 45px; height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue) 0%, var(--yellow) 100%);
            color: #fff;
            display: grid; place-items: center;
            font-weight: 800;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(30, 64, 175, 0.2);
            flex-shrink: 0;
        }
        .user-info-name { font-weight: 700; color: var(--ink); font-size: 0.95rem; line-height: 1.2; }
        .user-info-email { color: var(--muted); font-size: 0.8rem; margin-top: 0.2rem; }

        .user-role-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .role-admin { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .role-user { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        .status-dropdown {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            transition: all 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23475569'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.7rem center;
            background-size: 1em;
            padding-right: 2.2rem;
        }
        .status-dropdown:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        .pledge-icon { font-size: 1.2rem; }
        .pledge-signed { color: var(--success); }
        .pledge-unsigned { color: #cbd5e1; }

        .btn-view {
            background: var(--sky);
            color: var(--blue);
            border: 0;
            border-radius: 8px;
            padding: 0.4rem 0.8rem;
            font-weight: 700;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-view:hover { background: var(--blue); color: #fff; }

        /* Animations */
        @keyframes rowFadeUp {
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* ── Modal Styling ── */
        .modal-content { border-radius: 20px; border: 0; overflow: hidden; }
        .modal-header { background: linear-gradient(135deg, var(--blue-950), var(--blue-900)); color: #fff; border: 0; padding: 1.5rem; }
        .modal-header .btn-close { filter: invert(1); }
        .modal-body { padding: 2rem; }
        .profile-header { text-align: center; margin-bottom: 2rem; }
        .profile-avatar-lg {
            width: 100px; height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--yellow));
            color: #fff;
            display: inline-grid; place-items: center;
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            box-shadow: 0 10px 25px rgba(30, 64, 175, 0.3);
        }
        .profile-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .profile-detail-row:last-child { border-bottom: 0; }
        .profile-detail-label { color: var(--muted); font-weight: 600; font-size: 0.9rem; }
        .profile-detail-value { color: var(--ink); font-weight: 700; font-size: 0.95rem; text-align: right; }

    </style>
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="topbar-title">Manage Users</span>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content p-4">
            
            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-xl-3">
                    <div class="user-stat-card" style="--stat-color: var(--blue); --stat-bg: var(--sky);">
                        <div class="user-stat-icon"><i class="fas fa-users"></i></div>
                        <div class="user-stat-info">
                            <h3 data-count="<?php echo $stats['total']; ?>">0</h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="user-stat-card" style="--stat-color: #7c3aed; --stat-bg: #ede9fe;">
                        <div class="user-stat-icon"><i class="fas fa-user-shield"></i></div>
                        <div class="user-stat-info">
                            <h3 data-count="<?php echo $stats['admins']; ?>">0</h3>
                            <p>Administrators</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="user-stat-card" style="--stat-color: var(--success); --stat-bg: #dcfce7;">
                        <div class="user-stat-icon"><i class="fas fa-user-check"></i></div>
                        <div class="user-stat-info">
                            <h3 data-count="<?php echo $stats['active']; ?>">0</h3>
                            <p>Active Accounts</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-3">
                    <div class="user-stat-card" style="--stat-color: var(--danger); --stat-bg: #fee2e2;">
                        <div class="user-stat-icon"><i class="fas fa-user-slash"></i></div>
                        <div class="user-stat-info">
                            <h3 data-count="<?php echo $stats['suspended']; ?>">0</h3>
                            <p>Suspended</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Panel -->
            <div class="users-panel">
                <!-- Toolbar -->
                <div class="panel-toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by name or email...">
                    </div>
                    <div class="filter-pills">
                        <a href="users.php" class="filter-pill <?php echo empty($status_filter) && empty($type_filter) ? 'active' : ''; ?>">All</a>
                        <a href="users.php?type=user" class="filter-pill <?php echo $type_filter === 'user' ? 'active' : ''; ?>">Regular Users</a>
                        <a href="users.php?type=admin" class="filter-pill <?php echo $type_filter === 'admin' ? 'active' : ''; ?>">Admins</a>
                        <a href="users.php?status=suspended" class="filter-pill <?php echo $status_filter === 'suspended' ? 'active' : ''; ?>">Suspended</a>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="users-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Pledge</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($users->num_rows > 0): ?>
                                <?php $delay = 0.05; while ($user = $users->fetch_assoc()): ?>
                                    <tr style="animation-delay: <?php echo $delay; ?>s;">
                                        <td>
                                            <div class="table-user-cell">
                                                <div class="user-avatar"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                                <div>
                                                    <div class="user-info-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                    <div class="user-info-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="user-role-badge <?php echo $user['user_type'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                                                <?php echo $user['user_type']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['user_type'] !== 'admin'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="update_status" value="1">
                                                    <select name="status" class="status-dropdown" onchange="this.form.submit()">
                                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success" style="border-radius: 6px;">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['honesty_pledge_signed']): ?>
                                                <i class="fas fa-check-circle pledge-icon pledge-signed"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times-circle pledge-icon pledge-unsigned"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted small fw-semibold">
                                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn-view view-user-btn" 
                                                data-bs-toggle="modal" data-bs-target="#userDetailModal"
                                                data-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?>"
                                                data-course="<?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?>"
                                                data-year="<?php echo htmlspecialchars($user['year_level'] ?? 'N/A'); ?>"
                                                data-type="<?php echo htmlspecialchars(ucfirst($user['user_type'])); ?>"
                                                data-status="<?php echo htmlspecialchars(ucfirst($user['status'])); ?>"
                                                data-pledge="<?php echo $user['honesty_pledge_signed'] ? 'Signed' : 'Not Signed'; ?>">
                                                <i class="fas fa-eye me-1"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php $delay += 0.03; endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="fas fa-search fa-3x mb-3" style="color: #cbd5e1;"></i>
                                            <h5>No users found</h5>
                                            <p>Try adjusting your search or filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- end page-content -->
    </div><!-- end main-content -->

    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fas fa-id-card me-2"></i>User Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="profile-header">
                        <div class="profile-avatar-lg" id="modalAvatar">D</div>
                        <h4 class="fw-bold mb-1" id="modalName">Dummy Name</h4>
                        <p class="text-muted mb-0" id="modalEmail">dummy@email.com</p>
                    </div>
                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Account Type</span>
                        <span class="profile-detail-value" id="modalType">User</span>
                    </div>
                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Status</span>
                        <span class="profile-detail-value" id="modalStatus">Active</span>
                    </div>
                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Phone</span>
                        <span class="profile-detail-value" id="modalPhone">N/A</span>
                    </div>
                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Course / College</span>
                        <span class="profile-detail-value" id="modalCourse">N/A</span>
                    </div>
                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Year Level</span>
                        <span class="profile-detail-value" id="modalYear">N/A</span>
                    </div>
                    <div class="profile-detail-row">
                        <span class="profile-detail-label">Honesty Pledge</span>
                        <span class="profile-detail-value" id="modalPledge">Signed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    
    <script>
        // ============================================================
        // 1. COUNT-UP ANIMATION
        // ============================================================
        document.addEventListener("DOMContentLoaded", () => {
            const counters = document.querySelectorAll('.user-stat-info h3');
            const speed = 200;

            counters.forEach(counter => {
                const updateCount = () => {
                    const target = +counter.getAttribute('data-count');
                    const count = +counter.innerText;
                    const inc = target / speed;

                    if (count < target) {
                        counter.innerText = Math.ceil(count + inc);
                        setTimeout(updateCount, 15);
                    } else {
                        counter.innerText = target;
                    }
                };
                updateCount();
            });
        });

        // ============================================================
        // 2. LIVE SEARCH FILTERING
        // ============================================================
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#usersTable tbody tr');

            tableRows.forEach(row => {
                const name = row.querySelector('.user-info-name')?.textContent.toLowerCase() || '';
                const email = row.querySelector('.user-info-email')?.textContent.toLowerCase() || '';
                
                if (name.includes(searchValue) || email.includes(searchValue)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // ============================================================
        // 3. MODAL DATA POPULATION
        // ============================================================
        document.addEventListener("DOMContentLoaded", () => {
            const viewBtns = document.querySelectorAll('.view-user-btn');
            
            viewBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const name = this.getAttribute('data-name');
                    const email = this.getAttribute('data-email');
                    
                    // Populate modal
                    document.getElementById('modalName').textContent = name;
                    document.getElementById('modalEmail').textContent = email;
                    document.getElementById('modalPhone').textContent = this.getAttribute('data-phone');
                    document.getElementById('modalCourse').textContent = this.getAttribute('data-course');
                    document.getElementById('modalYear').textContent = this.getAttribute('data-year');
                    document.getElementById('modalType').textContent = this.getAttribute('data-type');
                    document.getElementById('modalStatus').textContent = this.getAttribute('data-status');
                    document.getElementById('modalPledge').textContent = this.getAttribute('data-pledge');
                    
                    // Set avatar initial
                    document.getElementById('modalAvatar').textContent = name.charAt(0).toUpperCase();
                });
            });
        });
    </script>
</body>
</html>