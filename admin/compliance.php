<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Note: Tables (privacy_logs, terms_policies, policy_acceptances, audit_trails, data_retention_policies, data_deletion_requests) 
// are defined in sql/all_additional_tables.sql

// Insert default data retention policies
$defaultRetentionPolicies = [
    ['Inactive User Accounts', 'user_accounts', 730, 'delete', true, false, 30, 'GDPR Article 17 - Right to erasure after 2 years of inactivity'],
    ['Old Job Applications', 'job_applications', 365, 'anonymize', true, false, 14, 'Data minimization - applications older than 1 year'],
    ['Message History', 'messages', 180, 'archive', true, false, 7, 'Business communication retention policy'],
    ['Activity Logs', 'activity_logs', 90, 'delete', true, true, 0, 'Operational data - 90 day rolling window'],
    ['Uploaded Files', 'uploaded_files', 365, 'review', true, false, 30, 'Document retention for compliance verification'],
    ['Session Data', 'session_data', 30, 'delete', true, true, 0, 'Security best practice - short session retention'],
    ['Analytics Data', 'analytics', 365, 'anonymize', true, true, 0, 'Statistical analysis - anonymized after 1 year'],
    ['System Backups', 'backups', 90, 'delete', true, true, 7, 'Disaster recovery - 90 day backup retention']
];

foreach ($defaultRetentionPolicies as $policy) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO data_retention_policies (policy_name, data_type, retention_period, action_after_expiry, is_active, auto_execute, notification_days, legal_basis) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute($policy);
}

// Insert default policies if none exist
$existingPolicies = $pdo->query("SELECT COUNT(*) FROM terms_policies")->fetchColumn();
if ($existingPolicies == 0) {
    $defaultPolicies = [
        ['terms_of_service', 'Terms of Service', 'Welcome to WORKLINK. By accessing or using our platform, you agree to be bound by these Terms of Service. Our platform connects job seekers with employers, providing recruitment and employment services.

1. **Account Registration**: You must provide accurate information when creating an account. You are responsible for maintaining the confidentiality of your credentials.

2. **User Conduct**: Users must not post false information, harass others, or engage in any illegal activities on the platform.

3. **Employer Responsibilities**: Employers must provide accurate job descriptions and comply with all applicable employment laws.

4. **Job Seeker Responsibilities**: Job seekers must provide truthful information in their profiles and applications.

5. **Intellectual Property**: All content on WORKLINK is protected by intellectual property laws.

6. **Limitation of Liability**: WORKLINK is not responsible for employment decisions made by employers or the accuracy of job listings.

7. **Termination**: We reserve the right to terminate accounts that violate these terms.', '1.0', true],
        
        ['privacy_policy', 'Privacy Policy', 'WORKLINK is committed to protecting your privacy. This policy explains how we collect, use, and safeguard your personal information.

**Data We Collect**:
- Personal identification information (name, email, phone)
- Professional information (resume, work history, education)
- Usage data and analytics
- Communication records

**How We Use Your Data**:
- To provide our recruitment services
- To match job seekers with relevant opportunities
- To communicate important updates
- To improve our platform

**Data Sharing**:
- With employers when you apply for jobs
- With service providers who assist our operations
- When required by law

**Your Rights**:
- Access your personal data
- Request data correction or deletion
- Opt-out of marketing communications
- Data portability

**Data Security**:
We implement industry-standard security measures to protect your information.

**Contact Us**:
For privacy inquiries, contact our Data Protection Officer.', '1.0', true],
        
        ['cookie_policy', 'Cookie Policy', 'WORKLINK uses cookies to enhance your browsing experience.

**Essential Cookies**: Required for basic platform functionality.

**Analytics Cookies**: Help us understand how users interact with our platform.

**Preference Cookies**: Remember your settings and preferences.

**Marketing Cookies**: Used to deliver relevant advertisements.

You can manage cookie preferences in your browser settings.', '1.0', true],
        
        ['data_processing', 'Data Processing Agreement', 'This Data Processing Agreement outlines how WORKLINK processes personal data in compliance with GDPR and other applicable data protection regulations.

**Data Controller**: The user or employer who uploads data to WORKLINK.

**Data Processor**: WORKLINK processes data on behalf of controllers.

**Processing Activities**: Resume storage, job matching, communication facilitation.

**Security Measures**: Encryption, access controls, regular audits.

**Sub-processors**: Cloud hosting providers, email services.

**Data Breach Notification**: We will notify affected parties within 72 hours of discovering a breach.', '1.0', true]
    ];
    
    foreach ($defaultPolicies as $policy) {
        $stmt = $pdo->prepare("INSERT INTO terms_policies (policy_type, title, content, version, effective_date, is_active, requires_acceptance, created_by) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?)");
        $stmt->execute([$policy[0], $policy[1], $policy[2], $policy[3], $policy[4], true, $_SESSION['user_id'] ?? 1]);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Privacy Log Actions
    if ($action === 'export_privacy_logs') {
        $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $_POST['date_to'] ?? date('Y-m-d');
        $actionType = $_POST['action_type_filter'] ?? 'all';
        
        // Log this export action
        $stmt = $pdo->prepare("INSERT INTO privacy_logs (user_id, action_type, description, ip_address, user_agent) VALUES (?, 'data_access', ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], "Privacy logs exported from $dateFrom to $dateTo", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        $message = 'Privacy logs export initiated. Download will begin shortly.';
    }
    
    // Terms & Policy Actions
    elseif ($action === 'create_policy') {
        $policyType = $_POST['policy_type'];
        $title = sanitizeInput($_POST['title']);
        $content = $_POST['content'];
        $version = sanitizeInput($_POST['version']);
        $effectiveDate = $_POST['effective_date'];
        $requiresAcceptance = isset($_POST['requires_acceptance']) ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO terms_policies (policy_type, title, content, version, effective_date, requires_acceptance, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$policyType, $title, $content, $version, $effectiveDate, $requiresAcceptance, $_SESSION['user_id']])) {
            // Log audit trail
            $stmt = $pdo->prepare("INSERT INTO audit_trails (admin_id, action_category, action_type, entity_type, entity_id, description, ip_address, risk_level) VALUES (?, 'compliance', 'policy_created', 'terms_policies', LAST_INSERT_ID(), ?, ?, 'medium')");
            $stmt->execute([$_SESSION['user_id'], "Created new $policyType: $title (v$version)", $_SERVER['REMOTE_ADDR']]);
            
            $message = 'Policy created successfully!';
        } else {
            $error = 'Failed to create policy.';
        }
    }
    elseif ($action === 'update_policy') {
        $policyId = (int)$_POST['policy_id'];
        $title = sanitizeInput($_POST['title']);
        $content = $_POST['content'];
        $version = sanitizeInput($_POST['version']);
        $effectiveDate = $_POST['effective_date'];
        $requiresAcceptance = isset($_POST['requires_acceptance']) ? 1 : 0;
        
        // Get old values for audit
        $oldPolicy = $pdo->query("SELECT * FROM terms_policies WHERE id = $policyId")->fetch();
        
        $stmt = $pdo->prepare("UPDATE terms_policies SET title = ?, content = ?, version = ?, effective_date = ?, requires_acceptance = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$title, $content, $version, $effectiveDate, $requiresAcceptance, $policyId])) {
            // Log audit trail
            $stmt = $pdo->prepare("INSERT INTO audit_trails (admin_id, action_category, action_type, entity_type, entity_id, old_values, new_values, description, ip_address, risk_level) VALUES (?, 'compliance', 'policy_updated', 'terms_policies', ?, ?, ?, ?, ?, 'medium')");
            $stmt->execute([
                $_SESSION['user_id'], 
                $policyId, 
                json_encode(['version' => $oldPolicy['version']]), 
                json_encode(['version' => $version]),
                "Updated policy: $title to version $version",
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $message = 'Policy updated successfully!';
        } else {
            $error = 'Failed to update policy.';
        }
    }
    elseif ($action === 'toggle_policy') {
        $policyId = (int)$_POST['policy_id'];
        
        // If activating, deactivate other policies of same type
        $policy = $pdo->query("SELECT * FROM terms_policies WHERE id = $policyId")->fetch();
        if (!$policy['is_active']) {
            $pdo->prepare("UPDATE terms_policies SET is_active = FALSE WHERE policy_type = ? AND id != ?")->execute([$policy['policy_type'], $policyId]);
        }
        
        $stmt = $pdo->prepare("UPDATE terms_policies SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$policyId]);
        
        $newStatus = !$policy['is_active'] ? 'activated' : 'deactivated';
        
        // Log audit trail
        $stmt = $pdo->prepare("INSERT INTO audit_trails (admin_id, action_category, action_type, entity_type, entity_id, description, ip_address, risk_level) VALUES (?, 'compliance', 'policy_status_changed', 'terms_policies', ?, ?, ?, 'high')");
        $stmt->execute([$_SESSION['user_id'], $policyId, "Policy {$policy['title']} $newStatus", $_SERVER['REMOTE_ADDR']]);
        
        $message = "Policy $newStatus successfully!";
    }
    elseif ($action === 'delete_policy') {
        $policyId = (int)$_POST['policy_id'];
        $policy = $pdo->query("SELECT * FROM terms_policies WHERE id = $policyId")->fetch();
        
        if ($policy['is_active']) {
            $error = 'Cannot delete an active policy. Deactivate it first.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM terms_policies WHERE id = ?");
            $stmt->execute([$policyId]);
            
            // Log audit trail
            $stmt = $pdo->prepare("INSERT INTO audit_trails (admin_id, action_category, action_type, entity_type, entity_id, description, ip_address, risk_level) VALUES (?, 'compliance', 'policy_deleted', 'terms_policies', ?, ?, ?, 'critical')");
            $stmt->execute([$_SESSION['user_id'], $policyId, "Deleted policy: {$policy['title']}", $_SERVER['REMOTE_ADDR']]);
            
            $message = 'Policy deleted successfully!';
        }
    }
    
    // Data Retention Policy Actions
    elseif ($action === 'update_retention') {
        $policyId = (int)$_POST['policy_id'];
        $retentionPeriod = (int)$_POST['retention_period'];
        $actionAfterExpiry = $_POST['action_after_expiry'];
        $autoExecute = isset($_POST['auto_execute']) ? 1 : 0;
        $notificationDays = (int)$_POST['notification_days'];
        
        $stmt = $pdo->prepare("UPDATE data_retention_policies SET retention_period = ?, action_after_expiry = ?, auto_execute = ?, notification_days = ? WHERE id = ?");
        if ($stmt->execute([$retentionPeriod, $actionAfterExpiry, $autoExecute, $notificationDays, $policyId])) {
            $message = 'Retention policy updated successfully!';
        }
    }
    elseif ($action === 'toggle_retention') {
        $policyId = (int)$_POST['policy_id'];
        $stmt = $pdo->prepare("UPDATE data_retention_policies SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$policyId]);
        $message = 'Retention policy status updated!';
    }
    elseif ($action === 'execute_retention') {
        $policyId = (int)$_POST['policy_id'];
        // Simulate execution
        $stmt = $pdo->prepare("UPDATE data_retention_policies SET last_executed_at = NOW() WHERE id = ?");
        $stmt->execute([$policyId]);
        
        // Log audit trail
        $policy = $pdo->query("SELECT * FROM data_retention_policies WHERE id = $policyId")->fetch();
        $stmt = $pdo->prepare("INSERT INTO audit_trails (admin_id, action_category, action_type, entity_type, entity_id, description, ip_address, risk_level) VALUES (?, 'compliance', 'retention_executed', 'data_retention_policies', ?, ?, ?, 'critical')");
        $stmt->execute([$_SESSION['user_id'], $policyId, "Executed retention policy: {$policy['policy_name']}", $_SERVER['REMOTE_ADDR']]);
        
        $message = 'Retention policy executed successfully!';
    }
    
    // Deletion Request Actions
    elseif ($action === 'process_deletion_request') {
        $requestId = (int)$_POST['request_id'];
        $newStatus = $_POST['new_status'];
        $adminNotes = sanitizeInput($_POST['admin_notes'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE data_deletion_requests SET status = ?, admin_notes = ?, processed_by = ?, processed_at = NOW() WHERE id = ?");
        if ($stmt->execute([$newStatus, $adminNotes, $_SESSION['user_id'], $requestId])) {
            // Log audit trail
            $stmt = $pdo->prepare("INSERT INTO audit_trails (admin_id, action_category, action_type, entity_type, entity_id, description, ip_address, risk_level) VALUES (?, 'compliance', 'deletion_request_processed', 'data_deletion_requests', ?, ?, ?, 'critical')");
            $stmt->execute([$_SESSION['user_id'], $requestId, "Deletion request #$requestId marked as $newStatus", $_SERVER['REMOTE_ADDR']]);
            
            $message = "Request marked as $newStatus successfully!";
        }
    }
}

// Current tab
$currentTab = $_GET['tab'] ?? 'privacy';

// Fetch data based on tab
$privacyLogs = [];
$policies = [];
$auditTrails = [];
$retentionPolicies = [];
$deletionRequests = [];

// Privacy Logs with filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$actionTypeFilter = $_GET['action_type_filter'] ?? 'all';

$privacyLogsQuery = "SELECT pl.*, u.username, u.email FROM privacy_logs pl LEFT JOIN users u ON pl.user_id = u.id WHERE DATE(pl.created_at) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];
if ($actionTypeFilter !== 'all') {
    $privacyLogsQuery .= " AND pl.action_type = ?";
    $params[] = $actionTypeFilter;
}
$privacyLogsQuery .= " ORDER BY pl.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($privacyLogsQuery);
$stmt->execute($params);
$privacyLogs = $stmt->fetchAll();

// Terms & Policies
$policies = $pdo->query("SELECT tp.*, u.username as created_by_name FROM terms_policies tp LEFT JOIN users u ON tp.created_by = u.id ORDER BY tp.policy_type, tp.is_active DESC, tp.created_at DESC")->fetchAll();

// Group policies by type
$policiesByType = [];
foreach ($policies as $policy) {
    $policiesByType[$policy['policy_type']][] = $policy;
}

// Audit Trails with filters
$auditCategory = $_GET['audit_category'] ?? 'all';
$auditRisk = $_GET['audit_risk'] ?? 'all';

$auditQuery = "SELECT at.*, u.username as user_name, a.username as admin_name FROM audit_trails at LEFT JOIN users u ON at.user_id = u.id LEFT JOIN users a ON at.admin_id = a.id WHERE DATE(at.created_at) BETWEEN ? AND ?";
$auditParams = [$dateFrom, $dateTo];
if ($auditCategory !== 'all') {
    $auditQuery .= " AND at.action_category = ?";
    $auditParams[] = $auditCategory;
}
if ($auditRisk !== 'all') {
    $auditQuery .= " AND at.risk_level = ?";
    $auditParams[] = $auditRisk;
}
$auditQuery .= " ORDER BY at.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($auditQuery);
$stmt->execute($auditParams);
$auditTrails = $stmt->fetchAll();

// Data Retention Policies
$retentionPolicies = $pdo->query("SELECT * FROM data_retention_policies ORDER BY is_active DESC, data_type")->fetchAll();

// Deletion Requests
$deletionRequests = $pdo->query("SELECT dr.*, u.username, u.email, p.username as processed_by_name FROM data_deletion_requests dr LEFT JOIN users u ON dr.user_id = u.id LEFT JOIN users p ON dr.processed_by = p.id ORDER BY dr.status = 'pending' DESC, dr.requested_at DESC LIMIT 50")->fetchAll();

// Statistics
$stats = [
    'privacy_logs_today' => $pdo->query("SELECT COUNT(*) FROM privacy_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'privacy_logs_month' => $pdo->query("SELECT COUNT(*) FROM privacy_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'active_policies' => $pdo->query("SELECT COUNT(*) FROM terms_policies WHERE is_active = TRUE")->fetchColumn(),
    'total_policies' => $pdo->query("SELECT COUNT(*) FROM terms_policies")->fetchColumn(),
    'audit_events_today' => $pdo->query("SELECT COUNT(*) FROM audit_trails WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'high_risk_events' => $pdo->query("SELECT COUNT(*) FROM audit_trails WHERE risk_level IN ('high', 'critical') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
    'active_retention' => $pdo->query("SELECT COUNT(*) FROM data_retention_policies WHERE is_active = TRUE")->fetchColumn(),
    'pending_deletions' => $pdo->query("SELECT COUNT(*) FROM data_deletion_requests WHERE status = 'pending'")->fetchColumn(),
];

// Policy types for forms
$policyTypes = [
    'terms_of_service' => ['icon' => 'fa-file-contract', 'label' => 'Terms of Service', 'color' => 'primary'],
    'privacy_policy' => ['icon' => 'fa-user-shield', 'label' => 'Privacy Policy', 'color' => 'cyan'],
    'cookie_policy' => ['icon' => 'fa-cookie-bite', 'label' => 'Cookie Policy', 'color' => 'amber'],
    'acceptable_use' => ['icon' => 'fa-check-circle', 'label' => 'Acceptable Use', 'color' => 'emerald'],
    'data_processing' => ['icon' => 'fa-database', 'label' => 'Data Processing', 'color' => 'violet'],
    'community_guidelines' => ['icon' => 'fa-users', 'label' => 'Community Guidelines', 'color' => 'rose']
];

// Action types for privacy logs
$actionTypes = [
    'data_access' => ['icon' => 'fa-eye', 'label' => 'Data Access', 'color' => 'primary'],
    'data_export' => ['icon' => 'fa-download', 'label' => 'Data Export', 'color' => 'cyan'],
    'data_deletion' => ['icon' => 'fa-trash-alt', 'label' => 'Data Deletion', 'color' => 'rose'],
    'consent_change' => ['icon' => 'fa-toggle-on', 'label' => 'Consent Change', 'color' => 'amber'],
    'profile_update' => ['icon' => 'fa-user-edit', 'label' => 'Profile Update', 'color' => 'emerald'],
    'login_attempt' => ['icon' => 'fa-sign-in-alt', 'label' => 'Login Attempt', 'color' => 'violet'],
    'password_change' => ['icon' => 'fa-key', 'label' => 'Password Change', 'color' => 'slate']
];

// Audit categories
$auditCategories = [
    'user_management' => ['icon' => 'fa-users', 'label' => 'User Management'],
    'content_moderation' => ['icon' => 'fa-shield-alt', 'label' => 'Content Moderation'],
    'system_config' => ['icon' => 'fa-cogs', 'label' => 'System Config'],
    'data_access' => ['icon' => 'fa-database', 'label' => 'Data Access'],
    'security' => ['icon' => 'fa-lock', 'label' => 'Security'],
    'compliance' => ['icon' => 'fa-balance-scale', 'label' => 'Compliance'],
    'financial' => ['icon' => 'fa-dollar-sign', 'label' => 'Financial']
];

// Data types for retention
$dataTypes = [
    'user_accounts' => ['icon' => 'fa-user', 'label' => 'User Accounts'],
    'job_applications' => ['icon' => 'fa-file-alt', 'label' => 'Job Applications'],
    'messages' => ['icon' => 'fa-envelope', 'label' => 'Messages'],
    'activity_logs' => ['icon' => 'fa-history', 'label' => 'Activity Logs'],
    'uploaded_files' => ['icon' => 'fa-folder', 'label' => 'Uploaded Files'],
    'session_data' => ['icon' => 'fa-clock', 'label' => 'Session Data'],
    'analytics' => ['icon' => 'fa-chart-line', 'label' => 'Analytics'],
    'backups' => ['icon' => 'fa-hdd', 'label' => 'Backups']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance & Audit - WORKLINK Admin</title>
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
            --emerald-light: #34d399;
            --amber: #f59e0b;
            --amber-light: #fbbf24;
            --rose: #f43f5e;
            --rose-light: #fb7185;
            --violet: #8b5cf6;
            --violet-light: #a78bfa;
            --teal: #14b8a6;
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
        
        .compliance-page {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            min-height: 100vh;
        }
        
        .compliance-page .admin-main-content {
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
            background: linear-gradient(135deg, var(--teal), var(--cyan));
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
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        
        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 576px) {
            .stats-row { grid-template-columns: 1fr; }
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
            border-color: rgba(20, 184, 166, 0.3);
            box-shadow: 0 12px 40px rgba(20, 184, 166, 0.15);
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
        
        .stat-box.privacy .stat-icon { background: linear-gradient(135deg, var(--teal), var(--cyan)); color: white; }
        .stat-box.policies .stat-icon { background: linear-gradient(135deg, var(--violet), var(--violet-light)); color: white; }
        .stat-box.audit .stat-icon { background: linear-gradient(135deg, var(--amber), var(--amber-light)); color: white; }
        .stat-box.retention .stat-icon { background: linear-gradient(135deg, var(--rose), var(--rose-light)); color: white; }
        
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
        
        .stat-badge {
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .stat-badge.warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--amber-light);
        }
        
        .stat-badge.danger {
            background: rgba(244, 63, 94, 0.2);
            color: var(--rose-light);
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
        
        .alert-custom.info {
            background: rgba(6, 182, 212, 0.15);
            border-left: 4px solid var(--cyan);
            color: var(--cyan-light);
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
            background: linear-gradient(135deg, var(--teal), var(--cyan));
            color: white;
            box-shadow: 0 4px 20px rgba(20, 184, 166, 0.4);
        }
        
        .tab-link i {
            font-size: 1rem;
        }
        
        .tab-link .badge {
            background: rgba(244, 63, 94, 0.3);
            color: var(--rose-light);
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .tab-link.active .badge {
            background: rgba(255,255,255,0.2);
            color: white;
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
            color: var(--teal);
        }
        
        .card-body-custom {
            padding: 24px;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .filter-group label {
            color: var(--slate-400);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input,
        .filter-group select {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: #fff;
            padding: 10px 14px;
            font-size: 0.85rem;
            min-width: 140px;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--teal);
            outline: none;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
        }
        
        /* Log Table */
        .log-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .log-table thead th {
            background: rgba(15, 23, 42, 0.5);
            color: var(--slate-400);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border: none;
        }
        
        .log-table thead th:first-child {
            border-radius: 10px 0 0 10px;
        }
        
        .log-table thead th:last-child {
            border-radius: 0 10px 10px 0;
        }
        
        .log-table tbody tr {
            background: rgba(15, 23, 42, 0.3);
            transition: all 0.3s ease;
        }
        
        .log-table tbody tr:hover {
            background: rgba(15, 23, 42, 0.6);
            transform: translateX(4px);
        }
        
        .log-table tbody td {
            padding: 14px 16px;
            color: var(--slate-300);
            font-size: 0.85rem;
            border: none;
            vertical-align: middle;
        }
        
        .log-table tbody td:first-child {
            border-radius: 10px 0 0 10px;
        }
        
        .log-table tbody td:last-child {
            border-radius: 0 10px 10px 0;
        }
        
        /* Action Type Badges */
        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .action-badge.primary { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .action-badge.cyan { background: rgba(6, 182, 212, 0.15); color: var(--cyan-light); }
        .action-badge.rose { background: rgba(244, 63, 94, 0.15); color: var(--rose-light); }
        .action-badge.amber { background: rgba(245, 158, 11, 0.15); color: var(--amber-light); }
        .action-badge.emerald { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .action-badge.violet { background: rgba(139, 92, 246, 0.15); color: var(--violet-light); }
        .action-badge.slate { background: rgba(100, 116, 139, 0.15); color: var(--slate-400); }
        .action-badge.teal { background: rgba(20, 184, 166, 0.15); color: var(--teal); }
        
        /* Risk Level Badges */
        .risk-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .risk-badge.low { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .risk-badge.medium { background: rgba(245, 158, 11, 0.15); color: var(--amber-light); }
        .risk-badge.high { background: rgba(244, 63, 94, 0.15); color: var(--rose-light); }
        .risk-badge.critical { background: rgba(244, 63, 94, 0.3); color: #ff6b6b; border: 1px solid rgba(244, 63, 94, 0.5); }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.active { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .status-badge.inactive { background: rgba(100, 116, 139, 0.15); color: var(--slate-400); }
        .status-badge.pending { background: rgba(245, 158, 11, 0.15); color: var(--amber-light); }
        .status-badge.processing { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .status-badge.completed { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .status-badge.rejected { background: rgba(244, 63, 94, 0.15); color: var(--rose-light); }
        
        /* Policy Cards */
        .policy-section {
            margin-bottom: 32px;
        }
        
        .policy-section:last-child {
            margin-bottom: 0;
        }
        
        .policy-type-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .policy-type-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .policy-type-icon.primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .policy-type-icon.cyan { background: linear-gradient(135deg, var(--cyan), var(--cyan-light)); color: white; }
        .policy-type-icon.amber { background: linear-gradient(135deg, var(--amber), var(--amber-light)); color: white; }
        .policy-type-icon.emerald { background: linear-gradient(135deg, var(--emerald), var(--emerald-light)); color: white; }
        .policy-type-icon.violet { background: linear-gradient(135deg, var(--violet), var(--violet-light)); color: white; }
        .policy-type-icon.rose { background: linear-gradient(135deg, var(--rose), var(--rose-light)); color: white; }
        
        .policy-type-title {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
        }
        
        .policy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 16px;
        }
        
        .policy-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .policy-card:hover {
            border-color: rgba(20, 184, 166, 0.3);
            transform: translateY(-2px);
        }
        
        .policy-card.active {
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .policy-card.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--emerald), var(--teal));
            border-radius: 14px 14px 0 0;
        }
        
        .policy-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .policy-title {
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            margin: 0 0 4px 0;
        }
        
        .policy-version {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            color: var(--slate-500);
        }
        
        .policy-content-preview {
            color: var(--slate-400);
            font-size: 0.8rem;
            line-height: 1.5;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .policy-meta {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            color: var(--slate-500);
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        
        .policy-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .policy-actions {
            display: flex;
            gap: 8px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        
        /* Retention Policy Cards */
        .retention-grid {
            display: grid;
            gap: 16px;
        }
        
        .retention-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }
        
        .retention-card:hover {
            border-color: rgba(244, 63, 94, 0.3);
        }
        
        .retention-card.inactive {
            opacity: 0.6;
        }
        
        .retention-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--rose), var(--rose-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
            flex-shrink: 0;
        }
        
        .retention-info {
            flex: 1;
        }
        
        .retention-name {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 6px 0;
        }
        
        .retention-meta {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            color: var(--slate-500);
            flex-wrap: wrap;
        }
        
        .retention-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .retention-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        /* Deletion Request Cards */
        .deletion-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        
        .deletion-card:hover {
            border-color: rgba(244, 63, 94, 0.3);
        }
        
        .deletion-card.pending {
            border-left: 4px solid var(--amber);
        }
        
        .deletion-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .deletion-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .deletion-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--rose), var(--violet));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }
        
        .deletion-user-info h6 {
            color: #fff;
            font-weight: 600;
            margin: 0 0 4px 0;
            font-size: 0.95rem;
        }
        
        .deletion-user-info span {
            color: var(--slate-500);
            font-size: 0.8rem;
        }
        
        .deletion-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .deletion-detail {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 10px;
            padding: 12px;
        }
        
        .deletion-detail label {
            display: block;
            color: var(--slate-500);
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .deletion-detail span {
            color: #fff;
            font-size: 0.85rem;
            font-weight: 500;
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
            background: linear-gradient(135deg, var(--teal), var(--cyan));
        }
        
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--teal), var(--cyan));
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
            box-shadow: 0 4px 20px rgba(20, 184, 166, 0.4);
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
        
        .btn-view { background: rgba(20, 184, 166, 0.15); color: var(--teal); }
        .btn-view:hover { background: rgba(20, 184, 166, 0.25); color: var(--teal); }
        
        .btn-edit { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .btn-edit:hover { background: rgba(99, 102, 241, 0.25); color: var(--primary-light); }
        
        .btn-run { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .btn-run:hover { background: rgba(16, 185, 129, 0.25); color: var(--emerald-light); }
        
        .btn-delete { background: rgba(244, 63, 94, 0.15); color: var(--rose-light); }
        .btn-delete:hover { background: rgba(244, 63, 94, 0.25); color: var(--rose-light); }
        
        .btn-approve { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .btn-approve:hover { background: rgba(16, 185, 129, 0.25); color: var(--emerald-light); }
        
        .btn-reject { background: rgba(244, 63, 94, 0.15); color: var(--rose-light); }
        .btn-reject:hover { background: rgba(244, 63, 94, 0.25); color: var(--rose-light); }
        
        .btn-export {
            background: rgba(139, 92, 246, 0.15);
            color: var(--violet-light);
        }
        
        .btn-export:hover {
            background: rgba(139, 92, 246, 0.25);
            color: var(--violet-light);
        }
        
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
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
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
            background-color: var(--teal);
            border-color: var(--teal);
        }
        
        /* Modal Styles */
        .modal-content {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--teal), var(--cyan));
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
            max-height: 70vh;
            overflow-y: auto;
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
            background: linear-gradient(135deg, var(--teal), var(--cyan));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-modal-save:hover {
            box-shadow: 0 4px 20px rgba(20, 184, 166, 0.3);
            color: white;
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
        
        /* Timeline styles for audit */
        .audit-timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .audit-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: rgba(255,255,255,0.1);
        }
        
        .audit-item {
            position: relative;
            padding-bottom: 24px;
        }
        
        .audit-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 6px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--teal);
            border: 2px solid var(--slate-800);
        }
        
        .audit-item.high::before { background: var(--amber); }
        .audit-item.critical::before { background: var(--rose); }
        
        .audit-content {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 12px;
            padding: 16px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        
        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .audit-action {
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .audit-time {
            color: var(--slate-500);
            font-size: 0.75rem;
        }
        
        .audit-description {
            color: var(--slate-400);
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        
        .audit-meta {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            color: var(--slate-500);
        }
        
        .audit-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* GDPR Info Box */
        .gdpr-info {
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.1), rgba(6, 182, 212, 0.1));
            border: 1px solid rgba(20, 184, 166, 0.2);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .gdpr-info h6 {
            color: var(--teal);
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .gdpr-info p {
            color: var(--slate-400);
            font-size: 0.85rem;
            margin: 0;
            line-height: 1.6;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--slate-600);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--slate-500);
        }
    </style>
</head>
<body class="admin-layout compliance-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-balance-scale"></i> Compliance & Audit</h1>
            <p>Data privacy, policy management, audit trails, and data retention controls</p>
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
            <div class="stat-box privacy">
                <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['privacy_logs_month']); ?></h3>
                    <span>Privacy Events (30d)</span>
                </div>
            </div>
            <div class="stat-box policies">
                <div class="stat-icon"><i class="fas fa-file-contract"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['active_policies']; ?>/<?php echo $stats['total_policies']; ?></h3>
                    <span>Active Policies</span>
                </div>
            </div>
            <div class="stat-box audit">
                <div class="stat-icon"><i class="fas fa-history"></i></div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['audit_events_today']); ?></h3>
                    <span>Audit Events Today</span>
                    <?php if ($stats['high_risk_events'] > 0): ?>
                        <span class="stat-badge danger"><?php echo $stats['high_risk_events']; ?> High Risk</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-box retention">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['active_retention']; ?></h3>
                    <span>Retention Policies</span>
                    <?php if ($stats['pending_deletions'] > 0): ?>
                        <span class="stat-badge warning"><?php echo $stats['pending_deletions']; ?> Pending</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <a href="?tab=privacy" class="tab-link <?php echo $currentTab === 'privacy' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> Data Privacy Logs
            </a>
            <a href="?tab=policies" class="tab-link <?php echo $currentTab === 'policies' ? 'active' : ''; ?>">
                <i class="fas fa-file-contract"></i> Terms & Policies
            </a>
            <a href="?tab=audit" class="tab-link <?php echo $currentTab === 'audit' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Audit Trails
            </a>
            <a href="?tab=retention" class="tab-link <?php echo $currentTab === 'retention' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i> Data Retention
                <?php if ($stats['pending_deletions'] > 0): ?>
                    <span class="badge"><?php echo $stats['pending_deletions']; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Main Content Card -->
        <div class="main-card">
            <?php if ($currentTab === 'privacy'): ?>
            <!-- Data Privacy Logs Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-user-shield"></i> Data Privacy Logs</h5>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="export_privacy_logs">
                    <input type="hidden" name="date_from" value="<?php echo $dateFrom; ?>">
                    <input type="hidden" name="date_to" value="<?php echo $dateTo; ?>">
                    <button type="submit" class="btn-action btn-export">
                        <i class="fas fa-download"></i> Export Logs
                    </button>
                </form>
            </div>
            <div class="card-body-custom">
                <div class="gdpr-info">
                    <h6><i class="fas fa-shield-alt"></i> GDPR Compliance</h6>
                    <p>All data access, modifications, and deletions are logged for compliance with GDPR Article 30 (Records of Processing Activities). These logs are retained for 6 years as per regulatory requirements.</p>
                </div>
                
                <!-- Filters -->
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="tab" value="privacy">
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Action Type</label>
                        <select name="action_type_filter">
                            <option value="all">All Actions</option>
                            <?php foreach ($actionTypes as $key => $type): ?>
                                <option value="<?php echo $key; ?>" <?php echo $actionTypeFilter === $key ? 'selected' : ''; ?>><?php echo $type['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary-custom" style="padding: 10px 20px;">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </form>
                
                <?php if (empty($privacyLogs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>No Privacy Logs Found</h5>
                        <p>No privacy events recorded for the selected period.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($privacyLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <div style="color: #fff; font-weight: 500;"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                                            <div style="color: var(--slate-500); font-size: 0.75rem;"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div style="color: #fff;"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></div>
                                            <div style="color: var(--slate-500); font-size: 0.75rem;"><?php echo htmlspecialchars($log['email'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <?php $actionInfo = $actionTypes[$log['action_type']] ?? ['icon' => 'fa-question', 'label' => ucfirst($log['action_type']), 'color' => 'slate']; ?>
                                            <span class="action-badge <?php echo $actionInfo['color']; ?>">
                                                <i class="fas <?php echo $actionInfo['icon']; ?>"></i>
                                                <?php echo $actionInfo['label']; ?>
                                            </span>
                                        </td>
                                        <td style="max-width: 300px;"><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                                        <td>
                                            <code style="color: var(--cyan-light); font-size: 0.8rem;"><?php echo htmlspecialchars($log['ip_address']); ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php elseif ($currentTab === 'policies'): ?>
            <!-- Terms & Policies Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-file-contract"></i> Terms & Policy Management</h5>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#createPolicyModal">
                    <i class="fas fa-plus"></i> Create Policy
                </button>
            </div>
            <div class="card-body-custom">
                <div class="alert-custom info" style="margin-bottom: 24px;">
                    <i class="fas fa-info-circle"></i>
                    <span>Only one policy of each type can be active at a time. Activating a new version will automatically deactivate the previous one.</span>
                </div>
                
                <?php foreach ($policyTypes as $type => $typeInfo): ?>
                    <?php $typePolicies = $policiesByType[$type] ?? []; ?>
                    <div class="policy-section">
                        <div class="policy-type-header">
                            <div class="policy-type-icon <?php echo $typeInfo['color']; ?>">
                                <i class="fas <?php echo $typeInfo['icon']; ?>"></i>
                            </div>
                            <h6 class="policy-type-title"><?php echo $typeInfo['label']; ?></h6>
                        </div>
                        
                        <?php if (empty($typePolicies)): ?>
                            <div style="padding: 20px; text-align: center; color: var(--slate-500);">
                                <i class="fas fa-file-alt" style="font-size: 24px; margin-bottom: 8px; display: block;"></i>
                                No <?php echo strtolower($typeInfo['label']); ?> created yet.
                            </div>
                        <?php else: ?>
                            <div class="policy-grid">
                                <?php foreach ($typePolicies as $policy): ?>
                                    <div class="policy-card <?php echo $policy['is_active'] ? 'active' : ''; ?>">
                                        <div class="policy-header">
                                            <div>
                                                <h6 class="policy-title"><?php echo htmlspecialchars($policy['title']); ?></h6>
                                                <span class="policy-version">
                                                    <i class="fas fa-code-branch"></i> v<?php echo htmlspecialchars($policy['version']); ?>
                                                </span>
                                            </div>
                                            <span class="status-badge <?php echo $policy['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $policy['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        <p class="policy-content-preview"><?php echo htmlspecialchars(strip_tags($policy['content'])); ?></p>
                                        <div class="policy-meta">
                                            <span><i class="fas fa-calendar"></i> Effective: <?php echo date('M j, Y', strtotime($policy['effective_date'])); ?></span>
                                            <?php if ($policy['requires_acceptance']): ?>
                                                <span><i class="fas fa-check-circle"></i> Requires Acceptance</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="policy-actions">
                                            <button class="btn-action btn-view" data-bs-toggle="modal" data-bs-target="#viewPolicyModal<?php echo $policy['id']; ?>">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editPolicyModal<?php echo $policy['id']; ?>">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_policy">
                                                <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                                <button type="submit" class="btn-action <?php echo $policy['is_active'] ? 'btn-delete' : 'btn-run'; ?>">
                                                    <i class="fas fa-<?php echo $policy['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                    <?php echo $policy['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <!-- View Policy Modal -->
                                    <div class="modal fade" id="viewPolicyModal<?php echo $policy['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="fas <?php echo $typeInfo['icon']; ?> me-2"></i><?php echo htmlspecialchars($policy['title']); ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                                                        <span class="status-badge <?php echo $policy['is_active'] ? 'active' : 'inactive'; ?> me-2"><?php echo $policy['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                                        <span style="color: var(--slate-400);">Version <?php echo htmlspecialchars($policy['version']); ?> • Effective <?php echo date('F j, Y', strtotime($policy['effective_date'])); ?></span>
                                                    </div>
                                                    <div style="color: var(--slate-300); line-height: 1.8; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($policy['content'])); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Edit Policy Modal -->
                                    <div class="modal fade" id="editPolicyModal<?php echo $policy['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Policy</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="update_policy">
                                                    <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">Title</label>
                                                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($policy['title']); ?>" required>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Version</label>
                                                                <input type="text" name="version" class="form-control" value="<?php echo htmlspecialchars($policy['version']); ?>" placeholder="e.g., 1.1" required>
                                                            </div>
                                                            <div class="col-md-6 mb-3">
                                                                <label class="form-label">Effective Date</label>
                                                                <input type="date" name="effective_date" class="form-control" value="<?php echo $policy['effective_date']; ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Content</label>
                                                            <textarea name="content" class="form-control" rows="12" required><?php echo htmlspecialchars($policy['content']); ?></textarea>
                                                        </div>
                                                        <div class="form-check">
                                                            <input type="checkbox" name="requires_acceptance" class="form-check-input" id="reqAccept<?php echo $policy['id']; ?>" <?php echo $policy['requires_acceptance'] ? 'checked' : ''; ?>>
                                                            <label class="form-check-label" for="reqAccept<?php echo $policy['id']; ?>" style="color: var(--slate-300);">Requires user acceptance</label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn-modal-save">Update Policy</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php elseif ($currentTab === 'audit'): ?>
            <!-- Audit Trails Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-history"></i> Audit Trails</h5>
                <button class="btn-action btn-export">
                    <i class="fas fa-download"></i> Export Audit Log
                </button>
            </div>
            <div class="card-body-custom">
                <!-- Filters -->
                <form method="GET" class="filter-bar">
                    <input type="hidden" name="tab" value="audit">
                    <div class="filter-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo $dateFrom; ?>">
                    </div>
                    <div class="filter-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo $dateTo; ?>">
                    </div>
                    <div class="filter-group">
                        <label>Category</label>
                        <select name="audit_category">
                            <option value="all">All Categories</option>
                            <?php foreach ($auditCategories as $key => $cat): ?>
                                <option value="<?php echo $key; ?>" <?php echo $auditCategory === $key ? 'selected' : ''; ?>><?php echo $cat['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Risk Level</label>
                        <select name="audit_risk">
                            <option value="all">All Levels</option>
                            <option value="low" <?php echo $auditRisk === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $auditRisk === 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $auditRisk === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="critical" <?php echo $auditRisk === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary-custom" style="padding: 10px 20px;">
                            <i class="fas fa-filter"></i> Apply
                        </button>
                    </div>
                </form>
                
                <?php if (empty($auditTrails)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h5>No Audit Events Found</h5>
                        <p>No audit events recorded for the selected criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="audit-timeline">
                        <?php foreach ($auditTrails as $audit): ?>
                            <div class="audit-item <?php echo $audit['risk_level']; ?>">
                                <div class="audit-content">
                                    <div class="audit-header">
                                        <div>
                                            <span class="audit-action"><?php echo ucwords(str_replace('_', ' ', $audit['action_type'])); ?></span>
                                            <span class="risk-badge <?php echo $audit['risk_level']; ?> ms-2"><?php echo ucfirst($audit['risk_level']); ?></span>
                                        </div>
                                        <span class="audit-time"><?php echo date('M j, Y g:i A', strtotime($audit['created_at'])); ?></span>
                                    </div>
                                    <p class="audit-description"><?php echo htmlspecialchars($audit['description'] ?? '-'); ?></p>
                                    <div class="audit-meta">
                                        <?php 
                                        $catInfo = $auditCategories[$audit['action_category']] ?? ['icon' => 'fa-tag', 'label' => ucfirst($audit['action_category'])];
                                        ?>
                                        <span><i class="fas <?php echo $catInfo['icon']; ?>"></i> <?php echo $catInfo['label']; ?></span>
                                        <?php if ($audit['admin_name']): ?>
                                            <span><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($audit['admin_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($audit['user_name']): ?>
                                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($audit['user_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($audit['ip_address']): ?>
                                            <span><i class="fas fa-globe"></i> <?php echo htmlspecialchars($audit['ip_address']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php elseif ($currentTab === 'retention'): ?>
            <!-- Data Retention Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-database"></i> Data Retention Policies</h5>
            </div>
            <div class="card-body-custom">
                <div class="gdpr-info">
                    <h6><i class="fas fa-gavel"></i> Legal Compliance</h6>
                    <p>Data retention policies ensure compliance with GDPR Article 5(1)(e) - Storage Limitation Principle. Personal data must be kept in a form which permits identification of data subjects for no longer than is necessary for the purposes for which the personal data are processed.</p>
                </div>
                
                <h6 style="color: #fff; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-clock" style="color: var(--rose);"></i> Retention Policies
                </h6>
                
                <div class="retention-grid">
                    <?php foreach ($retentionPolicies as $policy): 
                        $dataInfo = $dataTypes[$policy['data_type']] ?? ['icon' => 'fa-database', 'label' => ucfirst($policy['data_type'])];
                    ?>
                        <div class="retention-card <?php echo !$policy['is_active'] ? 'inactive' : ''; ?>">
                            <div class="retention-icon">
                                <i class="fas <?php echo $dataInfo['icon']; ?>"></i>
                            </div>
                            <div class="retention-info">
                                <h6 class="retention-name"><?php echo htmlspecialchars($policy['policy_name']); ?></h6>
                                <div class="retention-meta">
                                    <span><i class="fas fa-folder"></i> <?php echo $dataInfo['label']; ?></span>
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo $policy['retention_period']; ?> days</span>
                                    <span><i class="fas fa-tasks"></i> <?php echo ucfirst($policy['action_after_expiry']); ?></span>
                                    <?php if ($policy['auto_execute']): ?>
                                        <span style="color: var(--emerald-light);"><i class="fas fa-robot"></i> Auto</span>
                                    <?php endif; ?>
                                    <?php if ($policy['last_executed_at']): ?>
                                        <span><i class="fas fa-play-circle"></i> Last: <?php echo date('M j', strtotime($policy['last_executed_at'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="retention-actions">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_retention">
                                    <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                    <label class="toggle-switch">
                                        <input type="checkbox" <?php echo $policy['is_active'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </form>
                                <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#editRetentionModal<?php echo $policy['id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="execute_retention">
                                    <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                    <button type="submit" class="btn-action btn-run" onclick="return confirm('Execute this retention policy now? This action cannot be undone.')">
                                        <i class="fas fa-play"></i> Run
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Edit Retention Modal -->
                        <div class="modal fade" id="editRetentionModal<?php echo $policy['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Retention Policy</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="update_retention">
                                        <input type="hidden" name="policy_id" value="<?php echo $policy['id']; ?>">
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label">Policy Name</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($policy['policy_name']); ?>" disabled>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Retention Period (days)</label>
                                                <input type="number" name="retention_period" class="form-control" value="<?php echo $policy['retention_period']; ?>" min="1" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Action After Expiry</label>
                                                <select name="action_after_expiry" class="form-select" required>
                                                    <option value="delete" <?php echo $policy['action_after_expiry'] === 'delete' ? 'selected' : ''; ?>>Delete Permanently</option>
                                                    <option value="anonymize" <?php echo $policy['action_after_expiry'] === 'anonymize' ? 'selected' : ''; ?>>Anonymize Data</option>
                                                    <option value="archive" <?php echo $policy['action_after_expiry'] === 'archive' ? 'selected' : ''; ?>>Archive</option>
                                                    <option value="review" <?php echo $policy['action_after_expiry'] === 'review' ? 'selected' : ''; ?>>Flag for Review</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Notification Days Before Action</label>
                                                <input type="number" name="notification_days" class="form-control" value="<?php echo $policy['notification_days']; ?>" min="0">
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="auto_execute" class="form-check-input" id="autoExec<?php echo $policy['id']; ?>" <?php echo $policy['auto_execute'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="autoExec<?php echo $policy['id']; ?>" style="color: var(--slate-300);">Auto-execute on schedule</label>
                                            </div>
                                            <?php if ($policy['legal_basis']): ?>
                                                <div class="mt-3 p-3" style="background: rgba(20, 184, 166, 0.1); border-radius: 8px;">
                                                    <small style="color: var(--teal);"><i class="fas fa-gavel me-1"></i> Legal Basis:</small>
                                                    <p style="color: var(--slate-400); font-size: 0.8rem; margin: 4px 0 0 0;"><?php echo htmlspecialchars($policy['legal_basis']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modal-save">Update Policy</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Deletion Requests Section -->
                <?php if (!empty($deletionRequests)): ?>
                    <h6 style="color: #fff; font-weight: 700; margin: 32px 0 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-user-times" style="color: var(--rose);"></i> Data Deletion Requests
                        <?php if ($stats['pending_deletions'] > 0): ?>
                            <span class="stat-badge warning"><?php echo $stats['pending_deletions']; ?> Pending</span>
                        <?php endif; ?>
                    </h6>
                    
                    <?php foreach ($deletionRequests as $request): ?>
                        <div class="deletion-card <?php echo $request['status']; ?>">
                            <div class="deletion-header">
                                <div class="deletion-user">
                                    <div class="deletion-avatar">
                                        <?php echo strtoupper(substr($request['username'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <div class="deletion-user-info">
                                        <h6><?php echo htmlspecialchars($request['username'] ?? 'Unknown User'); ?></h6>
                                        <span><?php echo htmlspecialchars($request['email'] ?? ''); ?></span>
                                    </div>
                                </div>
                                <span class="status-badge <?php echo $request['status']; ?>"><?php echo ucfirst($request['status']); ?></span>
                            </div>
                            
                            <div class="deletion-details">
                                <div class="deletion-detail">
                                    <label>Request Type</label>
                                    <span><?php echo ucwords(str_replace('_', ' ', $request['request_type'])); ?></span>
                                </div>
                                <div class="deletion-detail">
                                    <label>Requested</label>
                                    <span><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></span>
                                </div>
                                <?php if ($request['scheduled_deletion_date']): ?>
                                    <div class="deletion-detail">
                                        <label>Scheduled For</label>
                                        <span><?php echo date('M j, Y', strtotime($request['scheduled_deletion_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($request['processed_by_name']): ?>
                                    <div class="deletion-detail">
                                        <label>Processed By</label>
                                        <span><?php echo htmlspecialchars($request['processed_by_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($request['reason']): ?>
                                <p style="color: var(--slate-400); font-size: 0.85rem; margin-bottom: 16px;">
                                    <i class="fas fa-quote-left me-2" style="color: var(--slate-600);"></i>
                                    <?php echo htmlspecialchars($request['reason']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($request['status'] === 'pending'): ?>
                                <div style="display: flex; gap: 8px;">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="process_deletion_request">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="new_status" value="processing">
                                        <button type="submit" class="btn-action btn-approve">
                                            <i class="fas fa-check"></i> Approve & Process
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="process_deletion_request">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="new_status" value="rejected">
                                        <button type="submit" class="btn-action btn-reject" onclick="return confirm('Reject this deletion request?')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Policy Modal -->
    <div class="modal fade" id="createPolicyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New Policy</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_policy">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Policy Type</label>
                                <select name="policy_type" class="form-select" required>
                                    <?php foreach ($policyTypes as $key => $type): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $type['label']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Version</label>
                                <input type="text" name="version" class="form-control" placeholder="e.g., 1.0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Terms of Service" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Effective Date</label>
                            <input type="date" name="effective_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Content</label>
                            <textarea name="content" class="form-control" rows="12" placeholder="Enter the policy content here..." required></textarea>
                            <small style="color: var(--slate-500);">You can use markdown formatting. The content will be displayed as plain text.</small>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="requires_acceptance" class="form-check-input" id="reqAcceptNew" checked>
                            <label class="form-check-label" for="reqAcceptNew" style="color: var(--slate-300);">Requires user acceptance before using the platform</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modal-save">Create Policy</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert-custom.success, .alert-custom.danger').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() { alert.remove(); }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
