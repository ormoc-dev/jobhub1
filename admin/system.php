<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Note: Tables (admin_roles, security_settings, platform_config, api_keys, automation_rules, audit_log) 
// are defined in sql/all_additional_tables.sql

// Insert default settings if not exists
$defaultSecuritySettings = [
    ['max_login_attempts', '5', 'number', 'Maximum failed login attempts before lockout'],
    ['lockout_duration', '30', 'number', 'Account lockout duration in minutes'],
    ['session_timeout', '120', 'number', 'Session timeout in minutes'],
    ['password_min_length', '8', 'number', 'Minimum password length'],
    ['require_2fa_admin', '0', 'boolean', 'Require 2FA for admin accounts'],
    ['require_2fa_employer', '0', 'boolean', 'Require 2FA for employer accounts'],
    ['ip_whitelist', '', 'text', 'Whitelisted IP addresses (comma separated)'],
    ['ip_blacklist', '', 'text', 'Blacklisted IP addresses (comma separated)'],
    ['force_https', '1', 'boolean', 'Force HTTPS connections'],
    ['csrf_protection', '1', 'boolean', 'Enable CSRF protection']
];

foreach ($defaultSecuritySettings as $setting) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO security_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
    $stmt->execute($setting);
}

$defaultPlatformConfig = [
    ['site_name', 'WORKLINK', 'string', 'general', 'Platform name'],
    ['site_description', 'Your Gateway to Career Success', 'text', 'general', 'Platform description'],
    ['contact_email', 'support@worklink.com', 'string', 'general', 'Support email'],
    ['max_job_applications', '50', 'number', 'limits', 'Max applications per day per user'],
    ['max_job_posts', '20', 'number', 'limits', 'Max active job posts per company'],
    ['default_job_expiry', '30', 'number', 'jobs', 'Default job posting expiry days'],
    ['require_company_verification', '1', 'boolean', 'verification', 'Require employer verification'],
    ['enable_ai_matching', '1', 'boolean', 'features', 'Enable AI job matching'],
    ['enable_messaging', '1', 'boolean', 'features', 'Enable in-app messaging'],
    ['maintenance_mode', '0', 'boolean', 'general', 'Enable maintenance mode']
];

foreach ($defaultPlatformConfig as $config) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO platform_config (config_key, config_value, config_type, category, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute($config);
}

// Insert default admin role if not exists
$stmt = $pdo->prepare("INSERT IGNORE INTO admin_roles (role_name, description, permissions, is_system) VALUES (?, ?, ?, ?)");
$stmt->execute(['Super Admin', 'Full system access', json_encode(['*']), true]);
$stmt->execute(['Admin', 'Standard admin access', json_encode(['users', 'companies', 'jobs', 'reports', 'messages']), true]);
$stmt->execute(['Moderator', 'Content moderation only', json_encode(['jobs', 'reports', 'messages']), false]);
$stmt->execute(['Support', 'User support only', json_encode(['users', 'messages']), false]);

// Insert default automation rules
$defaultRules = [
    ['Inactive Users Cleanup (2 Years)', 'cleanup', json_encode(['inactive_days' => 730, 'entity' => 'users', 'roles' => ['employee','employer']]), json_encode(['action' => 'delete', 'notify' => true]), false],
    ['Expired Jobs Archive', 'archive', json_encode(['expired_days' => 7, 'entity' => 'jobs']), json_encode(['action' => 'archive', 'notify_employer' => true]), true],
    ['Unverified Companies Reminder', 'notification', json_encode(['pending_days' => 7, 'entity' => 'companies']), json_encode(['action' => 'send_reminder']), true],
    ['Stale Applications Cleanup', 'cleanup', json_encode(['inactive_days' => 180, 'status' => 'pending', 'entity' => 'applications']), json_encode(['action' => 'archive']), false]
];

foreach ($defaultRules as $rule) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO automation_rules (rule_name, rule_type, conditions, actions, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute($rule);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Roles & Permissions
    if ($action === 'create_role') {
        $roleName = sanitizeInput($_POST['role_name']);
        $description = sanitizeInput($_POST['description']);
        $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';
        
        $stmt = $pdo->prepare("INSERT INTO admin_roles (role_name, description, permissions) VALUES (?, ?, ?)");
        if ($stmt->execute([$roleName, $description, $permissions])) {
            $message = 'Role created successfully!';
        } else {
            $error = 'Failed to create role.';
        }
    } elseif ($action === 'update_role') {
        $roleId = (int)$_POST['role_id'];
        $roleName = sanitizeInput($_POST['role_name']);
        $description = sanitizeInput($_POST['description']);
        $permissions = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';
        
        $stmt = $pdo->prepare("UPDATE admin_roles SET role_name = ?, description = ?, permissions = ? WHERE id = ? AND is_system = FALSE");
        if ($stmt->execute([$roleName, $description, $permissions, $roleId])) {
            $message = 'Role updated successfully!';
        } else {
            $error = 'Failed to update role. System roles cannot be modified.';
        }
    } elseif ($action === 'delete_role') {
        $roleId = (int)$_POST['role_id'];
        $stmt = $pdo->prepare("DELETE FROM admin_roles WHERE id = ? AND is_system = FALSE");
        if ($stmt->execute([$roleId]) && $stmt->rowCount() > 0) {
            $message = 'Role deleted successfully!';
        } else {
            $error = 'Failed to delete role. System roles cannot be deleted.';
        }
    }
    
    // Security Settings
    elseif ($action === 'update_security') {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("UPDATE security_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $stmt->execute([$value, $_SESSION['user_id'], $key]);
        }
        $message = 'Security settings updated successfully!';
    }
    
    // Platform Configuration
    elseif ($action === 'update_platform') {
        foreach ($_POST['config'] as $key => $value) {
            $stmt = $pdo->prepare("UPDATE platform_config SET config_value = ? WHERE config_key = ?");
            $stmt->execute([$value, $key]);
        }
        $message = 'Platform configuration updated successfully!';
    }
    
    // API Keys
    elseif ($action === 'create_api_key') {
        $keyName = sanitizeInput($_POST['key_name']);
        $serviceType = $_POST['service_type'];
        $rateLimit = (int)$_POST['rate_limit'];
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        
        $apiKey = 'wl_' . bin2hex(random_bytes(24));
        $apiSecret = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("INSERT INTO api_keys (key_name, api_key, api_secret, service_type, rate_limit, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$keyName, $apiKey, $apiSecret, $serviceType, $rateLimit, $expiresAt, $_SESSION['user_id']])) {
            $message = "API key created! Key: $apiKey (save this, it won't be shown again)";
        } else {
            $error = 'Failed to create API key.';
        }
    } elseif ($action === 'toggle_api_key') {
        $keyId = (int)$_POST['key_id'];
        $stmt = $pdo->prepare("UPDATE api_keys SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$keyId]);
        $message = 'API key status updated!';
    } elseif ($action === 'delete_api_key') {
        $keyId = (int)$_POST['key_id'];
        $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->execute([$keyId]);
        $message = 'API key deleted!';
    }
    
    // Automation Rules
    elseif ($action === 'toggle_automation') {
        $ruleId = (int)$_POST['rule_id'];
        $stmt = $pdo->prepare("UPDATE automation_rules SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$ruleId]);
        $message = 'Automation rule status updated!';
    } elseif ($action === 'create_automation') {
        $ruleName = sanitizeInput($_POST['rule_name']);
        $ruleType = $_POST['rule_type'];
        $conditionsArr = [
            'inactive_days' => (int)($_POST['inactive_days'] ?? 730),
            'entity' => $_POST['entity'] ?? 'users'
        ];
        // Include roles filter if provided
        if (!empty($_POST['roles']) && is_array($_POST['roles'])) {
            $rolesClean = array_values(array_map('sanitizeInput', $_POST['roles']));
            $conditionsArr['roles'] = $rolesClean;
        }
        $conditions = json_encode($conditionsArr);
        $actions = json_encode([
            'action' => $_POST['rule_action'] ?? 'archive',
            'notify' => isset($_POST['notify'])
        ]);

        $stmt = $pdo->prepare("INSERT INTO automation_rules (rule_name, rule_type, conditions, actions) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$ruleName, $ruleType, $conditions, $actions])) {
            $message = 'Automation rule created!';
        }
    } elseif ($action === 'delete_automation') {
        $ruleId = (int)$_POST['rule_id'];
        $stmt = $pdo->prepare("DELETE FROM automation_rules WHERE id = ?");
        $stmt->execute([$ruleId]);
        $message = 'Automation rule deleted!';
    }
}

// Current tab
$currentTab = $_GET['tab'] ?? 'roles';

// Fetch data
$roles = $pdo->query("SELECT * FROM admin_roles ORDER BY is_system DESC, role_name")->fetchAll();
$securitySettingsFull = $pdo->query("SELECT * FROM security_settings ORDER BY setting_key")->fetchAll();
$platformConfig = $pdo->query("SELECT * FROM platform_config ORDER BY category, config_key")->fetchAll();
$apiKeys = $pdo->query("SELECT ak.*, u.username as created_by_username FROM api_keys ak LEFT JOIN users u ON ak.created_by = u.id ORDER BY ak.created_at DESC")->fetchAll();
$automationRules = $pdo->query("SELECT * FROM automation_rules ORDER BY is_active DESC, rule_name")->fetchAll();

// Group platform config by category
$configByCategory = [];
foreach ($platformConfig as $config) {
    $configByCategory[$config['category']][] = $config;
}

// Get statistics
$stats = [];
$stats['roles'] = count($roles);
$stats['api_keys'] = count($apiKeys);
$stats['active_api_keys'] = $pdo->query("SELECT COUNT(*) FROM api_keys WHERE is_active = TRUE")->fetchColumn();
$stats['automation_rules'] = count($automationRules);
$stats['active_automations'] = $pdo->query("SELECT COUNT(*) FROM automation_rules WHERE is_active = TRUE")->fetchColumn();

// Available permissions
$availablePermissions = [
    'users' => ['icon' => 'fa-users', 'label' => 'User Management'],
    'companies' => ['icon' => 'fa-building', 'label' => 'Company Management'],
    'jobs' => ['icon' => 'fa-briefcase', 'label' => 'Job Management'],
    'applications' => ['icon' => 'fa-file-alt', 'label' => 'Applications'],
    'reports' => ['icon' => 'fa-chart-bar', 'label' => 'Reports & Analytics'],
    'messages' => ['icon' => 'fa-envelope', 'label' => 'Messages'],
    'content' => ['icon' => 'fa-edit', 'label' => 'Content Management'],
    'settings' => ['icon' => 'fa-cog', 'label' => 'System Settings'],
    'api' => ['icon' => 'fa-plug', 'label' => 'API Management'],
    'audit' => ['icon' => 'fa-history', 'label' => 'Audit Logs']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
        <style>
        .system-admin-page .admin-main-content {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
            padding: 1.5rem 2rem 2.5rem;
        }

        .sys-page-header {
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .sys-page-header h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .sys-page-header h1 i {
            color: #2563eb;
            opacity: 0.9;
        }

        .sys-page-header p {
            color: #64748b;
            margin: 0;
            font-size: 0.95rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        .stat-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.1rem 1.15rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .stat-box:hover {
            border-color: rgba(37, 99, 235, 0.25);
            box-shadow: 0 4px 14px rgba(30, 58, 138, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .stat-box.roles .stat-icon { background: #eef2ff; color: #2563eb; }
        .stat-box.security .stat-icon { background: #ecfdf5; color: #059669; }
        .stat-box.api .stat-icon { background: #ecfeff; color: #0891b2; }
        .stat-box.automation .stat-icon { background: #fffbeb; color: #d97706; }
        .stat-box.config .stat-icon { background: #f5f3ff; color: #7c3aed; }

        .stat-info h3 {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            line-height: 1.15;
        }

        .stat-info span {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 500;
        }

        .alert-custom {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            border: 1px solid transparent;
        }

        .alert-custom.success {
            background: #ecfdf5;
            border-color: #a7f3d0;
            color: #065f46;
        }

        .alert-custom.danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .alert-custom.info {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .tab-nav {
            display: flex;
            gap: 6px;
            margin-bottom: 1.25rem;
            background: #f1f5f9;
            padding: 6px;
            border-radius: 12px;
            flex-wrap: wrap;
            border: 1px solid #e2e8f0;
        }

        .tab-link {
            padding: 10px 16px;
            border-radius: 10px;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background 0.2s, color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .tab-link:hover {
            color: #1e3a8a;
            background: rgba(255, 255, 255, 0.85);
        }

        .tab-link.active {
            background: #fff;
            color: #1d4ed8;
            box-shadow: 0 1px 4px rgba(30, 58, 138, 0.12);
        }

        .main-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .card-header-custom {
            padding: 1.1rem 1.35rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            background: linear-gradient(120deg, #fafbff 0%, #f8fafc 100%);
        }

        .card-header-custom h5 {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 1.05rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header-custom h5 i {
            color: #2563eb;
        }

        .card-body-custom {
            padding: 1.35rem 1.35rem 1.5rem;
            background: #fff;
        }

        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.1rem;
        }

        .role-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
            transition: box-shadow 0.2s, border-color 0.2s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #2563eb, #38bdf8);
        }

        .role-card:hover {
            border-color: rgba(37, 99, 235, 0.3);
            box-shadow: 0 6px 20px rgba(30, 58, 138, 0.08);
        }

        .role-card.system::before {
            background: linear-gradient(90deg, #d97706, #fbbf24);
        }

        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .role-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .role-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #eef2ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            font-size: 1.05rem;
        }

        .role-card.system .role-icon {
            background: #fffbeb;
            color: #d97706;
        }

        .role-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
        }

        .role-badge {
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            margin-top: 4px;
            display: inline-block;
        }

        .role-badge.system {
            background: #fffbeb;
            color: #b45309;
        }

        .role-badge.custom {
            background: #eef2ff;
            color: #4338ca;
        }

        .role-description {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .role-permissions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }

        .permission-tag {
            background: #f1f5f9;
            color: #334155;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: 1px solid #e2e8f0;
        }

        .permission-tag.all {
            background: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }

        .role-actions {
            display: flex;
            gap: 8px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
        }

        .settings-section {
            margin-bottom: 28px;
        }

        .settings-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            color: #1e3a8a;
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-title i {
            color: #2563eb;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px;
        }

        .setting-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
        }

        .setting-item label {
            color: #0f172a;
            font-weight: 600;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 8px;
        }

        .setting-item .description {
            color: #64748b;
            font-size: 0.75rem;
            margin-top: 8px;
        }

        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.1rem;
        }

        .api-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.15rem;
            transition: box-shadow 0.2s, border-color 0.2s;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .api-card:hover {
            border-color: rgba(8, 145, 178, 0.35);
            box-shadow: 0 4px 16px rgba(8, 145, 178, 0.08);
        }

        .api-card.inactive {
            opacity: 0.72;
        }

        .api-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }

        .api-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 6px 0;
        }

        .api-type {
            font-size: 0.68rem;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .api-type.internal { background: #eef2ff; color: #4338ca; }
        .api-type.external { background: #ecfeff; color: #0e7490; }
        .api-type.webhook { background: #f5f3ff; color: #6d28d9; }

        .api-status {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
        }

        .api-status.active { background: #ecfdf5; color: #047857; }
        .api-status.inactive { background: #fef2f2; color: #b91c1c; }

        .api-key-display {
            background: #0f172a;
            border-radius: 8px;
            padding: 12px;
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 0.75rem;
            color: #7dd3fc;
            margin-bottom: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-all;
        }

        .api-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 14px;
        }

        .api-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .automation-grid {
            display: grid;
            gap: 14px;
        }

        .automation-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.1rem 1.15rem;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .automation-card:hover {
            border-color: rgba(217, 119, 6, 0.35);
            box-shadow: 0 4px 14px rgba(217, 119, 6, 0.08);
        }

        .automation-card.inactive {
            opacity: 0.7;
        }

        .automation-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .automation-card[data-type="cleanup"] .automation-icon { background: #ffe4e6; color: #be123c; }
        .automation-card[data-type="archive"] .automation-icon { background: #f5f3ff; color: #6d28d9; }
        .automation-card[data-type="notification"] .automation-icon { background: #ecfeff; color: #0e7490; }
        .automation-card[data-type="status_change"] .automation-icon { background: #ecfdf5; color: #047857; }

        .automation-info {
            flex: 1;
            min-width: 0;
        }

        .automation-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 6px 0;
        }

        .automation-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 0.75rem;
            color: #64748b;
        }

        .automation-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .automation-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }

        .toggle-switch {
            position: relative;
            width: 48px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 26px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
            box-shadow: 0 1px 2px rgba(0,0,0,0.12);
        }

        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: box-shadow 0.2s, transform 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-custom:hover {
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
            transform: translateY(-1px);
            color: white;
        }

        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-edit {
            background: #fff;
            color: #2563eb;
            border-color: rgba(37, 99, 235, 0.35);
        }
        .btn-edit:hover { background: #eef2ff; color: #1d4ed8; }

        .btn-run {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .btn-run:hover { background: #d1fae5; }

        .btn-delete {
            background: #fff;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .btn-delete:hover { background: #fef2f2; }

        .system-admin-page .form-control,
        .system-admin-page .form-select {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #0f172a;
            padding: 10px 14px;
            font-size: 0.9rem;
        }

        .system-admin-page .form-control:focus,
        .system-admin-page .form-select:focus {
            background: #fff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
            color: #0f172a;
        }

        .system-admin-page .form-control::placeholder {
            color: #94a3b8;
        }

        .system-admin-page .form-label {
            color: #334155;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .system-admin-page .form-check-input {
            background-color: #fff;
            border-color: #cbd5e1;
        }

        .system-admin-page .form-check-input:checked {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .system-admin-page .form-check-label {
            color: #475569;
        }

        .system-admin-page .modal-content {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
        }

        .system-admin-page .modal-header {
            background: linear-gradient(120deg, #f8fafc 0%, #eef4ff 100%);
            border-bottom: 1px solid #e2e8f0;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }

        .system-admin-page .modal-title {
            color: #1e3a8a;
            font-weight: 700;
        }

        .system-admin-page .modal-body {
            padding: 20px;
            color: #334155;
        }

        .system-admin-page .modal-footer {
            padding: 14px 20px;
            border-top: 1px solid #e2e8f0;
            background: #fafafa;
        }

        .btn-modal-cancel {
            background: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-modal-cancel:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .btn-modal-save {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-modal-save:hover {
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
            color: white;
        }

        .config-category {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 16px;
        }

        .config-category:last-child {
            margin-bottom: 0;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .category-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .category-icon.general { background: #eef2ff; color: #2563eb; }
        .category-icon.limits { background: #fffbeb; color: #d97706; }
        .category-icon.jobs { background: #ecfeff; color: #0e7490; }
        .category-icon.verification { background: #ecfdf5; color: #059669; }
        .category-icon.features { background: #f5f3ff; color: #7c3aed; }

        .category-title {
            color: #0f172a;
            font-weight: 700;
            font-size: 0.95rem;
            margin: 0;
            text-transform: capitalize;
        }

        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .permission-checkbox {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .permission-checkbox:hover {
            border-color: rgba(37, 99, 235, 0.35);
        }

        .permission-checkbox input:checked ~ .permission-label {
            color: #1d4ed8;
        }

        .permission-label {
            color: #475569;
            font-size: 0.84rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        .permission-label i {
            color: #94a3b8;
        }

        .empty-state {
            text-align: center;
            padding: 48px 20px;
        }

        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h5 {
            color: #334155;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="admin-layout system-admin-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="sys-page-header">
            <h1><i class="fas fa-cogs"></i> System Settings</h1>
            <p>Manage roles, security, platform configuration, API keys, and automation rules</p>
        </div>

        <?php if ($message): ?>
            <div class="alert-custom success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-custom danger">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-box roles">
                <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['roles']; ?></h3>
                    <span>Admin Roles</span>
                </div>
            </div>
            <div class="stat-box security">
                <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="stat-info">
                    <h3>Active</h3>
                    <span>Security Status</span>
                </div>
            </div>
            <div class="stat-box api">
                <div class="stat-icon"><i class="fas fa-plug"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['active_api_keys']; ?>/<?php echo $stats['api_keys']; ?></h3>
                    <span>API Keys Active</span>
                </div>
            </div>
            <div class="stat-box automation">
                <div class="stat-icon"><i class="fas fa-robot"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['active_automations']; ?></h3>
                    <span>Active Automations</span>
                </div>
            </div>
            <div class="stat-box config">
                <div class="stat-icon"><i class="fas fa-sliders-h"></i></div>
                <div class="stat-info">
                    <h3><?php echo count($platformConfig); ?></h3>
                    <span>Config Options</span>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <a href="?tab=roles" class="tab-link <?php echo $currentTab === 'roles' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> Roles & Permissions
            </a>
            <a href="?tab=security" class="tab-link <?php echo $currentTab === 'security' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i> Security & Access
            </a>
            <a href="?tab=platform" class="tab-link <?php echo $currentTab === 'platform' ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i> Platform Config
            </a>
            <a href="?tab=api" class="tab-link <?php echo $currentTab === 'api' ? 'active' : ''; ?>">
                <i class="fas fa-plug"></i> API / Integrations
            </a>
            <a href="?tab=automation" class="tab-link <?php echo $currentTab === 'automation' ? 'active' : ''; ?>">
                <i class="fas fa-robot"></i> Automation Rules
            </a>
        </div>

        <!-- Main Content Card -->
        <div class="main-card">
            <?php if ($currentTab === 'roles'): ?>
            <!-- Roles & Permissions Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-user-shield"></i> Roles & Permissions Management</h5>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                    <i class="fas fa-plus"></i> Create Role
                </button>
            </div>
            <div class="card-body-custom">
                <div class="role-grid">
                    <?php foreach ($roles as $role): 
                        $permissions = json_decode($role['permissions'], true) ?? [];
                    ?>
                    <div class="role-card <?php echo $role['is_system'] ? 'system' : ''; ?>">
                        <div class="role-header">
                            <div class="role-title">
                                <div class="role-icon">
                                    <i class="fas fa-<?php echo $role['is_system'] ? 'crown' : 'user-tag'; ?>"></i>
                                </div>
                                <div>
                                    <h6 class="role-name"><?php echo htmlspecialchars($role['role_name']); ?></h6>
                                    <span class="role-badge <?php echo $role['is_system'] ? 'system' : 'custom'; ?>">
                                        <?php echo $role['is_system'] ? 'System Role' : 'Custom Role'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p class="role-description"><?php echo htmlspecialchars($role['description']); ?></p>
                        <div class="role-permissions">
                            <?php if (in_array('*', $permissions)): ?>
                                <span class="permission-tag all"><i class="fas fa-infinity"></i> Full Access</span>
                            <?php else: ?>
                                <?php foreach ($permissions as $perm): ?>
                                    <span class="permission-tag">
                                        <i class="fas <?php echo $availablePermissions[$perm]['icon'] ?? 'fa-check'; ?>"></i>
                                        <?php echo $availablePermissions[$perm]['label'] ?? ucfirst($perm); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!$role['is_system']): ?>
                        <div class="role-actions">
                            <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editRoleModal<?php echo $role['id']; ?>">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete_role">
                                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this role?')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Edit Role Modal -->
                    <?php if (!$role['is_system']): ?>
                    <div class="modal fade" id="editRoleModal<?php echo $role['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Role</h5>
                                    <button type="button" class="btn-close btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label">Role Name</label>
                                            <input type="text" name="role_name" class="form-control" value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($role['description']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Permissions</label>
                                            <div class="permission-grid">
                                                <?php foreach ($availablePermissions as $key => $perm): ?>
                                                <label class="permission-checkbox">
                                                    <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" class="form-check-input" <?php echo in_array($key, $permissions) ? 'checked' : ''; ?>>
                                                    <span class="permission-label">
                                                        <i class="fas <?php echo $perm['icon']; ?>"></i>
                                                        <?php echo $perm['label']; ?>
                                                    </span>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn-modal-save">Update Role</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php elseif ($currentTab === 'security'): ?>
            <!-- Security & Access Control Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-shield-alt"></i> Security & Access Control</h5>
            </div>
            <div class="card-body-custom">
                <form method="POST">
                    <input type="hidden" name="action" value="update_security">
                    
                    <div class="settings-section">
                        <h6 class="section-title"><i class="fas fa-sign-in-alt"></i> Authentication Settings</h6>
                        <div class="settings-grid">
                            <?php foreach ($securitySettingsFull as $setting): 
                                if (!in_array($setting['setting_key'], ['max_login_attempts', 'lockout_duration', 'session_timeout', 'password_min_length'])) continue;
                            ?>
                            <div class="setting-item">
                                <label><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                <input type="number" name="settings[<?php echo $setting['setting_key']; ?>]" class="form-control" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                <p class="description"><?php echo htmlspecialchars($setting['description']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h6 class="section-title"><i class="fas fa-key"></i> Two-Factor Authentication</h6>
                        <div class="settings-grid">
                            <?php foreach ($securitySettingsFull as $setting): 
                                if (!in_array($setting['setting_key'], ['require_2fa_admin', 'require_2fa_employer'])) continue;
                            ?>
                            <div class="setting-item">
                                <label><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                <select name="settings[<?php echo $setting['setting_key']; ?>]" class="form-select">
                                    <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                                <p class="description"><?php echo htmlspecialchars($setting['description']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h6 class="section-title"><i class="fas fa-network-wired"></i> Network Security</h6>
                        <div class="settings-grid">
                            <?php foreach ($securitySettingsFull as $setting): 
                                if (!in_array($setting['setting_key'], ['ip_whitelist', 'ip_blacklist', 'force_https', 'csrf_protection'])) continue;
                            ?>
                            <div class="setting-item">
                                <label><?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?></label>
                                <?php if ($setting['setting_type'] === 'boolean'): ?>
                                <select name="settings[<?php echo $setting['setting_key']; ?>]" class="form-select">
                                    <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                                <?php elseif ($setting['setting_type'] === 'text'): ?>
                                <textarea name="settings[<?php echo $setting['setting_key']; ?>]" class="form-control" rows="2"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                <?php else: ?>
                                <input type="text" name="settings[<?php echo $setting['setting_key']; ?>]" class="form-control" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                <?php endif; ?>
                                <p class="description"><?php echo htmlspecialchars($setting['description']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn-primary-custom">
                            <i class="fas fa-save"></i> Save Security Settings
                        </button>
                    </div>
                </form>
            </div>

            <?php elseif ($currentTab === 'platform'): ?>
            <!-- Platform Configuration Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-sliders-h"></i> Platform Configuration</h5>
            </div>
            <div class="card-body-custom">
                <form method="POST">
                    <input type="hidden" name="action" value="update_platform">
                    
                    <?php 
                    $categoryIcons = [
                        'general' => 'fa-globe',
                        'limits' => 'fa-tachometer-alt',
                        'jobs' => 'fa-briefcase',
                        'verification' => 'fa-user-check',
                        'features' => 'fa-magic'
                    ];
                    foreach ($configByCategory as $category => $configs): ?>
                    <div class="config-category">
                        <div class="category-header">
                            <div class="category-icon <?php echo $category; ?>">
                                <i class="fas <?php echo $categoryIcons[$category] ?? 'fa-cog'; ?>"></i>
                            </div>
                            <h6 class="category-title"><?php echo ucfirst($category); ?> Settings</h6>
                        </div>
                        <div class="settings-grid">
                            <?php foreach ($configs as $config): ?>
                            <div class="setting-item">
                                <label><?php echo ucwords(str_replace('_', ' ', $config['config_key'])); ?></label>
                                <?php if ($config['config_type'] === 'boolean'): ?>
                                <select name="config[<?php echo $config['config_key']; ?>]" class="form-select">
                                    <option value="1" <?php echo $config['config_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo $config['config_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                                <?php elseif ($config['config_type'] === 'text'): ?>
                                <textarea name="config[<?php echo $config['config_key']; ?>]" class="form-control" rows="2"><?php echo htmlspecialchars($config['config_value']); ?></textarea>
                                <?php elseif ($config['config_type'] === 'number'): ?>
                                <input type="number" name="config[<?php echo $config['config_key']; ?>]" class="form-control" value="<?php echo htmlspecialchars($config['config_value']); ?>">
                                <?php else: ?>
                                <input type="text" name="config[<?php echo $config['config_key']; ?>]" class="form-control" value="<?php echo htmlspecialchars($config['config_value']); ?>">
                                <?php endif; ?>
                                <p class="description"><?php echo htmlspecialchars($config['description']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn-primary-custom">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>

            <?php elseif ($currentTab === 'api'): ?>
            <!-- API / Integrations Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-plug"></i> API Keys & Integrations</h5>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#createApiKeyModal">
                    <i class="fas fa-plus"></i> Create API Key
                </button>
            </div>
            <div class="card-body-custom">
                <?php if (empty($apiKeys)): ?>
                <div class="empty-state">
                    <i class="fas fa-plug"></i>
                    <h5>No API Keys</h5>
                    <p>Create your first API key to enable integrations.</p>
                </div>
                <?php else: ?>
                <div class="api-grid">
                    <?php foreach ($apiKeys as $key): ?>
                    <div class="api-card <?php echo !$key['is_active'] ? 'inactive' : ''; ?>">
                        <div class="api-header">
                            <div>
                                <h6 class="api-name"><?php echo htmlspecialchars($key['key_name']); ?></h6>
                                <span class="api-type <?php echo $key['service_type']; ?>"><?php echo ucfirst($key['service_type']); ?></span>
                            </div>
                            <span class="api-status <?php echo $key['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $key['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="api-key-display">
                            <?php echo htmlspecialchars(substr($key['api_key'], 0, 20) . '...' . substr($key['api_key'], -8)); ?>
                        </div>
                        <div class="api-meta">
                            <span><i class="fas fa-tachometer-alt"></i> <?php echo number_format($key['rate_limit']); ?> req/hr</span>
                            <?php if ($key['last_used_at']): ?>
                            <span><i class="fas fa-clock"></i> Used <?php echo date('M j', strtotime($key['last_used_at'])); ?></span>
                            <?php endif; ?>
                            <?php if ($key['expires_at']): ?>
                            <span><i class="fas fa-calendar"></i> Exp. <?php echo date('M j, Y', strtotime($key['expires_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_api_key">
                                <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                <button type="submit" class="btn-action <?php echo $key['is_active'] ? 'btn-delete' : 'btn-run'; ?>">
                                    <i class="fas fa-<?php echo $key['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    <?php echo $key['is_active'] ? 'Disable' : 'Enable'; ?>
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete_api_key">
                                <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this API key?')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($currentTab === 'automation'): ?>
            <!-- Automation Rules Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-robot"></i> Automation Rules</h5>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#createAutomationModal">
                    <i class="fas fa-plus"></i> Create Rule
                </button>
            </div>
            <div class="card-body-custom">
                <div class="alert-custom info">
                    <i class="fas fa-info-circle"></i>
                    <span><strong>Inactive Users Auto-Delete:</strong> Users inactive for 2+ years will be automatically deleted when the cleanup rule is active.</span>
                </div>
                
                <?php if (empty($automationRules)): ?>
                <div class="empty-state">
                    <i class="fas fa-robot"></i>
                    <h5>No Automation Rules</h5>
                    <p>Create automation rules to streamline your platform management.</p>
                </div>
                <?php else: ?>
                <div class="automation-grid">
                    <?php foreach ($automationRules as $rule): 
                        $conditions = json_decode($rule['conditions'], true) ?? [];
                        $actions = json_decode($rule['actions'], true) ?? [];
                        $typeIcons = [
                            'cleanup' => 'fa-broom',
                            'archive' => 'fa-archive',
                            'notification' => 'fa-bell',
                            'status_change' => 'fa-sync-alt'
                        ];
                    ?>
                    <div class="automation-card <?php echo !$rule['is_active'] ? 'inactive' : ''; ?>" data-type="<?php echo $rule['rule_type']; ?>">
                        <div class="automation-icon">
                            <i class="fas <?php echo $typeIcons[$rule['rule_type']] ?? 'fa-cog'; ?>"></i>
                        </div>
                        <div class="automation-info">
                            <h6 class="automation-name"><?php echo htmlspecialchars($rule['rule_name']); ?></h6>
                            <div class="automation-meta">
                                <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $rule['rule_type'])); ?></span>
                                <?php if (isset($conditions['inactive_days'])): ?>
                                <span><i class="fas fa-clock"></i> <?php echo $conditions['inactive_days']; ?> days</span>
                                <?php endif; ?>
                                <span><i class="fas fa-database"></i> <?php echo ucfirst($conditions['entity'] ?? 'N/A'); ?></span>
                                <span><i class="fas fa-play-circle"></i> Ran <?php echo $rule['execution_count']; ?> times</span>
                            </div>
                        </div>
                        <div class="automation-actions">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="toggle_automation">
                                <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                <label class="toggle-switch">
                                    <input type="checkbox" <?php echo $rule['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="run_automation">
                                <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                <button type="submit" class="btn-action btn-run" onclick="return confirm('Run this automation now?')">
                                    <i class="fas fa-play"></i> Run
                                </button>
                            </form>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete_automation">
                                <input type="hidden" name="rule_id" value="<?php echo $rule['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this rule?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Role Modal -->
    <div class="modal fade" id="createRoleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Role</h5>
                    <button type="button" class="btn-close btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_role">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" name="role_name" class="form-control" placeholder="e.g., Content Manager" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Describe what this role can do..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permissions</label>
                            <div class="permission-grid">
                                <?php foreach ($availablePermissions as $key => $perm): ?>
                                <label class="permission-checkbox">
                                    <input type="checkbox" name="permissions[]" value="<?php echo $key; ?>" class="form-check-input">
                                    <span class="permission-label">
                                        <i class="fas <?php echo $perm['icon']; ?>"></i>
                                        <?php echo $perm['label']; ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modal-save">Create Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create API Key Modal -->
    <div class="modal fade" id="createApiKeyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Create API Key</h5>
                    <button type="button" class="btn-close btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_api_key">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Key Name</label>
                            <input type="text" name="key_name" class="form-control" placeholder="e.g., Mobile App API" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Type</label>
                            <select name="service_type" class="form-select" required>
                                <option value="internal">Internal (Your Apps)</option>
                                <option value="external">External (Third Party)</option>
                                <option value="webhook">Webhook</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rate Limit (requests/hour)</label>
                            <input type="number" name="rate_limit" class="form-control" value="1000" min="100" max="100000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiration Date (optional)</label>
                            <input type="date" name="expires_at" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modal-save">Create Key</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Automation Rule Modal -->
    <div class="modal fade" id="createAutomationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-robot me-2"></i>Create Automation Rule</h5>
                    <button type="button" class="btn-close btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_automation">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rule Name</label>
                            <input type="text" name="rule_name" class="form-control" placeholder="e.g., Monthly User Cleanup" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rule Type</label>
                            <select name="rule_type" class="form-select" required>
                                <option value="cleanup">Cleanup (Delete/Remove)</option>
                                <option value="archive">Archive</option>
                                <option value="notification">Send Notification</option>
                                <option value="status_change">Status Change</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Entity</label>
                            <select name="entity" class="form-select" required>
                                <option value="users">Users</option>
                                <option value="jobs">Job Postings</option>
                                <option value="applications">Applications</option>
                                <option value="companies">Companies</option>
                                <option value="messages">Messages</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Inactive Days Threshold</label>
                            <input type="number" name="inactive_days" class="form-control" value="730" min="30" max="3650">
                            <p class="description mt-2">Default: 730 days (2 years) for inactive user cleanup</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Apply To Roles</label>
                            <select name="roles[]" class="form-select" multiple>
                                <option value="employee" selected>Jobseeker (employee)</option>
                                <option value="employer" selected>Employer</option>
                                <option value="admin">Admin</option>
                            </select>
                            <p class="description mt-2">Select which user roles the rule should apply to. Hold Ctrl/Cmd to select multiple.</p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Action</label>
                            <select name="rule_action" class="form-select" required>
                                <option value="delete">Delete Permanently</option>
                                <option value="archive">Archive</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="notify">Send Warning Email</option>
                            </select>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="notify" class="form-check-input" id="notifyCheck" checked>
                            <label class="form-check-label" for="notifyCheck">Send notification before action</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modal-save">Create Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Copy API key to clipboard
        document.querySelectorAll('.api-key-display').forEach(el => {
            el.style.cursor = 'pointer';
            el.title = 'Click to copy';
            el.addEventListener('click', function() {
                navigator.clipboard.writeText(this.textContent.trim());
                const original = this.textContent;
                this.textContent = 'Copied!';
                setTimeout(() => this.textContent = original, 1500);
            });
        });
    </script>
</body>
</html>
