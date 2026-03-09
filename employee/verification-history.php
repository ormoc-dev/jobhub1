<?php
include '../config.php';
requireRole('employee');

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.id as employee_profile_id FROM employee_profiles ep WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$employee = $stmt->fetch();

if (!$employee) {
    $_SESSION['error'] = 'Employee profile not found.';
    redirect('profile.php');
}

// Get document verification history for all applications by this employee
$stmt = $pdo->prepare("SELECT dv.*, ja.id as application_id, jp.title as job_title, c.company_name
                       FROM document_verifications dv
                       JOIN job_applications ja ON dv.application_id = ja.id
                       JOIN employee_profiles ep ON ja.employee_id = ep.id
                       JOIN job_postings jp ON ja.job_id = jp.id
                       JOIN companies c ON jp.company_id = c.id
                       WHERE ep.id = ?
                       ORDER BY dv.verified_at DESC");
$stmt->execute([$employee['employee_profile_id']]);
$verification_history = $stmt->fetchAll();

// Count unread notifications (if notifications table exists)
$unread_count = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetch()['count'];
    
    // Mark notifications as read for this page
    if ($unread_count > 0) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = 'document_verification'")->execute([$_SESSION['user_id']]);
    }
} catch (Exception $e) {
    // Notifications table doesn't exist yet, skip notification handling
    // This is optional functionality
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Verification History - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employee-main-content">
        <div class="row">
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="d-flex flex-column">
                    <a href="../index.php" class="navbar-brand text-white p-3 text-center">
                        <img src="../worklink.jpg" alt="WORKLINK" class="logo-img mb-2" style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover;">
                        WORKLINK
                    </a>
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i>My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="jobs.php">
                                <i class="fas fa-search"></i>Browse All Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="applications.php">
                                <i class="fas fa-file-alt"></i>My Applications
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="verification-history.php">
                                <i class="fas fa-check-circle"></i>Verification History
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="saved-jobs.php">
                                <i class="fas fa-heart"></i>Saved Jobs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="messages.php">
                                <i class="fas fa-envelope"></i>Messages
                            </a>
                        </li>
                        <li class="nav-item mt-auto">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">
                        <i class="fas fa-check-circle me-2"></i>Document Verification History
                    </h1>
                    <a href="dashboard.php" class="btn" style="border-color: #10b981; color: #10b981;">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php if (empty($verification_history)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No document verifications yet. Your documents will appear here once employers verify them.
                    </div>
                <?php else: ?>
                    <div class="card dashboard-card">
                        <div class="card-header">
                            <h5 class="mb-0">Verification Records</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Document Type</th>
                                            <th>Job Title</th>
                                            <th>Company</th>
                                            <th>Status</th>
                                            <th>Verified At</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($verification_history as $verify): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $doc_type = trim(strtolower($verify['document_type'] ?? ''));
                                                    $doc_type_name = match($doc_type) {
                                                        'resume' => 'Resume',
                                                        'id_document' => 'ID Document',
                                                        'document1' => 'Document 1 (Resume/CV)',
                                                        'document2' => 'Document 2 (Other)',
                                                        'tor_document' => 'TOR Document',
                                                        'employment_certificate' => 'Employment Certificate',
                                                        'seminar_certificate' => 'Seminar Certificate',
                                                        default => ucfirst(str_replace('_', ' ', $verify['document_type'] ?? 'Unknown Document'))
                                                    };
                                                    $doc_icon = match($doc_type) {
                                                        'resume' => 'fa-file',
                                                        'id_document' => 'fa-id-card',
                                                        'tor_document' => 'fa-file-alt',
                                                        'employment_certificate', 'seminar_certificate' => 'fa-certificate',
                                                        default => 'fa-file'
                                                    };
                                                    echo '<i class="fas ' . $doc_icon . ' me-1"></i>' . htmlspecialchars($doc_type_name);
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($verify['job_title']); ?></td>
                                                <td><?php echo htmlspecialchars($verify['company_name']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $verify['verification_status'] === 'verified' ? 'bg-success' : 'bg-danger'; ?>">
                                                        <i class="fas fa-<?php echo $verify['verification_status'] === 'verified' ? 'check' : 'times'; ?> me-1"></i>
                                                        <?php echo ucfirst($verify['verification_status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($verify['verified_at'])); ?></td>
                                                <td>
                                                    <?php if (!empty($verify['verification_notes'])): ?>
                                                        <button class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($verify['verification_notes']); ?>">
                                                            <i class="fas fa-comment"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>

