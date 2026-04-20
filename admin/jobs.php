<?php
include '../config.php';
requireRole('admin');

$message = '';
$error = '';

// Handle job actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action === 'add_job') {
        $companyId = (int)$_POST['company_id'];
        $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $requirements = sanitizeInput($_POST['requirements']);
        $location = sanitizeInput($_POST['location']);
        $salaryRange = sanitizeInput($_POST['salary_range']);
        $employmentType = sanitizeInput($_POST['employment_type']);
        $experienceLevel = sanitizeInput($_POST['experience_level']);
        $deadline = !empty($_POST['deadline']) ? sanitizeInput($_POST['deadline']) : null;
        $status = sanitizeInput($_POST['status']);
        
        if (empty($title) || empty($description) || empty($location) || $companyId == 0) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO job_postings (company_id, category_id, title, description, requirements, location, salary_range, employment_type, experience_level, deadline, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$companyId, $categoryId, $title, $description, $requirements, $location, $salaryRange, $employmentType, $experienceLevel, $deadline, $status]);
                $message = 'Job created successfully!';
            } catch (Exception $e) {
                $error = 'Error creating job: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'add_category') {
        $categoryName = sanitizeInput($_POST['category_name']);
        $description = sanitizeInput($_POST['description']);
        
        if (empty($categoryName)) {
            $error = 'Category name is required.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO job_categories (category_name, description) VALUES (?, ?)");
                $stmt->execute([$categoryName, $description]);
                $message = 'Category added successfully!';
            } catch (Exception $e) {
                $error = 'Error adding category: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'edit_category') {
        $categoryId = (int)$_POST['category_id'];
        $categoryName = sanitizeInput($_POST['category_name']);
        $description = sanitizeInput($_POST['description']);
        
        $stmt = $pdo->prepare("UPDATE job_categories SET category_name = ?, description = ? WHERE id = ?");
        $stmt->execute([$categoryName, $description, $categoryId]);
        $message = 'Category updated successfully!';
    } elseif ($action === 'toggle_category_status') {
        $categoryId = (int)$_POST['category_id'];
        $newStatus = $_POST['new_status'];
        
        $stmt = $pdo->prepare("UPDATE job_categories SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $categoryId]);
        $message = 'Category status updated successfully!';
    } elseif ($action === 'delete_category') {
        $categoryId = (int)$_POST['category_id'];
        
        $stmt = $pdo->prepare("DELETE FROM job_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $message = 'Category deleted successfully!';
    } else {
        $jobId = (int)$_POST['job_id'];
        
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'active' WHERE id = ?");
            $stmt->execute([$jobId]);
            $message = 'Job approved successfully!';
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$jobId]);
            $message = 'Job rejected successfully!';
        } elseif ($action === 'flag') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'suspended' WHERE id = ?");
            $stmt->execute([$jobId]);
            $message = 'Job flagged and suspended successfully!';
        } elseif ($action === 'unflag') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'active' WHERE id = ?");
            $stmt->execute([$jobId]);
            $message = 'Job unflagged and restored successfully!';
        } elseif ($action === 'close') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'closed' WHERE id = ?");
            $stmt->execute([$jobId]);
            $message = 'Job closed successfully!';
        } elseif ($action === 'reactivate') {
            $stmt = $pdo->prepare("UPDATE job_postings SET status = 'active' WHERE id = ?");
            $stmt->execute([$jobId]);
            $message = 'Job reactivated successfully!';
        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM job_postings WHERE id = ?");
            $stmt->execute([$jobId]);
            $message = 'Job deleted successfully!';
        }
    }
}

// Ensure all categories exist in database
$allCategoryList = [
    'Administrative / Office',
    'Customer Service / BPO',
    'Education',
    'Engineering',
    'Information Technology (IT)',
    'Finance / Accounting',
    'Healthcare / Medical',
    'Human Resources (HR)',
    'Manufacturing / Production',
    'Logistics / Warehouse / Supply Chain',
    'Marketing / Sales',
    'Creative / Media / Design',
    'Construction / Infrastructure',
    'Food / Hospitality / Tourism',
    'Retail / Sales Operations',
    'Transportation',
    'Law Enforcement / Criminology',
    'Security Services',
    'Skilled / Technical (TESDA)',
    'Agriculture / Fisheries',
    'Freelance / Online / Remote',
    'Legal / Government / Public Service',
    'Maritime / Aviation / Transport Specialized',
    'Science / Research / Environment',
    'Arts / Entertainment / Culture',
    'Religion / NGO / Development / Cooperative',
    'Special / Rare Jobs',
    'Utilities / Public Services',
    'Telecommunications',
    'Mining / Geology',
    'Oil / Gas / Energy',
    'Chemical / Industrial',
    'Allied Health / Special Education / Therapy',
    'Sports / Fitness / Recreation',
    'Fashion / Apparel / Beauty',
    'Food Service / Fast Food / QSR',
    'Home / Personal Services',
    'Insurance / Risk / Banking',
    'Micro Jobs / Informal / Daily Wage Jobs',
    'Real Estate / Property',
    'Entrepreneurship / Business / Corporate'
];

// Insert categories if they don't exist
foreach ($allCategoryList as $categoryName) {
    $stmt = $pdo->prepare("SELECT id FROM job_categories WHERE category_name = ?");
    $stmt->execute([$categoryName]);
    if (!$stmt->fetch()) {
        $insertStmt = $pdo->prepare("INSERT INTO job_categories (category_name, status) VALUES (?, 'active')");
        $insertStmt->execute([$categoryName]);
    }
}

// Get active tab
$activeTab = $_GET['tab'] ?? 'approval';
$search = $_GET['search'] ?? '';

// Get statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM job_postings")->fetchColumn();
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'pending'")->fetchColumn();
$stats['active'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'active'")->fetchColumn();
$stats['flagged'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'suspended'")->fetchColumn();
$stats['closed'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'closed'")->fetchColumn();
$stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM job_postings WHERE status = 'rejected'")->fetchColumn();
$stats['categories'] = $pdo->query("SELECT COUNT(*) FROM job_categories")->fetchColumn();

// Build search condition
$searchCondition = "";
$searchParams = [];
if (!empty($search)) {
    $searchCondition = " AND (jp.title LIKE ? OR jp.description LIKE ? OR c.company_name LIKE ?)";
    $searchTerm = "%$search%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm];
}

// Get pending jobs for approval
$pendingSql = "SELECT jp.*, c.company_name, jc.category_name,
               COUNT(ja.id) as application_count
        FROM job_postings jp 
        JOIN companies c ON jp.company_id = c.id 
        LEFT JOIN job_categories jc ON jp.category_id = jc.id 
        LEFT JOIN job_applications ja ON jp.id = ja.job_id 
        WHERE jp.status = 'pending' $searchCondition
        GROUP BY jp.id 
        ORDER BY jp.posted_date DESC";
$stmt = $pdo->prepare($pendingSql);
$stmt->execute($searchParams);
$pendingJobs = $stmt->fetchAll();

// Get active jobs
$activeSql = "SELECT jp.*, c.company_name, jc.category_name,
               COUNT(ja.id) as application_count
        FROM job_postings jp 
        JOIN companies c ON jp.company_id = c.id 
        LEFT JOIN job_categories jc ON jp.category_id = jc.id 
        LEFT JOIN job_applications ja ON jp.id = ja.job_id 
        WHERE jp.status = 'active' $searchCondition
        GROUP BY jp.id 
        ORDER BY jp.posted_date DESC";
$stmt = $pdo->prepare($activeSql);
$stmt->execute($searchParams);
$activeJobs = $stmt->fetchAll();

// Get flagged/suspended jobs
$flaggedSql = "SELECT jp.*, c.company_name, jc.category_name,
               COUNT(ja.id) as application_count
        FROM job_postings jp 
        JOIN companies c ON jp.company_id = c.id 
        LEFT JOIN job_categories jc ON jp.category_id = jc.id 
        LEFT JOIN job_applications ja ON jp.id = ja.job_id 
        WHERE jp.status = 'suspended' $searchCondition
        GROUP BY jp.id 
        ORDER BY jp.posted_date DESC";
$stmt = $pdo->prepare($flaggedSql);
$stmt->execute($searchParams);
$flaggedJobs = $stmt->fetchAll();

// Get all categories with job count and application count
$allCategories = $pdo->query("SELECT jc.*, 
    (SELECT COUNT(*) FROM job_postings WHERE category_id = jc.id) as job_count,
    (SELECT COUNT(*) FROM job_applications ja 
     JOIN job_postings jp ON ja.job_id = jp.id 
     WHERE jp.category_id = jc.id) as application_count
    FROM job_categories jc ORDER BY jc.category_name")->fetchAll();

// Get companies and categories for dropdowns
$companies = $pdo->query("SELECT id, company_name FROM companies WHERE status = 'active' ORDER BY company_name")->fetchAll();
$categories = $pdo->query("SELECT id, category_name FROM job_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Management - WORKLINK Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        .jobs-admin-page .admin-main-content {
            background: linear-gradient(180deg, #eef2ff 0%, #f8fafc 18%, #f1f5f9 55%, #f8fafc 100%);
        }

        .jobs-page-header {
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(120deg, #ffffff 0%, #f5f8ff 50%, #eef4ff 100%);
            border: 1px solid rgba(37, 99, 235, 0.12);
            border-radius: 14px;
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.06);
        }

        .jobs-page-header h1 {
            font-weight: 700;
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 0.35rem;
        }

        .jobs-page-header .text-muted {
            color: #64748b !important;
        }

        .jobs-admin-page .jobs-nav {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 6px;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }

        .jobs-admin-page .jobs-nav .nav {
            flex-wrap: wrap;
            gap: 4px;
        }

        .jobs-admin-page .jobs-nav .nav-link {
            color: #64748b;
            border-radius: 10px;
            padding: 12px 16px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: background 0.2s ease, color 0.2s ease;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .jobs-admin-page .jobs-nav .nav-link:hover {
            color: #1e40af;
            background: #f1f5f9;
        }

        .jobs-admin-page .jobs-nav .nav-link.active {
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }

        .jobs-admin-page .jobs-nav .badge {
            font-size: 0.65rem;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
        }

        .jobs-admin-page .jobs-nav .nav-link.active .badge {
            background: rgba(255, 255, 255, 0.28) !important;
            color: #ffffff !important;
        }

        .stat-mini {
            background: linear-gradient(165deg, #ffffff 0%, #fafbff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 0.85rem;
            text-align: center;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
            box-shadow: 0 1px 3px rgba(30, 58, 138, 0.06);
        }

        .stat-mini--total { border-left: 3px solid #2563eb; }
        .stat-mini--total h3 { color: #1d4ed8; }

        .stat-mini--pending { border-left: 3px solid #d97706; }
        .stat-mini--pending h3 { color: #b45309; }

        .stat-mini--active { border-left: 3px solid #059669; }
        .stat-mini--active h3 { color: #047857; }

        .stat-mini--flagged { border-left: 3px solid #dc2626; }
        .stat-mini--flagged h3 { color: #b91c1c; }

        .stat-mini--closed { border-left: 3px solid #64748b; }
        .stat-mini--closed h3 { color: #475569; }

        .stat-mini--categories { border-left: 3px solid #7c3aed; }
        .stat-mini--categories h3 { color: #6d28d9; }

        .stat-mini:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            box-shadow: 0 10px 28px rgba(30, 58, 138, 0.08);
        }

        .stat-mini h3 {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.15;
        }

        .stat-mini p {
            margin: 0.4rem 0 0;
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
        }

        .jobs-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .jobs-panel-header {
            background: linear-gradient(180deg, #f8fafc 0%, #f0f6ff 100%);
            border-bottom: 1px solid rgba(37, 99, 235, 0.1);
            padding: 0.9rem 1.25rem;
        }

        .jobs-panel-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1rem;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }

        .jobs-admin-page .jobs-nav .nav-pills .nav-link:not(.active) {
            background: transparent;
        }

        .jobs-admin-page .jobs-nav .nav-link.active:hover,
        .jobs-admin-page .jobs-nav .nav-link.active:focus {
            background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
        }

        .jobs-panel-header .fas {
            color: #2563eb !important;
            opacity: 0.88;
        }
        .job-card {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        .job-card:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .job-card .job-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .job-card .company-name {
            color: #3b82f6;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .job-card .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
            font-size: 0.85rem;
            color: #64748b;
        }
        .job-card .job-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .job-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .job-actions .btn {
            padding: 8px 16px;
            font-size: 0.85rem;
            border-radius: 8px;
        }
        .category-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            transition: all 0.3s ease;
            height: 100%;
        }
        .category-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .category-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 12px;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1.25rem;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
            opacity: 1;
        }
        .empty-state h5 {
            color: #475569;
            font-weight: 600;
        }
        .job-card--flagged {
            border-left: 3px solid #dc2626 !important;
        }
        .search-box {
            position: relative;
        }
        .search-box input {
            padding-left: 45px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            height: 48px;
        }
        .search-box input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-active { background: #d1fae5; color: #065f46; }
        .status-flagged { background: #fee2e2; color: #991b1b; }
        .status-closed { background: #e2e8f0; color: #475569; }
        
        /* Fix modal animation - Smooth fade without shaking - Ultimate fix */
        .modal {
            opacity: 0;
            transition: opacity 0.25s ease-in-out !important;
            display: none;
        }
        .modal.show {
            opacity: 1 !important;
            display: block !important;
        }
        .modal.fade:not(.show) {
            opacity: 0;
        }
        .modal.fade.show {
            opacity: 1;
        }
        .modal.fade .modal-dialog {
            transition: none !important;
            transform: none !important;
            -webkit-transform: none !important;
            -moz-transform: none !important;
            -ms-transform: none !important;
            -o-transform: none !important;
            will-change: auto !important;
        }
        .modal.show .modal-dialog {
            transform: none !important;
            -webkit-transform: none !important;
            -moz-transform: none !important;
            -ms-transform: none !important;
            -o-transform: none !important;
            will-change: auto !important;
        }
        .modal-dialog {
            margin: 1.75rem auto !important;
            position: relative !important;
            width: auto !important;
            max-width: 500px !important;
            transform: none !important;
            -webkit-transform: none !important;
            -moz-transform: none !important;
            -ms-transform: none !important;
            -o-transform: none !important;
            transition: none !important;
            -webkit-transition: none !important;
            -moz-transition: none !important;
            -ms-transition: none !important;
            -o-transition: none !important;
            will-change: auto !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
        }
        .modal-content {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: none !important;
            -webkit-transform: none !important;
            -moz-transform: none !important;
            -ms-transform: none !important;
            -o-transform: none !important;
            will-change: auto !important;
        }
        .modal-backdrop {
            opacity: 0;
            transition: opacity 0.25s ease-in-out !important;
        }
        .modal-backdrop.show {
            opacity: 0.5 !important;
        }
        .modal-backdrop.fade {
            transition: opacity 0.25s ease-in-out !important;
        }
    </style>
</head>
<body class="admin-layout jobs-admin-page">
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="admin-main-content">
        <div class="container-fluid px-4 py-4">
            <!-- Page Header -->
            <div class="jobs-page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1">Job management</h1>
                    <p class="text-muted mb-0">Manage job postings, approvals, and categories</p>
                </div>
                <div class="d-flex gap-2 flex-shrink-0">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fas fa-tags me-1"></i>Add category
                    </button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addJobModal">
                        <i class="fas fa-plus me-1"></i>Add job
                    </button>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Row -->
            <div class="row g-3 mb-4">
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="stat-mini stat-mini--total">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total jobs</p>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="stat-mini stat-mini--pending">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="stat-mini stat-mini--active">
                        <h3><?php echo $stats['active']; ?></h3>
                        <p>Active</p>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="stat-mini stat-mini--flagged">
                        <h3><?php echo $stats['flagged']; ?></h3>
                        <p>Flagged</p>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="stat-mini stat-mini--closed">
                        <h3><?php echo $stats['closed']; ?></h3>
                        <p>Closed</p>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-6">
                    <div class="stat-mini stat-mini--categories">
                        <h3><?php echo $stats['categories']; ?></h3>
                        <p>Categories</p>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <nav class="jobs-nav">
                <div class="nav nav-pills" role="tablist">
                    <a class="nav-link <?php echo $activeTab === 'approval' ? 'active' : ''; ?>" 
                       href="?tab=approval<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="fas fa-clock"></i>
                        Job Post Approval
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?php echo $stats['pending']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link <?php echo $activeTab === 'active' ? 'active' : ''; ?>" 
                       href="?tab=active<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="fas fa-briefcase"></i>
                        Active Job Listings
                        <span class="badge bg-success"><?php echo $stats['active']; ?></span>
                    </a>
                    <a class="nav-link <?php echo $activeTab === 'flagged' ? 'active' : ''; ?>" 
                       href="?tab=flagged<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                        <i class="fas fa-flag"></i>
                        Flagged / Reported Jobs
                        <?php if ($stats['flagged'] > 0): ?>
                            <span class="badge bg-danger"><?php echo $stats['flagged']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="nav-link <?php echo $activeTab === 'categories' ? 'active' : ''; ?>" 
                       href="?tab=categories">
                        <i class="fas fa-tags"></i>
                        Job Categories
                        <span class="badge bg-info"><?php echo $stats['categories']; ?></span>
                    </a>
                </div>
            </nav>

            <!-- Search Box (for job tabs) -->
            <?php if ($activeTab !== 'categories'): ?>
            <div class="row mb-4">
                <div class="col-md-6">
                    <form method="GET" class="search-box">
                        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Search jobs by title, description or company..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                </div>
                <?php if (!empty($search)): ?>
                <div class="col-md-6 d-flex align-items-center">
                    <a href="?tab=<?php echo $activeTab; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear Search
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Tab Content -->
            <div class="tab-content">
                
                <!-- Job Post Approval Tab -->
                <?php if ($activeTab === 'approval'): ?>
                <div class="tab-pane fade show active">
                    <div class="jobs-panel">
                        <div class="jobs-panel-header">
                            <h5><i class="fas fa-hourglass-half me-2"></i>Pending job approvals</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($pendingJobs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h5>All Caught Up!</h5>
                                <p>There are no pending jobs waiting for approval.</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($pendingJobs as $job): ?>
                                <div class="job-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <div class="company-name">
                                                <i class="fas fa-building me-1"></i>
                                                <?php echo htmlspecialchars($job['company_name']); ?>
                                            </div>
                                            <div class="job-meta">
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                                <?php if ($job['category_name']): ?>
                                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($job['category_name']); ?></span>
                                                <?php endif; ?>
                                                <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($job['posted_date'])); ?></span>
                                                <?php if ($job['salary_range']): ?>
                                                <span><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars($job['salary_range']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                            <span class="status-badge status-pending mb-2 d-inline-block">
                                                <i class="fas fa-clock me-1"></i>Pending Review
                                            </span>
                                            <div class="job-actions justify-content-md-end mt-2">
                                                <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this job posting?')">
                                                        <i class="fas fa-check me-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this job posting?')">
                                                        <i class="fas fa-times me-1"></i>Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Active Job Listings Tab -->
                <?php if ($activeTab === 'active'): ?>
                <div class="tab-pane fade show active">
                    <div class="jobs-panel">
                        <div class="jobs-panel-header">
                            <h5><i class="fas fa-briefcase me-2"></i>Active job listings</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($activeJobs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-briefcase"></i>
                                <h5>No Active Jobs</h5>
                                <p>There are no active job listings at the moment.</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($activeJobs as $job): ?>
                                <div class="job-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <div class="company-name">
                                                <i class="fas fa-building me-1"></i>
                                                <?php echo htmlspecialchars($job['company_name']); ?>
                                            </div>
                                            <div class="job-meta">
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                                <?php if ($job['category_name']): ?>
                                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($job['category_name']); ?></span>
                                                <?php endif; ?>
                                                <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($job['posted_date'])); ?></span>
                                                <span><i class="fas fa-users"></i> <?php echo $job['application_count']; ?> applications</span>
                                            </div>
                                        </div>
                                        <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                            <span class="status-badge status-active mb-2 d-inline-block">
                                                <i class="fas fa-check-circle me-1"></i>Active
                                            </span>
                                            <div class="job-actions justify-content-md-end mt-2">
                                                <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>View
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="flag">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Flag this job as reported/suspicious?')">
                                                        <i class="fas fa-flag me-1"></i>Flag
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="close">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Close this job listing?')">
                                                        <i class="fas fa-ban me-1"></i>Close
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this job permanently?')">
                                                        <i class="fas fa-trash me-1"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Flagged / Reported Jobs Tab -->
                <?php if ($activeTab === 'flagged'): ?>
                <div class="tab-pane fade show active">
                    <div class="jobs-panel">
                        <div class="jobs-panel-header">
                            <h5><i class="fas fa-flag me-2"></i>Flagged / reported jobs</h5>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($flaggedJobs)): ?>
                            <div class="empty-state">
                                <i class="fas fa-shield-alt"></i>
                                <h5>No Flagged Jobs</h5>
                                <p>There are no flagged or reported jobs at the moment.</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($flaggedJobs as $job): ?>
                                <div class="job-card job-card--flagged">
                                    <div class="row align-items-center">
                                        <div class="col-md-7">
                                            <div class="job-title"><?php echo htmlspecialchars($job['title']); ?></div>
                                            <div class="company-name">
                                                <i class="fas fa-building me-1"></i>
                                                <?php echo htmlspecialchars($job['company_name']); ?>
                                            </div>
                                            <div class="job-meta">
                                                <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                                                <?php if ($job['category_name']): ?>
                                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($job['category_name']); ?></span>
                                                <?php endif; ?>
                                                <span><i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($job['posted_date'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                            <span class="status-badge status-flagged mb-2 d-inline-block">
                                                <i class="fas fa-exclamation-triangle me-1"></i>Flagged
                                            </span>
                                            <div class="job-actions justify-content-md-end mt-2">
                                                <a href="view-job.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i>Review
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="unflag">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Restore this job to active status?')">
                                                        <i class="fas fa-undo me-1"></i>Restore
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this flagged job permanently?')">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Job Categories Tab -->
                <?php if ($activeTab === 'categories'): ?>
                <div class="tab-pane fade show active">
                    <div class="jobs-panel">
                        <div class="jobs-panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Job categories</h5>
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fas fa-plus me-1"></i>Add category
                            </button>
                        </div>
                        <div class="card-body p-4">
                            <?php if (empty($allCategories)): ?>
                            <div class="empty-state">
                                <i class="fas fa-tags"></i>
                                <h5>No Categories</h5>
                                <p>Create your first job category to organize job listings.</p>
                                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                    <i class="fas fa-plus me-1"></i>Add Category
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="row g-3">
                                <?php 
                                $categoryIcons = [
                                    'Administrative / Office' => ['icon' => 'fa-clipboard', 'color' => '#0ea5e9', 'bg' => '#e0f2fe'],
                                    'Customer Service / BPO' => ['icon' => 'fa-headset', 'color' => '#06b6d4', 'bg' => '#cffafe'],
                                    'Education' => ['icon' => 'fa-graduation-cap', 'color' => '#8b5cf6', 'bg' => '#ede9fe'],
                                    'Engineering' => ['icon' => 'fa-cogs', 'color' => '#6366f1', 'bg' => '#e0e7ff'],
                                    'Information Technology (IT)' => ['icon' => 'fa-laptop-code', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
                                    'Finance / Accounting' => ['icon' => 'fa-chart-line', 'color' => '#10b981', 'bg' => '#d1fae5'],
                                    'Healthcare / Medical' => ['icon' => 'fa-heartbeat', 'color' => '#ef4444', 'bg' => '#fee2e2'],
                                    'Human Resources (HR)' => ['icon' => 'fa-users', 'color' => '#ec4899', 'bg' => '#fce7f3'],
                                    'Manufacturing / Production' => ['icon' => 'fa-industry', 'color' => '#78716c', 'bg' => '#f5f5f4'],
                                    'Logistics / Warehouse / Supply Chain' => ['icon' => 'fa-truck', 'color' => '#14b8a6', 'bg' => '#ccfbf1'],
                                    'Marketing / Sales' => ['icon' => 'fa-bullhorn', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                                    'Creative / Media / Design' => ['icon' => 'fa-palette', 'color' => '#f472b6', 'bg' => '#fdf2f8'],
                                    'Construction / Infrastructure' => ['icon' => 'fa-hammer', 'color' => '#f97316', 'bg' => '#ffedd5'],
                                    'Food / Hospitality / Tourism' => ['icon' => 'fa-utensils', 'color' => '#eab308', 'bg' => '#fef9c3'],
                                    'Retail / Sales Operations' => ['icon' => 'fa-store', 'color' => '#a855f7', 'bg' => '#f3e8ff'],
                                    'Transportation' => ['icon' => 'fa-bus', 'color' => '#06b6d4', 'bg' => '#cffafe'],
                                    'Law Enforcement / Criminology' => ['icon' => 'fa-shield-alt', 'color' => '#1e40af', 'bg' => '#dbeafe'],
                                    'Security Services' => ['icon' => 'fa-user-shield', 'color' => '#475569', 'bg' => '#f1f5f9'],
                                    'Skilled / Technical (TESDA)' => ['icon' => 'fa-tools', 'color' => '#dc2626', 'bg' => '#fee2e2'],
                                    'Agriculture / Fisheries' => ['icon' => 'fa-seedling', 'color' => '#16a34a', 'bg' => '#dcfce7'],
                                    'Freelance / Online / Remote' => ['icon' => 'fa-laptop', 'color' => '#7c3aed', 'bg' => '#ede9fe'],
                                    'Legal / Government / Public Service' => ['icon' => 'fa-balance-scale', 'color' => '#64748b', 'bg' => '#f1f5f9'],
                                    'Maritime / Aviation / Transport Specialized' => ['icon' => 'fa-plane', 'color' => '#0284c7', 'bg' => '#e0f2fe'],
                                    'Science / Research / Environment' => ['icon' => 'fa-flask', 'color' => '#059669', 'bg' => '#d1fae5'],
                                    'Arts / Entertainment / Culture' => ['icon' => 'fa-music', 'color' => '#c026d3', 'bg' => '#fae8ff'],
                                    'Religion / NGO / Development / Cooperative' => ['icon' => 'fa-hands-helping', 'color' => '#ea580c', 'bg' => '#ffedd5'],
                                    'Special / Rare Jobs' => ['icon' => 'fa-star', 'color' => '#fbbf24', 'bg' => '#fef3c7'],
                                    'Utilities / Public Services' => ['icon' => 'fa-bolt', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                                    'Telecommunications' => ['icon' => 'fa-signal', 'color' => '#3b82f6', 'bg' => '#dbeafe'],
                                    'Mining / Geology' => ['icon' => 'fa-mountain', 'color' => '#78716c', 'bg' => '#f5f5f4'],
                                    'Oil / Gas / Energy' => ['icon' => 'fa-oil-can', 'color' => '#1e293b', 'bg' => '#f1f5f9'],
                                    'Chemical / Industrial' => ['icon' => 'fa-flask', 'color' => '#7c2d12', 'bg' => '#fef2f2'],
                                    'Allied Health / Special Education / Therapy' => ['icon' => 'fa-user-md', 'color' => '#be185d', 'bg' => '#fce7f3'],
                                    'Sports / Fitness / Recreation' => ['icon' => 'fa-dumbbell', 'color' => '#dc2626', 'bg' => '#fee2e2'],
                                    'Fashion / Apparel / Beauty' => ['icon' => 'fa-tshirt', 'color' => '#ec4899', 'bg' => '#fce7f3'],
                                    'Food Service / Fast Food / QSR' => ['icon' => 'fa-utensils', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                                    'Home / Personal Services' => ['icon' => 'fa-home', 'color' => '#16a34a', 'bg' => '#dcfce7'],
                                    'Insurance / Risk / Banking' => ['icon' => 'fa-piggy-bank', 'color' => '#10b981', 'bg' => '#d1fae5'],
                                    'Micro Jobs / Informal / Daily Wage Jobs' => ['icon' => 'fa-coins', 'color' => '#f59e0b', 'bg' => '#fef3c7'],
                                    'Real Estate / Property' => ['icon' => 'fa-building', 'color' => '#0ea5e9', 'bg' => '#e0f2fe'],
                                    'Entrepreneurship / Business / Corporate' => ['icon' => 'fa-briefcase', 'color' => '#1e40af', 'bg' => '#dbeafe'],
                                ];
                                $defaultIcon = ['icon' => 'fa-folder', 'color' => '#6b7280', 'bg' => '#f3f4f6'];
                                ?>
                                <?php foreach ($allCategories as $cat): ?>
                                <?php 
                                $iconData = $categoryIcons[$cat['category_name']] ?? $defaultIcon;
                                ?>
                                <div class="col-md-4 col-sm-6">
                                    <div class="category-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="category-icon" style="background: <?php echo $iconData['bg']; ?>; color: <?php echo $iconData['color']; ?>;">
                                                <i class="fas <?php echo $iconData['icon']; ?>"></i>
                                            </div>
                                            <span class="badge <?php echo $cat['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($cat['status']); ?>
                                            </span>
                                        </div>
                                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($cat['category_name']); ?></h6>
                                        <p class="text-muted small mb-2"><?php echo htmlspecialchars($cat['description'] ?? 'No description'); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-muted small">
                                                <div><i class="fas fa-briefcase me-1"></i><?php echo $cat['job_count']; ?> jobs</div>
                                                <div><i class="fas fa-user-check me-1"></i><?php echo $cat['application_count'] ?? 0; ?> applications</div>
                                            </div>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategoryModal<?php echo $cat['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_category_status">
                                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $cat['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $cat['status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success'; ?>">
                                                        <i class="fas <?php echo $cat['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete_category">
                                                    <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this category? Jobs in this category will become uncategorized.')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Category Modal -->
                                <div class="modal fade" id="editCategoryModal<?php echo $cat['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Category</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="edit_category">
                                                <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Category Name *</label>
                                                        <input type="text" class="form-control" name="category_name" value="<?php echo htmlspecialchars($cat['category_name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Description</label>
                                                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($cat['description'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- Add Job Modal -->
    <div class="modal fade" id="addJobModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-briefcase me-2"></i>Add New Job</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_job">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Company *</label>
                                <select class="form-select" name="company_id" required>
                                    <option value="">Select Company</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Job Title *</label>
                                <input type="text" class="form-control" name="title" required placeholder="e.g., Software Developer">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Location *</label>
                                <input type="text" class="form-control" name="location" required placeholder="e.g., Ormoc City">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Employment Type *</label>
                                <select class="form-select" name="employment_type" required>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Contract">Contract</option>
                                    <option value="Internship">Internship</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Experience Level *</label>
                                <select class="form-select" name="experience_level" required>
                                    <option value="Entry Level">Entry Level</option>
                                    <option value="Mid Level">Mid Level</option>
                                    <option value="Senior Level">Senior Level</option>
                                    <option value="Executive">Executive</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Salary Range</label>
                                <input type="text" class="form-control" name="salary_range" placeholder="e.g., ₱30,000 - ₱40,000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Application Deadline</label>
                                <input type="date" class="form-control" name="deadline">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="pending">Pending Review</option>
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Job Description *</label>
                            <textarea class="form-control" name="description" rows="4" required placeholder="Describe the job responsibilities..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Requirements</label>
                            <textarea class="form-control" name="requirements" rows="3" placeholder="List qualifications and requirements..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create Job
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Add New Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name *</label>
                            <input type="text" class="form-control" name="category_name" required placeholder="e.g., Information Technology">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Brief description of this category..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);">
                            <i class="fas fa-plus me-1"></i>Add Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ultimate fix for modal shaking - Complete Bootstrap override
        (function() {
            'use strict';
            
            // Inject CSS override immediately
            const style = document.createElement('style');
            style.id = 'modal-ultimate-fix';
            style.textContent = `
                .modal.fade .modal-dialog {
                    transition: none !important;
                    transform: none !important;
                    -webkit-transform: none !important;
                    -moz-transform: none !important;
                    -ms-transform: none !important;
                    -o-transform: none !important;
                    will-change: auto !important;
                }
                .modal.show .modal-dialog,
                .modal-dialog {
                    transform: none !important;
                    -webkit-transform: none !important;
                    -moz-transform: none !important;
                    -ms-transform: none !important;
                    -o-transform: none !important;
                    transition: none !important;
                    will-change: auto !important;
                }
                .modal-content {
                    transform: none !important;
                    -webkit-transform: none !important;
                    -moz-transform: none !important;
                    -ms-transform: none !important;
                    -o-transform: none !important;
                    will-change: auto !important;
                }
            `;
            document.head.insertBefore(style, document.head.firstChild);
            
            // Wait for DOM and Bootstrap to load
            document.addEventListener('DOMContentLoaded', function() {
                // Override Bootstrap's modal show method to prevent transforms
                const modals = document.querySelectorAll('.modal');
                
                modals.forEach(function(modal) {
                    // Force no transform on all events
                    const forceNoTransform = function() {
                        const dialog = modal.querySelector('.modal-dialog');
                        const content = modal.querySelector('.modal-content');
                        
                        if (dialog) {
                            dialog.style.setProperty('transform', 'none', 'important');
                            dialog.style.setProperty('-webkit-transform', 'none', 'important');
                            dialog.style.setProperty('-moz-transform', 'none', 'important');
                            dialog.style.setProperty('-ms-transform', 'none', 'important');
                            dialog.style.setProperty('-o-transform', 'none', 'important');
                            dialog.style.setProperty('transition', 'none', 'important');
                            dialog.style.setProperty('will-change', 'auto', 'important');
                        }
                        
                        if (content) {
                            content.style.setProperty('transform', 'none', 'important');
                            content.style.setProperty('-webkit-transform', 'none', 'important');
                            content.style.setProperty('-moz-transform', 'none', 'important');
                            content.style.setProperty('-ms-transform', 'none', 'important');
                            content.style.setProperty('-o-transform', 'none', 'important');
                        }
                    };
                    
                    // Apply on all modal events
                    ['show', 'shown', 'hide', 'hidden'].forEach(function(event) {
                        modal.addEventListener('show.bs.modal', forceNoTransform);
                        modal.addEventListener('shown.bs.modal', forceNoTransform);
                        modal.addEventListener('hide.bs.modal', forceNoTransform);
                        modal.addEventListener('hidden.bs.modal', forceNoTransform);
                    });
                    
                    // Also apply immediately
                    forceNoTransform();
                });
                
                // Use MutationObserver to catch any dynamic changes
                const observer = new MutationObserver(function(mutations) {
                    modals.forEach(function(modal) {
                        const dialog = modal.querySelector('.modal-dialog');
                        if (dialog && dialog.style.transform !== 'none') {
                            dialog.style.setProperty('transform', 'none', 'important');
                            dialog.style.setProperty('-webkit-transform', 'none', 'important');
                        }
                    });
                });
                
                modals.forEach(function(modal) {
                    observer.observe(modal, {
                        attributes: true,
                        attributeFilter: ['class', 'style'],
                        childList: false,
                        subtree: true
                    });
                });
            });
        })();
    </script>
</body>
</html>
