<?php
// Get pending counts for sidebar
$pending_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
?>

<!-- Admin Sidebar -->
<div class="sidebar">
    <div class="sidebar-wrapper">
         <a href="../index.php" class="navbar-brand">
            <img src="../images/LOGO.png" alt="WORKLINK"  style="height: 70px; width: 100%;  object-fit: cover;">
        </a>
        <hr class="mx-3 my-0" style="border-color: #495057;">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt me-2"></i>File Maintenance
                </a>
            </li>
            <li class="nav-item">
                <a href="company.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'company.php' ? 'active' : ''; ?>">
                    <i class="fas fa-city me-2"></i>Company Management
                </a>
            </li>
            <li class="nav-item">
                <a href="jobs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'jobs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase me-2"></i>Jobs
                </a>
            </li>
            <li class="nav-item">
                <a href="Hiring.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'Hiring.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog me-2"></i>Applications & Hiring
                </a>
            </li>
            <li class="nav-item">
                <a href="skills.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'skills.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tools me-2"></i>Skills Management
                </a>
            </li>
            <li class="nav-item">
                <a href="system.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'system.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cogs me-2"></i>System Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="compliance.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'compliance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-balance-scale me-2"></i>Compliance & Audit
                </a>
            </li>
            <li class="nav-item">
                <a href="support.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'support.php' ? 'active' : ''; ?>">
                    <i class="fas fa-headset me-2"></i>Support & Maintenance
                </a>
            </li>
            <li class="nav-item">
                <a href="Content.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'Content.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn me-2"></i>Content Management
                </a>
            </li>
            <li class="nav-item">
                <a href="matching.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'matching.php' ? 'active' : ''; ?>">
                    <i class="fas fa-brain me-2"></i>AI Matching & System Logic
                </a>
            </li>
            <li class="nav-item">
                <a href="messages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope me-2"></i>Messages
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
        </ul>
        <hr class="mx-3 my-3" style="border-color: #495057;">
        <div class="dropdown" style="padding: 0 15px 15px;">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="peso.jpg" alt="Admin" class="me-2" style="height: 32px; width: 32px; border-radius: 50%; object-fit: cover;">
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="users.php"><i class="fas fa-users me-2"></i>Users<?php if ($pending_users > 0): ?><span class="badge bg-warning text-dark ms-2"><?php echo $pending_users; ?></span><?php endif; ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign out</a></li>
            </ul>
        </div>
    </div>
</div>

<style>
.sidebar {
    background: #0D6EFD;
    border-right: 1px solid rgba(148, 163, 184, 0.22);
    box-shadow: inset -1px 0 0 rgba(15, 23, 42, 0.05);
}

.sidebar-wrapper {
    display: flex;
    flex-direction: column;
    height: 100%;   
    padding: 0;
    overflow: hidden;
}

.sidebar-wrapper ul {
    flex: 1;
    overflow-y: auto;
    margin: 0;
    padding: 0;
}

.sidebar .nav-item {
    margin: 0;
    padding: 0;
}

.sidebar .nav-link {
    margin: 2px 8px !important;
    padding: 12px 20px !important;
    color: #eef4ff !important;
    border-radius: 10px;
    transition: background 0.2s ease, color 0.2s ease;
    background: transparent !important;
    border: none !important;
}

.sidebar .nav-link:hover {
    background: rgba(255, 255, 255, 0.16);
    color: #ffffff;
}

.sidebar .nav-link.active {
    background: linear-gradient(135deg, #0b5ed7 0%, #0d6efd 55%, #3d8bfd 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 6px 14px rgba(13, 110, 253, 0.35) !important;
    border: none !important;
}

.sidebar .nav-link.active::before,
.sidebar .nav-link.active::after {
    display: none !important;
}

/* Override any Bootstrap nav-pills white background */
.sidebar .nav-pills .nav-link.active,
.sidebar .nav-item .nav-link.active {
    background: linear-gradient(135deg, #0b5ed7 0%, #0d6efd 55%, #3d8bfd 100%) !important;
    color: #ffffff !important;
    border: none !important;
}
</style>
