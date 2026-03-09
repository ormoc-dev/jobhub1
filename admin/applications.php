<?php
include '../config.php';
requireRole('admin');

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

// Filters
$company_filter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$job_filter = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$whereClause = "WHERE 1=1";
$params = [];

if ($company_filter > 0) {
    $whereClause .= " AND jp.company_id = ?";
    $params[] = $company_filter;
}

if ($job_filter > 0) {
    $whereClause .= " AND ja.job_id = ?";
    $params[] = $job_filter;
}

if ($status_filter !== 'all') {
    $whereClause .= " AND ja.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $whereClause .= " AND (ep.first_name LIKE ? OR ep.last_name LIKE ? OR u.email LIKE ? OR jp.title LIKE ? OR c.company_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$sql = "SELECT ja.*, ja.status, ja.interview_status, jp.title as job_title, jp.location, 
               c.company_name, c.id as company_id,
               ep.first_name, ep.last_name, u.email, ep.contact_no
        FROM job_applications ja
        JOIN job_postings jp ON ja.job_id = jp.id
        JOIN companies c ON jp.company_id = c.id
        JOIN employee_profiles ep ON ja.employee_id = ep.id
        JOIN users u ON ep.user_id = u.id
        $whereClause
        ORDER BY ja.applied_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Filter data
$companies = $pdo->query("SELECT id, company_name FROM companies ORDER BY company_name")->fetchAll();

if ($company_filter > 0) {
    $jobsStmt = $pdo->prepare("SELECT id, title FROM job_postings WHERE company_id = ? ORDER BY title");
    $jobsStmt->execute([$company_filter]);
    $jobs = $jobsStmt->fetchAll();
} else {
    $jobs = $pdo->query("SELECT jp.id, jp.title, c.company_name 
                         FROM job_postings jp 
                         JOIN companies c ON jp.company_id = c.id 
                         ORDER BY c.company_name, jp.title")->fetchAll();
}

// Context info for header buttons
$company_context = null;
if ($company_filter > 0) {
    $stmt = $pdo->prepare("SELECT id, company_name FROM companies WHERE id = ?");
    $stmt->execute([$company_filter]);
    $company_context = $stmt->fetch();
}

$job_context = null;
if ($job_filter > 0) {
    $stmt = $pdo->prepare("SELECT jp.id, jp.title, c.company_name, c.id as company_id 
                           FROM job_postings jp 
                           JOIN companies c ON jp.company_id = c.id 
                           WHERE jp.id = ?");
    $stmt->execute([$job_filter]);
    $job_context = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2"><i class="fas fa-file-alt me-2"></i>Applications</h1>
                <?php if ($company_context): ?>
                    <small class="text-white-50">Company: <?php echo htmlspecialchars($company_context['company_name']); ?></small>
                <?php elseif ($job_context): ?>
                    <small class="text-white-50">Job: <?php echo htmlspecialchars($job_context['title']); ?> (<?php echo htmlspecialchars($job_context['company_name']); ?>)</small>
                <?php endif; ?>
            </div>
            <div class="btn-toolbar mb-2 mb-md-0">
                <?php if ($job_context): ?>
                    <a href="view-job.php?id=<?php echo $job_context['id']; ?>" class="btn btn-outline-light me-2">
                        <i class="fas fa-briefcase me-1"></i>Back to Job
                    </a>
                    <a href="view-company.php?id=<?php echo $job_context['company_id']; ?>" class="btn btn-outline-light">
                        <i class="fas fa-building me-1"></i>Back to Company
                    </a>
                <?php elseif ($company_context): ?>
                    <a href="view-company.php?id=<?php echo $company_context['id']; ?>" class="btn btn-outline-light">
                        <i class="fas fa-building me-1"></i>Back to Company
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="company_id" class="form-label">Company</label>
                        <select class="form-select" name="company_id" id="company_id">
                            <option value="">All Companies</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>" <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="job_id" class="form-label">Job</label>
                        <select class="form-select" name="job_id" id="job_id">
                            <option value="">All Jobs</option>
                            <?php foreach ($jobs as $job): ?>
                                <option value="<?php echo $job['id']; ?>" <?php echo $job_filter == $job['id'] ? 'selected' : ''; ?>>
                                    <?php if (isset($job['company_name'])): ?>
                                        <?php echo htmlspecialchars($job['company_name'] . ' - ' . $job['title']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                            <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" id="search" 
                               placeholder="Applicant, job, company..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn" style="border-color: #10b981; color: #10b981;">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Applications List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Applications (<?php echo count($applications); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No applications found</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Job</th>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                                        </td>
                                        <td>
                                            <a href="view-job.php?id=<?php echo $app['job_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($app['job_title']); ?>
                                            </a>
                                            <?php if ($app['location']): ?>
                                                <br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($app['location']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view-company.php?id=<?php echo $app['company_id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($app['company_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($app['contact_no']): ?>
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['contact_no']); ?><br>
                                            <?php endif; ?>
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($app['applied_date'])); ?>
                                            <br><small class="text-muted"><?php echo timeAgo($app['applied_date']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusIcon = '';
                                            switch($app['status']) {
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
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                                <?php echo ucfirst($app['status']); ?>
                                            </span>
                                            <?php if (!empty($app['interview_status'])): ?>
                                                <br>
                                                <?php
                                                $interviewStatusClass = $app['interview_status'] === 'interviewed' ? 'bg-success text-white' : 'bg-secondary text-white';
                                                $interviewStatusIcon = $app['interview_status'] === 'interviewed' ? 'check-circle' : 'calendar-times';
                                                ?>
                                                <span class="badge <?php echo $interviewStatusClass; ?> mt-1">
                                                    <i class="fas fa-<?php echo $interviewStatusIcon; ?> me-1"></i>
                                                    <?php echo ucfirst($app['interview_status']); ?>
                                                </span>
                                            <?php endif; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
