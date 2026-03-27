<?php
// Include database connection if not already included
if (!isset($pdo)) {
    include '../config.php';
}

// Get user profile picture
$stmt = $pdo->prepare("SELECT u.profile_picture, c.company_logo, c.company_name FROM users u LEFT JOIN companies c ON u.id = c.user_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();

$profileImage = $userData['profile_picture'] ?? $userData['company_logo'] ?? null;
$displayName = $userData['company_name'] ?? $_SESSION['username'] ?? 'Employer';
?>
<!-- Employer Sidebar -->
<div class="sidebar">
    <div class="sidebar-wrapper d-flex flex-column h-100">
        <a href="../index.php" class="navbar-brand">
            <img src="../images/LOGO.png" alt="WORKLINK"  style="height: 70px; width: 100%;  object-fit: cover;">
        </a>
        <hr class="mx-3 my-0" style="border-color: rgba(255, 255, 255, 0.2);">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="company-profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'company-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-building me-2"></i>Company Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="post-job.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'post-job.php' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle me-2"></i>Post Job
                </a>
            </li>
            <li class="nav-item">
                <a href="jobs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'jobs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase me-2"></i>My Jobs
                </a>
            </li>
            <li class="nav-item">
                <a href="applications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users me-2"></i>Applications
                </a>
            </li>
            <li class="nav-item">
                <a href="employee-documents.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'employee-documents.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt me-2"></i>Records & Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="verification-history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'verification-history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle me-2"></i>Verification History
                </a>
            </li>
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="messages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope me-2"></i>Messages
                </a>
            </li>
            <li class="nav-item">
                <a href="Settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'Settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gear me-2"></i>Settings
                </a>
            </li>
            <li class="nav-item">
                <a href="matching.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'matching.php' ? 'active' : ''; ?>">
                    <i class="fas fa-handshake me-2"></i>Matching Result
                </a>
            </li>
            <li class="nav-item">
                <a href="support.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'support.php' ? 'active' : ''; ?>">
                    <i class="fas fa-headset me-2"></i>Support
                </a>
            </li>
            <li class="nav-item">
                <a href="Interviews.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'Interviews.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check me-2"></i>Interviews
                </a>
            </li>
        </ul>
        <hr class="mx-3 my-3" style="border-color: rgba(255, 255, 255, 0.2);">
        <div class="dropdown mt-auto" style="padding: 0 15px 15px;">
            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if ($profileImage && file_exists('../' . $profileImage)): ?>
                    <img src="../<?php echo htmlspecialchars($profileImage); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-2x me-2"></i>
                <?php endif; ?>
                <div class="d-flex flex-column">
                    <span><?php echo htmlspecialchars($displayName); ?></span>
                    <small class="text-muted"><?php echo htmlspecialchars($userData['email'] ?? ''); ?></small>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="company-profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="../index.php"><i class="fas fa-home me-2"></i>Public Site</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign out</a></li>
            </ul>
        </div>
    </div>
</div>

<link rel="stylesheet" href="css/minimal.css">
