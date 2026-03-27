<?php
include '../config.php';
requireRole('employer');

// Clean up expired jobs with no applications
cleanupExpiredJobs();

$message = '';
$error = '';

// Check for session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get company profile
$stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    redirect('company-profile.php');
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

$allowed_statuses = ['active', 'pending', 'expired', 'rejected'];
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'pending';
}

// Build query - for active jobs, exclude expired ones (deadline passed)
$whereClause = "WHERE jp.company_id = ?";
$params = [$company['id']];

// If filtering for active jobs, exclude expired ones
if ($status_filter === 'active') {
    $whereClause .= " AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())";
}

if ($status_filter !== 'all') {
    $whereClause .= " AND jp.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $whereClause .= " AND (jp.title LIKE ? OR jp.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Get jobs with application counts
$sql = "SELECT jp.*, jc.category_name,
               (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count,
               (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND status = 'pending') as pending_applications
        FROM job_postings jp
        LEFT JOIN job_categories jc ON jp.category_id = jc.id
        $whereClause
        ORDER BY jp.posted_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Get job statistics
$stats = [];
$stats['total'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ?");
$stats['total']->execute([$company['id']]);
$stats['total'] = $stats['total']->fetchColumn();

$stats['active'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'active'");
$stats['active']->execute([$company['id']]);
$stats['active'] = $stats['active']->fetchColumn();

$stats['pending'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'pending'");
$stats['pending']->execute([$company['id']]);
$stats['pending'] = $stats['pending']->fetchColumn();

$stats['expired'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'expired'");
$stats['expired']->execute([$company['id']]);
$stats['expired'] = $stats['expired']->fetchColumn();

$stats['rejected'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'rejected'");
$stats['rejected']->execute([$company['id']]);
$stats['rejected'] = $stats['rejected']->fetchColumn();

$stats['total_applications'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                             JOIN job_postings jp ON ja.job_id = jp.id 
                                             WHERE jp.company_id = ?");
$stats['total_applications']->execute([$company['id']]);
$stats['total_applications'] = $stats['total_applications']->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pamahalaan ang Listahan ng Trabaho - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/minimal.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <h1><i class="fas fa-briefcase me-2"></i>Pamahalaan ang Listahan ng Trabaho</h1>
            <a href="post-job.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>Mag-post ng Trabaho
            </a>
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

        <!-- Job Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-2 col-sm-4 col-6">
                <div class="stat-card text-center">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Jobs</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="stat-card text-center">
                    <div class="stat-value text-success"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="stat-card text-center">
                    <div class="stat-value text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="stat-card text-center">
                    <div class="stat-value text-danger"><?php echo $stats['expired']; ?></div>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="stat-card text-center">
                    <div class="stat-value text-secondary"><?php echo $stats['rejected']; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="stat-card text-center">
                    <div class="stat-value text-info"><?php echo $stats['total_applications']; ?></div>
                    <div class="stat-label">Applications</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a class="btn btn-outline-success <?php echo $status_filter === 'active' ? 'active' : ''; ?>" href="jobs.php?status=active">
                        Active Only
                    </a>
                    <a class="btn btn-outline-warning <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="jobs.php?status=pending">
                        Pending Only
                    </a>
                    <a class="btn btn-outline-danger <?php echo $status_filter === 'expired' ? 'active' : ''; ?>" href="jobs.php?status=expired">
                        Expired Only
                    </a>
                    <a class="btn btn-outline-dark <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" href="jobs.php?status=rejected">
                        Rejected Only
                    </a>
                </div>
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" id="search" 
                               placeholder="Search by job title or description..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-success w-100">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Jobs List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Pamahalaan ang Listahan ng Trabaho (<?php echo count($jobs); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($jobs)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No jobs posted yet</h5>
                        <p class="text-muted">Start posting jobs to attract talented candidates.</p>
                        <a href="post-job.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>Post Your First Job
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Category</th>
                                    <th>Location</th>
                                    <th>Employment Type</th>
                                    <th>Employees Required</th>
                                    <th>Applications</th>
                                    <th>Posted Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($job['title']); ?></strong>
                                                <?php if ($job['salary_range']): ?>
                                                    <br><small style="color: #059669;"><?php echo htmlspecialchars($job['salary_range']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($job['category_name']): ?>
                                                <span class="badge" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: white;"><?php echo htmlspecialchars($job['category_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">No category</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($job['location']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <i class="fas fa-users me-1"></i>
                                                <?php echo $job['employees_required']; ?> Employee<?php echo $job['employees_required'] === '1' ? '' : 's'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <span class="badge bg-info text-white"><?php echo $job['application_count']; ?> total</span>
                                                <?php if ($job['pending_applications'] > 0): ?>
                                                    <br><span class="badge bg-warning text-dark"><?php echo $job['pending_applications']; ?> pending</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($job['posted_date'])); ?>
                                            <br><small class="text-muted"><?php echo timeAgo($job['posted_date']); ?></small>
                                        </td>
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
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view-job.php?id=<?php echo $job['id']; ?>" 
                                                   class="btn btn-outline-info" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($job['application_count'] > 0): ?>
                                                    <a href="applications.php?job_id=<?php echo $job['id']; ?>" 
                                                       class="btn btn-outline-success" title="View Applications">
                                                        <i class="fas fa-users"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="edit-job.php?id=<?php echo $job['id']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit Job">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <div class="btn-group">
                                                    <a href="manage-job.php?id=<?php echo $job['id']; ?>" 
                                                       class="btn btn-outline-light" title="More Actions">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <!-- Note: Action forms have been moved to separate files for better organization -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
