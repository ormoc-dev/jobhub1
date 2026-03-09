<?php
include '../config.php';
requireRole('employee');

// Get application ID from URL
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$application_id) {
    $_SESSION['error'] = 'Invalid application ID.';
    redirect('applications.php');
}

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.* FROM employee_profiles ep WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('profile.php');
}

// Get application details with job and company information
$stmt = $pdo->prepare("SELECT ja.*, jp.title, jp.location, jp.salary_range, jp.description, jp.requirements,
                              c.company_name, c.company_logo, c.description as company_description,
                              jc.category_name
                       FROM job_applications ja
                       JOIN job_postings jp ON ja.job_id = jp.id
                       JOIN companies c ON jp.company_id = c.id
                       LEFT JOIN job_categories jc ON jp.category_id = jc.id
                       WHERE ja.id = ? AND ja.employee_id = ?");
$stmt->execute([$application_id, $profile['id']]);
$application = $stmt->fetch();

if (!$application) {
    $_SESSION['error'] = 'Application not found or access denied.';
    redirect('applications.php');
}

// Status styling
$statusClass = '';
$statusIcon = '';
switch($application['status']) {
    case 'pending':
        $statusClass = 'bg-warning text-dark';
        $statusIcon = 'clock';
        break;
    case 'reviewed':
        $statusClass = 'bg-info text-white';
        $statusIcon = 'eye';
        break;
    case 'accepted':
        $statusClass = 'bg-success text-white';
        $statusIcon = 'check';
        break;
    case 'rejected':
        $statusClass = 'bg-danger text-white';
        $statusIcon = 'times';
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Application - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employee-main-content">
        <div class="row">
            <div class="col-md-12 ms-sm-auto px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-file-alt me-2"></i>Application Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="applications.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Applications
                        </a>
                    </div>
                </div>

                <div class="row">
            <!-- Job Information -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-briefcase me-2"></i>Job Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($application['company_logo']): ?>
                            <div class="text-center mb-3">
                                <img src="../<?php echo htmlspecialchars($application['company_logo']); ?>" 
                                     alt="Company Logo" class="img-fluid" style="max-height: 80px;">
                            </div>
                        <?php endif; ?>
                        
                        <h3><?php echo htmlspecialchars($application['title']); ?></h3>
                        <p class="lead"><?php echo htmlspecialchars($application['company_name']); ?></p>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <?php if ($application['location']): ?>
                                    <p><i class="fas fa-map-marker-alt me-2"></i><strong>Location:</strong> <?php echo htmlspecialchars($application['location']); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($application['category_name']): ?>
                                    <p><strong>Category:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($application['category_name']); ?></span></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($application['salary_range']): ?>
                                    <p><i class="fas fa-peso-sign me-2"></i><strong>Salary:</strong> <?php echo htmlspecialchars($application['salary_range']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($application['description']): ?>
                            <hr>
                            <h6>Job Description</h6>
                            <div class="text-muted">
                                <?php echo nl2br(htmlspecialchars($application['description'])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($application['requirements']): ?>
                            <hr>
                            <h6>Requirements</h6>
                            <div class="text-muted">
                                <?php echo nl2br(htmlspecialchars($application['requirements'])); ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <a href="../job-details.php?id=<?php echo $application['job_id']; ?>" class="btn btn-success">
                                <i class="fas fa-eye me-1"></i>View Full Job Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Cover Letter -->
                <?php if ($application['cover_letter']): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-text me-2"></i>Your Cover Letter
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="bg-light p-3 rounded">
                            <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Resume -->
                <?php if ($application['resume']): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-file-pdf me-2"></i>Your Resume
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <a href="../<?php echo htmlspecialchars($application['resume']); ?>" 
                           class="btn btn-primary" target="_blank">
                            <i class="fas fa-file-pdf me-1"></i>View Resume
                        </a>
                        <a href="../<?php echo htmlspecialchars($application['resume']); ?>" 
                           class="btn btn-outline-primary" download>
                            <i class="fas fa-download me-1"></i>Download Resume
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Application Status -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Application Status
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <span class="badge <?php echo $statusClass; ?> fs-5 mb-3">
                            <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                            <?php echo ucfirst($application['status']); ?>
                        </span>
                        
                        <p class="mb-2"><strong>Applied Date:</strong></p>
                        <p><?php echo date('M j, Y g:i A', strtotime($application['applied_date'])); ?></p>
                        
                        <?php if ($application['reviewed_date']): ?>
                            <hr>
                            <p class="mb-2"><strong>Last Updated:</strong></p>
                            <p><?php echo date('M j, Y g:i A', strtotime($application['reviewed_date'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Company Information -->
                <?php if ($application['company_description']): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-building me-2"></i>About the Company
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            <?php echo nl2br(htmlspecialchars($application['company_description'])); ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

