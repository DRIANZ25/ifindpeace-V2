<?php $current_page = basename($_SERVER['PHP_SELF']); ?>
<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <a class="sidebar-brand" href="index.php" style="padding: 1.5rem 1.25rem; gap: 1rem;">
        <div class="sidebar-brand-icon" style="width: 48px; height: 48px; background: rgba(255,255,255,0.25); border-radius: 12px; font-size: 1.3rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); border: 2px solid rgba(255,105,180,0.3);">
            <i class="fas fa-search-location"></i>
        </div>
        <div class="sidebar-brand-text" style="font-weight: 800; font-size: 1.25rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);">
            iFind<span style="color: #ff69b4; text-shadow: 0 0 10px rgba(255,105,180,0.5);">Peace</span>
        </div>
    </a>

    <!-- Nav -->
    <ul class="sidebar-nav">
        <li class="sidebar-nav-label">Navigation</li>
        <li>
            <a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-home"></i></span>
                Home
            </a>
        </li>
        <li>
            <a href="<?php echo isAdmin() ? 'admin/dashboard.php' : 'dashboard.php'; ?>" class="<?php echo $current_page === 'dashboard.php' || $current_page === 'admin/dashboard.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                Dashboard
            </a>
        </li>
        <li>
            <a href="items.php" class="<?php echo $current_page === 'items.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-search"></i></span>
                Browse Items
            </a>
        </li>
        <li class="sidebar-nav-label">My Account</li>
        <li>
            <a href="report.php" class="<?php echo $current_page === 'report.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-plus-circle"></i></span>
                Report Item
            </a>
        </li>
        <li>
            <a href="my-items.php" class="<?php echo $current_page === 'my-items.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-box"></i></span>
                My Items
            </a>
        </li>
        <li>
            <a href="my-claims.php" class="<?php echo $current_page === 'my-claims.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-hand-paper"></i></span>
                My Claims
            </a>
        </li>
        <li>
            <a href="notifications.php" class="<?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-bell"></i></span>
                Notifications
                <?php 
                $unread = getUnreadNotificationCount($_SESSION['user_id']);
                if ($unread > 0): ?>
                    <span class="ms-auto badge" style="background:linear-gradient(135deg, #ff69b4 0%, #ff00ff 100%);color:white;font-size:0.7rem;padding:3px 10px;border-radius:20px;font-weight:700;box-shadow:0 2px 8px rgba(255,105,180,0.4);"><?php echo $unread; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                <span class="nav-icon"><i class="fas fa-user"></i></span>
                Profile
            </a>
        </li>
    </ul>

    <!-- Footer -->
    <div class="sidebar-footer" style="padding: 1.25rem; border-top: 1px solid rgba(255,255,255,0.15); background: rgba(0,0,0,0.15); gap: 1rem;">
        <div class="sidebar-footer-avatar" style="width: 42px; height: 42px; background: linear-gradient(135deg, #ff69b4 0%, #ff00ff 100%); border: 2px solid rgba(255,255,255,0.3); box-shadow: 0 4px 12px rgba(0,0,0,0.25); font-size: 1.15rem;">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="sidebar-footer-info">
            <div class="sidebar-footer-name" style="font-weight: 700; font-size: 0.9rem; text-shadow: 0 1px 2px rgba(0,0,0,0.2);"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
            <div class="sidebar-footer-role" style="color: rgba(255,255,255,0.8); font-weight: 500; font-size: 0.75rem;">Student</div>
        </div>
        <a href="logout.php" class="sidebar-footer-logout" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
