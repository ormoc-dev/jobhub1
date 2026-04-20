<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Note: Table employer_reports is defined in sql/all_additional_tables.sql

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_company') {
        $companyId = (int)$_POST['company_id'];
        $stmt = $pdo->prepare("UPDATE companies c JOIN users u ON c.user_id = u.id 
                             SET c.status = 'active', u.status = 'active', u.profile_picture = c.company_logo 
                             WHERE c.id = ?");
        $stmt->execute([$companyId]);
        $message = 'Company approved successfully! They can now sign in and post jobs.';
        
    } elseif ($action === 'reject_company') {
        $companyId = (int)$_POST['company_id'];
        $stmt = $pdo->prepare("UPDATE companies c JOIN users u ON c.user_id = u.id 
                             SET c.status = 'rejected', u.status = 'suspended' 
                             WHERE c.id = ?");
        $stmt->execute([$companyId]);
        $message = 'Company rejected.';
        
    } elseif ($action === 'suspend_company') {
        $companyId = (int)$_POST['company_id'];
        $stmt = $pdo->prepare("UPDATE companies c JOIN users u ON c.user_id = u.id 
                             SET c.status = 'suspended', u.status = 'suspended' 
                             WHERE c.id = ?");
        $stmt->execute([$companyId]);
        $message = 'Company suspended successfully.';
        
    } elseif ($action === 'activate_company') {
        $companyId = (int)$_POST['company_id'];
        $stmt = $pdo->prepare("UPDATE companies c JOIN users u ON c.user_id = u.id 
                             SET c.status = 'active', u.status = 'active' 
                             WHERE c.id = ?");
        $stmt->execute([$companyId]);
        $message = 'Company activated successfully.';
        
    } elseif ($action === 'delete_company') {
        $companyId = (int)$_POST['company_id'];
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ?");
        $stmt->execute([$companyId]);
        $userId = $stmt->fetchColumn();
        if ($userId) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
        }
        $message = 'Company deleted successfully.';
        
    } elseif ($action === 'feature_company') {
        $companyId = (int)$_POST['company_id'];
        $featuredUntil = $_POST['featured_until'] ?? date('Y-m-d', strtotime('+30 days'));
        $stmt = $pdo->prepare("UPDATE companies SET featured = 'yes', featured_until = ? WHERE id = ?");
        $stmt->execute([$featuredUntil, $companyId]);
        $message = 'Company is now featured!';
        
    } elseif ($action === 'unfeature_company') {
        $companyId = (int)$_POST['company_id'];
        $stmt = $pdo->prepare("UPDATE companies SET featured = 'no', featured_until = NULL WHERE id = ?");
        $stmt->execute([$companyId]);
        $message = 'Company feature removed.';
        
    } elseif ($action === 'resolve_report') {
        $reportId = (int)$_POST['report_id'];
        $resolution = sanitizeInput($_POST['resolution']);
        $adminNotes = sanitizeInput($_POST['admin_notes']);
        $stmt = $pdo->prepare("UPDATE employer_reports SET status = ?, admin_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?");
        $stmt->execute([$resolution, $adminNotes, $_SESSION['user_id'], $reportId]);
        $message = 'Report has been updated.';
        
    } elseif ($action === 'delete_report') {
        $reportId = (int)$_POST['report_id'];
        $stmt = $pdo->prepare("DELETE FROM employer_reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $message = 'Report deleted.';
    }
}

// Current tab
$currentTab = $_GET['tab'] ?? 'profiles';
$search = $_GET['search'] ?? '';

// Build search clause
$searchClause = "";
$searchParams = [];
if (!empty($search)) {
    $searchClause = " AND (c.company_name LIKE ? OR c.contact_email LIKE ? OR c.location_address LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%$search%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Get data based on tab
$companies = [];
$reports = [];

switch ($currentTab) {
    case 'profiles':
        $sql = "SELECT c.*, u.username, u.email, u.status as user_status, u.created_at as user_created,
                (SELECT COUNT(*) FROM job_postings WHERE company_id = c.id) as job_count,
                (SELECT COUNT(*) FROM job_applications ja JOIN job_postings jp ON ja.job_id = jp.id WHERE jp.company_id = c.id) as application_count
                FROM companies c 
                JOIN users u ON c.user_id = u.id 
                WHERE 1=1 $searchClause
                ORDER BY c.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($searchParams);
        $companies = $stmt->fetchAll();
        break;
        
    case 'verification':
        $sql = "SELECT c.*, u.username, u.email, u.status as user_status, u.created_at as user_created
                FROM companies c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.status = 'pending' $searchClause
                ORDER BY c.created_at ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($searchParams);
        $companies = $stmt->fetchAll();
        break;
        
    case 'reports':
        $sql = "SELECT er.*, c.company_name, c.company_logo, c.status as company_status,
                u.username as reporter_username, u.email as reporter_email,
                admin.username as resolved_by_username
                FROM employer_reports er
                JOIN companies c ON er.company_id = c.id
                JOIN users u ON er.reported_by = u.id
                LEFT JOIN users admin ON er.resolved_by = admin.id
                ORDER BY er.status ASC, er.created_at DESC";
        $stmt = $pdo->query($sql);
        $reports = $stmt->fetchAll();
        break;
}

// Get statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
$stats['active'] = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'active'")->fetchColumn();
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'pending'")->fetchColumn();
$stats['suspended'] = $pdo->query("SELECT COUNT(*) FROM companies WHERE status = 'suspended'")->fetchColumn();
$stats['featured'] = $pdo->query("SELECT COUNT(*) FROM companies WHERE featured = 'yes'")->fetchColumn();
$stats['reports_pending'] = $pdo->query("SELECT COUNT(*) FROM employer_reports WHERE status IN ('pending', 'investigating')")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Management - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --cp-border: #e2e8f0;
            --cp-text: #0f172a;
            --cp-muted: #64748b;
        }

        .company-page {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            min-height: 100vh;
            background: #f1f5f9;
        }

        .company-page .admin-main-content {
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
            padding: 1.5rem 2rem 2.5rem;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 1.5rem;
            padding: 1.25rem 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .page-header h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .page-header h1 i {
            color: #2563eb;
            opacity: 0.92;
            -webkit-text-fill-color: #2563eb;
            background: none;
        }

        .page-header p {
            color: #64748b;
            margin: 0;
            font-size: 0.9375rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        @media (max-width: 1200px) {
            .stats-row { grid-template-columns: repeat(3, 1fr); }
        }

        @media (max-width: 768px) {
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        .stat-box {
            background: linear-gradient(165deg, #ffffff 0%, #fafbff 100%);
            border: 1px solid var(--cp-border);
            border-left: 3px solid rgba(37, 99, 235, 0.45);
            border-radius: 12px;
            padding: 1.1rem 1.15rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, border-left-color 0.2s ease, transform 0.2s ease;
            box-shadow: 0 1px 3px rgba(30, 58, 138, 0.06);
        }

        .stat-box:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            border-left-color: #2563eb;
            box-shadow: 0 12px 28px rgba(30, 58, 138, 0.08);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }

        .stat-box.total .stat-icon { background: rgba(37, 99, 235, 0.1); color: #1d4ed8; }
        .stat-box.active .stat-icon { background: rgba(5, 150, 105, 0.1); color: #047857; }
        .stat-box.pending .stat-icon { background: rgba(217, 119, 6, 0.1); color: #b45309; }
        .stat-box.suspended .stat-icon { background: rgba(220, 38, 38, 0.08); color: #b91c1c; }
        .stat-box.featured .stat-icon { background: rgba(124, 58, 237, 0.1); color: #6d28d9; }
        .stat-box.reports .stat-icon { background: rgba(220, 38, 38, 0.08); color: #dc2626; }

        .stat-info h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            line-height: 1.1;
        }

        .stat-info span {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .alert-custom {
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid var(--cp-border);
        }

        .alert-custom.success {
            background: #ecfdf5;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-custom.warning {
            background: #fffbeb;
            border-color: #fde68a;
            color: #92400e;
        }

        .alert-custom.danger {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .tab-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 1.25rem;
            background: #ffffff;
            padding: 6px;
            border-radius: 12px;
            border: 1px solid var(--cp-border);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            width: fit-content;
            max-width: 100%;
        }

        .tab-link {
            padding: 10px 16px;
            border-radius: 10px;
            color: #64748b;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background 0.2s ease, color 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab-link:hover {
            color: #1e40af;
            background: #f1f5f9;
        }

        .tab-link.active {
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }

        .tab-link .badge {
            font-size: 0.65rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }

        .tab-link.active .badge {
            background: rgba(255, 255, 255, 0.25) !important;
            color: white !important;
        }

        .main-card {
            background: #ffffff;
            border: 1px solid var(--cp-border);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .card-header-custom {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
            background: linear-gradient(180deg, #f8fafc 0%, #f0f6ff 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-header-custom h5 {
            color: #334155;
            font-weight: 600;
            font-size: 1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header-custom h5 i {
            color: #2563eb;
            opacity: 0.88;
        }

        .card-header-custom h5 .count {
            background: rgba(37, 99, 235, 0.1);
            color: #1d4ed8;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .search-wrapper {
            display: flex;
            gap: 8px;
        }

        .search-input {
            background: #ffffff;
            border: 1px solid var(--cp-border);
            border-radius: 10px;
            padding: 10px 14px;
            color: #0f172a;
            width: 240px;
            max-width: 100%;
            font-size: 0.875rem;
        }

        .search-input::placeholder { color: #94a3b8; }
        .search-input:focus {
            outline: none;
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .btn-search {
            background: #2563eb;
            border: none;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-search:hover { background: #1d4ed8; }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            font-size: 0.6875rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 12px 18px;
            text-align: left;
            border-bottom: 1px solid var(--cp-border);
        }

        .data-table tbody tr {
            transition: background 0.15s ease;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .data-table tbody td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            color: #475569;
            font-size: 0.875rem;
            vertical-align: middle;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .company-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .company-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--cp-border);
        }

        .company-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1rem;
        }

        .company-details h6 {
            color: #0f172a;
            font-weight: 600;
            margin: 0 0 4px 0;
            font-size: 0.9375rem;
        }

        .company-details small {
            color: #64748b;
            font-size: 0.8rem;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-badge.active { background: #dcfce7; color: #166534; }
        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.suspended { background: #ffe4e6; color: #9f1239; }
        .status-badge.rejected { background: #f1f5f9; color: #475569; }

        .status-badge i { font-size: 0.6rem; }

        .featured-badge {
            background: #ede9fe;
            color: #5b21b6;
            border: 1px solid #ddd6fe;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-left: 8px;
        }

        .report-type {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .report-type.fraudulent { background: #fee2e2; color: #991b1b; }
        .report-type.scam { background: #ffedd5; color: #9a3412; }
        .report-type.harassment { background: #fce7f3; color: #9d174d; }
        .report-type.misleading { background: #fef3c7; color: #92400e; }
        .report-type.spam { background: #f1f5f9; color: #475569; }
        .report-type.other { background: #f1f5f9; color: #64748b; }

        .report-status {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .report-status.pending { background: #fef3c7; color: #92400e; }
        .report-status.investigating { background: #dbeafe; color: #1e40af; }
        .report-status.resolved { background: #dcfce7; color: #166534; }
        .report-status.dismissed { background: #f1f5f9; color: #64748b; }

        .action-btns {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action:hover { transform: translateY(-1px); }

        .btn-view { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .btn-view:hover { background: #dbeafe; color: #1e3a8a; }

        .btn-approve { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .btn-approve:hover { background: #d1fae5; }

        .btn-reject { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .btn-reject:hover { background: #fee2e2; }

        .btn-suspend { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .btn-suspend:hover { background: #fef3c7; }

        .btn-activate { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .btn-activate:hover { background: #d1fae5; }

        .btn-feature { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
        .btn-feature:hover { background: #ede9fe; }

        .btn-delete { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .btn-delete:hover { background: #fee2e2; }

        .empty-state {
            text-align: center;
            padding: 3rem 1.25rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: #475569;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .company-page .modal-content {
            background: #ffffff;
            border: 1px solid var(--cp-border);
            border-radius: 14px;
        }

        .company-page .modal-header {
            background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%);
            border-bottom: 1px solid var(--cp-border);
            border-radius: 14px 14px 0 0;
            padding: 1rem 1.25rem;
        }

        .company-page .modal-title {
            color: #1e3a8a;
            font-weight: 700;
        }

        .company-page .modal-body {
            padding: 1.25rem;
            color: #475569;
        }

        .company-page .modal-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid var(--cp-border);
            background: #fafbff;
        }

        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child { border-bottom: none; }

        .info-label {
            width: 140px;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .info-value {
            flex: 1;
            color: #0f172a;
            font-weight: 500;
        }

        .doc-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            color: #1d4ed8;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.2s ease;
        }

        .doc-link:hover {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .company-page .form-control,
        .company-page .form-select {
            background: #ffffff;
            border: 1px solid var(--cp-border);
            border-radius: 10px;
            color: #0f172a;
            padding: 12px 16px;
        }

        .company-page .form-control:focus,
        .company-page .form-select:focus {
            background: #ffffff;
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            color: #0f172a;
        }

        .company-page .form-label {
            color: #475569;
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .btn-modal-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid var(--cp-border);
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-modal-cancel:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-modal-save {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
        }

        .btn-modal-save:hover {
            background: #1d4ed8;
            color: white;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.3);
        }

        .stats-mini {
            display: flex;
            gap: 16px;
        }

        .stat-mini-item {
            text-align: center;
        }

        .stat-mini-item .num {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1d4ed8;
        }

        .stat-mini-item .lbl {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
        }

        .cp-readonly {
            background: #f8fafc;
            border: 1px solid var(--cp-border);
            color: #475569;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="admin-layout company-page">
    <?php include 'includes/sidebar.php'; ?>

    <div class="admin-main-content">
        <!-- Page Header -->
        <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1><i class="fas fa-city"></i> Company Management</h1>
                <p>Manage company profiles, verify employers, and handle reports</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert-custom success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($stats['pending'] > 0): ?>
            <div class="alert-custom warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span><strong><?php echo $stats['pending']; ?> company<?php echo $stats['pending'] > 1 ? 'ies' : ''; ?> awaiting verification.</strong> Review pending employers to allow them to post jobs.</span>
                <a href="?tab=verification" class="btn btn-sm btn-warning ms-auto" style="font-weight:600;">Review Now</a>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-box total">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['total']; ?></h3>
                    <span>Total Companies</span>
                </div>
            </div>
            <div class="stat-box active">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['active']; ?></h3>
                    <span>Active</span>
                </div>
            </div>
            <div class="stat-box pending">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <span>Pending</span>
                </div>
            </div>
            <div class="stat-box suspended">
                <div class="stat-icon"><i class="fas fa-ban"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['suspended']; ?></h3>
                    <span>Suspended</span>
                </div>
            </div>
            <div class="stat-box featured">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['featured']; ?></h3>
                    <span>Featured</span>
                </div>
            </div>
            <div class="stat-box reports">
                <div class="stat-icon"><i class="fas fa-flag"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['reports_pending']; ?></h3>
                    <span>Open Reports</span>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <a href="?tab=profiles" class="tab-link <?php echo $currentTab === 'profiles' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Company Profiles
                <span class="badge bg-secondary"><?php echo $stats['total']; ?></span>
            </a>
            <a href="?tab=verification" class="tab-link <?php echo $currentTab === 'verification' ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> Employer Verification
                <?php if ($stats['pending'] > 0): ?>
                    <span class="badge bg-warning text-dark"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=reports" class="tab-link <?php echo $currentTab === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-flag"></i> Reported Employers
                <?php if ($stats['reports_pending'] > 0): ?>
                    <span class="badge bg-danger"><?php echo $stats['reports_pending']; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Main Content Card -->
        <div class="main-card">
            <div class="card-header-custom">
                <h5>
                    <?php if ($currentTab === 'profiles'): ?>
                        <i class="fas fa-list"></i> All Companies
                    <?php elseif ($currentTab === 'verification'): ?>
                        <i class="fas fa-hourglass-half"></i> Pending Verification
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle"></i> Employer Reports
                    <?php endif; ?>
                    <span class="count"><?php echo $currentTab === 'reports' ? count($reports) : count($companies); ?></span>
                </h5>
                <?php if ($currentTab !== 'reports'): ?>
                <form method="GET" class="search-wrapper">
                    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">
                    <input type="text" name="search" class="search-input" placeholder="Search companies..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                </form>
                <?php endif; ?>
            </div>

            <?php if ($currentTab === 'reports'): ?>
                <!-- Reports Table -->
                <?php if (empty($reports)): ?>
                    <div class="empty-state">
                        <i class="fas fa-flag-checkered"></i>
                        <h5>No Reports Found</h5>
                        <p>All employer reports have been processed. Great job!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Type</th>
                                    <th>Reported By</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td>
                                        <div class="company-cell">
                                            <?php if ($report['company_logo']): ?>
                                                <img src="../<?php echo htmlspecialchars($report['company_logo']); ?>" class="company-avatar">
                                            <?php else: ?>
                                                <div class="company-avatar-placeholder"><?php echo strtoupper(substr($report['company_name'], 0, 2)); ?></div>
                                            <?php endif; ?>
                                            <div class="company-details">
                                                <h6><?php echo htmlspecialchars($report['company_name']); ?></h6>
                                                <small><?php echo ucfirst($report['company_status']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="report-type <?php echo $report['report_type']; ?>"><?php echo ucfirst($report['report_type']); ?></span></td>
                                    <td>
                                        <small>@<?php echo htmlspecialchars($report['reporter_username']); ?></small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars(substr($report['description'], 0, 60)); ?><?php echo strlen($report['description']) > 60 ? '...' : ''; ?></small></td>
                                    <td><span class="report-status <?php echo $report['status']; ?>"><?php echo ucfirst($report['status']); ?></span></td>
                                    <td><small><?php echo date('M j, Y', strtotime($report['created_at'])); ?></small></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-action btn-view" data-bs-toggle="modal" data-bs-target="#reportModal<?php echo $report['id']; ?>"><i class="fas fa-eye"></i></button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete_report">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete report?')"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Report Modal -->
                                <div class="modal fade" id="reportModal<?php echo $report['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="fas fa-flag me-2"></i>Report Details</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-4">
                                                    <div class="col-md-6">
                                                        <div class="info-row">
                                                            <span class="info-label">Company</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($report['company_name']); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Report Type</span>
                                                            <span class="info-value"><span class="report-type <?php echo $report['report_type']; ?>"><?php echo ucfirst($report['report_type']); ?></span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="info-row">
                                                            <span class="info-label">Reported By</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($report['reporter_username']); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Date</span>
                                                            <span class="info-value"><?php echo date('F j, Y', strtotime($report['created_at'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mb-4">
                                                    <label class="form-label">Description</label>
                                                    <div class="p-3 rounded cp-readonly"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div>
                                                </div>
                                                <?php if ($report['admin_notes']): ?>
                                                <div class="mb-4">
                                                    <label class="form-label">Admin Notes</label>
                                                    <div class="p-3 rounded cp-readonly"><?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?></div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <hr class="border-secondary opacity-25">
                                                <h6 class="mb-3 text-dark fw-semibold">Update report</h6>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="resolve_report">
                                                    <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <label class="form-label">Status</label>
                                                            <select name="resolution" class="form-select" required>
                                                                <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="investigating" <?php echo $report['status'] === 'investigating' ? 'selected' : ''; ?>>Investigating</option>
                                                                <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                                <option value="dismissed" <?php echo $report['status'] === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-12 mb-3">
                                                            <label class="form-label">Admin Notes</label>
                                                            <textarea name="admin_notes" class="form-control" rows="3"><?php echo htmlspecialchars($report['admin_notes'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="btn-modal-save">Update Report</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Companies Table -->
                <?php if (empty($companies)): ?>
                    <div class="empty-state">
                        <i class="fas fa-building"></i>
                        <h5>No Companies Found</h5>
                        <p><?php echo $currentTab === 'verification' ? 'All employers have been verified!' : 'No companies match your search.'; ?></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Contact</th>
                                    <th>Location</th>
                                    <?php if ($currentTab === 'profiles'): ?>
                                    <th>Stats</th>
                                    <?php endif; ?>
                                    <?php if ($currentTab === 'verification'): ?>
                                    <th>Documents</th>
                                    <?php endif; ?>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td>
                                        <div class="company-cell">
                                            <?php if ($company['company_logo']): ?>
                                                <img src="../<?php echo htmlspecialchars($company['company_logo']); ?>" class="company-avatar">
                                            <?php else: ?>
                                                <div class="company-avatar-placeholder"><?php echo strtoupper(substr($company['company_name'], 0, 2)); ?></div>
                                            <?php endif; ?>
                                            <div class="company-details">
                                                <h6>
                                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                                    <?php if ($company['featured'] === 'yes'): ?>
                                                        <span class="featured-badge"><i class="fas fa-star me-1"></i>Featured</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small>@<?php echo htmlspecialchars($company['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small><i class="fas fa-envelope me-1 text-primary"></i><?php echo htmlspecialchars($company['contact_email']); ?></small><br>
                                        <small><i class="fas fa-phone me-1 text-primary"></i><?php echo htmlspecialchars($company['contact_number']); ?></small>
                                    </td>
                                    <td><small><?php echo htmlspecialchars(substr($company['location_address'], 0, 35)); ?><?php echo strlen($company['location_address']) > 35 ? '...' : ''; ?></small></td>
                                    <?php if ($currentTab === 'profiles'): ?>
                                    <td>
                                        <div class="stats-mini">
                                            <div class="stat-mini-item">
                                                <div class="num"><?php echo $company['job_count'] ?? 0; ?></div>
                                                <div class="lbl">Jobs</div>
                                            </div>
                                            <div class="stat-mini-item">
                                                <div class="num"><?php echo $company['application_count'] ?? 0; ?></div>
                                                <div class="lbl">Apps</div>
                                            </div>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                    <?php if ($currentTab === 'verification'): ?>
                                    <td>
                                        <?php if ($company['business_permit']): ?>
                                            <a href="../<?php echo htmlspecialchars($company['business_permit']); ?>" target="_blank" class="btn-action btn-view" style="font-size:0.7rem;"><i class="fas fa-file-pdf"></i> Permit</a>
                                        <?php endif; ?>
                                        <?php if ($company['supporting_document']): ?>
                                            <a href="../<?php echo htmlspecialchars($company['supporting_document']); ?>" target="_blank" class="btn-action btn-view" style="font-size:0.7rem;"><i class="fas fa-file"></i> Doc</a>
                                        <?php endif; ?>
                                        <?php if (!$company['business_permit'] && !$company['supporting_document']): ?>
                                            <small class="text-muted">None</small>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="status-badge <?php echo $company['status']; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($company['status']); ?>
                                        </span>
                                    </td>
                                    <td><small><?php echo date('M j, Y', strtotime($company['created_at'])); ?></small></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn-action btn-view" data-bs-toggle="modal" data-bs-target="#companyModal<?php echo $company['id']; ?>"><i class="fas fa-eye"></i></button>
                                            
                                            <?php if ($currentTab === 'verification' || $company['status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="approve_company">
                                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                    <button type="submit" class="btn-action btn-approve" onclick="return confirm('Approve?')"><i class="fas fa-check"></i></button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="reject_company">
                                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                    <button type="submit" class="btn-action btn-reject" onclick="return confirm('Reject?')"><i class="fas fa-times"></i></button>
                                                </form>
                                            <?php elseif ($company['status'] === 'active'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="suspend_company">
                                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                    <button type="submit" class="btn-action btn-suspend" onclick="return confirm('Suspend?')"><i class="fas fa-ban"></i></button>
                                                </form>
                                            <?php elseif ($company['status'] === 'suspended'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="activate_company">
                                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                    <button type="submit" class="btn-action btn-activate" onclick="return confirm('Activate?')"><i class="fas fa-check"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($currentTab === 'profiles'): ?>
                                                <?php if ($company['featured'] !== 'yes'): ?>
                                                    <button class="btn-action btn-feature" data-bs-toggle="modal" data-bs-target="#featureModal<?php echo $company['id']; ?>"><i class="fas fa-star"></i></button>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="unfeature_company">
                                                        <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                        <button type="submit" class="btn-action" style="background: rgba(107,114,128,0.15); color: #9ca3af;"><i class="fas fa-star"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete_company">
                                                <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete permanently?')"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                
                                <!-- Company Details Modal -->
                                <div class="modal fade" id="companyModal<?php echo $company['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($company['company_name']); ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row">
                                                    <div class="col-md-4 text-center mb-4">
                                                        <?php if ($company['company_logo']): ?>
                                                            <img src="../<?php echo htmlspecialchars($company['company_logo']); ?>" class="rounded-3" style="width:120px;height:120px;object-fit:cover;border:3px solid #2563eb;">
                                                        <?php else: ?>
                                                            <div class="company-avatar-placeholder mx-auto" style="width:120px;height:120px;font-size:36px;"><?php echo strtoupper(substr($company['company_name'], 0, 2)); ?></div>
                                                        <?php endif; ?>
                                                        <div class="mt-3">
                                                            <span class="status-badge <?php echo $company['status']; ?>"><?php echo ucfirst($company['status']); ?></span>
                                                            <?php if ($company['featured'] === 'yes'): ?>
                                                                <span class="featured-badge ms-1"><i class="fas fa-star me-1"></i>Featured</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <div class="info-row">
                                                            <span class="info-label">Company Name</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($company['company_name']); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Email</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($company['contact_email']); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Phone</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($company['contact_number']); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Address</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($company['location_address']); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Contact Person</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($company['contact_first_name'] . ' ' . $company['contact_last_name']); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Position</span>
                                                            <span class="info-value"><?php echo htmlspecialchars($company['contact_position'] ?? '-'); ?></span>
                                                        </div>
                                                        <div class="info-row">
                                                            <span class="info-label">Registered</span>
                                                            <span class="info-value"><?php echo date('F j, Y', strtotime($company['created_at'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($company['description'])): ?>
                                                <div class="mt-4">
                                                    <label class="form-label">Description</label>
                                                    <div class="p-3 rounded cp-readonly"><?php echo nl2br(htmlspecialchars($company['description'])); ?></div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="mt-4">
                                                    <label class="form-label">Documents</label>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <?php if ($company['business_permit']): ?>
                                                            <a href="../<?php echo htmlspecialchars($company['business_permit']); ?>" target="_blank" class="doc-link">
                                                                <i class="fas fa-file-pdf"></i> Business Permit
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($company['supporting_document']): ?>
                                                            <a href="../<?php echo htmlspecialchars($company['supporting_document']); ?>" target="_blank" class="doc-link">
                                                                <i class="fas fa-file"></i> Supporting Document
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (!$company['business_permit'] && !$company['supporting_document']): ?>
                                                            <span class="text-muted">No documents uploaded</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Feature Modal -->
                                <div class="modal fade" id="featureModal<?php echo $company['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="fas fa-star me-2"></i>Feature Company</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="feature_company">
                                                <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                <div class="modal-body">
                                                    <p class="text-muted mb-0">Feature <strong class="text-dark"><?php echo htmlspecialchars($company['company_name']); ?></strong> on the homepage.</p>
                                                    <div class="mb-3">
                                                        <label class="form-label">Featured Until</label>
                                                        <input type="date" name="featured_until" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn-modal-save"><i class="fas fa-star me-1"></i>Feature</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

