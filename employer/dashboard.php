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
</head>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
                <div class="page-header d-flex justify-content-between align-items-center">
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars($company['company_name']); ?>!</h1>
                        <p>Manage your job postings and applications</p>
                    </div>
                    <div class="text-end">
                        <a href="reports.php" class="btn btn-success me-2">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </a>
                        <?php if ($company['status'] === 'pending'): ?>
                            <span class="badge bg-warning text-dark fs-6">Company Pending Approval</span>
                        <?php else: ?>
                            <span class="badge bg-success text-white fs-6">Company Active</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Key Performance Indicators -->
                <div class="row g-3 mb-4">
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

                <!-- Performance Metrics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Daily Application Trends</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailyApplicationsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Application Response Rate</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="responseRateChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Hiring Funnel</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="hiringFunnelChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">Monthly Job Postings</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyJobsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Application Insights -->
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card dashboard-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Application Status Overview</h5>
                                <a href="applications.php" class="btn btn-sm btn-outline-success">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="p-3" style="background: rgba(245, 158, 11, 0.1); border-radius: 8px;">
                                            <h3 class="mb-0 text-warning"><?php echo $stats['pending_applications']; ?></h3>
                                            <small class="text-muted">Pending</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-3" style="background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                                            <h3 class="mb-0 text-success"><?php echo $stats['accepted_applications']; ?></h3>
                                            <small class="text-muted">Accepted</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="p-3" style="background: rgba(107, 114, 128, 0.1); border-radius: 8px;">
                                            <h3 class="mb-0 text-secondary"><?php echo $stats['rejected_applications']; ?></h3>
                                            <small class="text-muted">Rejected</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mb-2" style="height: 8px;">
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
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Top Performing Jobs</h5>
                                <a href="jobs.php" class="btn btn-sm btn-outline-success">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($top_jobs)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No jobs posted yet</p>
                                        <a href="post-job.php" class="btn btn-success">Post a Job</a>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top_jobs as $job): ?>
                                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                            <div class="flex-grow-1">
                                                <strong><?php echo htmlspecialchars($job['title']); ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($job['location']); ?> • 
                                                    <span class="badge bg-<?php echo $job['status'] === 'active' ? 'success' : ($job['status'] === 'pending' ? 'warning' : 'secondary'); ?>">
                                                        <?php echo ucfirst($job['status']); ?>
                                                    </span>
                                                </small>
                                            </div>
                                            <div class="text-end ms-3">
                                                <div class="mb-1">
                                                    <span class="badge bg-info text-white fs-6"><?php echo $job['application_count']; ?></span>
                                                </div>
                                                <small class="text-muted d-block">applications</small>
                                                <a href="job-applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-success mt-1">View</a>
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
        </div>
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

    <!-- AI Chat Widget -->
    <div id="aiChatWidget" class="ai-chat-widget">
        <!-- Chat Button -->
        <button id="aiChatToggle" class="ai-chat-toggle" title="Chat with AI Assistant">
            <i class="fas fa-robot"></i>
            <span class="ai-chat-badge" id="aiChatBadge" style="display: none;">1</span>
        </button>

        <!-- Chat Window -->
        <div id="aiChatWindow" class="ai-chat-window" style="display: none;">
            <div class="ai-chat-header">
                <div class="ai-chat-title">
                    <i class="fas fa-robot me-2"></i>
                    <span>AI Hiring Assistant</span>
                </div>
                <button id="aiChatClose" class="ai-chat-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="aiChatMessages" class="ai-chat-messages">
                <div class="ai-message ai-message-bot">
                    <div class="ai-message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ai-message-content">
                        <p>Hello! I'm your AI Hiring Assistant. I have access to your company's applicant data and can help you with:</p>
                        <ul>
                            <li>Analyzing applicants and their qualifications</li>
                            <li>Writing job descriptions</li>
                            <li>Drafting interview questions</li>
                            <li>Creating offer letters</li>
                            <li>Hiring tips and best practices</li>
                        </ul>
                        <p>Ask me anything about your applicants or hiring needs!</p>
                    </div>
                </div>
            </div>
            <div class="ai-chat-input-area">
                <div class="ai-chat-suggestions">
                    <button class="ai-suggestion-btn" data-message="Analyze my recent applicants">Analyze applicants</button>
                    <button class="ai-suggestion-btn" data-message="Who are my top candidates?">Top candidates</button>
                    <button class="ai-suggestion-btn" data-message="Help me write a job description">Write job description</button>
                    <button class="ai-suggestion-btn" data-message="Generate interview questions">Interview questions</button>
                    <button class="ai-suggestion-btn" data-message="Draft an offer letter">Draft offer letter</button>
                </div>
                <div class="ai-chat-input-wrapper">
                    <textarea id="aiChatInput" class="ai-chat-input" placeholder="Type your message..." rows="1"></textarea>
                    <button id="aiChatSend" class="ai-chat-send">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* AI Chat Widget Styles */
        .ai-chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
        }

        .ai-chat-toggle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4c1d95 0%, #2d1b69 100%);
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(76, 29, 149, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .ai-chat-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(76, 29, 149, 0.5);
        }

        .ai-chat-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 12px;
            font-weight: bold;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ai-chat-window {
            position: absolute;
            bottom: 80px;
            right: 0;
            width: 380px;
            height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: aiChatSlideIn 0.3s ease;
        }

        @keyframes aiChatSlideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ai-chat-header {
            background: linear-gradient(135deg, #4c1d95 0%, #2d1b69 100%);
            color: white;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-chat-title {
            display: flex;
            align-items: center;
            font-weight: 600;
            font-size: 16px;
        }

        .ai-chat-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .ai-chat-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .ai-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8fafc;
        }

        .ai-message {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .ai-message-user {
            flex-direction: row-reverse;
        }

        .ai-message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }

        .ai-message-bot .ai-message-avatar {
            background: linear-gradient(135deg, #4c1d95 0%, #2d1b69 100%);
            color: white;
        }

        .ai-message-user .ai-message-avatar {
            background: #3b82f6;
            color: white;
        }

        .ai-message-content {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.5;
        }

        .ai-message-bot .ai-message-content {
            background: white;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .ai-message-user .ai-message-content {
            background: #3b82f6;
            color: white;
        }

        .ai-message-content ul {
            margin: 8px 0;
            padding-left: 20px;
        }

        .ai-message-content li {
            margin: 4px 0;
        }

        .ai-message-content p {
            margin: 0 0 8px 0;
        }

        .ai-message-content p:last-child {
            margin-bottom: 0;
        }

        .ai-chat-input-area {
            background: white;
            border-top: 1px solid #e2e8f0;
            padding: 12px 16px;
        }

        .ai-chat-suggestions {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .ai-suggestion-btn {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 6px 12px;
            font-size: 12px;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .ai-suggestion-btn:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        .ai-chat-input-wrapper {
            display: flex;
            gap: 8px;
            align-items: flex-end;
        }

        .ai-chat-input {
            flex: 1;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 10px 16px;
            font-size: 14px;
            resize: none;
            max-height: 100px;
            font-family: inherit;
        }

        .ai-chat-input:focus {
            outline: none;
            border-color: #4c1d95;
            box-shadow: 0 0 0 3px rgba(76, 29, 149, 0.1);
        }

        .ai-chat-send {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4c1d95 0%, #2d1b69 100%);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .ai-chat-send:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(76, 29, 149, 0.3);
        }

        .ai-chat-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .ai-typing {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            align-items: center;
        }

        .ai-typing-dot {
            width: 8px;
            height: 8px;
            background: #94a3b8;
            border-radius: 50%;
            animation: aiTypingBounce 1.4s infinite ease-in-out both;
        }

        .ai-typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .ai-typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes aiTypingBounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .ai-chat-window {
                width: calc(100vw - 40px);
                right: -10px;
            }
        }
    </style>

    <script>
        // AI Chat Widget Functionality
        (function() {
            const chatToggle = document.getElementById('aiChatToggle');
            const chatWindow = document.getElementById('aiChatWindow');
            const chatClose = document.getElementById('aiChatClose');
            const chatInput = document.getElementById('aiChatInput');
            const chatSend = document.getElementById('aiChatSend');
            const chatMessages = document.getElementById('aiChatMessages');
            const suggestionBtns = document.querySelectorAll('.ai-suggestion-btn');

            let isOpen = false;

            // Toggle chat window
            chatToggle.addEventListener('click', () => {
                isOpen = !isOpen;
                chatWindow.style.display = isOpen ? 'flex' : 'none';
                if (isOpen) {
                    chatInput.focus();
                }
            });

            // Close chat window
            chatClose.addEventListener('click', () => {
                isOpen = false;
                chatWindow.style.display = 'none';
            });

            // Send message
            async function sendMessage(message) {
                if (!message.trim()) return;

                // Add user message
                addMessage(message, 'user');
                chatInput.value = '';
                chatInput.style.height = 'auto';

                // Show typing indicator
                showTyping();

                try {
                    // Call AI API
                    const response = await fetch('ai_chat.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            message: message,
                            context: 'employer_dashboard'
                        })
                    });

                    const data = await response.json();

                    // Remove typing indicator
                    hideTyping();

                    if (data.success) {
                        addMessage(data.response, 'bot');
                    } else {
                        console.error('AI Chat Error:', data);
                        addMessage('Sorry, I encountered an error: ' + (data.error || 'Unknown error'), 'bot');
                    }
                } catch (error) {
                    hideTyping();
                    console.error('AI Chat Exception:', error);
                    addMessage('Sorry, I\'m having trouble connecting. Please try again later.', 'bot');
                }
            }

            // Add message to chat
            function addMessage(text, sender) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `ai-message ai-message-${sender}`;
                messageDiv.innerHTML = `
                    <div class="ai-message-avatar">
                        <i class="fas fa-${sender === 'bot' ? 'robot' : 'user'}"></i>
                    </div>
                    <div class="ai-message-content">
                        <p>${escapeHtml(text)}</p>
                    </div>
                `;
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Show typing indicator
            function showTyping() {
                const typingDiv = document.createElement('div');
                typingDiv.id = 'aiTyping';
                typingDiv.className = 'ai-message ai-message-bot';
                typingDiv.innerHTML = `
                    <div class="ai-message-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ai-message-content ai-typing">
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                    </div>
                `;
                chatMessages.appendChild(typingDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Hide typing indicator
            function hideTyping() {
                const typing = document.getElementById('aiTyping');
                if (typing) typing.remove();
            }

            // Escape HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Send button click
            chatSend.addEventListener('click', () => {
                sendMessage(chatInput.value);
            });

            // Enter key to send
            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage(chatInput.value);
                }
            });

            // Auto-resize textarea
            chatInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            });

            // Suggestion buttons
            suggestionBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    sendMessage(btn.dataset.message);
                });
            });
        })();
    </script>
</body>
</html>
