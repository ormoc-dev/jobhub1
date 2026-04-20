<?php
include '../config.php';
requireRole('employee');

$message_alert = '';
$error_alert = '';

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.* FROM employee_profiles ep WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('profile.php');
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = sanitizeInput($_POST['subject']);
    $message_content = sanitizeInput($_POST['message']);
    
    if (empty($subject) || empty($message_content) || !$receiver_id) {
        $error_alert = 'Please fill in all required fields.';
    } else {
        // Verify receiver exists and is an employer or admin
        $stmt = $pdo->prepare("SELECT u.id, u.role FROM users u WHERE u.id = ? AND u.role IN ('employer', 'admin') AND u.status = 'active'");
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

// Get employer messages (received from employers)
$stmt = $pdo->prepare("SELECT m.*, u.username as sender_username, u.role as sender_role,
                              CASE 
                                  WHEN u.role = 'employer' THEN c.company_name
                                  WHEN u.role = 'admin' THEN 'WORKLINK Admin'
                                  ELSE u.username
                              END as sender_name
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       LEFT JOIN companies c ON u.id = c.user_id
                       WHERE m.receiver_id = ? AND u.role = 'employer'
                       ORDER BY m.sent_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$employer_messages = $stmt->fetchAll();

// Get interview notifications (messages with interview-related subjects)
$stmt = $pdo->prepare("SELECT m.*, u.username as sender_username, u.role as sender_role,
                              CASE 
                                  WHEN u.role = 'employer' THEN c.company_name
                                  WHEN u.role = 'admin' THEN 'WORKLINK Admin'
                                  ELSE u.username
                              END as sender_name
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       LEFT JOIN companies c ON u.id = c.user_id
                       WHERE m.receiver_id = ? 
                       AND (LOWER(m.subject) LIKE '%interview%' 
                            OR LOWER(m.subject) LIKE '%scheduled%' 
                            OR LOWER(m.subject) LIKE '%meeting%'
                            OR LOWER(m.message) LIKE '%interview%')
                       ORDER BY m.sent_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$interview_notifications = $stmt->fetchAll();

// Get system alerts (messages from admins)
$stmt = $pdo->prepare("SELECT m.*, u.username as sender_username, u.role as sender_role,
                              CASE 
                                  WHEN u.role = 'employer' THEN c.company_name
                                  WHEN u.role = 'admin' THEN 'WORKLINK Admin'
                                  ELSE u.username
                              END as sender_name
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       LEFT JOIN companies c ON u.id = c.user_id
                       WHERE m.receiver_id = ? AND u.role = 'admin'
                       ORDER BY m.sent_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$system_alerts = $stmt->fetchAll();

// Get message statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$total_messages = $stmt->fetchColumn();

// Get unread counts for each category
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages m 
                       JOIN users u ON m.sender_id = u.id 
                       WHERE m.receiver_id = ? AND m.is_read = FALSE AND u.role = 'employer'");
$stmt->execute([$_SESSION['user_id']]);
$unread_employer = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages m 
                       WHERE m.receiver_id = ? AND m.is_read = FALSE 
                       AND (LOWER(m.subject) LIKE '%interview%' 
                            OR LOWER(m.subject) LIKE '%scheduled%' 
                            OR LOWER(m.subject) LIKE '%meeting%'
                            OR LOWER(m.message) LIKE '%interview%')");
$stmt->execute([$_SESSION['user_id']]);
$unread_interview = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages m 
                       JOIN users u ON m.sender_id = u.id 
                       WHERE m.receiver_id = ? AND m.is_read = FALSE AND u.role = 'admin'");
$stmt->execute([$_SESSION['user_id']]);
$unread_system = $stmt->fetchColumn();

$unread_count = $unread_employer + $unread_interview + $unread_system;

// Get companies for messaging dropdown
$stmt = $pdo->prepare("SELECT u.id, c.company_name 
                       FROM users u 
                       JOIN companies c ON u.id = c.user_id 
                       WHERE u.role = 'employer' AND u.status = 'active' 
                       ORDER BY c.company_name");
$stmt->execute();
$companies = $stmt->fetchAll();

// Get admin for messaging
$stmt = $pdo->prepare("SELECT u.id, u.username FROM users u WHERE u.role = 'admin' AND u.status = 'active' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/modern.css" rel="stylesheet">
    <style>
        .message-card { border-left: 3px solid #e2e8f0; }
        .message-card.unread { border-left-color: #3b82f6; background: #f8fafc; }
    </style>
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employee-main-content">
        <div class="hero-section">
            <div class="hero-content">
                <div class="row align-items-center g-4">
                    <div class="col-lg">
                        <p class="hero-eyebrow mb-1">Inbox</p>
                        <h1 class="hero-title mb-2">
                            <i class="fas fa-envelope text-primary me-2 fs-5 align-middle"></i>Messages
                            <?php if ($unread_count > 0): ?>
                                <span class="hero-badge hero-badge--warning ms-2 align-middle"><?php echo (int) $unread_count; ?> unread</span>
                            <?php endif; ?>
                        </h1>
                        <p class="hero-lead mb-0">Chat with employers and get updates on applications, interviews, and offers.</p>
                    </div>
                    <div class="col-lg-auto">
                        <button type="button" class="btn btn-primary btn-lg px-4" data-bs-toggle="modal" data-bs-target="#composeModal">
                            <i class="fas fa-plus me-2"></i>Compose message
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-container">
        <!-- Alert Messages -->
        <?php if ($message_alert): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $message_alert; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_alert): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_alert; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="messages-summary mb-4">
            <p class="applications-kpi-label">Overview</p>
            <div class="stats-grid stats-grid--messages">
                <div class="stat-card stat-card--kpi primary">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?php echo (int) $total_messages; ?></div>
                            <div class="stat-card-label">Total messages</div>
                        </div>
                        <div class="stat-card-icon blue">
                            <i class="fas fa-inbox"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card stat-card--kpi warning">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-card-value"><?php echo (int) $unread_count; ?></div>
                            <div class="stat-card-label">Unread</div>
                        </div>
                        <div class="stat-card-icon orange">
                            <i class="fas fa-envelope-open-text"></i>
                        </div>
                    </div>
                </div>
                <div class="messages-tip-card">
                    <i class="fas fa-info-circle text-primary flex-shrink-0 mt-1"></i>
                    <p class="mb-0">Employer replies, interview notes, and system notices stay organized in the tabs below.</p>
                </div>
            </div>
        </div>

        <!-- Messages Interface -->
        <div class="row">
            <div class="col-md-12">
                <!-- Messages Tabs -->
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="messagesTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="employer-tab" data-bs-toggle="tab" data-bs-target="#employer" type="button" role="tab">
                                    <i class="fas fa-building me-1"></i>Employer Messages
                                    <?php if ($unread_employer > 0): ?>
                                        <span class="badge bg-danger text-white"><?php echo $unread_employer; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="interview-tab" data-bs-toggle="tab" data-bs-target="#interview" type="button" role="tab">
                                    <i class="fas fa-calendar-check me-1"></i>Interview Notifications
                                    <?php if ($unread_interview > 0): ?>
                                        <span class="badge bg-danger text-white"><?php echo $unread_interview; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                    <i class="fas fa-bell me-1"></i>System Alerts
                                    <?php if ($unread_system > 0): ?>
                                        <span class="badge bg-danger text-white"><?php echo $unread_system; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="messagesTabContent">
                            <!-- Employer Messages Tab -->
                            <div class="tab-pane fade show active" id="employer" role="tabpanel">
                                <?php if (empty($employer_messages)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No employer messages yet</h5>
                                        <p class="text-muted">Messages from employers will appear here</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeModal">
                                            <i class="fas fa-plus me-1"></i>Send your first message
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($employer_messages as $msg): ?>
                                            <div class="list-group-item <?php echo !$msg['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!$msg['is_read']): ?>
                                                            <span class="badge bg-primary me-2">New</span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-building me-1 text-primary"></i>
                                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                            </h6>
                                                            <p class="mb-1"><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></p>
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 100))); ?><?php echo strlen($msg['message']) > 100 ? '...' : ''; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo timeAgo($msg['sent_date']); ?></small>
                                                        <div class="mt-2">
                                                            <a href="view-message.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <?php if (!$msg['is_read']): ?>
                                                                <a href="?mark_read=1&msg_id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-success">
                                                                    <i class="fas fa-check"></i> Mark Read
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Interview Notifications Tab -->
                            <div class="tab-pane fade" id="interview" role="tabpanel">
                                <?php if (empty($interview_notifications)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-calendar-check fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No interview notifications</h5>
                                        <p class="text-muted">Interview-related messages will appear here</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($interview_notifications as $msg): ?>
                                            <div class="list-group-item <?php echo !$msg['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!$msg['is_read']): ?>
                                                            <span class="badge bg-primary me-2">New</span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-calendar-check me-1 text-warning"></i>
                                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                            </h6>
                                                            <p class="mb-1"><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></p>
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 100))); ?><?php echo strlen($msg['message']) > 100 ? '...' : ''; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo timeAgo($msg['sent_date']); ?></small>
                                                        <div class="mt-2">
                                                            <a href="view-message.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <?php if (!$msg['is_read']): ?>
                                                                <a href="?mark_read=1&msg_id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-success">
                                                                    <i class="fas fa-check"></i> Mark Read
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- System Alerts Tab -->
                            <div class="tab-pane fade" id="system" role="tabpanel">
                                <?php if (empty($system_alerts)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No system alerts</h5>
                                        <p class="text-muted">System notifications and alerts will appear here</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($system_alerts as $msg): ?>
                                            <div class="list-group-item <?php echo !$msg['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!$msg['is_read']): ?>
                                                            <span class="badge bg-primary me-2">New</span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-shield-alt me-1 text-danger"></i>
                                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                            </h6>
                                                            <p class="mb-1"><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></p>
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 100))); ?><?php echo strlen($msg['message']) > 100 ? '...' : ''; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo timeAgo($msg['sent_date']); ?></small>
                                                        <div class="mt-2">
                                                            <a href="view-message.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <?php if (!$msg['is_read']): ?>
                                                                <a href="?mark_read=1&msg_id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-outline-success">
                                                                    <i class="fas fa-check"></i> Mark Read
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
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
                                <optgroup label="Companies">
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>">
                                            <?php echo htmlspecialchars($company['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php if ($admin): ?>
                                    <optgroup label="Administration">
                                        <option value="<?php echo $admin['id']; ?>">
                                            WORKLINK Admin
                                        </option>
                                    </optgroup>
                                <?php endif; ?>
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
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
