<?php
include '../config.php';
requireRole('employer');

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    $_SESSION['error'] = 'Invalid job ID.';
    redirect('jobs.php');
}

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company profile not found.';
    redirect('company-profile.php');
}

// Get job details
$stmt = $pdo->prepare("SELECT jp.* FROM job_postings jp WHERE jp.id = ? AND jp.company_id = ?");
$stmt->execute([$job_id, $company['id']]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['error'] = 'Job not found or you do not have permission to manage it.';
    redirect('jobs.php');
}

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'toggle_status') {
        $new_status = sanitizeInput($_POST['new_status']);
        $allowed_statuses = ['active', 'expired', 'pending'];
        
        if (in_array($new_status, $allowed_statuses)) {
            try {
                $stmt = $pdo->prepare("UPDATE job_postings SET status = ?, updated_at = NOW() WHERE id = ? AND company_id = ?");
                $success = $stmt->execute([$new_status, $job_id, $company['id']]);
                
                if ($success) {
                    $_SESSION['message'] = "Job status updated to " . ucfirst($new_status) . " successfully!";
                    redirect('jobs.php');
                } else {
                    $error = 'Failed to update job status.';
                }
            } catch(PDOException $e) {
                $error = 'Database error occurred.';
            }
        } else {
            $error = 'Invalid status.';
        }
    } elseif ($action === 'delete') {
        // Check if job has applications
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE job_id = ?");
        $stmt->execute([$job_id]);
        $application_count = $stmt->fetchColumn();
        
        try {
            $pdo->beginTransaction();
            
            if ($application_count > 0) {
                // Delete all applications for this job
                $stmt = $pdo->prepare("DELETE FROM job_applications WHERE job_id = ?");
                $stmt->execute([$job_id]);
                
                // Delete from saved jobs
                $stmt = $pdo->prepare("DELETE FROM saved_jobs WHERE job_id = ?");
                $stmt->execute([$job_id]);
            }
            
            // Delete the job posting
            $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id = ? AND company_id = ?");
            $success = $stmt->execute([$job_id, $company['id']]);
            
            if ($success) {
                $pdo->commit();
                $_SESSION['message'] = "Job '{$job['title']}' deleted successfully!";
                if ($application_count > 0) {
                    $_SESSION['message'] .= " ({$application_count} applications were also removed)";
                }
                redirect('jobs.php');
            } else {
                $pdo->rollback();
                $error = 'Failed to delete job.';
            }
            
        } catch(PDOException $e) {
            $pdo->rollback();
            $error = 'Database error occurred.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Job - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-cogs me-2"></i>Manage Job</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="jobs.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Jobs
                </a>
                <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-info">
                    <i class="fas fa-eye me-1"></i>View Details
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-briefcase me-2"></i><?php echo htmlspecialchars($job['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                                <p><strong>Employment Type:</strong> <span class="badge bg-secondary"><?php echo htmlspecialchars($job['employment_type']); ?></span></p>
                                <p><strong>Experience Level:</strong> <?php echo htmlspecialchars($job['experience_level']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Posted:</strong> <?php echo date('M j, Y g:i A', strtotime($job['posted_date'])); ?></p>
                                <p><strong>Status:</strong> 
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    switch($job['status']) {
                                        case 'active':
                                            $statusClass = 'bg-success text-white';
                                            $statusIcon = 'check';
                                            break;
                                        case 'pending':
                                            $statusClass = 'bg-warning text-dark';
                                            $statusIcon = 'clock';
                                            break;
                                        case 'expired':
                                            $statusClass = 'bg-danger text-white';
                                            $statusIcon = 'times';
                                            break;
                                        case 'rejected':
                                            $statusClass = 'bg-dark';
                                            $statusIcon = 'ban';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo ucfirst($job['status']); ?>
                                    </span>
                                </p>
                                <?php if ($job['deadline']): ?>
                                    <p><strong>Deadline:</strong> <?php echo date('M j, Y', strtotime($job['deadline'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Job Actions</h6>
                    </div>
                    <div class="card-body">
                        <!-- Status Management -->
                        <div class="mb-4">
                            <h6 class="mb-3">Status Management</h6>
                            <?php if ($job['status'] === 'active'): ?>
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="new_status" value="expired">
                                    <button type="submit" class="btn btn-outline-warning w-100" 
                                            onclick="return confirm('Are you sure you want to mark this job as expired?')">
                                        <i class="fas fa-pause me-1"></i>Mark as Expired
                                    </button>
                                </form>
                            <?php elseif ($job['status'] === 'expired'): ?>
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="new_status" value="active">
                                    <button type="submit" class="btn btn-outline-success w-100" 
                                            onclick="return confirm('Are you sure you want to reactivate this job?')">
                                        <i class="fas fa-play me-1"></i>Reactivate Job
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mb-4">
                            <h6 class="mb-3">Quick Actions</h6>
                            <div class="d-grid gap-2">
                                <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-info">
                                    <i class="fas fa-eye me-1"></i>View Full Details
                                </a>
                                
                                <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-edit me-1"></i>Edit Job
                                </a>

                                <?php
                                // Check if job has applications
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE job_id = ?");
                                $stmt->execute([$job_id]);
                                $application_count = $stmt->fetchColumn();
                                ?>

                                <?php if ($application_count > 0): ?>
                                    <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-users me-1"></i>View Applications (<?php echo $application_count; ?>)
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="mb-4">
                            <h6 class="mb-3 text-danger">Danger Zone</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-outline-danger w-100" 
                                        onclick="return confirm('Are you sure you want to delete this job? This action cannot be undone and will remove all applications.')">
                                    <i class="fas fa-trash me-1"></i>Delete Job
                                </button>
                            </form>
                            <?php if ($application_count > 0): ?>
                                <small class="text-muted">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    This will also delete <?php echo $application_count; ?> application(s).
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
