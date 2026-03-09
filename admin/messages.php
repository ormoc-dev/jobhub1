<?php
include '../config.php';
requireRole('admin');

$message_alert = '';
$error_alert = '';

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = sanitizeInput($_POST['subject']);
    $message_content = sanitizeInput($_POST['message']);
    
    if (empty($subject) || empty($message_content) || !$receiver_id) {
        $error_alert = 'Please fill in all required fields.';
    } else {
        $stmt = $pdo->prepare("SELECT u.id, u.role FROM users u WHERE u.id = ? AND u.role IN ('employee', 'employer') AND u.status = 'active'");
        $stmt->execute([$receiver_id]);
        $receiver = $stmt->fetch();
        
        if ($receiver) {
            try {
                $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $receiver_id, $subject, $message_content]);
                $message_alert = 'Message sent successfully!';
            } catch(PDOException $e) {
                $error_alert = 'Failed to send message. Please try again.';
            }
        } else {
            $error_alert = 'Invalid recipient selected.';
        }
    }
}

// Handle mark as read
if (isset($_GET['mark_read']) && isset($_GET['msg_id'])) {
    $msg_id = (int)$_GET['msg_id'];
    $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$msg_id, $_SESSION['user_id']]);
}

// Get conversations (all messages for admin)
$stmt = $pdo->prepare("SELECT m.*, 
                              u_sender.username as sender_username, u_sender.role as sender_role,
                              u_receiver.username as receiver_username, u_receiver.role as receiver_role,
                              CASE 
                                  WHEN u_sender.role = 'employee' THEN CONCAT(ep.first_name, ' ', ep.last_name)
                                  WHEN u_sender.role = 'employer' THEN c.company_name
                                  ELSE u_sender.username
                              END as sender_name,
                              CASE 
                                  WHEN u_receiver.role = 'employee' THEN CONCAT(ep_r.first_name, ' ', ep_r.last_name)
                                  WHEN u_receiver.role = 'employer' THEN c_r.company_name
                                  ELSE u_receiver.username
                              END as receiver_name
                       FROM messages m
                       JOIN users u_sender ON m.sender_id = u_sender.id
                       JOIN users u_receiver ON m.receiver_id = u_receiver.id
                       LEFT JOIN employee_profiles ep ON u_sender.id = ep.user_id
                       LEFT JOIN companies c ON u_sender.id = c.user_id
                       LEFT JOIN employee_profiles ep_r ON u_receiver.id = ep_r.user_id
                       LEFT JOIN companies c_r ON u_receiver.id = c_r.user_id
                       ORDER BY m.sent_date DESC");
$stmt->execute();
$received_messages = $stmt->fetchAll();

// Get sent messages (admin's sent messages)
$stmt = $pdo->prepare("SELECT m.*, u.username as receiver_username, u.role as receiver_role,
                              CASE 
                                  WHEN u.role = 'employee' THEN CONCAT(ep.first_name, ' ', ep.last_name)
                                  WHEN u.role = 'employer' THEN c.company_name
                                  ELSE u.username
                              END as receiver_name
                       FROM messages m
                       JOIN users u ON m.receiver_id = u.id
                       LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                       LEFT JOIN companies c ON u.id = c.user_id
                       WHERE m.sender_id = ?
                       ORDER BY m.sent_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$sent_messages = $stmt->fetchAll();

// Get message statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages");
$stmt->execute();
$total_messages = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE is_read = FALSE");
$stmt->execute();
$unread_count = $stmt->fetchColumn();

// Get employees for messaging
$stmt = $pdo->prepare("SELECT u.id, CONCAT(ep.first_name, ' ', ep.last_name) as employee_name, ep.employee_id
                       FROM users u 
                       JOIN employee_profiles ep ON u.id = ep.user_id 
                       WHERE u.role = 'employee' AND u.status = 'active' 
                       ORDER BY employee_name");
$stmt->execute();
$employees = $stmt->fetchAll();

// Get employers for messaging
$stmt = $pdo->prepare("SELECT u.id, c.company_name 
                       FROM users u 
                       JOIN companies c ON u.id = c.user_id 
                       WHERE u.role = 'employer' AND u.status = 'active' 
                       ORDER BY c.company_name");
$stmt->execute();
$employers = $stmt->fetchAll();

// Get additional statistics
$employee_messages = $pdo->query("SELECT COUNT(*) FROM messages m JOIN users u ON m.sender_id = u.id WHERE u.role = 'employee'")->fetchColumn();
$employer_messages = $pdo->query("SELECT COUNT(*) FROM messages m JOIN users u ON m.sender_id = u.id WHERE u.role = 'employer'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Center - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
            --purple-light: #f3e8ff;
            --purple-main: #8b5cf6;
            --purple-dark: #6d28d9;
            --teal-light: #ccfbf1;
            --teal-main: #14b8a6;
            --rose-light: #ffe4e6;
            --rose-main: #f43f5e;
            --amber-light: #fef3c7;
            --amber-main: #f59e0b;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-600: #475569;
            --slate-800: #1e293b;
        }

        .page-header {
            background: var(--primary-gradient);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .page-header h1 {
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }

        .stat-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .stat-card .card-body {
            padding: 1.5rem;
        }

        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--slate-800);
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 0.875rem;
            color: var(--slate-600);
            margin-top: 0.5rem;
        }

        .stat-card.purple .stat-icon { background: var(--purple-light); color: var(--purple-main); }
        .stat-card.teal .stat-icon { background: var(--teal-light); color: var(--teal-main); }
        .stat-card.rose .stat-icon { background: var(--rose-light); color: var(--rose-main); }
        .stat-card.amber .stat-icon { background: var(--amber-light); color: var(--amber-main); }

        /* Navigation Tabs */
        .nav-section {
            background: white;
            border-radius: 20px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .nav-section .nav-pills {
            gap: 0.5rem;
        }

        .nav-section .nav-link {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            color: var(--slate-600);
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .nav-section .nav-link:hover {
            background: var(--slate-100);
        }

        .nav-section .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .nav-section .nav-link .nav-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .nav-section .nav-link:not(.active) .nav-icon {
            background: var(--slate-100);
        }

        .nav-section .nav-link.active .nav-icon {
            background: rgba(255,255,255,0.2);
        }

        .nav-badge {
            background: #f43f5e;
            color: white;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }

        .nav-section .nav-link.active .nav-badge {
            background: white;
            color: var(--purple-main);
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            border: none;
        }

        .content-card .card-header {
            background: white;
            border-bottom: 1px solid var(--slate-200);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .content-card .card-body {
            padding: 1.5rem;
        }

        /* Message List */
        .message-item {
            display: flex;
            align-items: flex-start;
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 0.75rem;
            background: var(--slate-50);
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }

        .message-item:hover {
            background: white;
            border-color: var(--slate-200);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .message-item.unread {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-left: 4px solid var(--purple-main);
        }

        .message-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .message-avatar.employee { background: var(--success-gradient); }
        .message-avatar.employer { background: var(--info-gradient); }
        .message-avatar.admin { background: var(--primary-gradient); }

        .message-content { flex: 1; min-width: 0; }
        .message-content h6 { font-weight: 600; color: var(--slate-800); margin-bottom: 0.25rem; }
        .message-content .message-subject { font-weight: 500; color: var(--slate-800); margin-bottom: 0.25rem; }
        .message-content .message-preview { color: var(--slate-600); font-size: 0.875rem; margin-bottom: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .message-meta {
            text-align: right;
            flex-shrink: 0;
            margin-left: 1rem;
        }

        .message-meta .time { font-size: 0.75rem; color: var(--slate-600); margin-bottom: 0.5rem; }
        .message-meta .badge-new { background: var(--primary-gradient); color: white; font-size: 0.65rem; padding: 0.25rem 0.5rem; border-radius: 20px; }

        /* Notification Templates */
        .notification-template {
            background: var(--slate-50);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--slate-200);
            transition: all 0.3s ease;
        }

        .notification-template:hover {
            background: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .notification-template .template-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .notification-template.email .template-icon { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706; }
        .notification-template.sms .template-icon { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb; }
        .notification-template.push .template-icon { background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #16a34a; }

        /* Support Ticket Styles */
        .ticket-item {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 0.75rem;
            border: 1px solid var(--slate-200);
            transition: all 0.3s ease;
        }

        .ticket-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .ticket-status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .ticket-status.open { background: #dcfce7; color: #16a34a; }
        .ticket-status.pending { background: #fef3c7; color: #d97706; }
        .ticket-status.resolved { background: #dbeafe; color: #2563eb; }
        .ticket-status.closed { background: var(--slate-200); color: var(--slate-600); }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .priority-badge.high { background: #fee2e2; color: #dc2626; }
        .priority-badge.medium { background: #fef3c7; color: #d97706; }
        .priority-badge.low { background: #dbeafe; color: #2563eb; }

        /* Action Buttons */
        .btn-action {
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }

        .btn-action.btn-primary {
            background: var(--primary-gradient);
            border: none;
            color: white;
        }

        .btn-action.btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-action.btn-outline {
            background: white;
            border: 1px solid var(--slate-200);
            color: var(--slate-600);
        }

        .btn-action.btn-outline:hover {
            background: var(--slate-100);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-state .empty-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--slate-100);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: var(--slate-400);
        }

        .empty-state h5 { color: var(--slate-800); margin-bottom: 0.5rem; }
        .empty-state p { color: var(--slate-600); margin-bottom: 1.5rem; }

        /* Modal Styling */
        .modal-content {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 1.5rem;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body { padding: 1.5rem; }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--slate-200);
            padding: 0.75rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--purple-main);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .form-label {
            font-weight: 500;
            color: var(--slate-800);
            margin-bottom: 0.5rem;
        }

        /* Alert Styling */
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
        }

        .alert-custom.alert-success { background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%); color: #059669; }
        .alert-custom.alert-danger { background: linear-gradient(135deg, rgba(240, 147, 251, 0.1) 0%, rgba(245, 87, 108, 0.1) 100%); color: #dc2626; }

        /* Quick Stats Row */
        .quick-stat {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--slate-50);
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .quick-stat .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .quick-stat .stat-info h6 { margin: 0; font-weight: 600; color: var(--slate-800); }
        .quick-stat .stat-info small { color: var(--slate-600); }

        @media (max-width: 768px) {
            .nav-section .nav-link span.nav-text { display: none; }
            .nav-section .nav-link { padding: 0.75rem 1rem; }
        }
    </style>
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1><i class="fas fa-satellite-dish me-2"></i>Communication Center</h1>
                    <p>Manage system messages, notifications, and support tickets</p>
                </div>
                <button class="btn btn-light btn-lg" style="border-radius: 12px; font-weight: 600;" data-bs-toggle="modal" data-bs-target="#composeModal">
                    <i class="fas fa-plus me-2"></i>Compose
                </button>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message_alert): ?>
            <div class="alert alert-custom alert-success alert-dismissible fade show mb-4">
                <i class="fas fa-check-circle me-2"></i><?php echo $message_alert; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_alert): ?>
            <div class="alert alert-custom alert-danger alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_alert; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card purple">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_messages; ?></div>
                        <div class="stat-label">Total Messages</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card rose">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                        <div class="stat-value"><?php echo $unread_count; ?></div>
                        <div class="stat-label">Unread Messages</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card teal">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="stat-value"><?php echo $employee_messages; ?></div>
                        <div class="stat-label">From Employees</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card amber">
                    <div class="card-body">
                        <div class="stat-icon">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="stat-value"><?php echo $employer_messages; ?></div>
                        <div class="stat-label">From Employers</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="nav-section">
            <ul class="nav nav-pills" id="mainTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="messages-tab" data-bs-toggle="tab" data-bs-target="#messages" type="button">
                        <span class="nav-icon"><i class="fas fa-comments"></i></span>
                        <span class="nav-text">System Messages</span>
                        <?php if ($unread_count > 0): ?>
                            <span class="nav-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button">
                        <span class="nav-icon"><i class="fas fa-bell"></i></span>
                        <span class="nav-text">Email / SMS Notifications</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tickets-tab" data-bs-toggle="tab" data-bs-target="#tickets" type="button">
                        <span class="nav-icon"><i class="fas fa-headset"></i></span>
                        <span class="nav-text">Support Tickets</span>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="mainTabContent">
            <!-- System Messages Tab -->
            <div class="tab-pane fade show active" id="messages" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card content-card">
                            <div class="card-header">
                                <span><i class="fas fa-inbox me-2"></i>All Messages</span>
                                <div class="dropdown">
                                    <button class="btn btn-action btn-outline btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#">All Messages</a></li>
                                        <li><a class="dropdown-item" href="#">Unread Only</a></li>
                                        <li><a class="dropdown-item" href="#">From Employees</a></li>
                                        <li><a class="dropdown-item" href="#">From Employers</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($received_messages)): ?>
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <h5>No messages yet</h5>
                                        <p>Messages from employees and employers will appear here</p>
                                        <button class="btn btn-action btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                                            <i class="fas fa-plus me-1"></i>Send your first message
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($received_messages as $msg): ?>
                                        <div class="message-item <?php echo !$msg['is_read'] ? 'unread' : ''; ?>">
                                            <div class="message-avatar <?php echo $msg['sender_role']; ?>">
                                                <?php echo strtoupper(substr($msg['sender_name'], 0, 2)); ?>
                                            </div>
                                            <div class="message-content">
                                                <h6>
                                                    <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                    <span class="badge bg-secondary ms-2" style="font-size: 0.65rem;"><?php echo ucfirst($msg['sender_role']); ?></span>
                                                    <small class="text-muted ms-2"><i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($msg['receiver_name']); ?></small>
                                                </h6>
                                                <div class="message-subject"><?php echo htmlspecialchars($msg['subject']); ?></div>
                                                <p class="message-preview"><?php echo htmlspecialchars(substr($msg['message'], 0, 100)); ?><?php echo strlen($msg['message']) > 100 ? '...' : ''; ?></p>
                                            </div>
                                            <div class="message-meta">
                                                <div class="time"><?php echo timeAgo($msg['sent_date']); ?></div>
                                                <?php if (!$msg['is_read']): ?>
                                                    <span class="badge-new">NEW</span>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <a href="view-message.php?id=<?php echo $msg['id']; ?>" class="btn btn-action btn-outline btn-sm">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <!-- Sent Messages -->
                        <div class="card content-card mb-4">
                            <div class="card-header">
                                <span><i class="fas fa-paper-plane me-2"></i>My Sent Messages</span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($sent_messages)): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-paper-plane fa-2x text-muted mb-2"></i>
                                        <p class="text-muted small mb-0">No sent messages</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach (array_slice($sent_messages, 0, 5) as $msg): ?>
                                        <div class="quick-stat">
                                            <div class="stat-icon" style="background: var(--purple-light); color: var(--purple-main);">
                                                <i class="fas fa-arrow-right"></i>
                                            </div>
                                            <div class="stat-info">
                                                <h6><?php echo htmlspecialchars($msg['receiver_name']); ?></h6>
                                                <small><?php echo htmlspecialchars(substr($msg['subject'], 0, 30)); ?><?php echo strlen($msg['subject']) > 30 ? '...' : ''; ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card content-card">
                            <div class="card-header">
                                <span><i class="fas fa-bolt me-2"></i>Quick Actions</span>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-action btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                                        <i class="fas fa-pen me-2"></i>New Message
                                    </button>
                                    <a href="users.php" class="btn btn-action btn-outline">
                                        <i class="fas fa-users me-2"></i>Manage Users
                                    </a>
                                    <a href="companies.php" class="btn btn-action btn-outline">
                                        <i class="fas fa-building me-2"></i>Manage Companies
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email / SMS Notifications Tab -->
            <div class="tab-pane fade" id="notifications" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card content-card">
                            <div class="card-header">
                                <span><i class="fas fa-paper-plane me-2"></i>Send Notifications</span>
                            </div>
                            <div class="card-body">
                                <form>
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Notification Type</label>
                                            <select class="form-select">
                                                <option value="email">Email Notification</option>
                                                <option value="sms">SMS Notification</option>
                                                <option value="both">Both Email & SMS</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Recipients</label>
                                            <select class="form-select">
                                                <option value="all">All Users</option>
                                                <option value="employees">All Employees</option>
                                                <option value="employers">All Employers</option>
                                                <option value="specific">Specific User</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Subject / Title</label>
                                        <input type="text" class="form-control" placeholder="Enter notification subject">
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label">Message Content</label>
                                        <textarea class="form-control" rows="5" placeholder="Enter your notification message..."></textarea>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col-md-6">
                                            <label class="form-label">Schedule</label>
                                            <select class="form-select">
                                                <option value="now">Send Immediately</option>
                                                <option value="schedule">Schedule for Later</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Priority</label>
                                            <select class="form-select">
                                                <option value="normal">Normal</option>
                                                <option value="high">High Priority</option>
                                                <option value="urgent">Urgent</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-action btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>Send Notification
                                        </button>
                                        <button type="button" class="btn btn-action btn-outline">
                                            <i class="fas fa-eye me-2"></i>Preview
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <!-- Notification Templates -->
                        <div class="card content-card mb-4">
                            <div class="card-header">
                                <span><i class="fas fa-file-alt me-2"></i>Templates</span>
                            </div>
                            <div class="card-body">
                                <div class="notification-template email">
                                    <div class="template-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <h6>Welcome Email</h6>
                                    <p class="small text-muted mb-2">New user onboarding email template</p>
                                    <button class="btn btn-action btn-outline btn-sm">Use Template</button>
                                </div>
                                <div class="notification-template sms">
                                    <div class="template-icon">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <h6>Application Update</h6>
                                    <p class="small text-muted mb-2">Job application status SMS</p>
                                    <button class="btn btn-action btn-outline btn-sm">Use Template</button>
                                </div>
                                <div class="notification-template push">
                                    <div class="template-icon">
                                        <i class="fas fa-bell"></i>
                                    </div>
                                    <h6>System Announcement</h6>
                                    <p class="small text-muted mb-2">Platform-wide announcement</p>
                                    <button class="btn btn-action btn-outline btn-sm">Use Template</button>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Notifications -->
                        <div class="card content-card">
                            <div class="card-header">
                                <span><i class="fas fa-history me-2"></i>Recent Sent</span>
                            </div>
                            <div class="card-body">
                                <div class="quick-stat">
                                    <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>Welcome Email Batch</h6>
                                        <small>Sent to 45 users - 2 hours ago</small>
                                    </div>
                                </div>
                                <div class="quick-stat">
                                    <div class="stat-icon" style="background: #dbeafe; color: #2563eb;">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>Interview Reminders</h6>
                                        <small>Sent to 12 users - 5 hours ago</small>
                                    </div>
                                </div>
                                <div class="quick-stat">
                                    <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
                                        <i class="fas fa-bullhorn"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>System Maintenance</h6>
                                        <small>Sent to all - Yesterday</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Support Tickets Tab -->
            <div class="tab-pane fade" id="tickets" role="tabpanel">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card content-card">
                            <div class="card-header">
                                <span><i class="fas fa-ticket-alt me-2"></i>Support Tickets</span>
                                <div class="d-flex gap-2">
                                    <div class="dropdown">
                                        <button class="btn btn-action btn-outline btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-filter me-1"></i>Status
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#">All Tickets</a></li>
                                            <li><a class="dropdown-item" href="#">Open</a></li>
                                            <li><a class="dropdown-item" href="#">Pending</a></li>
                                            <li><a class="dropdown-item" href="#">Resolved</a></li>
                                            <li><a class="dropdown-item" href="#">Closed</a></li>
                                        </ul>
                                    </div>
                                    <button class="btn btn-action btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newTicketModal">
                                        <i class="fas fa-plus me-1"></i>New Ticket
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Sample Tickets -->
                                <div class="ticket-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">#TKT-001 - Unable to upload resume</h6>
                                            <small class="text-muted">Submitted by John Doe - 2 hours ago</small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <span class="priority-badge high"><i class="fas fa-flag"></i> High</span>
                                            <span class="ticket-status open"><i class="fas fa-circle"></i> Open</span>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-2">I'm trying to upload my resume but the system keeps showing an error message...</p>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-action btn-outline btn-sm"><i class="fas fa-eye me-1"></i>View</button>
                                        <button class="btn btn-action btn-outline btn-sm"><i class="fas fa-reply me-1"></i>Reply</button>
                                    </div>
                                </div>

                                <div class="ticket-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">#TKT-002 - Account verification pending</h6>
                                            <small class="text-muted">Submitted by ABC Company - 5 hours ago</small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <span class="priority-badge medium"><i class="fas fa-flag"></i> Medium</span>
                                            <span class="ticket-status pending"><i class="fas fa-clock"></i> Pending</span>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-2">Our company registration has been pending for 3 days. Please expedite the verification...</p>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-action btn-outline btn-sm"><i class="fas fa-eye me-1"></i>View</button>
                                        <button class="btn btn-action btn-outline btn-sm"><i class="fas fa-reply me-1"></i>Reply</button>
                                    </div>
                                </div>

                                <div class="ticket-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1">#TKT-003 - Password reset not working</h6>
                                            <small class="text-muted">Submitted by Jane Smith - 1 day ago</small>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <span class="priority-badge low"><i class="fas fa-flag"></i> Low</span>
                                            <span class="ticket-status resolved"><i class="fas fa-check"></i> Resolved</span>
                                        </div>
                                    </div>
                                    <p class="text-muted small mb-2">The password reset email is not arriving in my inbox...</p>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-action btn-outline btn-sm"><i class="fas fa-eye me-1"></i>View</button>
                                        <button class="btn btn-action btn-outline btn-sm"><i class="fas fa-times me-1"></i>Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <!-- Ticket Statistics -->
                        <div class="card content-card mb-4">
                            <div class="card-header">
                                <span><i class="fas fa-chart-pie me-2"></i>Ticket Overview</span>
                            </div>
                            <div class="card-body">
                                <div class="quick-stat">
                                    <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
                                        <i class="fas fa-folder-open"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>12 Open</h6>
                                        <small>Tickets awaiting response</small>
                                    </div>
                                </div>
                                <div class="quick-stat">
                                    <div class="stat-icon" style="background: #fef3c7; color: #d97706;">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>8 Pending</h6>
                                        <small>Waiting for user reply</small>
                                    </div>
                                </div>
                                <div class="quick-stat">
                                    <div class="stat-icon" style="background: #dbeafe; color: #2563eb;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>156 Resolved</h6>
                                        <small>This month</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Response Time -->
                        <div class="card content-card">
                            <div class="card-header">
                                <span><i class="fas fa-tachometer-alt me-2"></i>Performance</span>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h2 class="mb-0" style="color: var(--purple-main);">2.5h</h2>
                                    <small class="text-muted">Avg. Response Time</small>
                                </div>
                                <div class="progress mb-3" style="height: 8px; border-radius: 4px;">
                                    <div class="progress-bar" role="progressbar" style="width: 85%; background: var(--primary-gradient);" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span>Resolution Rate</span>
                                    <span>85%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Compose Message Modal -->
    <div class="modal fade" id="composeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-pencil-alt me-2"></i>Compose Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="receiver_id" class="form-label">To *</label>
                            <select class="form-select" id="receiver_id" name="receiver_id" required>
                                <option value="">Select recipient...</option>
                                <optgroup label="Employees">
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['employee_name']); ?> (<?php echo htmlspecialchars($employee['employee_id']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Employers">
                                    <?php foreach ($employers as $employer): ?>
                                        <option value="<?php echo $employer['id']; ?>">
                                            <?php echo htmlspecialchars($employer['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject *</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message *</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-action btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-action btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Ticket Modal -->
    <div class="modal fade" id="newTicketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-ticket-alt me-2"></i>Create Support Ticket
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select">
                                <option value="">Select category...</option>
                                <option value="account">Account Issues</option>
                                <option value="technical">Technical Support</option>
                                <option value="billing">Billing & Payments</option>
                                <option value="general">General Inquiry</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select class="form-select">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" class="form-control" placeholder="Brief description of the issue">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description *</label>
                        <textarea class="form-control" rows="5" placeholder="Detailed description of the issue..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attachments</label>
                        <input type="file" class="form-control" multiple>
                        <small class="text-muted">Max 5 files, 10MB each</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-action btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-action btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
