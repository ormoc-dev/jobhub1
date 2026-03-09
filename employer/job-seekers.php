<?php
include '../config.php';
requireRole('employer');

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
$status_filter = $_GET['status'] ?? 'all';
$education_filter = $_GET['education'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereClause = "WHERE u.role = 'employee' AND u.status = 'active'";
$params = [];

if ($status_filter !== 'all') {
    if ($status_filter === 'verified') {
        $whereClause .= " AND (ep.document1 IS NOT NULL AND ep.document1 != '' AND ep.document2 IS NOT NULL AND ep.document2 != '')";
    } elseif ($status_filter === 'unverified') {
        $whereClause .= " AND (ep.document1 IS NULL OR ep.document1 = '' OR ep.document2 IS NULL OR ep.document2 = '')";
    }
}

if (!empty($education_filter)) {
    $whereClause .= " AND ep.highest_education = ?";
    $params[] = $education_filter;
}

if (!empty($search)) {
    $whereClause .= " AND (ep.first_name LIKE ? OR ep.last_name LIKE ? OR u.email LIKE ? OR ep.employee_id LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Get all job seekers (employees)
$sql = "SELECT u.id as user_id, u.email, u.username, u.profile_picture, u.status as user_status, u.created_at,
               ep.*, 
               (SELECT COUNT(*) FROM job_applications ja 
                JOIN job_postings jp ON ja.job_id = jp.id 
                WHERE ja.employee_id = ep.id AND jp.company_id = ?) as applications_to_company
        FROM users u
        JOIN employee_profiles ep ON u.id = ep.user_id
        $whereClause
        ORDER BY ep.first_name, ep.last_name";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$company['id']], $params));
$job_seekers = $stmt->fetchAll();

// Get job seeker statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM users u JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.role = 'employee' AND u.status = 'active'")->fetchColumn();

$stats['verified'] = $pdo->query("SELECT COUNT(*) FROM users u JOIN employee_profiles ep ON u.id = ep.user_id WHERE u.role = 'employee' AND u.status = 'active' AND ep.document1 IS NOT NULL AND ep.document1 != '' AND ep.document2 IS NOT NULL AND ep.document2 != ''")->fetchColumn();

$stats['unverified'] = $stats['total'] - $stats['verified'];

// Get unique education levels for filter
$education_levels = $pdo->query("SELECT DISTINCT highest_education FROM employee_profiles WHERE highest_education IS NOT NULL AND highest_education != '' ORDER BY highest_education")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Seekers - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-user-tie me-2"></i>Job Seekers</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="applications.php" class="btn btn-outline-light">
                    <i class="fas fa-users me-1"></i>Applications
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

        <!-- Job Seeker Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['total']; ?></h4>
                        <small class="text-nowrap">Total Job Seekers</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card text-white bg-success">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['verified']; ?></h4>
                        <small>Verified</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card dashboard-card text-dark bg-warning">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['unverified']; ?></h4>
                        <small>Unverified</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Verification Status</label>
                        <select class="form-select" name="status" id="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                            <option value="unverified" <?php echo $status_filter === 'unverified' ? 'selected' : ''; ?>>Unverified</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="education" class="form-label">Education Level</label>
                        <select class="form-select" name="education" id="education">
                            <option value="">All Education Levels</option>
                            <?php foreach ($education_levels as $edu): ?>
                                <option value="<?php echo htmlspecialchars($edu); ?>" <?php echo $education_filter === $edu ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($edu); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Job Seeker</label>
                        <input type="text" class="form-control" name="search" id="search" 
                               placeholder="Search by name, email, or employee ID..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn" style="background: #10b981; border-color: #10b981; color: white;" class="w-100">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Job Seekers List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Job Seekers (<?php echo count($job_seekers); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($job_seekers)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No job seekers found</h5>
                        <p class="text-muted">No job seekers match your search criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Job Seeker</th>
                                    <th>Contact Information</th>
                                    <th>Education</th>
                                    <th>Location</th>
                                    <th>Applications to Your Jobs</th>
                                    <th>Verification Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($job_seekers as $seeker): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php
                                                $profileImage = $seeker['profile_picture'] ?? null;
                                                $imagePath = $profileImage && file_exists('../' . $profileImage) ? '../' . $profileImage : null;
                                                ?>
                                                <?php if ($imagePath): ?>
                                                    <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Profile" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($seeker['first_name'] . ' ' . $seeker['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">ID: <?php echo htmlspecialchars($seeker['employee_id']); ?></small>
                                                    <?php if ($seeker['sex']): ?>
                                                        <br><span class="badge bg-secondary"><?php echo htmlspecialchars($seeker['sex']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($seeker['email']); ?>
                                            <?php if ($seeker['contact_no']): ?>
                                                <br><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($seeker['contact_no']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($seeker['highest_education']): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($seeker['highest_education']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($seeker['address']): ?>
                                                <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($seeker['address']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $seeker['applications_to_company']; ?> application(s)</span>
                                        </td>
                                        <td>
                                            <?php
                                            $isVerified = ($seeker['document1'] && $seeker['document1'] != '') && ($seeker['document2'] && $seeker['document2'] != '');
                                            if ($isVerified):
                                            ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i>Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-clock me-1"></i>Unverified
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="employee-documents.php" class="btn btn-sm btn-outline-primary" title="View Documents">
                                                    <i class="fas fa-file-alt"></i> Documents
                                                </a>
                                                <a href="messages.php?user_id=<?php echo $seeker['user_id']; ?>" class="btn btn-sm" style="background: #10b981; border-color: #10b981; color: white;" title="Send Message">
                                                    <i class="fas fa-envelope"></i> Message
                                                </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

