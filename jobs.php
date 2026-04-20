<?php
include 'config.php';

// Clean up expired jobs with no applications
cleanupExpiredJobs();

// Get search parameters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$location = trim($_GET['location'] ?? '');
$job_type = trim($_GET['job_type'] ?? '');
$employment_type = trim($_GET['employment_type'] ?? '');

// Build query - exclude expired jobs (deadline passed)
$whereClause = "WHERE jp.status = 'active' AND (jp.deadline IS NULL OR jp.deadline >= CURDATE())";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (jp.title LIKE ? OR jp.description LIKE ? OR c.company_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($category)) {
    $whereClause .= " AND jc.category_name = ?";
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

// Get employee profile for saved jobs check
$employee_id = null;
if (isLoggedIn() && getUserRole() === 'employee') {
    $stmt = $pdo->prepare("SELECT id FROM employee_profiles WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $emp = $stmt->fetch();
    if ($emp) {
        $employee_id = $emp['id'];
    }
}

// Get jobs
$sql = "SELECT jp.*, c.company_name, c.company_logo, jc.category_name,
               (SELECT COUNT(*) FROM job_applications WHERE job_id = jp.id) as application_count" .
        ($employee_id ? ", (SELECT COUNT(*) FROM saved_jobs WHERE job_id = jp.id AND employee_id = $employee_id) as is_saved" : ", 0 as is_saved") . "
        FROM job_postings jp 
        JOIN companies c ON jp.company_id = c.id 
        LEFT JOIN job_categories jc ON jp.category_id = jc.id 
        $whereClause 
        ORDER BY jp.posted_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Get categories for filter
$categories = $pdo->query("SELECT * FROM job_categories WHERE status = 'active' ORDER BY category_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse All Jobs - WORKLINK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body class="wl-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-glass">
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="companies.php">Companies</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="jobs.php">Available Jobs</a>
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
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if (getUserRole() === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">Dashboard</a></li>
                                <?php elseif (getUserRole() === 'employee'): ?>
                                    <li><a class="dropdown-item" href="employee/dashboard.php">Dashboard</a></li>
                                <?php elseif (getUserRole() === 'employer'): ?>
                                    <li><a class="dropdown-item" href="employer/dashboard.php">Dashboard</a></li>
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
                            <a class="nav-link btn btn-auth-signup ms-2 px-3" href="register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-3">
                <!-- Search Filters -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Jobs</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET">
                            <div class="mb-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Job title, company...">
                            </div>
                            
                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-control" name="category">
                                    <option value="">All Categories</option>
                                    <option value="Administrative / Office" <?php echo $category == 'Administrative / Office' ? 'selected' : ''; ?>>🗂️ Administrative / Office</option>
                                    <option value="Customer Service / BPO" <?php echo $category == 'Customer Service / BPO' ? 'selected' : ''; ?>>☎️ Customer Service / BPO</option>
                                    <option value="Education" <?php echo $category == 'Education' ? 'selected' : ''; ?>>🎓 Education</option>
                                    <option value="Engineering" <?php echo $category == 'Engineering' ? 'selected' : ''; ?>>⚙️ Engineering</option>
                                    <option value="Information Technology (IT)" <?php echo $category == 'Information Technology (IT)' ? 'selected' : ''; ?>>💻 Information Technology (IT)</option>
                                    <option value="Finance / Accounting" <?php echo $category == 'Finance / Accounting' ? 'selected' : ''; ?>>💰 Finance / Accounting</option>
                                    <option value="Healthcare / Medical" <?php echo $category == 'Healthcare / Medical' ? 'selected' : ''; ?>>🏥 Healthcare / Medical</option>
                                    <option value="Human Resources (HR)" <?php echo $category == 'Human Resources (HR)' ? 'selected' : ''; ?>>👥 Human Resources (HR)</option>
                                    <option value="Manufacturing / Production" <?php echo $category == 'Manufacturing / Production' ? 'selected' : ''; ?>>🏭 Manufacturing / Production</option>
                                    <option value="Logistics / Warehouse / Supply Chain" <?php echo $category == 'Logistics / Warehouse / Supply Chain' ? 'selected' : ''; ?>>🚚 Logistics / Warehouse / Supply Chain</option>
                                    <option value="Marketing / Sales" <?php echo $category == 'Marketing / Sales' ? 'selected' : ''; ?>>📈 Marketing / Sales</option>
                                    <option value="Creative / Media / Design" <?php echo $category == 'Creative / Media / Design' ? 'selected' : ''; ?>>🎨 Creative / Media / Design</option>
                                    <option value="Construction / Infrastructure" <?php echo $category == 'Construction / Infrastructure' ? 'selected' : ''; ?>>🏗️ Construction / Infrastructure</option>
                                    <option value="Food / Hospitality / Tourism (including Fast-Food Chains)" <?php echo $category == 'Food / Hospitality / Tourism (including Fast-Food Chains)' ? 'selected' : ''; ?>>🍽️ Food / Hospitality / Tourism (including Fast-Food Chains)</option>
                                    <option value="Retail / Sales Operations" <?php echo $category == 'Retail / Sales Operations' ? 'selected' : ''; ?>>🛒 Retail / Sales Operations</option>
                                    <option value="Transportation" <?php echo $category == 'Transportation' ? 'selected' : ''; ?>>🚗 Transportation</option>
                                    <option value="Law Enforcement / Criminology" <?php echo $category == 'Law Enforcement / Criminology' ? 'selected' : ''; ?>>👮 Law Enforcement / Criminology</option>
                                    <option value="Security Services" <?php echo $category == 'Security Services' ? 'selected' : ''; ?>>🛡️ Security Services</option>
                                    <option value="Skilled / Technical (TESDA)" <?php echo $category == 'Skilled / Technical (TESDA)' ? 'selected' : ''; ?>>🔧 Skilled / Technical (TESDA)</option>
                                    <option value="Agriculture / Fisheries" <?php echo $category == 'Agriculture / Fisheries' ? 'selected' : ''; ?>>🌾 Agriculture / Fisheries</option>
                                    <option value="Freelance / Online / Remote" <?php echo $category == 'Freelance / Online / Remote' ? 'selected' : ''; ?>>🌐 Freelance / Online / Remote</option>
                                    <option value="Legal / Government / Public Service" <?php echo $category == 'Legal / Government / Public Service' ? 'selected' : ''; ?>>⚖️ Legal / Government / Public Service</option>
                                    <option value="Maritime / Aviation / Transport Specialized" <?php echo $category == 'Maritime / Aviation / Transport Specialized' ? 'selected' : ''; ?>>✈️ Maritime / Aviation / Transport Specialized</option>
                                    <option value="Science / Research / Environment" <?php echo $category == 'Science / Research / Environment' ? 'selected' : ''; ?>>🔬 Science / Research / Environment</option>
                                    <option value="Arts / Entertainment / Culture" <?php echo $category == 'Arts / Entertainment / Culture' ? 'selected' : ''; ?>>🎭 Arts / Entertainment / Culture</option>
                                    <option value="Religion / NGO / Development / Cooperative" <?php echo $category == 'Religion / NGO / Development / Cooperative' ? 'selected' : ''; ?>>✝️ Religion / NGO / Development / Cooperative</option>
                                    <option value="Special / Rare Jobs" <?php echo $category == 'Special / Rare Jobs' ? 'selected' : ''; ?>>🧩 Special / Rare Jobs</option>
                                    <option value="Utilities / Public Services" <?php echo $category == 'Utilities / Public Services' ? 'selected' : ''; ?>>🔌 Utilities / Public Services</option>
                                    <option value="Telecommunications" <?php echo $category == 'Telecommunications' ? 'selected' : ''; ?>>📡 Telecommunications</option>
                                    <option value="Mining / Geology" <?php echo $category == 'Mining / Geology' ? 'selected' : ''; ?>>⛏️ Mining / Geology</option>
                                    <option value="Oil / Gas / Energy" <?php echo $category == 'Oil / Gas / Energy' ? 'selected' : ''; ?>>🛢️ Oil / Gas / Energy</option>
                                    <option value="Chemical / Industrial" <?php echo $category == 'Chemical / Industrial' ? 'selected' : ''; ?>>⚗️ Chemical / Industrial</option>
                                    <option value="Allied Health / Special Education / Therapy" <?php echo $category == 'Allied Health / Special Education / Therapy' ? 'selected' : ''; ?>>🩺 Allied Health / Special Education / Therapy</option>
                                    <option value="Sports / Fitness / Recreation" <?php echo $category == 'Sports / Fitness / Recreation' ? 'selected' : ''; ?>>🏋️ Sports / Fitness / Recreation</option>
                                    <option value="Fashion / Apparel / Beauty" <?php echo $category == 'Fashion / Apparel / Beauty' ? 'selected' : ''; ?>>👗 Fashion / Apparel / Beauty</option>
                                    <option value="Home / Personal Services" <?php echo $category == 'Home / Personal Services' ? 'selected' : ''; ?>>🏡 Home / Personal Services</option>
                                    <option value="Insurance / Risk / Banking" <?php echo $category == 'Insurance / Risk / Banking' ? 'selected' : ''; ?>>🏦 Insurance / Risk / Banking</option>
                                    <option value="Micro Jobs / Informal / Daily Wage Jobs" <?php echo $category == 'Micro Jobs / Informal / Daily Wage Jobs' ? 'selected' : ''; ?>>💼 Micro Jobs / Informal / Daily Wage Jobs</option>
                                    <option value="Real Estate / Property" <?php echo $category == 'Real Estate / Property' ? 'selected' : ''; ?>>🏠 Real Estate / Property</option>
                                    <option value="Entrepreneurship / Business / Corporate" <?php echo $category == 'Entrepreneurship / Business / Corporate' ? 'selected' : ''; ?>>📊 Entrepreneurship / Business / Corporate</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="City, Province...">
                            </div>
                            
                            <div class="mb-3">
                                <label for="job_type" class="form-label">Job Type</label>
                                <select class="form-control" name="job_type" id="job_type">
                                    <option value="">All Job Types</option>
                                    <option value="Full Time" <?php echo ($job_type === 'Full Time') ? 'selected' : ''; ?>>Full Time</option>
                                    <option value="Part Time" <?php echo ($job_type === 'Part Time') ? 'selected' : ''; ?>>Part Time</option>
                                    <option value="Freelance" <?php echo ($job_type === 'Freelance') ? 'selected' : ''; ?>>Freelance</option>
                                    <option value="Internship" <?php echo ($job_type === 'Internship') ? 'selected' : ''; ?>>Internship</option>
                                    <option value="Contract-Based" <?php echo ($job_type === 'Contract-Based') ? 'selected' : ''; ?>>Contract-Based</option>
                                    <option value="Temporary" <?php echo ($job_type === 'Temporary') ? 'selected' : ''; ?>>Temporary</option>
                                    <option value="Work From Home" <?php echo ($job_type === 'Work From Home') ? 'selected' : ''; ?>>Work From Home</option>
                                    <option value="On-Site" <?php echo ($job_type === 'On-Site') ? 'selected' : ''; ?>>On-Site</option>
                                    <option value="Hybrid" <?php echo ($job_type === 'Hybrid') ? 'selected' : ''; ?>>Hybrid</option>
                                    <option value="Seasonal" <?php echo ($job_type === 'Seasonal') ? 'selected' : ''; ?>>Seasonal</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-accent w-100">
                                <i class="fas fa-search me-2"></i>Search Jobs
                            </button>
                            <a href="jobs.php" class="btn btn-outline-accent w-100 mt-2">
                                <i class="fas fa-times me-2"></i>Clear Filters
                            </a>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0">Job Opportunities</h1>
                    <span class="text-secondary"><?php echo count($jobs); ?> jobs found</span>
                </div>

                <?php if (empty($jobs)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-secondary mb-3"></i>
                        <h4>No jobs found</h4>
                        <p class="text-secondary">Try adjusting your search criteria or check back later for new opportunities.</p>
                        <a href="jobs.php" class="btn btn-accent">View All Jobs</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($jobs as $job): ?>
                            <div class="col-12">
                                <div class="card job-card">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-start">
                                                    <div class="me-3">
                                                        <?php if ($job['company_logo']): ?>
                                                            <img src="<?php echo $job['company_logo']; ?>" alt="Company Logo" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                                <i class="fas fa-building text-secondary"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h5 class="job-title mb-1">
                                                            <a href="job-details.php?id=<?php echo $job['id']; ?>">
                                                                <?php echo htmlspecialchars($job['title']); ?>
                                                            </a>
                                                        </h5>
                                                        <p class="company-name mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
                                                        <div class="job-meta">
                                                            <span class="me-3">
                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                <?php echo htmlspecialchars($job['location']); ?>
                                                            </span>
                                                            <span class="me-3">
                                                                <i class="fas fa-briefcase me-1"></i>
                                                                <?php echo htmlspecialchars($job['job_type'] ?? 'Full Time'); ?>
                                                            </span>
                                                            <span class="me-3">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo htmlspecialchars($job['employment_type']); ?>
                                                            </span>
                                                            <span class="me-3">
                                                                <i class="fas fa-users me-1"></i>
                                                                <?php echo htmlspecialchars($job['employees_required']); ?>
                                                            </span>
                                                            <?php if ($job['salary_range']): ?>
                                                                <span class="me-3">
                                                                    <i class="fas fa-money-bill-wave me-1"></i>
                                                                    <?php echo htmlspecialchars($job['salary_range']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-secondary mt-2 mb-0">
                                                            <?php echo substr(strip_tags($job['description']), 0, 150) . '...'; ?>
                                                        </p>
                                                        <?php if (!empty($job['qualification'])): ?>
                                                            <div class="mt-2">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-certificate me-1"></i>
                                                                    <strong>Qualifications:</strong> <?php echo html_entity_decode(substr(strip_tags($job['qualification']), 0, 100), ENT_QUOTES, 'UTF-8') . '...'; ?>
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <div class="mb-2">
                                                    <?php if ($job['job_type']): ?>
                                                        <span class="badge" style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white;">
                                                            <i class="fas fa-briefcase me-1"></i><?php echo htmlspecialchars($job['job_type']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($job['category_name']): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($job['category_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mb-2">
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($job['experience_level']); ?></span>
                                                </div>
                                                <div class="mb-2">
                                                    <small class="text-secondary">
                                                        Posted <?php echo date('M j, Y', strtotime($job['posted_date'])); ?>
                                                        <?php if (isset($job['application_count'])): ?>
                                                            <br>
                                                            <span class="text-muted">
                                                                <i class="fas fa-users me-1"></i><?php echo $job['application_count']; ?> application<?php echo $job['application_count'] != 1 ? 's' : ''; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                                <div class="d-flex flex-column gap-2">
                                                    <?php if (isLoggedIn() && getUserRole() === 'employee'): ?>
                                                        <a href="job-details.php?id=<?php echo $job['id']; ?>#apply" class="btn btn-sm btn-accent">
                                                            <i class="fas fa-paper-plane me-1"></i>APPLY NOW
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="login.php" class="btn btn-sm btn-accent">
                                                            <i class="fas fa-paper-plane me-1"></i>APPLY NOW
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="job-details.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-outline-accent">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </a>
                                                    <?php if (isLoggedIn() && getUserRole() === 'employee'): ?>
                                                        <button class="btn btn-sm <?php echo $job['is_saved'] ? 'btn-warning' : 'btn-outline-warning'; ?> save-job" data-job-id="<?php echo $job['id']; ?>">
                                                            <i class="<?php echo $job['is_saved'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-briefcase me-2"></i>WORKLINK</h5>
                    <p class="text-secondary">Connecting talent with opportunity since 2025.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-secondary mb-0">&copy; 2025 WORKLINK. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --wl-primary: #1e3a8a;
            --wl-primary-dark: #172554;
            --wl-accent: #14b8a6;
            --wl-ink: #0f172a;
            --wl-soft: #f8fafc;
            --wl-card: #ffffff;
        }

        body.wl-theme {
            background: #f3f5f7;
            color: var(--wl-ink);
        }

        body.wl-theme {
            --primary-color: var(--wl-primary);
            --secondary-color: #64748b;
        }

        .navbar-glass {
            background: linear-gradient(135deg, rgba(30, 58, 138, 0.95) 0%, rgba(23, 37, 84, 0.95) 100%);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
            backdrop-filter: blur(8px);
        }

        .navbar-glass .nav-link {
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .text-accent {
            color: var(--wl-accent) !important;
        }

        .btn-accent {
            background: var(--wl-accent);
            border-color: var(--wl-accent);
            color: #ffffff;
            box-shadow: 0 12px 24px rgba(20, 184, 166, 0.3);
        }

        .btn-accent:hover {
            background: #0f766e;
            border-color: #0f766e;
            color: #ffffff;
        }

        .btn-outline-accent {
            border: 2px solid var(--wl-accent);
            color: var(--wl-accent);
            background: transparent;
        }

        .btn-outline-accent:hover {
            background: var(--wl-accent);
            border-color: var(--wl-accent);
            color: #ffffff;
        }
    </style>
    <script>
        // Save job functionality
        document.querySelectorAll('.save-job').forEach(button => {
            button.addEventListener('click', function() {
                const jobId = this.dataset.jobId;
                const icon = this.querySelector('i');
                
                fetch('employee/save-job.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'job_id=' + jobId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.saved) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            this.classList.remove('btn-outline-warning');
                            this.classList.add('btn-warning');
                        } else {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            this.classList.remove('btn-warning');
                            this.classList.add('btn-outline-warning');
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
