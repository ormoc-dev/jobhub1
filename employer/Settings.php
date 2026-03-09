<?php
include '../config.php';
requireRole('employer');

$passwordError = '';
$passwordSuccess = '';
$contactError = '';
$contactSuccess = '';
$twoFactorError = '';
$twoFactorSuccess = '';
$notificationError = '';
$notificationSuccess = '';
$teamError = '';
$teamSuccess = '';

// Update current session activity
if (isset($_SESSION['user_id'])) {
    updateSessionActivity($_SESSION['user_id']);
}

// Note: 2FA columns (two_factor_enabled, two_factor_secret) are defined in sql/all_additional_tables.sql

// Get current 2FA status
$stmt = $pdo->prepare("SELECT two_factor_enabled, two_factor_secret FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user2FA = $stmt->fetch();
$twoFactorEnabled = $user2FA['two_factor_enabled'] ?? 0;
$twoFactorSecret = $user2FA['two_factor_secret'] ?? null;

// Handle 2FA toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_2fa'])) {
    $enable = isset($_POST['enable_2fa']) && $_POST['enable_2fa'] == '1';
    
    if ($enable && empty($twoFactorSecret)) {
        $twoFactorError = 'Please configure 2FA first before enabling it.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
            $stmt->execute([$enable ? 1 : 0, $_SESSION['user_id']]);
            $twoFactorEnabled = $enable ? 1 : 0;
            $twoFactorSuccess = $enable ? 'Two-factor authentication enabled successfully!' : 'Two-factor authentication disabled successfully!';
        } catch (Exception $e) {
            $twoFactorError = 'Error updating 2FA status: ' . $e->getMessage();
        }
    }
}

// Handle 2FA configuration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['configure_2fa'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate') {
        // Generate new 2FA secret
        $secret = generate2FASecret();
        try {
            $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
            $stmt->execute([$secret, $_SESSION['user_id']]);
            $twoFactorSecret = $secret;
            $twoFactorSuccess = '2FA secret generated. Please scan the QR code with your authenticator app.';
        } catch (Exception $e) {
            $twoFactorError = 'Error generating 2FA secret: ' . $e->getMessage();
        }
    } elseif ($action === 'verify') {
        $code = sanitizeInput($_POST['verification_code'] ?? '');
        if (empty($code)) {
            $twoFactorError = 'Please enter the verification code.';
        } elseif (verify2FACode($twoFactorSecret, $code)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 1 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $twoFactorEnabled = 1;
                $twoFactorSuccess = '2FA verified and enabled successfully!';
            } catch (Exception $e) {
                $twoFactorError = 'Error enabling 2FA: ' . $e->getMessage();
            }
        } else {
            $twoFactorError = 'Invalid verification code. Please try again.';
        }
    } elseif ($action === 'disable') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $twoFactorEnabled = 0;
            $twoFactorSecret = null;
            $twoFactorSuccess = '2FA disabled successfully!';
        } catch (Exception $e) {
            $twoFactorError = 'Error disabling 2FA: ' . $e->getMessage();
        }
    }
}

// Note: Notification columns (email_notifications, system_alerts) and table company_team_members
// are defined in sql/all_additional_tables.sql

// Get current user and company info
$stmt = $pdo->prepare("SELECT u.email, u.email_notifications, u.system_alerts, c.contact_number FROM users u 
                       LEFT JOIN companies c ON u.id = c.user_id 
                       WHERE u.id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentInfo = $stmt->fetch();
$currentEmail = $currentInfo['email'] ?? '';
$currentContactNumber = $currentInfo['contact_number'] ?? '';
$emailNotifications = $currentInfo['email_notifications'] ?? 1;
$systemAlerts = $currentInfo['system_alerts'] ?? 1;

// Handle contact info update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_contact_info'])) {
    $newEmail = sanitizeInput($_POST['email'] ?? '');
    $newContactNumber = sanitizeInput($_POST['contact_number'] ?? '');
    
    // Validation
    if (empty($newEmail)) {
        $contactError = 'Email is required.';
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $contactError = 'Please enter a valid email address.';
    } elseif (empty($newContactNumber)) {
        $contactError = 'Contact number is required.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if email already exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$newEmail, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                throw new Exception('Email address is already in use by another account.');
            }
            
            // Update email in users table
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$newEmail, $_SESSION['user_id']]);
            
            // Update contact number in companies table
            $stmt = $pdo->prepare("UPDATE companies SET contact_number = ?, contact_email = ? WHERE user_id = ?");
            $stmt->execute([$newContactNumber, $newEmail, $_SESSION['user_id']]);
            
            // Update session email
            $_SESSION['email'] = $newEmail;
            
            $pdo->commit();
            $contactSuccess = 'Contact information updated successfully!';
            
            // Refresh current info
            $currentEmail = $newEmail;
            $currentContactNumber = $newContactNumber;
        } catch (Exception $e) {
            $pdo->rollBack();
            $contactError = 'Error updating contact information: ' . $e->getMessage();
        }
    }
}

// Handle notification settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notifications'])) {
    $emailNotif = isset($_POST['email_notifications']) ? 1 : 0;
    $systemAlert = isset($_POST['system_alerts']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET email_notifications = ?, system_alerts = ? WHERE id = ?");
        $stmt->execute([$emailNotif, $systemAlert, $_SESSION['user_id']]);
        
        $emailNotifications = $emailNotif;
        $systemAlerts = $systemAlert;
        
        $notificationSuccess = 'Notification settings updated successfully!';
    } catch (Exception $e) {
        $notificationError = 'Error updating notification settings: ' . $e->getMessage();
    }
}

// Subscription / Billing info
$currentSubscription = null;
$billingHistory = [];
$subscriptionStatus = 'No Active Subscription';
$statusBadge = 'bg-secondary';
try {
    $currentSubscription = getUserSubscription($_SESSION['user_id']);
    if ($currentSubscription) {
        $endDate = strtotime($currentSubscription['end_date']);
        $now = time();
        if ($endDate > $now && $currentSubscription['status'] == 'active' && $currentSubscription['payment_status'] == 'paid') {
            $subscriptionStatus = 'Active';
            $statusBadge = 'bg-success';
        } else {
            $subscriptionStatus = 'Expired';
            $statusBadge = 'bg-danger';
        }
    }
    
    $historyStmt = $pdo->prepare("
        SELECT us.*, sp.plan_name, sp.plan_type, sp.price
        FROM user_subscriptions us
        JOIN subscription_plans sp ON us.plan_id = sp.id
        WHERE us.user_id = ?
        ORDER BY us.created_at DESC
    ");
    $historyStmt->execute([$_SESSION['user_id']]);
    $billingHistory = $historyStmt->fetchAll();
} catch (Exception $e) {
    $currentSubscription = null;
    $billingHistory = [];
}

// Handle add team member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_team_member'])) {
    $memberName = sanitizeInput($_POST['member_name'] ?? '');
    $memberEmail = sanitizeInput($_POST['member_email'] ?? '');
    $memberRole = sanitizeInput($_POST['member_role'] ?? '');
    
    if (empty($memberName) || empty($memberEmail) || empty($memberRole)) {
        $teamError = 'Please fill in all fields for the team member.';
    } elseif (!filter_var($memberEmail, FILTER_VALIDATE_EMAIL)) {
        $teamError = 'Please enter a valid email address for the team member.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO company_team_members (employer_id, full_name, email, role_title) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $memberName, $memberEmail, $memberRole]);
            $teamSuccess = 'Team member added successfully!';
            $_POST = [];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $teamError = 'This email is already added to your team.';
            } else {
                $teamError = 'Error adding team member: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            $teamError = 'Error adding team member: ' . $e->getMessage();
        }
    }
}

// Fetch team members
$teamMembers = [];
try {
    $stmt = $pdo->prepare("SELECT full_name, email, role_title, status, created_at 
                           FROM company_team_members 
                           WHERE employer_id = ? 
                           ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $teamMembers = $stmt->fetchAll();
} catch (Exception $e) {
    $teamMembers = [];
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = 'Please fill in all password fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'New password and confirm password do not match.';
    } elseif (strlen($newPassword) < 8) {
        $passwordError = 'New password must be at least 8 characters long.';
    } else {
        try {
            // Get current user's password from database
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($currentPassword, $user['password'])) {
                // Current password is correct, update to new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                
                $passwordSuccess = 'Password updated successfully!';
                // Clear form fields by resetting POST data
                $_POST = [];
            } else {
                $passwordError = 'Current password is incorrect.';
            }
        } catch (Exception $e) {
            $passwordError = 'Error updating password: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --accent: #10b981;
            --accent-dark: #047857;
            --accent-soft: #ecfdf5;
            --ink: #0f172a;
            --muted: #64748b;
        }

        .settings-hero {
            background: linear-gradient(135deg, #10b981 0%, #22c55e 45%, #0ea5e9 100%);
            color: #ffffff;
            border-radius: 16px;
            padding: 22px 26px;
            box-shadow: 0 12px 30px rgba(16, 185, 129, 0.25);
        }

        .settings-hero .badge {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            color: #ffffff;
        }

        .settings-section .card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .settings-section .section-title {
            color: var(--ink);
            font-weight: 600;
        }

        .settings-section .section-subtitle {
            color: var(--muted);
            margin-bottom: 0;
        }

        .settings-card {
            border-radius: 14px;
            border: none;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }

        .settings-card .card-body {
            padding: 20px;
        }

        .settings-label {
            font-weight: 600;
            color: var(--ink);
        }

        .settings-help {
            color: var(--muted);
            font-size: 0.9rem;
        }

        .settings-action {
            background: var(--accent);
            border-color: var(--accent);
            color: #ffffff;
        }

        .settings-action:hover {
            background: var(--accent-dark);
            border-color: var(--accent-dark);
            color: #ffffff;
        }

        .settings-outline {
            border-color: var(--accent);
            color: var(--accent);
        }

        .settings-outline:hover {
            background: var(--accent);
            color: #ffffff;
        }

        .login-activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed #e2e8f0;
        }

        .login-activity-item:last-child {
            border-bottom: none;
        }

        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
        }

        .nav-tabs .nav-link {
            color: var(--muted);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            color: var(--accent);
            border-bottom-color: rgba(16, 185, 129, 0.3);
        }

        .nav-tabs .nav-link.active {
            color: var(--accent);
            background-color: transparent;
            border-bottom-color: var(--accent);
            font-weight: 600;
        }

        .tab-content {
            padding-top: 20px;
        }
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employer-main-content">
        <div class="settings-hero mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h1 class="h3 mb-2">Settings</h1>
                    <p class="mb-0 text-white-50">Manage your account, users, subscription, and security preferences.</p>
                </div>
                <div class="text-end">
                    <span class="badge px-3 py-2">Last updated: <?php echo date('M d, Y'); ?></span>
                    <div class="mt-2">
                        <button class="btn btn-light btn-sm">
                            <i class="fas fa-shield-alt me-2"></i>Security Checklist
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                    <i class="fas fa-user-circle me-2"></i>Account Settings
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                    <i class="fas fa-users me-2"></i>User Management
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="subscription-tab" data-bs-toggle="tab" data-bs-target="#subscription" type="button" role="tab">
                    <i class="fas fa-credit-card me-2"></i>Subscription / Billing
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- Account Settings Tab -->
            <div class="tab-pane fade show active" id="account" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <div class="settings-section mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h4 class="section-title mb-1"><i class="fas fa-user-circle me-2 text-info"></i>Account Information</h4>
                                    <p class="section-subtitle">Keep your contact details up to date.</p>
                                </div>
                            <span class="badge bg-info">Profile</span>
                        </div>
                    <div class="card settings-card">
                        <div class="card-body">
                            <?php if ($contactError): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($contactError); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($contactSuccess): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($contactSuccess); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label settings-label">Update Email</label>
                                        <input type="email" name="email" class="form-control" placeholder="you@example.com" value="<?php echo htmlspecialchars($currentEmail); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label settings-label">Update Contact Number</label>
                                        <input type="tel" name="contact_number" class="form-control" placeholder="09xx xxx xxxx" value="<?php echo htmlspecialchars($currentContactNumber); ?>" required>
                                    </div>
                                </div>
                                <div class="mt-3 text-end">
                                    <button type="submit" name="save_contact_info" class="btn settings-action">Save Contact Info</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="settings-section mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h4 class="section-title mb-1"><i class="fas fa-bell me-2 text-warning"></i>Notification Settings</h4>
                            <p class="section-subtitle">Choose how you receive updates.</p>
                        </div>
                        <span class="badge bg-warning text-dark">Alerts</span>
                    </div>
                    <div class="card settings-card">
                        <div class="card-body">
                            <?php if ($notificationError): ?>
                                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($notificationError); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($notificationSuccess): ?>
                                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($notificationSuccess); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <div>
                                        <p class="settings-label mb-1">Email notifications</p>
                                        <p class="settings-help mb-0">Get updates about applications and interviews.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="email_notifications" role="switch" 
                                               id="email_notifications" value="1" <?php echo $emailNotifications ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <p class="settings-label mb-1">System alerts</p>
                                        <p class="settings-help mb-0">Security alerts and critical system updates.</p>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="system_alerts" role="switch" 
                                               id="system_alerts" value="1" <?php echo $systemAlerts ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                                <div class="mt-4 text-end">
                                    <button type="submit" name="update_notifications" class="btn settings-action">Update Notifications</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="settings-section mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h4 class="section-title mb-1"><i class="fas fa-lock me-2 text-success"></i>Account Security</h4>
                            <p class="section-subtitle">Protect your account with strong password controls.</p>
                        </div>
                        <span class="badge bg-success">Secure</span>
                    </div>
                    <div class="card settings-card mb-3">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="settings-label">Change Password</span>
                                <i class="fas fa-user-shield text-primary"></i>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($passwordError): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($passwordError); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($passwordSuccess): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($passwordSuccess); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label settings-label">Current Password</label>
                                        <input type="password" name="current_password" class="form-control" placeholder="Current password" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label settings-label">New Password</label>
                                        <input type="password" name="new_password" class="form-control" placeholder="New password (min. 8 characters)" required minlength="8">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label settings-label">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required minlength="8">
                                    </div>
                                </div>
                                <div class="mt-3 d-flex align-items-center justify-content-between">
                                    <small class="settings-help">Update every 90 days for better protection.</small>
                                    <button type="submit" name="update_password" class="btn settings-action">Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="settings-section mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h4 class="section-title mb-1"><i class="fas fa-user-secret me-2 text-danger"></i>Privacy &amp; Security</h4>
                                    <p class="section-subtitle">Additional safeguards for your account.</p>
                                </div>
                                <span class="badge bg-danger">Protected</span>
                            </div>
                    <div class="card settings-card mb-3">
                        <div class="card-body">
                            <?php if ($twoFactorError): ?>
                                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($twoFactorError); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($twoFactorSuccess): ?>
                                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($twoFactorSuccess); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <p class="settings-label mb-1">Two-factor authentication (optional)</p>
                                    <p class="settings-help mb-0">Add an extra verification step for logins.</p>
                                </div>
                                <div class="form-check form-switch">
                                    <form method="POST" action="" id="toggle2FAForm">
                                        <input type="hidden" name="toggle_2fa" value="1">
                                        <input type="hidden" name="enable_2fa" value="<?php echo $twoFactorEnabled ? '0' : '1'; ?>">
                                        <input class="form-check-input" type="checkbox" role="switch" <?php echo $twoFactorEnabled ? 'checked' : ''; ?> 
                                               onchange="document.getElementById('toggle2FAForm').submit();">
                                    </form>
                                </div>
                            </div>
                            <button type="button" class="btn settings-outline w-100 mt-3" data-bs-toggle="modal" data-bs-target="#configure2FAModal">
                                <i class="fas fa-cog me-2"></i>Configure 2FA
                            </button>
                        </div>
                    </div>
                    <div class="card settings-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <p class="settings-label mb-0">Login Activity</p>
                                <a href="#" class="text-decoration-none" style="color: var(--accent);" data-bs-toggle="modal" data-bs-target="#loginActivityModal">View all</a>
                            </div>
                            <?php
                            // Get login activity from database
                            // Note: login_sessions table is defined in sql/all_additional_tables.sql
                            try {
                                // Get login sessions ordered by last activity (most recent first)
                                $stmt = $pdo->prepare("SELECT device_type, browser, location, last_activity, is_active, session_id 
                                                       FROM login_sessions 
                                                       WHERE user_id = ? 
                                                       ORDER BY last_activity DESC 
                                                       LIMIT 5");
                                $stmt->execute([$_SESSION['user_id']]);
                                $loginSessions = $stmt->fetchAll();
                                
                                if (empty($loginSessions)) {
                                    // Show current session if no history exists
                                    $deviceType = detectDeviceType();
                                    $browser = detectBrowser();
                                    $ipAddress = getRealIPAddress();
                                    $location = getLocationFromIP($ipAddress);
                                    echo '<div class="login-activity-item">';
                                    echo '<div>';
                                    echo '<strong>' . htmlspecialchars($deviceType) . '</strong>';
                                    echo '<div class="settings-help"><i class="fas fa-map-marker-alt me-1"></i>' . htmlspecialchars($location) . ' • ' . htmlspecialchars($browser) . '</div>';
                                    echo '</div>';
                                    echo '<span class="badge bg-success">Now</span>';
                                    echo '</div>';
                                } else {
                                    $currentSessionId = session_id();
                                    foreach ($loginSessions as $session) {
                                        $isCurrentSession = ($session['session_id'] === $currentSessionId && $session['is_active']);
                                        $lastActivity = new DateTime($session['last_activity']);
                                        $now = new DateTime();
                                        $diff = $now->diff($lastActivity);
                                        
                                        echo '<div class="login-activity-item">';
                                        echo '<div>';
                                        echo '<strong>' . htmlspecialchars($session['device_type']) . '</strong>';
                                        echo '<div class="settings-help"><i class="fas fa-map-marker-alt me-1"></i>' . htmlspecialchars($session['location']) . '</div>';
                                        echo '</div>';
                                        
                                        if ($isCurrentSession) {
                                            echo '<span class="badge bg-success">Now</span>';
                                        } else {
                                            // Calculate time difference
                                            if ($diff->days > 0) {
                                                $timeAgo = $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                            } elseif ($diff->h > 0) {
                                                $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                            } elseif ($diff->i > 0) {
                                                $timeAgo = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                            } else {
                                                $timeAgo = 'Just now';
                                            }
                                            echo '<span class="settings-help">' . htmlspecialchars($timeAgo) . '</span>';
                                        }
                                        echo '</div>';
                                    }
                                }
                            } catch (Exception $e) {
                                // Fallback to showing current session info
                                $deviceType = detectDeviceType();
                                $browser = detectBrowser();
                                $location = getLocationFromIP($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
                                echo '<div class="login-activity-item">';
                                echo '<div>';
                                echo '<strong>' . htmlspecialchars($deviceType) . '</strong>';
                                echo '<div class="settings-help">' . htmlspecialchars($location) . ' • ' . htmlspecialchars($browser) . '</div>';
                                echo '</div>';
                                echo '<span class="badge bg-success">Now</span>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="settings-section">
                    <div class="card settings-card">
                        <div class="card-body">
                            <h6 class="settings-label mb-2">Security Tips</h6>
                            <ul class="list-unstyled mb-0 settings-help">
                                <li class="mb-2"><i class="fas fa-check-circle me-2 text-success"></i>Use a unique password.</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2 text-success"></i>Enable 2FA for extra safety.</li>
                                <li class="mb-2"><i class="fas fa-check-circle me-2 text-success"></i>Review login activity regularly.</li>
                                <li><i class="fas fa-check-circle me-2 text-success"></i>Update contact details for alerts.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            </div>
            <!-- End Account Settings Tab -->

            <!-- User Management Tab -->
            <div class="tab-pane fade" id="users" role="tabpanel">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="settings-section mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h4 class="section-title mb-1"><i class="fas fa-users me-2 text-primary"></i>User Management</h4>
                                    <p class="section-subtitle">Manage team members and user access.</p>
                                </div>
                                <span class="badge bg-primary">Team</span>
                            </div>
                            <div class="card settings-card">
                                <div class="card-body">
                                    <?php if ($teamError): ?>
                                        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($teamError); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($teamSuccess): ?>
                                        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($teamSuccess); ?>
                                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                        <div>
                                            <h6 class="mb-1">Team Members</h6>
                                            <p class="text-muted mb-0">Invite and track your hiring team.</p>
                                        </div>
                                        <button class="btn settings-action" data-bs-toggle="modal" data-bs-target="#addTeamMemberModal" type="button">
                                            <i class="fas fa-plus me-2"></i>Add Team Member
                                        </button>
                                    </div>

                                    <?php if (empty($teamMembers)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <h6 class="text-muted">No team members yet</h6>
                                            <p class="text-muted mb-0">Add your first team member to manage hiring.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Name</th>
                                                        <th>Email</th>
                                                        <th>Role</th>
                                                        <th>Status</th>
                                                        <th>Added</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($teamMembers as $member): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                            <td><?php echo htmlspecialchars($member['role_title']); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $member['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                    <?php echo htmlspecialchars(ucfirst($member['status'])); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M d, Y', strtotime($member['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End User Management Tab -->

            <!-- Subscription / Billing Tab -->
            <div class="tab-pane fade" id="subscription" role="tabpanel">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="settings-section mb-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h4 class="section-title mb-1"><i class="fas fa-credit-card me-2 text-success"></i>Subscription & Billing</h4>
                                    <p class="section-subtitle">Manage your subscription plan and billing information.</p>
                                </div>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="card settings-card">
                                <div class="card-body">
                                    <div class="text-center py-5">
                                        <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">Subscription & Billing</h5>
                                        <p class="text-muted">View your current plan, billing history, and payment methods.</p>
                                        <button class="btn settings-action mt-3" data-bs-toggle="modal" data-bs-target="#billingDetailsModal" type="button">
                                            <i class="fas fa-eye me-2"></i>View Billing Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Subscription / Billing Tab -->
        </div>
    </div>

    <!-- Login Activity Modal -->
    <div class="modal fade" id="loginActivityModal" tabindex="-1" aria-labelledby="loginActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginActivityModalLabel">
                        <i class="fas fa-history me-2"></i>All Login Activity
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php
                    // Get all login sessions for the user
                    try {
                        $stmt = $pdo->prepare("SELECT device_type, browser, location, ip_address, login_time, last_activity, is_active, session_id 
                                               FROM login_sessions 
                                               WHERE user_id = ? 
                                               ORDER BY last_activity DESC");
                        $stmt->execute([$_SESSION['user_id']]);
                        $allSessions = $stmt->fetchAll();
                        
                        $currentSessionId = session_id();
                        
                        if (empty($allSessions)) {
                            echo '<div class="text-center py-4">';
                            echo '<i class="fas fa-info-circle fa-3x text-muted mb-3"></i>';
                            echo '<p class="text-muted">No login activity found.</p>';
                            echo '</div>';
                        } else {
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-hover">';
                            echo '<thead class="table-light">';
                            echo '<tr>';
                            echo '<th>Device</th>';
                            echo '<th>Location</th>';
                            echo '<th>IP Address</th>';
                            echo '<th>Login Time</th>';
                            echo '<th>Last Activity</th>';
                            echo '<th>Status</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            
                            foreach ($allSessions as $session) {
                                $isCurrentSession = ($session['session_id'] === $currentSessionId && $session['is_active']);
                                $loginTime = new DateTime($session['login_time']);
                                $lastActivity = new DateTime($session['last_activity']);
                                $now = new DateTime();
                                $diff = $now->diff($lastActivity);
                                
                                // Calculate time ago
                                if ($isCurrentSession) {
                                    $timeAgo = 'Active now';
                                    $statusBadge = '<span class="badge bg-success">Active</span>';
                                } else {
                                    if ($diff->days > 0) {
                                        $timeAgo = $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
                                    } elseif ($diff->h > 0) {
                                        $timeAgo = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
                                    } elseif ($diff->i > 0) {
                                        $timeAgo = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
                                    } else {
                                        $timeAgo = 'Just now';
                                    }
                                    $statusBadge = '<span class="badge bg-secondary">Offline</span>';
                                }
                                
                                echo '<tr' . ($isCurrentSession ? ' class="table-success"' : '') . '>';
                                echo '<td><i class="fas fa-' . ($session['device_type'] === 'Mobile' ? 'mobile-alt' : 'laptop') . ' me-2"></i><strong>' . htmlspecialchars($session['device_type']) . '</strong><br><small class="text-muted">' . htmlspecialchars($session['browser']) . '</small></td>';
                                echo '<td><i class="fas fa-map-marker-alt me-1 text-danger"></i>' . htmlspecialchars($session['location']) . '</td>';
                                echo '<td><code>' . htmlspecialchars($session['ip_address']) . '</code></td>';
                                echo '<td>' . $loginTime->format('M d, Y<br>h:i A') . '</td>';
                                echo '<td>' . $lastActivity->format('M d, Y<br>h:i A') . '<br><small class="text-muted">' . $timeAgo . '</small></td>';
                                echo '<td>' . $statusBadge . '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        }
                    } catch (Exception $e) {
                        echo '<div class="alert alert-danger">';
                        echo '<i class="fas fa-exclamation-circle me-2"></i>Error loading login activity: ' . htmlspecialchars($e->getMessage());
                        echo '</div>';
                    }
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Configure 2FA Modal -->
    <div class="modal fade" id="configure2FAModal" tabindex="-1" aria-labelledby="configure2FAModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="configure2FAModalLabel">
                        <i class="fas fa-shield-alt me-2"></i>Configure Two-Factor Authentication
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($twoFactorSecret && !$twoFactorEnabled): ?>
                        <!-- Step 2: Show QR Code and Verification -->
                        <div class="text-center mb-4">
                            <h6 class="mb-3">Scan QR Code with Authenticator App</h6>
                            <p class="text-muted mb-3">Use Google Authenticator, Microsoft Authenticator, or any TOTP app to scan this QR code.</p>
                            <img src="<?php echo get2FAQRCodeURL($twoFactorSecret, $currentEmail); ?>" alt="2FA QR Code" class="img-fluid mb-3" style="max-width: 250px;">
                            <div class="alert alert-info">
                                <strong>Manual Entry:</strong><br>
                                <code><?php echo htmlspecialchars($twoFactorSecret); ?></code>
                            </div>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="configure_2fa" value="1">
                            <input type="hidden" name="action" value="verify">
                            <div class="mb-3">
                                <label for="verification_code" class="form-label">Enter 6-digit code from your app</label>
                                <input type="text" class="form-control text-center" id="verification_code" name="verification_code" 
                                       placeholder="000000" maxlength="6" pattern="[0-9]{6}" required style="font-size: 1.5rem; letter-spacing: 0.5rem;">
                                <small class="form-text text-muted">Enter the 6-digit code shown in your authenticator app to verify and enable 2FA.</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success flex-fill">
                                    <i class="fas fa-check me-2"></i>Verify & Enable 2FA
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="location.reload();">
                                    <i class="fas fa-redo me-2"></i>Generate New Code
                                </button>
                            </div>
                        </form>
                    <?php elseif ($twoFactorEnabled): ?>
                        <!-- 2FA is Enabled -->
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h5 class="text-success">2FA is Enabled</h5>
                            <p class="text-muted">Your account is protected with two-factor authentication.</p>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> If you disable 2FA, you'll need to set it up again to re-enable it.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="configure_2fa" value="1">
                            <input type="hidden" name="action" value="disable">
                            <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to disable 2FA? You will need to set it up again to re-enable it.');">
                                <i class="fas fa-times me-2"></i>Disable 2FA
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- Step 1: Generate Secret -->
                        <div class="text-center mb-4">
                            <i class="fas fa-mobile-alt fa-3x text-primary mb-3"></i>
                            <h5>Set Up Two-Factor Authentication</h5>
                            <p class="text-muted">Protect your account with an extra layer of security. You'll need an authenticator app like Google Authenticator or Microsoft Authenticator.</p>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>How it works:</strong>
                            <ol class="mb-0 mt-2">
                                <li>Click "Generate QR Code" below</li>
                                <li>Scan the QR code with your authenticator app</li>
                                <li>Enter the 6-digit code to verify and enable 2FA</li>
                            </ol>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="configure_2fa" value="1">
                            <input type="hidden" name="action" value="generate">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-qrcode me-2"></i>Generate QR Code
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Team Member Modal -->
    <div class="modal fade" id="addTeamMemberModal" tabindex="-1" aria-labelledby="addTeamMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addTeamMemberModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Add Team Member
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="add_team_member" value="1">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="member_name" required placeholder="Juan Dela Cruz">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="member_email" required placeholder="team@company.com">
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Role / Title</label>
                            <input type="text" class="form-control" name="member_role" required placeholder="Recruiter, HR Manager, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn settings-action">
                            <i class="fas fa-check me-2"></i>Add Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Billing Details Modal -->
    <div class="modal fade" id="billingDetailsModal" tabindex="-1" aria-labelledby="billingDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="billingDetailsModalLabel">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Billing Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1">Current Plan</p>
                                <h6 class="mb-1"><?php echo htmlspecialchars($currentSubscription['plan_name'] ?? 'Free Plan'); ?></h6>
                                <span class="badge <?php echo $statusBadge; ?>">
                                    <?php echo htmlspecialchars($subscriptionStatus); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1">Next Billing Date</p>
                                <h6 class="mb-0">
                                    <?php
                                    if ($currentSubscription && !empty($currentSubscription['end_date'])) {
                                        echo date('M d, Y', strtotime($currentSubscription['end_date']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1">Start Date</p>
                                <h6 class="mb-0">
                                    <?php
                                    if ($currentSubscription && !empty($currentSubscription['start_date'])) {
                                        echo date('M d, Y', strtotime($currentSubscription['start_date']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </h6>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <p class="text-muted mb-1">Payment Method</p>
                                <h6 class="mb-0"><?php echo htmlspecialchars($currentSubscription['payment_method'] ?? 'N/A'); ?></h6>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <h6>Recent Invoices</h6>
                        <?php if (empty($billingHistory)): ?>
                            <div class="text-muted">No invoices found.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Plan</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Receipt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($billingHistory as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                                                <td>PHP <?php echo number_format($row['price'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['payment_status'] === 'paid' ? 'success' : 'secondary'; ?>">
                                                        <?php echo htmlspecialchars(ucfirst($row['payment_status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($row['payment_status'] === 'paid'): ?>
                                                        <a class="btn btn-outline-primary btn-sm" href="download-receipt.php?id=<?php echo (int)$row['id']; ?>">
                                                            Download
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus verification code input
        document.addEventListener('DOMContentLoaded', function() {
            const verificationInput = document.getElementById('verification_code');
            if (verificationInput) {
                verificationInput.focus();
                // Auto-format: only numbers, max 6 digits
                verificationInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
                });
            }
        });
    </script>
</body>
</html>
