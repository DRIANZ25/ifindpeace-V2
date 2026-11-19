<?php
require_once '../config/config.php';
requireAdmin();

$conn = getDBConnection();

$stats = [
    'total_items' => $conn->query("SELECT COUNT(*) as count FROM items")->fetch_assoc()['count'],
    'lost_items' => $conn->query("SELECT COUNT(*) as count FROM items WHERE item_type = 'lost'")->fetch_assoc()['count'],
    'found_items' => $conn->query("SELECT COUNT(*) as count FROM items WHERE item_type = 'found'")->fetch_assoc()['count'],
    'returned_items' => $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'returned'")->fetch_assoc()['count'],
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'total_claims' => $conn->query("SELECT COUNT(*) as count FROM claims")->fetch_assoc()['count'],
    'approved_claims' => $conn->query("SELECT COUNT(*) as count FROM claims WHERE claim_status = 'approved'")->fetch_assoc()['count'],
    'completed_claims' => $conn->query("SELECT COUNT(*) as count FROM claims WHERE claim_status = 'completed'")->fetch_assoc()['count'],
];

$category_stats = $conn->query("SELECT c.category_name, COUNT(i.item_id) as count FROM categories c LEFT JOIN items i ON c.category_id = i.category_id GROUP BY c.category_id, c.category_name ORDER BY count DESC");
$recent_activity = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10");
$top_users = $conn->query("SELECT u.full_name, u.student_id, COUNT(i.item_id) as item_count FROM users u JOIN items i ON u.user_id = i.user_id GROUP BY u.user_id, u.full_name, u.student_id ORDER BY item_count DESC LIMIT 5");

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
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .admin-panel { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(10, 22, 40, 0.06); border: 1px solid #f1f5f9; margin-bottom: 1.5rem; overflow: hidden; }
        .admin-panel-header { padding: 1.25rem 1.5rem; border-bottom: 2px dashed #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .admin-panel-header h5 { margin: 0; font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; color: var(--blue-900); display: flex; align-items: center; gap: 0.5rem; }
        .admin-panel-body { padding: 1.5rem; }
        .table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #475569; border-bottom: 2px solid #f1f5f9; }
        .table td { vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .table tbody tr:hover { background-color: #eff6ff; }
    </style>
</head>
<body class="sidebar-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
                <span class="topbar-title">Platform Reports</span>
            </div>
            <div class="topbar-right">
                <div class="topbar-user"><i class="fas fa-user-shield"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
            </div>
        </div>

        <div class="page-content p-4">
            <!-- Overview Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-4" style="border-top: 4px solid var(--blue);">
                        <h2 class="fw-bold mb-1" style="color: var(--blue);"><?php echo $stats['total_items']; ?></h2>
                        <div class="text-muted fw-bold small text-uppercase">Total Items</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-4" style="border-top: 4px solid var(--yellow);">
                        <h2 class="fw-bold mb-1" style="color: #b45309;"><?php echo $stats['found_items']; ?></h2>
                        <div class="text-muted fw-bold small text-uppercase">Found Items</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-4" style="border-top: 4px solid #1f9d6b;">
                        <h2 class="fw-bold mb-1" style="color: #1f9d6b;"><?php echo $stats['returned_items']; ?></h2>
                        <div class="text-muted fw-bold small text-uppercase">Returned Items</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card shadow-sm border-0 text-center p-4" style="border-top: 4px solid #7c3aed;">
                        <h2 class="fw-bold mb-1" style="color: #7c3aed;"><?php echo $stats['total_users']; ?></h2>
                        <div class="text-muted fw-bold small text-uppercase">Total Users</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Category Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="admin-panel h-100">
                        <div class="admin-panel-header">
                            <h5><i class="fas fa-chart-pie"></i> Items by Category</h5>
                        </div>
                        <div class="admin-panel-body">
                            <div style="height: 300px; position: relative;">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Users -->
                <div class="col-lg-6 mb-4">
                    <div class="admin-panel h-100">
                        <div class="admin-panel-header">
                            <h5><i class="fas fa-trophy"></i> Top Contributors</h5>
                        </div>
                        <div class="admin-panel-body p-0">
                            <table class="table table-hover mb-0">
                                <thead><tr><th class="ps-4">User</th><th class="pe-4 text-end">Items</th></tr></thead>
                                <tbody>
                                    <?php while ($user = $top_users->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($user['student_id'] ?? 'N/A'); ?></small>
                                            </td>
                                            <td class="pe-4 text-end fw-bold" style="color: var(--blue);"><?php echo $user['item_count']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="admin-panel">
                <div class="admin-panel-header">
                    <h5><i class="fas fa-history"></i> Recent Platform Activity</h5>
                </div>
                <div class="admin-panel-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th class="ps-4">Action</th><th>Description</th><th>User ID</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4"><span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span></td>
                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                    <td><?php echo $activity['user_id'] ? 'User #'.$activity['user_id'] : 'System'; ?></td>
                                    <td class="text-muted small"><?php echo date('M d, g:i A', strtotime($activity['created_at'])); ?></td>
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
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            Chart.defaults.font.family = "'Instrument Sans', sans-serif";
            const ctx = document.getElementById('categoryChart').getContext('2d');
            
            const labels = [<?php 
                $category_stats->data_seek(0);
                while($c = $category_stats->fetch_assoc()) echo "'" . addslashes($c['category_name']) . "',";
            ?>];
            const data = [<?php 
                $category_stats->data_seek(0);
                while($c = $category_stats->fetch_assoc()) echo $c['count'] . ",";
            ?>];

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#1e40af', '#eab308', '#1f9d6b', '#7c3aed', '#dc2626', '#0ea5e9'],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '60%',
                    plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 20 } } }
                }
            });
        });
    </script>
</body>
</html>