<?php
include '../config.php';
requireRole('admin');

// Get statistics
$stats = [];

// Application Statistics
$stats['total_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();
$stats['pending_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'pending'")->fetchColumn();
$stats['reviewed_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'reviewed'")->fetchColumn();
$stats['accepted_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'accepted'")->fetchColumn();
$stats['rejected_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'rejected'")->fetchColumn();

// Interview Statistics
$stats['scheduled_interviews'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE interview_date IS NOT NULL AND interview_result IS NULL")->fetchColumn();
$stats['completed_interviews'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE interview_status = 'interviewed'")->fetchColumn();
$stats['passed_interviews'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE interview_result = 'passed'")->fetchColumn();
$stats['failed_interviews'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE interview_result = 'failed'")->fetchColumn();
$stats['pending_interviews'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE interview_date IS NOT NULL AND interview_result = 'pending'")->fetchColumn();

// Hiring/Offer Statistics
$stats['offers_sent'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE offer_sent = 1")->fetchColumn();
$stats['offers_accepted'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE offer_status = 'accepted'")->fetchColumn();
$stats['offers_rejected'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE offer_status = 'rejected'")->fetchColumn();
$stats['offers_pending'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE offer_status = 'pending'")->fetchColumn();

// Today's interviews
$today = date('Y-m-d');
$stats['today_interviews'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE interview_date = '$today'")->fetchColumn();

// This week's applications
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE applied_date BETWEEN ? AND ?");
$stmt->execute([$week_start, $week_end]);
$stats['this_week_applications'] = $stmt->fetchColumn();

// Calculate rates
$stats['acceptance_rate'] = $stats['total_applications'] > 0 
    ? round(($stats['accepted_applications'] / $stats['total_applications']) * 100, 1) 
    : 0;

$stats['interview_pass_rate'] = ($stats['passed_interviews'] + $stats['failed_interviews']) > 0 
    ? round(($stats['passed_interviews'] / ($stats['passed_interviews'] + $stats['failed_interviews'])) * 100, 1) 
    : 0;

$stats['offer_acceptance_rate'] = $stats['offers_sent'] > 0 
    ? round(($stats['offers_accepted'] / $stats['offers_sent']) * 100, 1) 
    : 0;

// Filters
$tab = $_GET['tab'] ?? 'monitoring';
$company_filter = isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0;
$status_filter = $_GET['status'] ?? 'all';
$interview_filter = $_GET['interview'] ?? 'all';
$offer_filter = $_GET['offer'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build base query
$baseQuery = "SELECT ja.*, jp.title as job_title, jp.location, 
               c.company_name, c.id as company_id, c.company_logo,
               ep.first_name, ep.last_name, ep.profile_picture, u.email, ep.contact_no
        FROM job_applications ja
        JOIN job_postings jp ON ja.job_id = jp.id
        JOIN companies c ON jp.company_id = c.id
        JOIN employee_profiles ep ON ja.employee_id = ep.id
        JOIN users u ON ep.user_id = u.id";

$whereClause = " WHERE 1=1";
$params = [];

if ($company_filter > 0) {
    $whereClause .= " AND jp.company_id = ?";
    $params[] = $company_filter;
}

if (!empty($search)) {
    $whereClause .= " AND (ep.first_name LIKE ? OR ep.last_name LIKE ? OR u.email LIKE ? OR jp.title LIKE ? OR c.company_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Tab-specific filters
if ($tab === 'monitoring') {
    if ($status_filter !== 'all') {
        $whereClause .= " AND ja.status = ?";
        $params[] = $status_filter;
    }
    $orderBy = " ORDER BY ja.applied_date DESC";
} elseif ($tab === 'interviews') {
    if ($interview_filter === 'scheduled') {
        $whereClause .= " AND ja.interview_date IS NOT NULL AND ja.interview_result IS NULL";
    } elseif ($interview_filter === 'completed') {
        $whereClause .= " AND ja.interview_status = 'interviewed'";
    } elseif ($interview_filter === 'passed') {
        $whereClause .= " AND ja.interview_result = 'passed'";
    } elseif ($interview_filter === 'failed') {
        $whereClause .= " AND ja.interview_result = 'failed'";
    } elseif ($interview_filter === 'today') {
        $whereClause .= " AND ja.interview_date = CURDATE()";
    } elseif ($interview_filter === 'upcoming') {
        $whereClause .= " AND ja.interview_date >= CURDATE() AND ja.interview_result IS NULL";
    }
    $orderBy = " ORDER BY ja.interview_date ASC, ja.interview_time ASC";
} else { // outcomes
    if ($offer_filter === 'sent') {
        $whereClause .= " AND ja.offer_sent = 1";
    } elseif ($offer_filter === 'accepted') {
        $whereClause .= " AND ja.offer_status = 'accepted'";
    } elseif ($offer_filter === 'rejected') {
        $whereClause .= " AND ja.offer_status = 'rejected'";
    } elseif ($offer_filter === 'pending') {
        $whereClause .= " AND ja.offer_status = 'pending'";
    } elseif ($offer_filter === 'hired') {
        $whereClause .= " AND ja.offer_status = 'accepted' AND ja.status = 'accepted'";
    }
    $orderBy = " ORDER BY ja.offer_sent_date DESC, ja.applied_date DESC";
}

$sql = $baseQuery . $whereClause . $orderBy . " LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get companies for filter
$companies = $pdo->query("SELECT id, company_name FROM companies WHERE status = 'active' ORDER BY company_name")->fetchAll();

// Monthly hiring trends (last 6 months)
$monthly_applications = [];
$monthly_hired = [];
for ($i = 5; $i >= 0; $i--) {
    $m_start = date('Y-m-01', strtotime("-$i months"));
    $m_end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE applied_date BETWEEN ? AND ?");
    $stmt->execute([$m_start, $m_end]);
    $monthly_applications[] = (int) $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE offer_status = 'accepted' AND applied_date BETWEEN ? AND ?");
    $stmt->execute([$m_start, $m_end]);
    $monthly_hired[] = (int) $stmt->fetchColumn();
}

// Recent activities
$recent_interviews = $pdo->query("
    SELECT ja.*, jp.title as job_title, c.company_name, ep.first_name, ep.last_name
    FROM job_applications ja
    JOIN job_postings jp ON ja.job_id = jp.id
    JOIN companies c ON jp.company_id = c.id
    JOIN employee_profiles ep ON ja.employee_id = ep.id
    WHERE ja.interview_date >= CURDATE()
    ORDER BY ja.interview_date ASC, ja.interview_time ASC
    LIMIT 5
")->fetchAll();

$recent_offers = $pdo->query("
    SELECT ja.*, jp.title as job_title, c.company_name, ep.first_name, ep.last_name
    FROM job_applications ja
    JOIN job_postings jp ON ja.job_id = jp.id
    JOIN companies c ON jp.company_id = c.id
    JOIN employee_profiles ep ON ja.employee_id = ep.id
    WHERE ja.offer_sent = 1
    ORDER BY ja.offer_sent_date DESC
    LIMIT 5
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications & Hiring - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        /* Hiring Dashboard Custom Styles */
        .admin-main-content {
            padding: 2rem 2.5rem;
        }

        /* Tab Navigation */
        .hiring-tabs {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 16px;
            padding: 6px;
            display: inline-flex;
            gap: 4px;
            margin-bottom: 2rem;
        }

        .hiring-tab {
            padding: 12px 24px;
            border-radius: 12px;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hiring-tab:hover {
            color: #e2e8f0;
            background: rgba(255, 255, 255, 0.05);
        }

        .hiring-tab.active {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        }

        .hiring-tab .badge {
            font-size: 0.7rem;
            padding: 4px 8px;
        }

        /* Section Titles */
        .section-title {
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.9rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(148, 163, 184, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            font-size: 1.1rem;
        }

        /* Stat Cards */
        .stat-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
        }

        .stat-card .card-body {
            padding: 1.5rem;
        }

        .stat-number {
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 0.25rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.2);
        }

        /* Dashboard Card */
        .dashboard-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            background: #1e293b;
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
        }

        .dashboard-card .card-header {
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1rem 1.5rem;
        }

        .dashboard-card .card-header h5 {
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            color: #e2e8f0;
        }

        .dashboard-card .card-body {
            padding: 1.5rem;
        }

        /* Filter Card */
        .filter-card {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .filter-card .form-select,
        .filter-card .form-control {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
            border-radius: 10px;
        }

        .filter-card .form-select:focus,
        .filter-card .form-control:focus {
            background: rgba(15, 23, 42, 0.8);
            border-color: #6366f1;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
            color: #e2e8f0;
        }

        .filter-card .form-label {
            color: #94a3b8;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Table Styles */
        .data-table {
            background: #1e293b;
            border-radius: 16px;
            overflow: hidden;
        }

        .data-table .table {
            margin-bottom: 0;
            color: #e2e8f0;
        }

        .data-table .table thead th {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 1.25rem;
            border: none;
        }

        .data-table .table tbody td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        .data-table .table tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .data-table .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Applicant Info */
        .applicant-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .applicant-avatar {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }

        .applicant-avatar-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .applicant-name {
            font-weight: 600;
            color: #f1f5f9;
        }

        .applicant-email {
            font-size: 0.8rem;
            color: #64748b;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-reviewed {
            background: rgba(6, 182, 212, 0.15);
            color: #22d3ee;
            border: 1px solid rgba(6, 182, 212, 0.3);
        }

        .status-accepted {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-passed {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-failed {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-scheduled {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        /* Progress Circles */
        .progress-ring {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }

        .progress-ring__circle {
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        .progress-ring__background {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 8;
        }

        .progress-ring__progress {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s ease;
        }

        .progress-ring__text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .progress-ring__percentage {
            font-size: 1.75rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .progress-ring__label {
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
        }

        /* Mini Stat Box */
        .mini-stat {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .mini-stat:hover {
            background: rgba(255, 255, 255, 0.06);
            transform: translateY(-2px);
        }

        .mini-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #f1f5f9;
        }

        .mini-stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
        }

        /* Activity Item */
        .activity-item {
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        /* Interview Time Badge */
        .interview-time {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 250px;
            padding: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: #475569;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #64748b;
            font-size: 0.9rem;
        }

        /* Button Styles */
        .btn-filter {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border: none;
            color: white;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
        }

        .btn-filter:hover {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            transform: translateY(-2px);
        }

        /* Row spacing */
        .row.g-4 {
            --bs-gutter-x: 1.5rem;
            --bs-gutter-y: 1.5rem;
        }

        /* Quick Stats Row */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }
    </style>
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 text-white mb-1">
                    <i class="fas fa-users-cog me-2" style="color: #a78bfa;"></i>Applications & Hiring
                </h1>
                <p class="text-muted mb-0">Monitor applications, track interviews, and manage hiring outcomes</p>
            </div>
            <div class="d-flex gap-2">
                <span class="badge px-3 py-2" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 0.85rem;">
                    <i class="fas fa-chart-line me-2"></i><?php echo $stats['acceptance_rate']; ?>% Acceptance Rate
                </span>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="hiring-tabs">
            <a href="?tab=monitoring" class="hiring-tab <?php echo $tab === 'monitoring' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                Application Monitoring
                <span class="badge bg-light text-dark"><?php echo $stats['pending_applications']; ?></span>
            </a>
            <a href="?tab=interviews" class="hiring-tab <?php echo $tab === 'interviews' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                Interview Tracking
                <span class="badge bg-light text-dark"><?php echo $stats['scheduled_interviews']; ?></span>
            </a>
            <a href="?tab=outcomes" class="hiring-tab <?php echo $tab === 'outcomes' ? 'active' : ''; ?>">
                <i class="fas fa-trophy"></i>
                Hiring Outcomes
                <span class="badge bg-light text-dark"><?php echo $stats['offers_accepted']; ?></span>
            </a>
        </div>

        <?php if ($tab === 'monitoring'): ?>
        <!-- APPLICATION MONITORING TAB -->
        
        <!-- Stats Overview -->
        <h5 class="section-title"><i class="fas fa-chart-pie"></i> Application Overview</h5>
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Total Applications</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['total_applications']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-file-alt fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-arrow-up me-1"></i><?php echo $stats['this_week_applications']; ?> this week
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Pending Review</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['pending_applications']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-clock fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-hourglass-half me-1"></i>Awaiting action
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Accepted</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['accepted_applications']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-check-circle fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-percentage me-1"></i><?php echo $stats['acceptance_rate']; ?>% rate
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Rejected</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['rejected_applications']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-times-circle fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-info-circle me-1"></i>Not qualified
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="monitoring">
                <div class="col-md-3">
                    <label class="form-label">Company</label>
                    <select class="form-select" name="company_id">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Search applicant, job, company..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fas fa-search me-2"></i>Filter Results
                    </button>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="data-table">
            <div class="card-header bg-transparent border-0 px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-list me-2" style="color: #a78bfa;"></i>Applications (<?php echo count($applications); ?>)
                </h5>
            </div>
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>No applications found</h5>
                    <p>Try adjusting your filters or check back later</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Job Position</th>
                                <th>Company</th>
                                <th>Applied Date</th>
                                <th>Status</th>
                                <th>Interview</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <div class="applicant-info">
                                            <?php if (!empty($app['profile_picture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($app['profile_picture']); ?>" alt="" class="applicant-avatar">
                                            <?php else: ?>
                                                <div class="applicant-avatar-placeholder">
                                                    <?php echo strtoupper(substr($app['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="applicant-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                                                <div class="applicant-email"><?php echo htmlspecialchars($app['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                        <?php if ($app['location']): ?>
                                            <br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($app['location']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="color: #94a3b8;"><?php echo htmlspecialchars($app['company_name']); ?></span>
                                    </td>
                                    <td>
                                        <div style="color: #e2e8f0;"><?php echo date('M j, Y', strtotime($app['applied_date'])); ?></div>
                                        <small class="text-muted"><?php echo timeAgo($app['applied_date']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = 'status-' . $app['status'];
                                        $statusIcon = match($app['status']) {
                                            'pending' => 'clock',
                                            'reviewed' => 'eye',
                                            'accepted' => 'check-circle',
                                            'rejected' => 'times-circle',
                                            default => 'circle'
                                        };
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                            <?php echo ucfirst($app['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($app['interview_date']): ?>
                                            <span class="interview-time">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M j', strtotime($app['interview_date'])); ?>
                                            </span>
                                            <?php if ($app['interview_result']): ?>
                                                <br>
                                                <span class="status-badge status-<?php echo $app['interview_result']; ?> mt-1">
                                                    <?php echo ucfirst($app['interview_result']); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php elseif ($tab === 'interviews'): ?>
        <!-- INTERVIEW TRACKING TAB -->
        
        <!-- Interview Stats -->
        <h5 class="section-title"><i class="fas fa-calendar-check"></i> Interview Statistics</h5>
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Scheduled</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['scheduled_interviews']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-clock me-1"></i><?php echo $stats['today_interviews']; ?> today
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Completed</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['completed_interviews']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-check-double fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-user-check me-1"></i>Interviewed
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Passed</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['passed_interviews']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-thumbs-up fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-percentage me-1"></i><?php echo $stats['interview_pass_rate']; ?>% pass rate
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Failed</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['failed_interviews']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-thumbs-down fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-user-times me-1"></i>Not passed
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Upcoming Interviews -->
            <div class="col-md-6">
                <div class="dashboard-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-day me-2" style="color: #a78bfa;"></i>Upcoming Interviews</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_interviews)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No upcoming interviews</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_interviews as $interview): ?>
                                <div class="activity-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-white"><?php echo htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($interview['job_title']); ?> • <?php echo htmlspecialchars($interview['company_name']); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="interview-time">
                                            <?php echo date('M j', strtotime($interview['interview_date'])); ?>
                                            <?php if ($interview['interview_time']): ?>
                                                <br><small><?php echo date('g:i A', strtotime($interview['interview_time'])); ?></small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Interview Pass Rate Chart -->
            <div class="col-md-6">
                <div class="dashboard-card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie me-2" style="color: #34d399;"></i>Interview Results</h5>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center">
                        <div class="row w-100">
                            <div class="col-6">
                                <div class="progress-ring">
                                    <svg width="120" height="120" class="progress-ring__circle">
                                        <circle class="progress-ring__background" cx="60" cy="60" r="52"></circle>
                                        <circle class="progress-ring__progress" cx="60" cy="60" r="52" 
                                            stroke="#10b981" 
                                            stroke-dasharray="<?php echo 2 * 3.14159 * 52; ?>" 
                                            stroke-dashoffset="<?php echo 2 * 3.14159 * 52 * (1 - $stats['interview_pass_rate'] / 100); ?>">
                                        </circle>
                                    </svg>
                                    <div class="progress-ring__text">
                                        <div class="progress-ring__percentage"><?php echo $stats['interview_pass_rate']; ?>%</div>
                                        <div class="progress-ring__label">Pass Rate</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="quick-stats">
                                    <div class="mini-stat">
                                        <div class="mini-stat-value text-success"><?php echo $stats['passed_interviews']; ?></div>
                                        <div class="mini-stat-label">Passed</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-stat-value text-danger"><?php echo $stats['failed_interviews']; ?></div>
                                        <div class="mini-stat-label">Failed</div>
                                    </div>
                                    <div class="mini-stat">
                                        <div class="mini-stat-value text-warning"><?php echo $stats['pending_interviews']; ?></div>
                                        <div class="mini-stat-label">Pending</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interview Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="interviews">
                <div class="col-md-3">
                    <label class="form-label">Company</label>
                    <select class="form-select" name="company_id">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Interview Status</label>
                    <select class="form-select" name="interview">
                        <option value="all" <?php echo $interview_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="today" <?php echo $interview_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="upcoming" <?php echo $interview_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="completed" <?php echo $interview_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="passed" <?php echo $interview_filter === 'passed' ? 'selected' : ''; ?>>Passed</option>
                        <option value="failed" <?php echo $interview_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Search applicant, job, company..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fas fa-search me-2"></i>Filter Results
                    </button>
                </div>
            </form>
        </div>

        <!-- Interviews Table -->
        <div class="data-table">
            <div class="card-header bg-transparent border-0 px-4 py-3">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-list me-2" style="color: #a78bfa;"></i>Interview Schedule (<?php echo count($applications); ?>)
                </h5>
            </div>
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h5>No interviews found</h5>
                    <p>Try adjusting your filters or check back later</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Job Position</th>
                                <th>Company</th>
                                <th>Interview Date</th>
                                <th>Mode</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <div class="applicant-info">
                                            <?php if (!empty($app['profile_picture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($app['profile_picture']); ?>" alt="" class="applicant-avatar">
                                            <?php else: ?>
                                                <div class="applicant-avatar-placeholder">
                                                    <?php echo strtoupper(substr($app['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="applicant-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                                                <div class="applicant-email"><?php echo htmlspecialchars($app['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: #94a3b8;"><?php echo htmlspecialchars($app['company_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($app['interview_date']): ?>
                                            <div class="interview-time">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M j, Y', strtotime($app['interview_date'])); ?>
                                                <?php if ($app['interview_time']): ?>
                                                    <br><small><?php echo date('g:i A', strtotime($app['interview_time'])); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Not scheduled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($app['interview_mode']): ?>
                                            <span class="status-badge status-scheduled">
                                                <i class="fas fa-<?php echo $app['interview_mode'] === 'online' ? 'video' : ($app['interview_mode'] === 'phone' ? 'phone' : 'building'); ?> me-1"></i>
                                                <?php echo ucfirst($app['interview_mode']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($app['interview_result']): ?>
                                            <span class="status-badge status-<?php echo $app['interview_result']; ?>">
                                                <i class="fas fa-<?php echo $app['interview_result'] === 'passed' ? 'check-circle' : ($app['interview_result'] === 'failed' ? 'times-circle' : 'hourglass-half'); ?>"></i>
                                                <?php echo ucfirst($app['interview_result']); ?>
                                            </span>
                                            <?php if ($app['interview_rating']): ?>
                                                <br>
                                                <small class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star<?php echo $i <= $app['interview_rating'] ? '' : '-o'; ?>"></i>
                                                    <?php endfor; ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">
                                                <i class="fas fa-hourglass-half"></i>
                                                Pending
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

        <?php else: ?>
        <!-- HIRING OUTCOMES TAB -->
        
        <!-- Hiring Stats -->
        <h5 class="section-title"><i class="fas fa-trophy"></i> Hiring Outcomes</h5>
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Offers Sent</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['offers_sent']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-paper-plane fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-envelope me-1"></i>Job offers extended
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Offers Accepted</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['offers_accepted']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-user-check fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-percentage me-1"></i><?php echo $stats['offer_acceptance_rate']; ?>% acceptance
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Pending Decision</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['offers_pending']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-hourglass-half fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-clock me-1"></i>Awaiting response
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card text-white" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="stat-label mb-1">Offers Rejected</p>
                                <h3 class="stat-number mb-0"><?php echo number_format($stats['offers_rejected']); ?></h3>
                            </div>
                            <div class="stat-icon">
                                <i class="fas fa-user-times fa-lg"></i>
                            </div>
                        </div>
                        <small class="text-white-50 mt-2 d-block">
                            <i class="fas fa-times me-1"></i>Declined offers
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <!-- Hiring Trends Chart -->
            <div class="col-md-8">
                <div class="dashboard-card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-area me-2" style="color: #34d399;"></i>Hiring Trends (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="hiringTrendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Offers -->
            <div class="col-md-4">
                <div class="dashboard-card h-100">
                    <div class="card-header">
                        <h5><i class="fas fa-handshake me-2" style="color: #fbbf24;"></i>Recent Offers</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_offers)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-contract fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No recent offers</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_offers as $offer): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong class="text-white"><?php echo htmlspecialchars($offer['first_name'] . ' ' . $offer['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($offer['job_title']); ?></small>
                                        </div>
                                        <?php if ($offer['offer_status']): ?>
                                            <span class="status-badge status-<?php echo $offer['offer_status']; ?>">
                                                <?php echo ucfirst($offer['offer_status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($offer['offer_salary']): ?>
                                        <small class="text-success d-block mt-1">
                                            <i class="fas fa-money-bill-wave me-1"></i><?php echo htmlspecialchars($offer['offer_salary']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offer Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="tab" value="outcomes">
                <div class="col-md-3">
                    <label class="form-label">Company</label>
                    <select class="form-select" name="company_id">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>" <?php echo $company_filter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Offer Status</label>
                    <select class="form-select" name="offer">
                        <option value="all" <?php echo $offer_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="sent" <?php echo $offer_filter === 'sent' ? 'selected' : ''; ?>>All Offers</option>
                        <option value="accepted" <?php echo $offer_filter === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                        <option value="pending" <?php echo $offer_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo $offer_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="hired" <?php echo $offer_filter === 'hired' ? 'selected' : ''; ?>>Hired</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" placeholder="Search applicant, job, company..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fas fa-search me-2"></i>Filter Results
                    </button>
                </div>
            </form>
        </div>

        <!-- Outcomes Table -->
        <div class="data-table">
            <div class="card-header bg-transparent border-0 px-4 py-3">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-list me-2" style="color: #a78bfa;"></i>Hiring Outcomes (<?php echo count($applications); ?>)
                </h5>
            </div>
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-trophy"></i>
                    <h5>No hiring outcomes found</h5>
                    <p>Try adjusting your filters or check back later</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Applicant</th>
                                <th>Job Position</th>
                                <th>Company</th>
                                <th>Offer Salary</th>
                                <th>Start Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td>
                                        <div class="applicant-info">
                                            <?php if (!empty($app['profile_picture'])): ?>
                                                <img src="../<?php echo htmlspecialchars($app['profile_picture']); ?>" alt="" class="applicant-avatar">
                                            <?php else: ?>
                                                <div class="applicant-avatar-placeholder">
                                                    <?php echo strtoupper(substr($app['first_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="applicant-name"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                                                <div class="applicant-email"><?php echo htmlspecialchars($app['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                    </td>
                                    <td>
                                        <span style="color: #94a3b8;"><?php echo htmlspecialchars($app['company_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($app['offer_salary']): ?>
                                            <span class="text-success">
                                                <i class="fas fa-money-bill-wave me-1"></i>
                                                <?php echo htmlspecialchars($app['offer_salary']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($app['offer_start_date']): ?>
                                            <span style="color: #e2e8f0;">
                                                <i class="fas fa-calendar-check me-1"></i>
                                                <?php echo date('M j, Y', strtotime($app['offer_start_date'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($app['offer_status']): ?>
                                            <?php
                                            $offerStatusIcon = match($app['offer_status']) {
                                                'accepted' => 'check-circle',
                                                'rejected' => 'times-circle',
                                                'pending' => 'hourglass-half',
                                                'withdrawn' => 'undo',
                                                default => 'circle'
                                            };
                                            ?>
                                            <span class="status-badge status-<?php echo $app['offer_status']; ?>">
                                                <i class="fas fa-<?php echo $offerStatusIcon; ?>"></i>
                                                <?php echo ucfirst($app['offer_status']); ?>
                                            </span>
                                        <?php elseif ($app['offer_sent']): ?>
                                            <span class="status-badge status-scheduled">
                                                <i class="fas fa-paper-plane"></i>
                                                Sent
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">No offer</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Hiring Trends Chart (only on outcomes tab)
        <?php if ($tab === 'outcomes'): ?>
        const hiringTrendsCtx = document.getElementById('hiringTrendsChart');
        if (hiringTrendsCtx) {
            new Chart(hiringTrendsCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: [<?php for ($i = 5; $i >= 0; $i--) { echo "'" . date('M', strtotime("-$i months")) . "'"; if ($i > 0) echo ','; } ?>],
                    datasets: [{
                        label: 'Applications',
                        data: [<?php echo implode(',', $monthly_applications); ?>],
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#8b5cf6'
                    }, {
                        label: 'Hired',
                        data: [<?php echo implode(',', $monthly_hired); ?>],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#94a3b8',
                                font: { size: 12 }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255,255,255,0.05)' },
                            ticks: { color: '#64748b' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b' }
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>

