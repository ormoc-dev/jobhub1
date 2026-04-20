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
    <link href="css/modern.css" rel="stylesheet">
    <style>
        /* Additional custom styles for jobs page */
        .jobs-hero {
            background: linear-gradient(135deg, var(--primary) 0%, var(--purple) 100%);
            padding: 2rem;
            color: var(--white);
        }
        
        .jobs-hero h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .jobs-hero p {
            color: rgba(255, 255, 255, 0.85);
            margin: 0;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-pill {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 1rem;
            text-align: center;
            border: 1px solid var(--gray-200);
        }
        
        .stat-pill .number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .stat-pill .label {
            color: var(--gray-500);
            font-size: 0.8125rem;
        }
        
        @media (max-width: 768px) {
            .stats-overview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="employee-layout">
    <?php include 'includes/sidebar.php'; ?>

    <div class="employee-main-content">
        <!-- Hero Header -->
        <div class="jobs-hero">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <h1><i class="fas fa-briefcase me-2"></i>Find Your Dream Job</h1>
                    <p>Discover opportunities that match your skills and interests</p>
                </div>
                <a href="dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <div class="content-container">
            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stat-pill">
                    <div class="number"><?php echo count($searchJobs); ?></div>
                    <div class="label">Available Jobs</div>
                </div>
                <div class="stat-pill">
                    <div class="number"><?php echo count($recommendedJobs); ?></div>
                    <div class="label">Recommended for You</div>
                </div>
                <div class="stat-pill">
                    <div class="number"><?php echo $total_saved; ?></div>
                    <div class="label">Saved Jobs</div>
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
                        <div class="filter-card-header">
                            <i class="fas fa-filter"></i> Filter Jobs
                        </div>
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="tab" value="search">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Jobs</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Job title, company, keywords...">
                            </div>
                            <div class="col-md-2">
                                <label for="category" class="form-label">Category</label>
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
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location); ?>" 
                                       placeholder="City, state...">
                            </div>
                            <div class="col-md-2">
                                <label for="job_type" class="form-label">Job Type</label>
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
                                <label for="employment_type" class="form-label">Work Location</label>
                                <select class="form-select" id="employment_type" name="employment_type">
                                    <option value="">All Locations</option>
                                    <option value="Onsite" <?php echo $employment_type === 'Onsite' ? 'selected' : ''; ?>>Onsite</option>
                                    <option value="Work from Home" <?php echo $employment_type === 'Work from Home' ? 'selected' : ''; ?>>Work from Home</option>
                                    <option value="Hybrid" <?php echo $employment_type === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>Search Jobs
                                </button>
                                <a href="jobs.php?tab=search" class="btn btn-light ms-2">
                                    <i class="fas fa-times me-2"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($searchJobs)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <h5>No jobs found</h5>
                            <p>Try adjusting your search criteria or browse all available positions.</p>
                            <a href="jobs.php?tab=search" class="btn btn-primary">Browse All Jobs</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($searchJobs as $job): ?>
                            <div class="job-card">
                                <div class="job-card-header">
                                    <?php if ($job['company_logo']): ?>
                                        <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                             alt="Company Logo" class="company-logo">
                                    <?php else: ?>
                                        <div class="company-logo-placeholder">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="job-card-title">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </a>
                                        <div class="job-card-company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                    </div>
                                    <button type="button" class="btn-save <?php echo $job['is_saved'] ? 'saved' : ''; ?>" 
                                            data-job-id="<?php echo $job['id']; ?>"
                                            data-saved="<?php echo $job['is_saved']; ?>">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </div>
                                <div class="job-card-meta">
                                    <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                    <?php if ($job['job_type']): ?>
                                        <span><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['job_type']); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-clock"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                    <?php if ($job['salary_range']): ?>
                                        <span><i class="fas fa-money-bill-wave"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="job-card-description">
                                    <?php echo substr(strip_tags($job['description']), 0, 150) . '...'; ?>
                                </div>
                                <div class="job-card-footer">
                                    <div class="job-card-posted">
                                        <i class="fas fa-calendar me-1"></i>Posted <?php echo date('M j, Y', strtotime($job['posted_date'])); ?>
                                    </div>
                                    <div class="job-card-actions">
                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        <?php if (isset($job['has_applied']) && $job['has_applied'] > 0): ?>
                                            <button class="btn btn-success btn-sm" disabled>
                                                <i class="fas fa-check-circle me-1"></i>Applied
                                            </button>
                                        <?php else: ?>
                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>#apply" class="btn btn-primary btn-sm">
                                                <i class="fas fa-paper-plane me-1"></i>Apply Now
                                            </a>
                                        <?php endif; ?>
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
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <h5>No recommended jobs found</h5>
                            <p>Try lowering the minimum match score or complete your profile for better matches.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recommendedJobs as $job): ?>
                            <div class="job-card job-card-featured">
                                <div class="job-card-header">
                                    <?php if ($job['company_logo']): ?>
                                        <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                             alt="Company Logo" class="company-logo">
                                    <?php else: ?>
                                        <div class="company-logo-placeholder">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="job-card-title">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                        </a>
                                        <div class="job-card-company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="match-score <?php echo $job['match_score'] >= 70 ? 'high' : ($job['match_score'] >= 40 ? 'medium' : 'low'); ?>">
                                            <i class="fas fa-star me-1"></i><?php echo $job['match_score']; ?>% Match
                                        </span>
                                        <div class="match-progress">
                                            <div class="match-progress-bar <?php echo scoreProgressClass((int) $job['match_score']); ?>" 
                                                 style="width: <?php echo $job['match_score']; ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="job-card-meta">
                                    <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                    <?php if ($job['job_type']): ?>
                                        <span><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['job_type']); ?></span>
                                    <?php endif; ?>
                                    <span><i class="fas fa-clock"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                    <?php if ($job['salary_range']): ?>
                                        <span><i class="fas fa-money-bill-wave"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="job-card-description">
                                    <?php echo substr(strip_tags($job['description']), 0, 150) . '...'; ?>
                                </div>
                                <div class="job-card-footer">
                                    <div class="job-card-posted">
                                        <i class="fas fa-calendar me-1"></i>Posted <?php echo date('M j, Y', strtotime($job['posted_date'])); ?>
                                    </div>
                                    <div class="job-card-actions">
                                        <button type="button" class="btn-save <?php echo $job['is_saved'] ? 'saved' : ''; ?>" 
                                                data-job-id="<?php echo $job['id']; ?>"
                                                data-saved="<?php echo $job['is_saved']; ?>">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        <?php if (isset($job['has_applied']) && $job['has_applied'] > 0): ?>
                                            <button class="btn btn-success btn-sm" disabled>
                                                <i class="fas fa-check-circle me-1"></i>Applied
                                            </button>
                                        <?php else: ?>
                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>#apply" class="btn btn-primary btn-sm">
                                                <i class="fas fa-paper-plane me-1"></i>Apply Now
                                            </a>
                                        <?php endif; ?>
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
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h5>No saved jobs yet</h5>
                            <p>Start saving jobs that interest you to keep track of them easily.</p>
                            <a href="jobs.php?tab=search" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Browse All Jobs
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($savedJobs as $job): ?>
                            <div class="job-card">
                                <div class="job-card-header">
                                    <?php if ($job['company_logo']): ?>
                                        <img src="../<?php echo htmlspecialchars($job['company_logo']); ?>" 
                                             alt="Company Logo" class="company-logo">
                                    <?php else: ?>
                                        <div class="company-logo-placeholder">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="job-card-title">
                                            <?php echo htmlspecialchars($job['title']); ?>
                                            <?php if ($job['applied']): ?>
                                                <span class="badge badge-soft-success ms-2">Applied</span>
                                            <?php endif; ?>
                                        </a>
                                        <div class="job-card-company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                                    </div>
                                    <button type="button" class="btn-save saved" 
                                            data-job-id="<?php echo $job['id']; ?>"
                                            data-saved="1"
                                            data-remove="true">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                </div>
                                <div class="job-card-meta">
                                    <span><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
                                    <span><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['employment_type']); ?></span>
                                    <?php if ($job['category_name']): ?>
                                        <span><i class="fas fa-tag"></i><?php echo htmlspecialchars($job['category_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($job['salary_range']): ?>
                                    <div class="mb-2" style="color: var(--success); font-weight: 600;">
                                        <i class="fas fa-money-bill-wave me-1"></i>
                                        <?php echo htmlspecialchars($job['salary_range']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="job-card-footer">
                                    <div class="job-card-posted">
                                        <i class="fas fa-heart me-1" style="color: var(--pink);"></i>Saved on <?php echo date('M j, Y', strtotime($job['saved_date'])); ?>
                                    </div>
                                    <div class="job-card-actions">
                                        <a href="../job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Details
                                        </a>
                                        <?php if (!$job['applied']): ?>
                                            <a href="../job-details.php?id=<?php echo $job['id']; ?>#apply" class="btn btn-primary btn-sm">
                                                <i class="fas fa-paper-plane me-1"></i>Apply Now
                                            </a>
                                        <?php else: ?>
                                            <a href="applications.php" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i>View Application
                                            </a>
                                        <?php endif; ?>
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
        document.querySelectorAll('.btn-save').forEach(function(btn) {
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
                                btnElement.innerHTML = '<i class="fas fa-heart"></i>';
                                btnElement.dataset.saved = '0';
                                btnElement.classList.remove('saved');
                            }
                        } else {
                            btnElement.innerHTML = '<i class="fas fa-heart"></i>';
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
