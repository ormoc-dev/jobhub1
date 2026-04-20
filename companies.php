<?php
include 'config.php';

// Get companies
$search = $_GET['search'] ?? '';
$showOnlyFeatured = isset($_GET['featured']) && $_GET['featured'] == '1';

$whereClause = "WHERE c.status = 'active'";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND c.company_name LIKE ?";
    $params[] = "%$search%";
}

if ($showOnlyFeatured) {
    $whereClause .= " AND c.featured = 'yes' AND (c.featured_until IS NULL OR c.featured_until >= CURDATE())";
}

$sql = "SELECT c.*, COUNT(jp.id) as job_count,
               CASE WHEN c.featured = 'yes' AND (c.featured_until IS NULL OR c.featured_until >= CURDATE()) THEN 1 ELSE 0 END as is_featured
        FROM companies c 
        LEFT JOIN job_postings jp ON c.id = jp.company_id AND jp.status = 'active' 
        $whereClause 
        GROUP BY c.id 
        ORDER BY is_featured DESC, c.company_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies - WORKLINK</title>
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
        <div class="text-center mb-5">
            <h1 class="display-4 fw-bold">
                <?php if ($showOnlyFeatured): ?>
                    <i class="fas fa-star text-warning me-2"></i>
                    Featured Companies
                <?php else: ?>
                    Our Partner Companies
                <?php endif; ?>
            </h1>
            <p class="lead text-secondary">
                <?php if ($showOnlyFeatured): ?>
                    Discover our premium featured partners and their exciting opportunities
                <?php else: ?>
                    Discover great companies and exciting career opportunities
                <?php endif; ?>
            </p>
            <?php if ($showOnlyFeatured): ?>
                <a href="companies.php" class="btn btn-outline-accent">
                    <i class="fas fa-arrow-left me-1"></i>
                    View All Companies
                </a>
            <?php endif; ?>
        </div>

        <!-- Search -->
        <div class="row justify-content-center mb-5">
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-2" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search companies...">
                    <button type="submit" class="btn btn-accent">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Featured Companies Section -->
        <?php 
        if (!$showOnlyFeatured) {
            $featuredCompanies = array_filter($companies, function($company) {
                return $company['is_featured'] == 1;
            });
            if (!empty($featuredCompanies)): ?>
                <div class="mb-5">
                    <div class="d-flex align-items-center mb-4">
                        <i class="fas fa-star text-warning me-2"></i>
                        <h2 class="h3 mb-0">Featured Companies</h2>
                        <span class="badge bg-warning text-dark ms-2"><?php echo count($featuredCompanies); ?></span>
                    </div>
                    <div class="row g-4 mb-5">
                        <?php foreach (array_slice($featuredCompanies, 0, 3) as $company): ?>
                            <div class="col-md-4">
                                <div class="card featured-company-card border-warning h-100">
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-star me-1"></i>Featured
                                        </span>
                                    </div>
                                    <div class="card-body text-center">
                                        <?php if (!empty($company['company_logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($company['company_logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($company['company_name']); ?>" 
                                                 class="rounded-circle mb-3" 
                                                 style="width: 80px; height: 80px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                                 style="width: 80px; height: 80px; font-size: 2rem;">
                                                <i class="fas fa-building"></i>
                                            </div>
                                        <?php endif; ?>
                                        <h5 class="card-title"><?php echo htmlspecialchars($company['company_name']); ?></h5>
                                        <p class="card-text text-secondary">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($company['location_address']); ?>
                                        </p>
                                        <div class="d-flex justify-content-center gap-2 mb-3">
                                            <span class="badge badge-accent"><?php echo $company['job_count']; ?> Jobs</span>
                                        </div>
                                        <a href="company-profile.php?id=<?php echo $company['id']; ?>" class="btn btn-outline-warning">
                                            <i class="fas fa-eye me-1"></i>View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($featuredCompanies) > 3): ?>
                        <div class="text-center mb-4">
                            <button class="btn btn-outline-warning" onclick="showAllFeatured()">
                                <i class="fas fa-plus me-1"></i>
                                View All Featured Companies (<?php echo count($featuredCompanies) - 3; ?> more)
                            </button>
                        </div>
                    <?php endif; ?>
                    <hr class="my-5">
                </div>
            <?php endif;
        } ?>

        <!-- Companies Grid -->
        <?php if (empty($companies)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building fa-3x text-secondary mb-3"></i>
                <h4>No companies found</h4>
                <p class="text-secondary">Try adjusting your search or check back later.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($companies as $company): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card dashboard-card h-100 <?php echo $company['is_featured'] ? 'border-info' : ''; ?> position-relative">
                            <?php if ($company['is_featured']): ?>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-info">
                                        <i class="fas fa-star me-1"></i>Featured
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="card-body text-center<?php echo $company['is_featured'] ? ' pt-5' : ''; ?>">
                                <div class="mb-3">
                                    <?php if ($company['company_logo']): ?>
                                        <img src="<?php echo $company['company_logo']; ?>" alt="<?php echo htmlspecialchars($company['company_name']); ?>" 
                                             class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto" style="width: 80px; height: 80px;">
                                            <i class="fas fa-building fa-2x text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($company['company_name']); ?></h5>
                                <p class="text-secondary">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?php echo htmlspecialchars($company['location_address']); ?>
                                </p>
                                <?php if ($company['description']): ?>
                                    <p class="card-text"><?php echo substr(htmlspecialchars($company['description']), 0, 100) . '...'; ?></p>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <span class="badge badge-accent">
                                        <i class="fas fa-briefcase me-1"></i>
                                        <?php echo $company['job_count']; ?> open position<?php echo $company['job_count'] !== 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                                <div class="d-flex flex-wrap gap-2 justify-content-center">
                                    <a href="company-details.php?id=<?php echo $company['id']; ?>" class="btn btn-outline-accent btn-sm">
                                        <i class="fas fa-info-circle me-1"></i>View Details
                                    </a>
                                    <?php if ($company['job_count'] > 0): ?>
                                        <a href="jobs.php?company=<?php echo $company['id']; ?>" class="btn btn-accent btn-sm">
                                            <i class="fas fa-briefcase me-1"></i>View Jobs
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
            border-color: var(--wl-accent);
            color: var(--wl-accent);
        }

        .btn-outline-accent:hover {
            background: var(--wl-accent);
            border-color: var(--wl-accent);
            color: #ffffff;
        }

        .badge-accent {
            background: var(--wl-accent);
            color: #ffffff;
        }
    </style>
    <script>
        function showAllFeatured() {
            const url = new URL(window.location.href);
            url.searchParams.set('featured', '1');
            window.location.href = url.toString();
        }
    </script>
</body>
</html>
