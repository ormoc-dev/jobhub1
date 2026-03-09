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
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --cyan: #06b6d4;
            --cyan-light: #22d3ee;
            --emerald: #10b981;
            --amber: #f59e0b;
            --rose: #f43f5e;
            --violet: #8b5cf6;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
            --slate-100: #f1f5f9;
        }
        
        .system-page {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            min-height: 100vh;
        }
        
        .system-page .admin-main-content {
            background: transparent;
            padding: 24px 32px;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 28px;
        }
        
        .page-header h1 {
            font-weight: 800;
            font-size: 1.75rem;
            color: #fff;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header h1 i {
            background: linear-gradient(135deg, var(--primary), var(--cyan));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .page-header p {
            color: var(--slate-500);
            margin: 0;
            font-size: 0.95rem;
        }
        
        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(3, 1fr); }
        }
        
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        .stat-box {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-4px);
            border-color: rgba(99, 102, 241, 0.3);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.15);
        }
        
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .stat-box.roles .stat-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .stat-box.security .stat-icon { background: linear-gradient(135deg, var(--emerald), #34d399); color: white; }
        .stat-box.api .stat-icon { background: linear-gradient(135deg, var(--cyan), var(--cyan-light)); color: white; }
        .stat-box.automation .stat-icon { background: linear-gradient(135deg, var(--amber), #fbbf24); color: white; }
        .stat-box.config .stat-icon { background: linear-gradient(135deg, var(--violet), #a78bfa); color: white; }
        
        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
            line-height: 1;
        }
        
        .stat-info span {
            font-size: 0.8rem;
            color: var(--slate-500);
            font-weight: 500;
        }
        
        /* Alert Styles */
        .alert-custom {
            border: none;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-custom.success {
            background: rgba(16, 185, 129, 0.15);
            border-left: 4px solid var(--emerald);
            color: #6ee7b7;
        }
        
        .alert-custom.danger {
            background: rgba(244, 63, 94, 0.15);
            border-left: 4px solid var(--rose);
            color: #fda4af;
        }
        
        /* Tab Navigation */
        .tab-nav {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            background: rgba(30, 41, 59, 0.5);
            padding: 6px;
            border-radius: 16px;
            flex-wrap: wrap;
        }
        
        .tab-link {
            padding: 14px 22px;
            border-radius: 12px;
            color: var(--slate-400);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }
        
        .tab-link:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
        }
        
        .tab-link.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }
        
        .tab-link i {
            font-size: 1rem;
        }
        
        /* Main Card */
        .main-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .card-header-custom h5 {
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header-custom h5 i {
            color: var(--primary-light);
        }
        
        .card-body-custom {
            padding: 24px;
        }
        
        /* Role Cards */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .role-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--cyan));
        }
        
        .role-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            transform: translateY(-4px);
        }
        
        .role-card.system::before {
            background: linear-gradient(90deg, var(--amber), #fbbf24);
        }
        
        .role-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
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
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        
        .role-card.system .role-icon {
            background: linear-gradient(135deg, var(--amber), #fbbf24);
        }
        
        .role-name {
            color: #fff;
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
        }
        
        .role-badge.system {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
        }
        
        .role-badge.custom {
            background: rgba(99, 102, 241, 0.15);
            color: var(--primary-light);
        }
        
        .role-description {
            color: var(--slate-400);
            font-size: 0.85rem;
            margin-bottom: 16px;
            line-height: 1.5;
        }
        
        .role-permissions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
        }
        
        .permission-tag {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary-light);
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .permission-tag.all {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(251, 191, 36, 0.2));
            color: #fbbf24;
        }
        
        .role-actions {
            display: flex;
            gap: 8px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        
        /* Security Settings */
        .settings-section {
            margin-bottom: 32px;
        }
        
        .settings-section:last-child {
            margin-bottom: 0;
        }
        
        .section-title {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .section-title i {
            color: var(--primary-light);
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        
        .setting-item {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px;
            padding: 16px;
        }
        
        .setting-item label {
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            display: block;
            margin-bottom: 8px;
        }
        
        .setting-item .description {
            color: var(--slate-500);
            font-size: 0.75rem;
            margin-top: 8px;
        }
        
        /* API Key Cards */
        .api-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 20px;
        }
        
        .api-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .api-card:hover {
            border-color: rgba(6, 182, 212, 0.3);
        }
        
        .api-card.inactive {
            opacity: 0.6;
        }
        
        .api-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        
        .api-name {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 6px 0;
        }
        
        .api-type {
            font-size: 0.7rem;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .api-type.internal { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .api-type.external { background: rgba(6, 182, 212, 0.15); color: var(--cyan-light); }
        .api-type.webhook { background: rgba(139, 92, 246, 0.15); color: #a78bfa; }
        
        .api-status {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .api-status.active { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .api-status.inactive { background: rgba(244, 63, 94, 0.15); color: #fb7185; }
        
        .api-key-display {
            background: var(--slate-900);
            border-radius: 8px;
            padding: 12px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.75rem;
            color: var(--cyan-light);
            margin-bottom: 16px;
            overflow: hidden;
            text-overflow: ellipsis;
            word-break: break-all;
        }
        
        .api-meta {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            color: var(--slate-500);
            margin-bottom: 16px;
        }
        
        .api-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Automation Rules */
        .automation-grid {
            display: grid;
            gap: 16px;
        }
        
        .automation-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }
        
        .automation-card:hover {
            border-color: rgba(245, 158, 11, 0.3);
        }
        
        .automation-card.inactive {
            opacity: 0.6;
        }
        
        .automation-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        
        .automation-card[data-type="cleanup"] .automation-icon { background: linear-gradient(135deg, var(--rose), #fb7185); color: white; }
        .automation-card[data-type="archive"] .automation-icon { background: linear-gradient(135deg, var(--violet), #a78bfa); color: white; }
        .automation-card[data-type="notification"] .automation-icon { background: linear-gradient(135deg, var(--cyan), var(--cyan-light)); color: white; }
        .automation-card[data-type="status_change"] .automation-icon { background: linear-gradient(135deg, var(--emerald), #34d399); color: white; }
        
        .automation-info {
            flex: 1;
        }
        
        .automation-name {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 6px 0;
        }
        
        .automation-meta {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            color: var(--slate-500);
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
        }
        
        /* Toggle Switch */
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
            background-color: var(--slate-700);
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
        }
        
        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-custom:hover {
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
            color: white;
        }
        
        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-action:hover { transform: translateY(-2px); }
        
        .btn-edit { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .btn-edit:hover { background: rgba(99, 102, 241, 0.25); color: var(--primary-light); }
        
        .btn-run { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .btn-run:hover { background: rgba(16, 185, 129, 0.25); color: #34d399; }
        
        .btn-delete { background: rgba(244, 63, 94, 0.15); color: #fb7185; }
        .btn-delete:hover { background: rgba(244, 63, 94, 0.25); color: #fb7185; }
        
        /* Form Elements */
        .form-control, .form-select {
            background: var(--slate-900);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            padding: 12px 16px;
            font-size: 0.9rem;
        }
        
        .form-control:focus, .form-select:focus {
            background: var(--slate-900);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            color: #fff;
        }
        
        .form-control::placeholder { color: var(--slate-500); }
        
        .form-label {
            color: var(--slate-300);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        
        .form-check-input {
            background-color: var(--slate-700);
            border-color: var(--slate-600);
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        /* Modal Styles */
        .modal-content {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px 20px 0 0;
            padding: 20px 24px;
            border: none;
        }
        
        .modal-title {
            color: white;
            font-weight: 700;
        }
        
        .modal-body {
            padding: 24px;
            color: var(--slate-300);
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        
        .btn-modal-cancel {
            background: var(--slate-700);
            color: var(--slate-300);
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-modal-cancel:hover {
            background: var(--slate-600);
            color: #fff;
        }
        
        .btn-modal-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-modal-save:hover {
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.3);
            color: white;
        }
        
        /* Config Categories */
        .config-category {
            background: rgba(15, 23, 42, 0.3);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .config-category:last-child {
            margin-bottom: 0;
        }
        
        .category-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
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
        
        .category-icon.general { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .category-icon.limits { background: linear-gradient(135deg, var(--amber), #fbbf24); color: white; }
        .category-icon.jobs { background: linear-gradient(135deg, var(--cyan), var(--cyan-light)); color: white; }
        .category-icon.verification { background: linear-gradient(135deg, var(--emerald), #34d399); color: white; }
        .category-icon.features { background: linear-gradient(135deg, var(--violet), #a78bfa); color: white; }
        
        .category-title {
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            margin: 0;
            text-transform: capitalize;
        }
        
        /* Permission Checkbox Grid */
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .permission-checkbox {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 10px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .permission-checkbox:hover {
            border-color: rgba(99, 102, 241, 0.3);
        }
        
        .permission-checkbox input:checked ~ .permission-label {
            color: var(--primary-light);
        }
        
        .permission-label {
            color: var(--slate-300);
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .permission-label i {
            color: var(--slate-500);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 60px;
            color: var(--slate-700);
            margin-bottom: 20px;
        }
        
        .empty-state h5 {
            color: var(--slate-300);
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .empty-state p {
            color: var(--slate-500);
            font-size: 0.9rem;
        }
    </style>
</head>
<body class="admin-layout system-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="page-header">
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
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                <div class="alert-custom" style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid var(--amber); color: #fbbf24; margin-bottom: 24px;">
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
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                            <label class="form-check-label" for="notifyCheck" style="color: var(--slate-300);">Send notification before action</label>
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
