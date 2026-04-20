<?php
include '../config.php';
requireRole('employee');

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.*, u.email, u.status FROM employee_profiles ep 
                       JOIN users u ON ep.user_id = u.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $stmt = $pdo->prepare("INSERT INTO employee_profiles (user_id, employee_id) VALUES (?, ?)");
        $employeeId = 'EMP' . str_pad($_SESSION['user_id'], 6, '0', STR_PAD_LEFT);
        $stmt->execute([$_SESSION['user_id'], $employeeId]);
        redirect('profile.php');
    } else {
        session_destroy();
        $_SESSION = [];
        redirect('../login.php');
    }
}

// Dashboard stats (match employer layout)
$stats = [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_jobs sj 
                       JOIN employee_profiles ep ON sj.employee_id = ep.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['saved_jobs'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE ep.user_id = ? AND ja.status = 'accepted'");
$stmt->execute([$_SESSION['user_id']]);
$stats['accepted_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE ep.user_id = ? AND ja.status = 'rejected'");
$stmt->execute([$_SESSION['user_id']]);
$stats['rejected_applications'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE ep.user_id = ? AND ja.status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$stats['pending_applications'] = $stmt->fetchColumn();

// This week (Mon–Sun)
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE ep.user_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$_SESSION['user_id'], $week_start, $week_end]);
$stats['this_week_applications'] = $stmt->fetchColumn();

// This month
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE ep.user_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$_SESSION['user_id'], $month_start, $month_end]);
$stats['this_month_applications'] = $stmt->fetchColumn();

// Acceptance rate
$stats['acceptance_rate'] = $stats['total_applications'] > 0
    ? round(($stats['accepted_applications'] / $stats['total_applications']) * 100, 1)
    : 0;

// Avg applications per job (distinct jobs applied)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT ja.job_id) FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$distinct_jobs = $stmt->fetchColumn();
$stats['avg_per_job'] = $distinct_jobs > 0
    ? round($stats['total_applications'] / $distinct_jobs, 1)
    : 0;

// Daily applications (last 7 days, Sun–Sat for chart labels)
$daily_applications = [];
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                           JOIN employee_profiles ep ON ja.employee_id = ep.id 
                           WHERE ep.user_id = ? AND DATE(ja.applied_date) = ?");
    $stmt->execute([$_SESSION['user_id'], $day]);
    $daily_applications[] = (int) $stmt->fetchColumn();
}

// Monthly applications (last 6 months)
$monthly_applications = [];
for ($i = 5; $i >= 0; $i--) {
    $m_start = date('Y-m-01', strtotime("-$i months"));
    $m_end = date('Y-m-t', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                           JOIN employee_profiles ep ON ja.employee_id = ep.id 
                           WHERE ep.user_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?");
    $stmt->execute([$_SESSION['user_id'], $m_start, $m_end]);
    $monthly_applications[] = (int) $stmt->fetchColumn();
}

// Recent applications & recommended jobs (for optional bottom section)
$stmt = $pdo->prepare("SELECT ja.*, jp.title, jp.location, c.company_name 
                       FROM job_applications ja 
                       JOIN employee_profiles ep ON ja.employee_id = ep.id 
                       JOIN job_postings jp ON ja.job_id = jp.id 
                       JOIN companies c ON jp.company_id = c.id 
                       WHERE ep.user_id = ? 
                       ORDER BY ja.applied_date DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recent_applications = $stmt->fetchAll();

$stmt = $pdo->query("SELECT jp.*, c.company_name, c.company_logo 
                     FROM job_postings jp 
                     JOIN companies c ON jp.company_id = c.id 
                     WHERE jp.status = 'active' 
                     ORDER BY jp.posted_date DESC LIMIT 5");
$recommended_jobs = $stmt->fetchAll();

// Profile completion (for User Statistics)
$completed_fields = 0;
$total_fields = 10;
if (!empty($profile['first_name'])) $completed_fields++;
if (!empty($profile['last_name'])) $completed_fields++;
if (!empty($profile['sex'])) $completed_fields++;
if (!empty($profile['date_of_birth'])) $completed_fields++;
if (!empty($profile['contact_no'])) $completed_fields++;
if (!empty($profile['civil_status'])) $completed_fields++;
if (!empty($profile['highest_education'])) $completed_fields++;
if (!empty($profile['address'])) $completed_fields++;
if (!empty($profile['document1'])) $completed_fields++;
if (!empty($profile['document2'])) $completed_fields++;
$completion_percentage = ($total_fields > 0) ? ($completed_fields / $total_fields) * 100 : 0;

$stats['active_jobs'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'active'")->fetchColumn();

// System Overview Statistics
$system_stats = [];
$system_stats['total_active_jobs'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'active'")->fetchColumn();
$system_stats['total_active_companies'] = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'")->fetchColumn();
$system_stats['total_active_employees'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'")->fetchColumn();
$system_stats['total_active_employers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employer' AND status = 'active'")->fetchColumn();
$system_stats['total_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();

// User Statistics
$user_stats = [];
$user_stats['profile_completion'] = round($completion_percentage, 1);
$user_stats['total_applications'] = $stats['total_applications'];
$user_stats['saved_jobs'] = $stats['saved_jobs'];
$user_stats['acceptance_rate'] = $stats['acceptance_rate'];
$user_stats['active_jobs_available'] = $system_stats['total_active_jobs'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/modern.css" rel="stylesheet">
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employee-main-content">
        <!-- Hero Section -->
        <div class="hero-section">
            <div class="hero-content">
                <div class="row align-items-center g-4">
                    <div class="col-lg">
                        <p class="hero-eyebrow mb-1">Your dashboard</p>
                        <h1 class="hero-title mb-2">Welcome back, <?php echo htmlspecialchars($profile['first_name'] ?? $_SESSION['username']); ?></h1>
                        <p class="hero-lead mb-0">Ready for your next role? There are <strong><?php echo number_format($system_stats['total_active_jobs']); ?></strong> open jobs from <strong><?php echo number_format($system_stats['total_active_companies']); ?></strong> companies right now.</p>
                    </div>
                    <div class="col-lg-auto">
                        <div class="d-flex flex-wrap align-items-center gap-2 justify-content-lg-end">
                            <a href="jobs.php" class="btn btn-primary btn-lg px-4">
                                <i class="fas fa-search me-2"></i>Browse jobs
                            </a>
                            <?php if ($profile['status'] === 'pending'): ?>
                                <span class="hero-badge hero-badge--warning">
                                    <i class="fas fa-clock"></i>Profile pending
                                </span>
                            <?php else: ?>
                                <span class="hero-badge hero-badge--success">
                                    <i class="fas fa-check-circle"></i>Profile active
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-container">
        <!-- Platform Overview -->
        <div class="section-title">Platform Overview</div>
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo number_format($system_stats['total_active_jobs']); ?></div>
                        <div class="stat-card-label">Active Jobs</div>
                    </div>
                    <div class="stat-card-icon blue">
                        <i class="fas fa-briefcase"></i>
                    </div>
                </div>
                <div class="stat-card-meta">New opportunities available now</div>
            </div>
            <div class="stat-card success">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo number_format($system_stats['total_active_companies']); ?></div>
                        <div class="stat-card-label">Active Companies</div>
                    </div>
                    <div class="stat-card-icon green">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
                <div class="stat-card-meta">Hiring right now</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo number_format($system_stats['total_active_employees']); ?></div>
                        <div class="stat-card-label">Job Seekers</div>
                    </div>
                    <div class="stat-card-icon purple">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-card-meta">Active on platform</div>
            </div>
            <div class="stat-card info">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo number_format($system_stats['total_applications']); ?></div>
                        <div class="stat-card-label">Total Applications</div>
                    </div>
                    <div class="stat-card-icon cyan">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div class="stat-card-meta">Submitted across platform</div>
            </div>
        </div>

        <!-- Your Statistics -->
        <div class="section-title">Your Statistics</div>
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $user_stats['profile_completion']; ?>%</div>
                        <div class="stat-card-label">Profile Completion</div>
                    </div>
                    <div class="stat-card-icon blue">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-card-progress">
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $user_stats['profile_completion']; ?>%"></div>
                    </div>
                </div>
                <div class="stat-card-meta"><?php echo $completed_fields; ?> of <?php echo $total_fields; ?> fields completed</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo number_format($user_stats['total_applications']); ?></div>
                        <div class="stat-card-label">My Applications</div>
                    </div>
                    <div class="stat-card-icon purple">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                </div>
                <div class="stat-card-meta"><?php echo $stats['pending_applications']; ?> pending review</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo number_format($user_stats['saved_jobs']); ?></div>
                        <div class="stat-card-label">Saved Jobs</div>
                    </div>
                    <div class="stat-card-icon pink">
                        <i class="fas fa-heart"></i>
                    </div>
                </div>
                <div class="stat-card-meta"><a href="saved-jobs.php" style="color: var(--primary);">View all saved jobs →</a></div>
            </div>
            <div class="stat-card success">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-card-value"><?php echo $user_stats['acceptance_rate']; ?>%</div>
                        <div class="stat-card-label">Acceptance Rate</div>
                    </div>
                    <div class="stat-card-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card-meta"><?php echo $stats['accepted_applications']; ?> applications accepted</div>
            </div>
        </div>

        <!-- Analytics Charts -->
        <div class="section-title">Analytics</div>
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar me-2"></i>Daily Applications</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyApplicationsChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2"></i>Application Status</h5>
                        <a href="applications.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <canvas id="responseRateChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-filter me-2"></i>Application Funnel</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="funnelChart" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Monthly Activity</h5>
                        <a href="jobs.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyApplicationsChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions + Recent Applications -->
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="jobs.php" class="quick-action-btn">
                                <i class="fas fa-search"></i>
                                <span>Browse Jobs</span>
                            </a>
                            <a href="profile.php" class="quick-action-btn">
                                <i class="fas fa-user-edit"></i>
                                <span>Update Profile</span>
                            </a>
                            <a href="applications.php" class="quick-action-btn">
                                <i class="fas fa-file-alt"></i>
                                <span>My Applications</span>
                            </a>
                            <a href="saved-jobs.php" class="quick-action-btn">
                                <i class="fas fa-heart"></i>
                                <span>Saved Jobs</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-history me-2"></i>Recent Applications</h5>
                        <a href="applications.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_applications)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <h5>No applications yet</h5>
                                <p>Start applying to jobs to track your progress here</p>
                                <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Job Position</th>
                                            <th>Company</th>
                                            <th>Applied Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_applications as $app): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($app['title']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($app['applied_date'])); ?></td>
                                                <td>
                                                    <?php if ($app['status'] === 'pending'): ?>
                                                        <span class="badge badge-soft-warning">Pending</span>
                                                    <?php elseif ($app['status'] === 'accepted'): ?>
                                                        <span class="badge badge-soft-success">Accepted</span>
                                                    <?php elseif ($app['status'] === 'rejected'): ?>
                                                        <span class="badge badge-soft-danger">Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-soft-info"><?php echo ucfirst($app['status']); ?></span>
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
        </div>

        <?php if ($profile['status'] === 'pending' || empty($profile['first_name'])): ?>
        <div class="alert alert-warning mt-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-lg me-3"></i>
                <div class="flex-grow-1">
                    <strong>Complete Your Profile</strong>
                    <p class="mb-0">Your profile is incomplete. Complete it to get better job recommendations and increase your chances of getting hired.</p>
                </div>
                <a href="profile.php" class="btn btn-warning ms-3">Complete Profile</a>
            </div>
        </div>
        <?php endif; ?>
        </div><!-- /content-container -->
    </div><!-- /employee-main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const chartData = {
            dailyApplications: {
                labels: [<?php for ($i = 6; $i >= 0; $i--) { echo "'" . date('D', strtotime("-$i days")) . "'"; if ($i > 0) echo ','; } ?>],
                data: [<?php echo implode(',', $daily_applications); ?>]
            },
            responseRate: {
                pending: <?php echo $stats['pending_applications']; ?>,
                accepted: <?php echo $stats['accepted_applications']; ?>,
                rejected: <?php echo $stats['rejected_applications']; ?>
            },
            funnel: {
                total: <?php echo $stats['total_applications']; ?>,
                pending: <?php echo $stats['pending_applications']; ?>,
                accepted: <?php echo $stats['accepted_applications']; ?>
            },
            monthlyApplications: {
                labels: [<?php for ($i = 5; $i >= 0; $i--) { echo "'" . date('M', strtotime("-$i months")) . "'"; if ($i > 0) echo ','; } ?>],
                data: [<?php echo implode(',', $monthly_applications); ?>]
            }
        };

        function initCharts() {
            const grid = { color: 'rgba(0,0,0,0.08)' };
            const xGrid = { display: false };

            new Chart(document.getElementById('dailyApplicationsChart').getContext('2d'), {
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
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: grid }, x: { grid: xGrid } }
                }
            });

            new Chart(document.getElementById('responseRateChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Accepted', 'Rejected'],
                    datasets: [{
                        data: [chartData.responseRate.pending, chartData.responseRate.accepted, chartData.responseRate.rejected],
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

            new Chart(document.getElementById('funnelChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Total Applications', 'Pending Review', 'Accepted'],
                    datasets: [{
                        label: 'Funnel',
                        data: [chartData.funnel.total, chartData.funnel.pending, chartData.funnel.accepted],
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointBackgroundColor: '#8b5cf6'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: grid }, x: { grid: xGrid } }
                }
            });

            new Chart(document.getElementById('monthlyApplicationsChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: chartData.monthlyApplications.labels,
                    datasets: [{
                        label: 'My Applications',
                        data: chartData.monthlyApplications.data,
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6, 182, 212, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#06b6d4'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: grid }, x: { grid: xGrid } }
                }
            });
        }
        document.addEventListener('DOMContentLoaded', initCharts);
    </script>
</body>
</html>
