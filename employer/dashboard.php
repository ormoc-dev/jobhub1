<?php
include '../config.php';
requireRole('employer');

// Get company profile
$stmt = $pdo->prepare("SELECT c.*, u.status FROM companies c 
                       JOIN users u ON c.user_id = u.id 
                       WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$company = $stmt->fetch();

if (!$company) {
    redirect('company-profile.php');
}

// Get dashboard statistics
$stats = [];
$stats['total_jobs'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ?");
$stats['total_jobs']->execute([$company['id']]);
$stats['total_jobs'] = $stats['total_jobs']->fetchColumn();

$stats['active_jobs'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'active'");
$stats['active_jobs']->execute([$company['id']]);
$stats['active_jobs'] = $stats['active_jobs']->fetchColumn();

$stats['total_applications'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                             JOIN job_postings jp ON ja.job_id = jp.id 
                                             WHERE jp.company_id = ?");
$stats['total_applications']->execute([$company['id']]);
$stats['total_applications'] = $stats['total_applications']->fetchColumn();

$stats['pending_applications'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                               JOIN job_postings jp ON ja.job_id = jp.id 
                                               WHERE jp.company_id = ? AND ja.status = 'pending'");
$stats['pending_applications']->execute([$company['id']]);
$stats['pending_applications'] = $stats['pending_applications']->fetchColumn();

// Get additional statistics for charts
$stats['accepted_applications'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                                JOIN job_postings jp ON ja.job_id = jp.id 
                                                WHERE jp.company_id = ? AND ja.status = 'accepted'");
$stats['accepted_applications']->execute([$company['id']]);
$stats['accepted_applications'] = $stats['accepted_applications']->fetchColumn();

$stats['rejected_applications'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                                JOIN job_postings jp ON ja.job_id = jp.id 
                                                WHERE jp.company_id = ? AND ja.status = 'rejected'");
$stats['rejected_applications']->execute([$company['id']]);
$stats['rejected_applications'] = $stats['rejected_applications']->fetchColumn();

// Get monthly applications data (last 6 months)
$monthly_applications = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                          JOIN job_postings jp ON ja.job_id = jp.id 
                          WHERE jp.company_id = ? AND ja.applied_date BETWEEN ? AND ?");
    $stmt->execute([$company['id'], $month_start, $month_end]);
    $monthly_applications[] = $stmt->fetchColumn();
}

// Get monthly job postings data (last 6 months)
$monthly_jobs = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_postings 
                          WHERE company_id = ? AND posted_date BETWEEN ? AND ?");
    $stmt->execute([$company['id'], $month_start, $month_end]);
    $monthly_jobs[] = $stmt->fetchColumn();
}

// Get job status distribution
$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM job_postings WHERE company_id = ? GROUP BY status");
$stmt->execute([$company['id']]);
$job_status_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stats['pending_jobs'] = $job_status_data['pending'] ?? 0;
$stats['inactive_jobs'] = ($job_status_data['inactive'] ?? 0) + ($job_status_data['closed'] ?? 0);

$stats['active_candidates'] = $pdo->prepare("SELECT COUNT(*) FROM users u
                                            JOIN employee_profiles ep ON u.id = ep.user_id
                                            WHERE u.role = 'employee' AND u.status = 'active'");
$stats['active_candidates']->execute();
$stats['active_candidates'] = $stats['active_candidates']->fetchColumn();

// Get application status breakdown
$stmt = $pdo->prepare("SELECT ja.status, COUNT(*) as count FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? GROUP BY ja.status");
$stmt->execute([$company['id']]);
$application_status_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent applications
$stmt = $pdo->prepare("SELECT ja.*, jp.title, ep.first_name, ep.last_name, ep.employee_id 
                       FROM job_applications ja 
                       JOIN job_postings jp ON ja.job_id = jp.id 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE jp.company_id = ? 
                       ORDER BY ja.applied_date DESC LIMIT 5");
$stmt->execute([$company['id']]);
$recent_applications = $stmt->fetchAll();

// Get active jobs
$stmt = $pdo->prepare("SELECT jp.*, COUNT(ja.id) as application_count 
                       FROM job_postings jp 
                       LEFT JOIN job_applications ja ON jp.id = ja.job_id 
                       WHERE jp.company_id = ? AND jp.status = 'active' 
                       GROUP BY jp.id 
                       ORDER BY jp.posted_date DESC LIMIT 5");
$stmt->execute([$company['id']]);
$active_jobs = $stmt->fetchAll();

// Get new statistics for updated sections
// Average applications per job
$stats['avg_applications_per_job'] = $stats['total_jobs'] > 0 ? round($stats['total_applications'] / $stats['total_jobs'], 1) : 0;

// This week's applications
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN job_postings jp ON ja.job_id = jp.id 
                       WHERE jp.company_id = ? AND ja.applied_date BETWEEN ? AND ?");
$stmt->execute([$company['id'], $week_start, $week_end]);
$stats['this_week_applications'] = $stmt->fetchColumn();

// This month's applications
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN job_postings jp ON ja.job_id = jp.id 
                       WHERE jp.company_id = ? AND ja.applied_date BETWEEN ? AND ?");
$stmt->execute([$company['id'], $month_start, $month_end]);
$stats['this_month_applications'] = $stmt->fetchColumn();

// Acceptance rate
$stats['acceptance_rate'] = $stats['total_applications'] > 0 ? round(($stats['accepted_applications'] / $stats['total_applications']) * 100, 1) : 0;

// Get top performing jobs (by application count)
$stmt = $pdo->prepare("SELECT jp.*, COUNT(ja.id) as application_count 
                       FROM job_postings jp 
                       LEFT JOIN job_applications ja ON jp.id = ja.job_id 
                       WHERE jp.company_id = ? 
                       GROUP BY jp.id 
                       ORDER BY application_count DESC LIMIT 5");
$stmt->execute([$company['id']]);
$top_jobs = $stmt->fetchAll();

// Get application trends (last 7 days)
$daily_applications = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                          JOIN job_postings jp ON ja.job_id = jp.id 
                          WHERE jp.company_id = ? AND DATE(ja.applied_date) = ?");
    $stmt->execute([$company['id'], $day]);
    $daily_applications[] = $stmt->fetchColumn();
}

// Get job views/interactions if available
$stats['closed_jobs'] = ($job_status_data['closed'] ?? 0);
$stats['total_views'] = $stats['total_applications'] * 3; // Estimated views
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/minimal.css" rel="stylesheet">
    <style>
        .employer-dashboard .dashboard-hero {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .employer-dashboard .dashboard-eyebrow {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gray-500);
            margin: 0;
        }

        .employer-dashboard .dashboard-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0 0 0.35rem;
            line-height: 1.25;
        }

        .employer-dashboard .dashboard-subtitle {
            font-size: 0.9375rem;
            color: var(--gray-600);
            max-width: 36rem;
        }

        .employer-dashboard .dashboard-kpi-row {
            --bs-gutter-y: 0.75rem;
        }

        .employer-dashboard .dashboard-kpi-row .stat-card {
            height: 100%;
        }

        .employer-dashboard .dashboard-section-label {
            font-size: 0.8125rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--gray-500);
            margin-bottom: 0.75rem;
        }

        .employer-dashboard .dashboard-chart-card .card-header h5 {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--gray-800);
        }

        .employer-dashboard .dashboard-chart-card .card-body {
            position: relative;
            min-height: 240px;
        }

        .employer-dashboard .dashboard-chart-card canvas {
            max-height: 260px;
        }

        .employer-dashboard .dashboard-stat-chip {
            border-radius: 10px;
            padding: 0.85rem 0.5rem;
            border: 1px solid var(--border-color);
            background: var(--gray-50);
        }

        .employer-dashboard .dashboard-stat-chip h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .employer-dashboard .dashboard-stat-chip small {
            color: var(--gray-500);
            font-weight: 500;
        }

        .employer-dashboard .progress {
            height: 10px !important;
            border-radius: 999px;
            background: var(--gray-100);
            overflow: hidden;
        }

        .employer-dashboard .progress-bar {
            box-shadow: none;
        }

        .employer-dashboard .dashboard-job-row {
            padding: 0.75rem 0;
            border-color: var(--border-color) !important;
        }

        .employer-dashboard .dashboard-job-row:last-child {
            border-bottom: none !important;
        }

        .employer-dashboard .badge-status-pill {
            font-weight: 600;
        }
    </style>
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content employer-dashboard">
        <div class="page-header dashboard-hero d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
            <div class="min-w-0">
                <p class="dashboard-eyebrow">Employer dashboard</p>
                <h1 class="dashboard-title">Welcome back, <?php echo htmlspecialchars($company['company_name']); ?>!</h1>
                <p class="dashboard-subtitle mb-0">Manage your job postings and applications.</p>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                <a href="reports.php" class="btn btn-primary">
                    <i class="fas fa-chart-bar me-2"></i>View Reports
                </a>
                <?php if ($company['status'] === 'pending'): ?>
                    <span class="badge rounded-pill bg-warning bg-opacity-25 text-dark border border-warning">Pending approval</span>
                <?php else: ?>
                    <span class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success border-opacity-25">Company active</span>
                <?php endif; ?>
            </div>
        </div>

                <!-- Key Performance Indicators -->
                <div class="row g-3 mb-4 dashboard-kpi-row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon primary me-3">
                                    <i class="fas fa-calendar-week"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?php echo $stats['this_week_applications']; ?></div>
                                    <div class="stat-label">This Week</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon info me-3">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?php echo $stats['this_month_applications']; ?></div>
                                    <div class="stat-label">This Month</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon success me-3">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?php echo $stats['acceptance_rate']; ?>%</div>
                                    <div class="stat-label">Acceptance Rate</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon warning me-3">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div>
                                    <div class="stat-value"><?php echo $stats['avg_applications_per_job']; ?></div>
                                    <div class="stat-label">Avg per Job</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="dashboard-section-label">Activity &amp; trends</p>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card dashboard-chart-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-column me-2 text-primary opacity-75"></i>Daily application trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyApplicationsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card dashboard-chart-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-primary opacity-75"></i>Application response mix</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="responseRateChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card dashboard-chart-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-filter me-2 text-primary opacity-75"></i>Hiring funnel</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="hiringFunnelChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card dashboard-chart-card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar me-2 text-primary opacity-75"></i>Monthly job postings</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyJobsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <p class="dashboard-section-label">Pipeline &amp; jobs</p>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0">Application status</h5>
                                <a href="applications.php" class="btn btn-sm btn-outline-primary">View all</a>
                            </div>
                            <div class="card-body">
                                <div class="row text-center g-2 mb-3">
                                    <div class="col-4">
                                        <div class="dashboard-stat-chip">
                                            <h3 class="mb-0"><?php echo $stats['pending_applications']; ?></h3>
                                            <small>Pending</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="dashboard-stat-chip">
                                            <h3 class="mb-0"><?php echo $stats['accepted_applications']; ?></h3>
                                            <small>Accepted</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="dashboard-stat-chip">
                                            <h3 class="mb-0"><?php echo $stats['rejected_applications']; ?></h3>
                                            <small>Rejected</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mb-2">
                                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $stats['total_applications'] > 0 ? ($stats['pending_applications'] / $stats['total_applications']) * 100 : 0; ?>%"></div>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $stats['total_applications'] > 0 ? ($stats['accepted_applications'] / $stats['total_applications']) * 100 : 0; ?>%"></div>
                                    <div class="progress-bar bg-secondary" role="progressbar" style="width: <?php echo $stats['total_applications'] > 0 ? ($stats['rejected_applications'] / $stats['total_applications']) * 100 : 0; ?>%"></div>
                                </div>
                                <p class="text-muted small mb-0">Total: <?php echo $stats['total_applications']; ?> applications</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h5 class="mb-0">Top performing jobs</h5>
                                <a href="jobs.php" class="btn btn-sm btn-outline-primary">View all</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_jobs)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No jobs posted yet</p>
                                        <a href="post-job.php" class="btn btn-primary">Post a job</a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top_jobs as $job):
                                        $jobPill = $job['status'] === 'active' ? 'success' : ($job['status'] === 'pending' ? 'warning' : 'secondary');
                                        ?>
                                        <div class="d-flex justify-content-between align-items-center dashboard-job-row border-bottom">
                                            <div class="flex-grow-1 min-w-0">
                                                <strong><?php echo htmlspecialchars($job['title']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($job['location']); ?> •
                                                    <span class="badge rounded-pill badge-status-pill bg-<?php echo $jobPill; ?> bg-opacity-10 text-<?php echo $jobPill; ?> border border-<?php echo $jobPill; ?> border-opacity-25">
                                                        <?php echo ucfirst($job['status']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                            <div class="text-end ms-3 flex-shrink-0">
                                                <div class="mb-1">
                                                    <span class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?php echo $job['application_count']; ?></span>
                                                </div>
                                                <small class="text-muted d-block">Applications</small>
                                                <a href="job-applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-primary mt-1">Open</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Status -->
                <?php if ($company['status'] === 'pending'): ?>
                <div class="row g-4 mt-4">
                    <div class="col-12">
                        <div class="alert alert-warning">
                            <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Account Pending Approval</h5>
                            <p class="mb-3">Your company account is currently pending admin approval. Once approved, you'll be able to post jobs and receive applications.</p>
                            <p class="mb-0">In the meantime, you can complete your company profile and prepare job postings.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart data from PHP
        const chartData = {
            dailyApplications: {
                labels: [<?php 
                    for ($i = 6; $i >= 0; $i--) {
                        echo "'" . date('D', strtotime("-$i days")) . "'";
                        if ($i > 0) echo ',';
                    }
                ?>],
                data: [<?php echo implode(',', $daily_applications); ?>]
            },
            responseRate: {
                pending: <?php echo $stats['pending_applications']; ?>,
                accepted: <?php echo $stats['accepted_applications']; ?>,
                rejected: <?php echo $stats['rejected_applications']; ?>
            },
            hiringFunnel: {
                total: <?php echo $stats['total_applications']; ?>,
                pending: <?php echo $stats['pending_applications']; ?>,
                accepted: <?php echo $stats['accepted_applications']; ?>
            },
            monthlyJobs: {
                labels: [<?php 
                    for ($i = 5; $i >= 0; $i--) {
                        echo "'" . date('M', strtotime("-$i months")) . "'";
                        if ($i > 0) echo ',';
                    }
                ?>],
                data: [<?php echo implode(',', $monthly_jobs); ?>]
            }
        };

        // Daily Applications Chart
        const dailyApplicationsCtx = document.getElementById('dailyApplicationsChart').getContext('2d');
        new Chart(dailyApplicationsCtx, {
            type: 'bar',
            data: {
                labels: chartData.dailyApplications.labels,
                datasets: [{
                    label: 'Applications',
                    data: chartData.dailyApplications.data,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: '#3b82f6',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Response Rate Chart
        const responseRateCtx = document.getElementById('responseRateChart').getContext('2d');
        new Chart(responseRateCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Accepted', 'Rejected'],
                datasets: [{
                    data: [chartData.responseRate.pending, chartData.responseRate.accepted, chartData.responseRate.rejected],
                    backgroundColor: [
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ],
                    borderColor: [
                        '#f59e0b',
                        '#10b981',
                        '#6b7280'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Hiring Funnel Chart
        const hiringFunnelCtx = document.getElementById('hiringFunnelChart').getContext('2d');
        new Chart(hiringFunnelCtx, {
            type: 'line',
            data: {
                labels: ['Total Applications', 'Pending Review', 'Accepted'],
                datasets: [{
                    label: 'Hiring Funnel',
                    data: [chartData.hiringFunnel.total, chartData.hiringFunnel.pending, chartData.hiringFunnel.accepted],
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35,
                    pointRadius: 5,
                    pointBackgroundColor: '#2563eb'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Monthly Job Postings Chart
        const monthlyJobsCtx = document.getElementById('monthlyJobsChart').getContext('2d');
        new Chart(monthlyJobsCtx, {
            type: 'line',
            data: {
                labels: chartData.monthlyJobs.labels,
                datasets: [{
                    label: 'Jobs Posted',
                    data: chartData.monthlyJobs.data,
                    borderColor: '#475569',
                    backgroundColor: 'rgba(71, 85, 105, 0.08)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
