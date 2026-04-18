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

// Get date range from filters
$start_date = $_GET['start_date'] ?? date('Y-m-01', strtotime('-6 months'));
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// ============================================
// 1. HIRING PERFORMANCE REPORT
// ============================================
$hiring_stats = [];

// Total applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$hiring_stats['total_applications'] = $stmt->fetchColumn();

// Accepted/Hired
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND ja.status = 'accepted' AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$hiring_stats['hired'] = $stmt->fetchColumn();

// Rejected
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND ja.status = 'rejected' AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$hiring_stats['rejected'] = $stmt->fetchColumn();

// Pending
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND ja.status = 'pending' AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$hiring_stats['pending'] = $stmt->fetchColumn();

// Reviewed
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND ja.status = 'reviewed' AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$hiring_stats['reviewed'] = $stmt->fetchColumn();

// Calculate rates
$hiring_stats['hire_rate'] = $hiring_stats['total_applications'] > 0 
    ? round(($hiring_stats['hired'] / $hiring_stats['total_applications']) * 100, 2) 
    : 0;
$hiring_stats['rejection_rate'] = $hiring_stats['total_applications'] > 0 
    ? round(($hiring_stats['rejected'] / $hiring_stats['total_applications']) * 100, 2) 
    : 0;

// Monthly hiring trend
$monthly_hiring = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                          JOIN job_postings jp ON ja.job_id = jp.id 
                          WHERE jp.company_id = ? AND ja.status = 'accepted' 
                          AND ja.applied_date BETWEEN ? AND ?");
    $stmt->execute([$company['id'], $month_start, $month_end]);
    $monthly_hiring[] = $stmt->fetchColumn();
}

// ============================================
// 2. APPLICATION FUNNEL REPORT
// ============================================
$funnel_data = [
    'applied' => $hiring_stats['total_applications'],
    'reviewed' => $hiring_stats['reviewed'],
    'interviewed' => 0,
    'offered' => 0,
    'hired' => $hiring_stats['hired']
];

// Interviewed count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND ja.interview_status = 'interviewed' 
                      AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$funnel_data['interviewed'] = $stmt->fetchColumn();

// Offered count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND ja.offer_sent = 1 
                      AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$funnel_data['offered'] = $stmt->fetchColumn();

// Calculate conversion rates
$funnel_rates = [
    'review_rate' => $funnel_data['applied'] > 0 ? round(($funnel_data['reviewed'] / $funnel_data['applied']) * 100, 2) : 0,
    'interview_rate' => $funnel_data['reviewed'] > 0 ? round(($funnel_data['interviewed'] / $funnel_data['reviewed']) * 100, 2) : 0,
    'offer_rate' => $funnel_data['interviewed'] > 0 ? round(($funnel_data['offered'] / $funnel_data['interviewed']) * 100, 2) : 0,
    'hire_rate' => $funnel_data['offered'] > 0 ? round(($funnel_data['hired'] / $funnel_data['offered']) * 100, 2) : 0
];

// ============================================
// 3. TIME-TO-HIRE REPORT
// ============================================
$stmt = $pdo->prepare("SELECT 
                      AVG(DATEDIFF(COALESCE(ja.reviewed_date, ja.applied_date), ja.applied_date)) as avg_time_to_review,
                      AVG(CASE WHEN ja.interview_date IS NOT NULL 
                          THEN DATEDIFF(ja.interview_date, ja.applied_date) 
                          ELSE NULL END) as avg_time_to_interview,
                      AVG(CASE WHEN ja.offer_sent_date IS NOT NULL 
                          THEN DATEDIFF(ja.offer_sent_date, ja.applied_date) 
                          ELSE NULL END) as avg_time_to_offer,
                      AVG(CASE WHEN ja.status = 'accepted' AND ja.reviewed_date IS NOT NULL 
                          THEN DATEDIFF(ja.reviewed_date, ja.applied_date) 
                          ELSE NULL END) as avg_time_to_hire
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?");
$stmt->execute([$company['id'], $start_date, $end_date]);
$time_to_hire = $stmt->fetch();

// Time-to-hire distribution
$stmt = $pdo->prepare("SELECT 
                      CASE 
                          WHEN DATEDIFF(COALESCE(ja.reviewed_date, ja.applied_date), ja.applied_date) <= 3 THEN '0-3 days'
                          WHEN DATEDIFF(COALESCE(ja.reviewed_date, ja.applied_date), ja.applied_date) <= 7 THEN '4-7 days'
                          WHEN DATEDIFF(COALESCE(ja.reviewed_date, ja.applied_date), ja.applied_date) <= 14 THEN '8-14 days'
                          WHEN DATEDIFF(COALESCE(ja.reviewed_date, ja.applied_date), ja.applied_date) <= 30 THEN '15-30 days'
                          ELSE '30+ days'
                      END as time_range,
                      COUNT(*) as count
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?
                      GROUP BY time_range");
$stmt->execute([$company['id'], $start_date, $end_date]);
$time_distribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// ============================================
// 4. JOB POST EFFECTIVENESS
// ============================================
$stmt = $pdo->prepare("SELECT 
                      jp.id,
                      jp.title,
                      jp.posted_date,
                      jp.status,
                      COUNT(DISTINCT ja.id) as total_applications,
                      COUNT(DISTINCT CASE WHEN ja.status = 'accepted' THEN ja.id END) as hired,
                      COUNT(DISTINCT CASE WHEN ja.status = 'rejected' THEN ja.id END) as rejected,
                      COUNT(DISTINCT CASE WHEN ja.interview_status = 'interviewed' THEN ja.id END) as interviewed,
                      ROUND(COUNT(DISTINCT CASE WHEN ja.status = 'accepted' THEN ja.id END) * 100.0 / NULLIF(COUNT(DISTINCT ja.id), 0), 2) as hire_rate
                      FROM job_postings jp 
                      LEFT JOIN job_applications ja ON jp.id = ja.job_id 
                      WHERE jp.company_id = ? AND DATE(jp.posted_date) BETWEEN ? AND ?
                      GROUP BY jp.id, jp.title, jp.posted_date, jp.status
                      ORDER BY total_applications DESC
                      LIMIT 20");
$stmt->execute([$company['id'], $start_date, $end_date]);
$job_effectiveness = $stmt->fetchAll();

// Job performance by category
$stmt = $pdo->prepare("SELECT 
                      COALESCE(jc.category_name, 'Uncategorized') as category,
                      COUNT(DISTINCT jp.id) as job_count,
                      COUNT(DISTINCT ja.id) as total_applications,
                      COUNT(DISTINCT CASE WHEN ja.status = 'accepted' THEN ja.id END) as hired
                      FROM job_postings jp 
                      LEFT JOIN job_categories jc ON jp.category_id = jc.id
                      LEFT JOIN job_applications ja ON jp.id = ja.job_id 
                      WHERE jp.company_id = ? AND DATE(jp.posted_date) BETWEEN ? AND ?
                      GROUP BY category
                      ORDER BY total_applications DESC");
$stmt->execute([$company['id'], $start_date, $end_date]);
$category_performance = $stmt->fetchAll();

// ============================================
// 5. CANDIDATE SOURCE REPORT
// ============================================
// Since we don't have explicit source tracking, we'll use application date patterns
$stmt = $pdo->prepare("SELECT 
                      DATE_FORMAT(ja.applied_date, '%Y-%m') as month,
                      COUNT(*) as applications
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?
                      GROUP BY month
                      ORDER BY month");
$stmt->execute([$company['id'], $start_date, $end_date]);
$source_by_month = $stmt->fetchAll();

// Applications by job
$stmt = $pdo->prepare("SELECT 
                      jp.title,
                      COUNT(ja.id) as application_count
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?
                      GROUP BY jp.id, jp.title
                      ORDER BY application_count DESC
                      LIMIT 10");
$stmt->execute([$company['id'], $start_date, $end_date]);
$source_by_job = $stmt->fetchAll();

// ============================================
// 6. DIVERSITY / SKILLS DISTRIBUTION
// ============================================
// Gender distribution
$stmt = $pdo->prepare("SELECT 
                      ep.sex,
                      COUNT(DISTINCT ja.id) as count
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?
                      GROUP BY ep.sex");
$stmt->execute([$company['id'], $start_date, $end_date]);
$gender_distribution = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Education distribution
$stmt = $pdo->prepare("SELECT 
                      ep.highest_education,
                      COUNT(DISTINCT ja.id) as count
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?
                      AND ep.highest_education IS NOT NULL AND ep.highest_education != ''
                      GROUP BY ep.highest_education
                      ORDER BY count DESC
                      LIMIT 10");
$stmt->execute([$company['id'], $start_date, $end_date]);
$education_distribution = $stmt->fetchAll();

// Experience level distribution
$stmt = $pdo->prepare("SELECT 
                      COALESCE(ep.experience_level, 'Not Specified') as experience_level,
                      COUNT(DISTINCT ja.id) as count
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?
                      GROUP BY experience_level
                      ORDER BY count DESC");
$stmt->execute([$company['id'], $start_date, $end_date]);
$experience_distribution = $stmt->fetchAll();

// Skills distribution
$stmt = $pdo->prepare("SELECT 
                      ep.skills,
                      COUNT(DISTINCT ja.id) as count
                      FROM job_applications ja 
                      JOIN job_postings jp ON ja.job_id = jp.id 
                      JOIN employee_profiles ep ON ja.employee_id = ep.id
                      WHERE jp.company_id = ? AND DATE(ja.applied_date) BETWEEN ? AND ?
                      AND ep.skills IS NOT NULL AND ep.skills != ''
                      GROUP BY ep.skills
                      ORDER BY count DESC
                      LIMIT 15");
$stmt->execute([$company['id'], $start_date, $end_date]);
$skills_data = $stmt->fetchAll();

// Process skills (they might be comma-separated)
$skills_distribution = [];
foreach ($skills_data as $row) {
    $skills = explode(',', $row['skills']);
    foreach ($skills as $skill) {
        $skill = trim($skill);
        if (!empty($skill)) {
            if (!isset($skills_distribution[$skill])) {
                $skills_distribution[$skill] = 0;
            }
            $skills_distribution[$skill] += $row['count'];
        }
    }
}
arsort($skills_distribution);
$top_skills = array_slice($skills_distribution, 0, 10, true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        :root {
            --wl-primary: #1e3a8a;
            --wl-primary-light: #3b82f6;
            --wl-accent: #60a5fa;
            --wl-success: #10b981;
            --wl-warning: #f59e0b;
            --wl-danger: #ef4444;
            --wl-info: #06b6d4;
            --wl-dark: #1e293b;
            --wl-light: #f8fafc;
            --wl-card: #ffffff;
            --wl-shadow: rgba(0, 0, 0, 0.1);
        }

        @media print {
            .sidebar, .no-print { display: none !important; }
        }

        body.employer-layout {
            background: #f8fafc;
            min-height: 100vh;
            overflow-x: hidden;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }

        .employer-main-content {
            min-height: 100vh;
            background: transparent;
            margin-left: 250px;
            padding: 20px 30px;
            width: calc(100% - 250px);
            box-sizing: border-box;
            overflow-x: hidden;
            color: #1e293b;
            max-width: 100%;
        }

        @media (max-width: 992px) {
            .employer-main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }
        }

        @media (min-width: 1400px) {
            .employer-main-content {
                padding: 30px 40px;
            }
        }

        /* Header Section */
        .reports-header {
            background: #ffffff;
            color: #0f172a;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            width: 100%;
            box-sizing: border-box;
        }

        .reports-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: visible;
            color: #0f172a;
        }

        .reports-header h1 i {
            color: var(--wl-primary-light);
        }

        .reports-header p {
            font-size: 1rem;
            margin: 0;
            color: #64748b;
        }

        .reports-header .btn {
            border-radius: 10px;
            font-weight: 600;
        }

        /* Filter Card */
        .filter-card {
            background: var(--wl-card);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            margin-bottom: 2rem;
            padding: 1.5rem;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .filter-card:hover {
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: #cbd5e1;
        }

        .filter-card .row {
            margin: 0;
        }

        .filter-card .row > * {
            padding-left: 10px;
            padding-right: 10px;
        }

        .filter-card .form-label {
            display: block;
            width: 100%;
        }

        .filter-card .form-control {
            width: 100%;
            box-sizing: border-box;
        }

        .filter-card .form-label {
            font-weight: 600;
            color: var(--wl-dark);
            margin-bottom: 0.5rem;
        }

        .filter-card .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .filter-card .form-control:focus {
            border-color: var(--wl-primary-light);
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
        }

        /* Ensure all content is visible */
        .report-section {
            margin-bottom: 2.5rem;
            width: 100%;
            overflow: visible;
        }

        .reports-layout {
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
            padding-bottom: 2rem;
        }

        .reports-layout .report-section {
            margin-bottom: 0;
        }

        .reports-layout .report-section:last-child {
            margin-bottom: 0;
        }

        .report-lead {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0 0 1.25rem;
            max-width: 42rem;
            line-height: 1.5;
        }

        .report-subtitle {
            font-size: 0.8125rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            margin: 0 0 0.75rem;
        }

        .report-subtitle i {
            opacity: 0.85;
        }

        .report-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .report-kpi-item {
            min-width: 0;
        }

        .report-kpi-grid .stat-card {
            margin-bottom: 0;
            height: 100%;
            min-height: 8.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .report-chart-shell {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem 1.25rem 1.25rem;
        }

        .report-chart-shell .chart-container {
            margin-bottom: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
        }

        .report-two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        .report-table-shell {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .report-table-shell .report-subtitle {
            padding: 1rem 1.25rem 0;
            margin-bottom: 0.5rem;
        }

        .report-table-shell .table-responsive {
            border-radius: 0;
        }

        .report-skills-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1.25rem;
        }

        .report-skills-panel {
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem 1rem 1.25rem;
        }

        .report-skills-panel .report-subtitle {
            margin-bottom: 0.65rem;
        }

        .report-skills-panel .chart-container {
            margin-bottom: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
        }

        .report-skills-wide {
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem 1rem 1.25rem;
        }

        .report-skills-wide .chart-container {
            margin-bottom: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
        }

        @media (max-width: 1199px) {
            .report-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991px) {
            .report-two-col {
                grid-template-columns: 1fr;
            }

            .report-skills-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575px) {
            .report-kpi-grid {
                grid-template-columns: 1fr;
            }
        }

        .report-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            margin-bottom: 1.5rem;
            overflow: visible;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
            background: var(--wl-card);
            width: 100%;
        }

        .report-card:hover {
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            border-color: #cbd5e1;
        }

        .report-card .card-header {
            background: #f8fafc !important;
            color: #0f172a !important;
            padding: 1rem 1.5rem;
            border: none;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 1rem;
        }

        .report-card .card-header h4,
        .report-card .card-header h4 i {
            color: inherit !important;
        }

        .report-card .card-header.bg-primary {
            background: #eff6ff !important;
            color: #1e3a8a !important;
            border-bottom-color: #bfdbfe;
        }

        .report-card .card-header.bg-primary h4 i {
            color: #2563eb !important;
        }

        .report-card .card-header.bg-info {
            background: #ecfeff !important;
            color: #164e63 !important;
            border-bottom-color: #a5f3fc;
        }

        .report-card .card-header.bg-info h4 i {
            color: #0891b2 !important;
        }

        .report-card .card-header.bg-success {
            background: #ecfdf5 !important;
            color: #065f46 !important;
            border-bottom-color: #a7f3d0;
        }

        .report-card .card-header.bg-success h4 i {
            color: #059669 !important;
        }

        .report-card .card-header.bg-warning {
            background: #fffbeb !important;
            color: #78350f !important;
            border-bottom-color: #fde68a;
        }

        .report-card .card-header.bg-warning h4 i {
            color: #d97706 !important;
        }

        .report-card .card-header.bg-secondary {
            background: #f1f5f9 !important;
            color: #334155 !important;
            border-bottom-color: #e2e8f0;
        }

        .report-card .card-header.bg-secondary h4 i {
            color: #64748b !important;
        }

        .report-card .card-header.bg-dark {
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border-bottom-color: #cbd5e1;
        }

        .report-card .card-header.bg-dark h4 i {
            color: #475569 !important;
        }

        .report-card .card-body {
            padding: 2rem;
            color: #1e293b;
            background: #ffffff;
        }

        .report-card .card-body p,
        .report-card .card-body td,
        .report-card .card-body th,
        .report-card .card-body strong,
        .report-card .card-body span {
            color: inherit;
        }

        /* Ensure text visibility in stat cards */
        .stat-card * {
            color: white !important;
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 10;
            color: white !important;
        }

        .stat-card p {
            font-size: 1rem;
            margin-bottom: 0.25rem;
            opacity: 1;
            position: relative;
            z-index: 10;
            color: white !important;
            font-weight: 500;
        }

        .stat-card small {
            font-size: 0.9rem;
            opacity: 0.95;
            position: relative;
            z-index: 10;
            color: rgba(255, 255, 255, 0.92) !important;
            font-weight: 400;
        }

        .stat-card.success {
            background: #059669;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .stat-card.success:hover {
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
        }

        .stat-card.warning {
            background: #d97706;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .stat-card.warning:hover {
            box-shadow: 0 4px 12px rgba(217, 119, 6, 0.25);
        }

        .stat-card.info {
            background: #2563eb;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .stat-card.danger {
            background: #dc2626;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }

        .stat-card.danger:hover {
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 320px;
            margin-bottom: 1.5rem;
            background: var(--wl-light);
            border-radius: 10px;
            padding: 1rem;
            width: 100%;
            min-height: 250px;
            overflow: visible;
        }

        .chart-container canvas {
            max-width: 100% !important;
            height: auto !important;
        }

        /* Funnel Bars */
        .funnel-container {
            padding: 1rem 0;
            width: 100%;
            overflow: visible;
        }

        .funnel-bar {
            background: #2563eb;
            color: white;
            padding: 1.25rem 1.5rem;
            margin-bottom: 0.75rem;
            border-radius: 10px;
            text-align: center;
            transition: box-shadow 0.2s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            position: relative;
            overflow: visible;
            min-width: 200px;
            width: 100%;
            display: block;
        }

        .funnel-bar:hover {
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .funnel-bar strong {
            font-size: 1.1rem;
            display: block;
            margin-bottom: 0.5rem;
            color: white !important;
            font-weight: 600;
        }

        .funnel-bar small {
            color: rgba(255,255,255,0.95) !important;
            font-size: 0.9rem;
        }

        .funnel-bar:nth-child(1) {
            background: #4f46e5;
        }
        .funnel-bar:nth-child(2) {
            background: #2563eb;
        }
        .funnel-bar:nth-child(3) {
            background: #059669;
        }
        .funnel-bar:nth-child(4) {
            background: #d97706;
        }
        .funnel-bar:nth-child(5) {
            background: #dc2626;
        }

        /* Tables */
        .table-responsive {
            max-height: 450px;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 10px;
            width: 100%;
            display: block;
        }

        .table-responsive table {
            width: 100%;
            min-width: 600px;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: #f8fafc !important;
            color: #0f172a !important;
        }

        .table thead th {
            border: none;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.04em;
            color: #475569 !important;
        }

        .table tbody tr {
            transition: background-color 0.15s ease;
        }

        .table tbody tr:hover {
            background-color: #f1f5f9;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            border-radius: 8px;
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: background-color 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .btn-primary,
        .btn-success {
            border: none;
        }

        .btn-success {
            background: #059669 !important;
            box-shadow: none;
        }

        .btn-success:hover {
            background: #047857 !important;
            box-shadow: 0 2px 8px rgba(5, 150, 105, 0.3);
        }

        .btn-primary {
            background: #2563eb !important;
            box-shadow: none;
        }

        .btn-primary:hover {
            background: #1d4ed8 !important;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
        }

        /* Section Headings */
        .report-card h5 {
            color: #1e293b !important;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--wl-light);
            font-size: 1.1rem;
        }

        /* Table text visibility */
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            color: #1e293b !important;
        }

        .table tbody td strong {
            color: #1e293b !important;
            font-weight: 600;
        }

        /* Badge text */
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            border-radius: 8px;
            color: white !important;
        }

        .badge.text-dark {
            color: #1e293b !important;
        }

        /* Form labels */
        .form-label {
            color: #1e293b !important;
            font-weight: 600;
        }

        /* Funnel bar text */
        .funnel-bar strong {
            font-size: 1.1rem;
            display: block;
            margin-bottom: 0.5rem;
            color: white !important;
            font-weight: 600;
        }

        .funnel-bar small {
            color: rgba(255,255,255,0.95) !important;
            font-size: 0.9rem;
        }

        /* Fix container overflow */
        .container-fluid, .container {
            overflow-x: visible;
        }

        /* Ensure cards don't get cut off */
        .card-body {
            overflow: visible !important;
        }

        /* Stat Cards - Ensure full visibility */
        .stat-cards-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        .stat-cards-row .col-md-3,
        .stat-cards-row .col-sm-6,
        .stat-cards-row .col-12 {
            flex: 1 1 calc(25% - 1rem);
            min-width: 200px;
            max-width: 100%;
            box-sizing: border-box;
            padding: 0 0.5rem;
        }

        .stat-card {
            background: #2563eb;
            color: white;
            border-radius: 12px;
            padding: 1.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            transition: box-shadow 0.2s ease;
            position: relative;
            overflow: visible;
            width: 100%;
            box-sizing: border-box;
        }

        .stat-card:hover {
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stat-cards-row .col-md-3 {
                flex: 1 1 calc(50% - 1rem);
            }
        }

        @media (max-width: 992px) {
            .employer-main-content {
                margin-left: 0;
                width: 100%;
                padding: 15px;
            }

            .reports-header {
                padding: 1.25rem 1.5rem;
            }

            .reports-header h1 {
                font-size: 1.75rem;
                white-space: normal;
            }
        }

        @media (max-width: 768px) {
            .employer-main-content {
                padding: 1rem;
            }

            .reports-header {
                padding: 1.5rem;
            }

            .reports-header h1 {
                font-size: 1.5rem;
            }

            .stat-card h3 {
                font-size: 2rem;
            }

            .chart-container {
                height: 250px;
                padding: 0.5rem;
            }

            .stat-cards-row .col-md-3 {
                flex: 1 1 100%;
                padding: 0;
            }

            .report-card .card-body {
                padding: 1.5rem;
            }

            .funnel-bar {
                padding: 1rem;
                font-size: 0.9rem;
            }

            .table-responsive {
                max-height: 300px;
            }
        }

        @media (max-width: 576px) {
            .employer-main-content {
                padding: 0.75rem;
            }

            .reports-header {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .filter-card {
                padding: 1rem;
            }

            .chart-container {
                height: 200px;
            }
        }

        /* Scrollbar Styling */
        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: var(--wl-light);
            border-radius: 10px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: var(--wl-primary-light);
            border-radius: 10px;
        }

        /* Ensure all text is visible */
        .text-muted {
            color: #64748b !important;
        }

        /* Ensure form controls are visible */
        .form-control {
            color: #1e293b !important;
            background-color: #ffffff !important;
        }

        /* Ensure all paragraphs and text elements are visible */
        .card-body p, .card-body span, .card-body div, .card-body td, .card-body th, .card-body label {
            color: #1e293b !important;
        }

        .card-body strong {
            color: #1e293b !important;
        }

        /* Ensure table text is visible */
        .table tbody {
            color: #1e293b !important;
        }

        /* Ensure stat card text is always white */
        .stat-card, .stat-card *, .stat-card h3, .stat-card p, .stat-card small {
            color: white !important;
        }
    </style>
<body class="employer-layout">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="employer-main-content">
        <!-- Header Section -->
        <div class="reports-header no-print">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h1><i class="fas fa-chart-line me-2"></i>Reports & Analytics</h1>
                    <p class="mb-0"><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($company['company_name']); ?></p>
                </div>
                <div class="mt-3 mt-md-0">
                    <button type="button" class="btn btn-outline-secondary me-2" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf me-2"></i>Download PDF
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-2"></i>Download Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card no-print">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-calendar-alt me-2 text-primary"></i>Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><i class="fas fa-calendar-check me-2 text-primary"></i>End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="fas fa-filter me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>

        <div class="reports-layout">

        <!-- 1. HIRING PERFORMANCE REPORT -->
        <div class="report-section">
            <div class="card report-card">
                <div class="card-header bg-primary">
                    <h4 class="mb-0"><i class="fas fa-bullseye me-2"></i>Hiring Performance Report</h4>
                </div>
                <div class="card-body">
                    <p class="report-lead">Application volume, outcomes, and the six‑month hiring trend for the dates you selected above.</p>
                    <div class="report-kpi-grid">
                        <div class="report-kpi-item">
                            <div class="stat-card info">
                                <h3><?php echo number_format($hiring_stats['total_applications']); ?></h3>
                                <p class="mb-0">Total Applications</p>
                            </div>
                        </div>
                        <div class="report-kpi-item">
                            <div class="stat-card success">
                                <h3><?php echo number_format($hiring_stats['hired']); ?></h3>
                                <p class="mb-0">Hired Candidates</p>
                                <small><?php echo $hiring_stats['hire_rate']; ?>% Hire Rate</small>
                            </div>
                        </div>
                        <div class="report-kpi-item">
                            <div class="stat-card danger">
                                <h3><?php echo number_format($hiring_stats['rejected']); ?></h3>
                                <p class="mb-0">Rejected</p>
                                <small><?php echo $hiring_stats['rejection_rate']; ?>% Rejection Rate</small>
                            </div>
                        </div>
                        <div class="report-kpi-item">
                            <div class="stat-card warning">
                                <h3><?php echo number_format($hiring_stats['pending']); ?></h3>
                                <p class="mb-0">Pending Review</p>
                            </div>
                        </div>
                    </div>
                    <div class="report-chart-shell mt-4">
                        <h5 class="report-subtitle"><i class="fas fa-chart-area me-2 text-primary"></i>Hiring trend (last 6 months)</h5>
                        <div class="chart-container">
                            <canvas id="hiringPerformanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. APPLICATION FUNNEL REPORT -->
        <div class="report-section">
            <div class="card report-card">
                <div class="card-header bg-info">
                    <h4 class="mb-0"><i class="fas fa-filter me-2"></i>Application Funnel Report</h4>
                </div>
                <div class="card-body">
                    <p class="report-lead">Stage counts and conversion view: bars show relative width; chart compares absolute numbers.</p>
                    <div class="report-two-col">
                        <div>
                            <h5 class="report-subtitle"><i class="fas fa-stream me-2"></i>Pipeline stages</h5>
                            <div class="funnel-container">
                                <div class="funnel-bar" style="width: 100%;">
                                    <strong><i class="fas fa-user-check me-2"></i>Applied</strong>
                                    <?php echo number_format($funnel_data['applied']); ?> candidates
                                </div>
                                <div class="funnel-bar" style="width: <?php echo min($funnel_rates['review_rate'], 100); ?>%;">
                                    <strong><i class="fas fa-eye me-2"></i>Reviewed</strong>
                                    <?php echo number_format($funnel_data['reviewed']); ?> 
                                    <small>(<?php echo $funnel_rates['review_rate']; ?>%)</small>
                                </div>
                                <div class="funnel-bar" style="width: <?php echo min(($funnel_data['reviewed'] > 0 ? ($funnel_data['interviewed'] / $funnel_data['reviewed']) * 100 : 0), 100); ?>%;">
                                    <strong><i class="fas fa-comments me-2"></i>Interviewed</strong>
                                    <?php echo number_format($funnel_data['interviewed']); ?> 
                                    <small>(<?php echo $funnel_rates['interview_rate']; ?>%)</small>
                                </div>
                                <div class="funnel-bar" style="width: <?php echo min(($funnel_data['interviewed'] > 0 ? ($funnel_data['offered'] / $funnel_data['interviewed']) * 100 : 0), 100); ?>%;">
                                    <strong><i class="fas fa-handshake me-2"></i>Offered</strong>
                                    <?php echo number_format($funnel_data['offered']); ?> 
                                    <small>(<?php echo $funnel_rates['offer_rate']; ?>%)</small>
                                </div>
                                <div class="funnel-bar" style="width: <?php echo min(($funnel_data['offered'] > 0 ? ($funnel_data['hired'] / $funnel_data['offered']) * 100 : 0), 100); ?>%;">
                                    <strong><i class="fas fa-check-circle me-2"></i>Hired</strong>
                                    <?php echo number_format($funnel_data['hired']); ?> 
                                    <small>(<?php echo $funnel_rates['hire_rate']; ?>%)</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h5 class="report-subtitle"><i class="fas fa-chart-bar me-2"></i>Funnel comparison</h5>
                            <div class="chart-container">
                                <canvas id="funnelChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. TIME-TO-HIRE REPORT -->
        <div class="report-section">
            <div class="card report-card">
                <div class="card-header bg-success">
                    <h4 class="mb-0"><i class="fas fa-clock me-2"></i>Time-to-Hire Report</h4>
                </div>
                <div class="card-body">
                    <p class="report-lead">Average days between milestones, plus how hires cluster by time-to-review buckets.</p>
                    <div class="report-kpi-grid">
                        <div class="report-kpi-item">
                            <div class="stat-card info">
                                <h3><?php echo round($time_to_hire['avg_time_to_review'] ?? 0, 1); ?></h3>
                                <p class="mb-0"><i class="fas fa-eye me-2"></i>Avg Days to Review</p>
                            </div>
                        </div>
                        <div class="report-kpi-item">
                            <div class="stat-card">
                                <h3><?php echo round($time_to_hire['avg_time_to_interview'] ?? 0, 1); ?></h3>
                                <p class="mb-0"><i class="fas fa-comments me-2"></i>Avg Days to Interview</p>
                            </div>
                        </div>
                        <div class="report-kpi-item">
                            <div class="stat-card warning">
                                <h3><?php echo round($time_to_hire['avg_time_to_offer'] ?? 0, 1); ?></h3>
                                <p class="mb-0"><i class="fas fa-handshake me-2"></i>Avg Days to Offer</p>
                            </div>
                        </div>
                        <div class="report-kpi-item">
                            <div class="stat-card success">
                                <h3><?php echo round($time_to_hire['avg_time_to_hire'] ?? 0, 1); ?></h3>
                                <p class="mb-0"><i class="fas fa-check-circle me-2"></i>Avg Days to Hire</p>
                            </div>
                        </div>
                    </div>
                    <div class="report-chart-shell mt-4">
                        <h5 class="report-subtitle"><i class="fas fa-chart-pie me-2 text-success"></i>Time-to-review distribution</h5>
                        <div class="chart-container">
                            <canvas id="timeToHireChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. JOB POST EFFECTIVENESS -->
        <div class="report-section">
            <div class="card report-card">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><i class="fas fa-briefcase me-2"></i>Job Post Effectiveness</h4>
                </div>
                <div class="card-body">
                    <p class="report-lead">Per-job demand (applications, interviews, hires) and category mix, then a sortable-style detail table.</p>
                    <div class="report-two-col">
                        <div>
                            <h5 class="report-subtitle"><i class="fas fa-chart-line me-2"></i>Top jobs (applications vs hired)</h5>
                            <div class="chart-container">
                                <canvas id="jobEffectivenessChart"></canvas>
                            </div>
                        </div>
                        <div>
                            <h5 class="report-subtitle"><i class="fas fa-tags me-2"></i>Applications by category</h5>
                            <div class="chart-container">
                                <canvas id="categoryPerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="report-table-shell mt-4">
                        <h5 class="report-subtitle"><i class="fas fa-table me-2"></i>Job listing details</h5>
                        <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-briefcase me-2"></i>Job Title</th>
                                    <th><i class="fas fa-calendar me-2"></i>Posted Date</th>
                                    <th><i class="fas fa-file-alt me-2"></i>Applications</th>
                                    <th><i class="fas fa-comments me-2"></i>Interviewed</th>
                                    <th><i class="fas fa-check-circle me-2"></i>Hired</th>
                                    <th><i class="fas fa-percentage me-2"></i>Hire Rate</th>
                                    <th><i class="fas fa-info-circle me-2"></i>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($job_effectiveness)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No job data available for the selected period</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($job_effectiveness as $job): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($job['title']); ?></strong></td>
                                    <td><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></td>
                                    <td><span class="badge bg-info"><?php echo $job['total_applications']; ?></span></td>
                                    <td><span class="badge bg-warning text-dark"><?php echo $job['interviewed']; ?></span></td>
                                    <td><span class="badge bg-success"><?php echo $job['hired']; ?></span></td>
                                    <td><strong><?php echo $job['hire_rate']; ?>%</strong></td>
                                    <td><span class="badge bg-<?php echo $job['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($job['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. CANDIDATE SOURCE REPORT -->
        <div class="report-section">
            <div class="card report-card">
                <div class="card-header bg-secondary">
                    <h4 class="mb-0"><i class="fas fa-users me-2"></i>Candidate Source Report</h4>
                </div>
                <div class="card-body">
                    <p class="report-lead">Application volume over time alongside which job titles attract the most applicants.</p>
                    <div class="report-two-col">
                        <div>
                            <h5 class="report-subtitle"><i class="fas fa-calendar-alt me-2"></i>Applications by month</h5>
                            <div class="chart-container">
                                <canvas id="sourceByMonthChart"></canvas>
                            </div>
                        </div>
                        <div class="report-table-shell">
                            <h5 class="report-subtitle"><i class="fas fa-fire me-2"></i>Top jobs by applications</h5>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Job Title</th>
                                            <th class="text-end">Applications</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($source_by_job as $job): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($job['title']); ?></td>
                                            <td class="text-end"><span class="badge bg-primary"><?php echo $job['application_count']; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. DIVERSITY / SKILLS DISTRIBUTION -->
        <div class="report-section">
            <div class="card report-card">
                <div class="card-header bg-dark">
                    <h4 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Diversity & Skills Distribution</h4>
                </div>
                <div class="card-body">
                    <p class="report-lead">Demographics and profile signals from applicants in the selected period; skills chart uses the tallest canvas for readability.</p>
                    <div class="report-skills-grid">
                        <div class="report-skills-panel">
                            <h5 class="report-subtitle"><i class="fas fa-venus-mars me-2 text-primary"></i>Gender distribution</h5>
                            <div class="chart-container">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                        <div class="report-skills-panel">
                            <h5 class="report-subtitle"><i class="fas fa-graduation-cap me-2 text-success"></i>Education level</h5>
                            <div class="chart-container">
                                <canvas id="educationChart"></canvas>
                            </div>
                        </div>
                        <div class="report-skills-panel">
                            <h5 class="report-subtitle"><i class="fas fa-briefcase me-2 text-warning"></i>Experience level</h5>
                            <div class="chart-container">
                                <canvas id="experienceChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="report-skills-wide mt-4">
                        <h5 class="report-subtitle"><i class="fas fa-code me-2 text-info"></i>Top skills</h5>
                        <div class="chart-container" style="height: 400px;">
                            <canvas id="skillsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div><!-- .reports-layout -->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart data from PHP
        const chartData = {
            hiringPerformance: {
                labels: [<?php 
                    for ($i = 5; $i >= 0; $i--) {
                        echo "'" . date('M', strtotime("-$i months")) . "'";
                        if ($i > 0) echo ',';
                    }
                ?>],
                data: [<?php echo implode(',', $monthly_hiring); ?>]
            },
            funnel: {
                labels: ['Applied', 'Reviewed', 'Interviewed', 'Offered', 'Hired'],
                data: [<?php echo implode(',', array_values($funnel_data)); ?>]
            },
            timeToHire: {
                labels: [<?php 
                    $labels = ['0-3 days', '4-7 days', '8-14 days', '15-30 days', '30+ days'];
                    foreach ($labels as $i => $label) {
                        echo "'$label'";
                        if ($i < count($labels) - 1) echo ',';
                    }
                ?>],
                data: [<?php 
                    $values = [];
                    foreach ($labels as $label) {
                        $values[] = $time_distribution[$label] ?? 0;
                    }
                    echo implode(',', $values);
                ?>]
            },
            jobEffectiveness: {
                labels: [<?php 
                    $jobLabels = array_slice(array_column($job_effectiveness, 'title'), 0, 5);
                    if (empty($jobLabels)) {
                        echo "'No Data'";
                    } else {
                        foreach ($jobLabels as $i => $label) {
                            echo "'" . htmlspecialchars($label, ENT_QUOTES) . "'";
                            if ($i < count($jobLabels) - 1) echo ',';
                        }
                    }
                ?>],
                applications: [<?php 
                    $jobApps = array_slice(array_column($job_effectiveness, 'total_applications'), 0, 5);
                    echo empty($jobApps) ? '0' : implode(',', $jobApps);
                ?>],
                hired: [<?php 
                    $jobHired = array_slice(array_column($job_effectiveness, 'hired'), 0, 5);
                    echo empty($jobHired) ? '0' : implode(',', $jobHired);
                ?>]
            },
            categoryPerformance: {
                labels: [<?php 
                    if (empty($category_performance)) {
                        echo "'No Data'";
                    } else {
                        foreach ($category_performance as $i => $cat) {
                            echo "'" . htmlspecialchars($cat['category'], ENT_QUOTES) . "'";
                            if ($i < count($category_performance) - 1) echo ',';
                        }
                    }
                ?>],
                applications: [<?php 
                    if (empty($category_performance)) {
                        echo '0';
                    } else {
                        foreach ($category_performance as $i => $cat) {
                            echo $cat['total_applications'];
                            if ($i < count($category_performance) - 1) echo ',';
                        }
                    }
                ?>]
            },
            sourceByMonth: {
                labels: [<?php 
                    if (empty($source_by_month)) {
                        echo "'No Data'";
                    } else {
                        foreach ($source_by_month as $i => $month) {
                            echo "'" . date('M Y', strtotime($month['month'] . '-01')) . "'";
                            if ($i < count($source_by_month) - 1) echo ',';
                        }
                    }
                ?>],
                data: [<?php 
                    if (empty($source_by_month)) {
                        echo '0';
                    } else {
                        foreach ($source_by_month as $i => $month) {
                            echo $month['applications'];
                            if ($i < count($source_by_month) - 1) echo ',';
                        }
                    }
                ?>]
            },
            gender: {
                labels: [<?php 
                    if (empty($gender_distribution)) {
                        echo "'No Data'";
                    } else {
                        foreach ($gender_distribution as $gender => $count) {
                            echo "'$gender'";
                            if ($gender !== array_key_last($gender_distribution)) echo ',';
                        }
                    }
                ?>],
                data: [<?php echo empty($gender_distribution) ? '0' : implode(',', array_values($gender_distribution)); ?>]
            },
            education: {
                labels: [<?php 
                    if (empty($education_distribution)) {
                        echo "'No Data'";
                    } else {
                        foreach ($education_distribution as $i => $edu) {
                            echo "'" . htmlspecialchars($edu['highest_education'], ENT_QUOTES) . "'";
                            if ($i < count($education_distribution) - 1) echo ',';
                        }
                    }
                ?>],
                data: [<?php 
                    if (empty($education_distribution)) {
                        echo '0';
                    } else {
                        foreach ($education_distribution as $i => $edu) {
                            echo $edu['count'];
                            if ($i < count($education_distribution) - 1) echo ',';
                        }
                    }
                ?>]
            },
            experience: {
                labels: [<?php 
                    if (empty($experience_distribution)) {
                        echo "'No Data'";
                    } else {
                        foreach ($experience_distribution as $i => $exp) {
                            echo "'" . htmlspecialchars($exp['experience_level'], ENT_QUOTES) . "'";
                            if ($i < count($experience_distribution) - 1) echo ',';
                        }
                    }
                ?>],
                data: [<?php 
                    if (empty($experience_distribution)) {
                        echo '0';
                    } else {
                        foreach ($experience_distribution as $i => $exp) {
                            echo $exp['count'];
                            if ($i < count($experience_distribution) - 1) echo ',';
                        }
                    }
                ?>]
            },
            skills: {
                labels: [<?php 
                    if (empty($top_skills)) {
                        echo "'No Data'";
                    } else {
                        $skillLabels = array_keys($top_skills);
                        foreach ($skillLabels as $i => $skill) {
                            echo "'" . htmlspecialchars($skill, ENT_QUOTES) . "'";
                            if ($i < count($skillLabels) - 1) echo ',';
                        }
                    }
                ?>],
                data: [<?php echo empty($top_skills) ? '0' : implode(',', array_values($top_skills)); ?>]
            }
        };

        // Initialize Charts
        function initCharts() {
            // Hiring Performance Chart
            new Chart(document.getElementById('hiringPerformanceChart'), {
                type: 'line',
                data: {
                    labels: chartData.hiringPerformance.labels,
                    datasets: [{
                        label: 'Hired Candidates',
                        data: chartData.hiringPerformance.data,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.15)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
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

            // Funnel Chart
            new Chart(document.getElementById('funnelChart'), {
                type: 'bar',
                data: {
                    labels: chartData.funnel.labels,
                    datasets: [{
                        label: 'Candidates',
                        data: chartData.funnel.data,
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            '#667eea',
                            '#3b82f6',
                            '#10b981',
                            '#f59e0b',
                            '#ef4444'
                        ],
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
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

            // Time to Hire Chart
            new Chart(document.getElementById('timeToHireChart'), {
                type: 'bar',
                data: {
                    labels: chartData.timeToHire.labels,
                    datasets: [{
                        label: 'Applications',
                        data: chartData.timeToHire.data,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: '#3b82f6',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
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

            // Job Effectiveness Chart
            new Chart(document.getElementById('jobEffectivenessChart'), {
                type: 'bar',
                data: {
                    labels: chartData.jobEffectiveness.labels,
                    datasets: [
                        {
                            label: 'Applications',
                            data: chartData.jobEffectiveness.applications,
                            backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            borderColor: '#3b82f6',
                            borderWidth: 2,
                            borderRadius: 8
                        },
                        {
                            label: 'Hired',
                            data: chartData.jobEffectiveness.hired,
                            backgroundColor: 'rgba(16, 185, 129, 0.8)',
                            borderColor: '#10b981',
                            borderWidth: 2,
                            borderRadius: 8
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        },
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
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

            // Category Performance Chart
            new Chart(document.getElementById('categoryPerformanceChart'), {
                type: 'doughnut',
                data: {
                    labels: chartData.categoryPerformance.labels,
                    datasets: [{
                        data: chartData.categoryPerformance.applications,
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(118, 75, 162, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(6, 182, 212, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    }
                }
            });

            // Source by Month Chart
            new Chart(document.getElementById('sourceByMonthChart'), {
                type: 'line',
                data: {
                    labels: chartData.sourceByMonth.labels,
                    datasets: [{
                        label: 'Applications',
                        data: chartData.sourceByMonth.data,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.15)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
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

            // Gender Chart
            new Chart(document.getElementById('genderChart'), {
                type: 'pie',
                data: {
                    labels: chartData.gender.labels,
                    datasets: [{
                        data: chartData.gender.data,
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(236, 72, 153, 0.8)'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    }
                }
            });

            // Education Chart
            new Chart(document.getElementById('educationChart'), {
                type: 'doughnut',
                data: {
                    labels: chartData.education.labels,
                    datasets: [{
                        data: chartData.education.data,
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(118, 75, 162, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(6, 182, 212, 0.8)'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    }
                }
            });

            // Experience Chart
            new Chart(document.getElementById('experienceChart'), {
                type: 'bar',
                data: {
                    labels: chartData.experience.labels,
                    datasets: [{
                        label: 'Candidates',
                        data: chartData.experience.data,
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
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

            // Skills Chart
            new Chart(document.getElementById('skillsChart'), {
                type: 'bar',
                data: {
                    labels: chartData.skills.labels,
                    datasets: [{
                        label: 'Candidates',
                        data: chartData.skills.data,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Export to PDF
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            doc.setFontSize(18);
            doc.text('Hiring Reports & Analytics', 14, 20);
            doc.setFontSize(12);
            doc.text('Company: <?php echo htmlspecialchars($company['company_name']); ?>', 14, 30);
            doc.text('Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>', 14, 37);
            
            let yPos = 50;
            doc.setFontSize(14);
            doc.text('Hiring Performance', 14, yPos);
            yPos += 10;
            doc.setFontSize(10);
            doc.text(`Total Applications: ${chartData.funnel.data[0]}`, 14, yPos);
            yPos += 7;
            doc.text(`Hired: ${chartData.funnel.data[4]}`, 14, yPos);
            yPos += 7;
            doc.text(`Hire Rate: <?php echo $hiring_stats['hire_rate']; ?>%`, 14, yPos);
            
            doc.save('hiring-report-<?php echo date('Y-m-d'); ?>.pdf');
        }

        // Export to Excel
        function exportToExcel() {
            const wb = XLSX.utils.book_new();
            
            // Hiring Performance Sheet
            const hiringData = [
                ['Hiring Performance Report'],
                ['Company', '<?php echo htmlspecialchars($company['company_name']); ?>'],
                ['Period', '<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>'],
                [],
                ['Metric', 'Value'],
                ['Total Applications', <?php echo $hiring_stats['total_applications']; ?>],
                ['Hired', <?php echo $hiring_stats['hired']; ?>],
                ['Rejected', <?php echo $hiring_stats['rejected']; ?>],
                ['Pending', <?php echo $hiring_stats['pending']; ?>],
                ['Hire Rate', '<?php echo $hiring_stats['hire_rate']; ?>%']
            ];
            const ws1 = XLSX.utils.aoa_to_sheet(hiringData);
            XLSX.utils.book_append_sheet(wb, ws1, 'Hiring Performance');
            
            // Job Effectiveness Sheet
            const jobData = [['Job Title', 'Posted Date', 'Applications', 'Interviewed', 'Hired', 'Hire Rate', 'Status']];
            <?php foreach ($job_effectiveness as $job): ?>
            jobData.push([
                '<?php echo htmlspecialchars($job['title']); ?>',
                '<?php echo date('Y-m-d', strtotime($job['posted_date'])); ?>',
                <?php echo $job['total_applications']; ?>,
                <?php echo $job['interviewed']; ?>,
                <?php echo $job['hired']; ?>,
                '<?php echo $job['hire_rate']; ?>%',
                '<?php echo ucfirst($job['status']); ?>'
            ]);
            <?php endforeach; ?>
            const ws2 = XLSX.utils.aoa_to_sheet(jobData);
            XLSX.utils.book_append_sheet(wb, ws2, 'Job Effectiveness');
            
            XLSX.writeFile(wb, 'hiring-report-<?php echo date('Y-m-d'); ?>.xlsx');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', initCharts);
    </script>
</body>
</html>
