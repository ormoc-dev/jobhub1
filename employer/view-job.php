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

// Get job details with application count
$stmt = $pdo->prepare("SELECT jp.*, jc.category_name,
                       (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
                       (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND status = 'pending') as pending_applications
                       FROM job_postings jp
                       LEFT JOIN job_categories jc ON jp.category_id = jc.id
                       WHERE jp.id = ? AND jp.company_id = ?");
$stmt->execute([$job_id, $company['id']]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['error'] = 'Job not found or you do not have permission to view it.';
    redirect('jobs.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Job Details - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-eye me-2"></i>Job Details</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="jobs.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Jobs
                </a>
                <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>Edit Job
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Job Information -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-briefcase me-2"></i><?php echo htmlspecialchars($job['title']); ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Category:</strong></td>
                                        <td>
                                            <?php if ($job['category_name']): ?>
                                                <span class="badge bg-primary text-white"><?php echo htmlspecialchars($job['category_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No category</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Location:</strong></td>
                                        <td><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($job['location']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Employment Type:</strong></td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($job['employment_type']); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Experience Level:</strong></td>
                                        <td><?php echo htmlspecialchars($job['experience_level']); ?></td>
                                    </tr>
                                    <?php if ($job['salary_range']): ?>
                                    <tr>
                                        <td><strong>Salary Range:</strong></td>
                                        <td><span class="text-success"><?php echo htmlspecialchars($job['salary_range']); ?></span></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-chart-bar me-2"></i>Statistics & Status</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
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
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Applications:</strong></td>
                                        <td>
                                            <span class="badge bg-info text-white"><?php echo $job['application_count']; ?> total</span>
                                            <?php if ($job['pending_applications'] > 0): ?>
                                                <br><span class="badge bg-warning text-dark"><?php echo $job['pending_applications']; ?> pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Posted Date:</strong></td>
                                        <td>
                                            <?php echo date('M j, Y g:i A', strtotime($job['posted_date'])); ?>
                                            <br><small class="text-muted"><?php echo timeAgo($job['posted_date']); ?></small>
                                        </td>
                                    </tr>
                                    <?php if ($job['deadline']): ?>
                                    <tr>
                                        <td><strong>Deadline:</strong></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($job['deadline'])); ?>
                                            <?php 
                                            $deadline_date = new DateTime($job['deadline']);
                                            $current_date = new DateTime();
                                            $days_left = $current_date->diff($deadline_date)->days;
                                            $is_past = $current_date > $deadline_date;
                                            ?>
                                            <br><small class="<?php echo $is_past ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo $is_past ? 'Expired' : $days_left . ' days left'; ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>

                        <hr>

                        <div class="mb-4">
                            <h6><i class="fas fa-file-text me-2"></i>Job Description</h6>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                            </div>
                        </div>

                        <?php if ($job['requirements']): ?>
                        <div class="mb-4">
                            <h6><i class="fas fa-list-check me-2"></i>Requirements</h6>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($job['qualification'])): ?>
                        <div class="mb-4">
                            <h6><i class="fas fa-certificate me-2"></i>Qualifications</h6>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(html_entity_decode($job['qualification'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Actions & Quick Stats -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit me-1"></i>Edit Job
                            </a>
                            
                            <?php if ($job['application_count'] > 0): ?>
                                <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-users me-1"></i>View Applications (<?php echo $job['application_count']; ?>)
                                </a>
                            <?php endif; ?>

                            <?php if ($job['status'] === 'active'): ?>
                                <form method="POST" action="toggle-job-status.php">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <input type="hidden" name="new_status" value="expired">
                                    <button type="submit" class="btn btn-outline-warning w-100" 
                                            onclick="return confirm('Are you sure you want to mark this job as expired?')">
                                        <i class="fas fa-pause me-1"></i>Mark as Expired
                                    </button>
                                </form>
                            <?php elseif ($job['status'] === 'expired'): ?>
                                <form method="POST" action="toggle-job-status.php">
                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                    <input type="hidden" name="new_status" value="active">
                                    <button type="submit" class="btn btn-outline-success w-100" 
                                            onclick="return confirm('Are you sure you want to reactivate this job?')">
                                        <i class="fas fa-play me-1"></i>Reactivate Job
                                    </button>
                                </form>
                            <?php endif; ?>

                            <hr>

                            <form method="POST" action="delete-job.php">
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger w-100" 
                                        onclick="return confirm('Are you sure you want to delete this job? This action cannot be undone and will remove all applications.')">
                                    <i class="fas fa-trash me-1"></i>Delete Job
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Application Summary -->
                <?php if ($job['application_count'] > 0): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Application Summary</h6>
                    </div>
                    <div class="card-body">
                        <?php
                        // Get application statistics
                        $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM job_applications WHERE job_id = ? GROUP BY status");
                        $stmt->execute([$job_id]);
                        $app_stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        ?>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="text-warning"><?php echo $app_stats['pending'] ?? 0; ?></h5>
                                <small>Pending</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-info"><?php echo $app_stats['reviewed'] ?? 0; ?></h5>
                                <small>Reviewed</small>
                            </div>
                        </div>
                        <div class="row text-center mt-2">
                            <div class="col-6">
                                <h5 class="text-success"><?php echo $app_stats['accepted'] ?? 0; ?></h5>
                                <small>Accepted</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-danger"><?php echo $app_stats['rejected'] ?? 0; ?></h5>
                                <small>Rejected</small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
