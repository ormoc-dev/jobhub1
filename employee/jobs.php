<?php
include '../config.php';
requireRole('employee');
include '../employer/includes/matching.php';

// Clean up expired jobs with no applications
cleanupExpiredJobs();

// Get employee profile
$stmt = $pdo->prepare("SELECT ep.*, u.email FROM employee_profiles ep 
                       JOIN users u ON ep.user_id = u.id 
                       WHERE ep.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

if (!$profile) {
    redirect('profile.php');
}

$employeeText = buildCandidateText($profile);
$employeeId = $profile['id'] ?? 0;

// Get active tab
$activeTab = $_GET['tab'] ?? 'search';
if (!in_array($activeTab, ['search', 'recommended', 'saved'])) {
    $activeTab = 'search';
}

// ========== JOB SEARCH TAB ==========
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';
$job_type = $_GET['job_type'] ?? '';
$employment_type = $_GET['employment_type'] ?? '';

$whereClause = "WHERE jp.status = 'active' AND c.status = 'active' AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR c.company_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($category)) {
    $whereClause .= " AND jp.category_id = ?";
    $params[] = $category;
}

if (!empty($location)) {
    $whereClause .= " AND jp.location LIKE ?";
    $params[] = "%$location%";
}

if (!empty($job_type)) {
    $whereClause .= " AND jp.job_type = ?";
    $params[] = $job_type;
}

if (!empty($employment_type)) {
    $whereClause .= " AND jp.employment_type = ?";
    $params[] = $employment_type;
}

$sql = "SELECT jp.*, c.company_name, c.company_logo, jc.category_name,
               CASE WHEN sj.id IS NOT NULL THEN 1 ELSE 0 END as is_saved,
               (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND employee_id = ?) as has_applied
        FROM job_postings jp 
        JOIN companies c ON jp.company_id = c.id 
        LEFT JOIN job_categories jc ON jp.category_id = jc.id 
        LEFT JOIN saved_jobs sj ON jp.id = sj.job_id AND sj.employee_id = ?
        $whereClause 
        ORDER BY jp.posted_date DESC LIMIT 20";

$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge([$employeeId, $employeeId], $params));
$searchJobs = $stmt->fetchAll();

// ========== RECOMMENDED JOBS TAB ==========
$minScore = isset($_GET['min_score']) ? max(0, min(100, (int) $_GET['min_score'])) : 0;

$jobsStmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo, jc.category_name,
                                  CASE WHEN sj.id IS NOT NULL THEN 1 ELSE 0 END as is_saved,
                                  (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND employee_id = ?) as has_applied
                           FROM job_postings jp 
                           JOIN companies c ON jp.company_id = c.id 
                           LEFT JOIN job_categories jc ON jp.category_id = jc.id
                           LEFT JOIN saved_jobs sj ON jp.id = sj.job_id AND sj.employee_id = ?
                          WHERE jp.status = 'active' AND c.status = 'active'
                           ORDER BY jp.posted_date DESC");
$jobsStmt->execute([$employeeId, $employeeId]);
$allJobs = $jobsStmt->fetchAll();

$recommendedJobs = [];
if (!empty($allJobs)) {
    foreach ($allJobs as $job) {
        $score = computeMatchScore($job, $profile);
        $job['match_score'] = $score;
        
        if ($score >= $minScore) {
            $recommendedJobs[] = $job;
        }
    }
    
    usort($recommendedJobs, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });
}

function scoreBadgeClass(int $score): string {
    if ($score >= 80) return 'bg-success';
    if ($score >= 60) return 'bg-info';
    if ($score >= 40) return 'bg-warning text-dark';
    return 'bg-secondary';
}

function scoreProgressClass(int $score): string {
    if ($score >= 80) return 'bg-success';
    if ($score >= 60) return 'bg-info';
    if ($score >= 40) return 'bg-warning';
    return 'bg-secondary';
}

// ========== SAVED JOBS TAB ==========
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM saved_jobs sj 
                       JOIN job_postings jp ON sj.job_id = jp.id 
                       WHERE sj.employee_id = ? AND jp.status = 'active'");
$stmt->execute([$profile['id']]);
$total_saved = $stmt->fetchColumn();
$total_pages = ceil($total_saved / $per_page);

$stmt = $pdo->prepare("SELECT jp.*, c.company_name, c.company_logo, jc.category_name, 
                              sj.saved_date, jp.posted_date,
                              (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id AND employee_id = ?) as applied
                       FROM saved_jobs sj
                       JOIN job_postings jp ON sj.job_id = jp.id
                       JOIN companies c ON jp.company_id = c.id
                       LEFT JOIN job_categories jc ON jp.category_id = jc.id
                       WHERE sj.employee_id = ? AND jp.status = 'active'
                       ORDER BY sj.saved_date DESC
                       LIMIT $per_page OFFSET $offset");
$stmt->execute([$profile['id'], $profile['id']]);
$savedJobs = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM job_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);
            --primary-color: #10b981;
            --primary-dark: #047857;
            --secondary-color: #059669;
            --accent-color: #34d399;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --bg-light: #f9fafb;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --card-hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        body {
            background: var(--bg-light);
        }

        .jobs-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .nav-tabs {
            border-bottom: 3px solid #e5e7eb;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            color: var(--text-light);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 1rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            background: transparent;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            border-bottom-color: var(--accent-color);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background: transparent;
        }

        .job-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }

        .job-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-2px);
        }

        .company-logo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
        }

        .company-logo-placeholder {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .job-title {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .job-title:hover {
            color: var(--primary-color);
        }

        .company-name {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .job-meta span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .job-meta i {
            color: var(--primary-color);
        }

        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4);
            color: white;
        }

        .btn-outline-primary-custom {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: white;
        }

        .btn-outline-primary-custom:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .match-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .match-progress {
            height: 8px;
            border-radius: 10px;
            background: #e5e7eb;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-hover-shadow);
        }

        .stats-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stats-card .label {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }

        .save-btn {
            border: 2px solid var(--secondary-color);
            color: var(--secondary-color);
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .save-btn:hover {
            background: var(--secondary-color);
            color: white;
        }

        .save-btn.saved {
            background: #fee2e2;
            border-color: #ef4444;
            color: #ef4444;
        }

        .save-btn.saved:hover {
            background: #ef4444;
            color: white;
        }

        .tab-content {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employee-main-content">
        <!-- Header -->
        <div class="jobs-header">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1"><i class="fas fa-briefcase me-2"></i>Find Your Dream Job</h1>
                        <p class="mb-0 opacity-90">Discover opportunities that match your skills and interests</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="number"><?php echo count($searchJobs); ?></div>
                        <div class="label">Available Jobs</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="number"><?php echo count($recommendedJobs); ?></div>
                        <div class="label">Recommended for You</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="number"><?php echo $total_saved; ?></div>
                        <div class="label">Saved Jobs</div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <ul class="nav nav-tabs" id="jobsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'search' ? 'active' : ''; ?>" 
                            id="search-tab" data-bs-toggle="tab" data-bs-target="#search" 
                            type="button" role="tab">
                        <i class="fas fa-search me-2"></i>Job Search
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'recommended' ? 'active' : ''; ?>" 
                            id="recommended-tab" data-bs-toggle="tab" data-bs-target="#recommended" 
                            type="button" role="tab">
                        <i class="fas fa-star me-2"></i>Recommended Jobs
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?php echo $activeTab === 'saved' ? 'active' : ''; ?>" 
                            id="saved-tab" data-bs-toggle="tab" data-bs-target="#saved" 
                            type="button" role="tab">
                        <i class="fas fa-heart me-2"></i>Saved Jobs
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="jobsTabsContent">
                <!-- Job Search Tab -->
                <div class="tab-pane fade <?php echo $activeTab === 'search' ? 'show active' : ''; ?>" 
                     id="search" role="tabpanel">
                    <div class="filter-card">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="tab" value="search">
                            <div class="col-md-4">
                                <label for="search" class="form-label fw-bold">Search Jobs</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Job title, company, keywords...">
                            </div>
                            <div class="col-md-2">
                                <label for="category" class="form-label fw-bold">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="location" class="form-label fw-bold">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location); ?>" 
                                       placeholder="City, state...">
                            </div>
                            <div class="col-md-2">
                                <label for="job_type" class="form-label fw-bold">Job Type</label>
                                <select class="form-select" id="job_type" name="job_type">
                                    <option value="">All Types</option>
                                    <option value="Full Time" <?php echo $job_type === 'Full Time' ? 'selected' : ''; ?>>Full Time</option>
                                    <option value="Part Time" <?php echo $job_type === 'Part Time' ? 'selected' : ''; ?>>Part Time</option>
                                    <option value="Freelance" <?php echo $job_type === 'Freelance' ? 'selected' : ''; ?>>Freelance</option>
                                    <option value="Internship" <?php echo $job_type === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                    <option value="Contract-Based" <?php echo $job_type === 'Contract-Based' ? 'selected' : ''; ?>>Contract-Based</option>
                                    <option value="Temporary" <?php echo $job_type === 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                                    <option value="Work From Home" <?php echo $job_type === 'Work From Home' ? 'selected' : ''; ?>>Work From Home</option>
                                    <option value="On-Site" <?php echo $job_type === 'On-Site' ? 'selected' : ''; ?>>On-Site</option>
                                    <option value="Hybrid" <?php echo $job_type === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="employment_type" class="form-label fw-bold">Work Location</label>
                                <select class="form-select" id="employment_type" name="employment_type">
                                    <option value="">All Locations</option>
                                    <option value="Onsite" <?php echo $employment_type === 'Onsite' ? 'selected' : ''; ?>>Onsite</option>
                                    <option value="Work from Home" <?php echo $employment_type === 'Work from Home' ? 'selected' : ''; ?>>Work from Home</option>
                                    <option value="Hybrid" <?php echo $employment_type === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="fas fa-search me-1"></i>Search Jobs
                                </button>
                                <a href="jobs.php?tab=search" class="btn btn-outline-primary-custom">
                                    <i class="fas fa-times me-1"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($searchJobs)): ?>
                        <div class="job-card empty-state">
                            <i class="fas fa-search"></i>
                            <h5>No jobs found</h5>
                            <p>Try adjusting your search criteria or browse all available positions.</p>
                            <a href="jobs.php?tab=search" class="btn btn-primary-custom">Browse All Jobs</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($searchJobs as $job): ?>
                            <div class="job-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start mb-3">
                                            <?php if ($job['company_logo']): ?>
                                                <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                                     alt="Company Logo" class="company-logo me-3">
                                            <?php else: ?>
                                                <div class="company-logo-placeholder me-3">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="job-title">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                </a>
                                                <p class="company-name mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                                <div class="job-meta">
                                                    <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                                    <?php if ($job['job_type']): ?>
                                                        <span><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['job_type']); ?></span>
                                                    <?php endif; ?>
                                                    <span><i class="fas fa-clock"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                                    <?php if ($job['salary_range']): ?>
                                                        <span><i class="fas fa-money-bill-wave"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-muted mb-0">
                                                    <?php echo substr(strip_tags($job['description']), 0, 150) . '...'; ?>
                                                </p>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>Posted <?php echo date('M j, Y', strtotime($job['posted_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-grid gap-2">
                                            <?php if (isset($job['has_applied']) && $job['has_applied'] > 0): ?>
                                                <button class="btn btn-success" disabled>
                                                    <i class="fas fa-check-circle me-1"></i>Already Applied
                                                </button>
                                            <?php else: ?>
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>#apply" class="btn btn-primary-custom">
                                                    <i class="fas fa-paper-plane me-1"></i>APPLY NOW
                                                </a>
                                            <?php endif; ?>
                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary-custom">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                            <button type="button" class="btn save-btn <?php echo $job['is_saved'] ? 'saved' : ''; ?>" 
                                                    data-job-id="<?php echo $job['id']; ?>"
                                                    data-saved="<?php echo $job['is_saved']; ?>">
                                                <i class="fas fa-heart me-1"></i>
                                                <?php echo $job['is_saved'] ? 'Saved' : 'Save Job'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Recommended Jobs Tab -->
                <div class="tab-pane fade <?php echo $activeTab === 'recommended' ? 'show active' : ''; ?>" 
                     id="recommended" role="tabpanel">
                    <?php if (trim($employeeText) === ''): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Complete your profile to get more accurate matching scores.
                            <a href="profile.php" class="alert-link">Update profile</a>.
                        </div>
                    <?php endif; ?>

                    <div class="filter-card">
                        <form method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="tab" value="recommended">
                            <div class="col-md-4">
                                <label for="min_score" class="form-label fw-bold">Minimum Match Score</label>
                                <select class="form-select" name="min_score" id="min_score">
                                    <?php foreach ([0, 20, 40, 60, 80] as $scoreOption): ?>
                                        <option value="<?php echo $scoreOption; ?>" <?php echo $minScore === $scoreOption ? 'selected' : ''; ?>>
                                            <?php echo $scoreOption; ?>% and above
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary-custom w-100">
                                    <i class="fas fa-filter me-1"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($recommendedJobs)): ?>
                        <div class="job-card empty-state">
                            <i class="fas fa-star"></i>
                            <h5>No recommended jobs found</h5>
                            <p>Try lowering the minimum match score or complete your profile for better matches.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recommendedJobs as $job): ?>
                            <div class="job-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start mb-3">
                                            <?php if ($job['company_logo']): ?>
                                                <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                                     alt="Company Logo" class="company-logo me-3">
                                            <?php else: ?>
                                                <div class="company-logo-placeholder me-3">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="job-title">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                </a>
                                                <p class="company-name mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                                <div class="job-meta">
                                                    <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                                    <?php if ($job['job_type']): ?>
                                                        <span><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['job_type']); ?></span>
                                                    <?php endif; ?>
                                                    <span><i class="fas fa-clock"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                                    <?php if ($job['salary_range']): ?>
                                                        <span><i class="fas fa-money-bill-wave"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="match-badge <?php echo scoreBadgeClass((int) $job['match_score']); ?>">
                                                        Match <?php echo $job['match_score']; ?>%
                                                    </span>
                                                    <div class="match-progress">
                                                        <div class="progress-bar <?php echo scoreProgressClass((int) $job['match_score']); ?>" 
                                                             style="width: <?php echo $job['match_score']; ?>%; height: 100%;"></div>
                                                    </div>
                                                </div>
                                                <p class="text-muted mb-0 mt-2">
                                                    <?php echo substr(strip_tags($job['description']), 0, 150) . '...'; ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-grid gap-2">
                                            <?php if (isset($job['has_applied']) && $job['has_applied'] > 0): ?>
                                                <button class="btn btn-success" disabled>
                                                    <i class="fas fa-check-circle me-1"></i>Already Applied
                                                </button>
                                            <?php else: ?>
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>#apply" class="btn btn-primary-custom">
                                                    <i class="fas fa-paper-plane me-1"></i>Apply Now
                                                </a>
                                            <?php endif; ?>
                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary-custom">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                            <button type="button" class="btn save-btn <?php echo $job['is_saved'] ? 'saved' : ''; ?>" 
                                                    data-job-id="<?php echo $job['id']; ?>"
                                                    data-saved="<?php echo $job['is_saved']; ?>">
                                                <i class="fas fa-heart me-1"></i>
                                                <?php echo $job['is_saved'] ? 'Saved' : 'Save Job'; ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Saved Jobs Tab -->
                <div class="tab-pane fade <?php echo $activeTab === 'saved' ? 'show active' : ''; ?>" 
                     id="saved" role="tabpanel">
                    <?php if (empty($savedJobs)): ?>
                        <div class="job-card empty-state">
                            <i class="fas fa-heart"></i>
                            <h5>No saved jobs yet</h5>
                            <p>Start saving jobs that interest you to keep track of them easily.</p>
                            <a href="jobs.php?tab=search" class="btn btn-primary-custom">
                                <i class="fas fa-search me-1"></i>Browse All Jobs
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($savedJobs as $job): ?>
                            <div class="job-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-start mb-3">
                                            <?php if ($job['company_logo']): ?>
                                                <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                                     alt="Company Logo" class="company-logo me-3">
                                            <?php else: ?>
                                                <div class="company-logo-placeholder me-3">
                                                    <i class="fas fa-building"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="job-title">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                    <?php if ($job['applied']): ?>
                                                        <span class="badge bg-success ms-2">Applied</span>
                                                    <?php endif; ?>
                                                </a>
                                                <p class="company-name mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                                <div class="job-meta">
                                                    <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                                    <span><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                                    <?php if ($job['category_name']): ?>
                                                        <span><i class="fas fa-tag"></i><?php echo htmlspecialchars($job['category_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($job['salary_range']): ?>
                                                    <div class="fw-bold mb-2" style="color: var(--primary-color);">
                                                        <i class="fas fa-money-bill-wave me-1"></i>
                                                        <?php echo htmlspecialchars($job['salary_range']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-heart me-1"></i>Saved on <?php echo date('M j, Y', strtotime($job['saved_date'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="d-grid gap-2">
                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-primary-custom">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                            <?php if (!$job['applied']): ?>
                                                <a href="../job-details.php?id=<?php echo $job['id']; ?>#apply" class="btn btn-outline-primary-custom">
                                                    <i class="fas fa-paper-plane me-1"></i>Apply Now
                                                </a>
                                            <?php else: ?>
                                                <a href="applications.php" class="btn btn-outline-primary-custom">
                                                    <i class="fas fa-check me-1"></i>View Application
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn save-btn saved" 
                                                    data-job-id="<?php echo $job['id']; ?>"
                                                    data-saved="1"
                                                    data-remove="true">
                                                <i class="fas fa-heart-broken me-1"></i>Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Saved jobs pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?tab=saved&page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?tab=saved&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?tab=saved&page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Save/Unsave job functionality
        document.querySelectorAll('.save-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const jobId = this.dataset.jobId;
                const isSaved = this.dataset.saved === '1';
                const isRemove = this.dataset.remove === 'true';
                const action = (isSaved || isRemove) ? 'unsave' : 'save';
                const btnElement = this;
                
                fetch('../ajax/save-job.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=${action}&job_id=${jobId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (action === 'unsave') {
                            // If removing from saved tab, reload the page
                            if (isRemove) {
                                window.location.reload();
                            } else {
                                btnElement.innerHTML = '<i class="fas fa-heart me-1"></i>Save Job';
                                btnElement.dataset.saved = '0';
                                btnElement.classList.remove('saved');
                            }
                        } else {
                            btnElement.innerHTML = '<i class="fas fa-heart me-1"></i>Saved';
                            btnElement.dataset.saved = '1';
                            btnElement.classList.add('saved');
                        }
                    } else {
                        alert(data.message || 'An error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the job');
                });
            });
        });
    </script>
</body>
</html>
