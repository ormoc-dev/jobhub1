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

// Auto-move expired applications to 'rejected' when job deadline has passed
$stmt = $pdo->prepare("UPDATE job_applications ja 
                       JOIN job_postings jp ON ja.job_id = jp.id 
                       SET ja.status = 'rejected'
                       WHERE jp.company_id = ? 
                       AND ja.status = 'pending' 
                       AND jp.deadline IS NOT NULL 
                       AND jp.deadline < CURDATE()");
$stmt->execute([$company['id']]);

// Handle application actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $application_id = (int)$_POST['application_id'];
    
    if ($action === 'update_status') {
        $new_status = sanitizeInput($_POST['new_status']);
        $allowed_statuses = ['pending', 'reviewed', 'accepted', 'rejected'];
        
        if (in_array($new_status, $allowed_statuses)) {
            // Verify application belongs to company
            $stmt = $pdo->prepare("SELECT ja.* FROM job_applications ja 
                                   JOIN job_postings jp ON ja.job_id = jp.id 
                                   WHERE ja.id = ? AND jp.company_id = ?");
            $stmt->execute([$application_id, $company['id']]);
            $application = $stmt->fetch();
            
            if ($application) {
                $stmt = $pdo->prepare("UPDATE job_applications SET status = ?, reviewed_date = NOW() WHERE id = ?");
                if ($stmt->execute([$new_status, $application_id])) {
                    $message = 'Application status updated successfully!';
                } else {
                    $error = 'Failed to update application status.';
                }
            } else {
                $error = 'Application not found or access denied.';
            }
        } else {
            $error = 'Invalid status.';
        }
    }
    
    if ($action === 'update_interview_status') {
        $new_interview_status = sanitizeInput($_POST['new_interview_status']);
        $allowed_interview_statuses = ['uninterview', 'interviewed'];
        
        if (in_array($new_interview_status, $allowed_interview_statuses)) {
            // Verify application belongs to company
            $stmt = $pdo->prepare("SELECT ja.* FROM job_applications ja 
                                   JOIN job_postings jp ON ja.job_id = jp.id 
                                   WHERE ja.id = ? AND jp.company_id = ?");
            $stmt->execute([$application_id, $company['id']]);
            $application = $stmt->fetch();
            
            if ($application) {
                $stmt = $pdo->prepare("UPDATE job_applications SET interview_status = ? WHERE id = ?");
                if ($stmt->execute([$new_interview_status, $application_id])) {
                    $message = 'Interview status updated successfully!';
                } else {
                    $error = 'Failed to update interview status.';
                }
            } else {
                $error = 'Application not found or access denied.';
            }
        } else {
            $error = 'Invalid interview status.';
        }
    }
}

// Get filter parameters
$job_filter = $_GET['job_id'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$fast_filter = $_GET['fast_filter'] ?? '';
$interview_filter = $_GET['interview'] ?? '';

// Build query
$whereClause = "WHERE jp.company_id = ?";
$params = [$company['id']];

if (!empty($job_filter)) {
    $whereClause .= " AND ja.job_id = ?";
    $params[] = $job_filter;
}

// Handle interview filter tabs
if ($interview_filter === 'for_interview') {
    // For Interview tab - scheduled interviews that are NOT yet completed
    $whereClause .= " AND ja.interview_date IS NOT NULL AND (ja.interview_status IS NULL OR ja.interview_status = 'uninterview') AND ja.status NOT IN ('accepted', 'rejected')";
} elseif ($interview_filter === 'scheduled') {
    // Interviewed tab - completed interviews, waiting for hire/reject decision
    $whereClause .= " AND ja.interview_status = 'interviewed' AND ja.status NOT IN ('accepted', 'rejected')";
} 
// Handle status filter (other tabs)
elseif (!empty($status_filter)) {
    $whereClause .= " AND ja.status = ?";
    $params[] = $status_filter;
}
// Default: show pending (New Applicants) if no filter is set
else {
    $whereClause .= " AND ja.status = 'pending'";
    $status_filter = 'pending'; // Set for active tab highlighting
}

if (!empty($search)) {
    $whereClause .= " AND (ep.first_name LIKE ? OR ep.last_name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Get applications with job and employee details
$sql = "SELECT ja.*, ja.status, ja.interview_status, ja.interview_date, ja.interview_time, ja.interview_mode,
               jp.title as job_title, jp.location, jp.salary_range,
               ep.first_name, ep.last_name, u.email, ep.contact_no, ep.address,
               ep.highest_education, ep.sex, ep.date_of_birth,
               CASE WHEN fa.application_id IS NOT NULL THEN 1 ELSE 0 END as is_fast_application,
               COALESCE(fa.priority_score, 0) as priority_score
        FROM job_applications ja
        JOIN job_postings jp ON ja.job_id = jp.id
        JOIN employee_profiles ep ON ja.employee_id = ep.id
        JOIN users u ON ep.user_id = u.id
        " . ($fast_filter === 'fast' ? "INNER JOIN fast_applications fa ON ja.id = fa.application_id AND fa.is_priority = TRUE" : "LEFT JOIN fast_applications fa ON ja.id = fa.application_id AND fa.is_priority = TRUE") . "
        $whereClause
        ORDER BY " . (($interview_filter === 'scheduled' || $interview_filter === 'for_interview') ? "ja.interview_date ASC, ja.interview_time ASC, " : "") . "is_fast_application DESC, priority_score DESC, ja.applied_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get application statistics
$stats = [];
$stats['total'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                 JOIN job_postings jp ON ja.job_id = jp.id 
                                 WHERE jp.company_id = ?");
$stats['total']->execute([$company['id']]);
$stats['total'] = $stats['total']->fetchColumn();

$stats['pending'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                   JOIN job_postings jp ON ja.job_id = jp.id 
                                   WHERE jp.company_id = ? AND ja.status = 'pending'");
$stats['pending']->execute([$company['id']]);
$stats['pending'] = $stats['pending']->fetchColumn();

$stats['reviewed'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                    JOIN job_postings jp ON ja.job_id = jp.id 
                                    WHERE jp.company_id = ? AND ja.status = 'reviewed'");
$stats['reviewed']->execute([$company['id']]);
$stats['reviewed'] = $stats['reviewed']->fetchColumn();

$stats['accepted'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                    JOIN job_postings jp ON ja.job_id = jp.id 
                                    WHERE jp.company_id = ? AND ja.status = 'accepted'");
$stats['accepted']->execute([$company['id']]);
$stats['accepted'] = $stats['accepted']->fetchColumn();

$stats['rejected'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                    JOIN job_postings jp ON ja.job_id = jp.id 
                                    WHERE jp.company_id = ? AND ja.status = 'rejected'");
$stats['rejected']->execute([$company['id']]);
$stats['rejected'] = $stats['rejected']->fetchColumn();

// Get company jobs for filter dropdown
$company_jobs = $pdo->prepare("SELECT id, title FROM job_postings WHERE company_id = ? ORDER BY title");
$company_jobs->execute([$company['id']]);
$company_jobs = $company_jobs->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Applications - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-users me-2"></i>Job Applications</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="jobs.php" class="btn btn-outline-light">
                    <i class="fas fa-briefcase me-1"></i>My Jobs
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

        <style>
            @keyframes pulse-glow {
                0%, 100% { 
                    box-shadow: 0 4px 15px rgba(40,167,69,0.5);
                }
                50% { 
                    box-shadow: 0 4px 25px rgba(40,167,69,0.8);
                }
            }
            .table-warning:hover {
                transform: scale(1.01);
                transition: transform 0.2s ease;
            }
            .application-row {
                cursor: pointer;
                transition: background-color 0.2s ease;
            }
            .application-row:hover {
                background-color: #f8f9fa !important;
            }
            .application-row.table-warning:hover {
                background: linear-gradient(90deg, #fff9e6 0%, #fff8e0 100%) !important;
            }
            .nav-tabs {
                border-bottom: 2px solid #dee2e6;
            }
            .nav-tabs .nav-link {
                color: #495057;
                border: none;
                border-bottom: 3px solid transparent;
                padding: 15px 20px;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            .nav-tabs .nav-link:hover {
                border-bottom-color: #0d6efd;
                color: #0d6efd;
                background-color: #f8f9fa;
            }
            .nav-tabs .nav-link.active {
                color: #0d6efd;
                border-bottom-color: #0d6efd;
                background-color: #f8f9fa;
                font-weight: 600;
            }
        </style>

        <!-- Application Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['total']; ?></h4>
                        <small class="text-nowrap">Total Applications</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card dashboard-card text-dark bg-warning">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['pending']; ?></h4>
                        <small>Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card dashboard-card text-white bg-info">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['reviewed']; ?></h4>
                        <small>Reviewed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="card dashboard-card text-white bg-success">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['accepted']; ?></h4>
                        <small>Accepted</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white bg-danger">
                    <div class="card-body text-center p-3">
                        <h4 class="mb-2"><?php echo $stats['rejected']; ?></h4>
                        <small>Rejected</small>
                    </div>
                </div>
            </div>
        </div>

                <!-- Application Tabs -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs nav-justified" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo ($status_filter === 'pending' || (empty($status_filter) && empty($interview_filter))) ? 'active' : ''; ?>" 
                           href="?status=pending">
                            <i class="fas fa-user-plus me-2"></i>New Applicants
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $status_filter === 'reviewed' ? 'active' : ''; ?>" 
                           href="?status=reviewed">
                            <i class="fas fa-star me-2"></i>Shortlisted Candidates
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $interview_filter === 'scheduled' ? 'active' : ''; ?>" 
                           href="?interview=scheduled">
                            <i class="fas fa-calendar-check me-2"></i>Interviewed
                            <?php 
                            // Count interviewed (completed interviews)
                            $interviewed_count = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                                                 JOIN job_postings jp ON ja.job_id = jp.id 
                                                                 WHERE jp.company_id = ? 
                                                                 AND ja.interview_status = 'interviewed'
                                                                 AND ja.status NOT IN ('accepted', 'rejected')");
                            $interviewed_count->execute([$company['id']]);
                            $interviewed_count = $interviewed_count->fetchColumn();
                            if ($interviewed_count > 0): ?>
                                <span class="badge bg-success ms-1"><?php echo $interviewed_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $status_filter === 'accepted' ? 'active' : ''; ?>" 
                           href="?status=accepted">
                            <i class="fas fa-check-circle me-2"></i>Hired Applicants
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" 
                           href="?status=rejected">
                            <i class="fas fa-archive me-2"></i>Rejected / Archived
                        </a>
                    </li>
                </ul>
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
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No applications found</h5>
                        <p class="text-muted">
                            <?php if (empty($company_jobs)): ?>
                                Start posting jobs to receive applications.
                            <?php else: ?>
                                Applications will appear here once candidates apply to your job postings.
                            <?php endif; ?>
                        </p>
                        <?php if (empty($company_jobs)): ?>
                            <a href="post-job.php" class="btn" style="background: #10b981; border-color: #10b981; color: white;">
                                <i class="fas fa-plus me-1"></i>Post Your First Job
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                        <tr>
                                    <th>Applicant</th>
                                    <th>Job Position</th>
                                    <?php if ($interview_filter === 'scheduled' || $interview_filter === 'for_interview'): ?>
                                    <th>Interview Schedule</th>
                                    <?php else: ?>
                                    <th>Contact</th>
                                    <?php endif; ?>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr class="<?php echo $app['is_fast_application'] ? 'table-warning application-row' : 'application-row'; ?>" 
                                        style="<?php echo $app['is_fast_application'] ? 'border-left: 8px solid #ffc107; background: linear-gradient(90deg, #fffbf0 0%, #fff9e6 100%) !important; box-shadow: 0 4px 8px rgba(255,193,7,0.3);' : ''; ?>"
                                        onclick="window.location.href='view-application.php?id=<?php echo $app['id']; ?>'">
                                        <td>
                                            <div>
                                                <?php if ($app['is_fast_application']): ?>
                                                    <div class="d-flex align-items-center mb-3 flex-wrap gap-2">
                                                        <span class="badge bg-warning text-dark px-4 py-2 me-2" 
                                                              style="font-size: 1rem; font-weight: bold; box-shadow: 0 2px 8px rgba(255,193,7,0.5);" 
                                                              title="Premium Fast Application - Paid for Priority Review">
                                                            <i class="fas fa-bolt me-1"></i><strong>FAST APPLICATION</strong>
                                                        </span>
                                                        <span class="badge bg-danger text-white px-3 py-2" 
                                                              style="font-size: 0.9rem; font-weight: bold; box-shadow: 0 2px 8px rgba(220,53,69,0.5);">
                                                            <i class="fas fa-crown me-1"></i><strong>PAID SUBSCRIBER</strong>
                                                        </span>
                                                        <span class="badge bg-success text-white px-3 py-2" 
                                                              style="font-size: 0.85rem; font-weight: bold;">
                                                            <i class="fas fa-money-bill-wave me-1"></i>PREMIUM MEMBER
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <strong style="font-size: 1.2rem; <?php echo $app['is_fast_application'] ? 'color: #856404;' : ''; ?>">
                                                    <a href="view-application.php?id=<?php echo $app['id']; ?>" 
                                                       style="text-decoration: none; color: inherit; <?php echo $app['is_fast_application'] ? 'color: #856404;' : 'color: #0d6efd;'; ?>"
                                                       onmouseover="this.style.textDecoration='underline'; this.style.color='<?php echo $app['is_fast_application'] ? '#856404' : '#0a58ca'; ?>'"
                                                       onmouseout="this.style.textDecoration='none'; this.style.color='<?php echo $app['is_fast_application'] ? '#856404' : '#0d6efd'; ?>'"
                                                       title="Click to view full application details"
                                                       onclick="event.stopPropagation();">
                                                        <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                                    </a>
                                                    <?php if ($app['is_fast_application']): ?>
                                                        <i class="fas fa-star text-warning ms-2" style="font-size: 0.9rem;"></i>
                                                    <?php endif; ?>
                                                </strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                                                <?php if ($app['highest_education']): ?>
                                                    <br><span class="badge bg-secondary mt-1"><?php echo htmlspecialchars($app['highest_education']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                            <?php if ($app['location']): ?>
                                                <br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($app['location']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($interview_filter === 'scheduled' || $interview_filter === 'for_interview'): ?>
                                                <?php if (!empty($app['interview_date'])): ?>
                                                    <i class="fas fa-calendar me-1 text-primary"></i>
                                                    <strong><?php echo date('F j, Y', strtotime($app['interview_date'])); ?></strong><br>
                                                <?php endif; ?>
                                                <?php if (!empty($app['interview_time'])): ?>
                                                    <i class="fas fa-clock me-1 text-success"></i>
                                                    <?php echo htmlspecialchars($app['interview_time']); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($app['interview_mode'])): ?>
                                                    <i class="fas fa-video me-1 text-info"></i>
                                                    <small><?php echo htmlspecialchars($app['interview_mode']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if ($app['contact_no']): ?>
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['contact_no']); ?><br>
                                                <?php endif; ?>
                                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($app['applied_date'])); ?>
                                            <br><small class="text-muted"><?php echo timeAgo($app['applied_date']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            // Badge ay base sa active tab: isa lang ang lalabas
                                            $displayStatus = '';
                                            $displayClass = '';
                                            $displayIcon = '';
                                            if ($interview_filter === 'for_interview') {
                                                $displayStatus = 'For Interview';
                                                $displayClass = 'bg-primary text-white';
                                                $displayIcon = 'calendar-alt';
                                            } elseif ($interview_filter === 'scheduled') {
                                                $displayStatus = 'Interviewed';
                                                $displayClass = 'bg-success text-white';
                                                $displayIcon = 'check-circle';
                                            } elseif ($status_filter === 'accepted') {
                                                $displayStatus = 'Hired';
                                                $displayClass = 'bg-success text-white';
                                                $displayIcon = 'user-check';
                                            } elseif ($status_filter === 'reviewed') {
                                                $displayStatus = 'Reviewed';
                                                $displayClass = 'bg-info text-white';
                                                $displayIcon = 'eye';
                                            } elseif ($status_filter === 'rejected') {
                                                $displayStatus = 'Rejected';
                                                $displayClass = 'bg-danger text-white';
                                                $displayIcon = 'times';
                                            } else {
                                                $displayStatus = 'Pending';
                                                $displayClass = 'bg-warning text-dark';
                                                $displayIcon = 'clock';
                                            }
                                            ?>
                                            <span class="badge <?php echo $displayClass; ?>">
                                                <i class="fas fa-<?php echo $displayIcon; ?> me-1"></i>
                                                <?php echo $displayStatus; ?>
                                            </span>
                                        </td>
                                        <td onclick="event.stopPropagation();">
                                            <div class="btn-group btn-group-sm">
                                                <a href="view-application.php?id=<?php echo $app['id']; ?>" 
                                                   class="btn btn-outline-primary" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($app['resume']): ?>
                                                    <a href="../<?php echo htmlspecialchars($app['resume']); ?>" 
                                                       target="_blank" class="btn btn-outline-success" title="View Resume">
                                                        <i class="fas fa-file-alt"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($interview_filter === 'for_interview'): ?>
                                                    <button type="button" class="btn btn-info" title="Mark as Interviewed (Interview Done)"
                                                            onclick="event.stopPropagation(); confirmMarkInterviewed(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars(addslashes($app['first_name'] . ' ' . $app['last_name'])); ?>');">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($interview_filter === 'scheduled' && $app['interview_status'] === 'interviewed'): ?>
                                                    <button type="button" class="btn btn-success" title="Hire Applicant"
                                                            onclick="event.stopPropagation(); confirmHire(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars(addslashes($app['first_name'] . ' ' . $app['last_name'])); ?>');">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger" title="Reject Applicant"
                                                            onclick="event.stopPropagation(); confirmReject(<?php echo $app['id']; ?>, '<?php echo htmlspecialchars(addslashes($app['first_name'] . ' ' . $app['last_name'])); ?>');">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                <?php endif; ?>
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

    <!-- Hidden forms for hire/reject/interview actions -->
    <form id="hireForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="new_status" value="accepted">
        <input type="hidden" name="application_id" id="hireApplicationId">
    </form>
    
    <form id="rejectForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="new_status" value="rejected">
        <input type="hidden" name="application_id" id="rejectApplicationId">
    </form>
    
    <form id="markInterviewedForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_interview_status">
        <input type="hidden" name="new_interview_status" value="interviewed">
        <input type="hidden" name="application_id" id="markInterviewedApplicationId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmHire(applicationId, applicantName) {
            if (confirm('Are you sure you want to HIRE ' + applicantName + '?\n\nThis will move them to "Hired Applicants" tab.')) {
                document.getElementById('hireApplicationId').value = applicationId;
                document.getElementById('hireForm').submit();
            }
        }
        
        function confirmReject(applicationId, applicantName) {
            if (confirm('Are you sure you want to REJECT ' + applicantName + '?\n\nThis will move them to "Rejected / Archived" tab.')) {
                document.getElementById('rejectApplicationId').value = applicationId;
                document.getElementById('rejectForm').submit();
            }
        }
        
        function confirmMarkInterviewed(applicationId, applicantName) {
            if (confirm('Mark ' + applicantName + ' as INTERVIEWED?\n\nThis means the interview has been completed.\nThey will move to the "Interviewed" tab where you can decide to Hire or Reject.')) {
                document.getElementById('markInterviewedApplicationId').value = applicationId;
                document.getElementById('markInterviewedForm').submit();
            }
        }
    </script>
</body>
</html>
