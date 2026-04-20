<?php
include 'config.php';

// Get company ID from URL
$company_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$company_id) {
    redirect('companies.php');
}

// Get company details with job statistics
$stmt = $pdo->prepare("SELECT c.*, u.created_at as user_created, u.email as user_email,
                       COUNT(jp.id) as total_jobs,
                       COUNT(CASE WHEN jp.status = 'active' THEN 1 END) as active_jobs
                       FROM companies c 
                       LEFT JOIN users u ON c.user_id = u.id
                       LEFT JOIN job_postings jp ON c.id = jp.company_id 
                       WHERE c.id = ? AND c.status = 'active'
                       GROUP BY c.id");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if (!$company) {
    $_SESSION['error'] = 'Company not found or is not currently active.';
    redirect('companies.php');
}

// Get recent active jobs from this company
$stmt = $pdo->prepare("SELECT jp.*, jc.category_name,
                       (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count
                       FROM job_postings jp
                       LEFT JOIN job_categories jc ON jp.category_id = jc.id
                       WHERE jp.company_id = ? AND jp.status = 'active'
                       ORDER BY jp.posted_date DESC 
                       LIMIT 10");
$stmt->execute([$company_id]);
$jobs = $stmt->fetchAll();

// Get company statistics
$stats = [];
$stats['total_jobs'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ?");
$stats['total_jobs']->execute([$company_id]);
$stats['total_jobs'] = $stats['total_jobs']->fetchColumn();

$stats['active_jobs'] = $pdo->prepare("SELECT COUNT(*) FROM job_postings WHERE company_id = ? AND status = 'active'");
$stats['active_jobs']->execute([$company_id]);
$stats['active_jobs'] = $stats['active_jobs']->fetchColumn();

$stats['total_applications'] = $pdo->prepare("SELECT COUNT(*) FROM job_applications ja 
                                             JOIN job_postings jp ON ja.job_id = jp.id 
                                             WHERE jp.company_id = ?");
$stats['total_applications']->execute([$company_id]);
$stats['total_applications'] = $stats['total_applications']->fetchColumn();

// Page title
$page_title = htmlspecialchars($company['company_name']) . ' - Company Details';

$contactEmail = '';
if (!empty($company['contact_email'])) {
    $contactEmail = trim($company['contact_email']);
} elseif (!empty($company['user_email'])) {
    $contactEmail = trim($company['user_email']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
    <style>
        :root {
            --wl-primary: #1e3a8a;
            --wl-primary-dark: #172554;
            --wl-accent: #14b8a6;
            --wl-ink: #0f172a;
            --wl-soft: #f8fafc;
            --wl-card: #ffffff;
        }

        body {
            background: #f3f5f7;
            color: var(--wl-ink);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        /* Enhanced Company Header */
        .company-header {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.95) 0%, rgba(23, 37, 84, 0.95) 100%);
            color: white;
            padding: 100px 0 60px;
            position: relative;
            overflow: hidden;
        }
        .company-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }
        .company-header .container {
            position: relative;
            z-index: 1;
        }
        .company-logo {
            width: 140px;
            height: 140px;
            object-fit: cover;
            border: 5px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .company-logo:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }

        /* Enhanced Stat Cards */
        .stat-card {
            background: var(--wl-card);
            border-radius: 20px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(15, 23, 42, 0.05);
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--wl-primary), var(--wl-accent));
        }
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }
        .stat-card h3 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--wl-primary);
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--wl-primary), var(--wl-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .stat-card p {
            color: #64748b;
            margin: 0;
            font-size: 1rem;
            font-weight: 500;
        }
        .stat-card .stat-icon {
            font-size: 2.5rem;
            color: var(--wl-accent);
            margin-bottom: 1rem;
            opacity: 0.8;
        }

        /* Enhanced Info Cards */
        .company-info-card {
            background: var(--wl-card);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 16px 30px rgba(15, 23, 42, 0.08);
            margin-bottom: 30px;
            border: 1px solid rgba(15, 23, 42, 0.05);
            transition: box-shadow 0.3s ease;
        }
        .company-info-card:hover {
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
        }
        .company-info-card h2 {
            color: var(--wl-ink);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid var(--wl-accent);
        }
        .company-info-card h5 {
            color: var(--wl-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .company-info-card ul li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(15, 23, 42, 0.05);
        }
        .company-info-card ul li:last-child {
            border-bottom: none;
        }
        .company-info-card ul li strong {
            color: var(--wl-ink);
            font-weight: 600;
            display: inline-block;
            min-width: 140px;
        }

        /* Enhanced Job Cards */
        .job-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            background: var(--wl-card);
        }
        .job-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.15);
        }
        .job-card .card-body {
            padding: 1.5rem;
        }
        .job-card .card-title {
            margin-bottom: 1rem;
        }
        .job-card .job-title {
            color: var(--wl-ink);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }
        .job-card .job-title:hover {
            color: var(--wl-accent);
        }
        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        .job-meta .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            border-radius: 8px;
        }

        /* Enhanced Buttons */
        .btn-accent {
            background: var(--wl-accent);
            border-color: var(--wl-accent);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(20, 184, 166, 0.3);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .btn-accent:hover {
            background: #0f766e;
            border-color: #0f766e;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(20, 184, 166, 0.4);
        }
        .btn-outline-accent {
            border: 2px solid var(--wl-accent);
            color: var(--wl-accent);
            background: transparent;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .btn-outline-accent:hover {
            background: var(--wl-accent);
            border-color: var(--wl-accent);
            color: #ffffff;
            transform: translateY(-2px);
        }

        /* Quick Actions Card */
        .quick-actions-card {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.05), rgba(20, 184, 166, 0.05));
            border: 2px solid rgba(20, 184, 166, 0.1);
        }

        /* Featured Badge */
        .featured-badge {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 8px 24px rgba(245, 158, 11, 0.3);
        }
        .featured-badge i {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* Section Spacing */
        section {
            padding-top: 3rem;
            padding-bottom: 3rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .company-header {
                padding: 80px 0 40px;
            }
            .company-logo {
                width: 100px;
                height: 100px;
            }
            .stat-card h3 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body class="wl-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-glass" style="background: linear-gradient(135deg, rgba(30, 58, 138, 0.95) 0%, rgba(23, 37, 84, 0.95) 100%); box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25); backdrop-filter: blur(8px);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center py-1" href="index.php">
                <img src="images/LOGO.png" alt="WORKLINK Job Seeker System" class="navbar-brand-logo" style="height: 48px; width: auto; max-width: min(260px, 58vw); object-fit: contain;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Employee & Employer Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="companies.php">Companies</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">Available Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'User'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if (getUserRole() === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">Admin Dashboard</a></li>
                                <?php elseif (getUserRole() === 'employee'): ?>
                                    <li><a class="dropdown-item" href="employee/dashboard.php">My Dashboard</a></li>
                                <?php elseif (getUserRole() === 'employer'): ?>
                                    <li><a class="dropdown-item" href="employer/dashboard.php">Employer Dashboard</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link btn btn-auth-login px-3" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-auth-signup ms-2 px-3" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Company Header -->
    <section class="company-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <?php if ($company['company_logo']): ?>
                            <img src="<?php echo htmlspecialchars($company['company_logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($company['company_name']); ?>" 
                                 class="company-logo rounded-circle me-4">
                        <?php else: ?>
                            <div class="company-logo rounded-circle me-4 d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-building fa-3x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h1 class="display-5 fw-bold mb-2"><?php echo htmlspecialchars($company['company_name']); ?></h1>
                            <?php if (!empty($company['industry'])): ?>
                                <p class="lead mb-2">
                                    <i class="fas fa-industry me-2"></i><?php echo htmlspecialchars($company['industry']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($company['location_address']): ?>
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($company['location_address']); ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($company['company_size'])): ?>
                                <p class="mb-0">
                                    <i class="fas fa-users me-2"></i><?php echo htmlspecialchars($company['company_size']); ?> employees
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="d-flex flex-column gap-2">
                        <?php if ($company['active_jobs'] > 0): ?>
                            <a href="jobs.php?company=<?php echo $company['id']; ?>" class="btn btn-accent btn-lg">
                                <i class="fas fa-briefcase me-2"></i>View Open Jobs (<?php echo $company['active_jobs']; ?>)
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($contactEmail)): ?>
                            <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>" class="btn btn-outline-accent">
                                <i class="fas fa-envelope me-2"></i>Contact Company
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Company Statistics -->
    <section class="py-5" style="background: linear-gradient(135deg, rgba(30, 58, 138, 0.12), rgba(29, 78, 216, 0.08));">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-briefcase stat-icon"></i>
                        <h3><?php echo $stats['total_jobs']; ?></h3>
                        <p>Total Jobs Posted</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-check-circle stat-icon"></i>
                        <h3><?php echo $stats['active_jobs']; ?></h3>
                        <p>Currently Open Jobs</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <i class="fas fa-file-alt stat-icon"></i>
                        <h3><?php echo $stats['total_applications']; ?></h3>
                        <p>Total Applications Received</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Company Information -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <div class="company-info-card">
                        <h2 class="mb-4">About <?php echo htmlspecialchars($company['company_name']); ?></h2>
                        <?php if ($company['description']): ?>
                            <div class="mb-4">
                                <?php echo nl2br(htmlspecialchars($company['description'])); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-secondary">No company description available.</p>
                        <?php endif; ?>

                        <!-- Company Details -->
                        <div class="row mt-5">
                            <div class="col-md-6 mb-4">
                                <div class="p-4" style="background: linear-gradient(135deg, rgba(30, 58, 138, 0.05), rgba(20, 184, 166, 0.05)); border-radius: 16px; border-left: 4px solid var(--wl-primary);">
                                    <h5 class="mb-3"><i class="fas fa-building me-2" style="color: var(--wl-primary);"></i>Company Information</h5>
                                    <ul class="list-unstyled">
                                        <?php if (!empty($company['industry'])): ?>
                                            <li class="mb-2"><strong><i class="fas fa-industry me-2" style="color: var(--wl-accent);"></i>Industry:</strong> <?php echo htmlspecialchars($company['industry']); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($company['company_size'])): ?>
                                            <li class="mb-2"><strong><i class="fas fa-users me-2" style="color: var(--wl-accent);"></i>Company Size:</strong> <?php echo htmlspecialchars($company['company_size']); ?> employees</li>
                                        <?php endif; ?>
                                        <?php if ($company['user_created']): ?>
                                            <li class="mb-2"><strong><i class="fas fa-calendar me-2" style="color: var(--wl-accent);"></i>Founded:</strong> <?php echo date('F Y', strtotime($company['user_created'])); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="p-4" style="background: linear-gradient(135deg, rgba(30, 58, 138, 0.05), rgba(20, 184, 166, 0.05)); border-radius: 16px; border-left: 4px solid var(--wl-accent);">
                                    <h5 class="mb-3"><i class="fas fa-address-card me-2" style="color: var(--wl-accent);"></i>Contact Information</h5>
                                    <ul class="list-unstyled">
                                        <?php if ($company['contact_first_name'] && $company['contact_last_name']): ?>
                                            <li class="mb-2"><strong><i class="fas fa-user me-2" style="color: var(--wl-accent);"></i>Contact Person:</strong> <?php echo htmlspecialchars($company['contact_first_name'] . ' ' . $company['contact_last_name']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($company['contact_position']): ?>
                                            <li class="mb-2"><strong><i class="fas fa-briefcase me-2" style="color: var(--wl-accent);"></i>Position:</strong> <?php echo htmlspecialchars($company['contact_position']); ?></li>
                                        <?php endif; ?>
                                        <?php if ($company['contact_number']): ?>
                                            <li class="mb-2"><strong><i class="fas fa-phone me-2" style="color: var(--wl-accent);"></i>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($company['contact_number']); ?>" style="color: var(--wl-accent); text-decoration: none;"><?php echo htmlspecialchars($company['contact_number']); ?></a></li>
                                        <?php endif; ?>
                                        <?php if ($company['contact_email']): ?>
                                            <li class="mb-2"><strong><i class="fas fa-envelope me-2" style="color: var(--wl-accent);"></i>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($company['contact_email']); ?>" style="color: var(--wl-accent); text-decoration: none;"><?php echo htmlspecialchars($company['contact_email']); ?></a></li>
                                        <?php endif; ?>
                                        <?php if ($company['location_address']): ?>
                                            <li class="mb-2"><strong><i class="fas fa-map-marker-alt me-2" style="color: var(--wl-accent);"></i>Address:</strong> <?php echo htmlspecialchars($company['location_address']); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Job Openings -->
                    <?php if (!empty($jobs)): ?>
                        <div class="company-info-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2>Current Job Openings</h2>
                                <?php if (count($jobs) >= 10): ?>
                                    <a href="jobs.php?company=<?php echo $company['id']; ?>" class="btn btn-outline-accent">
                                        View All Jobs <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row g-4">
                                <?php foreach ($jobs as $job): ?>
                                    <div class="col-md-6">
                                        <div class="card job-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h5 class="card-title mb-0 flex-grow-1">
                                                        <a href="job-details.php?id=<?php echo $job['id']; ?>" class="job-title">
                                                            <?php echo htmlspecialchars($job['title']); ?>
                                                        </a>
                                                    </h5>
                                                    <?php if ($job['application_count'] > 0): ?>
                                                        <span class="badge" style="background: rgba(20, 184, 166, 0.1); color: var(--wl-accent); border: 1px solid var(--wl-accent);">
                                                            <i class="fas fa-users me-1"></i><?php echo $job['application_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="job-meta mb-3">
                                                    <?php if ($job['category_name']): ?>
                                                        <span class="badge me-2" style="background: var(--wl-accent); color: white;">
                                                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($job['category_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="badge me-2" style="background: rgba(30, 58, 138, 0.1); color: var(--wl-primary); border: 1px solid var(--wl-primary);">
                                                        <?php echo htmlspecialchars($job['employment_type']); ?>
                                                    </span>
                                                    <?php if ($job['location']): ?>
                                                        <span class="text-secondary small d-inline-flex align-items-center">
                                                            <i class="fas fa-map-marker-alt me-1" style="color: var(--wl-accent);"></i><?php echo htmlspecialchars($job['location']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="card-text text-secondary mb-3" style="line-height: 1.6;">
                                                    <?php echo substr(strip_tags($job['description']), 0, 120) . '...'; ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                                    <small class="text-secondary">
                                                        <i class="fas fa-clock me-1" style="color: var(--wl-accent);"></i>Posted <?php echo timeAgo($job['posted_date']); ?>
                                                    </small>
                                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-accent">
                                                        View Details <i class="fas fa-arrow-right ms-1"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="company-info-card quick-actions-card">
                        <h5 class="mb-4"><i class="fas fa-bolt me-2" style="color: var(--wl-accent);"></i>Quick Actions</h5>
                        <div class="d-grid gap-3">
                            <?php if ($company['active_jobs'] > 0): ?>
                                <a href="jobs.php?company=<?php echo $company['id']; ?>" class="btn btn-accent">
                                    <i class="fas fa-briefcase me-2"></i>View All Jobs (<?php echo $company['active_jobs']; ?>)
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($contactEmail)): ?>
                                <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>?subject=Inquiry%20about%20<?php echo urlencode($company['company_name']); ?>" class="btn btn-outline-accent">
                                    <i class="fas fa-envelope me-2"></i>Contact Company
                                </a>
                            <?php endif; ?>
                            <a href="companies.php" class="btn btn-outline-dark">
                                <i class="fas fa-arrow-left me-2"></i>Back to Companies
                            </a>
                        </div>
                    </div>

                    <?php if ($company['featured'] === 'yes' && (!$company['featured_until'] || strtotime($company['featured_until']) >= time())): ?>
                        <div class="featured-badge">
                            <i class="fas fa-star fa-3x mb-3"></i>
                            <h5 class="mb-2">Featured Company</h5>
                            <p class="mb-0" style="opacity: 0.9;">This company is one of our featured employers on WORKLINK.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer-dark text-white py-4 mt-5" style="background: #0b1220;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-briefcase me-2"></i>WORKLINK</h5>
                    <p class="text-muted">Connecting talent with opportunity across the Philippines.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">&copy; 2025 WORKLINK. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
