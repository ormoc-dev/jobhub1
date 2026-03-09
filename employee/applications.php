<?php
include '../config.php';
requireRole('employee');

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.* FROM employee_profiles ep WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('profile.php');
}

// Get active tab from URL
$active_tab = $_GET['tab'] ?? 'submitted';

// Get employee applications with job and company details (including interview and offer fields)
$stmt = $pdo->prepare("SELECT ja.*, ja.interview_status, ja.interview_date, ja.interview_time, ja.interview_mode, 
                              ja.interview_location, ja.interview_result, ja.offer_sent, ja.offer_status, 
                              ja.offer_salary, ja.offer_start_date,
                              jp.title, jp.location, jp.salary_range, c.company_name, c.company_logo
                       FROM job_applications ja
                       JOIN job_postings jp ON ja.job_id = jp.id
                       JOIN companies c ON jp.company_id = c.id
                       WHERE ja.employee_id = ?
                       ORDER BY ja.applied_date DESC");
$stmt->execute([$profile['id']]);
$all_applications = $stmt->fetchAll();

// Filter applications based on active tab
$applications = [];
switch($active_tab) {
    case 'interviews':
        // Interview Invites - applications with interview scheduled (not yet hired/rejected)
        $applications = array_filter($all_applications, function($app) {
            // Show if interview is scheduled AND not yet hired/rejected
            return (isset($app['interview_date']) && $app['interview_date'] !== null) 
                && !in_array($app['status'], ['accepted', 'rejected']);
        });
        // Sort by interview date (soonest first)
        usort($applications, function($a, $b) {
            $dateA = $a['interview_date'] . ' ' . ($a['interview_time'] ?? '00:00:00');
            $dateB = $b['interview_date'] . ' ' . ($b['interview_time'] ?? '00:00:00');
            return strtotime($dateA) - strtotime($dateB);
        });
        break;
    case 'offers':
        // Job Offers - applications with offers
        $applications = array_filter($all_applications, function($app) {
            return (isset($app['offer_sent']) && $app['offer_sent'] == 1) 
                || (isset($app['offer_status']) && $app['offer_status'] !== null);
        });
        break;
    case 'history':
        // Application History - all applications
        $applications = $all_applications;
        break;
    case 'submitted':
    default:
        // Submitted Applications - all applications
        $applications = $all_applications;
        break;
}

// Re-index array after filtering
$applications = array_values($applications);

// Get application statistics
$stats = [];
$stats['total'] = count($all_applications);
$stats['pending'] = count(array_filter($all_applications, function($app) { return $app['status'] === 'pending'; }));
$stats['reviewed'] = count(array_filter($all_applications, function($app) { return $app['status'] === 'reviewed'; }));
$stats['accepted'] = count(array_filter($all_applications, function($app) { return $app['status'] === 'accepted'; }));
$stats['rejected'] = count(array_filter($all_applications, function($app) { return $app['status'] === 'rejected'; }));
$stats['interviews'] = count(array_filter($all_applications, function($app) {
    // Count interviews that are scheduled AND not yet hired/rejected
    return (isset($app['interview_date']) && $app['interview_date'] !== null) 
        && !in_array($app['status'], ['accepted', 'rejected']);
}));
$stats['offers'] = count(array_filter($all_applications, function($app) {
    return (isset($app['offer_sent']) && $app['offer_sent'] == 1) 
        || (isset($app['offer_status']) && $app['offer_status'] !== null);
}));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employee-main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2"><i class="fas fa-file-alt me-2"></i>My Applications</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="jobs.php" class="btn" style="background: #10b981; border-color: #10b981; color: white;">
                    <i class="fas fa-search me-1"></i>Browse More Jobs
                </a>
            </div>
        </div>

        <!-- Application Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-2">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['total']; ?></h4>
                        <small>Total Applications</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-dark bg-warning">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['pending']; ?></h4>
                        <small><i class="fas fa-clock me-1"></i>Pending</small>
                        <div class="small text-muted" style="font-size: 0.65rem;">Hindi pa nakita</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-white bg-info">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['reviewed']; ?></h4>
                        <small><i class="fas fa-eye me-1"></i>Under Review</small>
                        <div class="small" style="font-size: 0.65rem; opacity: 0.9;">Nakita na</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card dashboard-card text-white bg-success">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['accepted']; ?></h4>
                        <small><i class="fas fa-check-circle me-1"></i>Accepted</small>
                        <div class="small" style="font-size: 0.65rem; opacity: 0.9;">Approved na!</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card dashboard-card text-white bg-danger">
                    <div class="card-body text-center">
                        <h4><?php echo $stats['rejected']; ?></h4>
                        <small><i class="fas fa-times-circle me-1"></i>Rejected</small>
                        <div class="small" style="font-size: 0.65rem; opacity: 0.9;">Hindi na-approve</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="row mb-3">
            <div class="col-12">
                <ul class="nav nav-pills nav-justified" id="applicationsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'submitted' ? 'active' : ''; ?>" 
                           href="?tab=submitted"
                           style="<?php echo $active_tab === 'submitted' ? 'background: #10b981; color: white;' : 'color: #10b981; border: 1px solid #10b981;'; ?>">
                            <i class="fas fa-file-alt me-2"></i>Submitted Applications
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'interviews' ? 'active' : ''; ?>" 
                           href="?tab=interviews"
                           style="<?php echo $active_tab === 'interviews' ? 'background: #10b981; color: white;' : 'color: #10b981; border: 1px solid #10b981;'; ?>">
                            <i class="fas fa-calendar-check me-2"></i>Interview Invites
                            <?php if ($stats['interviews'] > 0): ?>
                                <span class="badge bg-info ms-1"><?php echo $stats['interviews']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'offers' ? 'active' : ''; ?>" 
                           href="?tab=offers"
                           style="<?php echo $active_tab === 'offers' ? 'background: #10b981; color: white;' : 'color: #10b981; border: 1px solid #10b981;'; ?>">
                            <i class="fas fa-hand-holding-usd me-2"></i>Job Offers
                            <?php if ($stats['offers'] > 0): ?>
                                <span class="badge bg-success ms-1"><?php echo $stats['offers']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'history' ? 'active' : ''; ?>" 
                           href="?tab=history"
                           style="<?php echo $active_tab === 'history' ? 'background: #10b981; color: white;' : 'color: #10b981; border: 1px solid #10b981;'; ?>">
                            <i class="fas fa-history me-2"></i>Application History
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Applications List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="mb-0">
                            <?php
                            $tabLabels = [
                                'submitted' => '<i class="fas fa-file-alt me-2"></i>Submitted Applications',
                                'interviews' => '<i class="fas fa-calendar-check me-2"></i>Interview Invites',
                                'offers' => '<i class="fas fa-hand-holding-usd me-2"></i>Job Offers',
                                'history' => '<i class="fas fa-history me-2"></i>Application History'
                            ];
                            echo $tabLabels[$active_tab] ?? $tabLabels['submitted'];
                            ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="applicationsTabContent">
                        <?php if (empty($applications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">
                                    <?php 
                                    switch($active_tab) {
                                        case 'interviews':
                                            echo 'No Interview Invites';
                                            break;
                                        case 'offers':
                                            echo 'No Job Offers';
                                            break;
                                        default:
                                            echo 'No applications yet';
                                    }
                                    ?>
                                </h5>
                                <p class="text-muted">
                                    <?php 
                                    switch($active_tab) {
                                        case 'interviews':
                                            echo 'You don\'t have any interview invites yet.';
                                            break;
                                        case 'offers':
                                            echo 'You don\'t have any job offers yet.';
                                            break;
                                        default:
                                            echo 'Start applying to jobs to see your applications here.';
                                    }
                                    ?>
                                </p>
                                <?php if ($active_tab !== 'interviews' && $active_tab !== 'offers'): ?>
                                    <a href="jobs.php" class="btn" style="background: #10b981; border-color: #10b981; color: white;">
                                        <i class="fas fa-search me-1"></i>Browse All Jobs
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Job Title</th>
                                            <th>Company</th>
                                            <th>Location</th>
                                            <th>Applied Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $app): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($app['company_logo']): ?>
                                                            <img src="../<?php echo htmlspecialchars($app['company_logo']); ?>" 
                                                                 alt="Company Logo" class="me-2" 
                                                                 style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($app['title']); ?></strong>
                                                            <?php if ($app['salary_range']): ?>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($app['salary_range']); ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                                <td>
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($app['location']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($app['applied_date'])); ?>
                                                    <br><small class="text-muted"><?php echo date('g:i A', strtotime($app['applied_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusIcon = '';
                                                    $statusLabel = '';
                                                    $statusDescription = '';
                                                    switch($app['status']) {
                                                        case 'pending':
                                                            $statusClass = 'bg-warning text-dark';
                                                            $statusIcon = 'clock';
                                                            $statusLabel = 'Pending';
                                                            $statusDescription = 'Hindi pa nakita ng Employer';
                                                            break;
                                                        case 'reviewed':
                                                            $statusClass = 'bg-info text-white';
                                                            $statusIcon = 'eye';
                                                            $statusLabel = 'Under Review';
                                                            $statusDescription = 'Nakita na ng Employer';
                                                            break;
                                                        case 'accepted':
                                                            $statusClass = 'bg-success text-white';
                                                            $statusIcon = 'check-circle';
                                                            $statusLabel = 'Accepted';
                                                            $statusDescription = 'Congratulations! Approved na';
                                                            break;
                                                        case 'rejected':
                                                            $statusClass = 'bg-danger text-white';
                                                            $statusIcon = 'times-circle';
                                                            $statusLabel = 'Rejected';
                                                            $statusDescription = 'Hindi na-approve';
                                                            break;
                                                    }
                                                    ?>
                                                    <?php if ($active_tab === 'interviews' && isset($app['interview_date']) && $app['interview_date']): ?>
                                                        <?php 
                                                        // Determine if interview is upcoming, today, or past
                                                        $today = date('Y-m-d');
                                                        $interview_date = $app['interview_date'];
                                                        $is_today = ($interview_date === $today);
                                                        $is_past = ($interview_date < $today);
                                                        $is_interviewed = (isset($app['interview_status']) && $app['interview_status'] === 'interviewed');
                                                        
                                                        if ($is_interviewed) {
                                                            $badge_class = 'bg-success';
                                                            $badge_text = 'Interview Completed';
                                                            $badge_icon = 'check-circle';
                                                            $bg_color = '#e8f5e9';
                                                            $border_color = '#10b981';
                                                        } elseif ($is_today) {
                                                            $badge_class = 'bg-warning text-dark';
                                                            $badge_text = 'Interview TODAY!';
                                                            $badge_icon = 'exclamation-circle';
                                                            $bg_color = '#fff3cd';
                                                            $border_color = '#ffc107';
                                                        } elseif ($is_past) {
                                                            $badge_class = 'bg-info';
                                                            $badge_text = 'Waiting for Result';
                                                            $badge_icon = 'hourglass-half';
                                                            $bg_color = '#e3f2fd';
                                                            $border_color = '#2196f3';
                                                        } else {
                                                            $badge_class = 'bg-primary';
                                                            $badge_text = 'Interview Scheduled';
                                                            $badge_icon = 'calendar-check';
                                                            $bg_color = '#e8f4fc';
                                                            $border_color = '#0d6efd';
                                                        }
                                                        ?>
                                                        <!-- Interview Invites Tab - Show interview details prominently -->
                                                        <span class="badge <?php echo $badge_class; ?> mb-2" style="font-size: 0.9rem;">
                                                            <i class="fas fa-<?php echo $badge_icon; ?> me-1"></i><?php echo $badge_text; ?>
                                                        </span>
                                                        <div class="mt-1 p-2 rounded" style="background: <?php echo $bg_color; ?>; border-left: 3px solid <?php echo $border_color; ?>;">
                                                            <small class="d-block text-dark fw-bold">
                                                                <i class="fas fa-calendar me-1" style="color: <?php echo $border_color; ?>"></i>
                                                                <?php echo date('F j, Y', strtotime($app['interview_date'])); ?>
                                                                <?php if ($is_today): ?>
                                                                    <span class="badge bg-danger ms-1">TODAY</span>
                                                                <?php endif; ?>
                                                            </small>
                                                            <?php if (isset($app['interview_time']) && $app['interview_time']): ?>
                                                                <small class="d-block text-dark">
                                                                    <i class="fas fa-clock me-1" style="color: <?php echo $border_color; ?>"></i>
                                                                    <?php echo htmlspecialchars($app['interview_time']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                            <?php if (isset($app['interview_mode']) && $app['interview_mode']): ?>
                                                                <small class="d-block text-dark">
                                                                    <i class="fas fa-video me-1" style="color: <?php echo $border_color; ?>"></i>
                                                                    <?php echo htmlspecialchars($app['interview_mode']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                            <?php if (isset($app['interview_location']) && $app['interview_location']): ?>
                                                                <small class="d-block text-dark">
                                                                    <i class="fas fa-map-marker-alt me-1" style="color: <?php echo $border_color; ?>"></i>
                                                                    <?php echo htmlspecialchars($app['interview_location']); ?>
                                                                </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Other tabs - Show regular status with descriptive labels -->
                                                        <div class="status-container">
                                                            <span class="badge <?php echo $statusClass; ?> mb-1" style="font-size: 0.85rem;">
                                                                <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                                                <?php echo $statusLabel; ?>
                                                            </span>
                                                            <br><small class="text-muted fst-italic" style="font-size: 0.75rem;">
                                                                <?php echo $statusDescription; ?>
                                                            </small>
                                                        </div>
                                                        <?php if (isset($app['interview_date']) && $app['interview_date']): ?>
                                                            <div class="mt-2">
                                                                <small class="text-info">
                                                                    <i class="fas fa-calendar-check me-1"></i>
                                                                    Interview: <?php echo date('M j, Y', strtotime($app['interview_date'])); ?>
                                                                    <?php if (isset($app['interview_time']) && $app['interview_time']): ?>
                                                                        - <?php echo htmlspecialchars($app['interview_time']); ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (isset($app['offer_status']) && $app['offer_status']): ?>
                                                            <div class="mt-1">
                                                                <small class="text-success">
                                                                    <i class="fas fa-hand-holding-usd me-1"></i>
                                                                    Offer: <?php echo ucfirst($app['offer_status']); ?>
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="../job-details.php?id=<?php echo $app['job_id']; ?>" 
                                                           class="btn" style="border-color: #10b981; color: #10b981;" title="View Job Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="view-application.php?id=<?php echo $app['id']; ?>" 
                                                           class="btn btn-outline-info" title="View Application">
                                                            <i class="fas fa-file-alt"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- View Application Modal -->
                                            <div class="modal fade" id="viewApplicationModal<?php echo $app['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Application Details</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <h6>Job Information</h6>
                                                                    <p><strong>Title:</strong> <?php echo htmlspecialchars($app['title']); ?></p>
                                                                    <p><strong>Company:</strong> <?php echo htmlspecialchars($app['company_name']); ?></p>
                                                                    <p><strong>Location:</strong> <?php echo htmlspecialchars($app['location']); ?></p>
                                                                    <?php if ($app['salary_range']): ?>
                                                                        <p><strong>Salary:</strong> <?php echo htmlspecialchars($app['salary_range']); ?></p>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <h6>Application Details</h6>
                                                                    <p><strong>Applied:</strong> <?php echo date('M j, Y g:i A', strtotime($app['applied_date'])); ?></p>
                                                                    <p><strong>Status:</strong> 
                                                                        <span class="badge <?php echo $statusClass; ?>">
                                                                            <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                                                            <?php echo $statusLabel; ?>
                                                                        </span>
                                                                        <br><small class="text-muted fst-italic"><?php echo $statusDescription; ?></small>
                                                                    </p>
                                                                    <?php if ($app['reviewed_date']): ?>
                                                                        <p><strong>Last Updated:</strong> <?php echo date('M j, Y g:i A', strtotime($app['reviewed_date'])); ?></p>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($app['interview_date']) && $app['interview_date']): ?>
                                                                        <hr>
                                                                        <h6>Interview Information</h6>
                                                                        <p><strong>Interview Date:</strong> <?php echo date('M j, Y', strtotime($app['interview_date'])); ?></p>
                                                                        <?php if (isset($app['interview_time']) && $app['interview_time']): ?>
                                                                            <p><strong>Interview Time:</strong> <?php echo date('g:i A', strtotime($app['interview_time'])); ?></p>
                                                                        <?php endif; ?>
                                                                        <?php if (isset($app['interview_mode']) && $app['interview_mode']): ?>
                                                                            <p><strong>Mode:</strong> <?php echo ucfirst($app['interview_mode']); ?></p>
                                                                        <?php endif; ?>
                                                                        <?php if (isset($app['interview_location']) && $app['interview_location']): ?>
                                                                            <p><strong>Location:</strong> <?php echo htmlspecialchars($app['interview_location']); ?></p>
                                                                        <?php endif; ?>
                                                                        <?php if (isset($app['interview_result']) && $app['interview_result']): ?>
                                                                            <p><strong>Result:</strong> 
                                                                                <span class="badge <?php echo $app['interview_result'] === 'passed' ? 'bg-success' : ($app['interview_result'] === 'failed' ? 'bg-danger' : 'bg-warning'); ?>">
                                                                                    <?php echo ucfirst($app['interview_result']); ?>
                                                                                </span>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                    <?php if (isset($app['offer_status']) && $app['offer_status']): ?>
                                                                        <hr>
                                                                        <h6>Job Offer Information</h6>
                                                                        <p><strong>Offer Status:</strong> 
                                                                            <span class="badge <?php echo $app['offer_status'] === 'accepted' ? 'bg-success' : ($app['offer_status'] === 'rejected' ? 'bg-danger' : 'bg-info'); ?>">
                                                                                <?php echo ucfirst($app['offer_status']); ?>
                                                                            </span>
                                                                        </p>
                                                                        <?php if (isset($app['offer_salary']) && $app['offer_salary']): ?>
                                                                            <p><strong>Offered Salary:</strong> <?php echo htmlspecialchars($app['offer_salary']); ?></p>
                                                                        <?php endif; ?>
                                                                        <?php if (isset($app['offer_start_date']) && $app['offer_start_date']): ?>
                                                                            <p><strong>Start Date:</strong> <?php echo date('M j, Y', strtotime($app['offer_start_date'])); ?></p>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if ($app['cover_letter']): ?>
                                                                <hr>
                                                                <h6>Cover Letter</h6>
                                                                <div class="bg-light p-3 rounded">
                                                                    <?php echo nl2br(htmlspecialchars($app['cover_letter'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if ($app['resume']): ?>
                                                                <hr>
                                                                <h6>Resume</h6>
                                                                <a href="../<?php echo htmlspecialchars($app['resume']); ?>" 
                                                                   class="btn btn-outline-primary" target="_blank">
                                                                    <i class="fas fa-file-pdf me-1"></i>View Resume
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                            <a href="../job-details.php?id=<?php echo $app['job_id']; ?>" class="btn btn-success">
                                                                <i class="fas fa-eye me-1"></i>View Job Details
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        #applicationsTab .nav-link {
            transition: all 0.3s ease;
            margin: 0 2px;
            border-radius: 8px;
        }
        #applicationsTab .nav-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
        }
        #applicationsTab .nav-link.active {
            font-weight: 600;
        }
        
        /* Status Container Styling */
        .status-container {
            display: inline-block;
            text-align: left;
        }
        .status-container .badge {
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        /* Pending Status - Pulsing animation */
        .badge.bg-warning {
            animation: pulse-warning 2s infinite;
        }
        @keyframes pulse-warning {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            50% { box-shadow: 0 0 0 8px rgba(255, 193, 7, 0); }
        }
        
        /* Under Review Status - Subtle glow */
        .badge.bg-info {
            box-shadow: 0 2px 8px rgba(13, 202, 240, 0.3);
        }
        
        /* Accepted Status - Success glow */
        .badge.bg-success {
            box-shadow: 0 2px 8px rgba(25, 135, 84, 0.4);
        }
        
        /* Rejected Status */
        .badge.bg-danger {
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }
        
        /* Status description styling */
        .status-container small.text-muted {
            display: block;
            margin-top: 4px;
            line-height: 1.2;
        }
    </style>
</body>
</html>
