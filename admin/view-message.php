<?php
include '../config.php';
requireRole('admin');

// Get message ID and type from URL
$message_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'received'; // 'received' or 'sent'

if (!$message_id) {
    $_SESSION['error'] = 'Invalid message ID.';
    redirect('messages.php');
}

// Get message details based on type
if ($type === 'sent') {
    $stmt = $pdo->prepare("SELECT m.*, u.username as receiver_username, u.role as receiver_role,
                                  CASE 
                                      WHEN u.role = 'employee' THEN CONCAT(ep.first_name, ' ', ep.last_name)
                                      WHEN u.role = 'employer' THEN c.company_name
                                      ELSE u.username
                                  END as other_party_name
                           FROM messages m
                           JOIN users u ON m.receiver_id = u.id
                           LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                           LEFT JOIN companies c ON u.id = c.user_id
                           WHERE m.id = ? AND m.sender_id = ?");
    $stmt->execute([$message_id, $_SESSION['user_id']]);
    $message = $stmt->fetch();
    
    if ($message) {
        $message['other_party_id'] = $message['receiver_id'];
        $message['other_party_role'] = $message['receiver_role'];
    }
} else {
    $stmt = $pdo->prepare("SELECT m.*, u.username as sender_username, u.role as sender_role,
                                  CASE 
                                      WHEN u.role = 'employee' THEN CONCAT(ep.first_name, ' ', ep.last_name)
                                      WHEN u.role = 'employer' THEN c.company_name
                                      ELSE u.username
                                  END as other_party_name
                           FROM messages m
                           JOIN users u ON m.sender_id = u.id
                           LEFT JOIN employee_profiles ep ON u.id = ep.user_id
                           LEFT JOIN companies c ON u.id = c.user_id
                           WHERE m.id = ? AND m.receiver_id = ?");
    $stmt->execute([$message_id, $_SESSION['user_id']]);
    $message = $stmt->fetch();
    
    if ($message) {
        $message['other_party_id'] = $message['sender_id'];
        $message['other_party_role'] = $message['sender_role'];
    }
}

if (!$message) {
    $_SESSION['error'] = 'Message not found or you do not have permission to view it.';
    redirect('messages.php');
}

// Mark message as read if it's unread and received
if ($type === 'received' && !$message['is_read']) {
    $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE id = ?");
    $stmt->execute([$message_id]);
}

// Handle reply submission (only for received messages)
$reply_success = '';
$reply_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reply']) && $type === 'received') {
    $subject = sanitizeInput($_POST['subject']);
    $reply_content = sanitizeInput($_POST['message']);
    
    if (empty($subject) || empty($reply_content)) {
        $reply_error = 'Please fill in all required fields.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $message['other_party_id'], $subject, $reply_content]);
            $reply_success = 'Reply sent successfully!';
        } catch(PDOException $e) {
            $reply_error = 'Failed to send reply. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Message - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="fas fa-envelope-open me-2"></i>
                <?php echo $type === 'sent' ? 'Sent Message' : 'View Message'; ?>
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="messages.php" class="btn btn-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Messages
                </a>
                <?php if ($type === 'received'): ?>
                    <button class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#replyForm">
                        <i class="fas fa-reply me-1"></i>Reply
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($reply_success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $reply_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($reply_error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $reply_error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Message Details -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?php if ($type === 'sent'): ?>
                                    <i class="fas fa-paper-plane text-success me-1"></i>
                                    Sent to <?php echo htmlspecialchars($message['other_party_name']); ?>
                                <?php else: ?>
                                    <?php if ($message['other_party_role'] == 'employee'): ?>
                                        <i class="fas fa-user text-success me-1"></i>
                                    <?php elseif ($message['other_party_role'] == 'employer'): ?>
                                        <i class="fas fa-building text-primary me-1"></i>
                                    <?php endif; ?>
                                    From <?php echo htmlspecialchars($message['other_party_name']); ?>
                                <?php endif; ?>
                            </h5>
                            <span class="badge bg-<?php echo $message['other_party_role'] == 'employee' ? 'success' : 'primary'; ?>">
                                <?php echo ucfirst($message['other_party_role']); ?>
                            </span>
                        </div>
                        <div class="mt-2">
                            <strong><?php echo htmlspecialchars($message['subject']); ?></strong>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><?php echo $type === 'sent' ? 'To:' : 'From:'; ?></strong> <?php echo htmlspecialchars($message['other_party_name']); ?>
                                </div>
                                <div class="col-md-6 text-end">
                                    <strong><?php echo $type === 'sent' ? 'Sent:' : 'Date:'; ?></strong> <?php echo date('F j, Y g:i A', strtotime($message['sent_date'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                    </div>
                </div>

                <!-- Reply Form -->
                <?php if ($type === 'received'): ?>
                    <div class="collapse mt-4" id="replyForm">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-reply me-1"></i>Reply to <?php echo htmlspecialchars($message['other_party_name']); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject *</label>
                                        <input type="text" class="form-control" id="subject" name="subject" value="Re: <?php echo htmlspecialchars($message['subject']); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message *</label>
                                        <textarea class="form-control" id="message" name="message" rows="6" required></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-secondary me-2" data-bs-toggle="collapse" data-bs-target="#replyForm">
                                            Cancel
                                        </button>
                                        <button type="submit" name="send_reply" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>Send Reply
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Message Info</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Message ID:</strong><br>
                            <small class="text-white-50">#<?php echo $message['id']; ?></small>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong><br>
                            <?php if ($type === 'sent'): ?>
                                <span class="badge bg-info text-white">Sent</span>
                            <?php else: ?>
                                <span class="badge bg-success text-white">Read</span>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <strong><?php echo $type === 'sent' ? 'Recipient' : 'Sender'; ?> Type:</strong><br>
                            <span class="badge bg-<?php echo $message['other_party_role'] == 'employee' ? 'success' : 'primary'; ?>">
                                <?php echo ucfirst($message['other_party_role']); ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong><?php echo $type === 'sent' ? 'Sent:' : 'Received:'; ?></strong><br>
                            <small class="text-white-50"><?php echo timeAgo($message['sent_date']); ?></small>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <?php if ($type === 'received'): ?>
                                <button class="btn btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#replyForm">
                                    <i class="fas fa-reply me-1"></i>Reply
                                </button>
                            <?php endif; ?>
                            <a href="messages.php" class="btn btn-outline-secondary">
                                <i class="fas fa-list me-1"></i>All Messages
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">Admin Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($message['other_party_role'] == 'employee'): ?>
                                <a href="employees.php" class="btn btn-outline-info">
                                    <i class="fas fa-users me-1"></i>Manage Employees
                                </a>
                            <?php elseif ($message['other_party_role'] == 'employer'): ?>
                                <a href="companies.php" class="btn btn-outline-info">
                                    <i class="fas fa-building me-1"></i>Manage Companies
                                </a>
                            <?php endif; ?>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-user-cog me-1"></i>User Management
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
