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
.support-admin-page .admin-main-content {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
            padding: 1.5rem 2rem 2.5rem;
        }

        .support-page-header {
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .support-page-header h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .support-page-header h1 i {
            color: #059669;
            opacity: 0.95;
        }

        .support-page-header p {
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
            border-color: rgba(5, 150, 105, 0.28);
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.1);
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

        .stat-box.tickets .stat-icon { background: #eef2ff; color: #2563eb; }
        .stat-box.open .stat-icon { background: #fffbeb; color: #d97706; }
        .stat-box.errors .stat-icon { background: #ffe4e6; color: #be123c; }
        .stat-box.health .stat-icon { background: #ecfdf5; color: #059669; }
        .stat-box.urgent .stat-icon { background: #fee2e2; color: #dc2626; }

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
            padding: 10px 18px;
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

        .tab-link .badge {
            background: #e0e7ff;
            color: #4338ca;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .tab-link.active .badge {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .tab-badge-critical {
            background: #fee2e2 !important;
            color: #b91c1c !important;
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
            flex-wrap: wrap;
        }

        .card-header-custom h5 i {
            color: #059669;
        }

        .card-body-custom {
            padding: 1.35rem 1.35rem 1.5rem;
            background: #fff;
        }

        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            padding: 14px 1.35rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .filter-select {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #0f172a;
            padding: 10px 14px;
            font-size: 0.85rem;
            min-width: 150px;
        }

        .filter-select:focus {
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
            outline: none;
        }

        .filter-select--compact {
            padding: 6px 12px;
            font-size: 0.75rem;
            min-width: auto;
        }

        .ticket-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .ticket-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            transition: box-shadow 0.2s, border-color 0.2s;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .ticket-card:hover {
            border-color: rgba(5, 150, 105, 0.35);
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        }

        .ticket-card.priority-urgent { border-left: 4px solid #dc2626; }
        .ticket-card.priority-high { border-left: 4px solid #d97706; }
        .ticket-card.priority-medium { border-left: 4px solid #2563eb; }
        .ticket-card.priority-low { border-left: 4px solid #94a3b8; }

        .ticket-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .ticket-number {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
            font-family: ui-monospace, 'Consolas', monospace;
        }

        .ticket-subject {
            color: #0f172a;
            font-weight: 600;
            font-size: 1rem;
            margin: 6px 0;
        }

        .ticket-meta {
            display: flex;
            gap: 14px;
            font-size: 0.75rem;
            color: #64748b;
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

        .badge-status, .badge-priority, .badge-category {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-status.open { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-status.in_progress { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
        .badge-status.pending { background: #ede9fe; color: #6d28d9; border: 1px solid #c4b5fd; }
        .badge-status.resolved { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge-status.closed { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .badge-priority.urgent { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-priority.high { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .badge-priority.medium { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-priority.low { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        .badge-category { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge-category--spaced { margin-left: 8px; }

        .supp-status-inline { margin-left: 12px; }

        .error-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .error-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 18px;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .error-card:hover {
            border-color: rgba(190, 18, 60, 0.25);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        .error-card.severity-critical {
            border-left: 4px solid #dc2626;
            background: #fef2f2;
        }

        .error-card.severity-error {
            border-left: 4px solid #e11d48;
        }

        .error-card.severity-warning {
            border-left: 4px solid #d97706;
        }

        .error-card.severity-info {
            border-left: 4px solid #2563eb;
        }

        .error-card.resolved {
            opacity: 0.72;
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

        .error-type-icon.php { background: #ede9fe; color: #6d28d9; }
        .error-type-icon.javascript { background: #fffbeb; color: #b45309; }
        .error-type-icon.database { background: #dbeafe; color: #1d4ed8; }
        .error-type-icon.security { background: #fee2e2; color: #b91c1c; }
        .error-type-icon.api { background: #ecfeff; color: #0e7490; }
        .error-type-icon.system { background: #eef2ff; color: #4338ca; }

        .error-message {
            color: #0f172a;
            font-size: 0.875rem;
            margin-bottom: 8px;
            font-family: ui-monospace, 'Consolas', monospace;
            word-break: break-word;
        }

        .error-location {
            color: #64748b;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-type-label {
            color: #0f172a;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .severity-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .severity-badge.critical { background: #fee2e2; color: #991b1b; }
        .severity-badge.error { background: #ffe4e6; color: #be123c; }
        .severity-badge.warning { background: #fffbeb; color: #b45309; }
        .severity-badge.info { background: #dbeafe; color: #1e40af; }

        .severity-inline { margin-left: 8px; }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }

        .status-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            transition: box-shadow 0.2s, border-color 0.2s;
        }

        .status-card:hover {
            border-color: rgba(5, 150, 105, 0.35);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        }

        .status-card.operational { border-left: 4px solid #059669; }
        .status-card.degraded { border-left: 4px solid #d97706; }
        .status-card.partial_outage { border-left: 4px solid #ea580c; }
        .status-card.major_outage { border-left: 4px solid #dc2626; }
        .status-card.maintenance { border-left: 4px solid #7c3aed; }

        .status-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .component-name {
            color: #0f172a;
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
            background: #ecfdf5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #059669;
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
            animation: supp-pulse 2s infinite;
        }

        .status-dot.operational { background: #059669; }
        .status-dot.degraded { background: #d97706; }
        .status-dot.partial_outage { background: #ea580c; }
        .status-dot.major_outage { background: #dc2626; animation: supp-pulse-fast 1s infinite; }
        .status-dot.maintenance { background: #7c3aed; }

        @keyframes supp-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.45; }
        }

        @keyframes supp-pulse-fast {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.15); }
        }

        .status-text.operational { color: #047857; }
        .status-text.degraded { color: #b45309; }
        .status-text.partial_outage { color: #c2410c; }
        .status-text.major_outage { color: #b91c1c; }
        .status-text.maintenance { color: #6d28d9; }

        .response-time {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #64748b;
            font-size: 0.8rem;
        }

        .response-time i {
            color: #0891b2;
        }

        .maintenance-section {
            margin-top: 28px;
        }

        .maintenance-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .maintenance-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .maintenance-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #ede9fe;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6d28d9;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .maintenance-info { flex: 1; }

        .maintenance-title {
            color: #0f172a;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .maintenance-time {
            color: #64748b;
            font-size: 0.8rem;
        }

        .maintenance-status {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.68rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .maintenance-status.scheduled { background: #eef2ff; color: #4338ca; }
        .maintenance-status.in_progress { background: #fffbeb; color: #b45309; }
        .maintenance-status.completed { background: #ecfdf5; color: #047857; }
        .maintenance-status.cancelled { background: #f1f5f9; color: #64748b; }

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
            background: #fff;
            color: #2563eb;
            border-color: rgba(37, 99, 235, 0.35);
        }
        .btn-view:hover { background: #eef2ff; }

        .btn-resolve {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }
        .btn-resolve:hover { background: #d1fae5; }

        .btn-delete {
            background: #fff;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .btn-delete:hover { background: #fef2f2; }

        .support-admin-page .form-control,
        .support-admin-page .form-select {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #0f172a;
            padding: 10px 14px;
            font-size: 0.9rem;
        }

        .support-admin-page .form-control:focus,
        .support-admin-page .form-select:focus {
            background: #fff;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
            color: #0f172a;
        }

        .support-admin-page .form-control::placeholder {
            color: #94a3b8;
        }

        .support-admin-page .form-label {
            color: #334155;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .support-admin-page .form-check-input {
            margin: 0;
        }

        .support-admin-page .modal-content {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
        }

        .support-admin-page .modal-header {
            background: linear-gradient(120deg, #ecfdf5 0%, #eef4ff 100%);
            border-bottom: 1px solid #e2e8f0;
            border-radius: 14px 14px 0 0;
            padding: 16px 20px;
        }

        .support-admin-page .modal-title {
            color: #1e3a8a;
            font-weight: 700;
        }

        .support-admin-page .modal-body {
            padding: 20px;
            color: #334155;
        }

        .support-admin-page .modal-footer {
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
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
        }

        .ticket-info-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.25rem;
            height: fit-content;
        }

        .supp-ticket-subject {
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .supp-ticket-desc {
            color: #475569;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .supp-section-heading {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .supp-empty-replies {
            color: #64748b;
            text-align: center;
            padding: 20px;
        }

        .supp-reply-form {
            margin-top: 24px;
        }

        .supp-panel-heading {
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .supp-panel-heading .fa-info-circle {
            color: #059669;
        }

        .form-select--flex {
            flex: 1;
        }

        .reply-item {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 14px;
        }

        .reply-item.admin {
            background: #ecfdf5;
            border-left: 3px solid #059669;
        }

        .reply-item.user {
            background: #eef2ff;
            border-left: 3px solid #6366f1;
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .reply-author {
            color: #0f172a;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .reply-time {
            color: #64748b;
            font-size: 0.75rem;
        }

        .reply-content {
            color: #334155;
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .info-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .info-value {
            color: #0f172a;
            font-size: 0.9rem;
            font-weight: 500;
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

        .empty-state--compact {
            padding: 40px;
        }

        .empty-state--compact i {
            font-size: 40px;
        }

        .overall-status {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .overall-status h3 {
            color: #0f172a;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .overall-status p {
            color: #64748b;
            margin: 0;
        }

        .overall-status.all-operational {
            border-color: rgba(5, 150, 105, 0.35);
            background: linear-gradient(120deg, #f0fdf4 0%, #f8fafc 100%);
        }

        .overall-status.all-operational h3 {
            color: #047857;
        }

        .overall-status-degraded h3 {
            color: #b45309;
        }

        .status-ok-icon {
            color: #059669;
        }

        .status-summary {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .summary-item {
            text-align: center;
        }

        .summary-number {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
        }

        .summary-number--success {
            color: #047857;
        }

        .summary-label {
            font-size: 0.8rem;
            color: #64748b;
        }

        .section-heading-muted {
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .maintenance-service-option {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
        }

        .maintenance-service-option span {
            color: #475569;
            font-size: 0.85rem;
        }
    </style>
</head>
<body class="admin-layout support-admin-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="support-page-header">
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
                    <span class="badge tab-badge-critical"><?php echo $stats['critical_errors']; ?></span>
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
                    <span class="badge-status <?php echo $ticketDetails['status']; ?> supp-status-inline">
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
                        <h6 class="supp-ticket-subject">
                            <?php echo htmlspecialchars($ticketDetails['subject']); ?>
                        </h6>
                        <p class="supp-ticket-desc">
                            <?php echo nl2br(htmlspecialchars($ticketDetails['description'])); ?>
                        </p>
                        
                        <h6 class="supp-section-heading">
                            <i class="fas fa-comments me-2"></i>Conversation
                        </h6>
                        
                        <?php if (empty($ticketReplies)): ?>
                            <p class="supp-empty-replies">No replies yet</p>
                        <?php else: ?>
                            <?php foreach ($ticketReplies as $reply): ?>
                            <div class="reply-item <?php echo $reply['is_admin'] ? 'admin' : 'user'; ?>">
                                <div class="reply-header">
                                    <span class="reply-author">
                                        <i class="fas fa-<?php echo $reply['is_admin'] ? 'user-shield' : 'user'; ?> me-2"></i>
                                        <?php echo htmlspecialchars($reply['user_name'] ?? 'User'); ?>
                                        <?php if ($reply['is_admin']): ?>
                                            <span class="badge-category badge-category--spaced">Admin</span>
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
                        <form method="POST" class="supp-reply-form">
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
                        <h6 class="supp-panel-heading">
                            <i class="fas fa-info-circle me-2"></i>Ticket Information
                        </h6>
                        
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <form method="POST" class="d-flex gap-2">
                                <input type="hidden" name="action" value="update_ticket_status">
                                <input type="hidden" name="ticket_id" value="<?php echo $ticketDetails['id']; ?>">
                                <select name="status" class="form-select form-select--flex">
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
                    <a href="?tab=helpdesk&ticket_id=<?php echo $ticket['id']; ?>" class="ticket-card-link">
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
                    <i class="fas fa-check-circle status-ok-icon"></i>
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
                                    <span class="error-type-label">
                                        <?php echo $err['error_type']; ?>
                                    </span>
                                    <span class="severity-badge <?php echo $err['severity']; ?> severity-inline">
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
                <div class="overall-status <?php echo $stats['healthy_components'] == $stats['total_components'] ? 'all-operational' : 'overall-status-degraded'; ?>">
                    <?php if ($stats['healthy_components'] == $stats['total_components']): ?>
                        <h3><i class="fas fa-check-circle me-2 status-ok-icon"></i>All Systems Operational</h3>
                        <p>All components are running smoothly</p>
                    <?php else: ?>
                        <h3><i class="fas fa-exclamation-circle me-2"></i>Some Systems Degraded</h3>
                        <p><?php echo $stats['total_components'] - $stats['healthy_components']; ?> component(s) require attention</p>
                    <?php endif; ?>
                    <div class="status-summary">
                        <div class="summary-item">
                            <div class="summary-number summary-number--success"><?php echo $stats['healthy_components']; ?></div>
                            <div class="summary-label">Operational</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-number"><?php echo $stats['total_components']; ?></div>
                            <div class="summary-label">Total Components</div>
                        </div>
                    </div>
                </div>
                
                <!-- Component Status Grid -->
                <h6 class="section-heading-muted">
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
                                <select name="status" class="filter-select filter-select--compact" onchange="this.form.submit()">
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
                    <h6 class="section-heading-muted">
                        <i class="fas fa-calendar-alt me-2"></i>Scheduled Maintenance
                    </h6>
                    <?php if (empty($maintenances)): ?>
                    <div class="empty-state empty-state--compact">
                        <i class="fas fa-calendar-check"></i>
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
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                <label class="maintenance-service-option">
                                    <input type="checkbox" name="services[]" value="<?php echo htmlspecialchars($comp['component']); ?>" class="form-check-input">
                                    <span><?php echo htmlspecialchars($comp['component']); ?></span>
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
