<?php
// Include database connection if not already included
if (!isset($pdo)) {
    include '../config.php';
}

// Get user profile info for sidebar display
$stmt = $pdo->prepare("SELECT u.profile_picture, ep.profile_picture as emp_profile_picture, ep.first_name, ep.last_name, u.email FROM users u LEFT JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch();

$profileImage = $userData['profile_picture'] ?? $userData['emp_profile_picture'] ?? null;
$firstName = $userData['first_name'] ?? '';
$displayName = $firstName !== '' ? ('Hi ' . $firstName) : ('Hi ' . ($_SESSION['username'] ?? 'Employee'));
?>
<!-- Employee Sidebar -->
<div class="sidebar">
    <div class="sidebar-wrapper d-flex flex-column h-100">
        <a href="../index.php" class="navbar-brand">
            <img src="../worklink.jpg" alt="WORKLINK" class="logo-img me-2" style="height: 30px; width: 30px; border-radius: 50%; object-fit: cover;">
            WORKLINK
        </a>
        <hr class="mx-3 my-0" style="border-color: rgba(255, 255, 255, 0.2);">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user me-2"></i>My Profile
                </a>
            </li>
            <li class="nav-item">
                <a href="../jobs.php" class="nav-link">
                    <i class="fas fa-search me-2"></i>Browse All Jobs
                </a>
            </li>
            <li class="nav-item">
                <a href="applications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt me-2"></i>My Applications
                </a>
            </li>
            <li class="nav-item">
                <a href="saved-jobs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'saved-jobs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-heart me-2"></i>Saved Jobs
                </a>
            </li>
            <li class="nav-item">
                <a href="interviews.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'interviews.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check me-2"></i>Interviews
                </a>
            </li>
            <li class="nav-item">
                <a href="careertools.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'careertools.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tools me-2"></i>Career Tools
                </a>
            </li>
            <li class="nav-item">
                <a href="verification-history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'verification-history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle me-2"></i>Verification History
                </a>
            </li>
            <li class="nav-item">
                <a href="messages.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope me-2"></i>Messages
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
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign out</a></li>
            </ul>
        </div>
    </div>
</div>

<style>
.sidebar {
    background: linear-gradient(180deg, #4c1d95 0%, #2d1b69 100%) !important;
    border-right: 1px solid rgba(139, 92, 246, 0.3);
    box-shadow: 2px 0 15px rgba(124, 58, 237, 0.2);
}

.sidebar-wrapper {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

.sidebar-wrapper ul {
    flex: 1;
    overflow-y: auto;
    margin: 0;
    padding: 8px;
}

.sidebar .navbar-brand {
    color: #faf5ff !important;
    padding: 20px 15px;
    font-weight: 700;
    font-size: 1.2rem;
    letter-spacing: 0.5px;
}

.sidebar .navbar-brand:hover {
    color: #c4b5fd !important;
}

.sidebar .navbar-brand .small {
    font-size: 0.85rem;
    font-weight: 400;
    margin-top: 4px;
    color: #c4b5fd !important;
}

.sidebar hr {
    border-color: rgba(167, 139, 250, 0.3) !important;
}

.sidebar .nav-link {
    color: #e9d5ff !important;
    margin: 4px 0;
    border-radius: 10px;
    padding: 12px 16px;
    transition: all 0.3s ease;
    font-weight: 500;
    border-left: 3px solid transparent;
    position: relative;
}

.sidebar .nav-link i {
    width: 20px;
    text-align: center;
    color: #a78bfa;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    background: rgba(167, 139, 250, 0.2) !important;
    color: #ddd6fe !important;
    border-left-color: #a78bfa;
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(167, 139, 250, 0.15);
}

.sidebar .nav-link:hover i {
    color: #c4b5fd;
}

.sidebar .nav-link.active {
    background: linear-gradient(90deg, rgba(167, 139, 250, 0.3) 0%, rgba(139, 92, 246, 0.15) 100%) !important;
    color: #faf5ff !important;
    border-left-color: #c4b5fd;
    box-shadow: 0 4px 15px rgba(167, 139, 250, 0.3);
    font-weight: 600;
}

.sidebar .nav-link.active i {
    color: #faf5ff;
}

.sidebar .text-muted {
    color: #c4b5fd !important;
}

.sidebar .dropdown-toggle {
    color: #faf5ff !important;
    padding: 12px 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.sidebar .dropdown-toggle:hover {
    background: rgba(167, 139, 250, 0.15) !important;
    color: #ddd6fe !important;
}

.sidebar .dropdown-toggle .text-muted {
    color: #a78bfa !important;
    font-size: 0.8rem;
}

.sidebar .dropdown-menu-dark {
    background: #4c1d95 !important;
    border: 1px solid rgba(167, 139, 250, 0.3);
    box-shadow: 0 10px 30px rgba(124, 58, 237, 0.4);
}

.sidebar .dropdown-item {
    color: #e9d5ff !important;
    padding: 10px 16px;
    transition: all 0.2s ease;
}

.sidebar .dropdown-item:hover {
    background: rgba(167, 139, 250, 0.2) !important;
    color: #faf5ff !important;
}

.sidebar .dropdown-item i {
    color: #a78bfa;
    width: 20px;
}

.sidebar .dropdown-item:hover i {
    color: #c4b5fd;
}

/* Custom scrollbar for sidebar */
.sidebar-wrapper ul::-webkit-scrollbar {
    width: 6px;
}

.sidebar-wrapper ul::-webkit-scrollbar-track {
    background: rgba(45, 27, 105, 0.5);
    border-radius: 10px;
}

.sidebar-wrapper ul::-webkit-scrollbar-thumb {
    background: rgba(167, 139, 250, 0.6);
    border-radius: 10px;
}

.sidebar-wrapper ul::-webkit-scrollbar-thumb:hover {
    background: rgba(196, 181, 253, 0.8);
}
</style>
