<?php
include '../config.php';
requireRole('admin');

// Get dashboard statistics
$stats = [];
$stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['active_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$stats['pending_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
$stats['active_companies'] = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'")->fetchColumn();
$stats['pending_companies'] = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'pending'")->fetchColumn();
$stats['total_jobs'] = $pdo->query("SELECT COUNT(*) FROM job_postings")->fetchColumn();
$stats['active_jobs'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'active'")->fetchColumn();
$stats['total_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();
$stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM employee_profiles")->fetchColumn();
$stats['total_employers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employer'")->fetchColumn();

// Application status breakdown
$stats['pending_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'pending'")->fetchColumn();
$stats['accepted_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'accepted'")->fetchColumn();
$stats['rejected_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'rejected'")->fetchColumn();

// This week's data
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?");
$stmt->execute([$week_start, $week_end]);
$stats['this_week_users'] = $stmt->fetchColumn();

// This month's data
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE applied_date BETWEEN ? AND ?");
$stmt->execute([$month_start, $month_end]);
$stats['this_month_applications'] = $stmt->fetchColumn();

// Acceptance rate
$stats['acceptance_rate'] = $stats['total_applications'] > 0 
    ? round(($stats['accepted_applications'] / $stats['total_applications']) * 100, 1) 
    : 0;

// Avg applications per job
$stats['avg_per_job'] = $stats['total_jobs'] > 0 
    ? round($stats['total_applications'] / $stats['total_jobs'], 1) 
    : 0;

// Daily registrations (last 7 days)
$daily_users = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$day]);
    $daily_users[] = (int) $stmt->fetchColumn();
}

// Monthly applications (last 6 months)
$monthly_applications = [];
for ($i = 5; $i >= 0; $i--) {
    $m_start = date('Y-m-01', strtotime("-$i months"));
    $m_end = date('Y-m-t', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE applied_date BETWEEN ? AND ?");
    $stmt->execute([$m_start, $m_end]);
    $monthly_applications[] = (int) $stmt->fetchColumn();
}

// Monthly job postings (last 6 months)
$monthly_jobs = [];
for ($i = 5; $i >= 0; $i--) {
    $m_start = date('Y-m-01', strtotime("-$i months"));
    $m_end = date('Y-m-t', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE posted_date BETWEEN ? AND ?");
    $stmt->execute([$m_start, $m_end]);
    $monthly_jobs[] = (int) $stmt->fetchColumn();
}

// Recent activities
$recent_users = $pdo->query("SELECT u.*, ep.first_name, ep.last_name, c.company_name 
                            FROM users u 
                            LEFT JOIN employee_profiles ep ON u.id = ep.user_id 
                            LEFT JOIN companies c ON u.id = c.user_id 
                            WHERE u.status = 'active' 
                            ORDER BY u.created_at DESC LIMIT 5")->fetchAll();

$recent_jobs = $pdo->query("SELECT jp.*, c.company_name 
                           FROM job_postings jp 
                           JOIN companies c ON jp.company_id = c.id 
                           WHERE jp.status = 'active' 
                           ORDER BY jp.posted_date DESC LIMIT 5")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        /* Admin Dashboard Custom Styles */
        .admin-main-content {
            padding: 2rem 2.5rem;
        }
        
        /* Section Title Styling */
        .dashboard-section-title {
            color: #94a3b8;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            margin-top: 2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(148, 163, 184, 0.2);
        }
        
        /* Row Spacing */
        .row.g-4 {
            --bs-gutter-x: 1.75rem;
            --bs-gutter-y: 1.75rem;
        }
        
        /* Section Margin */
        .mb-section {
            margin-bottom: 2.5rem;
        }
        
        .stat-row {
            margin-bottom: 1rem;
        }
        
        /* Stat Cards Enhancement */
        .dashboard-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
        }
        .dashboard-card .card-body {
            padding: 1.75rem;
        }
        .dashboard-card .card-header {
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 1.5rem;
        }
        .dashboard-card .card-header h5 {
            font-weight: 600;
            font-size: 1rem;
        }
        
        /* Stat Card Icons */
        .stat-icon-wrapper {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 220px;
            padding: 0.5rem;
        }
        
        /* Quick Action Buttons */
        .quick-action-btn {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        /* Status Boxes */
        .status-box {
            padding: 1.25rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .status-box:hover {
            transform: scale(1.02);
        }
        .status-box h3, .status-box h4 {
            font-weight: 700;
        }
        
        /* Progress Bar Enhancement */
        .progress {
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar {
            transition: width 0.6s ease;
        }
        
        /* Recent Items Styling */
        .recent-item {
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.2s ease;
        }
        .recent-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-color: rgba(255, 255, 255, 0.1);
        }
        .recent-item:last-child {
            margin-bottom: 0;
        }
        
        /* Badge Styling */
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            border-radius: 8px;
        }
        
        /* Welcome Section */
        .welcome-section {
            padding: 1.5rem 0;
            margin-bottom: 1.5rem;
        }
        .welcome-section h1 {
            font-weight: 700;
            color: #fff;
        }
        
        /* Card Number Styling */
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        /* Hover States for View All Buttons */
        .btn-view-all {
            border: 1px solid #10b981;
            color: #10b981;
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-view-all:hover {
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main-content">
        <!-- Welcome Section -->
        <div class="welcome-section d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">Welcome back, Admin!</h1>
                <p class="text-muted mb-0">Manage your platform and monitor activities</p>
            </div>
            <div class="text-end d-flex align-items-center gap-3">
                <a href="reports.php" class="btn quick-action-btn" style="background: linear-gradient(135deg, #10b981, #059669); border: none; color: white;">
                    <i class="fas fa-chart-bar me-2"></i>View Reports
                </a>
                <span class="badge bg-success text-white fs-6 px-3 py-2">
                    <i class="fas fa-circle me-2" style="font-size: 8px;"></i>System Active
                </span>
            </div>
        </div>

        <!-- System Overview -->
        <h5 class="dashboard-section-title" style="margin-top: 0;"><i class="fas fa-globe me-2"></i>System Overview</h5>
        <div class="row g-4 stat-row">
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">Total Users</p>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                            </div>
                            <i class="fas fa-users fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50"><?php echo $stats['active_users']; ?> active users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">Active Companies</p>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['active_companies']); ?></h3>
                            </div>
                            <i class="fas fa-building fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50"><?php echo $stats['pending_companies']; ?> pending approval</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">Active Jobs</p>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['active_jobs']); ?></h3>
                            </div>
                            <i class="fas fa-briefcase fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50"><?php echo $stats['total_jobs']; ?> total jobs</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">Total Applications</p>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['total_applications']); ?></h3>
                            </div>
                            <i class="fas fa-file-alt fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50"><?php echo $stats['pending_applications']; ?> pending</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Statistics -->
        <h5 class="dashboard-section-title"><i class="fas fa-user-circle me-2"></i>User Statistics</h5>
        <div class="row g-4 stat-row">
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">This Week</p>
                                <h3 class="card-title mb-0"><?php echo $stats['this_week_users']; ?></h3>
                            </div>
                            <i class="fas fa-calendar-week fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50">New registrations</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">Pending Users</p>
                                <h3 class="card-title mb-0"><?php echo $stats['pending_users']; ?></h3>
                            </div>
                            <i class="fas fa-user-clock fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50">Awaiting approval</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">Job Seekers</p>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['total_employees']); ?></h3>
                            </div>
                            <i class="fas fa-user-graduate fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50">Employee profiles</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-card text-white" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="card-text mb-1">Employers</p>
                                <h3 class="card-title mb-0"><?php echo number_format($stats['total_employers']); ?></h3>
                            </div>
                            <i class="fas fa-user-tie fa-2x opacity-75"></i>
                        </div>
                        <small class="text-white-50">Registered employers</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job & Application Metrics - Charts -->
        <h5 class="dashboard-section-title"><i class="fas fa-chart-bar me-2"></i>Job & Application Metrics</h5>
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-plus me-2 text-primary"></i>Daily User Registrations</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyUsersChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-pie-chart me-2 text-warning"></i>Application Status</h5>
                        <a href="applications.php" class="btn-view-all">View All <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="card-body">
                        <canvas id="applicationStatusChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2" style="color: #8b5cf6;"></i>Monthly Applications</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyApplicationsChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-area me-2 text-info"></i>Monthly Job Postings</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyJobsChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Application Insights -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2 text-warning"></i>Application Status Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-4 g-3">
                            <div class="col-4">
                                <div class="status-box" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3);">
                                    <i class="fas fa-clock text-warning mb-2" style="font-size: 1.25rem;"></i>
                                    <h3 class="mb-1 text-warning"><?php echo $stats['pending_applications']; ?></h3>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="status-box" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3);">
                                    <i class="fas fa-check-circle text-success mb-2" style="font-size: 1.25rem;"></i>
                                    <h3 class="mb-1 text-success"><?php echo $stats['accepted_applications']; ?></h3>
                                    <small class="text-muted">Accepted</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="status-box" style="background: rgba(107, 114, 128, 0.15); border: 1px solid rgba(107, 114, 128, 0.3);">
                                    <i class="fas fa-times-circle text-secondary mb-2" style="font-size: 1.25rem;"></i>
                                    <h3 class="mb-1 text-secondary"><?php echo $stats['rejected_applications']; ?></h3>
                                    <small class="text-muted">Rejected</small>
                                </div>
                            </div>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $stats['total_applications'] > 0 ? ($stats['pending_applications'] / $stats['total_applications']) * 100 : 0; ?>%"></div>
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $stats['total_applications'] > 0 ? ($stats['accepted_applications'] / $stats['total_applications']) * 100 : 0; ?>%"></div>
                            <div class="progress-bar bg-secondary" role="progressbar" style="width: <?php echo $stats['total_applications'] > 0 ? ($stats['rejected_applications'] / $stats['total_applications']) * 100 : 0; ?>%"></div>
                        </div>
                        <p class="text-muted small mb-0">Total: <?php echo $stats['total_applications']; ?> applications | Acceptance Rate: <?php echo $stats['acceptance_rate']; ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2 text-success"></i>Platform Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-4">
                                <div class="status-box" style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3);">
                                    <i class="fas fa-chart-line text-primary mb-2" style="font-size: 1.5rem;"></i>
                                    <h4 class="mb-1 text-white"><?php echo $stats['avg_per_job']; ?></h4>
                                    <small class="text-muted">Avg Apps/Job</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="status-box" style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.3);">
                                    <i class="fas fa-percentage mb-2" style="font-size: 1.5rem; color: #a78bfa;"></i>
                                    <h4 class="mb-1 text-white"><?php echo $stats['acceptance_rate']; ?>%</h4>
                                    <small class="text-muted">Accept Rate</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="status-box" style="background: rgba(6, 182, 212, 0.15); border: 1px solid rgba(6, 182, 212, 0.3);">
                                    <i class="fas fa-bolt text-info mb-2" style="font-size: 1.5rem;"></i>
                                    <h4 class="mb-1 text-white"><?php echo $stats['active_companies'] > 0 ? round($stats['active_jobs'] / $stats['active_companies'], 1) : 0; ?></h4>
                                    <small class="text-muted">Jobs/Company</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Job Postings -->
        <h5 class="dashboard-section-title"><i class="fas fa-briefcase me-2"></i>Recent Job Postings</h5>
        <div class="row g-4 mb-section">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2 text-info"></i>Latest Postings</h5>
                        <a href="jobs.php" class="btn-view-all">View All <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_jobs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-briefcase fa-3x text-muted mb-3" style="opacity: 0.5;"></i>
                                <p class="text-muted mb-0">No recent job postings</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_jobs as $job): ?>
                                <div class="recent-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="stat-icon-wrapper" style="background: rgba(6, 182, 212, 0.15); width: 45px; height: 45px;">
                                            <i class="fas fa-briefcase text-info"></i>
                                        </div>
                                        <div>
                                            <strong class="text-white"><?php echo htmlspecialchars($job['title']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company_name']); ?> 
                                                <span class="mx-2">•</span>
                                                <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($job['posted_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <span class="badge text-white px-3" style="background: linear-gradient(135deg, #10b981, #059669);">
                                        <i class="fas fa-check-circle me-1"></i>Active
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h5 class="dashboard-section-title"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
        <div class="row g-4">
            <div class="col-12">
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-rocket me-2 text-warning"></i>Shortcuts</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <div class="col-md-3 col-sm-6">
                                <a href="users.php" class="btn quick-action-btn w-100 d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; color: white;">
                                    <i class="fas fa-users"></i>
                                    <span>Manage Users</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="companies.php" class="btn quick-action-btn w-100 d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #10b981, #059669); border: none; color: white;">
                                    <i class="fas fa-building"></i>
                                    <span>Manage Companies</span>
                                </a>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <a href="jobs.php" class="btn quick-action-btn w-100 d-flex align-items-center justify-content-center gap-2" style="background: linear-gradient(135deg, #f59e0b, #d97706); border: none; color: white;">
                                    <i class="fas fa-briefcase"></i>
                                    <span>Manage Jobs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart data from PHP
        const chartData = {
            dailyUsers: {
                labels: [<?php for ($i = 6; $i >= 0; $i--) { echo "'" . date('D', strtotime("-$i days")) . "'"; if ($i > 0) echo ','; } ?>],
                data: [<?php echo implode(',', $daily_users); ?>]
            },
            applicationStatus: {
                pending: <?php echo $stats['pending_applications']; ?>,
                accepted: <?php echo $stats['accepted_applications']; ?>,
                rejected: <?php echo $stats['rejected_applications']; ?>
            },
            monthlyApplications: {
                labels: [<?php for ($i = 5; $i >= 0; $i--) { echo "'" . date('M', strtotime("-$i months")) . "'"; if ($i > 0) echo ','; } ?>],
                data: [<?php echo implode(',', $monthly_applications); ?>]
            },
            monthlyJobs: {
                labels: [<?php for ($i = 5; $i >= 0; $i--) { echo "'" . date('M', strtotime("-$i months")) . "'"; if ($i > 0) echo ','; } ?>],
                data: [<?php echo implode(',', $monthly_jobs); ?>]
            }
        };

        // Daily Users Chart
        new Chart(document.getElementById('dailyUsersChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: chartData.dailyUsers.labels,
                datasets: [{
                    label: 'New Users',
                    data: chartData.dailyUsers.data,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: '#3b82f6',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Application Status Chart
        new Chart(document.getElementById('applicationStatusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Accepted', 'Rejected'],
                datasets: [{
                    data: [chartData.applicationStatus.pending, chartData.applicationStatus.accepted, chartData.applicationStatus.rejected],
                    backgroundColor: ['rgba(245, 158, 11, 0.8)', 'rgba(16, 185, 129, 0.8)', 'rgba(107, 114, 128, 0.8)'],
                    borderColor: ['#f59e0b', '#10b981', '#6b7280'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Monthly Applications Chart
        new Chart(document.getElementById('monthlyApplicationsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.monthlyApplications.labels,
                datasets: [{
                    label: 'Applications',
                    data: chartData.monthlyApplications.data,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#8b5cf6'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Monthly Jobs Chart
        new Chart(document.getElementById('monthlyJobsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: chartData.monthlyJobs.labels,
                datasets: [{
                    label: 'Jobs Posted',
                    data: chartData.monthlyJobs.data,
                    borderColor: '#06b6d4',
                    backgroundColor: 'rgba(6, 182, 212, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    </script>
</body>
</html>
