<?php
include '../config.php';
requireRole('admin');

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$job_id) {
    $_SESSION['error'] = 'Invalid job ID.';
    redirect('jobs.php');
}

// Get job details with all related data
$stmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo, c.contact_email, c.contact_number, c.location_address,
                             jc.category_name, u.username as employer_username, u.email as employer_email,
                             COUNT(ja.id) as application_count
                      FROM job_postings jp
                      LEFT JOIN companies c ON jp.company_id = c.id
                      LEFT JOIN users u ON c.user_id = u.id
                      LEFT JOIN job_categories jc ON jp.category_id = jc.id
                      LEFT JOIN job_applications ja ON jp.id = ja.job_id
                      WHERE jp.id = ?
                      GROUP BY jp.id");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    $_SESSION['error'] = 'Job not found.';
    redirect('jobs.php');
}

// Handle status update
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $job_id = (int)$_POST['job_id'];
    
    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'active' WHERE id = ?");
            $stmt->execute([$job_id]);
            $message = 'Job approved successfully!';
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$job_id]);
            $message = 'Job rejected successfully!';
        } elseif ($action === 'suspend') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$job_id]);
            $message = 'Job suspended successfully!';
        } elseif ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'active' WHERE id = ?");
            $stmt->execute([$job_id]);
            $message = 'Job activated successfully!';
        } elseif ($action === 'delete') {
            // Check if job has applications
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE job_id = ?");
            $stmt->execute([$job_id]);
            $app_count = $stmt->fetchColumn();
            
            if ($app_count > 0) {
                $error = 'Cannot delete job with existing applications. Please suspend it instead.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id = ?");
                $stmt->execute([$job_id]);
                $_SESSION['message'] = 'Job deleted successfully!';
                redirect('jobs.php');
            }
        }
        
        // Refresh job data after update
        if (empty($error)) {
            $stmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo, c.contact_email, c.contact_number, c.location_address,
                                         jc.category_name, u.username as employer_username, u.email as employer_email,
                                         COUNT(ja.id) as application_count
                                  FROM job_postings jp
                                  LEFT JOIN companies c ON jp.company_id = c.id
                                  LEFT JOIN users u ON c.user_id = u.id
                                  LEFT JOIN job_categories jc ON jp.category_id = jc.id
                                  LEFT JOIN job_applications ja ON jp.id = ja.job_id
                                  WHERE jp.id = ?
                                  GROUP BY jp.id");
            $stmt->execute([$job_id]);
            $job = $stmt->fetch();
        }
    } catch (Exception $e) {
        $error = 'Error updating job: ' . $e->getMessage();
    }
}

// Status styling
$statusClass = '';
$statusIcon = '';
switch ($job['status']) {
    case 'active':
        $statusClass = 'bg-success text-white';
        $statusIcon = 'fas fa-check-circle';
        break;
    case 'pending':
        $statusClass = 'bg-warning text-dark';
        $statusIcon = 'fas fa-clock';
        break;
    case 'rejected':
        $statusClass = 'bg-danger text-white';
        $statusIcon = 'fas fa-times-circle';
        break;
    case 'suspended':
        $statusClass = 'bg-secondary';
        $statusIcon = 'fas fa-pause-circle';
        break;
    case 'expired':
        $statusClass = 'bg-dark';
        $statusIcon = 'fas fa-calendar-times';
        break;
    default:
        $statusClass = 'bg-secondary';
        $statusIcon = 'fas fa-question-circle';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Job Details - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1 class="h3 mb-0">Job Details</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="jobs.php">Jobs</a></li>
                            <li class="breadcrumb-item active">View Job</li>
                        </ol>
                    </nav>
                </div>
                <div class="col-md-4 text-end">
                    <a href="jobs.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Jobs
                    </a>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Job Information Card -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-briefcase me-2"></i><?php echo htmlspecialchars($job['title']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-white-50 mb-3">Basic Information</h6>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-building me-2 text-primary"></i>Company:</strong>
                                        <span class="ms-2"><?php echo htmlspecialchars($job['company_name']); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-tags me-2 text-primary"></i>Category:</strong>
                                        <span class="ms-2"><?php echo htmlspecialchars($job['category_name'] ?? 'Uncategorized'); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-map-marker-alt me-2 text-primary"></i>Location:</strong>
                                        <span class="ms-2"><?php echo htmlspecialchars($job['location']); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-clock me-2 text-primary"></i>Employment Type:</strong>
                                        <span class="ms-2"><?php echo ucfirst(htmlspecialchars($job['employment_type'])); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-white-50 mb-3">Job Details</h6>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-peso-sign me-2 text-primary"></i>Salary Range:</strong>
                                        <span class="ms-2"><?php echo htmlspecialchars($job['salary_range'] ?: 'Not specified'); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-chart-line me-2 text-primary"></i>Experience Level:</strong>
                                        <span class="ms-2"><?php echo ucfirst(htmlspecialchars($job['experience_level'])); ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-calendar me-2 text-primary"></i>Deadline:</strong>
                                        <span class="ms-2"><?php echo $job['deadline'] ? date('F j, Y', strtotime($job['deadline'])) : 'No deadline'; ?></span>
                                    </div>
                                    <div class="mb-3">
                                        <strong><i class="fas fa-users me-2 text-primary"></i>Applications:</strong>
                                        <span class="badge bg-primary text-white ms-2"><?php echo $job['application_count']; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <h6 class="text-white-50 mb-3">Job Description</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                                </div>
                            </div>

                            <?php if ($job['requirements']): ?>
                            <div class="mb-4">
                                <h6 class="text-white-50 mb-3">Requirements</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($job['qualification'])): ?>
                            <div class="mb-4">
                                <h6 class="text-white-50 mb-3">Qualifications</h6>
                                <div class="border rounded p-3 bg-light">
                                    <?php echo nl2br(html_entity_decode($job['qualification'], ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Information -->
                <div class="col-lg-4">
                    <!-- Job Status Card -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>Job Status
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <span class="badge <?php echo $statusClass; ?> fs-6 px-3 py-2">
                                    <i class="<?php echo $statusIcon; ?> me-2"></i><?php echo ucfirst($job['status']); ?>
                                </span>
                            </div>
                            <div class="mb-3">
                                <small class="text-white-50">Posted Date:</small><br>
                                <strong><?php echo date('F j, Y g:i A', strtotime($job['posted_date'])); ?></strong>
                            </div>
                            <div class="mb-3">
                                <small class="text-white-50">Last Updated:</small><br>
                                <strong><?php echo date('F j, Y g:i A', strtotime($job['updated_at'])); ?></strong>
                            </div>
                        </div>
                    </div>

                    <!-- Company Information Card -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-building me-2"></i>Company Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Company Name:</strong><br>
                                <span><?php echo htmlspecialchars($job['company_name']); ?></span>
                            </div>
                            <div class="mb-3">
                                <strong>Contact Email:</strong><br>
                                <span><?php echo htmlspecialchars($job['contact_email'] ?: $job['employer_email']); ?></span>
                            </div>
                            <?php if ($job['contact_number']): ?>
                            <div class="mb-3">
                                <strong>Contact Number:</strong><br>
                                <span><?php echo htmlspecialchars($job['contact_number']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($job['location_address']): ?>
                            <div class="mb-3">
                                <strong>Address:</strong><br>
                                <span><?php echo htmlspecialchars($job['location_address']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions Card -->
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-cogs me-2"></i>Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($job['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Approve this job?')">
                                            <i class="fas fa-check me-2"></i>Approve Job
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Reject this job?')">
                                            <i class="fas fa-times me-2"></i>Reject Job
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($job['status'] === 'active'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="suspend">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-warning w-100" onclick="return confirm('Suspend this job?')">
                                            <i class="fas fa-pause me-2"></i>Suspend Job
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($job['status'], ['suspended', 'rejected'])): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Activate this job?')">
                                            <i class="fas fa-play me-2"></i>Activate Job
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($job['application_count'] > 0): ?>
                                    <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-info w-100">
                                        <i class="fas fa-file-alt me-2"></i>View Applications (<?php echo $job['application_count']; ?>)
                                    </a>
                                <?php endif; ?>

                                <a href="view-company.php?id=<?php echo $job['company_id']; ?>" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-building me-2"></i>View Company
                                </a>

                                <?php if ($job['application_count'] == 0): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this job? This action cannot be undone.')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger w-100">
                                            <i class="fas fa-trash me-2"></i>Delete Job
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
