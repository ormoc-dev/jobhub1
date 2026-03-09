<?php
include '../config.php';
requireRole('admin');

// Get date range from filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'employment';

// Employment Statistics
$employment_stats = [];

// Total Applications
$employment_stats['total_applications'] = $pdo->query("SELECT COUNT(*) FROM job_applications")->fetchColumn();

// Applications in date range
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE DATE(applied_date) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$employment_stats['period_applications'] = $stmt->fetchColumn();

// Hired/Accepted count
$employment_stats['total_hired'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'accepted'")->fetchColumn();

// Hired in date range
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications WHERE status = 'accepted' AND DATE(applied_date) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$employment_stats['period_hired'] = $stmt->fetchColumn();

// Pending Applications
$employment_stats['pending'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'pending'")->fetchColumn();

// Reviewed Applications
$employment_stats['reviewed'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'reviewed'")->fetchColumn();

// Rejected Applications
$employment_stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM job_applications WHERE status = 'rejected'")->fetchColumn();

// Active Job Seekers (employees with active profiles)
$employment_stats['active_job_seekers'] = $pdo->query("SELECT COUNT(*) FROM employee_profiles ep JOIN users u ON ep.user_id = u.id WHERE u.status = 'active'")->fetchColumn();

// Active Job Postings
$employment_stats['active_jobs'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'active'")->fetchColumn();

// Applications by status in period
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM job_applications 
    WHERE DATE(applied_date) BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$start_date, $end_date]);
$status_breakdown = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Placement Rate Calculations
$placement_stats = [];

// Overall Placement Rate
$placement_stats['overall_rate'] = $employment_stats['total_applications'] > 0 
    ? round(($employment_stats['total_hired'] / $employment_stats['total_applications']) * 100, 1) 
    : 0;

// Period Placement Rate
$placement_stats['period_rate'] = $employment_stats['period_applications'] > 0 
    ? round(($employment_stats['period_hired'] / $employment_stats['period_applications']) * 100, 1) 
    : 0;

// Average time to placement (days from application to review/acceptance)
$avg_time_query = $pdo->query("
    SELECT AVG(DATEDIFF(
        COALESCE(reviewed_date, applied_date), 
        applied_date
    )) as avg_days
    FROM job_applications
    WHERE status = 'accepted' AND reviewed_date IS NOT NULL
")->fetch();
$placement_stats['avg_placement_days'] = round($avg_time_query['avg_days'] ?? 0, 1);

// Placement by company
$placement_by_company = $pdo->query("
    SELECT c.company_name, COUNT(ja.id) as hired_count,
           COUNT(ja.id) * 100.0 / NULLIF((SELECT COUNT(*) FROM job_applications ja2 
               JOIN job_postings jp2 ON ja2.job_id = jp2.id 
               WHERE jp2.company_id = c.id), 0) as placement_rate
    FROM job_applications ja
    JOIN job_postings jp ON ja.job_id = jp.id
    JOIN companies c ON jp.company_id = c.id
    WHERE ja.status = 'accepted'
    GROUP BY c.id, c.company_name
    ORDER BY hired_count DESC
    LIMIT 10
")->fetchAll();

// Placement by category
$placement_by_category = $pdo->query("
    SELECT jc.category_name, COUNT(ja.id) as hired_count
    FROM job_applications ja
    JOIN job_postings jp ON ja.job_id = jp.id
    JOIN job_categories jc ON jp.category_id = jc.id
    WHERE ja.status = 'accepted'
    GROUP BY jc.id, jc.category_name
    ORDER BY hired_count DESC
    LIMIT 10
")->fetchAll();

// Monthly placement trends (last 12 months)
$monthly_placements = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE status = 'accepted' AND DATE_FORMAT(applied_date, '%Y-%m') = ?
    ");
    $stmt->execute([$month]);
    $monthly_placements[$month_name] = $stmt->fetchColumn();
}

// Monthly applications trends
$monthly_applications = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE DATE_FORMAT(applied_date, '%Y-%m') = ?
    ");
    $stmt->execute([$month]);
    $monthly_applications[$month_name] = $stmt->fetchColumn();
}

// User Activity Logs - Recent activities
$recent_activities = [];

// Recent user registrations
$recent_registrations = $pdo->query("
    SELECT u.id, u.username, u.email, u.role, u.created_at, u.status,
           CASE 
               WHEN u.role = 'employee' THEN CONCAT(ep.first_name, ' ', ep.last_name)
               WHEN u.role = 'employer' THEN c.company_name
               ELSE 'Admin'
           END as display_name
    FROM users u
    LEFT JOIN employee_profiles ep ON u.id = ep.user_id
    LEFT JOIN companies c ON u.id = c.user_id
    ORDER BY u.created_at DESC
    LIMIT 15
")->fetchAll();

// Recent job applications
$recent_job_applications = $pdo->query("
    SELECT ja.id, ja.applied_date, ja.status,
           jp.title as job_title,
           c.company_name,
           CONCAT(ep.first_name, ' ', ep.last_name) as applicant_name,
           u.email as applicant_email
    FROM job_applications ja
    JOIN job_postings jp ON ja.job_id = jp.id
    JOIN companies c ON jp.company_id = c.id
    JOIN employee_profiles ep ON ja.employee_id = ep.id
    JOIN users u ON ep.user_id = u.id
    ORDER BY ja.applied_date DESC
    LIMIT 15
")->fetchAll();

// Recent job postings
$recent_postings = $pdo->query("
    SELECT jp.id, jp.title, jp.posted_date, jp.status,
           c.company_name,
           jc.category_name
    FROM job_postings jp
    JOIN companies c ON jp.company_id = c.id
    LEFT JOIN job_categories jc ON jp.category_id = jc.id
    ORDER BY jp.posted_date DESC
    LIMIT 15
")->fetchAll();

// Status change logs (hiring decisions)
$hiring_decisions = $pdo->query("
    SELECT ja.id, ja.status, ja.applied_date,
           jp.title as job_title,
           c.company_name,
           CONCAT(ep.first_name, ' ', ep.last_name) as applicant_name
    FROM job_applications ja
    JOIN job_postings jp ON ja.job_id = jp.id
    JOIN companies c ON jp.company_id = c.id
    JOIN employee_profiles ep ON ja.employee_id = ep.id
    WHERE ja.status IN ('accepted', 'rejected')
    ORDER BY ja.applied_date DESC
    LIMIT 15
")->fetchAll();

// Top performing jobs (most applications)
$top_jobs = $pdo->query("
    SELECT jp.title, c.company_name, 
           COUNT(ja.id) as total_applications,
           SUM(CASE WHEN ja.status = 'accepted' THEN 1 ELSE 0 END) as hired,
           SUM(CASE WHEN ja.status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM job_postings jp
    LEFT JOIN job_applications ja ON jp.id = ja.job_id
    JOIN companies c ON jp.company_id = c.id
    GROUP BY jp.id, jp.title, c.company_name
    HAVING total_applications > 0
    ORDER BY total_applications DESC
    LIMIT 10
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Reports Page Custom Styles */
        .reports-container {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #16213e 100%);
            min-height: 100vh;
            padding: 30px;
        }

        /* Page Header */
        .reports-header {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15) 0%, rgba(59, 130, 246, 0.15) 100%);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 24px;
            padding: 35px 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .reports-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.2) 0%, transparent 70%);
            border-radius: 50%;
        }

        .reports-header h1 {
            font-size: 2.25rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .reports-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.05rem;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .header-actions {
            position: relative;
            z-index: 1;
        }

        .btn-export {
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            border: none;
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
            color: #ffffff;
        }

        /* Filter Card */
        .filter-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
        }

        .filter-card .form-label {
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .filter-card .form-control,
        .filter-card .form-select {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: #ffffff;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            color: #ffffff;
        }

        .btn-filter {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: none;
            color: #ffffff;
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            color: #ffffff;
        }

        /* Tab Navigation */
        .report-tabs {
            background: rgba(30, 41, 59, 0.5);
            border-radius: 16px;
            padding: 8px;
            margin-bottom: 30px;
            display: inline-flex;
            gap: 5px;
        }

        .report-tab {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .report-tab:hover {
            color: #ffffff;
            background: rgba(139, 92, 246, 0.2);
        }

        .report-tab.active {
            background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
        }

        /* Stat Cards */
        .stat-card-report {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 28px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .stat-card-report:hover {
            transform: translateY(-8px);
            border-color: rgba(139, 92, 246, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .stat-card-report::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -10%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.05) 0%, transparent 70%);
            border-radius: 50%;
        }

        .stat-icon-box {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }

        .stat-icon-purple { background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); color: #fff; }
        .stat-icon-emerald { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; }
        .stat-icon-amber { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; }
        .stat-icon-rose { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); color: #fff; }
        .stat-icon-sky { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #fff; }
        .stat-icon-indigo { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff; }

        .stat-number-report {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label-report {
            color: #94a3b8;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .stat-change {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .stat-change.positive {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .stat-change.negative {
            background: rgba(244, 63, 94, 0.15);
            color: #f43f5e;
        }

        /* Placement Rate Gauge */
        .placement-gauge-card {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(99, 102, 241, 0.2) 100%);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 24px;
            padding: 35px;
            text-align: center;
        }

        .gauge-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 25px;
        }

        .gauge-circle {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 15;
        }

        .gauge-progress {
            fill: none;
            stroke: url(#gaugeGradient);
            stroke-width: 15;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: center;
            transition: stroke-dashoffset 1.5s ease;
        }

        .gauge-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .gauge-percentage {
            font-size: 3rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
        }

        .gauge-label {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 5px;
        }

        /* Chart Cards */
        .chart-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            border-color: rgba(139, 92, 246, 0.3);
        }

        .chart-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chart-title {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title i {
            color: #8b5cf6;
        }

        .chart-body {
            padding: 25px;
        }

        /* Activity Log Styles */
        .activity-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
        }

        .activity-header {
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-title {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-body {
            max-height: 450px;
            overflow-y: auto;
        }

        .activity-body::-webkit-scrollbar {
            width: 6px;
        }

        .activity-body::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .activity-body::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.5);
            border-radius: 3px;
        }

        .activity-item {
            padding: 18px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }

        .activity-item:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon-wrapper {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon-user { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); }
        .activity-icon-job { background: linear-gradient(135deg, #0ea5e9 0%, #06b6d4 100%); }
        .activity-icon-application { background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); }
        .activity-icon-hired { background: linear-gradient(135deg, #10b981 0%, #14b8a6 100%); }
        .activity-icon-rejected { background: linear-gradient(135deg, #f43f5e 0%, #ef4444 100%); }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            color: #e2e8f0;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .activity-text strong {
            color: #ffffff;
        }

        .activity-meta {
            color: #64748b;
            font-size: 0.8rem;
            display: flex;
            gap: 15px;
        }

        .activity-time {
            color: #8b5cf6;
        }

        /* Status Badge */
        .status-pill {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .status-reviewed { background: rgba(14, 165, 233, 0.15); color: #0ea5e9; }
        .status-accepted { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .status-rejected { background: rgba(244, 63, 94, 0.15); color: #f43f5e; }
        .status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }

        /* Top Performers Table */
        .table-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            overflow: hidden;
        }

        .table-dark-custom {
            background: transparent;
            color: #e2e8f0;
            margin: 0;
        }

        .table-dark-custom thead th {
            background: rgba(139, 92, 246, 0.2);
            color: #ffffff;
            border: none;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table-dark-custom tbody td {
            border: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 15px 20px;
            vertical-align: middle;
        }

        .table-dark-custom tbody tr:hover {
            background: rgba(139, 92, 246, 0.1);
        }

        .rank-badge {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .rank-1 { background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #1f2937; }
        .rank-2 { background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%); color: #ffffff; }
        .rank-3 { background: linear-gradient(135deg, #cd7c2e 0%, #b45309 100%); color: #ffffff; }
        .rank-default { background: rgba(139, 92, 246, 0.2); color: #a78bfa; }

        /* Section Title */
        .section-title-report {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title-report i {
            color: #8b5cf6;
        }

        /* Print Styles */
        @media print {
            .reports-container {
                background: #ffffff;
                padding: 20px;
            }
            
            .filter-card, .btn-export, .report-tabs {
                display: none;
            }
            
            .stat-card-report, .chart-card, .activity-card, .table-card {
                background: #ffffff;
                border: 1px solid #e2e8f0;
                box-shadow: none;
            }
            
            .stat-number-report, .chart-title, .activity-title, .section-title-report {
                color: #1e293b;
            }
            
            .stat-label-report, .activity-text {
                color: #64748b;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .reports-container {
                padding: 15px;
            }
            
            .reports-header {
                padding: 25px 20px;
            }
            
            .reports-header h1 {
                font-size: 1.75rem;
            }
            
            .stat-number-report {
                font-size: 2rem;
            }
            
            .gauge-container {
                width: 150px;
                height: 150px;
            }
            
            .gauge-percentage {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="admin-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content reports-container">
        <!-- Header -->
        <div class="reports-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1><i class="fas fa-chart-line me-3"></i>Reports & Analytics</h1>
                    <p>Comprehensive employment reports, placement rates, and user activity logs</p>
                </div>
                <div class="header-actions d-flex gap-2">
                    <button class="btn btn-export" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Print Report
                    </button>
                    <button class="btn btn-export" onclick="exportData()">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select" onchange="this.form.submit()">
                        <option value="employment" <?php echo $report_type === 'employment' ? 'selected' : ''; ?>>Employment Reports</option>
                        <option value="placement" <?php echo $report_type === 'placement' ? 'selected' : ''; ?>>Placement Rate</option>
                        <option value="activity" <?php echo $report_type === 'activity' ? 'selected' : ''; ?>>User Activity Logs</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-filter w-100">
                        <i class="fas fa-filter me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab Navigation -->
        <div class="report-tabs">
            <a href="?report_type=employment&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="report-tab <?php echo $report_type === 'employment' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase me-2"></i>Employment Reports
            </a>
            <a href="?report_type=placement&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="report-tab <?php echo $report_type === 'placement' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie me-2"></i>Placement Rate
            </a>
            <a href="?report_type=activity&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
               class="report-tab <?php echo $report_type === 'activity' ? 'active' : ''; ?>">
                <i class="fas fa-history me-2"></i>Activity Logs
            </a>
        </div>

        <?php if ($report_type === 'employment'): ?>
        <!-- Employment Reports Section -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-purple">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-number-report"><?php echo number_format($employment_stats['total_applications']); ?></div>
                    <div class="stat-label-report">Total Applications</div>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +<?php echo number_format($employment_stats['period_applications']); ?> this period
                    </span>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-emerald">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number-report"><?php echo number_format($employment_stats['total_hired']); ?></div>
                    <div class="stat-label-report">Total Hired</div>
                    <span class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +<?php echo number_format($employment_stats['period_hired']); ?> this period
                    </span>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-amber">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number-report"><?php echo number_format($employment_stats['pending']); ?></div>
                    <div class="stat-label-report">Pending Review</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-sky">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number-report"><?php echo number_format($employment_stats['active_job_seekers']); ?></div>
                    <div class="stat-label-report">Active Job Seekers</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Applications & Placements Trend
                        </h5>
                    </div>
                    <div class="chart-body">
                        <canvas id="trendChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">
                            <i class="fas fa-chart-pie"></i>
                            Application Status
                        </h5>
                    </div>
                    <div class="chart-body">
                        <canvas id="statusChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performing Jobs -->
        <div class="table-card mb-4">
            <div class="chart-header">
                <h5 class="chart-title">
                    <i class="fas fa-trophy"></i>
                    Top Performing Job Listings
                </h5>
            </div>
            <table class="table table-dark-custom">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Job Title</th>
                        <th>Company</th>
                        <th>Applications</th>
                        <th>Hired</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_jobs as $index => $job): ?>
                    <tr>
                        <td>
                            <span class="rank-badge <?php echo $index < 3 ? 'rank-' . ($index + 1) : 'rank-default'; ?>">
                                <?php echo $index + 1; ?>
                            </span>
                        </td>
                        <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                        <td><span class="status-pill status-active"><?php echo $job['total_applications']; ?></span></td>
                        <td><span class="status-pill status-accepted"><?php echo $job['hired']; ?></span></td>
                        <td><span class="status-pill status-pending"><?php echo $job['pending']; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_jobs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x mb-3" style="color: #64748b;"></i>
                            <p class="text-muted mb-0">No job applications data available</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php elseif ($report_type === 'placement'): ?>
        <!-- Placement Rate Section -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="placement-gauge-card">
                    <h5 class="text-white mb-4 fw-bold">Overall Placement Rate</h5>
                    <div class="gauge-container">
                        <svg viewBox="0 0 200 200" style="width: 100%; height: 100%;">
                            <defs>
                                <linearGradient id="gaugeGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                    <stop offset="0%" style="stop-color:#8b5cf6" />
                                    <stop offset="100%" style="stop-color:#06b6d4" />
                                </linearGradient>
                            </defs>
                            <circle class="gauge-circle" cx="100" cy="100" r="85"/>
                            <circle class="gauge-progress" cx="100" cy="100" r="85" 
                                    stroke-dasharray="534" 
                                    stroke-dashoffset="<?php echo 534 - (534 * $placement_stats['overall_rate'] / 100); ?>"/>
                        </svg>
                        <div class="gauge-text">
                            <div class="gauge-percentage"><?php echo $placement_stats['overall_rate']; ?>%</div>
                            <div class="gauge-label">Success Rate</div>
                        </div>
                    </div>
                    <div class="row text-center mt-4">
                        <div class="col-6">
                            <div class="stat-number-report" style="font-size: 1.5rem;"><?php echo number_format($employment_stats['total_hired']); ?></div>
                            <div class="stat-label-report" style="font-size: 0.8rem;">Total Placed</div>
                        </div>
                        <div class="col-6">
                            <div class="stat-number-report" style="font-size: 1.5rem;"><?php echo $placement_stats['avg_placement_days']; ?></div>
                            <div class="stat-label-report" style="font-size: 0.8rem;">Avg. Days</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="stat-card-report">
                            <div class="stat-icon-box stat-icon-purple">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="stat-number-report"><?php echo $placement_stats['period_rate']; ?>%</div>
                            <div class="stat-label-report">Period Placement Rate</div>
                            <small class="text-muted"><?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card-report">
                            <div class="stat-icon-box stat-icon-emerald">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-number-report"><?php echo number_format($employment_stats['period_hired']); ?></div>
                            <div class="stat-label-report">Period Placements</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card-report">
                            <div class="stat-icon-box stat-icon-sky">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-number-report"><?php echo number_format($employment_stats['active_jobs']); ?></div>
                            <div class="stat-label-report">Active Job Openings</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card-report">
                            <div class="stat-icon-box stat-icon-rose">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="stat-number-report"><?php echo number_format($employment_stats['rejected']); ?></div>
                            <div class="stat-label-report">Not Selected</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Placement Charts -->
        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">
                            <i class="fas fa-chart-bar"></i>
                            Monthly Placement Trend
                        </h5>
                    </div>
                    <div class="chart-body">
                        <canvas id="placementTrendChart" height="150"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card">
                    <div class="chart-header">
                        <h5 class="chart-title">
                            <i class="fas fa-building"></i>
                            Top Hiring Companies
                        </h5>
                    </div>
                    <div class="chart-body">
                        <canvas id="companyChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Placement by Company & Category Tables -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="table-card">
                    <div class="chart-header">
                        <h5 class="chart-title">
                            <i class="fas fa-building"></i>
                            Placements by Company
                        </h5>
                    </div>
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Company</th>
                                <th>Hired</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($placement_by_company as $index => $company): ?>
                            <tr>
                                <td>
                                    <span class="rank-badge <?php echo $index < 3 ? 'rank-' . ($index + 1) : 'rank-default'; ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($company['company_name'] ?? 'Unknown'); ?></strong></td>
                                <td><span class="status-pill status-accepted"><?php echo $company['hired_count']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($placement_by_company)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No placement data available</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="table-card">
                    <div class="chart-header">
                        <h5 class="chart-title">
                            <i class="fas fa-tags"></i>
                            Placements by Category
                        </h5>
                    </div>
                    <table class="table table-dark-custom">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Category</th>
                                <th>Hired</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($placement_by_category as $index => $category): ?>
                            <tr>
                                <td>
                                    <span class="rank-badge <?php echo $index < 3 ? 'rank-' . ($index + 1) : 'rank-default'; ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td><strong><?php echo htmlspecialchars($category['category_name']); ?></strong></td>
                                <td><span class="status-pill status-accepted"><?php echo $category['hired_count']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($placement_by_category)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted">No placement data available</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- User Activity Logs Section -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-purple">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="stat-number-report"><?php echo count($recent_registrations); ?></div>
                    <div class="stat-label-report">Recent Registrations</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-sky">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <div class="stat-number-report"><?php echo count($recent_postings); ?></div>
                    <div class="stat-label-report">Recent Job Posts</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-amber">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stat-number-report"><?php echo count($recent_job_applications); ?></div>
                    <div class="stat-label-report">Recent Applications</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card-report">
                    <div class="stat-icon-box stat-icon-emerald">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-number-report"><?php echo count($hiring_decisions); ?></div>
                    <div class="stat-label-report">Hiring Decisions</div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- User Registrations -->
            <div class="col-lg-6">
                <div class="activity-card">
                    <div class="activity-header">
                        <h5 class="activity-title">
                            <i class="fas fa-user-plus" style="color: #8b5cf6;"></i>
                            Recent User Registrations
                        </h5>
                    </div>
                    <div class="activity-body">
                        <?php foreach ($recent_registrations as $user): ?>
                        <div class="activity-item">
                            <div class="activity-icon-wrapper activity-icon-user">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($user['display_name'] ?: $user['username']); ?></strong>
                                    registered as <strong><?php echo ucfirst($user['role']); ?></strong>
                                </div>
                                <div class="activity-meta">
                                    <span><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?></span>
                                    <span class="activity-time"><i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?></span>
                                </div>
                            </div>
                            <span class="status-pill status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recent_registrations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-3x mb-3" style="color: #64748b;"></i>
                            <p class="text-muted">No recent registrations</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Job Applications -->
            <div class="col-lg-6">
                <div class="activity-card">
                    <div class="activity-header">
                        <h5 class="activity-title">
                            <i class="fas fa-paper-plane" style="color: #f59e0b;"></i>
                            Recent Job Applications
                        </h5>
                    </div>
                    <div class="activity-body">
                        <?php foreach ($recent_job_applications as $app): ?>
                        <div class="activity-item">
                            <div class="activity-icon-wrapper activity-icon-application">
                                <i class="fas fa-file-alt text-white"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($app['applicant_name']); ?></strong>
                                    applied for <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                </div>
                                <div class="activity-meta">
                                    <span><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($app['company_name']); ?></span>
                                    <span class="activity-time"><i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($app['applied_date'])); ?></span>
                                </div>
                            </div>
                            <span class="status-pill status-<?php echo $app['status']; ?>"><?php echo ucfirst($app['status']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recent_job_applications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x mb-3" style="color: #64748b;"></i>
                            <p class="text-muted">No recent applications</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Job Postings -->
            <div class="col-lg-6">
                <div class="activity-card">
                    <div class="activity-header">
                        <h5 class="activity-title">
                            <i class="fas fa-briefcase" style="color: #0ea5e9;"></i>
                            Recent Job Postings
                        </h5>
                    </div>
                    <div class="activity-body">
                        <?php foreach ($recent_postings as $posting): ?>
                        <div class="activity-item">
                            <div class="activity-icon-wrapper activity-icon-job">
                                <i class="fas fa-briefcase text-white"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($posting['company_name']); ?></strong>
                                    posted <strong><?php echo htmlspecialchars($posting['title']); ?></strong>
                                </div>
                                <div class="activity-meta">
                                    <span><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($posting['category_name'] ?? 'Uncategorized'); ?></span>
                                    <span class="activity-time"><i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($posting['posted_date'])); ?></span>
                                </div>
                            </div>
                            <span class="status-pill status-<?php echo $posting['status']; ?>"><?php echo ucfirst($posting['status']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recent_postings)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-3x mb-3" style="color: #64748b;"></i>
                            <p class="text-muted">No recent job postings</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Hiring Decisions -->
            <div class="col-lg-6">
                <div class="activity-card">
                    <div class="activity-header">
                        <h5 class="activity-title">
                            <i class="fas fa-gavel" style="color: #10b981;"></i>
                            Recent Hiring Decisions
                        </h5>
                    </div>
                    <div class="activity-body">
                        <?php foreach ($hiring_decisions as $decision): ?>
                        <div class="activity-item">
                            <div class="activity-icon-wrapper <?php echo $decision['status'] === 'accepted' ? 'activity-icon-hired' : 'activity-icon-rejected'; ?>">
                                <i class="fas <?php echo $decision['status'] === 'accepted' ? 'fa-check' : 'fa-times'; ?> text-white"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($decision['applicant_name']); ?></strong>
                                    was <strong><?php echo $decision['status'] === 'accepted' ? 'hired' : 'not selected'; ?></strong>
                                    for <strong><?php echo htmlspecialchars($decision['job_title']); ?></strong>
                                </div>
                                <div class="activity-meta">
                                    <span><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($decision['company_name']); ?></span>
                                    <span class="activity-time"><i class="fas fa-clock me-1"></i><?php echo date('M j, Y', strtotime($decision['applied_date'])); ?></span>
                                </div>
                            </div>
                            <span class="status-pill status-<?php echo $decision['status']; ?>"><?php echo ucfirst($decision['status']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($hiring_decisions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-check fa-3x mb-3" style="color: #64748b;"></i>
                            <p class="text-muted">No recent hiring decisions</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart Data
        const monthlyLabels = <?php echo json_encode(array_keys($monthly_applications)); ?>;
        const monthlyApplicationsData = <?php echo json_encode(array_values($monthly_applications)); ?>;
        const monthlyPlacementsData = <?php echo json_encode(array_values($monthly_placements)); ?>;
        
        const statusData = {
            pending: <?php echo $employment_stats['pending']; ?>,
            reviewed: <?php echo $employment_stats['reviewed']; ?>,
            accepted: <?php echo $employment_stats['total_hired']; ?>,
            rejected: <?php echo $employment_stats['rejected']; ?>
        };

        const companyLabels = <?php echo json_encode(array_column($placement_by_company, 'company_name')); ?>;
        const companyData = <?php echo json_encode(array_column($placement_by_company, 'hired_count')); ?>;

        // Chart.js Global Defaults
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

        <?php if ($report_type === 'employment'): ?>
        // Trend Chart
        new Chart(document.getElementById('trendChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Applications',
                    data: monthlyApplicationsData,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#8b5cf6'
                }, {
                    label: 'Placements',
                    data: monthlyPlacementsData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true, padding: 20 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // Status Pie Chart
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Reviewed', 'Accepted', 'Rejected'],
                datasets: [{
                    data: [statusData.pending, statusData.reviewed, statusData.accepted, statusData.rejected],
                    backgroundColor: ['#f59e0b', '#0ea5e9', '#10b981', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 15 }
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($report_type === 'placement'): ?>
        // Placement Trend Chart
        new Chart(document.getElementById('placementTrendChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Placements',
                    data: monthlyPlacementsData,
                    backgroundColor: 'rgba(139, 92, 246, 0.8)',
                    borderColor: '#8b5cf6',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // Company Chart
        new Chart(document.getElementById('companyChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: companyLabels.slice(0, 5),
                datasets: [{
                    label: 'Hired',
                    data: companyData.slice(0, 5),
                    backgroundColor: [
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(14, 165, 233, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(244, 63, 94, 0.8)'
                    ],
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });
        <?php endif; ?>

        // Export Function
        function exportData() {
            alert('Export functionality - You can customize this to export data as CSV/Excel/PDF');
        }
    </script>
</body>
</html>
