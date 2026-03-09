<?php
include '../config.php';
requireRole('employer');

$message_alert = '';
$error_alert = '';

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    redirect('company-profile.php');
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = sanitizeInput($_POST['subject']);
    $message_content = sanitizeInput($_POST['message']);
    
    if (empty($subject) || empty($message_content) || !$receiver_id) {
        $error_alert = 'Please fill in all required fields.';
    } else {
        // Verify receiver exists and is an employee or admin
        $stmt = $pdo->prepare("SELECT u.id, u.role FROM users u WHERE u.id = ? AND u.role IN ('employee', 'admin') AND u.status = 'active'");
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

// Handle mark as read for messages
if (isset($_GET['mark_read']) && isset($_GET['msg_id'])) {
    $msg_id = (int)$_GET['msg_id'];
    $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE id = ? AND receiver_id = ?");
    $stmt->execute([$msg_id, $_SESSION['user_id']]);
    header('Location: messages.php');
    exit;
}

// Handle mark as read for notifications
if (isset($_GET['mark_notif_read']) && isset($_GET['notif_id'])) {
    $notif_id = (int)$_GET['notif_id'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        $stmt->execute([$notif_id, $_SESSION['user_id']]);
        header('Location: messages.php');
        exit;
    } catch(PDOException $e) {
        // Notifications table might not exist, ignore
    }
}

// Get applicant messages (from employees only)
$stmt = $pdo->prepare("SELECT m.*, u.username as sender_username, u.role as sender_role,
                              CONCAT(ep.first_name, ' ', ep.last_name) as sender_name
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                       WHERE m.receiver_id = ? AND u.role = 'employee'
                       ORDER BY m.sent_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$applicant_messages = $stmt->fetchAll();

// Get system announcements (from admin only)
$stmt = $pdo->prepare("SELECT m.*, u.username as sender_username, u.role as sender_role,
                              'WORKLINK Admin' as sender_name
                       FROM messages m
                       JOIN users u ON m.sender_id = u.id
                       WHERE m.receiver_id = ? AND u.role = 'admin'
                       ORDER BY m.sent_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$system_announcements = $stmt->fetchAll();

// Get notifications
$notifications = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
} catch(PDOException $e) {
    // Notifications table might not exist, use empty array
}

// Get message statistics
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_messages = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE");
$stmt->execute([$_SESSION['user_id']]);
$unread_messages = $stmt->fetchColumn();

// Get unread notifications count
$unread_notifications = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_notifications = $stmt->fetchColumn();
} catch(PDOException $e) {
    // Notifications table might not exist
}

$unread_count = $unread_messages + $unread_notifications;

// Get employees who have applied to this company's jobs
$stmt = $pdo->prepare("SELECT DISTINCT u.id, CONCAT(ep.first_name, ' ', ep.last_name) as employee_name, ep.employee_id
                       FROM users u 
                       JOIN employee_profiles ep ON u.id = ep.user_id 
                       JOIN job_applications ja ON ep.id = ja.employee_id
                       JOIN job_postings jp ON ja.job_id = jp.id
                       WHERE jp.company_id = ? AND u.status = 'active' 
                       ORDER BY employee_name");
$stmt->execute([$company['id']]);
$employees = $stmt->fetchAll();

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
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="fas fa-envelope me-2"></i>Messages
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger text-white"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <button class="btn" style="background: #10b981; border-color: #10b981; color: white;" data-bs-toggle="modal" data-bs-target="#composeModal">
                    <i class="fas fa-plus me-1"></i>Compose Message
                </button>
            </div>
        </div>

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

        <!-- Message Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                    <div class="card-body text-center">
                        <h4><?php echo $total_messages; ?></h4>
                        <small>Total Messages</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-dark bg-warning">
                    <div class="card-body text-center">
                        <h4><?php echo $unread_count; ?></h4>
                        <small>Unread Messages</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <span class="fw-semibold">Message Center includes:</span>
                    <ul class="mb-0 ps-4">
                        <li>Contact jobseekers</li>
                        <li>Interview invitations</li>
                        <li>System notifications</li>
                    </ul>
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
                                <button class="nav-link active" id="applicant-messages-tab" data-bs-toggle="tab" data-bs-target="#applicant-messages" type="button" role="tab">
                                    <i class="fas fa-users me-1"></i>Applicant Messages
                                    <?php 
                                    $applicant_unread = count(array_filter($applicant_messages, function($msg) { return !$msg['is_read']; }));
                                    if ($applicant_unread > 0): ?>
                                        <span class="badge bg-danger text-white"><?php echo $applicant_unread; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                    <i class="fas fa-bell me-1"></i>Notifications
                                    <?php if ($unread_notifications > 0): ?>
                                        <span class="badge bg-danger text-white"><?php echo $unread_notifications; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="system-announcements-tab" data-bs-toggle="tab" data-bs-target="#system-announcements" type="button" role="tab">
                                    <i class="fas fa-bullhorn me-1"></i>System Announcements
                                    <?php 
                                    $announcement_unread = count(array_filter($system_announcements, function($msg) { return !$msg['is_read']; }));
                                    if ($announcement_unread > 0): ?>
                                        <span class="badge bg-danger text-white"><?php echo $announcement_unread; ?></span>
                                    <?php endif; ?>
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="messagesTabContent">
                            <!-- Applicant Messages Tab -->
                            <div class="tab-pane fade show active" id="applicant-messages" role="tabpanel">
                                <?php if (empty($applicant_messages)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No applicant messages yet</h5>
                                        <p class="text-muted">Messages from job applicants will appear here</p>
                                        <button class="btn" style="background: #10b981; border-color: #10b981; color: white;" data-bs-toggle="modal" data-bs-target="#composeModal">
                                            <i class="fas fa-plus me-1"></i>Send your first message
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($applicant_messages as $msg): ?>
                                            <div class="list-group-item <?php echo !$msg['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!$msg['is_read']): ?>
                                                            <span class="badge me-2" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white;">New</span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-user me-1" style="color: #059669;"></i>
                                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                            </h6>
                                                            <p class="mb-1"><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></p>
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 100))); ?><?php echo strlen($msg['message']) > 100 ? '...' : ''; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo timeAgo($msg['sent_date']); ?></small>
                                                        <div class="mt-2">
                                                            <a href="view-message.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm" style="border-color: #10b981; color: #10b981;">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <?php if (!$msg['is_read']): ?>
                                                                <a href="?mark_read=1&msg_id=<?php echo $msg['id']; ?>" class="btn btn-sm" style="border-color: #059669; color: #059669;">
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

                            <!-- Notifications Tab -->
                            <div class="tab-pane fade" id="notifications" role="tabpanel">
                                <?php 
                                $total_notifications = count($notifications);
                                $read_notifications = count(array_filter($notifications, function($notif) { return $notif['is_read']; }));
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bell me-2" style="color: #10b981;"></i>
                                        Total Notifications: <strong><?php echo $total_notifications; ?></strong>
                                    </h6>
                                    <?php if ($total_notifications > 0): ?>
                                        <small class="text-muted">
                                            <?php echo $read_notifications; ?> read, <?php echo $unread_notifications; ?> unread
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <?php if (empty($notifications)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No notifications yet</h5>
                                        <p class="text-muted">System notifications will appear here</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($notifications as $notif): ?>
                                            <div class="list-group-item <?php echo !$notif['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!$notif['is_read']): ?>
                                                            <span class="badge me-2" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white;">New</span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-bell me-1" style="color: #10b981;"></i>
                                                                <span class="badge bg-info me-2"><?php echo htmlspecialchars(ucfirst($notif['type'])); ?></span>
                                                            </h6>
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo timeAgo($notif['created_at']); ?></small>
                                                        <div class="mt-2">
                                                            <?php if (!$notif['is_read']): ?>
                                                                <a href="?mark_notif_read=1&notif_id=<?php echo $notif['id']; ?>" class="btn btn-sm" style="border-color: #059669; color: #059669;">
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

                            <!-- System Announcements Tab -->
                            <div class="tab-pane fade" id="system-announcements" role="tabpanel">
                                <?php if (empty($system_announcements)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No system announcements</h5>
                                        <p class="text-muted">Important announcements from WORKLINK Admin will appear here</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($system_announcements as $msg): ?>
                                            <div class="list-group-item <?php echo !$msg['is_read'] ? 'list-group-item-warning' : ''; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!$msg['is_read']): ?>
                                                            <span class="badge me-2" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white;">New</span>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <i class="fas fa-shield-alt me-1" style="color: #ef4444;"></i>
                                                                <?php echo htmlspecialchars($msg['sender_name']); ?>
                                                            </h6>
                                                            <p class="mb-1"><strong><?php echo htmlspecialchars($msg['subject']); ?></strong></p>
                                                            <p class="mb-1"><?php echo nl2br(htmlspecialchars(substr($msg['message'], 0, 100))); ?><?php echo strlen($msg['message']) > 100 ? '...' : ''; ?></p>
                                                        </div>
                                                    </div>
                                                    <div class="text-end">
                                                        <small class="text-muted"><?php echo timeAgo($msg['sent_date']); ?></small>
                                                        <div class="mt-2">
                                                            <a href="view-message.php?id=<?php echo $msg['id']; ?>" class="btn btn-sm" style="border-color: #10b981; color: #10b981;">
                                                                <i class="fas fa-eye"></i> View
                                                            </a>
                                                            <?php if (!$msg['is_read']): ?>
                                                                <a href="?mark_read=1&msg_id=<?php echo $msg['id']; ?>" class="btn btn-sm" style="border-color: #059669; color: #059669;">
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
                                <optgroup label="Job Applicants">
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['employee_name']); ?> (<?php echo htmlspecialchars($employee['employee_id']); ?>)
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
                        <button type="submit" name="send_message" class="btn" style="background: #10b981; border-color: #10b981; color: white;">
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
