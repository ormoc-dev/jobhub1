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
            <img src="../images/LOGO.png" alt="WORKLINK"  style="height: 70px; width: 100%;  object-fit: cover;">
        </a>
        <hr>
        <ul class="nav flex-column">
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
        <div class="dropdown mt-auto">
            <a href="#" class="dropdown-toggle" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if ($profileImage && file_exists('../' . $profileImage)): ?>
                    <img src="../<?php echo htmlspecialchars($profileImage); ?>" alt="Profile">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size: 32px; margin-right: 0.75rem;"></i>
                <?php endif; ?>
                <div>
                    <span><?php echo htmlspecialchars($displayName); ?></span>
                    <small style="color: #94a3b8; display: block; font-size: 0.75rem;"><?php echo htmlspecialchars($userData['email'] ?? ''); ?></small>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="dropdownUser1">
                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sign out</a></li>
            </ul>
        </div>
    </div>
</div>

<link href="css/minimal.css" rel="stylesheet">
