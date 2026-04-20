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
        /* Admin Dashboard Custom Styles — soft brand blues, not loud */
        .admin-main-content {
            padding: 2rem 2.5rem;
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
        }
        
        /* Section Title Styling */
        .dashboard-section-title {
            color: #475569;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.5px;
            margin-top: 2rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(37, 99, 235, 0.12);
        }
        .dashboard-section-title > i {
            color: #2563eb;
            opacity: 0.88;
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
        
        /* Stat KPI rows: wider gutters between cards + space before next section */
        .admin-main-content .stat-row.row {
            --bs-gutter-x: 3rem;
            --bs-gutter-y: 2.25rem;
            margin-bottom: 2.5rem;
        }

        /* Softer card hover inside admin main (overrides global .card) */
        .admin-main-content > .card:hover,
        .admin-main-content .row .card:hover {
            transform: translateY(-2px);
        }

        /* KPI stat tiles — light surface + cool accent edge */
        .admin-stat-card {
            background: linear-gradient(165deg, #ffffff 0%, #fafbff 100%);
            border: 1px solid #e2e8f0;
            border-left: 3px solid rgba(37, 99, 235, 0.55);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(30, 58, 138, 0.06);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, border-left-color 0.2s ease;
            overflow: hidden;
        }
        .admin-stat-card:hover {
            border-color: #cbd5e1;
            border-left-color: #2563eb;
            box-shadow: 0 12px 32px rgba(30, 58, 138, 0.09);
        }
        .admin-stat-card .card-body {
            padding: 1.35rem 1.4rem;
        }
        .admin-stat-label {
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.055em;
            text-transform: uppercase;
            color: #5b6b8a;
            margin-bottom: 0.35rem;
        }
        .admin-stat-value {
            font-size: 1.65rem;
            font-weight: 700;
            color: #1e293b;
            letter-spacing: -0.02em;
            line-height: 1.15;
            margin-bottom: 0;
        }
        .admin-stat-meta {
            font-size: 0.8125rem;
            color: #94a3b8;
            margin-bottom: 0;
            margin-top: 0.65rem;
        }
        .admin-stat-icon {
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(37, 99, 235, 0.1);
            color: #1d4ed8;
            font-size: 1.05rem;
        }
        
        /* Chart / content cards */
        .dashboard-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            overflow: hidden;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.07);
        }
        .dashboard-card .card-body {
            padding: 1.5rem;
        }
        .dashboard-card .card-header {
            background: linear-gradient(180deg, #f8fafc 0%, #f0f6ff 100%);
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
            padding: 0.9rem 1.25rem;
        }
        .dashboard-card .card-header h5 {
            font-weight: 600;
            font-size: 0.9375rem;
            color: #334155;
        }
        
        /* Chart Container */
        .chart-container {
            position: relative;
            height: 220px;
            padding: 0.5rem;
        }
        
        /* Quick Action Buttons */
        .quick-action-btn {
            padding: 0.55rem 1.15rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            background: #ffffff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            box-shadow: none;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        .quick-action-btn:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1e3a8a;
            transform: none;
            box-shadow: none;
        }
        .quick-action-btn i {
            color: #2563eb;
            opacity: 0.9;
        }
        
        /* Card header icons — muted primary */
        .admin-main-content .dashboard-card .card-header h5 .fas {
            color: #2563eb !important;
            opacity: 0.88;
        }

        /* Insight / platform metric cells */
        .admin-metric-cell {
            padding: 1rem 0.65rem;
            border-radius: 10px;
            background: linear-gradient(180deg, #fafbff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            height: 100%;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .admin-metric-cell:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
        }
        .admin-metric-cell__icon {
            color: #3b82f6;
            opacity: 0.85;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .admin-metric-cell__value {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            margin-bottom: 0.2rem;
        }
        .admin-metric-cell__value--sm {
            font-size: 1.2rem;
        }
        .admin-metric-cell__label {
            display: block;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Stacked segment bar (neutral slate tones) */
        .admin-progress-track {
            display: flex;
            width: 100%;
            height: 8px;
            border-radius: 999px;
            overflow: hidden;
            background: #e2e8f0;
        }
        .admin-progress-seg {
            height: 100%;
            min-width: 0;
            transition: width 0.5s ease;
        }
        .admin-progress-seg--pending { background: #93c5fd; }
        .admin-progress-seg--accepted { background: #3b82f6; }
        .admin-progress-seg--rejected { background: #cbd5e1; }

        /* Recent job rows */
        .admin-recent-row {
            padding: 1rem 1.15rem;
            margin-bottom: 0.65rem;
            border-radius: 10px;
            background: #fafbff;
            border: 1px solid #e2e8f0;
            transition: border-color 0.2s ease, background 0.2s ease;
        }
        .admin-recent-row:last-child {
            margin-bottom: 0;
        }
        .admin-recent-row:hover {
            border-color: rgba(37, 99, 235, 0.22);
            background: #f0f6ff;
        }
        .admin-recent-row__title {
            font-weight: 600;
            color: #0f172a;
        }
        .admin-badge-status {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* Shortcut tiles */
        .admin-shortcut-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.9rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.875rem;
            color: #334155 !important;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            text-decoration: none !important;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
        }
        .admin-shortcut-btn:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e3a8a !important;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
        }
        .admin-shortcut-btn i {
            color: #2563eb;
            opacity: 0.85;
            font-size: 1rem;
        }
        
        /* Progress Bar Enhancement (charts elsewhere) */
        .progress {
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar {
            transition: width 0.6s ease;
        }
        
        /* Badge Styling */
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            border-radius: 8px;
        }
        
        /* Welcome Section */
        .welcome-section {
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.75rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 45%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }
        .welcome-section h1 {
            font-weight: 700;
            color: #1e3a8a;
        }
        .welcome-section .text-muted {
            color: #64748b !important;
        }
        
        /* Card Number Styling */
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        /* Hover States for View All Buttons */
        .btn-view-all {
            border: 1px solid #cbd5e1;
            color: #475569;
            border-radius: 8px;
            padding: 0.4rem 1rem;
            font-weight: 500;
            font-size: 0.8125rem;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        .btn-view-all:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1e40af;
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
                <a href="reports.php" class="btn quick-action-btn">
                    <i class="fas fa-chart-bar me-2"></i>View Reports
                </a>
                <span class="badge rounded-pill fw-medium px-3 py-2 border" style="background: #f1f5f9; color: #475569; border-color: #e2e8f0 !important;">
                    <i class="fas fa-circle me-2 text-success" style="font-size: 7px; vertical-align: middle;"></i>System active
                </span>
            </div>
        </div>

        <!-- System Overview -->
        <h5 class="dashboard-section-title" style="margin-top: 0;"><i class="fas fa-globe me-2"></i>System Overview</h5>
        <div class="row g-4 stat-row">
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">Total users</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['total_users']); ?></h3>
                                <p class="admin-stat-meta"><?php echo number_format($stats['active_users']); ?> active users</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-users"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">Active companies</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['active_companies']); ?></h3>
                                <p class="admin-stat-meta"><?php echo number_format($stats['pending_companies']); ?> pending approval</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-building"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">Active jobs</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['active_jobs']); ?></h3>
                                <p class="admin-stat-meta"><?php echo number_format($stats['total_jobs']); ?> total jobs</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-briefcase"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">Total applications</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['total_applications']); ?></h3>
                                <p class="admin-stat-meta"><?php echo number_format($stats['pending_applications']); ?> pending</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Statistics -->
        <h5 class="dashboard-section-title"><i class="fas fa-user-circle me-2"></i>User Statistics</h5>
        <div class="row g-4 stat-row">
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">This week</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['this_week_users']); ?></h3>
                                <p class="admin-stat-meta">New registrations</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-calendar-week"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">Pending users</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['pending_users']); ?></h3>
                                <p class="admin-stat-meta">Awaiting approval</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-user-clock"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">Job seekers</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['total_employees']); ?></h3>
                                <p class="admin-stat-meta">Employee profiles</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-user-graduate"></i></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card admin-stat-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <p class="admin-stat-label">Employers</p>
                                <h3 class="admin-stat-value"><?php echo number_format($stats['total_employers']); ?></h3>
                                <p class="admin-stat-meta">Registered employers</p>
                            </div>
                            <div class="admin-stat-icon" aria-hidden="true"><i class="fas fa-user-tie"></i></div>
                        </div>
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
        <?php
        $appTotal = (int) $stats['total_applications'];
        $pctPending = $appTotal > 0 ? ($stats['pending_applications'] / $appTotal) * 100 : 0;
        $pctAccepted = $appTotal > 0 ? ($stats['accepted_applications'] / $appTotal) * 100 : 0;
        $pctRejected = $appTotal > 0 ? ($stats['rejected_applications'] / $appTotal) * 100 : 0;
        ?>
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Application status overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-4 g-3">
                            <div class="col-4">
                                <div class="admin-metric-cell">
                                    <i class="fas fa-clock admin-metric-cell__icon d-block"></i>
                                    <div class="admin-metric-cell__value"><?php echo number_format($stats['pending_applications']); ?></div>
                                    <small class="admin-metric-cell__label">Pending</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="admin-metric-cell">
                                    <i class="fas fa-check-circle admin-metric-cell__icon d-block"></i>
                                    <div class="admin-metric-cell__value"><?php echo number_format($stats['accepted_applications']); ?></div>
                                    <small class="admin-metric-cell__label">Accepted</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="admin-metric-cell">
                                    <i class="fas fa-times-circle admin-metric-cell__icon d-block"></i>
                                    <div class="admin-metric-cell__value"><?php echo number_format($stats['rejected_applications']); ?></div>
                                    <small class="admin-metric-cell__label">Rejected</small>
                                </div>
                            </div>
                        </div>
                        <div class="admin-progress-track mb-2" role="img" aria-label="Application status distribution">
                            <div class="admin-progress-seg admin-progress-seg--pending" style="width: <?php echo $pctPending; ?>%;"></div>
                            <div class="admin-progress-seg admin-progress-seg--accepted" style="width: <?php echo $pctAccepted; ?>%;"></div>
                            <div class="admin-progress-seg admin-progress-seg--rejected" style="width: <?php echo $pctRejected; ?>%;"></div>
                        </div>
                        <p class="text-muted small mb-0">Total: <?php echo number_format($appTotal); ?> applications · Acceptance rate: <?php echo htmlspecialchars($stats['acceptance_rate']); ?>%</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Platform statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center g-3">
                            <div class="col-4">
                                <div class="admin-metric-cell">
                                    <i class="fas fa-chart-line admin-metric-cell__icon d-block"></i>
                                    <div class="admin-metric-cell__value admin-metric-cell__value--sm"><?php echo htmlspecialchars($stats['avg_per_job']); ?></div>
                                    <small class="admin-metric-cell__label">Avg apps / job</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="admin-metric-cell">
                                    <i class="fas fa-percentage admin-metric-cell__icon d-block"></i>
                                    <div class="admin-metric-cell__value admin-metric-cell__value--sm"><?php echo htmlspecialchars($stats['acceptance_rate']); ?>%</div>
                                    <small class="admin-metric-cell__label">Accept rate</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="admin-metric-cell">
                                    <i class="fas fa-bolt admin-metric-cell__icon d-block"></i>
                                    <div class="admin-metric-cell__value admin-metric-cell__value--sm"><?php echo $stats['active_companies'] > 0 ? round($stats['active_jobs'] / $stats['active_companies'], 1) : 0; ?></div>
                                    <small class="admin-metric-cell__label">Jobs / company</small>
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
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Latest postings</h5>
                        <a href="jobs.php" class="btn-view-all">View all <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_jobs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-briefcase fa-3x text-muted mb-3" style="opacity: 0.5;"></i>
                                <p class="text-muted mb-0">No recent job postings</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_jobs as $job): ?>
                                <div class="admin-recent-row d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <div class="admin-stat-icon flex-shrink-0" style="width: 42px; height: 42px; font-size: 1rem;" aria-hidden="true">
                                            <i class="fas fa-briefcase"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="admin-recent-row__title text-truncate"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <small class="text-muted">
                                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($job['company_name']); ?>
                                                <span class="mx-2">·</span>
                                                <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($job['posted_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <span class="badge admin-badge-status mb-0"><i class="fas fa-check-circle me-1"></i>Active</span>
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
                        <h5 class="mb-0"><i class="fas fa-rocket me-2"></i>Shortcuts</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 g-md-4">
                            <div class="col-md-4 col-sm-6">
                                <a href="users.php" class="admin-shortcut-btn w-100"><i class="fas fa-users" aria-hidden="true"></i><span>Manage users</span></a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="companies.php" class="admin-shortcut-btn w-100"><i class="fas fa-building" aria-hidden="true"></i><span>Manage companies</span></a>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <a href="jobs.php" class="admin-shortcut-btn w-100"><i class="fas fa-briefcase" aria-hidden="true"></i><span>Manage jobs</span></a>
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
