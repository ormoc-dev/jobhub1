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
    <link href="css/modern.css" rel="stylesheet">
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employee-main-content">
        <!-- Hero Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1><i class="fas fa-file-alt me-2"></i>My Applications</h1>
                    <p>Track your job applications and their status</p>
                </div>
                <a href="jobs.php" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i>Browse More Jobs
                </a>
            </div>
        </div>

        <div class="content-container">
        <!-- Application Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $stats['total']; ?></div>
                        <div class="stat-card-label">Total Applications</div>
                    </div>
                    <div class="stat-card-icon blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card warning">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $stats['pending']; ?></div>
                        <div class="stat-card-label">Pending</div>
                    </div>
                    <div class="stat-card-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card info">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $stats['reviewed']; ?></div>
                        <div class="stat-card-label">Reviewed</div>
                    </div>
                    <div class="stat-card-icon cyan">
                        <i class="fas fa-eye"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card success">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $stats['accepted']; ?></div>
                        <div class="stat-card-label">Accepted</div>
                    </div>
                    <div class="stat-card-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card purple">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $stats['rejected']; ?></div>
                        <div class="stat-card-label">Rejected</div>
                    </div>
                    <div class="stat-card-icon purple">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="row mb-3">
            <div class="col-12">
                <ul class="nav nav-tabs" id="applicationsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'submitted' ? 'active' : ''; ?>" href="?tab=submitted">
                            <i class="fas fa-file-alt me-2"></i>Submitted
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'interviews' ? 'active' : ''; ?>" href="?tab=interviews">
                            <i class="fas fa-calendar-check me-2"></i>Interviews
                            <?php if ($stats['interviews'] > 0): ?>
                                <span class="badge bg-info ms-1"><?php echo $stats['interviews']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'offers' ? 'active' : ''; ?>" href="?tab=offers">
                            <i class="fas fa-hand-holding-usd me-2"></i>Offers
                            <?php if ($stats['offers'] > 0): ?>
                                <span class="badge bg-success ms-1"><?php echo $stats['offers']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $active_tab === 'history' ? 'active' : ''; ?>" href="?tab=history">
                            <i class="fas fa-history me-2"></i>History
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Applications List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5>
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
                    <div class="card-body p-0">
                        <div class="tab-content" id="applicationsTabContent">
                        <?php if (empty($applications)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h5>
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
                                <p>
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
                                    <a href="jobs.php" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Browse All Jobs
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
                                                    switch($app['status']) {
                                                        case 'pending':
                                                            $statusClass = 'status-pending';
                                                            $statusIcon = 'clock';
                                                            $statusLabel = 'Pending';
                                                            break;
                                                        case 'reviewed':
                                                            $statusClass = 'status-reviewed';
                                                            $statusIcon = 'eye';
                                                            $statusLabel = 'Under Review';
                                                            break;
                                                        case 'accepted':
                                                            $statusClass = 'status-accepted';
                                                            $statusIcon = 'check-circle';
                                                            $statusLabel = 'Accepted';
                                                            break;
                                                        case 'rejected':
                                                            $statusClass = 'status-rejected';
                                                            $statusIcon = 'times-circle';
                                                            $statusLabel = 'Rejected';
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
                                                            $badge_class = 'badge-soft-success';
                                                            $badge_text = 'Interview Completed';
                                                            $badge_icon = 'check-circle';
                                                        } elseif ($is_today) {
                                                            $badge_class = 'badge-soft-warning';
                                                            $badge_text = 'Interview TODAY!';
                                                            $badge_icon = 'exclamation-circle';
                                                        } elseif ($is_past) {
                                                            $badge_class = 'badge-soft-info';
                                                            $badge_text = 'Waiting for Result';
                                                            $badge_icon = 'hourglass-half';
                                                        } else {
                                                            $badge_class = 'badge-soft-primary';
                                                            $badge_text = 'Interview Scheduled';
                                                            $badge_icon = 'calendar-check';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $badge_class; ?> mb-2">
                                                            <i class="fas fa-<?php echo $badge_icon; ?> me-1"></i><?php echo $badge_text; ?>
                                                        </span>
                                                        <div class="interview-card">
                                                            <div class="interview-card-header">
                                                                <i class="fas fa-calendar"></i> Interview Details
                                                                <?php if ($is_today): ?>
                                                                    <span class="badge badge-soft-danger ms-2">TODAY</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="interview-card-detail">
                                                                <i class="fas fa-calendar-day"></i>
                                                                <?php echo date('F j, Y', strtotime($app['interview_date'])); ?>
                                                            </div>
                                                            <?php if (isset($app['interview_time']) && $app['interview_time']): ?>
                                                                <div class="interview-card-detail">
                                                                    <i class="fas fa-clock"></i>
                                                                    <?php echo htmlspecialchars($app['interview_time']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (isset($app['interview_mode']) && $app['interview_mode']): ?>
                                                                <div class="interview-card-detail">
                                                                    <i class="fas fa-video"></i>
                                                                    <?php echo htmlspecialchars($app['interview_mode']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (isset($app['interview_location']) && $app['interview_location']): ?>
                                                                <div class="interview-card-detail">
                                                                    <i class="fas fa-map-marker-alt"></i>
                                                                    <?php echo htmlspecialchars($app['interview_location']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="status-badge <?php echo $statusClass; ?>">
                                                            <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                                            <?php echo $statusLabel; ?>
                                                        </span>
                                                        <?php if (isset($app['interview_date']) && $app['interview_date']): ?>
                                                            <div class="mt-2">
                                                                <small style="color: var(--info);">
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
                                                                <small style="color: var(--success);">
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
                                                           class="btn btn-outline-primary" title="View Job Details">
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

        </div><!-- /content-container -->
    </div><!-- /employee-main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
