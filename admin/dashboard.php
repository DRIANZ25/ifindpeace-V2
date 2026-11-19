<?php
require_once '../config/config.php';
requireAdmin();

// FIXED: Set timezone to Philippines so the greeting is accurate
date_default_timezone_set('Asia/Manila');

$conn = getDBConnection();

// Get platform statistics (Expanded for better analytics)
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM items) as total_items,
                (SELECT COUNT(*) FROM users WHERE user_type = 'user') as user_count,
                (SELECT COUNT(*) FROM items WHERE status IN ('returned', 'claimed')) as claimed_count,
                (SELECT COUNT(*) FROM claims) as total_claims,
                (SELECT COUNT(*) FROM items WHERE status = 'pending') as pending_items,
                (SELECT COUNT(*) FROM claims WHERE claim_status = 'pending') as pending_claims,
                (SELECT COUNT(*) FROM categories) as total_categories";
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
        /* ============================================================
           ADMIN DASHBOARD SPECIFIC STYLES (Blue / Yellow Theme)
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
            --purple: #7c3aed;
        }

        body {
            background-color: var(--paper);
            font-family: 'Instrument Sans', system-ui, sans-serif;
        }

        /* Override topbar for admin */
        .topbar {
            background: #ffffff !important;
            border-bottom: 2px solid var(--sky);
        }
        .topbar-title { color: var(--blue-900); font-weight: 800; }
        .topbar-user { background: var(--sky); color: var(--blue-900); border: 1px solid #dbeafe; }

        /* ── Welcome Banner ── */
        .admin-welcome {
            position: relative;
            overflow: hidden;
            padding: 2.5rem 2rem;
            border-radius: 24px;
            background: radial-gradient(800px 400px at 90% 10%, rgba(250, 204, 21, 0.15), transparent 60%),
                        linear-gradient(135deg, var(--blue-950) 0%, var(--blue-900) 100%);
            color: #fff;
            box-shadow: 0 20px 40px rgba(10, 22, 40, 0.15);
            margin-bottom: 2rem;
        }
        .admin-welcome::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.05;
            background-image: linear-gradient(#fff 1px, transparent 1px), linear-gradient(90deg, #fff 1px, transparent 1px);
            background-size: 30px 30px;
            mask-image: linear-gradient(to bottom, #000 20%, transparent 100%);
            -webkit-mask-image: linear-gradient(to bottom, #000 20%, transparent 100%);
        }
        .welcome-text { position: relative; z-index: 2; }
        .welcome-text h2 { font-family: 'Bricolage Grotesque', sans-serif; font-weight: 800; font-size: 2rem; margin-bottom: 0.5rem; }
        .welcome-text h2 span { color: var(--yellow-hot); }
        .welcome-text p { color: rgba(255,255,255,0.8); margin: 0; font-size: 0.95rem; }
        .welcome-icon-bg {
            position: absolute;
            right: 2rem;
            bottom: -20px;
            font-size: 10rem;
            color: rgba(250, 204, 21, 0.08);
            transform: rotate(-15deg);
            z-index: 1;
            pointer-events: none;
        }

        /* ── Stat Cards ── */
        .admin-stat-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(10, 22, 40, 0.06);
            border: 1px solid #f1f5f9;
            transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease);
            height: 100%;
            z-index: 1;
        }
        .admin-stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(10, 22, 40, 0.12);
        }
        .admin-stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: var(--card-color, var(--blue));
            border-radius: 20px 20px 0 0;
        }
        .admin-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 1.4rem;
            margin-bottom: 1rem;
            background: var(--card-bg, var(--sky));
            color: var(--card-color, var(--blue));
            transition: transform 0.3s;
        }
        .admin-stat-card:hover .admin-stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        .admin-stat-number {
            font-family: 'Bricolage Grotesque', sans-serif;
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--ink);
            line-height: 1;
            margin-bottom: 0.3rem;
        }
        .admin-stat-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .admin-stat-trend {
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.6rem;
            border-radius: 50px;
            background: #f1f5f9;
            color: var(--muted);
        }

        /* ── Panel Base ── */
        .admin-panel {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(10, 22, 40, 0.06);
            border: 1px solid #f1f5f9;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .admin-panel-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 2px dashed #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-panel-header h5 {
            margin: 0;
            font-family: 'Bricolage Grotesque', sans-serif;
            font-weight: 800;
            color: var(--blue-900);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .admin-panel-header i { color: var(--yellow); }
        .admin-panel-body { padding: 1.5rem; }

        /* ── Charts ── */
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }

        /* ── Quick Actions ── */
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.2rem;
            border-radius: 16px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s var(--ease);
            position: relative;
            overflow: hidden;
        }
        .quick-action-btn:hover {
            color: #fff;
            transform: translateY(-3px);
        }
        .quick-action-btn::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }
        .quick-action-btn:hover::after { left: 100%; }
        .qa-icon {
            width: 40px; height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: grid; place-items: center;
            font-size: 1.1rem;
        }
        .qa-blue { background: linear-gradient(135deg, var(--blue), #1e3a8a); box-shadow: 0 8px 20px rgba(30,64,175,0.3); }
        .qa-yellow { background: linear-gradient(135deg, var(--yellow), #b45309); box-shadow: 0 8px 20px rgba(234,179,8,0.3); }
        .qa-purple { background: linear-gradient(135deg, var(--purple), #4c1d95); box-shadow: 0 8px 20px rgba(124,58,237,0.3); }

        /* ── Table ── */
        .admin-table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            border-bottom: 2px solid #f1f5f9;
            padding: 1rem 0.5rem;
        }
        .admin-table td {
            padding: 1rem 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }
        .admin-table tbody tr {
            transition: background 0.2s;
        }
        .admin-table tbody tr:hover {
            background: var(--sky);
        }
        .user-avatar-sm {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--yellow));
            color: #fff;
            display: grid; place-items: center;
            font-weight: 800;
            font-size: 0.9rem;
        }
        .status-badge {
            padding: 0.35rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #fee2e2; color: #991b1b; }

        /* ── Animations ── */
        .fade-up { opacity: 0; transform: translateY(30px); animation: fadeUp 0.8s var(--ease) forwards; }
        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }
        
        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        
        .floating {
            animation: float 4s ease-in-out infinite;
        }
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
                <span class="topbar-title">Admin Dashboard</span>
            </div>
            <div class="topbar-right">
                <div class="topbar-user">
                    <i class="fas fa-user-shield"></i>
                    <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </div>
            </div>
        </div>

        <div class="page-content">
            
            <!-- Welcome Banner -->
            <div class="admin-welcome fade-up">
                <i class="fas fa-chart-pie welcome-icon-bg"></i>
                <div class="welcome-text">
                    <!-- FIXED: Added an ID so JavaScript can update it dynamically -->
                    <h2><span id="greetingText">Good day</span>, <span>Admin</span>!</h2>
                    <p>Here is the latest overview of the iFindPeace platform. The system is running smoothly.</p>
                </div>
            </div>

            <!-- Admin Reminder -->
            <div class="alert alert-warning border-warning shadow-sm fade-up delay-1 d-flex align-items-center gap-3 mb-4" role="alert" style="background: #fffbeb; color: #713f12; border-radius: 16px; padding: 1.2rem;">
                <div style="background: var(--yellow); color: #713f12; width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; flex-shrink: 0; font-size: 1.2rem;">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div>
                    <strong class="d-block mb-1" style="font-family: 'Bricolage Grotesque';">Organization Office Drop-off Point</strong>
                    <span class="small">Please ensure all found items are surrendered to the Organization Office. Only mark items as "Returned" after physically handing them over to the verified owner.</span>
                </div>
            </div>

            <!-- Statistics Row -->
            <div class="row g-4 mb-4">
                <!-- Card 1 -->
                <div class="col-md-6 col-xl-3">
                    <div class="admin-stat-card fade-up delay-2" style="--card-color: var(--blue); --card-bg: var(--sky);">
                        <div class="admin-stat-icon floating"><i class="fas fa-box"></i></div>
                        <div class="admin-stat-number" data-count="<?php echo $stats['total_items']; ?>">0</div>
                        <div class="admin-stat-label">Total Items</div>
                        <div class="admin-stat-trend"><i class="fas fa-layer-group"></i> <?php echo $stats['total_categories']; ?> Categories</div>
                    </div>
                </div>
                <!-- Card 2 -->
                <div class="col-md-6 col-xl-3">
                    <div class="admin-stat-card fade-up delay-3" style="--card-color: var(--yellow); --card-bg: #fef9c3;">
                        <div class="admin-stat-icon floating" style="animation-delay: 0.5s;"><i class="fas fa-users"></i></div>
                        <div class="admin-stat-number" data-count="<?php echo $stats['user_count']; ?>">0</div>
                        <div class="admin-stat-label">Registered Users</div>
                        <div class="admin-stat-trend"><i class="fas fa-user-plus"></i> Active community</div>
                    </div>
                </div>
                <!-- Card 3 -->
                <div class="col-md-6 col-xl-3">
                    <div class="admin-stat-card fade-up delay-4" style="--card-color: var(--success); --card-bg: #dcfce7;">
                        <div class="admin-stat-icon floating" style="animation-delay: 1s;"><i class="fas fa-check-circle"></i></div>
                        <div class="admin-stat-number" data-count="<?php echo $stats['claimed_count']; ?>">0</div>
                        <div class="admin-stat-label">Items Returned</div>
                        <div class="admin-stat-trend" style="color: var(--success);"><i class="fas fa-arrow-up"></i> Successfully claimed</div>
                    </div>
                </div>
                <!-- Card 4 -->
                <div class="col-md-6 col-xl-3">
                    <div class="admin-stat-card fade-up delay-5" style="--card-color: var(--purple); --card-bg: #ede9fe;">
                        <div class="admin-stat-icon floating" style="animation-delay: 1.5s;"><i class="fas fa-hand-paper"></i></div>
                        <div class="admin-stat-number" data-count="<?php echo $stats['total_claims']; ?>">0</div>
                        <div class="admin-stat-label">Total Claims</div>
                        <div class="admin-stat-trend" style="color: var(--purple);"><i class="fas fa-clock"></i> <?php echo $stats['pending_claims']; ?> pending review</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="admin-panel fade-up delay-3">
                        <div class="admin-panel-header">
                            <h5><i class="fas fa-chart-bar"></i> Platform Overview</h5>
                            <small class="text-muted">Items vs Claims vs Users</small>
                        </div>
                        <div class="admin-panel-body">
                            <div class="chart-container">
                                <canvas id="overviewChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="admin-panel fade-up delay-4">
                        <div class="admin-panel-header">
                            <h5><i class="fas fa-chart-pie"></i> Claims Distribution</h5>
                        </div>
                        <div class="admin-panel-body d-flex justify-content-center align-items-center">
                            <div class="chart-container" style="height: 240px; width: 240px;">
                                <canvas id="claimsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Recent Users & Quick Actions -->
            <div class="row g-4">
                <!-- Recent Users -->
                <div class="col-lg-8">
                    <div class="admin-panel fade-up delay-4 h-100">
                        <div class="admin-panel-header">
                            <h5><i class="fas fa-users"></i> Recent Registrations</h5>
                            <a href="users.php" class="btn btn-sm btn-outline-primary" style="border-radius: 50px;">View All</a>
                        </div>
                        <div class="admin-panel-body p-0 table-responsive">
                            <?php if ($recent_users->num_rows > 0): ?>
                                <table class="table admin-table mb-0">
                                    <thead>
                                        <tr>
                                            <th class="ps-4">User</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="user-avatar-sm"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></div>
                                                        <div>
                                                            <div class="fw-bold" style="color: var(--ink);"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                            <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $user['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                        <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> <?php echo ucfirst($user['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-muted small">
                                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-users-slash fa-3x mb-3" style="color: #cbd5e1;"></i>
                                    <p>No users found.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="admin-panel fade-up delay-5 h-100">
                        <div class="admin-panel-header">
                            <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                        </div>
                        <div class="admin-panel-body d-flex flex-column gap-3">
                            <a href="items.php" class="quick-action-btn qa-blue">
                                <div class="qa-icon"><i class="fas fa-box"></i></div>
                                <div>
                                    <div class="fw-bold">Manage Items</div>
                                    <div class="small" style="opacity: 0.8; font-weight: 500;"><?php echo $stats['pending_items']; ?> pending approval</div>
                                </div>
                                <i class="fas fa-chevron-right ms-auto" style="opacity: 0.5;"></i>
                            </a>
                            
                            <a href="claims.php" class="quick-action-btn qa-yellow">
                                <div class="qa-icon"><i class="fas fa-hand-paper"></i></div>
                                <div>
                                    <div class="fw-bold">Manage Claims</div>
                                    <div class="small" style="opacity: 0.8; font-weight: 500;"><?php echo $stats['pending_claims']; ?> pending review</div>
                                </div>
                                <i class="fas fa-chevron-right ms-auto" style="opacity: 0.5;"></i>
                            </a>

                            <a href="users.php" class="quick-action-btn qa-purple">
                                <div class="qa-icon"><i class="fas fa-users"></i></div>
                                <div>
                                    <div class="fw-bold">Manage Users</div>
                                    <div class="small" style="opacity: 0.8; font-weight: 500;">View all registered accounts</div>
                                </div>
                                <i class="fas fa-chevron-right ms-auto" style="opacity: 0.5;"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- end page-content -->
    </div><!-- end main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    
    <script>
        // ============================================================
        // 1. DYNAMIC TIME-BASED GREETING (Fixed)
        // ============================================================
        document.addEventListener("DOMContentLoaded", () => {
            const hour = new Date().getHours();
            let greeting = "Good morning";
            
            if (hour >= 12 && hour < 18) {
                greeting = "Good afternoon";
            } else if (hour >= 18 || hour < 5) {
                greeting = "Good evening";
            }
            
            const greetingEl = document.getElementById('greetingText');
            if (greetingEl) {
                greetingEl.textContent = greeting;
            }
        });

        // ============================================================
        // 2. COUNT-UP ANIMATION FOR STAT CARDS
        // ============================================================
        document.addEventListener("DOMContentLoaded", () => {
            const counters = document.querySelectorAll('.admin-stat-number');
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
        // 3. CHART.JS INITIALIZATION
        // ============================================================
        document.addEventListener("DOMContentLoaded", () => {
            Chart.defaults.font.family = "'Instrument Sans', sans-serif";
            Chart.defaults.color = '#64748b';
            
            // --- Chart 1: Overview Bar Chart ---
            const ctx1 = document.getElementById('overviewChart').getContext('2d');
            
            let gradientBlue = ctx1.createLinearGradient(0, 0, 0, 400);
            gradientBlue.addColorStop(0, 'rgba(30, 64, 175, 0.9)');
            gradientBlue.addColorStop(1, 'rgba(30, 64, 175, 0.4)');

            let gradientYellow = ctx1.createLinearGradient(0, 0, 0, 400);
            gradientYellow.addColorStop(0, 'rgba(250, 204, 21, 0.9)');
            gradientYellow.addColorStop(1, 'rgba(250, 204, 21, 0.4)');

            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: ['Total Items', 'Total Users', 'Total Claims', 'Items Returned'],
                    datasets: [{
                        label: 'Platform Data',
                        data: [
                            <?php echo $stats['total_items']; ?>, 
                            <?php echo $stats['user_count']; ?>, 
                            <?php echo $stats['total_claims']; ?>, 
                            <?php echo $stats['claimed_count']; ?>
                        ],
                        backgroundColor: [
                            gradientBlue,
                            'rgba(124, 58, 237, 0.8)',
                            gradientYellow,
                            'rgba(31, 157, 107, 0.8)'
                        ],
                        borderColor: [
                            '#1e40af',
                            '#7c3aed',
                            '#eab308',
                            '#1f9d6b'
                        ],
                        borderWidth: 1,
                        borderRadius: 8,
                        borderSkipped: false,
                        barThickness: 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 2000, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0a1628',
                            titleColor: '#facc15',
                            bodyColor: '#fff',
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9', drawBorder: false }, ticks: { stepSize: 1, precision: 0 } },
                        x: { grid: { display: false, drawBorder: false } }
                    }
                }
            });

            // --- Chart 2: Claims Doughnut Chart ---
            const ctx2 = document.getElementById('claimsChart').getContext('2d');
            const pendingClaims = <?php echo $stats['pending_claims']; ?>;
            const resolvedClaims = <?php echo $stats['total_claims'] - $stats['pending_claims']; ?>;

            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Resolved'],
                    datasets: [{
                        data: [pendingClaims, resolvedClaims],
                        backgroundColor: ['#eab308', '#1f9d6b'],
                        borderWidth: 0,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    animation: { animateScale: true, animateRotate: true },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { weight: '600' } } },
                        tooltip: { backgroundColor: '#0a1628', titleColor: '#facc15', bodyColor: '#fff', padding: 12, cornerRadius: 8 }
                    }
                }
            });
        });
    </script>
</body>
</html>