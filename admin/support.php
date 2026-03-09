<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Note: Tables (support_tickets, ticket_replies, error_logs, system_health, maintenance_schedules) 
// are defined in sql/all_additional_tables.sql

// Insert sample data if tables are empty
$ticketCount = $pdo->query("SELECT COUNT(*) FROM support_tickets")->fetchColumn();
if ($ticketCount == 0) {
    $sampleTickets = [
        ['TKT-' . date('Ymd') . '-001', 'Cannot login to my account', 'I keep getting invalid credentials error even though my password is correct.', 'technical', 'high', 'open', 'employee'],
        ['TKT-' . date('Ymd') . '-002', 'Job posting not visible', 'I posted a job 2 days ago but it still shows as pending.', 'account', 'medium', 'in_progress', 'employer'],
        ['TKT-' . date('Ymd') . '-003', 'Feature request: Dark mode', 'Would love to have a dark mode option for the dashboard.', 'feature_request', 'low', 'pending', 'employee'],
        ['TKT-' . date('Ymd') . '-004', 'Application status not updating', 'My job applications are stuck on pending status.', 'bug_report', 'high', 'open', 'employee'],
        ['TKT-' . date('Ymd') . '-005', 'Need help with company verification', 'Our business documents were rejected. Need clarification.', 'account', 'urgent', 'open', 'employer']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO support_tickets (ticket_number, subject, description, category, priority, status, user_type, user_email) VALUES (?, ?, ?, ?, ?, ?, ?, 'sample@email.com')");
    foreach ($sampleTickets as $ticket) {
        $stmt->execute($ticket);
    }
}

$errorCount = $pdo->query("SELECT COUNT(*) FROM error_logs")->fetchColumn();
if ($errorCount == 0) {
    $sampleErrors = [
        ['php', 'error', 'Undefined variable: user_profile in dashboard.php', 'dashboard.php', 125, NULL],
        ['database', 'warning', 'Slow query detected: SELECT * FROM job_applications WHERE user_id = ? took 2.5s', 'api/applications.php', 45, NULL],
        ['javascript', 'error', 'TypeError: Cannot read property \'length\' of undefined', 'assets/js/main.js', 234, NULL],
        ['security', 'critical', 'Multiple failed login attempts from IP 192.168.1.100', 'login.php', 0, NULL],
        ['api', 'warning', 'Rate limit exceeded for API key: wl_abc123...', 'api/middleware.php', 78, NULL],
        ['system', 'info', 'Scheduled backup completed successfully', 'cron/backup.php', 0, NULL]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO error_logs (error_type, severity, message, file, line, stack_trace) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($sampleErrors as $err) {
        $stmt->execute($err);
    }
}

$healthCount = $pdo->query("SELECT COUNT(*) FROM system_health")->fetchColumn();
if ($healthCount == 0) {
    $components = [
        ['Web Server', 'operational', 45],
        ['Database', 'operational', 12],
        ['File Storage', 'operational', 89],
        ['Email Service', 'operational', 156],
        ['API Gateway', 'operational', 34],
        ['Search Engine', 'operational', 67],
        ['Cache Server', 'operational', 8],
        ['Background Jobs', 'operational', 23]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO system_health (component, status, response_time) VALUES (?, ?, ?)");
    foreach ($components as $comp) {
        $stmt->execute($comp);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_ticket_status') {
        $ticketId = (int)$_POST['ticket_id'];
        $newStatus = $_POST['status'];
        $resolvedAt = ($newStatus === 'resolved' || $newStatus === 'closed') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $pdo->prepare("UPDATE support_tickets SET status = ?, resolved_at = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$newStatus, $resolvedAt, $ticketId])) {
            $message = 'Ticket status updated successfully!';
        }
    } elseif ($action === 'reply_ticket') {
        $ticketId = (int)$_POST['ticket_id'];
        $replyMessage = sanitizeInput($_POST['reply_message']);
        
        $stmt = $pdo->prepare("INSERT INTO ticket_replies (ticket_id, user_id, user_name, message, is_admin) VALUES (?, ?, ?, ?, TRUE)");
        if ($stmt->execute([$ticketId, $_SESSION['user_id'], $_SESSION['username'] ?? 'Admin', $replyMessage])) {
            // Update ticket status to in_progress if it was open
            $pdo->prepare("UPDATE support_tickets SET status = 'in_progress', updated_at = NOW() WHERE id = ? AND status = 'open'")->execute([$ticketId]);
            $message = 'Reply sent successfully!';
        }
    } elseif ($action === 'resolve_error') {
        $errorId = (int)$_POST['error_id'];
        $stmt = $pdo->prepare("UPDATE error_logs SET is_resolved = TRUE, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
        if ($stmt->execute([$_SESSION['user_id'], $errorId])) {
            $message = 'Error marked as resolved!';
        }
    } elseif ($action === 'clear_resolved_errors') {
        $stmt = $pdo->prepare("DELETE FROM error_logs WHERE is_resolved = TRUE");
        $stmt->execute();
        $message = 'Resolved errors cleared!';
    } elseif ($action === 'update_component_status') {
        $componentId = (int)$_POST['component_id'];
        $newStatus = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE system_health SET status = ?, last_check = NOW() WHERE id = ?");
        if ($stmt->execute([$newStatus, $componentId])) {
            $message = 'Component status updated!';
        }
    } elseif ($action === 'schedule_maintenance') {
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $start = $_POST['scheduled_start'];
        $end = $_POST['scheduled_end'];
        $services = isset($_POST['services']) ? json_encode($_POST['services']) : '[]';
        
        $stmt = $pdo->prepare("INSERT INTO maintenance_schedules (title, description, scheduled_start, scheduled_end, affected_services, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $description, $start, $end, $services, $_SESSION['user_id']])) {
            $message = 'Maintenance scheduled successfully!';
        }
    } elseif ($action === 'delete_maintenance') {
        $maintenanceId = (int)$_POST['maintenance_id'];
        $stmt = $pdo->prepare("DELETE FROM maintenance_schedules WHERE id = ?");
        $stmt->execute([$maintenanceId]);
        $message = 'Maintenance schedule deleted!';
    }
}

// Current tab
$currentTab = $_GET['tab'] ?? 'helpdesk';

// Fetch statistics
$stats = [];
$stats['total_tickets'] = $pdo->query("SELECT COUNT(*) FROM support_tickets")->fetchColumn();
$stats['open_tickets'] = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'open'")->fetchColumn();
$stats['in_progress_tickets'] = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status = 'in_progress'")->fetchColumn();
$stats['resolved_tickets'] = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('resolved', 'closed')")->fetchColumn();
$stats['urgent_tickets'] = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE priority = 'urgent' AND status NOT IN ('resolved', 'closed')")->fetchColumn();

$stats['total_errors'] = $pdo->query("SELECT COUNT(*) FROM error_logs")->fetchColumn();
$stats['unresolved_errors'] = $pdo->query("SELECT COUNT(*) FROM error_logs WHERE is_resolved = FALSE")->fetchColumn();
$stats['critical_errors'] = $pdo->query("SELECT COUNT(*) FROM error_logs WHERE severity = 'critical' AND is_resolved = FALSE")->fetchColumn();

$stats['healthy_components'] = $pdo->query("SELECT COUNT(*) FROM system_health WHERE status = 'operational'")->fetchColumn();
$stats['total_components'] = $pdo->query("SELECT COUNT(*) FROM system_health")->fetchColumn();

// Fetch data based on current tab
$tickets = [];
$errors = [];
$components = [];
$maintenances = [];

if ($currentTab === 'helpdesk') {
    $statusFilter = $_GET['status'] ?? 'all';
    $categoryFilter = $_GET['category'] ?? 'all';
    $priorityFilter = $_GET['priority'] ?? 'all';
    
    $query = "SELECT * FROM support_tickets WHERE 1=1";
    $params = [];
    
    if ($statusFilter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
    }
    if ($categoryFilter !== 'all') {
        $query .= " AND category = ?";
        $params[] = $categoryFilter;
    }
    if ($priorityFilter !== 'all') {
        $query .= " AND priority = ?";
        $params[] = $priorityFilter;
    }
    
    $query .= " ORDER BY FIELD(priority, 'urgent', 'high', 'medium', 'low'), created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
}

if ($currentTab === 'errors') {
    $typeFilter = $_GET['type'] ?? 'all';
    $severityFilter = $_GET['severity'] ?? 'all';
    $resolvedFilter = $_GET['resolved'] ?? 'unresolved';
    
    $query = "SELECT * FROM error_logs WHERE 1=1";
    $params = [];
    
    if ($typeFilter !== 'all') {
        $query .= " AND error_type = ?";
        $params[] = $typeFilter;
    }
    if ($severityFilter !== 'all') {
        $query .= " AND severity = ?";
        $params[] = $severityFilter;
    }
    if ($resolvedFilter === 'unresolved') {
        $query .= " AND is_resolved = FALSE";
    } elseif ($resolvedFilter === 'resolved') {
        $query .= " AND is_resolved = TRUE";
    }
    
    $query .= " ORDER BY FIELD(severity, 'critical', 'error', 'warning', 'info'), created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $errors = $stmt->fetchAll();
}

if ($currentTab === 'status') {
    $components = $pdo->query("SELECT * FROM system_health ORDER BY component")->fetchAll();
    $maintenances = $pdo->query("SELECT * FROM maintenance_schedules ORDER BY scheduled_start DESC LIMIT 10")->fetchAll();
}

// Get ticket details if viewing a specific ticket
$ticketDetails = null;
$ticketReplies = [];
if (isset($_GET['ticket_id'])) {
    $ticketId = (int)$_GET['ticket_id'];
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticketDetails = $stmt->fetch();
    
    if ($ticketDetails) {
        $stmt = $pdo->prepare("SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
        $stmt->execute([$ticketId]);
        $ticketReplies = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Maintenance - WORKLINK Admin</title>
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
            --blue: #3b82f6;
            --blue-light: #60a5fa;
            --slate-900: #0f172a;
            --slate-800: #1e293b;
            --slate-700: #334155;
            --slate-600: #475569;
            --slate-500: #64748b;
            --slate-400: #94a3b8;
            --slate-300: #cbd5e1;
            --slate-200: #e2e8f0;
        }
        
        .support-page {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            min-height: 100vh;
        }
        
        .support-page .admin-main-content {
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
            background: linear-gradient(135deg, var(--emerald), var(--cyan));
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
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
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
        
        .stat-box.tickets .stat-icon { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; }
        .stat-box.open .stat-icon { background: linear-gradient(135deg, var(--amber), var(--amber-light)); color: white; }
        .stat-box.errors .stat-icon { background: linear-gradient(135deg, var(--rose), var(--rose-light)); color: white; }
        .stat-box.health .stat-icon { background: linear-gradient(135deg, var(--emerald), var(--emerald-light)); color: white; }
        .stat-box.urgent .stat-icon { background: linear-gradient(135deg, #dc2626, #ef4444); color: white; }
        
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
            padding: 14px 24px;
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
            background: linear-gradient(135deg, var(--emerald), #059669);
            color: white;
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
        }
        
        .tab-link .badge {
            background: rgba(255,255,255,0.2);
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
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
            color: var(--emerald);
        }
        
        .card-body-custom {
            padding: 24px;
        }
        
        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            padding: 16px 24px;
            background: rgba(15, 23, 42, 0.3);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .filter-select {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            padding: 10px 16px;
            font-size: 0.85rem;
            min-width: 150px;
        }
        
        .filter-select:focus {
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            outline: none;
        }
        
        /* Ticket Cards */
        .ticket-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .ticket-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .ticket-card:hover {
            border-color: rgba(16, 185, 129, 0.3);
            transform: translateX(4px);
        }
        
        .ticket-card.priority-urgent {
            border-left: 4px solid #dc2626;
        }
        
        .ticket-card.priority-high {
            border-left: 4px solid var(--amber);
        }
        
        .ticket-card.priority-medium {
            border-left: 4px solid var(--blue);
        }
        
        .ticket-card.priority-low {
            border-left: 4px solid var(--slate-500);
        }
        
        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .ticket-number {
            color: var(--slate-500);
            font-size: 0.75rem;
            font-weight: 600;
            font-family: monospace;
        }
        
        .ticket-subject {
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            margin: 6px 0;
        }
        
        .ticket-meta {
            display: flex;
            gap: 16px;
            font-size: 0.75rem;
            color: var(--slate-500);
            flex-wrap: wrap;
        }
        
        .ticket-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .ticket-badges {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        /* Badges */
        .badge-status, .badge-priority, .badge-category {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-status.open { background: rgba(245, 158, 11, 0.15); color: var(--amber-light); }
        .badge-status.in_progress { background: rgba(59, 130, 246, 0.15); color: var(--blue-light); }
        .badge-status.pending { background: rgba(139, 92, 246, 0.15); color: var(--violet-light); }
        .badge-status.resolved { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .badge-status.closed { background: rgba(100, 116, 139, 0.15); color: var(--slate-400); }
        
        .badge-priority.urgent { background: rgba(220, 38, 38, 0.2); color: #fca5a5; }
        .badge-priority.high { background: rgba(245, 158, 11, 0.15); color: var(--amber-light); }
        .badge-priority.medium { background: rgba(59, 130, 246, 0.15); color: var(--blue-light); }
        .badge-priority.low { background: rgba(100, 116, 139, 0.15); color: var(--slate-400); }
        
        .badge-category { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        
        /* Error Log Cards */
        .error-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .error-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 16px 20px;
            transition: all 0.3s ease;
        }
        
        .error-card:hover {
            border-color: rgba(244, 63, 94, 0.3);
        }
        
        .error-card.severity-critical {
            border-left: 4px solid #dc2626;
            background: rgba(220, 38, 38, 0.05);
        }
        
        .error-card.severity-error {
            border-left: 4px solid var(--rose);
        }
        
        .error-card.severity-warning {
            border-left: 4px solid var(--amber);
        }
        
        .error-card.severity-info {
            border-left: 4px solid var(--blue);
        }
        
        .error-card.resolved {
            opacity: 0.6;
        }
        
        .error-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .error-type {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-type-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .error-type-icon.php { background: linear-gradient(135deg, #8b5cf6, #a78bfa); color: white; }
        .error-type-icon.javascript { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; }
        .error-type-icon.database { background: linear-gradient(135deg, #3b82f6, #60a5fa); color: white; }
        .error-type-icon.security { background: linear-gradient(135deg, #dc2626, #ef4444); color: white; }
        .error-type-icon.api { background: linear-gradient(135deg, #06b6d4, #22d3ee); color: white; }
        .error-type-icon.system { background: linear-gradient(135deg, #6366f1, #818cf8); color: white; }
        
        .error-message {
            color: #fff;
            font-size: 0.9rem;
            margin-bottom: 8px;
            font-family: 'Monaco', 'Menlo', monospace;
            word-break: break-word;
        }
        
        .error-location {
            color: var(--slate-500);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .severity-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .severity-badge.critical { background: rgba(220, 38, 38, 0.2); color: #fca5a5; }
        .severity-badge.error { background: rgba(244, 63, 94, 0.15); color: var(--rose-light); }
        .severity-badge.warning { background: rgba(245, 158, 11, 0.15); color: var(--amber-light); }
        .severity-badge.info { background: rgba(59, 130, 246, 0.15); color: var(--blue-light); }
        
        /* System Status Grid */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        
        .status-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .status-card:hover {
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .status-card.operational {
            border-left: 4px solid var(--emerald);
        }
        
        .status-card.degraded {
            border-left: 4px solid var(--amber);
        }
        
        .status-card.partial_outage {
            border-left: 4px solid #f97316;
        }
        
        .status-card.major_outage {
            border-left: 4px solid #dc2626;
        }
        
        .status-card.maintenance {
            border-left: 4px solid var(--violet);
        }
        
        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .component-name {
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .component-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--emerald), #059669);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-dot.operational { background: var(--emerald); box-shadow: 0 0 10px var(--emerald); }
        .status-dot.degraded { background: var(--amber); box-shadow: 0 0 10px var(--amber); }
        .status-dot.partial_outage { background: #f97316; box-shadow: 0 0 10px #f97316; }
        .status-dot.major_outage { background: #dc2626; box-shadow: 0 0 10px #dc2626; animation: pulse-fast 1s infinite; }
        .status-dot.maintenance { background: var(--violet); box-shadow: 0 0 10px var(--violet); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        @keyframes pulse-fast {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        
        .status-text.operational { color: var(--emerald-light); }
        .status-text.degraded { color: var(--amber-light); }
        .status-text.partial_outage { color: #fb923c; }
        .status-text.major_outage { color: #fca5a5; }
        .status-text.maintenance { color: var(--violet-light); }
        
        .response-time {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--slate-500);
            font-size: 0.8rem;
        }
        
        .response-time i {
            color: var(--cyan);
        }
        
        /* Maintenance Schedule */
        .maintenance-section {
            margin-top: 32px;
        }
        
        .maintenance-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .maintenance-card {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .maintenance-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--violet), var(--violet-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .maintenance-info {
            flex: 1;
        }
        
        .maintenance-title {
            color: #fff;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }
        
        .maintenance-time {
            color: var(--slate-500);
            font-size: 0.8rem;
        }
        
        .maintenance-status {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .maintenance-status.scheduled { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .maintenance-status.in_progress { background: rgba(245, 158, 11, 0.15); color: var(--amber-light); }
        .maintenance-status.completed { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .maintenance-status.cancelled { background: rgba(100, 116, 139, 0.15); color: var(--slate-400); }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--emerald), #059669);
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
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.4);
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
        
        .btn-view { background: rgba(99, 102, 241, 0.15); color: var(--primary-light); }
        .btn-view:hover { background: rgba(99, 102, 241, 0.25); color: var(--primary-light); }
        
        .btn-resolve { background: rgba(16, 185, 129, 0.15); color: var(--emerald-light); }
        .btn-resolve:hover { background: rgba(16, 185, 129, 0.25); color: var(--emerald-light); }
        
        .btn-delete { background: rgba(244, 63, 94, 0.15); color: var(--rose-light); }
        .btn-delete:hover { background: rgba(244, 63, 94, 0.25); color: var(--rose-light); }
        
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
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
            color: #fff;
        }
        
        .form-control::placeholder { color: var(--slate-500); }
        
        .form-label {
            color: var(--slate-300);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }
        
        /* Modal Styles */
        .modal-content {
            background: var(--slate-800);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--emerald), #059669);
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
            background: linear-gradient(135deg, var(--emerald), #059669);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }
        
        .btn-modal-save:hover {
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.3);
            color: white;
        }
        
        /* Ticket Detail View */
        .ticket-detail {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        @media (max-width: 992px) {
            .ticket-detail {
                grid-template-columns: 1fr;
            }
        }
        
        .ticket-conversation {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 24px;
        }
        
        .ticket-info-panel {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 24px;
            height: fit-content;
        }
        
        .reply-item {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
        }
        
        .reply-item.admin {
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid var(--emerald);
        }
        
        .reply-item.user {
            background: rgba(99, 102, 241, 0.1);
            border-left: 3px solid var(--primary);
        }
        
        .reply-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .reply-author {
            color: #fff;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .reply-time {
            color: var(--slate-500);
            font-size: 0.75rem;
        }
        
        .reply-content {
            color: var(--slate-300);
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .info-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-label {
            color: var(--slate-500);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        
        .info-value {
            color: #fff;
            font-size: 0.9rem;
            font-weight: 500;
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
        
        /* Overall System Status */
        .overall-status {
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .overall-status h3 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .overall-status p {
            color: var(--slate-500);
            margin: 0;
        }
        
        .overall-status.all-operational {
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .overall-status.all-operational h3 {
            color: var(--emerald-light);
        }
        
        .status-summary {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-top: 20px;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-number {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
        }
        
        .summary-label {
            font-size: 0.8rem;
            color: var(--slate-500);
        }
    </style>
</head>
<body class="admin-layout support-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-headset"></i> Support & Maintenance</h1>
            <p>Manage helpdesk tickets, monitor error logs, and track system health</p>
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
            <div class="stat-box tickets">
                <div class="stat-icon"><i class="fas fa-ticket-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_tickets']; ?></h3>
                    <span>Total Tickets</span>
                </div>
            </div>
            <div class="stat-box open">
                <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['open_tickets']; ?></h3>
                    <span>Open Tickets</span>
                </div>
            </div>
            <div class="stat-box urgent">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['urgent_tickets']; ?></h3>
                    <span>Urgent Tickets</span>
                </div>
            </div>
            <div class="stat-box errors">
                <div class="stat-icon"><i class="fas fa-bug"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['unresolved_errors']; ?></h3>
                    <span>Unresolved Errors</span>
                </div>
            </div>
            <div class="stat-box health">
                <div class="stat-icon"><i class="fas fa-heartbeat"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['healthy_components']; ?>/<?php echo $stats['total_components']; ?></h3>
                    <span>Systems Healthy</span>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <a href="?tab=helpdesk" class="tab-link <?php echo $currentTab === 'helpdesk' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i> Helpdesk
                <?php if ($stats['open_tickets'] > 0): ?>
                    <span class="badge"><?php echo $stats['open_tickets']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=errors" class="tab-link <?php echo $currentTab === 'errors' ? 'active' : ''; ?>">
                <i class="fas fa-bug"></i> Error Logs
                <?php if ($stats['critical_errors'] > 0): ?>
                    <span class="badge" style="background: rgba(220, 38, 38, 0.5);"><?php echo $stats['critical_errors']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=status" class="tab-link <?php echo $currentTab === 'status' ? 'active' : ''; ?>">
                <i class="fas fa-server"></i> System Status
            </a>
        </div>

        <!-- Main Content Card -->
        <div class="main-card">
            <?php if ($currentTab === 'helpdesk'): ?>
            <!-- Helpdesk Tab -->
            <?php if ($ticketDetails): ?>
            <!-- Ticket Detail View -->
            <div class="card-header-custom">
                <h5>
                    <i class="fas fa-ticket-alt"></i> 
                    <?php echo htmlspecialchars($ticketDetails['ticket_number']); ?>
                    <span class="badge-status <?php echo $ticketDetails['status']; ?>" style="margin-left: 12px;">
                        <?php echo ucfirst(str_replace('_', ' ', $ticketDetails['status'])); ?>
                    </span>
                </h5>
                <a href="?tab=helpdesk" class="btn-action btn-view">
                    <i class="fas fa-arrow-left"></i> Back to Tickets
                </a>
            </div>
            <div class="card-body-custom">
                <div class="ticket-detail">
                    <div class="ticket-conversation">
                        <h6 style="color: #fff; font-weight: 600; margin-bottom: 8px;">
                            <?php echo htmlspecialchars($ticketDetails['subject']); ?>
                        </h6>
                        <p style="color: var(--slate-400); font-size: 0.9rem; margin-bottom: 24px; line-height: 1.6;">
                            <?php echo nl2br(htmlspecialchars($ticketDetails['description'])); ?>
                        </p>
                        
                        <h6 style="color: var(--slate-400); font-size: 0.85rem; font-weight: 600; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <i class="fas fa-comments me-2"></i>Conversation
                        </h6>
                        
                        <?php if (empty($ticketReplies)): ?>
                            <p style="color: var(--slate-500); text-align: center; padding: 20px;">No replies yet</p>
                        <?php else: ?>
                            <?php foreach ($ticketReplies as $reply): ?>
                            <div class="reply-item <?php echo $reply['is_admin'] ? 'admin' : 'user'; ?>">
                                <div class="reply-header">
                                    <span class="reply-author">
                                        <i class="fas fa-<?php echo $reply['is_admin'] ? 'user-shield' : 'user'; ?> me-2"></i>
                                        <?php echo htmlspecialchars($reply['user_name'] ?? 'User'); ?>
                                        <?php if ($reply['is_admin']): ?>
                                            <span class="badge-category" style="margin-left: 8px;">Admin</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="reply-time"><?php echo date('M j, Y g:i A', strtotime($reply['created_at'])); ?></span>
                                </div>
                                <div class="reply-content">
                                    <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Reply Form -->
                        <form method="POST" style="margin-top: 24px;">
                            <input type="hidden" name="action" value="reply_ticket">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticketDetails['id']; ?>">
                            <div class="mb-3">
                                <label class="form-label">Reply to Ticket</label>
                                <textarea name="reply_message" class="form-control" rows="4" placeholder="Type your reply..." required></textarea>
                            </div>
                            <button type="submit" class="btn-primary-custom">
                                <i class="fas fa-paper-plane"></i> Send Reply
                            </button>
                        </form>
                    </div>
                    
                    <div class="ticket-info-panel">
                        <h6 style="color: #fff; font-weight: 600; margin-bottom: 20px;">
                            <i class="fas fa-info-circle me-2" style="color: var(--emerald);"></i>Ticket Information
                        </h6>
                        
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="update_ticket_status">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticketDetails['id']; ?>">
                                <select name="status" class="form-select" style="flex: 1;">
                                    <option value="open" <?php echo $ticketDetails['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $ticketDetails['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="pending" <?php echo $ticketDetails['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="resolved" <?php echo $ticketDetails['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                    <option value="closed" <?php echo $ticketDetails['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                                <button type="submit" class="btn-action btn-resolve">
                                    <i class="fas fa-save"></i>
                                </button>
                            </form>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Priority</div>
                            <div class="info-value">
                                <span class="badge-priority <?php echo $ticketDetails['priority']; ?>">
                                    <?php echo ucfirst($ticketDetails['priority']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Category</div>
                            <div class="info-value">
                                <span class="badge-category">
                                    <?php echo ucfirst(str_replace('_', ' ', $ticketDetails['category'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">User Type</div>
                            <div class="info-value"><?php echo ucfirst($ticketDetails['user_type']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($ticketDetails['user_email']); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">Created</div>
                            <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($ticketDetails['created_at'])); ?></div>
                        </div>
                        
                        <?php if ($ticketDetails['resolved_at']): ?>
                        <div class="info-item">
                            <div class="info-label">Resolved</div>
                            <div class="info-value"><?php echo date('M j, Y g:i A', strtotime($ticketDetails['resolved_at'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Tickets List View -->
            <div class="card-header-custom">
                <h5><i class="fas fa-headset"></i> Support Tickets</h5>
            </div>
            <div class="filter-bar">
                <select class="filter-select" onchange="applyFilters();" id="statusFilter">
                    <option value="all" <?php echo ($_GET['status'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="open" <?php echo ($_GET['status'] ?? '') === 'open' ? 'selected' : ''; ?>>Open</option>
                    <option value="in_progress" <?php echo ($_GET['status'] ?? '') === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="resolved" <?php echo ($_GET['status'] ?? '') === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="closed" <?php echo ($_GET['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                </select>
                <select class="filter-select" onchange="applyFilters();" id="priorityFilter">
                    <option value="all" <?php echo ($_GET['priority'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Priority</option>
                    <option value="urgent" <?php echo ($_GET['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="high" <?php echo ($_GET['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo ($_GET['priority'] ?? '') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo ($_GET['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
                <select class="filter-select" onchange="applyFilters();" id="categoryFilter">
                    <option value="all" <?php echo ($_GET['category'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <option value="technical" <?php echo ($_GET['category'] ?? '') === 'technical' ? 'selected' : ''; ?>>Technical</option>
                    <option value="billing" <?php echo ($_GET['category'] ?? '') === 'billing' ? 'selected' : ''; ?>>Billing</option>
                    <option value="account" <?php echo ($_GET['category'] ?? '') === 'account' ? 'selected' : ''; ?>>Account</option>
                    <option value="general" <?php echo ($_GET['category'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                    <option value="bug_report" <?php echo ($_GET['category'] ?? '') === 'bug_report' ? 'selected' : ''; ?>>Bug Report</option>
                    <option value="feature_request" <?php echo ($_GET['category'] ?? '') === 'feature_request' ? 'selected' : ''; ?>>Feature Request</option>
                </select>
            </div>
            <div class="card-body-custom">
                <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-ticket-alt"></i>
                    <h5>No Tickets Found</h5>
                    <p>There are no support tickets matching your filters.</p>
                </div>
                <?php else: ?>
                <div class="ticket-list">
                    <?php foreach ($tickets as $ticket): ?>
                    <a href="?tab=helpdesk&ticket_id=<?php echo $ticket['id']; ?>" style="text-decoration: none;">
                        <div class="ticket-card priority-<?php echo $ticket['priority']; ?>">
                            <div class="ticket-header">
                                <div>
                                    <span class="ticket-number"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                    <h6 class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></h6>
                                </div>
                                <div class="ticket-badges">
                                    <span class="badge-status <?php echo $ticket['status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                    <span class="badge-priority <?php echo $ticket['priority']; ?>">
                                        <?php echo ucfirst($ticket['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ticket-meta">
                                <span><i class="fas fa-tag"></i> <?php echo ucfirst(str_replace('_', ' ', $ticket['category'])); ?></span>
                                <span><i class="fas fa-user"></i> <?php echo ucfirst($ticket['user_type']); ?></span>
                                <span><i class="fas fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php elseif ($currentTab === 'errors'): ?>
            <!-- Error Logs Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-bug"></i> Error Logs</h5>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="clear_resolved_errors">
                    <button type="submit" class="btn-action btn-delete" onclick="return confirm('Clear all resolved errors?')">
                        <i class="fas fa-trash"></i> Clear Resolved
                    </button>
                </form>
            </div>
            <div class="filter-bar">
                <select class="filter-select" onchange="applyErrorFilters();" id="typeFilter">
                    <option value="all" <?php echo ($_GET['type'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <option value="php" <?php echo ($_GET['type'] ?? '') === 'php' ? 'selected' : ''; ?>>PHP</option>
                    <option value="javascript" <?php echo ($_GET['type'] ?? '') === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                    <option value="database" <?php echo ($_GET['type'] ?? '') === 'database' ? 'selected' : ''; ?>>Database</option>
                    <option value="security" <?php echo ($_GET['type'] ?? '') === 'security' ? 'selected' : ''; ?>>Security</option>
                    <option value="api" <?php echo ($_GET['type'] ?? '') === 'api' ? 'selected' : ''; ?>>API</option>
                    <option value="system" <?php echo ($_GET['type'] ?? '') === 'system' ? 'selected' : ''; ?>>System</option>
                </select>
                <select class="filter-select" onchange="applyErrorFilters();" id="severityFilter">
                    <option value="all" <?php echo ($_GET['severity'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Severity</option>
                    <option value="critical" <?php echo ($_GET['severity'] ?? '') === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    <option value="error" <?php echo ($_GET['severity'] ?? '') === 'error' ? 'selected' : ''; ?>>Error</option>
                    <option value="warning" <?php echo ($_GET['severity'] ?? '') === 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="info" <?php echo ($_GET['severity'] ?? '') === 'info' ? 'selected' : ''; ?>>Info</option>
                </select>
                <select class="filter-select" onchange="applyErrorFilters();" id="resolvedFilter">
                    <option value="unresolved" <?php echo ($_GET['resolved'] ?? 'unresolved') === 'unresolved' ? 'selected' : ''; ?>>Unresolved</option>
                    <option value="resolved" <?php echo ($_GET['resolved'] ?? '') === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="all" <?php echo ($_GET['resolved'] ?? '') === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <div class="card-body-custom">
                <?php if (empty($errors)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="color: var(--emerald);"></i>
                    <h5>No Errors Found</h5>
                    <p>Great! There are no error logs matching your filters.</p>
                </div>
                <?php else: ?>
                <div class="error-list">
                    <?php foreach ($errors as $err): ?>
                    <div class="error-card severity-<?php echo $err['severity']; ?> <?php echo $err['is_resolved'] ? 'resolved' : ''; ?>">
                        <div class="error-header">
                            <div class="error-type">
                                <div class="error-type-icon <?php echo $err['error_type']; ?>">
                                    <?php
                                    $icons = [
                                        'php' => 'fa-php',
                                        'javascript' => 'fa-js',
                                        'database' => 'fa-database',
                                        'security' => 'fa-shield-alt',
                                        'api' => 'fa-plug',
                                        'system' => 'fa-server'
                                    ];
                                    ?>
                                    <i class="fab <?php echo $icons[$err['error_type']] ?? 'fas fa-exclamation'; ?>"></i>
                                </div>
                                <div>
                                    <span style="color: #fff; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">
                                        <?php echo $err['error_type']; ?>
                                    </span>
                                    <span class="severity-badge <?php echo $err['severity']; ?>" style="margin-left: 8px;">
                                        <?php echo ucfirst($err['severity']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <?php if ($err['is_resolved']): ?>
                                    <span class="badge-status resolved">
                                        <i class="fas fa-check me-1"></i>Resolved
                                    </span>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="resolve_error">
                                        <input type="hidden" name="error_id" value="<?php echo $err['id']; ?>">
                                        <button type="submit" class="btn-action btn-resolve">
                                            <i class="fas fa-check"></i> Resolve
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="error-message">
                            <?php echo htmlspecialchars($err['message']); ?>
                        </div>
                        <div class="error-location">
                            <?php if ($err['file']): ?>
                                <span><i class="fas fa-file-code me-1"></i><?php echo htmlspecialchars($err['file']); ?></span>
                            <?php endif; ?>
                            <?php if ($err['line']): ?>
                                <span><i class="fas fa-code me-1"></i>Line <?php echo $err['line']; ?></span>
                            <?php endif; ?>
                            <span><i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($err['created_at'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php elseif ($currentTab === 'status'): ?>
            <!-- System Status Tab -->
            <div class="card-header-custom">
                <h5><i class="fas fa-server"></i> System Status</h5>
                <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#scheduleMaintenanceModal">
                    <i class="fas fa-calendar-plus"></i> Schedule Maintenance
                </button>
            </div>
            <div class="card-body-custom">
                <!-- Overall Status -->
                <div class="overall-status <?php echo $stats['healthy_components'] == $stats['total_components'] ? 'all-operational' : ''; ?>">
                    <?php if ($stats['healthy_components'] == $stats['total_components']): ?>
                        <h3><i class="fas fa-check-circle me-2"></i>All Systems Operational</h3>
                        <p>All components are running smoothly</p>
                    <?php else: ?>
                        <h3 style="color: var(--amber-light);"><i class="fas fa-exclamation-circle me-2"></i>Some Systems Degraded</h3>
                        <p><?php echo $stats['total_components'] - $stats['healthy_components']; ?> component(s) require attention</p>
                    <?php endif; ?>
                    <div class="status-summary">
                        <div class="summary-item">
                            <div class="summary-number" style="color: var(--emerald-light);"><?php echo $stats['healthy_components']; ?></div>
                            <div class="summary-label">Operational</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-number"><?php echo $stats['total_components']; ?></div>
                            <div class="summary-label">Total Components</div>
                        </div>
                    </div>
                </div>
                
                <!-- Component Status Grid -->
                <h6 style="color: var(--slate-400); font-size: 0.85rem; font-weight: 600; margin-bottom: 16px;">
                    <i class="fas fa-cubes me-2"></i>Component Status
                </h6>
                <div class="status-grid">
                    <?php 
                    $componentIcons = [
                        'Web Server' => 'fa-globe',
                        'Database' => 'fa-database',
                        'File Storage' => 'fa-hdd',
                        'Email Service' => 'fa-envelope',
                        'API Gateway' => 'fa-plug',
                        'Search Engine' => 'fa-search',
                        'Cache Server' => 'fa-bolt',
                        'Background Jobs' => 'fa-tasks'
                    ];
                    foreach ($components as $comp): 
                    ?>
                    <div class="status-card <?php echo $comp['status']; ?>">
                        <div class="status-header">
                            <div class="component-name">
                                <div class="component-icon">
                                    <i class="fas <?php echo $componentIcons[$comp['component']] ?? 'fa-server'; ?>"></i>
                                </div>
                                <?php echo htmlspecialchars($comp['component']); ?>
                            </div>
                            <div class="status-indicator">
                                <span class="status-dot <?php echo $comp['status']; ?>"></span>
                                <span class="status-text <?php echo $comp['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $comp['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="response-time">
                                <i class="fas fa-tachometer-alt"></i>
                                <?php echo $comp['response_time']; ?>ms response time
                            </div>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="update_component_status">
                                <input type="hidden" name="component_id" value="<?php echo $comp['id']; ?>">
                                <select name="status" class="filter-select" style="padding: 6px 12px; font-size: 0.75rem; min-width: auto;" onchange="this.form.submit()">
                                    <option value="operational" <?php echo $comp['status'] === 'operational' ? 'selected' : ''; ?>>Operational</option>
                                    <option value="degraded" <?php echo $comp['status'] === 'degraded' ? 'selected' : ''; ?>>Degraded</option>
                                    <option value="partial_outage" <?php echo $comp['status'] === 'partial_outage' ? 'selected' : ''; ?>>Partial Outage</option>
                                    <option value="major_outage" <?php echo $comp['status'] === 'major_outage' ? 'selected' : ''; ?>>Major Outage</option>
                                    <option value="maintenance" <?php echo $comp['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                </select>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Scheduled Maintenance -->
                <div class="maintenance-section">
                    <h6 style="color: var(--slate-400); font-size: 0.85rem; font-weight: 600; margin-bottom: 16px;">
                        <i class="fas fa-calendar-alt me-2"></i>Scheduled Maintenance
                    </h6>
                    <?php if (empty($maintenances)): ?>
                    <div class="empty-state" style="padding: 40px;">
                        <i class="fas fa-calendar-check" style="font-size: 40px;"></i>
                        <h5>No Scheduled Maintenance</h5>
                        <p>There are no upcoming maintenance windows.</p>
                    </div>
                    <?php else: ?>
                    <div class="maintenance-list">
                        <?php foreach ($maintenances as $maint): ?>
                        <div class="maintenance-card">
                            <div class="maintenance-icon">
                                <i class="fas fa-wrench"></i>
                            </div>
                            <div class="maintenance-info">
                                <div class="maintenance-title"><?php echo htmlspecialchars($maint['title']); ?></div>
                                <div class="maintenance-time">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('M j, Y g:i A', strtotime($maint['scheduled_start'])); ?> - 
                                    <?php echo date('g:i A', strtotime($maint['scheduled_end'])); ?>
                                </div>
                            </div>
                            <span class="maintenance-status <?php echo $maint['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $maint['status'])); ?>
                            </span>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete_maintenance">
                                <input type="hidden" name="maintenance_id" value="<?php echo $maint['id']; ?>">
                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this maintenance schedule?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Schedule Maintenance Modal -->
    <div class="modal fade" id="scheduleMaintenanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Schedule Maintenance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="schedule_maintenance">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g., Database Upgrade" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Describe the maintenance work..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="datetime-local" name="scheduled_start" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input type="datetime-local" name="scheduled_end" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Affected Services</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($components as $comp): ?>
                                <label style="display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: rgba(15,23,42,0.5); border-radius: 8px; cursor: pointer;">
                                    <input type="checkbox" name="services[]" value="<?php echo htmlspecialchars($comp['component']); ?>" class="form-check-input" style="margin: 0;">
                                    <span style="color: var(--slate-300); font-size: 0.85rem;"><?php echo htmlspecialchars($comp['component']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modal-save">Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const priority = document.getElementById('priorityFilter').value;
            const category = document.getElementById('categoryFilter').value;
            
            let url = '?tab=helpdesk';
            if (status !== 'all') url += '&status=' + status;
            if (priority !== 'all') url += '&priority=' + priority;
            if (category !== 'all') url += '&category=' + category;
            
            window.location.href = url;
        }
        
        function applyErrorFilters() {
            const type = document.getElementById('typeFilter').value;
            const severity = document.getElementById('severityFilter').value;
            const resolved = document.getElementById('resolvedFilter').value;
            
            let url = '?tab=errors';
            if (type !== 'all') url += '&type=' + type;
            if (severity !== 'all') url += '&severity=' + severity;
            url += '&resolved=' + resolved;
            
            window.location.href = url;
        }
    </script>
</body>
</html>
