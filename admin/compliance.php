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
.compliance-admin-page .admin-main-content {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
            padding: 1.5rem 2rem 2.5rem;
        }

        .compliance-page-header {
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .compliance-page-header h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .compliance-page-header h1 i {
            color: #0d9488;
            opacity: 0.95;
        }

        .compliance-page-header p {
            color: #64748b;
            margin: 0;
            font-size: 0.95rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 576px) {
            .stats-row { grid-template-columns: 1fr; }
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
            border-color: rgba(13, 148, 136, 0.28);
            box-shadow: 0 4px 14px rgba(13, 148, 136, 0.1);
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

        .stat-box.privacy .stat-icon { background: #ccfbf1; color: #0f766e; }
        .stat-box.policies .stat-icon { background: #ede9fe; color: #6d28d9; }
        .stat-box.audit .stat-icon { background: #fffbeb; color: #b45309; }
        .stat-box.retention .stat-icon { background: #ffe4e6; color: #be123c; }

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

        .stat-badge {
            font-size: 0.65rem;
            padding: 3px 8px;
            border-radius: 6px;
            font-weight: 600;
            margin-left: 8px;
            display: inline-block;
        }

        .stat-badge.warning {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .stat-badge.danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
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
            background: #ecfeff;
            border-color: #a5f3fc;
            color: #0e7490;
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
            color: #0f766e;
            box-shadow: 0 1px 4px rgba(30, 58, 138, 0.12);
        }

        .tab-link i { font-size: 1rem; }

        .tab-link .badge {
            background: #fee2e2;
            color: #b91c1c;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }

        .tab-link.active .badge {
            background: #ccfbf1;
            color: #0f766e;
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

        .card-header-custom h5 i { color: #0d9488; }

        .card-body-custom {
            padding: 1.35rem 1.35rem 1.5rem;
            background: #fff;
        }

        .filter-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 1.25rem;
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .filter-group input,
        .filter-group select {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            color: #0f172a;
            padding: 10px 14px;
            font-size: 0.85rem;
            min-width: 140px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #0d9488;
            outline: none;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }

        .log-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .log-table thead th {
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 12px 14px;
            border: none;
        }

        .log-table thead th:first-child { border-radius: 10px 0 0 10px; }
        .log-table thead th:last-child { border-radius: 0 10px 10px 0; }

        .log-table tbody tr {
            background: #f8fafc;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 0 rgba(226, 232, 240, 0.8);
        }

        .log-table tbody tr:hover {
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }

        .log-table tbody td {
            padding: 12px 14px;
            color: #334155;
            font-size: 0.85rem;
            border: none;
            vertical-align: middle;
        }

        .log-table tbody td:first-child { border-radius: 10px 0 0 10px; }
        .log-table tbody td:last-child { border-radius: 0 10px 10px 0; }

        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
        }

        .action-badge.primary { background: #eef2ff; color: #4338ca; border-color: #c7d2fe; }
        .action-badge.cyan { background: #ecfeff; color: #0e7490; border-color: #a5f3fc; }
        .action-badge.rose { background: #ffe4e6; color: #be123c; border-color: #fecdd3; }
        .action-badge.amber { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .action-badge.emerald { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .action-badge.violet { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
        .action-badge.slate { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .action-badge.teal { background: #ccfbf1; color: #0f766e; border-color: #99f6e4; }

        .risk-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .risk-badge.low { background: #ecfdf5; color: #047857; }
        .risk-badge.medium { background: #fffbeb; color: #b45309; }
        .risk-badge.high { background: #fef2f2; color: #b91c1c; }
        .risk-badge.critical { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .status-badge {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-badge.active { background: #ecfdf5; color: #047857; }
        .status-badge.inactive { background: #f1f5f9; color: #64748b; }
        .status-badge.pending { background: #fffbeb; color: #b45309; }
        .status-badge.processing { background: #eef2ff; color: #4338ca; }
        .status-badge.completed { background: #ecfdf5; color: #047857; }
        .status-badge.rejected { background: #fef2f2; color: #b91c1c; }

        .policy-section { margin-bottom: 28px; }
        .policy-section:last-child { margin-bottom: 0; }

        .policy-type-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
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

        .policy-type-icon.primary { background: #eef2ff; color: #2563eb; }
        .policy-type-icon.cyan { background: #ecfeff; color: #0e7490; }
        .policy-type-icon.amber { background: #fffbeb; color: #d97706; }
        .policy-type-icon.emerald { background: #ecfdf5; color: #059669; }
        .policy-type-icon.violet { background: #f5f3ff; color: #7c3aed; }
        .policy-type-icon.rose { background: #ffe4e6; color: #be123c; }

        .policy-type-title {
            color: #0f172a;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
        }

        .policy-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 14px;
        }

        .policy-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            transition: box-shadow 0.2s, border-color 0.2s;
            position: relative;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .policy-card:hover {
            border-color: rgba(13, 148, 136, 0.35);
            box-shadow: 0 4px 14px rgba(13, 148, 136, 0.08);
        }

        .policy-card.active { border-color: rgba(5, 150, 105, 0.4); }

        .policy-card.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #059669, #14b8a6);
            border-radius: 14px 14px 0 0;
        }

        .policy-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .policy-title {
            color: #0f172a;
            font-weight: 700;
            font-size: 0.95rem;
            margin: 0 0 4px 0;
        }

        .policy-version {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            color: #64748b;
        }

        .policy-content-preview {
            color: #64748b;
            font-size: 0.8rem;
            line-height: 1.5;
            margin-bottom: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .policy-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            color: #64748b;
            margin-bottom: 14px;
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
            border-top: 1px solid #e2e8f0;
        }

        .retention-grid { display: grid; gap: 14px; }

        .retention-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .retention-card:hover {
            border-color: rgba(190, 18, 60, 0.25);
            box-shadow: 0 4px 14px rgba(190, 18, 60, 0.06);
        }

        .retention-card.inactive { opacity: 0.68; }

        .retention-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: #ffe4e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #be123c;
            flex-shrink: 0;
        }

        .retention-info { flex: 1; min-width: 0; }

        .retention-name {
            color: #0f172a;
            font-weight: 700;
            font-size: 1rem;
            margin: 0 0 6px 0;
        }

        .retention-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            color: #64748b;
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
            flex-shrink: 0;
        }

        .deletion-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 14px;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .deletion-card:hover {
            border-color: rgba(190, 18, 60, 0.25);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        .deletion-card.pending {
            border-left: 4px solid #d97706;
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
            background: linear-gradient(135deg, #fda4af, #c4b5fd);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }

        .deletion-user-info h6 {
            color: #0f172a;
            font-weight: 600;
            margin: 0 0 4px 0;
            font-size: 0.95rem;
        }

        .deletion-user-info span {
            color: #64748b;
            font-size: 0.8rem;
        }

        .deletion-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 14px;
        }

        .deletion-detail {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
        }

        .deletion-detail label {
            display: block;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .deletion-detail span {
            color: #0f172a;
            font-size: 0.85rem;
            font-weight: 500;
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
            background: linear-gradient(135deg, #0d9488, #14b8a6);
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

        .btn-view {
            background: #ccfbf1;
            color: #0f766e;
            border-color: #99f6e4;
        }
        .btn-view:hover { background: #99f6e4; }

        .btn-edit {
            background: #fff;
            color: #2563eb;
            border-color: rgba(37, 99, 235, 0.35);
        }
        .btn-edit:hover { background: #eef2ff; }

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

        .btn-approve {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .btn-approve:hover { background: #d1fae5; }

        .btn-reject {
            background: #fff;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .btn-reject:hover { background: #fef2f2; }

        .btn-export {
            background: #f5f3ff;
            color: #6d28d9;
            border-color: #ddd6fe;
        }
        .btn-export:hover {
            background: #ede9fe;
        }

        .compliance-admin-page .form-control,
        .compliance-admin-page .form-select {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #0f172a;
            padding: 10px 14px;
            font-size: 0.9rem;
        }

        .compliance-admin-page .form-control:focus,
        .compliance-admin-page .form-select:focus {
            background: #fff;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
            color: #0f172a;
        }

        .compliance-admin-page .form-control::placeholder {
            color: #94a3b8;
        }

        .compliance-admin-page .form-label {
            color: #334155;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .compliance-admin-page .form-check-input {
            background-color: #fff;
            border-color: #cbd5e1;
        }

        .compliance-admin-page .form-check-input:checked {
            background-color: #0d9488;
            border-color: #0d9488;
        }

        .compliance-admin-page .form-check-label {
            color: #475569;
        }

        .compliance-admin-page .modal-content {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
        }

        .compliance-admin-page .modal-header {
            background: linear-gradient(120deg, #f0fdfa 0%, #eef4ff 100%);
            border-bottom: 1px solid #e2e8f0;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }

        .compliance-admin-page .modal-title {
            color: #1e3a8a;
            font-weight: 700;
        }

        .compliance-admin-page .modal-body {
            padding: 20px;
            color: #334155;
            max-height: 70vh;
            overflow-y: auto;
        }

        .compliance-admin-page .modal-footer {
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

        .audit-timeline {
            position: relative;
            padding-left: 28px;
        }

        .audit-timeline::before {
            content: '';
            position: absolute;
            left: 9px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }

        .audit-item {
            position: relative;
            padding-bottom: 22px;
        }

        .audit-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 6px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #14b8a6;
            border: 2px solid #fff;
            box-shadow: 0 0 0 1px #e2e8f0;
        }

        .audit-item.high::before { background: #d97706; }
        .audit-item.critical::before { background: #dc2626; }

        .audit-content {
            background: #f8fafc;
            border-radius: 12px;
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
        }

        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .audit-action {
            color: #0f172a;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .audit-time {
            color: #64748b;
            font-size: 0.75rem;
        }

        .audit-description {
            color: #475569;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .audit-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            color: #64748b;
            flex-wrap: wrap;
        }

        .audit-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .gdpr-info {
            background: linear-gradient(120deg, #f0fdfa 0%, #ecfeff 100%);
            border: 1px solid rgba(13, 148, 136, 0.2);
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 1.25rem;
        }

        .gdpr-info h6 {
            color: #0f766e;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .gdpr-info p {
            color: #475569;
            font-size: 0.875rem;
            margin: 0;
            line-height: 1.6;
        }

        .compliance-section-title {
            color: #1e3a8a;
            font-weight: 700;
            margin: 0 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .compliance-section-title i {
            color: #be123c;
        }

        .compliance-section-title--spaced {
            margin-top: 2rem;
        }

        .retention-meta-auto {
            color: #047857;
            font-weight: 600;
        }

        .legal-basis-box {
            margin-top: 0.75rem;
            padding: 0.75rem 1rem;
            background: #f0fdfa;
            border: 1px solid rgba(13, 148, 136, 0.2);
            border-radius: 8px;
        }

        .legal-basis-box .legal-basis-label {
            color: #0f766e;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .legal-basis-box .legal-basis-text {
            color: #475569;
            font-size: 0.8rem;
            margin: 4px 0 0 0;
        }

        .compliance-quote {
            color: #64748b;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .compliance-quote .fa-quote-left {
            color: #94a3b8;
        }

        .form-hint {
            display: block;
            color: #64748b;
            font-size: 0.8rem;
            margin-top: 0.35rem;
        }

        .log-stack-primary {
            color: #0f172a;
            font-weight: 500;
        }

        .log-stack-muted {
            color: #64748b;
            font-size: 0.75rem;
        }

        .log-ip-code {
            color: #0e7490;
            font-size: 0.8rem;
            background: #ecfeff;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .policy-type-empty {
            padding: 1.25rem;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px dashed #e2e8f0;
        }

        .policy-type-empty > i {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
            color: #cbd5e1;
        }

        .policy-modal-toolbar {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .policy-modal-meta {
            color: #64748b;
        }

        .policy-modal-content-text {
            color: #334155;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        .log-desc-cell {
            max-width: 300px;
        }

        .deletion-actions-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-primary-custom.btn-compact {
            padding: 10px 20px;
        }
    </style>
</head>
<body class="admin-layout compliance-admin-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="compliance-page-header">
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
                        <button type="submit" class="btn-primary-custom btn-compact">
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
                                            <div class="log-stack-primary"><?php echo date('M j, Y', strtotime($log['created_at'])); ?></div>
                                            <div class="log-stack-muted"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="log-stack-primary"><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></div>
                                            <div class="log-stack-muted"><?php echo htmlspecialchars($log['email'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <?php $actionInfo = $actionTypes[$log['action_type']] ?? ['icon' => 'fa-question', 'label' => ucfirst($log['action_type']), 'color' => 'slate']; ?>
                                            <span class="action-badge <?php echo $actionInfo['color']; ?>">
                                                <i class="fas <?php echo $actionInfo['icon']; ?>"></i>
                                                <?php echo $actionInfo['label']; ?>
                                            </span>
                                        </td>
                                        <td class="log-desc-cell"><?php echo htmlspecialchars($log['description'] ?? '-'); ?></td>
                                        <td>
                                            <code class="log-ip-code"><?php echo htmlspecialchars($log['ip_address']); ?></code>
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
                <div class="alert-custom info">
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
                            <div class="policy-type-empty">
                                <i class="fas fa-file-alt"></i>
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
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="policy-modal-toolbar">
                                                        <span class="status-badge <?php echo $policy['is_active'] ? 'active' : 'inactive'; ?> me-2"><?php echo $policy['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                                        <span class="policy-modal-meta">Version <?php echo htmlspecialchars($policy['version']); ?> • Effective <?php echo date('F j, Y', strtotime($policy['effective_date'])); ?></span>
                                                    </div>
                                                    <div class="policy-modal-content-text"><?php echo nl2br(htmlspecialchars($policy['content'])); ?></div>
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
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                                            <label class="form-check-label" for="reqAccept<?php echo $policy['id']; ?>">Requires user acceptance</label>
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
                        <button type="submit" class="btn-primary-custom btn-compact">
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
                
                <h6 class="compliance-section-title">
                    <i class="fas fa-clock"></i> Retention Policies
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
                                        <span class="retention-meta-auto"><i class="fas fa-robot"></i> Auto</span>
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
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                                <label class="form-check-label" for="autoExec<?php echo $policy['id']; ?>">Auto-execute on schedule</label>
                                            </div>
                                            <?php if ($policy['legal_basis']): ?>
                                                <div class="legal-basis-box mt-3">
                                                    <small class="legal-basis-label"><i class="fas fa-gavel me-1"></i> Legal Basis:</small>
                                                    <p class="legal-basis-text"><?php echo htmlspecialchars($policy['legal_basis']); ?></p>
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
                    <h6 class="compliance-section-title compliance-section-title--spaced">
                        <i class="fas fa-user-times"></i> Data Deletion Requests
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
                                <p class="compliance-quote">
                                    <i class="fas fa-quote-left me-2"></i>
                                    <?php echo htmlspecialchars($request['reason']); ?>
                                </p>
                            <?php endif; ?>
                            
                            <?php if ($request['status'] === 'pending'): ?>
                                <div class="deletion-actions-row">
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                            <small class="form-hint">You can use markdown formatting. The content will be displayed as plain text.</small>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="requires_acceptance" class="form-check-input" id="reqAcceptNew" checked>
                            <label class="form-check-label" for="reqAcceptNew">Requires user acceptance before using the platform</label>
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
